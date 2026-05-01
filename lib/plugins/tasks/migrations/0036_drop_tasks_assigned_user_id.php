<?php

use App\Core\Migration;

/**
 * Final cleanup of the legacy tasks assignment column.
 *
 * `assigned_user_id` was introduced in migration 0030 as a direct FK to users.
 * It was deprecated in favour of the polymorphic (assigned_type, assigned_id)
 * pair added in migration 0034:
 *
 *   0034 — added assigned_type, assigned_id columns
 *   0035 — backfilled assigned_type='user', assigned_id=assigned_user_id for all existing rows
 *   Phase 3 — controller write path switched to assigned_type/assigned_id
 *   Phase 4 — all reads and reminders switched to the canonical columns
 *   0036 (this migration) — drops assigned_user_id permanently
 *
 * SQLite < 3.35.0 does not support ALTER TABLE … DROP COLUMN.
 * This migration uses the table-recreation pattern for full compatibility.
 *
 * down() restores the column and repopulates it from the canonical fields:
 *   assigned_user_id = assigned_id  WHERE assigned_type = 'user'
 *
 * Migration number: 0036
 */
class DropTasksAssignedUserId extends Migration
{
    public function up(): void
    {
        $pdo = $this->db->pdo();

        // Recreate table without assigned_user_id and its FK.
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

        // Restore all indexes (tasks_assigned_user_id is intentionally omitted).
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_entity             ON tasks (entity_type, entity_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_created_by_user_id ON tasks (created_by_user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_status             ON tasks (status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_due_at             ON tasks (due_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_deleted_at         ON tasks (deleted_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_assigned_type      ON tasks (assigned_type, assigned_id)');
    }

    public function down(): void
    {
        // Restore the column and its index.  Repopulate from canonical fields.
        $pdo = $this->db->pdo();

        $pdo->exec("
            CREATE TABLE tasks_new (
                id                       INTEGER      NOT NULL,
                title                    VARCHAR(255) NOT NULL,
                description              TEXT,
                status                   VARCHAR(32)  NOT NULL DEFAULT 'open',
                assigned_user_id         INTEGER,
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
                FOREIGN KEY (assigned_user_id)   REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        // Repopulate assigned_user_id from the canonical fields where applicable.
        $pdo->exec("
            INSERT INTO tasks_new
                (id, title, description, status,
                 assigned_user_id,
                 due_at, entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload)
            SELECT
                 id, title, description, status,
                 CASE WHEN assigned_type = 'user' THEN assigned_id ELSE NULL END,
                 due_at, entity_type, entity_id, created_by_user_id,
                 created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at,
                 assigned_type, assigned_id, execution_type, execution_payload
            FROM tasks
        ");

        $pdo->exec('DROP TABLE tasks');
        $pdo->exec('ALTER TABLE tasks_new RENAME TO tasks');

        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_entity             ON tasks (entity_type, entity_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_assigned_user_id   ON tasks (assigned_user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_created_by_user_id ON tasks (created_by_user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_status             ON tasks (status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_due_at             ON tasks (due_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_deleted_at         ON tasks (deleted_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS tasks_assigned_type      ON tasks (assigned_type, assigned_id)');
    }
}
