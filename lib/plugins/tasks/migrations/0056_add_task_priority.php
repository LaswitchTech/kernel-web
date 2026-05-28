<?php

use App\Core\Migration;

/**
 * Add priority column to tasks table.
 *
 * Priority values:
 *   -1 = low
 *    0 = medium (default)
 *    1 = high
 *    2 = critical
 *
 * Tasks with higher priority are sorted before lower-priority tasks
 * in the default task list ordering.
 *
 * Migration number: 0056
 */
class AddTaskPriority extends Migration
{
    public function up(): void
    {
        $this->db->execute('ALTER TABLE tasks ADD COLUMN priority INTEGER NOT NULL DEFAULT 0');
        $this->db->execute(
            'CREATE INDEX IF NOT EXISTS tasks_priority ON tasks (priority)'
        );
    }

    public function down(): void
    {
        // SQLite < 3.35.0 cannot DROP COLUMN. Recreate the table.
        $pdo = $this->db->pdo();

        $pdo->exec("
            CREATE TABLE tasks_new (
                id                       INTEGER      NOT NULL,
                title                    VARCHAR(255)  NOT NULL,
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
                schedule_type            VARCHAR(32),
                schedule_value           TEXT,
                organization_id          INTEGER,

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
                 last_run_at, last_run_status, last_run_message,
                 schedule_type, schedule_value, organization_id)
            SELECT
                 id, title, description, status, due_at,
                 entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload,
                 last_run_at, last_run_status, last_run_message,
                 schedule_type, schedule_value, organization_id
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
