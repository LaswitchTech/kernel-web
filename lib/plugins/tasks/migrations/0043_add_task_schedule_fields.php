<?php

use App\Core\Migration;

/**
 * Add schedule control fields to the tasks table.
 *
 * Two nullable columns control when the scheduler executes a cron task:
 *
 *   schedule_type   VARCHAR(32) NULL
 *     'always'   — run on every scheduler tick (default behaviour when NULL)
 *     'interval' — run only when at least schedule_value seconds have elapsed
 *                  since last_run_at.  Never run if last_run_at is NULL.
 *     'cron'     — run when the current minute matches a 5-field cron expression
 *                  stored in schedule_value (minute hour dom month dow).
 *                  Supported per-field syntax: wildcard (*), step (star-slash-N), exact integer.
 *
 *   schedule_value  TEXT NULL
 *     'interval' — positive integer string (seconds between runs, e.g. '3600')
 *     'cron'     — 5-field cron expression string (e.g. '0 2 * * *')
 *     'always'   — not used; must be NULL
 *
 * These fields are only meaningful when assigned_type = 'cron'.
 * Non-cron tasks must leave both columns NULL.
 *
 * When schedule_type IS NULL the scheduler treats the task as 'always'
 * (backward-compatible: existing tasks run every tick until explicitly reconfigured).
 *
 * Validation is enforced at the service layer (TaskService::validate()).
 * The scheduler uses shouldRunTask() to evaluate the schedule at run time.
 *
 * SQLite requires one ALTER TABLE statement per column (no multi-column ADD).
 *
 * Migration number: 0043
 */
class AddTaskScheduleFields extends Migration
{
    public function up(): void
    {
        $pdo = $this->db->pdo();

        $pdo->exec('ALTER TABLE tasks ADD COLUMN schedule_type  VARCHAR(32)');
        $pdo->exec('ALTER TABLE tasks ADD COLUMN schedule_value TEXT');
    }

    public function down(): void
    {
        // SQLite < 3.35.0 does not support ALTER TABLE … DROP COLUMN.
        // Recreate the table without the two schedule columns.
        // Current full schema includes all columns added through migration 0037.
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
                last_run_at              VARCHAR(32),
                last_run_status          VARCHAR(32),
                last_run_message         TEXT,

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
                 assigned_type, assigned_id, execution_type, execution_payload,
                 last_run_at, last_run_status, last_run_message)
            SELECT
                 id, title, description, status, due_at,
                 entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload,
                 last_run_at, last_run_status, last_run_message
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
