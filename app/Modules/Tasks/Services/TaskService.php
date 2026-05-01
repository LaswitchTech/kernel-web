<?php

namespace App\Modules\Tasks\Services;

use App\Modules\Tasks\Models\TaskRepository;

/**
 * Validation and write orchestration for the Tasks module.
 *
 * Enforces field rules before delegating to TaskRepository.
 * Has no dependency on any NetMon-specific class.
 */
class TaskService
{
    /** Valid status values — matches the DB CHECK constraint comment. */
    public const STATUSES = ['open', 'in_progress', 'completed', 'canceled'];

    /**
     * Valid assigned_type values.
     *
     * NULL (unset) is also valid and means "unassigned".
     *
     *   'user'  — assigned to a human user (assigned_id = users.id)
     *   'agent' — assigned to a future AI/automation agent (assigned_id = agents.id)
     *   'cron'  — reserved for scheduled automation; assigned_id must be NULL
     */
    public const ASSIGNED_TYPES = ['user', 'agent', 'cron'];

    /**
     * Valid execution_type values — the explicit whitelist for cron task dispatch.
     *
     * NULL (unset) means no automated execution is configured.
     * Each value maps to a concrete system script in scripts/scheduler.php's
     * SCRIPT_MAP constant.  No value outside this list may be stored via the
     * web UI or executed by the scheduler.
     *
     *   'monitor.run'                    — run scripts/monitor.php (device + service monitoring pass)
     *   'discover.run'                   — run scripts/discover.php (network discovery scan)
     *   'cleanup.run'                    — run scripts/cleanup.php (data retention cleanup)
     *   'task-reminders.run'             — run scripts/task-reminders.php (send pending reminders)
     *   'notify.run'                     — run scripts/notify.php (process notification queue)
     *   'topology-candidates.generate'   — run scripts/generate-topology-candidates.php
     *                                      Supports optional payload: {"segment_id": <int>}
     *                                      When segment_id is present the script is invoked with
     *                                      --segment=<id> to restrict generation to one segment.
     */
    public const EXECUTION_TYPES = [
        'monitor.run',
        'discover.run',
        'cleanup.run',
        'task-reminders.run',
        'notify.run',
        'topology-candidates.generate',
    ];

    /**
     * Valid schedule_type values for cron-assigned tasks.
     *
     * NULL (unset) is also valid and is treated as 'always' by the scheduler
     * for backward compatibility with tasks created before this field existed.
     *
     *   'always'   — run on every scheduler tick; schedule_value must be NULL
     *   'interval' — run when at least schedule_value seconds have elapsed
     *                since last_run_at; schedule_value must be a positive integer
     *   'cron'     — run when the current minute matches the 5-field cron
     *                expression in schedule_value
     *                Supported per-field syntax: wildcard (*), step (star-slash-N), exact integer
     *
     * schedule_type and schedule_value may only be set on tasks with
     * assigned_type = 'cron'.
     */
    public const SCHEDULE_TYPES = ['always', 'interval', 'cron'];

    /** Human-readable labels for schedule types (used in the create/edit UI). */
    public const SCHEDULE_TYPE_LABELS = [
        'always'   => 'Always — run on every scheduler tick',
        'interval' => 'Interval — run every N seconds',
        'cron'     => 'Cron expression — run on a cron schedule (e.g. 0 2 * * *)',
    ];

    /**
     * Human-readable labels for execution types (used in the create/edit UI).
     *
     * Keys match EXECUTION_TYPES exactly.
     */
    public const EXECUTION_TYPE_LABELS = [
        'monitor.run'                  => 'Monitor — run monitoring pass',
        'discover.run'                 => 'Discovery — run discovery scan',
        'cleanup.run'                  => 'Cleanup — run data retention cleanup',
        'task-reminders.run'           => 'Task Reminders — send pending reminders',
        'notify.run'                   => 'Notifications — process notification queue',
        'topology-candidates.generate' => 'Topology — generate candidates from segments',
    ];

