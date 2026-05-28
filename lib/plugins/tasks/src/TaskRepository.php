<?php

namespace App\Plugins\tasks;

/**
 * All database queries for the tasks table.
 *
 * This repository is entity-agnostic: it knows about tasks and their
 * polymorphic (entity_type, entity_id) linkage, but has no dependency on
 * any NetMon-specific class — it belongs to the reusable Tasks plugin and
 * may be used by any application built on this platform.
 *
 * Returns raw arrays; no domain objects.
 */
class TaskRepository extends \App\Core\OrganizationScopedRepository
{
    private \App\Core\DatabaseInterface $db;

    public function __construct(\App\Core\DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // ------READ---------------------------------------------------------------

    /**
     * Return all tasks, newest first.
     *
     * @return array<int, array>
     */
    public function findAll(): array
    {
        $and  = $this->organizationAnd();
        $orgParams = $this->organizationParams();

        $sql = "SELECT
            t.id,
            t.title,
            t.description,
            t.status,
            t.priority,
            t.assigned_type,
            t.assigned_id,
            t.execution_type,
            t.last_run_at,
            t.last_run_status,
            t.due_at,
            t.entity_type,
            t.entity_id,
            t.created_by_user_id,
            t.organization_id,
            t.created_at,
            t.updated_at,
            a.username     AS assigned_username,
            a.display_name AS assigned_display,
            c.username     AS created_username,
            c.display_name AS created_display
         FROM   tasks t
         LEFT   JOIN users a ON (t.assigned_type = 'user' AND a.id = t.assigned_id)
         LEFT   JOIN users c ON c.id = t.created_by_user_id
         WHERE  t.deleted_at IS NULL";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        $sql .= " ORDER  BY t.priority DESC, t.due_at ASC NULLS LAST, t.created_at DESC";

        return $this->db->fetch($sql, $orgParams);
    }

    /**
     * Return all tasks linked to a specific entity, newest first.
     *
     * @return array<int, array>
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$entityType, $entityId], $this->organizationParams());

        $sql = "SELECT
            t.id,
            t.title,
            t.description,
            t.status,
            t.assigned_type,
            t.assigned_id,
            t.execution_type,
            t.last_run_at,
            t.last_run_status,
            t.due_at,
            t.entity_type,
            t.entity_id,
            t.created_by_user_id,
            t.organization_id,
            t.created_at,
            t.updated_at,
            a.username     AS assigned_username,
            a.display_name AS assigned_display,
            c.username     AS created_username,
            c.display_name AS created_display
         FROM   tasks t
         LEFT   JOIN users a ON (t.assigned_type = 'user' AND a.id = t.assigned_id)
         LEFT   JOIN users c ON c.id = t.created_by_user_id
         WHERE  t.entity_type = ?
           AND  t.entity_id   = ?
           AND  t.deleted_at  IS NULL";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        $sql .= " ORDER  BY t.created_at DESC";

        return $this->db->fetch($sql, $params);
    }

    /**
     * Find a single task by ID.
     *
     * Returns null if the task does not exist.
     */
    public function findById(int $id): ?array
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$id], $this->organizationParams());

        $sql = "SELECT
            t.id,
            t.title,
            t.description,
            t.status,
            t.priority,
            t.assigned_type,
            t.assigned_id,
            t.execution_type,
            t.execution_payload,
            t.last_run_at,
            t.last_run_status,
            t.last_run_message,
            t.due_at,
            t.entity_type,
            t.entity_id,
            t.created_by_user_id,
            t.organization_id,
            t.created_at,
            t.updated_at,
            a.username     AS assigned_username,
            a.display_name AS assigned_display,
            c.username     AS created_username,
            c.display_name AS created_display
         FROM   tasks t
         LEFT   JOIN users a ON (t.assigned_type = 'user' AND a.id = t.assigned_id)
         LEFT   JOIN users c ON c.id = t.created_by_user_id
         WHERE  t.id = ?
           AND  t.deleted_at IS NULL";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }

        return $this->db->fetchOne($sql, $params);
    }

    // ------WRITE--------------------------------------------------------------

    /**
     * Insert a new task row.
     *
     * @param  array{
     *   title:               string,
     *   description:         string|null,
     *   status:              string,
     *   assigned_type:       string|null,
     *   assigned_id:         int|null,
     *   due_at:              string|null,
     *   entity_type:         string|null,
     *   entity_id:           int|null,
     *   created_by_user_id:  int|null,
     *   organization_id:     int|null,
     * } $data
     * @return int  The new task ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "INSERT INTO tasks
                (title, description, status, priority, due_at,
                 entity_type, entity_id, created_by_user_id,
                 assigned_type, assigned_id, execution_type, execution_payload,
                 organization_id,
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['title'],
                $data['description']        ?? null,
                $data['status'],
                $data['priority']           ?? 0,
                $data['due_at']             ?? null,
                $data['entity_type']        ?? null,
                $data['entity_id']          ?? null,
                $data['created_by_user_id'] ?? null,
                $data['assigned_type']      ?? null,
                $data['assigned_id']        ?? null,
                $data['execution_type']     ?? null,
                $data['execution_payload']  ?? null,
                $data['organization_id']    ?? null,
                $now,
                $now,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update mutable fields of an existing task.
     */
    public function update(int $id, array $data): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "UPDATE tasks
             SET title             = ?,
                 description       = ?,
                 status            = ?,
                 priority          = ?,
                 due_at            = ?,
                 assigned_type     = ?,
                 assigned_id       = ?,
                 execution_type    = ?,
                 execution_payload = ?,
                 updated_at        = ?
             WHERE id = ?",
            [
                $data['title'],
                $data['description']       ?? null,
                $data['status'],
                $data['priority']          ?? 0,
                $data['due_at']            ?? null,
                $data['assigned_type']     ?? null,
                $data['assigned_id']       ?? null,
                $data['execution_type']    ?? null,
                $data['execution_payload'] ?? null,
                $now,
                $id,
            ]
        );
    }

    public function delete(int $id, string $now): void
    {
        $this->db->execute(
            'UPDATE tasks SET deleted_at = ?, updated_at = ? WHERE id = ?',
            [$now, $now, $id]
        );
    }

    // ------USER-SCOPED--------------------------------------------------------

    public function findForUser(int $userId): array
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$userId], $this->organizationParams());

        $sql = "SELECT
            t.id, t.title, t.description, t.status,
            t.assigned_type, t.assigned_id, t.due_at,
            t.entity_type, t.entity_id, t.created_by_user_id,
            t.created_at, t.updated_at,
            a.username     AS assigned_username,
            a.display_name AS assigned_display,
            c.username     AS created_username,
            c.display_name AS created_display
         FROM   tasks t
         LEFT   JOIN users a ON (t.assigned_type = 'user' AND a.id = t.assigned_id)
         LEFT   JOIN users c ON c.id = t.created_by_user_id
         WHERE  t.assigned_type = 'user'
           AND  t.assigned_id   = ?
           AND  t.status NOT IN ('completed', 'canceled')
           AND  t.deleted_at IS NULL";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        $sql .= " ORDER  BY CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END,
             t.due_at ASC,
             t.created_at DESC";

        return $this->db->fetch($sql, $params);
    }

    public function findOverdueForUser(int $userId): array
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$userId], $this->organizationParams());

        $sql = "SELECT
            t.id, t.title, t.description, t.status,
            t.assigned_type, t.assigned_id, t.due_at,
            t.entity_type, t.entity_id, t.created_by_user_id,
            t.created_at, t.updated_at,
            a.username     AS assigned_username,
            a.display_name AS assigned_display,
            c.username     AS created_username,
            c.display_name AS created_display
         FROM   tasks t
         LEFT   JOIN users a ON (t.assigned_type = 'user' AND a.id = t.assigned_id)
         LEFT   JOIN users c ON c.id = t.created_by_user_id
         WHERE  t.assigned_type = 'user'
           AND  t.assigned_id   = ?
           AND  t.status NOT IN ('completed', 'canceled')
           AND  t.deleted_at IS NULL
           AND  t.due_at IS NOT NULL
           AND  DATE(t.due_at) < DATE('now')";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        $sql .= " ORDER  BY t.due_at ASC, t.created_at DESC";

        return $this->db->fetch($sql, $params);
    }

    public function findDueTodayForUser(int $userId): array
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$userId], $this->organizationParams());

        $sql = "SELECT
            t.id, t.title, t.description, t.status,
            t.assigned_type, t.assigned_id, t.due_at,
            t.entity_type, t.entity_id, t.created_by_user_id,
            t.created_at, t.updated_at,
            a.username     AS assigned_username,
            a.display_name AS assigned_display,
            c.username     AS created_username,
            c.display_name AS created_display
         FROM   tasks t
         LEFT   JOIN users a ON (t.assigned_type = 'user' AND a.id = t.assigned_id)
         LEFT   JOIN users c ON c.id = t.created_by_user_id
         WHERE  t.assigned_type = 'user'
           AND  t.assigned_id   = ?
           AND  t.status NOT IN ('completed', 'canceled')
           AND  t.deleted_at IS NULL
           AND  t.due_at IS NOT NULL
           AND  DATE(t.due_at) = DATE('now')";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        $sql .= " ORDER  BY t.due_at ASC, t.created_at DESC";

        return $this->db->fetch($sql, $params);
    }

    public function countOpenForUser(int $userId): int
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$userId], $this->organizationParams());

        $sql = "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type = 'user'
               AND  assigned_id   = ?
               AND  status IN ('open', 'in_progress')
               AND  deleted_at IS NULL";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }

        return (int) (($this->db->fetchOne($sql, $params)['n'] ?? 0));
    }

    public function countOverdueForUser(int $userId): int
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$userId], $this->organizationParams());

        $sql = "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type = 'user'
               AND  assigned_id   = ?
               AND  status NOT IN ('completed', 'canceled')
               AND  deleted_at IS NULL
               AND  due_at IS NOT NULL
               AND  DATE(due_at) < DATE('now')";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }

        return (int) (($this->db->fetchOne($sql, $params)['n'] ?? 0));
    }

    public function countDueTodayForUser(int $userId): int
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$userId], $this->organizationParams());

        $sql = "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type = 'user'
               AND  assigned_id   = ?
               AND  status NOT IN ('completed', 'canceled')
               AND  deleted_at IS NULL
               AND  due_at IS NOT NULL
               AND  DATE(due_at) = DATE('now')";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }

        return (int) (($this->db->fetchOne($sql, $params)['n'] ?? 0));
    }

    public function findUnassigned(): array
    {
        $and    = $this->organizationAnd();
        $params = $this->organizationParams();

        $sql = "SELECT
            t.id, t.title, t.description, t.status,
            t.assigned_type, t.assigned_id, t.due_at,
            t.entity_type, t.entity_id, t.created_by_user_id,
            t.created_at, t.updated_at,
            c.username     AS created_username,
            c.display_name AS created_display
         FROM   tasks t
         LEFT   JOIN users c ON c.id = t.created_by_user_id
         WHERE  assigned_type IS NULL
           AND  status NOT IN ('completed', 'canceled')
           AND  deleted_at IS NULL";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        $sql .= " ORDER  BY t.created_at DESC";

        return $this->db->fetch($sql, $params);
    }

    public function countUnassigned(): int
    {
        $and    = $this->organizationAnd();
        $params = $this->organizationParams();

        $sql = "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type IS NULL
               AND  status NOT IN ('completed', 'canceled')
               AND  deleted_at IS NULL";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }

        return (int) (($this->db->fetchOne($sql, $params)['n'] ?? 0));
    }

    // ------REMINDER QUERIES (used by scripts/task-reminders.php)--------------

    public function findDueTodayPendingReminder(): array
    {
        return $this->db->fetch(
            "SELECT
                t.id, t.title, t.description, t.status,
                t.assigned_type, t.assigned_id, t.due_at,
                t.entity_type, t.entity_id,
                t.reminder_due_sent_at, t.reminder_overdue_sent_at,
                u.email        AS assigned_user_email,
                u.username     AS assigned_username,
                u.display_name AS assigned_display
            FROM   tasks t
            JOIN   users u ON (t.assigned_type = 'user' AND u.id = t.assigned_id)
            WHERE  t.status NOT IN ('completed', 'canceled')
              AND  t.deleted_at IS NULL
              AND  t.due_at IS NOT NULL
              AND  DATE(t.due_at) = DATE('now')
              AND  t.reminder_due_sent_at IS NULL",
            []
        );
    }

    public function findOverduePendingReminder(): array
    {
        return $this->db->fetch(
            "SELECT
                t.id, t.title, t.description, t.status,
                t.assigned_type, t.assigned_id, t.due_at,
                t.entity_type, t.entity_id,
                t.reminder_due_sent_at, t.reminder_overdue_sent_at,
                u.email        AS assigned_user_email,
                u.username     AS assigned_username,
                u.display_name AS assigned_display
            FROM   tasks t
            JOIN   users u ON (t.assigned_type = 'user' AND u.id = t.assigned_id)
            WHERE  t.status NOT IN ('completed', 'canceled')
              AND  t.deleted_at IS NULL
              AND  t.due_at IS NOT NULL
              AND  DATE(t.due_at) < DATE('now')
              AND  t.reminder_overdue_sent_at IS NULL",
            []
        );
    }

    public function markReminderDueSent(int $id, string $now): void
    {
        $this->db->execute(
            'UPDATE tasks SET reminder_due_sent_at = ? WHERE id = ?',
            [$now, $id]
        );
    }

    public function markReminderOverdueSent(int $id, string $now): void
    {
        $this->db->execute(
            'UPDATE tasks SET reminder_overdue_sent_at = ? WHERE id = ?',
            [$now, $id]
        );
    }

    // ------SCHEDULER----------------------------------------------------------

    public function findRunnableCron(): array
    {
        return $this->db->fetch(
            "SELECT id, title, execution_type, execution_payload,
                    schedule_type, schedule_value, last_run_at
             FROM   tasks
             WHERE  assigned_type  = 'cron'
               AND  status        IN ('open', 'in_progress')
               AND  execution_type IS NOT NULL
               AND  deleted_at     IS NULL
             ORDER  BY id ASC",
            []
        );
    }

    public function updateExecution(int $id, string $now, string $status, ?string $message): void
    {
        $this->db->execute(
            "UPDATE tasks
             SET last_run_at      = ?,
                 last_run_status  = ?,
                 last_run_message = ?,
                 updated_at       = ?
             WHERE id = ?",
            [$now, $status, $message, $now, $id]
        );
    }
}
