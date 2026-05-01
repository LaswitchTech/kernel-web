<?php

use App\Core\Migration;

/**
 * Add soft-delete support to the tasks table.
 *
 * deleted_at stores the timestamp when a task was soft-deleted.
 * NULL means the task is active; a non-NULL value means it is deleted.
 *
 * All default read queries in TaskRepository filter with WHERE deleted_at IS NULL
 * so soft-deleted rows are invisible to the application without an explicit
 * opt-in.  Hard deletes are intentionally not supported — tasks are preserved
 * for historical integrity even after deletion.
 *
 * A sparse index on deleted_at keeps active-row scans fast: the vast majority
 * of rows will be NULL and the index will be very small.
 */
class AddTasksDeletedAt extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            'ALTER TABLE tasks ADD COLUMN deleted_at VARCHAR(32)'
        );

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS tasks_deleted_at ON tasks (deleted_at)'
        );
    }

    public function down(): void
    {
        // SQLite < 3.35.0 does not support ALTER TABLE ... DROP COLUMN.
        // Recreate the table without the deleted_at column for full portability.
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
                reminder_due_sent_at     VARCHAR(32),
                reminder_overdue_sent_at VARCHAR(32),

                PRIMARY KEY (id),
                FOREIGN KEY (assigned_user_id)   REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            INSERT INTO tasks_new
                (id, title, description, status, assigned_user_id, due_at,
                 entity_type, entity_id, created_by_user_id, created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at)
            SELECT
                 id, title, description, status, assigned_user_id, due_at,
                 entity_type, entity_id, created_by_user_id, created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at
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
