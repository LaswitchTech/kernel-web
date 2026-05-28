<?php

use App\Core\Migration;

/**
 * Create task_activity table for per-task event tracking.
 *
 * Each row records a discrete event on a task (status change, reassignment,
 * priority change, creation, etc.). This provides an audit trail for the
 * task lifecycle, distinct from the admin_audit_log which logs all system-wide
 * admin actions.
 *
 * Event types:
 *   task_created     — task was created
 *   status_changed   — status was changed
 *   assigned         — task was assigned or reassigned
 *   priority_changed — priority was changed
 *   completed        — task was completed
 *   canceled         — task was canceled
 *   deleted          — task was soft-deleted
 *
 * Migration number: 0057
 */
class CreateTaskActivityTable extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS task_activity (
                id          INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
                task_id     INTEGER      NOT NULL,
                event_type  VARCHAR(32)  NOT NULL,
                user_id     INTEGER      NULL,
                old_value   TEXT,
                new_value   TEXT,
                meta        TEXT         NOT NULL DEFAULT '{}',
                created_at  VARCHAR(32)  NOT NULL,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )"
        );

        $this->db->execute(
            'CREATE INDEX IF NOT EXISTS task_activity_task_id ON task_activity (task_id)'
        );
        $this->db->execute(
            'CREATE INDEX IF NOT EXISTS task_activity_created_at ON task_activity (created_at DESC)'
        );
        $this->db->execute(
            'CREATE INDEX IF NOT EXISTS task_activity_event_type ON task_activity (event_type)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS task_activity');
    }
}
