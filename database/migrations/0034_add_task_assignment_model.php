<?php

use App\Core\Migration;

/**
 * Extend the tasks table with a generic assignment model and execution hooks.
 *
 * ## Assignment model
 *
 * The original tasks schema used a single `assigned_user_id` FK to reference
 * a human user.  This migration adds a polymorphic assignment pair so tasks
 * can later be assigned to any type of actor (human user, AI agent, etc.):
 *
 *   assigned_type  TEXT NULL   — assignment actor type: 'user' | 'agent' | 'cron' | NULL
 *   assigned_id    INTEGER NULL — primary key of the actor in its own table
 *
 * Rules (enforced at the service layer):
 *   - NULL / NULL  = unassigned (no actor)
 *   - 'user' / X   = assigned to users.id = X  (equivalent to assigned_user_id = X)
 *   - 'agent' / X  = assigned to a future agent record with id = X
 *   - 'cron' / NULL = reserved for scheduled/automated tasks; no actor ID applies
 *
 * `assigned_user_id` is kept for backward compatibility in Phase 1.  Queries
 * that filter by user use an OR condition covering both the legacy column and
 * the new polymorphic pair.  The legacy column will be retired in a future
 * migration once all write paths have been updated to populate `assigned_type`
 * and `assigned_id`.
 *
 * ## Execution model
 *
 * Two columns reserve schema space for future automated task execution:
 *
 *   execution_type     TEXT NULL   — executor identifier (e.g. 'cron')
 *   execution_payload  TEXT NULL   — JSON payload consumed by the executor
 *
 * These columns are NOT acted on in Phase 1.  No execution engine exists yet.
 * The columns are added now so future migrations do not need to change the
 * tasks table structure.
 *
 * SQLite requires one ALTER TABLE statement per column (no multi-column ADD).
 */
class AddTaskAssignmentModel extends Migration
{
    public function up(): void
    {
        $pdo = $this->db->pdo();

        $pdo->exec('ALTER TABLE tasks ADD COLUMN assigned_type    TEXT');
        $pdo->exec('ALTER TABLE tasks ADD COLUMN assigned_id      INTEGER');
        $pdo->exec('ALTER TABLE tasks ADD COLUMN execution_type   TEXT');
        $pdo->exec('ALTER TABLE tasks ADD COLUMN execution_payload TEXT');

        // Sparse index on assigned_type for future queries filtering by actor type.
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS tasks_assigned_type ON tasks (assigned_type, assigned_id)'
        );
    }

    public function down(): void
    {
        // SQLite < 3.35.0 does not support ALTER TABLE … DROP COLUMN.
        // Recreate the table without the four new columns.
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

                PRIMARY KEY (id),
                FOREIGN KEY (assigned_user_id)   REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            INSERT INTO tasks_new
                (id, title, description, status, assigned_user_id, due_at,
                 entity_type, entity_id, created_by_user_id, created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at)
            SELECT
                 id, title, description, status, assigned_user_id, due_at,
                 entity_type, entity_id, created_by_user_id, created_at, updated_at,
                 reminder_due_sent_at, reminder_overdue_sent_at, deleted_at
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
    }
}
