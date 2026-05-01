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
class TaskRepository
{
    private \App\Core\DatabaseInterface $db;

    public function __construct(\App\Core\DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // ---------------------------------------------------------------
    // Read
    // ---------------------------------------------------------------

    /**
     * Return all tasks, newest first.
     *
     * @return array<int, array>
     */
    public function findAll(): array
    {
        return $this->db->fetch(
            "SELECT
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
                t.created_at,
                t.updated_at,
                a.username     AS assigned_username,
                a.display_name AS assigned_display,
                c.username     AS created_username,
                c.display_name AS created_display
            FROM   tasks t
            LEFT   JOIN users a ON (t.assigned_type = 'user' AND a.id = t.assigned_id)
            LEFT   JOIN users c ON c.id = t.created_by_user_id
            WHERE  t.deleted_at IS NULL
            ORDER  BY t.created_at DESC",
            []
        );
    }

    /**
     * Return all tasks linked to a specific entity, newest first.
     *
     * @return array<int, array>
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->db->fetch(
            "SELECT
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
              AND  t.deleted_at  IS NULL
            ORDER  BY t.created_at DESC",
            [$entityType, $entityId]
        );
    }

    /**
     * Find a single task by ID.
     *
     * Returns null if the task does not exist.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT
                t.id,
                t.title,
                t.description,
                t.status,
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
              AND  t.deleted_at IS NULL",
            [$id]
        );
    }

    // ---------------------------------------------------------------
    // Write
    // ---------------------------------------------------------------

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
     * } $data
     * @return int  The new task ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "INSERT INTO tasks
                (title, description, status, due_at,
                 entity_type, entity_id, created_by_user_id,
                 assigned_type, assigned_id, execution_type, execution_payload,
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['title'],
                $data['description']        ?? null,
                $data['status'],
                $data['due_at']             ?? null,
                $data['entity_type']        ?? null,
                $data['entity_id']          ?? null,
                $data['created_by_user_id'] ?? null,
                $data['assigned_type']      ?? null,
                $data['assigned_id']        ?? null,
                $data['execution_type']     ?? null,
                $data['execution_payload']  ?? null,
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

    // ---------------------------------------------------------------
    // User-scoped queries
    // ---------------------------------------------------------------

    public function findForUser(int $userId): array
    {
        return $this->db->fetch(
            "SELECT
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
            ORDER  BY CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END,
                      t.due_at ASC,
                      t.created_at DESC",
            [$userId]
        );
    }

    public function findOverdueForUser(int $userId): array
    {
        return $this->db->fetch(
            "SELECT
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
              AND  DATE(t.due_at) < DATE('now')
            ORDER  BY t.due_at ASC, t.created_at DESC",
            [$userId]
        );
    }

    public function findDueTodayForUser(int $userId): array
    {
        return $this->db->fetch(
            "SELECT
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
              AND  DATE(t.due_at) = DATE('now')
            ORDER  BY t.due_at ASC, t.created_at DESC",
            [$userId]
        );
    }

    public function countOpenForUser(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type = 'user'
               AND  assigned_id   = ?
               AND  status IN ('open', 'in_progress')
               AND  deleted_at IS NULL",
            [$userId]
        );
        return (int) ($row['n'] ?? 0);
    }

    public function countOverdueForUser(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type = 'user'
               AND  assigned_id   = ?
               AND  status NOT IN ('completed', 'canceled')
               AND  deleted_at IS NULL
               AND  due_at IS NOT NULL
               AND  DATE(due_at) < DATE('now')",
            [$userId]
        );
        return (int) ($row['n'] ?? 0);
    }

    public function countDueTodayForUser(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type = 'user'
               AND  assigned_id   = ?
               AND  status NOT IN ('completed', 'canceled')
               AND  deleted_at IS NULL
               AND  due_at IS NOT NULL
               AND  DATE(due_at) = DATE('now')",
            [$userId]
        );
        return (int) ($row['n'] ?? 0);
    }

    public function findUnassigned(): array
    {
        return $this->db->fetch(
            "SELECT
                t.id, t.title, t.description, t.status,
                t.assigned_type, t.assigned_id, t.due_at,
                t.entity_type, t.entity_id, t.created_by_user_id,
                t.created_at, t.updated_at,
                c.username     AS created_username,
                c.display_name AS created_display
            FROM   tasks t
            LEFT   JOIN users c ON c.id = t.created_by_user_id
            WHERE  t.assigned_type IS NULL
              AND  t.status NOT IN ('completed', 'canceled')
              AND  t.deleted_at IS NULL
            ORDER  BY t.created_at DESC",
            []
        );
    }

    public function countUnassigned(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n
             FROM   tasks
             WHERE  assigned_type IS NULL
               AND  status NOT IN ('completed', 'canceled')
               AND  deleted_at IS NULL",
            []
        );
        return (int) ($row['n'] ?? 0);
    }

    // ---------------------------------------------------------------
    // Reminder queries (used by scripts/task-reminders.php)
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // Cron / scheduler queries
    // ---------------------------------------------------------------

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
