<?php

/**
 * Kernel-Web — Retention Cleanup
 *
 * Deletes historical rows older than the configured retention thresholds.
 * Safe to run repeatedly (idempotent).
 *
 * Retention thresholds are read from config/local.php under the 'cleanup' key:
 *
 *   cleanup.retention.old_sessions_days      (default: 30)
 *   cleanup.retention.expired_tokens_days   (default: 30)
 *   cleanup.retention.audit_log_days        (default: 90)
 *   cleanup.retention.temp_files_days       (default: 7)
 *
 * Tables cleaned:
 *   - api_tokens   (cutoff: expires_at)
 *   - sessions     (cutoff: last_activity)
 *   - audit_logs   (cutoff: created_at)
 *
 * Files cleaned:
 *   - storage/tmp/*  (cutoff: modified time)
 *
 * Usage:
 *   php scripts/cleanup.php
 *
 * Schedule with cron (recommended: once daily):
 *   0 3 * * * php /path/to/scripts/cleanup.php >> /path/to/storage/logs/cleanup.log 2>&1
 *
 * Exit codes:
 *   0 — success
 *   1 — fatal error
 */

declare(strict_types=1);

// ------ autoloader ------
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

// ------ bootstrap ------
use App\Core\Config;
use App\Core\Env;
use App\Core\SQLiteDriver;

Env::load(__DIR__ . '/../.env');

$dbConfig = Config::load('database');

if ($dbConfig['driver'] === 'sqlite') {
    $db = new SQLiteDriver($dbConfig['sqlite']['path']);
} else {
    fwrite(STDERR, "Unsupported database driver: {$dbConfig['driver']}\n");
    exit(1);
}

// ------ retention config ------
$cleanupConfig = Config::load('cleanup');
$retention     = $cleanupConfig['retention'] ?? [];

$sessionsDays      = (int) ($retention['old_sessions_days']      ?? 30);
$tokensDays        = (int) ($retention['expired_tokens_days']    ?? 30);
$auditLogDays      = (int) ($retention['audit_log_days']         ?? 90);
$tempFilesDays     = (int) ($retention['temp_files_days']        ?? 7);

if ($sessionsDays < 1 || $tokensDays < 1 || $auditLogDays < 1 || $tempFilesDays < 1) {
    fwrite(STDERR, "Retention days must be >= 1. Check your cleanup.retention config.\n");
    exit(1);
}

$now    = new \DateTime('now');
$cutoff = function (int $days) use ($now) {
    return (clone $now)->modify("-{$days} days")->format('Y-m-d H:i:s');
};

// ------ run cleanup ------
$deletedSessions     = 0;
$deletedTokens       = 0;
$deletedAuditLog     = 0;
$deletedTempFiles    = 0;

try {
    // Expired API tokens (delete rows where expires_at < now)
    $deletedTokens = (int) $db->execute(
        'DELETE FROM api_tokens WHERE expires_at IS NOT NULL AND expires_at < ?',
        [$now->format('Y-m-d H:i:s')]
    );

    // Old sessions
    $deletedSessions = (int) $db->execute(
        'DELETE FROM sessions WHERE last_activity < ?',
        [$cutoff($sessionsDays)]
    );

    // Old audit logs
    $deletedAuditLog = (int) $db->execute(
        'DELETE FROM audit_logs WHERE created_at < ?',
        [$cutoff($auditLogDays)]
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "Database cleanup failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Old temp files
$tmpDir = __DIR__ . '/../storage/tmp';
if (is_dir($tmpDir)) {
    $count = 0;
    foreach (new \DirectoryIterator($tmpDir) as $file) {
        if ($file->isDot()) {
            continue;
        }
        if ((time() - $file->getMTime()) > ($tempFilesDays * 86400)) {
            @unlink($file->getPathname());
            $count++;
        }
    }
    $deletedTempFiles = $count;
}

// ------ summary ------
echo "\n── Kernel-Web Cleanup Summary\n";
echo "Expired tokens deleted: {$deletedTokens}\n";
echo "Old sessions deleted:   {$deletedSessions}\n";
echo "Old audit logs deleted: {$deletedAuditLog}\n";
echo "Temp files deleted:     {$deletedTempFiles}\n";

exit(0);
