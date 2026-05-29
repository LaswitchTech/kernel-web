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

    /** Priority weights — mapped from named levels. */
    public const PRIORITY_LEVELS = [
        'low'      => -1,
        'medium'   =>  0,
        'high'     =>  1,
        'critical' =>  2,
    ];

    /** Priority levels indexed by weight (inverse of PRIORITY_LEVELS). */
    public const PRIORITY_LABELS = [
        -1 => 'Low',
         0 => 'Medium',
         1 => 'High',
         2 => 'Critical',
    ];

    /** Sort order: higher priority first. */
    public const PRIORITY_SORT = [
        'critical' => 2,
        'high'     => 1,
        'medium'   => 0,
        'low'      => -1,
    ];

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
    private ?TaskActivityRepository $activity;

    public function __construct(TaskRepository $repo, ?TaskActivityRepository $activity = null)
    {
        $this->repo     = $repo;
        $this->activity = $activity;
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

        $id = $this->repo->create($data);

        // Log creation activity if activity repo is available.
        if ($this->activity !== null) {
            $this->activity->logEvent(
                $id,
                'task_created',
                $data['created_by_user_id'] ?? null,
                null,
                null,
                ['title' => $data['title']]
            );
        }

        return $id;
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

        // Log deletion activity.
        if ($this->activity !== null) {
            $this->activity->logEvent(
                $id,
                'deleted',
                null,
                null,
                null,
                ['title' => $task['title'] ?? null]
            );
        }

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

        // Log activity for changes that matter.
        if ($this->activity === null) {
            return;
        }

        $oldStatus = $task['status'] ?? null;
        $newStatus = $data['status'] ?? $oldStatus;
        $oldPriority = $task['priority'] ?? null;
        $newPriority = $data['priority'] ?? $oldPriority;
        $oldAssignee = $task['assigned_type'] . ':' . ($task['assigned_id'] ?? '');
        $newAssignee = ($data['assigned_type'] ?? null) . ':' . ($data['assigned_id'] ?? null);

        if ($oldStatus !== $newStatus) {
            $this->activity->logEvent(
                $id,
                'status_changed',
                null,
                $oldStatus,
                $newStatus,
                ['old_title' => $oldStatus, 'new_title' => $newStatus]
            );
        }

        if ($oldPriority !== $newPriority) {
            $this->activity->logEvent(
                $id,
                'priority_changed',
                null,
                (string) $oldPriority,
                (string) $newPriority,
                ['old_value' => (string) $oldPriority, 'new_value' => (string) $newPriority]
            );
        }

        if ($oldAssignee !== $newAssignee) {
            $this->activity->logEvent(
                $id,
                'assigned',
                null,
                $oldAssignee,
                $newAssignee,
                ['old_assignee' => $oldAssignee, 'new_assignee' => $newAssignee]
            );
        }
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

        // Priority: accept named level ("high") or integer weight (1).
        $priorityInput = trim($data['priority'] ?? '');
        if ($priorityInput !== '') {
            if (isset(self::PRIORITY_LEVELS[$priorityInput])) {
                $data['priority'] = self::PRIORITY_LEVELS[$priorityInput];
            } elseif (ctype_digit($priorityInput) || (string) (int) $priorityInput === $priorityInput) {
                $data['priority'] = (int) $priorityInput;
            } else {
                $data['priority'] = self::PRIORITY_LEVELS['medium'];
            }
        } else {
            $data['priority'] = null;
        }

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
        // ---- Priority ----

        if ($data['priority'] !== null && !in_array($data['priority'], array_values(self::PRIORITY_LEVELS), true)) {
            throw new \InvalidArgumentException(
                'Invalid priority value. Must be one of: ' . implode(', ', array_keys(self::PRIORITY_LEVELS))
            );
        }

        // ---- Title ----

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

    // ------
    // Action registration
    // ------

    /**
     * Register all tasks plugin actions with the ActionRegistry.
     *
     * Called by the plugins.bootstrap hook during plugin bootstrap.
     */
    public static function registerActions(): void
    {
        $prefix = 'tasks';

        ActionRegistry::add(new ActionDefinition(
            id: "{$prefix}.create",
            name: 'Create Task',
            description: 'Create a new task with a title and optional metadata.',
            service_class: self::class,
            service_method: 'createFromInput',
            parameters: [
                ['name' => 'title', 'type' => 'string', 'required' => true, 'description' => 'Task title', 'min_length' => 1, 'max_length' => 255],
                ['name' => 'description', 'type' => 'string', 'required' => false, 'description' => 'Task description', 'default' => null],
                ['name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'Initial status', 'default' => 'open', 'enum' => self::STATUSES],
                ['name' => 'priority', 'type' => 'string', 'required' => false, 'description' => 'Priority level', 'default' => 'medium', 'enum' => array_keys(self::PRIORITY_LEVELS)],
                ['name' => 'due_at', 'type' => 'string', 'required' => false, 'description' => 'Due date (YYYY-MM-DD HH:MM:SS)', 'default' => null],
                ['name' => 'assigned_type', 'type' => 'string', 'required' => false, 'description' => 'Assignment type', 'default' => null, 'enum' => self::ASSIGNED_TYPES],
                ['name' => 'assigned_id', 'type' => 'nullable_int', 'required' => false, 'description' => 'Assignee ID', 'default' => null],
            ],
            permission: 'tasks.manage',
            risk_level: 'low',
            audit_entity_type: 'task',
            metadata: [
                'scope' => 'tasks',
                'category' => 'crud',
                'input_summary' => 'title, description, status, priority',
            ],
            source: 'tasks',
            order: 10,
        ));

        ActionRegistry::add(new ActionDefinition(
            id: "{$prefix}.update",
            name: 'Update Task',
            description: 'Update an existing task\'s fields (status, priority, assignee, etc.).',
            service_class: self::class,
            service_method: 'updateFromInput',
            parameters: [
                ['name' => 'id', 'type' => 'int', 'required' => true, 'description' => 'Task ID'],
                ['name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'New status', 'enum' => self::STATUSES],
                ['name' => 'priority', 'type' => 'string', 'required' => false, 'description' => 'New priority', 'enum' => array_keys(self::PRIORITY_LEVELS)],
                ['name' => 'title', 'type' => 'string', 'required' => false, 'description' => 'New title', 'max_length' => 255],
                ['name' => 'description', 'type' => 'string', 'required' => false, 'description' => 'New description'],
                ['name' => 'due_at', 'type' => 'string', 'required' => false, 'description' => 'New due date'],
                ['name' => 'assigned_type', 'type' => 'string', 'required' => false, 'description' => 'New assignment type', 'enum' => self::ASSIGNED_TYPES],
                ['name' => 'assigned_id', 'type' => 'nullable_int', 'required' => false, 'description' => 'New assignee ID'],
            ],
            permission: 'tasks.manage',
            risk_level: 'low',
            audit_entity_type: 'task',
            metadata: [
                'scope' => 'tasks',
                'category' => 'crud',
                'input_summary' => 'id, status, priority, title, description',
            ],
            source: 'tasks',
            order: 20,
        ));

        ActionRegistry::add(new ActionDefinition(
            id: "{$prefix}.delete",
            name: 'Delete Task',
            description: 'Permanently delete a task. This action requires approval for agent callers.',
            service_class: self::class,
            service_method: 'deleteFromInput',
            parameters: [
                ['name' => 'id', 'type' => 'int', 'required' => true, 'description' => 'Task ID to delete'],
            ],
            permission: 'tasks.manage',
            risk_level: 'high',
            audit_entity_type: 'task',
            metadata: [
                'scope' => 'tasks',
                'category' => 'crud',
                'input_summary' => 'id',
            ],
            source: 'tasks',
            order: 30,
        ));

        ActionRegistry::add(new ActionDefinition(
            id: "{$prefix}.assign",
            name: 'Assign Task',
            description: 'Change the assignee of a task.',
            service_class: self::class,
            service_method: 'assignFromInput',
            parameters: [
                ['name' => 'id', 'type' => 'int', 'required' => true, 'description' => 'Task ID'],
                ['name' => 'assigned_type', 'type' => 'string', 'required' => true, 'description' => 'Assignment type', 'enum' => self::ASSIGNED_TYPES],
                ['name' => 'assigned_id', 'type' => 'nullable_int', 'required' => true, 'description' => 'Assignee ID'],
            ],
            permission: 'tasks.manage',
            risk_level: 'low',
            audit_entity_type: 'task',
            metadata: [
                'scope' => 'tasks',
                'category' => 'assign',
                'input_summary' => 'id, assigned_type, assigned_id',
            ],
            source: 'tasks',
            order: 40,
        ));
    }

    /**
     * Thin wrappers that expose the service methods as action handlers.
     *
     * These instantiate the service internally (or use the container if available)
     * and call the existing service methods. They bridge the action contract
     * with the existing TaskService API.
     */

    public static function createFromInput(array $input): int
    {
        $db = \App\Core\Config::load('database');
        $driver = match ($db['driver'] ?? 'sqlite') {
            'sqlite' => new \App\Core\SQLiteDriver($db['sqlite']['path']),
            default => throw new \RuntimeException('Unsupported database driver'),
        };
        $repo = new TaskRepository($driver);
        $service = new self($repo);
        return $service->create($input);
    }

    public static function updateFromInput(array $input): array
    {
        if (!isset($input['id'])) {
            throw new \InvalidArgumentException('id is required for update.');
        }
        $id = (int) $input['id'];
        unset($input['id']);

        $db = \App\Core\Config::load('database');
        $driver = match ($db['driver'] ?? 'sqlite') {
            'sqlite' => new \App\Core\SQLiteDriver($db['sqlite']['path']),
            default => throw new \RuntimeException('Unsupported database driver'),
        };
        $repo = new TaskRepository($driver);
        $service = new self($repo);
        $service->update($id, $input);
        return ['id' => $id, 'updated' => true];
    }

    public static function deleteFromInput(array $input): array
    {
        if (!isset($input['id'])) {
            throw new \InvalidArgumentException('id is required for delete.');
        }
        $id = (int) $input['id'];

        $db = \App\Core\Config::load('database');
        $driver = match ($db['driver'] ?? 'sqlite') {
            'sqlite' => new \App\Core\SQLiteDriver($db['sqlite']['path']),
            default => throw new \RuntimeException('Unsupported database driver'),
        };
        $repo = new TaskRepository($driver);
        $service = new self($repo);
        $task = $service->delete($id);
        return ['id' => $id, 'deleted' => true, 'title' => $task['title'] ?? null];
    }

    public static function assignFromInput(array $input): array
    {
        if (!isset($input['id'], $input['assigned_type'], $input['assigned_id'])) {
            throw new \InvalidArgumentException('id, assigned_type, and assigned_id are required for assign.');
        }
        $id = (int) $input['id'];

        $db = \App\Core\Config::load('database');
        $driver = match ($db['driver'] ?? 'sqlite') {
            'sqlite' => new \App\Core\SQLiteDriver($db['sqlite']['path']),
            default => throw new \RuntimeException('Unsupported database driver'),
        };
        $repo = new TaskRepository($driver);
        $service = new self($repo);
        $service->update($id, [
            'assigned_type' => $input['assigned_type'],
            'assigned_id' => $input['assigned_id'],
        ]);
        return ['id' => $id, 'assigned_type' => $input['assigned_type'], 'assigned_id' => (int) $input['assigned_id']];
    }
}
