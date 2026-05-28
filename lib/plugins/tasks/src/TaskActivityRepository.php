<?php

namespace App\Plugins\tasks;

use App\Core\DatabaseInterface;
use App\Core\OrganizationScopedRepository;

/**
 * Activity tracking for the tasks table.
 *
 * Each row records a discrete event on a task (status change, reassignment,
 * priority change, creation, etc.). Provides an audit trail for the task
 * lifecycle, distinct from admin_audit_log.
 *
 * Event types (validated at write time):
 *   task_created, status_changed, assigned, priority_changed,
 *   completed, canceled, deleted
 */
class TaskActivityRepository extends OrganizationScopedRepository
{
    private DatabaseInterface $db;

    /** Valid event type values. */
    public const EVENT_TYPES = [
        'task_created',
        'status_changed',
        'assigned',
        'priority_changed',
        'completed',
        'canceled',
        'deleted',
    ];

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Log a new activity event on a task.
     *
     * @param string $eventType One of self::EVENT_TYPES.
     * @param int|null $userId Actor user ID (null = system).
     * @param array $meta Small context array (names, counts, changed fields).
     * @return int Activity row ID.
     */
    public function logEvent(
        int    $taskId,
        string $eventType,
        ?int   $userId,
        ?string $oldValue = null,
        ?string $newValue = null,
        array  $meta = []
    ): int {
        if (!in_array($eventType, self::EVENT_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid event type: {$eventType}");
        }

        $this->db->execute(
            'INSERT INTO task_activity (task_id, event_type, user_id, old_value, new_value, meta, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $taskId,
                $eventType,
                $userId,
                $oldValue,
                $newValue,
                json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                date('Y-m-d H:i:s'),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Return activity events for a specific task, ordered newest first.
     *
     * @param int $taskId Task ID.
     * @param int $limit Max rows (default 200).
     * @return array<int, array>
     */
    public function findByTask(int $taskId, int $limit = 200): array
    {
        $sql = 'SELECT * FROM task_activity WHERE task_id = ? ORDER BY created_at DESC, id DESC LIMIT ?';
        return $this->db->fetch($sql, [$taskId, $limit]);
    }

    /**
     * Return the most recent activity events across all tasks (with optional org scoping).
     *
     * Each row includes:
     *   task_id, event_type, user_id, old_value, new_value, meta, created_at
     *   actor_display_name (or null if user_id is null/missing)
     *   actor_username (or null if user_id is null/missing)
     *   task_title (title of the task)
     *
     * @param  int   $limit  Max rows to return (default 500).
     * @param  string $eventType Filter by event type ('all' = no filter).
     * @return array<int, array>
     */
    public function findRecent(int $limit = 500, string $eventType = 'all'): array
    {
        $whereParts = [];
        $bindings   = [];

        if ($eventType !== 'all') {
            $whereParts[] = 'a.event_type = ?';
            $bindings[] = $eventType;
        }

        // Apply org scoping if available.
        if ($this->organizationIds !== null && $this->organizationIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->organizationIds), '?'));
            $whereParts[] = 't.organization_id IN (' . $placeholders . ')';
            foreach ($this->organizationIds as $orgId) {
                $bindings[] = $orgId;
            }
        }

        $whereClause = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $bindings[] = $limit;

        $sql = "SELECT
                a.id,
                a.task_id,
                a.event_type,
                a.user_id,
                a.old_value,
                a.new_value,
                a.meta,
                a.created_at,
                u.display_name AS actor_display_name,
                u.username     AS actor_username,
                t.title        AS task_title
             FROM task_activity a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN tasks t ON t.id = a.task_id
             {$whereClause}
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ?";

        return $this->db->fetch($sql, $bindings);
    }

    /**
     * Return activity events for tasks assigned to a specific user.
     *
     * @param int $userId Actor user ID.
     * @param int $limit Max rows (default 500).
     * @return array<int, array>
     */
    public function findForUser(int $userId, int $limit = 500): array
    {
        $sql = "SELECT
                a.id,
                a.task_id,
                a.event_type,
                a.user_id,
                a.old_value,
                a.new_value,
                a.meta,
                a.created_at,
                u.display_name AS actor_display_name,
                u.username     AS actor_username,
                t.title        AS task_title
             FROM task_activity a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN tasks t ON t.id = a.task_id
             WHERE a.task_id IN (
                 SELECT id FROM tasks
                 WHERE assigned_type = 'user' AND assigned_id = ?
                    OR assigned_user_id = ?
             )
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ?";

        return $this->db->fetch($sql, [$userId, $userId, $limit]);
    }
}
