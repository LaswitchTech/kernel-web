<?php

/**
 * Kernel-Web Task Scheduler
 *
 * Finds all active cron-assigned tasks and dispatches them by running their
 * mapped system script. Intended to be run every minute by the host cron daemon.
 *
 * Safety model:
 *   - Only tasks with assigned_type='cron' and a non-null execution_type are processed.
 *   - execution_type values are validated against SCRIPT_MAP before any execution.
 *     Types not present in the map are logged and skipped — never executed.
 *   - Scripts are run via proc_open() with an array command: no shell string,
 *     no interpolation, no user-supplied data on the command line.
 *   - The resolved script path is checked with realpath() and must be within
 *     the scripts/ directory; a path-traversal check aborts the run if violated.
 *
 * Execution tracking:
 *   Every attempt updates the task row:
 *     last_run_at      — timestamp of this run
 *     last_run_status  — 'ok' (exit 0) or 'failed' (non-zero exit)
 *     last_run_message — first MAX_MESSAGE_BYTES of combined stdout+stderr
 *
 * Audit events emitted:
 *   task.execute           — scheduler started a task execution
 *   task.execute_completed — script finished with exit code 0
 *   task.execute_failed    — script finished with non-zero exit code, or could not start
 *
 * Usage:
 *   php scripts/scheduler.php            # run one scheduler pass
 *   php scripts/scheduler.php --verbose  # per-task detail
 *   php scripts/scheduler.php --dry-run  # report without executing or writing to DB
 *
 * Host cron example (run every minute):
 *   * * * * * php /path/to/scripts/scheduler.php >> /path/to/storage/logs/scheduler.log 2>&1
 *
 * Note: no per-task timeout is enforced in this phase — a long-running script
 * will block subsequent tasks in the same pass.  Timeout enforcement is deferred.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Script whitelist — the ONLY execution_type values that will ever run.
// Keys must match TaskService::EXECUTION_TYPES exactly.
// ---------------------------------------------------------------------------
const SCRIPT_MAP = [
    'monitor.run'                  => 'monitor.php',
    'discover.run'                 => 'discover.php',
    'cleanup.run'                  => 'cleanup.php',
    'task-reminders.run'           => 'task-reminders.php',
    'notify.run'                   => 'notify.php',
    'topology-candidates.generate' => 'generate-topology-candidates.php',
];

// Maximum bytes of output captured into last_run_message.
const MAX_MESSAGE_BYTES = 2000;

// ---------------------------------------------------------------------------
// Autoloader
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base   = __DIR__ . '/../app/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
use App\Core\Config;
use App\Core\Env;
use App\Core\SQLiteDriver;
use App\Models\AuditLogRepository;
use App\Modules\Tasks\Models\TaskRepository;
use App\Modules\Tasks\Services\TaskService;

Env::load(__DIR__ . '/../.env');

$dbConfig = Config::load('database');

if ($dbConfig['driver'] === 'sqlite') {
    $db = new SQLiteDriver($dbConfig['sqlite']['path']);
} else {
    fwrite(STDERR, "Unsupported database driver: {$dbConfig['driver']}\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Parse flags
// ---------------------------------------------------------------------------
$args    = array_slice($argv, 1);
$verbose = in_array('--verbose', $args, true) || in_array('-v', $args, true);
$dryRun  = in_array('--dry-run', $args, true);

// ---------------------------------------------------------------------------
// Services
// ---------------------------------------------------------------------------
$taskRepo   = new TaskRepository($db);
$taskService = new TaskService($taskRepo);
$auditRepo  = new AuditLogRepository($db);

// ---------------------------------------------------------------------------
// Find runnable tasks
// ---------------------------------------------------------------------------
$now       = date('Y-m-d H:i:s');
$timestamp = $now;

echo "NetMon Task Scheduler — {$timestamp}\n";
echo str_repeat('-', 50) . "\n";

if ($dryRun) {
    echo "[DRY RUN] No scripts will be executed and no DB writes will occur.\n\n";
}

$tasks = $taskService->getCronRunnable();
$count = count($tasks);

echo "Runnable cron tasks: {$count}\n\n";

if ($count === 0) {
    echo "Nothing to run.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Dispatch loop
// ---------------------------------------------------------------------------
$scriptsDir      = realpath(__DIR__);
$countDispatched = 0;
$countFailed     = 0;

foreach ($tasks as $task) {
    $taskId        = (int) $task['id'];
    $taskTitle     = (string) $task['title'];
    $executionType = (string) $task['execution_type'];

    // Validate execution_type against whitelist.
    if (!array_key_exists($executionType, SCRIPT_MAP)) {
        $msg = "Unknown execution_type '{$executionType}' — skipping (not in whitelist).";
        echo "  [SKIP] #{$taskId}: {$taskTitle} — {$msg}\n";

        if (!$dryRun) {
            $taskService->recordExecution($taskId, 'failed', $msg);
            auditLog($auditRepo, 'task.execute_failed', $taskId, [
                'execution_type' => $executionType,
                'reason'         => $msg,
            ]);
        }
        $countFailed++;
        continue;
    }

    // Resolve and safety-check the script path.
    $scriptFile = SCRIPT_MAP[$executionType];
    $scriptPath = realpath($scriptsDir . '/' . $scriptFile);

    if ($scriptPath === false || strncmp($scriptPath, $scriptsDir . '/', strlen($scriptsDir) + 1) !== 0) {
        $msg = "Resolved path for '{$executionType}' is outside the scripts directory — aborting.";
        echo "  [SKIP] #{$taskId}: {$taskTitle} — {$msg}\n";

        if (!$dryRun) {
            $taskService->recordExecution($taskId, 'failed', $msg);
            auditLog($auditRepo, 'task.execute_failed', $taskId, [
                'execution_type' => $executionType,
                'reason'         => $msg,
            ]);
        }
        $countFailed++;
        continue;
    }

    // Check whether the task's schedule permits execution right now.
    if (!shouldRunTask($task, $now)) {
        if ($verbose) {
            $schType = $task['schedule_type'] ?? 'always (null)';
            $schVal  = isset($task['schedule_value']) ? " ({$task['schedule_value']})" : '';
            $lastRun = $task['last_run_at'] ?? 'never';
            echo "  [SCHED-SKIP] #{$taskId}: \"{$taskTitle}\" — {$schType}{$schVal}, last_run={$lastRun}\n";
        }
        continue;
    }

    // Build script-specific CLI arguments from execution_payload.
    //
    // Supported payload keys per execution_type:
    //
    //   topology-candidates.generate:
    //     segment_id  (int, > 0) → --segment=<id>
    //
    // All other execution_types receive no extra arguments.
    // Unknown payload keys and malformed JSON are silently ignored.
    // User-supplied data NEVER reaches the shell as a string — each argument
    // is a discrete array element passed directly to proc_open.
    $scriptArgs = buildScriptArgs($executionType, $task['execution_payload'] ?? null);

    $argSuffix = !empty($scriptArgs) ? '  ' . implode(' ', $scriptArgs) : '';

    if ($verbose) {
        echo "  [run] #{$taskId}: \"{$taskTitle}\" → {$scriptFile}{$argSuffix}\n";
    }

    if ($dryRun) {
        echo "  [dry-run] would execute: php {$scriptFile}{$argSuffix}\n";
        continue;
    }

    // Audit: execution started.
    auditLog($auditRepo, 'task.execute', $taskId, [
        'execution_type' => $executionType,
        'script'         => $scriptFile,
        'args'           => $scriptArgs,
    ]);

    // Execute the script as a subprocess.
    // The command is an array — no shell string, no interpolation.
    $descriptors = [
        0 => ['pipe', 'r'],   // stdin  (closed immediately)
        1 => ['pipe', 'w'],   // stdout
        2 => ['pipe', 'w'],   // stderr
    ];

    $process = proc_open([PHP_BINARY, $scriptPath, ...$scriptArgs], $descriptors, $pipes, $scriptsDir);

    if (!is_resource($process)) {
        $msg = "proc_open() failed — could not start process.";
        $taskService->recordExecution($taskId, 'failed', $msg);
        auditLog($auditRepo, 'task.execute_failed', $taskId, [
            'execution_type' => $executionType,
            'reason'         => $msg,
        ]);
        echo "  [FAIL] #{$taskId}: {$taskTitle} — {$msg}\n";
        $countFailed++;
        continue;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    // Combine output; cap at MAX_MESSAGE_BYTES.
    $combined = trim(($stdout ?? '') . "\n" . ($stderr ?? ''));
    $combined = $combined !== '' ? $combined : null;
    if ($combined !== null && strlen($combined) > MAX_MESSAGE_BYTES) {
        $combined = substr($combined, 0, MAX_MESSAGE_BYTES) . '… [truncated]';
    }

    $runStatus = ($exitCode === 0) ? 'ok' : 'failed';
    $taskService->recordExecution($taskId, $runStatus, $combined);

    if ($exitCode === 0) {
        auditLog($auditRepo, 'task.execute_completed', $taskId, [
            'execution_type' => $executionType,
            'exit_code'      => 0,
        ]);
        $countDispatched++;
        if ($verbose) {
            echo "    → completed (exit 0).\n";
        }
    } else {
        auditLog($auditRepo, 'task.execute_failed', $taskId, [
            'execution_type' => $executionType,
            'exit_code'      => $exitCode,
        ]);
        $countFailed++;
        echo "  [FAIL] #{$taskId}: \"{$taskTitle}\" exited {$exitCode}.\n";
        if ($verbose && $combined !== null) {
            echo "    Output: " . substr($combined, 0, 200) . "\n";
        }
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo str_repeat('-', 50) . "\n";
if ($dryRun) {
    echo "Would execute: {$count} task(s) (dry run — nothing executed).\n";
} else {
    echo "Completed: {$countDispatched}   Failed: {$countFailed}\n";
}

exit($countFailed > 0 ? 1 : 0);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Append an audit log entry.  Fail-silent — never aborts the main flow.
 */
