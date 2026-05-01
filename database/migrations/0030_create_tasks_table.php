<?php

use App\Core\Migration;

/**
 * Tasks module — reusable polymorphic task management.
 *
 * A task is a unit of work that may optionally be linked to any domain entity
 * via the (entity_type, entity_id) pair.  No foreign key is placed on entity_id
 * because the target may be any entity type across the application, and a
 * polymorphic FK is not expressible in relational SQL across entity types.
 * Application code is responsible for validating that the linked entity exists.
 *
 * User references use ON DELETE SET NULL so tasks are preserved when an account
 * is removed — they become unassigned rather than orphaned.
 *
 * Status lifecycle:
 *   open  →  in_progress  →  completed
 *                          →  canceled
 *   (any status may be set explicitly — no enforced state machine at the DB level)
 *
 * Schema is intentionally minimal for Phase 1.  Columns reserved for future
 * expansion without a schema change:
 *   - reminder_at  — timestamp for a single one-off reminder notification
 *   - priority     — integer priority weight (low/medium/high)
 *   - parent_id    — self-referential for subtasks (NULL in Phase 1)
 *   - completed_at — explicit completion timestamp
 *
 * Migration number: 0030
 */
class CreateTasksTable extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS tasks (
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

        // Hot path: fetch all tasks for a given entity (device, alert, finding, etc.)
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS tasks_entity
             ON tasks (entity_type, entity_id)'
        );

        // All tasks assigned to a given user.
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS tasks_assigned_user_id
             ON tasks (assigned_user_id)'
        );

        // All tasks created by a given user.
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS tasks_created_by_user_id
             ON tasks (created_by_user_id)'
        );

        // Filtering by status (e.g. "show all open tasks").
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS tasks_status
             ON tasks (status)'
        );

        // Sorting / filtering by due date (upcoming deadlines).
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS tasks_due_at
             ON tasks (due_at)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS tasks');
    }
}
