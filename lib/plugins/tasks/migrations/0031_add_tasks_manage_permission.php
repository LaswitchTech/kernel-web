<?php

use App\Core\Migration;

/**
 * Add the tasks.manage permission and grant it to the admin group.
 *
 * tasks.manage gates all Task Management routes (list, create, edit, update).
 * It is granted to the admin group by default.  Future phases may grant it to
 * additional groups without removing admin access.
 *
 * The INSERT ... WHERE NOT EXISTS pattern makes this migration idempotent:
 * running it more than once is safe and produces no duplicate rows.
 */
class AddTasksManagePermission extends Migration
{
    public function up(): void
    {
        // Insert the permission if it does not already exist.
        $this->db->pdo()->exec(
            "INSERT INTO permissions (name, description)
             SELECT 'tasks.manage', 'Create, view, and update tasks across the application'
             WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'tasks.manage')"
        );

        // Grant tasks.manage to the admin group if the grant does not already exist.
        $this->db->pdo()->exec(
            "INSERT INTO group_permissions (group_id, permission_id)
             SELECT g.id, p.id
             FROM groups g
             CROSS JOIN permissions p
             WHERE g.name = 'admin'
               AND p.name = 'tasks.manage'
               AND NOT EXISTS (
                   SELECT 1 FROM group_permissions gp2
                   WHERE gp2.group_id = g.id AND gp2.permission_id = p.id
               )"
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec(
            "DELETE FROM group_permissions
             WHERE permission_id = (SELECT id FROM permissions WHERE name = 'tasks.manage')"
        );

        $this->db->pdo()->exec(
            "DELETE FROM permissions WHERE name = 'tasks.manage'"
        );
    }
}
