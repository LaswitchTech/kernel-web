<?php

use App\Core\Migration;

/**
 * Add reminder tracking columns to the tasks table.
 *
 * Two nullable VARCHAR(32) columns track when each reminder type was last
 * dispatched for a task.  The task-reminders worker uses them to prevent
 * duplicate notifications.
 *
 * Deduplication rules enforced by scripts/task-reminders.php:
 *
 *   reminder_due_sent_at
 *     Set when the "due today" reminder is enqueued.
 *     Condition to send:  IS NULL
 *     Effect:             fires at most once per task, on the due date.
 *
 *   reminder_overdue_sent_at
 *     Set when the "overdue" reminder is enqueued.
 *     Condition to send:  IS NULL
 *     Effect:             fires exactly once per task, the first time the
 *                         worker runs after the due date has passed.
 *
 * Both columns default to NULL (never sent).  Completing or cancelling a task
 * prevents it from being selected by the reminder queries — no explicit reset
 * of these fields is required.
 *
 * SQLite requires one ALTER TABLE statement per column (no multi-column ADD).
 */
class AddTaskReminderFields extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            'ALTER TABLE tasks ADD COLUMN reminder_due_sent_at     VARCHAR(32)'
        );
        $this->db->pdo()->exec(
            'ALTER TABLE tasks ADD COLUMN reminder_overdue_sent_at VARCHAR(32)'
        );
    }

    public function down(): void
    {
        // SQLite < 3.35.0 does not support ALTER TABLE ... DROP COLUMN.
        // Recreate the table without the reminder columns for full portability.
        $pdo = $this->db->pdo();

        $pdo->exec("
            CREATE TABLE tasks_new (
                id                  INTEGER      NOT NULL,
                title               VARCHAR(255) NOT NULL,
                description         TEXT,
                status              VARCHAR(32)  NOT NULL DEFAULT 'open',
                assigned_user_id    INTEGER,
                due_at              VARCHAR(32),
                entity_type         VARCHAR(64),
                entity_id           INTEGER,
                created_by_user_id  INTEGER,
                created_at          VARCHAR(32)  NOT NULL,
                updated_at          VARCHAR(32)  NOT NULL,

                PRIMARY KEY (id),
                FOREIGN KEY (assigned_user_id)   REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            INSERT INTO tasks_new
                (id, title, description, status, assigned_user_id, due_at,
                 entity_type, entity_id, created_by_user_id, created_at, updated_at)
            SELECT
                 id, title, description, status, assigned_user_id, due_at,
                 entity_type, entity_id, created_by_user_id, created_at, updated_at
            FROM tasks
        ");

        $pdo->exec('DROP TABLE tasks');
        $pdo->exec('ALTER TABLE tasks_new RENAME TO tasks');

        // Restore indexes dropped with the original table.
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_entity             ON tasks (entity_type, entity_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_assigned_user_id   ON tasks (assigned_user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_created_by_user_id ON tasks (created_by_user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_status             ON tasks (status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_due_at             ON tasks (due_at)');
    }
}