function auditLog(AuditLogRepository $repo, string $action, int $taskId, array $meta = []): void
{
    try {
        $repo->log(null, $action, 'task', $taskId, $meta);
    } catch (\Throwable $e) {
        // Intentionally swallowed.
    }
}

/**
 * Determine whether a cron task should execute on this scheduler tick.
 *
 * schedule_type semantics:
 *
 *   NULL / 'always'
 *     Always returns true — backward-compatible default for tasks that pre-date
 *     the schedule fields or that are explicitly set to run every tick.
 *
 *   'interval'
 *     Returns true when at least schedule_value seconds have elapsed since
 *     last_run_at.  Returns true when last_run_at is NULL (never run).
 *     Returns true if schedule_value is unparseable (fail-open).
 *
 *   'cron'
 *     Returns true when the current minute (derived from $now) matches the
 *     5-field cron expression in schedule_value.
 *     Returns false when schedule_value is absent or invalid.
 *
 * Unknown schedule_type values return true (fail-open, forward-compat).
 *
 * @param array  $task  Row from findRunnableCron() — must include schedule_type,
 *                       schedule_value, last_run_at.
 * @param string $now   Current timestamp as 'Y-m-d H:i:s'.
 */
function shouldRunTask(array $task, string $now): bool
{
    $scheduleType = $task['schedule_type'] ?? null;

    // NULL and 'always' — run every tick.
    if ($scheduleType === null || $scheduleType === 'always') {
        return true;
    }

    if ($scheduleType === 'interval') {
        $intervalSeconds = (int) ($task['schedule_value'] ?? 0);

        if ($intervalSeconds <= 0) {
            return true; // misconfigured — fail-open
        }

        $lastRun = $task['last_run_at'] ?? null;

        if ($lastRun === null) {
            return true; // never run
        }

        $lastRunTime = strtotime($lastRun);
        $nowTime     = strtotime($now);

        if ($lastRunTime === false || $nowTime === false) {
            return true; // unparseable timestamps — fail-open
        }

        return ($nowTime - $lastRunTime) >= $intervalSeconds;
    }

    if ($scheduleType === 'cron') {
        $expr = $task['schedule_value'] ?? '';
        return matchCronExpression($expr, $now);
    }

    // Unknown schedule_type — fail-open for forward compatibility.
    return true;
}

