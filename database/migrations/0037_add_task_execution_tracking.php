<?php

use App\Core\Migration;

/**
 * Add execution run-tracking columns to the tasks table.
 *
 * These three columns record the outcome of the most recent scheduler
 * execution for cron-assigned tasks.  They give at-a-glance visibility into
 * whether a scheduled task is running successfully without requiring a
 * separate execution-history table.
 *
 *   last_run_at      — timestamp of the most recent execution attempt
 *   last_run_status  — 'ok' (exit 0) or 'failed' (non-zero exit); NULL = never run
 *   last_run_message — truncated combined stdout+stderr from the last run; NULL = none
 *
 * Only tasks with assigned_type='cron' and a non-null execution_type will
 * ever have these fields populated.  For user-assigned or unassigned tasks
 * they remain NULL throughout the task's lifecycle.
 *
 * SQLite requires one ALTER TABLE statement per column (no multi-column ADD).
 *
 * Migration number: 0037
 */
class AddTaskExecutionTracking extends Migration
{
    public function up(): void
    {
        $pdo = $this->db->pdo();
        $pdo->exec('ALTER TABLE tasks ADD COLUMN last_run_at      VARCHAR(32)');
        $pdo->exec('ALTER TABLE tasks ADD COLUMN last_run_status  VARCHAR(32)');
        $pdo->exec('ALTER TABLE tasks ADD COLUMN last_run_message TEXT');
    }

    public function down(): void
    {
        // SQLite < 3.35.0 does not support ALTER TABLE … DROP COLUMN.
        // Recreate the table without the three new columns.
        $pdo = $this->db->pdo();

        $pdo->exec("
            CREATE TABLE tasks_new (
                id                       INTEGER      NOT NULL,
                title                    VARCHAR(255) NOT NULL,
                description              TEXT,
                status                   VARCHAR(32)  NOT NULL DEFAULT 'open',
                due_at                   VARCHAR(32),
                entity_type              VARCHAR(64),
                entity_id                INTEGER,
                created_by_user_id       INTEGER,
                created_at               VARCHAR(32)  NOT NULL,
                updated_at               VARCHAR(32)  NOT NULL,
                reminder_due_sent_at     VARCHAR(32),
                reminder_overdue_sent_at VARCHAR(32),
                deleted_at               VARCHAR(32),
                assigned_type            TEXT,
                assigned_id              INTEGER,
                execution_type           TEXT,
                execution_payload        TEXT,

                PRIMARY KEY (id),
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            INSERT INTO tasks_new
                (id, title, description, status, due_at,
                 entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload)
            SELECT
                 id, title, description, status, due_at,
                 entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload
            FROM tasks
        ");

        $pdo->exec('DROP TABLE tasks');
        $pdo->exec('ALTER TABLE tasks_new RENAME TO tasks');

        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_entity             ON tasks (entity_type, entity_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_created_by_user_id ON tasks (created_by_user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_status             ON tasks (status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_due_at             ON tasks (due_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_deleted_at         ON tasks (deleted_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_assigned_type      ON tasks (assigned_type, assigned_id)');
    }
}
