<?php

namespace App\Modules\Tasks\Models;

use App\Core\DatabaseInterface;

/**
 * All database queries for the tasks table.
 *
 * This repository is entity-agnostic: it knows about tasks and their
 * polymorphic (entity_type, entity_id) linkage, but has no dependency on
 * any NetMon-specific class — it belongs to the reusable Tasks module and
 * may be used by any application built on this platform.
 *
 * Returns raw arrays; no domain objects.
 */
class TaskRepository
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Return all tasks, newest first.
     *
     * JOINs users twice so callers receive assigned_display / created_display
     * without additional queries.
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

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

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
     *
     * Only the fields present in $data are updated; other columns are unchanged.
     *
     * @param  int   $id    Task primary key
     * @param  array $data  Subset of mutable columns
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

    /**
     * Soft-delete a task by setting deleted_at to the current timestamp.
     *
     * Does NOT hard-delete.  Soft-deleted tasks are excluded from all default
     * read queries (findAll, findByEntity, findById) via WHERE deleted_at IS NULL.
     *
     * The caller is responsible for confirming the task exists and has not
     * already been deleted before calling this method.
     */
    public function delete(int $id, string $now): void
    {
        $this->db->execute(
            'UPDATE tasks SET deleted_at = ?, updated_at = ? WHERE id = ?',
            [$now, $now, $id]
        );
    }

    // -------------------------------------------------------------------------
    // User-scoped queries (used by TaskController::index for My Tasks view)
    // -------------------------------------------------------------------------

    /**
     * Return all active (non-completed, non-canceled) tasks assigned to a user.
     *
     * Filters on the canonical assignment model: assigned_type = 'user' AND assigned_id = ?.
     *
     * @return array<int, array>
     */
    public function findForUser(int $userId): array
    {
        return $this->db->fetch(
            "SELECT
                t.id,
                t.title,
                t.description,
                t.status,
                t.assigned_type,
                t.assigned_id,
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

    /**
     * Return active tasks assigned to a user whose due date is strictly in the past.
     *
     * @return array<int, array>
     */
    public function findOverdueForUser(int $userId): array
    {
        return $this->db->fetch(
            "SELECT
                t.id,
                t.title,
                t.description,
                t.status,
                t.assigned_type,
                t.assigned_id,
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

    /**
     * Return active tasks assigned to a user whose due date is today.
     *
     * @return array<int, array>
     */
    public function findDueTodayForUser(int $userId): array
    {
        return $this->db->fetch(
            "SELECT
                t.id,
                t.title,
                t.description,
                t.status,
                t.assigned_type,
                t.assigned_id,
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

    /**
     * Count open + in_progress tasks assigned to a user.
     */
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

    /**
     * Count active tasks assigned to a user whose due date is strictly in the past.
     */
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

    /**
     * Count active tasks assigned to a user whose due date is today.
     */
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

    /**
     * Return active tasks with no assignee.
     *
     * A task is considered unassigned when assigned_type IS NULL.
     * All existing rows were backfilled by migration 0035; the write path
     * (TaskController) now always sets assigned_type for assigned tasks.
     *
     * Sorted newest first.
     *
     * @return array<int, array>
     */
    public function findUnassigned(): array
    {
        return $this->db->fetch(
            "SELECT
                t.id,
                t.title,
                t.description,
                t.status,
                t.assigned_type,
                t.assigned_id,
                t.due_at,
                t.entity_type,
                t.entity_id,
                t.created_by_user_id,
                t.created_at,
                t.updated_at,
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

    /**
     * Count active unassigned tasks.
     *
     * Matches the same definition as findUnassigned().
     */
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

    // -------------------------------------------------------------------------
    // Reminder queries (used by scripts/task-reminders.php)
    // -------------------------------------------------------------------------

    /**
     * Return tasks due today that have not yet had a "due today" reminder sent.
     *
     * Conditions:
     *   - assigned_type = 'user' (only user-assigned tasks receive reminders)
     *   - status NOT IN ('completed', 'canceled')
     *   - DATE(due_at) = DATE('now')
     *   - reminder_due_sent_at IS NULL
     *
     * The INNER JOIN on users ensures only tasks with a valid assigned user are
     * returned.  Tasks whose assigned user was deleted are excluded automatically.
     * Tasks assigned to non-user types (agent, cron) are excluded by the JOIN condition.
     *
     * Returns user email alongside task fields so the caller can dispatch
     * notifications without a second query.
     *
     * @return array<int, array>
     */
    public function findDueTodayPendingReminder(): array
    {
        return $this->db->fetch(
            "SELECT
                t.id,
                t.title,
                t.description,
                t.status,
                t.assigned_type,
                t.assigned_id,
                t.due_at,
                t.entity_type,
                t.entity_id,
                t.reminder_due_sent_at,
                t.reminder_overdue_sent_at,
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

    /**
     * Return overdue tasks that have not yet had an "overdue" reminder sent.
     *
     * Conditions:
     *   - assigned_type = 'user' (only user-assigned tasks receive reminders)
     *   - status NOT IN ('completed', 'canceled')
     *   - DATE(due_at) < DATE('now')   (strictly in the past)
     *   - reminder_overdue_sent_at IS NULL  (sent at most once per task, ever)
     *
     * Tasks assigned to non-user types (agent, cron) are excluded by the JOIN condition.
     *
     * @return array<int, array>
     */
    public function findOverduePendingReminder(): array
    {
        return $this->db->fetch(
            "SELECT
                t.id,
                t.title,
                t.description,
                t.status,
                t.assigned_type,
                t.assigned_id,
                t.due_at,
                t.entity_type,
                t.entity_id,
                t.reminder_due_sent_at,
                t.reminder_overdue_sent_at,
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

    /**
     * Record that the "due today" reminder was dispatched for a task.
     *
     * Called by scripts/task-reminders.php immediately after a successful
     * dispatch() call, so a second run of the script on the same day
     * does not re-send the notification.
     */
    public function markReminderDueSent(int $id, string $now): void
    {
        $this->db->execute(
            'UPDATE tasks SET reminder_due_sent_at = ? WHERE id = ?',
            [$now, $id]
        );
    }

    /**
     * Record that the "overdue" reminder was dispatched for a task.
     *
     * Called by scripts/task-reminders.php immediately after a successful
     * dispatch() call.  The IS NULL guard in findOverduePendingReminder()
     * ensures this fires at most once per task.
     */
    public function markReminderOverdueSent(int $id, string $now): void
    {
        $this->db->execute(
            'UPDATE tasks SET reminder_overdue_sent_at = ? WHERE id = ?',
            [$now, $id]
        );
    }

    // -------------------------------------------------------------------------
    // Cron / scheduler queries
    // -------------------------------------------------------------------------

    /**
     * Return all tasks the scheduler should execute on this pass.
     *
     * Conditions:
     *   - assigned_type = 'cron'
     *   - status IN ('open', 'in_progress')
     *   - execution_type IS NOT NULL
     *   - deleted_at IS NULL
     *
     * Only the fields needed for dispatch are selected; the scheduler does not
     * need display columns (username, etc.).
     *
     * @return array<int, array>
     */
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

    /**
     * Record the outcome of a scheduler execution attempt.
     *
     * Updates last_run_at, last_run_status, last_run_message, and updated_at.
     *
     * @param  string      $status   'ok' or 'failed'
     * @param  string|null $message  Truncated combined stdout+stderr (may be null)
     */
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