/**
 * Test whether the timestamp $now matches a 5-field cron expression.
 *
 * Field order: minute  hour  dom  month  dow
 *   minute  0–59
 *   hour    0–23
 *   dom     1–31
 *   month   1–12
 *   dow     0–6  (0 = Sunday)
 *
 * Supported per-field syntax: wildcard (*), step (e.g. "every 5" = star-slash-5),
 * or exact integer.  Returns false for malformed expressions.
 *
 * @param string $expr  5-field cron expression (e.g. '0 2 * * *').
 * @param string $now   Timestamp as 'Y-m-d H:i:s'.
 */
function matchCronExpression(string $expr, string $now): bool
{
    if ($expr === '') {
        return false;
    }

    $fields = preg_split('/\s+/', trim($expr));

    if (count($fields) !== 5) {
        return false;
    }

    $t = strtotime($now);

    if ($t === false) {
        return false;
    }

    [$minField, $hourField, $domField, $monField, $dowField] = $fields;

    return matchCronField($minField,  (int) date('i', $t))   // minute  0–59
        && matchCronField($hourField, (int) date('G', $t))   // hour    0–23
        && matchCronField($domField,  (int) date('j', $t))   // dom     1–31
        && matchCronField($monField,  (int) date('n', $t))   // month   1–12
        && matchCronField($dowField,  (int) date('w', $t));  // dow     0–6
}

