<?php

namespace App\Plugins\tasks;

/**
 * Validation and write orchestration for the Tasks plugin.
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

    // ------
    // Read
    // ------

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

    // ------
    // Write
    // ------

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

    // ------
    // User-scoped read methods (for My Tasks / task dashboard)
    // ------

    public function getForUser(int $userId): array
    {
        return $this->repo->findForUser($userId);
    }

    public function getOverdueForUser(int $userId): array
    {
        return $this->repo->findOverdueForUser($userId);
    }

    public function getDueTodayForUser(int $userId): array
    {
        return $this->repo->findDueTodayForUser($userId);
    }

    public function countOpenForUser(int $userId): int
    {
        return $this->repo->countOpenForUser($userId);
    }

    public function countOverdueForUser(int $userId): int
    {
        return $this->repo->countOverdueForUser($userId);
    }

    public function countDueTodayForUser(int $userId): int
    {
        return $this->repo->countDueTodayForUser($userId);
    }

    public function getUnassigned(): array
    {
        return $this->repo->findUnassigned();
    }

    public function countUnassigned(): int
    {
        return $this->repo->countUnassigned();
    }

    // ------
    // Cron / scheduler methods
    // ------

    public function getCronRunnable(): array
    {
        return $this->repo->findRunnableCron();
    }

    public function recordExecution(int $id, string $status, ?string $message): void
    {
        $this->repo->updateExecution($id, date('Y-m-d H:i:s'), $status, $message);
    }

    public function delete(int $id): array
    {
        $task = $this->repo->findById($id);
        if ($task === null) {
            throw new \InvalidArgumentException('Task not found.');
        }

        $this->repo->delete($id, date('Y-m-d H:i:s'));

        return $task;
    }

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

    // ------
    // Helpers
    // ------

    private function normalise(array $data): array
    {
        $data['title']       = trim($data['title'] ?? '');
        $data['description'] = trim($data['description'] ?? '') ?: null;
        $data['status']      = trim($data['status'] ?? 'open');
        $data['due_at']      = trim($data['due_at'] ?? '') ?: null;

        $assignedType = trim($data['assigned_type'] ?? '');
        $data['assigned_type'] = $assignedType !== '' ? $assignedType : null;

        $assignedId = (int) ($data['assigned_id'] ?? 0);
        $data['assigned_id'] = $assignedId > 0 ? $assignedId : null;

        $executionType = trim($data['execution_type'] ?? '');
        $data['execution_type'] = $executionType !== '' ? $executionType : null;

        $executionPayload = trim($data['execution_payload'] ?? '');
        $data['execution_payload'] = $executionPayload !== '' ? $executionPayload : null;

        $scheduleType = trim($data['schedule_type'] ?? '');
        $data['schedule_type'] = $scheduleType !== '' ? $scheduleType : null;

        $scheduleValue = trim($data['schedule_value'] ?? '');
        $data['schedule_value'] = $scheduleValue !== '' ? $scheduleValue : null;

        $entityType = trim($data['entity_type'] ?? '');
        $entityId   = (int) ($data['entity_id'] ?? 0);
        $data['entity_type'] = $entityType !== '' ? $entityType : null;
        $data['entity_id']   = $entityId > 0 ? $entityId : null;

        return $data;
    }

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

        if ($data['entity_type'] !== null) {
            if (!in_array($data['entity_type'], self::ENTITY_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid entity type.');
            }
            if ($data['entity_id'] === null) {
                throw new \InvalidArgumentException('Entity ID is required when entity type is provided.');
            }
        }

        if ($data['assigned_type'] !== null) {
            if (!in_array($data['assigned_type'], self::ASSIGNED_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid assigned_type value.');
            }

            if (in_array($data['assigned_type'], ['user', 'agent'], true) && $data['assigned_id'] === null) {
                throw new \InvalidArgumentException('assigned_id is required for assigned_type "' . $data['assigned_type'] . '".');
            }

            if ($data['assigned_type'] === 'cron' && $data['assigned_id'] !== null) {
                throw new \InvalidArgumentException('assigned_id must be NULL for assigned_type "cron".');
            }
        }

        if ($data['execution_type'] !== null) {
            if (!in_array($data['execution_type'], self::EXECUTION_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid execution_type value.');
            }
            if ($data['assigned_type'] !== 'cron') {
                throw new \InvalidArgumentException('execution_type may only be set when assigned_type is "cron".');
            }
        }

        if ($data['assigned_type'] === 'cron' && $data['execution_type'] === null) {
            throw new \InvalidArgumentException('execution_type is required for cron-assigned tasks.');
        }

        if ($data['execution_payload'] !== null) {
            json_decode($data['execution_payload']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('execution_payload must be valid JSON.');
            }
        }

        if ($data['schedule_type'] !== null) {
            if (!in_array($data['schedule_type'], self::SCHEDULE_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid schedule_type value.');
            }

            if ($data['assigned_type'] !== 'cron') {
                throw new \InvalidArgumentException(
                    'schedule_type may only be set when assigned_type is "cron".'
                );
            }

            if ($data['schedule_type'] === 'interval') {
                $v = $data['schedule_value'];
                if ($v === null || !ctype_digit($v) || (int) $v <= 0) {
                    throw new \InvalidArgumentException(
                        'schedule_value must be a positive integer (seconds) when schedule_type is "interval".'
                    );
                }
            }

            if ($data['schedule_type'] === 'cron') {
                $v = $data['schedule_value'];
                if ($v === null || !self::isValidCronExpression($v)) {
                    throw new \InvalidArgumentException(
                        'schedule_value must be a valid 5-field cron expression when schedule_type is "cron".'
                    );
                }
            }

            if ($data['schedule_type'] === 'always' && $data['schedule_value'] !== null) {
                throw new \InvalidArgumentException(
                    'schedule_value must be NULL when schedule_type is "always".'
                );
            }
        }

        if ($data['schedule_value'] !== null && $data['schedule_type'] === null) {
            throw new \InvalidArgumentException(
                'schedule_value requires schedule_type to be set.'
            );
        }
    }
}
