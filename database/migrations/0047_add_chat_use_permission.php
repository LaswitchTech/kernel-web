<?php

use App\Core\Migration;

/**
 * Add the chat.use permission and grant it to the admin group.
 *
 * chat.use gates all Chat module routes (/chat and sub-routes).
 * Granted to the admin group by default.  Future phases may grant it
 * to additional groups without removing admin access.
 *
 * Both INSERT statements use WHERE NOT EXISTS to make the migration idempotent:
 * running it more than once is safe and produces no duplicate rows.
 *
 * Migration number: 0047
 */
class AddChatUsePermission extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            "INSERT INTO permissions (name, description)
             SELECT 'chat.use', 'Access the Chat module — view rooms and send messages'
             WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'chat.use')"
        );

        $this->db->pdo()->exec(
            "INSERT INTO group_permissions (group_id, permission_id)
             SELECT g.id, p.id
             FROM groups g
             CROSS JOIN permissions p
             WHERE g.name = 'admin'
               AND p.name = 'chat.use'
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
             WHERE permission_id = (SELECT id FROM permissions WHERE name = 'chat.use')"
        );

        $this->db->pdo()->exec(
            "DELETE FROM permissions WHERE name = 'chat.use'"
        );
    }
}