/**
 * Test whether a single cron field matches $value.
 *
 * Supported syntax:
 *   '*'        — always matches
 *   step expr  — matches when $value % step === 0  (step = integer after the slash)
 *   '<int>'    — matches when (int) $field === $value
 *
 * Returns false for unrecognised syntax.
 */
function matchCronField(string $field, int $value): bool
{
    if ($field === '*') {
        return true;
    }

    if (strncmp($field, '*/', 2) === 0) {
        $step = (int) substr($field, 2);
        return $step > 0 && ($value % $step) === 0;
    }

    if (ctype_digit($field)) {
        return (int) $field === $value;
    }

    return false; // unrecognised syntax
}

/**
 * Derive the extra CLI arguments to append to a script invocation.
 *
 * Arguments are returned as an array of strings — each element becomes a
 * discrete argv entry in proc_open's command array.  No shell string is ever
 * constructed; this completely eliminates shell-injection risk.
 *
 * Supported execution_type / payload mapping:
 *
 *   topology-candidates.generate
 *     payload key  segment_id  (positive integer)
 *     arg produced --segment=<id>
 *
 * All other execution_types return [].
 * Unknown payload keys are silently ignored.
 * Malformed JSON is silently ignored.
 * Non-integer or non-positive segment_id values are silently ignored.
 *
 * @param  string      $executionType
 * @param  string|null $rawPayload    Raw JSON string from tasks.execution_payload
 * @return list<string>               Safe argv fragments
 */
function buildScriptArgs(string $executionType, ?string $rawPayload): array
{
    if ($executionType !== 'topology-candidates.generate') {
        return [];
    }

    if ($rawPayload === null || $rawPayload === '') {
        return [];
    }

    $payload = json_decode($rawPayload, true);

    if (!is_array($payload)) {
        return [];
    }

    $segmentId = $payload['segment_id'] ?? null;

    if (!is_int($segmentId) || $segmentId <= 0) {
        return [];
    }

    return ['--segment=' . $segmentId];
}