    /**
     * Human-readable labels for assignment types exposed in the web UI.
     *
     * 'agent' is intentionally omitted — not yet implemented.
     * Used to build the assignment-type select in create/edit forms.
     */
    public const ASSIGNED_TYPE_LABELS = [
        'user' => 'User',
        'cron' => 'System / Scheduler',
    ];

    /**
     * Allow-listed entity types for polymorphic task linkage.
     *
     * Tasks may be linked to any of these entity types.  The list is explicit
     * and validated at the service layer — no arbitrary entity_type strings
     * may be stored via the web UI.
     *
     * Supported values:
     *   'device'  — a device record (/devices/{id})
     *   'alert'   — an alert record (/alerts/{id})
     *   'finding' — a discovery finding (/discovery/{id})
     */
    public const ENTITY_TYPES = ['device', 'alert', 'finding'];

    /** Human-readable labels for display. */
    public const STATUS_LABELS = [
        'open'        => 'Open',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'canceled'    => 'Canceled',
    ];

    public const MAX_TITLE_LENGTH       = 255;
    public const MAX_DESCRIPTION_LENGTH = 10000;

    private TaskRepository $repo;

    public function __construct(TaskRepository $repo)
    {
        $this->repo = $repo;
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    public function getAll(): array
    {
        return $this->repo->findAll();
    }

    public function getByEntity(string $entityType, int $entityId): array
    {
        return $this->repo->findByEntity($entityType, $entityId);
    }

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Validate input and create a task.
     *
     * @param  array $data  Raw input (title, description, status, assigned_type, assigned_id,
     *                      due_at, entity_type, entity_id, created_by_user_id)
     * @return int          New task ID
     * @throws \InvalidArgumentException  On validation failure
     */
    public function create(array $data): int
    {
        $data = $this->normalise($data);
        $this->validate($data);

        return $this->repo->create($data);
    }

    // -------------------------------------------------------------------------
    // User-scoped read methods (for My Tasks / task dashboard)
    // -------------------------------------------------------------------------

    /**
     * All active (non-completed, non-canceled) tasks assigned to a user.
     * Sorted: overdue/soonest first, no-due-date last.
     */
    public function getForUser(int $userId): array
    {
        return $this->repo->findForUser($userId);
    }

    /** Active tasks assigned to a user whose due date is in the past. */
    public function getOverdueForUser(int $userId): array
    {
        return $this->repo->findOverdueForUser($userId);
    }

    /** Active tasks assigned to a user whose due date is today. */
    public function getDueTodayForUser(int $userId): array
    {
        return $this->repo->findDueTodayForUser($userId);
    }

    /** Count of open + in_progress tasks assigned to a user. */
    public function countOpenForUser(int $userId): int
    {
        return $this->repo->countOpenForUser($userId);
    }

    /** Count of active tasks assigned to a user whose due date is in the past. */
    public function countOverdueForUser(int $userId): int
    {
        return $this->repo->countOverdueForUser($userId);
    }

    /** Count of active tasks assigned to a user whose due date is today. */
    public function countDueTodayForUser(int $userId): int
    {
        return $this->repo->countDueTodayForUser($userId);
    }

    /** Active tasks with no assignee. */
    public function getUnassigned(): array
    {
        return $this->repo->findUnassigned();
    }

    /** Count of active unassigned tasks. */
    public function countUnassigned(): int
    {
        return $this->repo->countUnassigned();
    }

    // -------------------------------------------------------------------------
    // Cron / scheduler methods
    // -------------------------------------------------------------------------

    /**
     * Return all tasks that the scheduler should run on this pass.
     *
     * A task is "runnable" when:
     *   - assigned_type = 'cron'
     *   - status IN ('open', 'in_progress')
     *   - execution_type IS NOT NULL
     *
     * @return array<int, array>
     */
    public function getCronRunnable(): array
    {
        return $this->repo->findRunnableCron();
    }

    /**
     * Record the outcome of a scheduler execution attempt.
     *
     * @param  int         $id      Task primary key
     * @param  string      $status  'ok' or 'failed'
     * @param  string|null $message Truncated output/error from the execution
     */
    public function recordExecution(int $id, string $status, ?string $message): void
    {
        $this->repo->updateExecution($id, date('Y-m-d H:i:s'), $status, $message);
    }

    /**
     * Soft-delete a task.
     *
     * Sets deleted_at to the current timestamp.  The task is excluded from all
     * default read queries after this call but is not permanently removed.
     *
     * Returns the task array as it existed before deletion so the caller can
     * use entity_type / entity_id for a smart redirect.
     *
     * @param  int $id  Task primary key
     * @return array    The task row before deletion
     * @throws \InvalidArgumentException  If the task is not found
     */
    public function delete(int $id): array
    {
        $task = $this->repo->findById($id);
        if ($task === null) {
            throw new \InvalidArgumentException('Task not found.');
        }

        $this->repo->delete($id, date('Y-m-d H:i:s'));

        return $task;
    }

    /**
     * Validate input and update an existing task.
     *
     * @param  int   $id    Task primary key
     * @param  array $data  Mutable fields (title, description, status, assigned_type, assigned_id, due_at)
     * @throws \InvalidArgumentException  On validation failure or task not found
     */
    public function update(int $id, array $data): void
    {
        $task = $this->repo->findById($id);
        if ($task === null) {
            throw new \InvalidArgumentException('Task not found.');
        }

        $data = $this->normalise($data);
        $this->validate($data);

        $this->repo->update($id, $data);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Trim string fields and coerce empty strings to null where appropriate.
     */
    private function normalise(array $data): array
    {
        $data['title']       = trim($data['title'] ?? '');
        $data['description'] = trim($data['description'] ?? '') ?: null;
        $data['status']      = trim($data['status'] ?? 'open');
        $data['due_at']      = trim($data['due_at'] ?? '') ?: null;

        // Assignment model — coerce empties to null.
        $assignedType = trim($data['assigned_type'] ?? '');
        $data['assigned_type'] = $assignedType !== '' ? $assignedType : null;

        $assignedId = (int) ($data['assigned_id'] ?? 0);
        $data['assigned_id'] = $assignedId > 0 ? $assignedId : null;

        // Execution model — coerce empties to null.
        $executionType = trim($data['execution_type'] ?? '');
        $data['execution_type'] = $executionType !== '' ? $executionType : null;

        $executionPayload = trim($data['execution_payload'] ?? '');
        $data['execution_payload'] = $executionPayload !== '' ? $executionPayload : null;

        // Schedule model — coerce empties to null.
        $scheduleType = trim($data['schedule_type'] ?? '');
        $data['schedule_type'] = $scheduleType !== '' ? $scheduleType : null;

        $scheduleValue = trim($data['schedule_value'] ?? '');
        $data['schedule_value'] = $scheduleValue !== '' ? $scheduleValue : null;

        // Polymorphic target — both fields required or both null.
        $entityType = trim($data['entity_type'] ?? '');
        $entityId   = (int) ($data['entity_id'] ?? 0);
        $data['entity_type'] = $entityType !== '' ? $entityType : null;
        $data['entity_id']   = $entityId > 0 ? $entityId : null;

        return $data;
    }

    /**
     * Validate a 5-field cron expression.
     *
     * Accepted per-field syntax:
     *   *       — any value
     *   step    — every nth value; written as star + slash + n (e.g. every 5 minutes)
     *   integer — exact match
     *
     * Does NOT validate field ranges (e.g. hour 0-23) — range enforcement
     * is left to the scheduler's runtime matching.
     */
    private static function isValidCronExpression(string $expr): bool
    {
        $fields = preg_split('/\s+/', trim($expr));

        if (count($fields) !== 5) {
            return false;
        }

        foreach ($fields as $field) {
            if (!preg_match('/^(\*|\*\/[1-9]\d*|\d+)$/', $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply business-rule validations.
     *
     * @throws \InvalidArgumentException
     */
    private function validate(array $data): void
    {
        if ($data['title'] === '') {
            throw new \InvalidArgumentException('Title is required.');
        }

        if (mb_strlen($data['title']) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException(
                'Title must be ' . self::MAX_TITLE_LENGTH . ' characters or fewer.'
            );
        }

        if ($data['description'] !== null &&
            mb_strlen($data['description']) > self::MAX_DESCRIPTION_LENGTH
        ) {
            throw new \InvalidArgumentException(
                'Description must be ' . number_format(self::MAX_DESCRIPTION_LENGTH) . ' characters or fewer.'
            );
        }

        if (!in_array($data['status'], self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid status value.');
        }

        // Validate entity linkage: if entity_type is set it must be allow-listed,
        // and entity_id must also be present (both-or-neither rule).
        if ($data['entity_type'] !== null) {
            if (!in_array($data['entity_type'], self::ENTITY_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid entity type.');
            }
            if ($data['entity_id'] === null) {
                throw new \InvalidArgumentException('Entity ID is required when entity type is provided.');
            }
        }

        // Validate new assignment model.
        if ($data['assigned_type'] !== null) {
            if (!in_array($data['assigned_type'], self::ASSIGNED_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid assigned_type value.');
            }

            // 'user' and 'agent' assignments require an assigned_id.
            if (in_array($data['assigned_type'], ['user', 'agent'], true) && $data['assigned_id'] === null) {
                throw new \InvalidArgumentException('assigned_id is required for assigned_type "' . $data['assigned_type'] . '".');
            }

            // 'cron' assignments must NOT have an assigned_id (no actor).
            if ($data['assigned_type'] === 'cron' && $data['assigned_id'] !== null) {
                throw new \InvalidArgumentException('assigned_id must be NULL for assigned_type "cron".');
            }
        }

        // Validate execution model.
        if ($data['execution_type'] !== null) {
            if (!in_array($data['execution_type'], self::EXECUTION_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid execution_type value.');
            }
            // execution_type requires assigned_type = 'cron'.
            if ($data['assigned_type'] !== 'cron') {
                throw new \InvalidArgumentException('execution_type may only be set when assigned_type is "cron".');
            }
        }

        // cron tasks must have an execution_type configured.
        if ($data['assigned_type'] === 'cron' && $data['execution_type'] === null) {
            throw new \InvalidArgumentException('execution_type is required for cron-assigned tasks.');
        }

        // execution_payload must be valid JSON when present.
        if ($data['execution_payload'] !== null) {
            json_decode($data['execution_payload']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('execution_payload must be valid JSON.');
            }
        }

        // Validate schedule model.
        if ($data['schedule_type'] !== null) {
            if (!in_array($data['schedule_type'], self::SCHEDULE_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid schedule_type value.');
            }

            // schedule_type is only meaningful for cron-assigned tasks.
            if ($data['assigned_type'] !== 'cron') {
                throw new \InvalidArgumentException(
                    'schedule_type may only be set when assigned_type is "cron".'
                );
            }

            // 'interval' requires a positive integer schedule_value (seconds).
            if ($data['schedule_type'] === 'interval') {
                $v = $data['schedule_value'];
                if ($v === null || !ctype_digit($v) || (int) $v <= 0) {
                    throw new \InvalidArgumentException(
                        'schedule_value must be a positive integer (seconds) when schedule_type is "interval".'
                    );
                }
            }

            // 'cron' requires a valid 5-field cron expression.
            if ($data['schedule_type'] === 'cron') {
                $v = $data['schedule_value'];
                if ($v === null || !self::isValidCronExpression($v)) {
                    throw new \InvalidArgumentException(
                        'schedule_value must be a valid 5-field cron expression when schedule_type is "cron" '
                        . '(supported syntax per field: *, */n, exact integer).'
                    );
                }
            }

            // 'always' must not have a schedule_value.
            if ($data['schedule_type'] === 'always' && $data['schedule_value'] !== null) {
                throw new \InvalidArgumentException(
                    'schedule_value must be NULL when schedule_type is "always".'
                );
            }
        }

        // schedule_value without schedule_type is not allowed.
        if ($data['schedule_value'] !== null && $data['schedule_type'] === null) {
            throw new \InvalidArgumentException(
                'schedule_value requires schedule_type to be set.'
            );
        }
    }
}
