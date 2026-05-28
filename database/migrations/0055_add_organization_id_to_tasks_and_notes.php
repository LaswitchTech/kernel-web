<?php

use App\Core\Migration;

/**
 * Add organization_id to tasks and notes tables for multi-tenant data scoping.
 *
 * Both columns are nullable:
 *   NULL = unscoped / public (existing behavior preserved)
 *   INT  = scoped to the given organization
 *
 * Indexes on organization_id are created per-table to avoid
 * SQLite's lack of IF NOT EXISTS on CREATE INDEX.
 *
 * Migration number: 0055
 */
class Migration0055AddOrganizationIdToTasksAndNotes extends Migration
{
    public function up(): void
    {
        // ---------- tasks ----------

        $this->db->execute(
            'ALTER TABLE tasks ADD COLUMN organization_id INTEGER'
        );

        $this->db->execute(
            'CREATE INDEX IF NOT EXISTS tasks_organization_id ON tasks (organization_id)'
        );

        // ---------- notes ----------

        $this->db->execute(
            'ALTER TABLE notes ADD COLUMN organization_id INTEGER'
        );

        $this->db->execute(
            'CREATE INDEX IF NOT EXISTS notes_organization_id ON notes (organization_id)'
        );
    }

    public function down(): void
    {
        // SQLite < 3.35.0 cannot DROP COLUMN. Recreate the table.
        $this->db->pdo()->exec("
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
                schedule_type            VARCHAR(32),
                schedule_value           TEXT,

                PRIMARY KEY (id),
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $this->db->pdo()->exec("
            INSERT INTO tasks_new
                (id, title, description, status, due_at,
                 entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload,
                 last_run_at, last_run_status, last_run_message,
                 schedule_type, schedule_value)
            SELECT
                 id, title, description, status, due_at,
                 entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload,
                 last_run_at, last_run_status, last_run_message,
                 schedule_type, schedule_value
            FROM tasks
        ");

        $this->db->pdo()->exec('DROP TABLE tasks');
        $this->db->pdo()->exec('ALTER TABLE tasks_new RENAME TO tasks');

        $this->db->pdo()->exec('CREATE INDEX IF NOT EXISTS tasks_entity             ON tasks (entity_type, entity_id)');
        $this->db->pdo()->exec('CREATE INDEX IF NOT EXISTS tasks_created_by_user_id ON tasks (created_by_user_id)');
        $this->db->pdo()->exec('CREATE INDEX IF NOT EXISTS tasks_status             ON tasks (status)');
        $this->db->pdo()->exec('CREATE INDEX IF NOT EXISTS tasks_due_at             ON tasks (due_at)');
        $this->db->pdo()->exec('CREATE INDEX IF NOT EXISTS tasks_deleted_at         ON tasks (deleted_at)');
        $this->db->pdo()->exec('CREATE INDEX IF NOT EXISTS tasks_assigned_type      ON tasks (assigned_type, assigned_id)');

        // Notes has only one dependent migration after it, so simple recreate is safe.
        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS notes_new (
                id          INTEGER      NOT NULL,
                entity_type VARCHAR(64)  NOT NULL,
                entity_id   INTEGER      NOT NULL,
                user_id     INTEGER,
                content     TEXT         NOT NULL,
                created_at  VARCHAR(32)  NOT NULL,
                updated_at  VARCHAR(32)  NOT NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )"
        );

        $this->db->pdo()->exec("
            INSERT INTO notes_new (id, entity_type, entity_id, user_id, content, created_at, updated_at)
            SELECT id, entity_type, entity_id, user_id, content, created_at, updated_at
            FROM notes
        ");

        $this->db->pdo()->exec('DROP TABLE IF EXISTS notes');
        $this->db->pdo()->exec('ALTER TABLE notes_new RENAME TO notes');

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS notes_entity ON notes (entity_type, entity_id)'
        );
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS notes_user_id ON notes (user_id)'
        );
    }
}
