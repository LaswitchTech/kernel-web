<?php

use App\Core\Migration;

/**
 * Add the extensions.manage permission and grant it to the admin group.
 *
 * extensions.manage gates the admin Extensions management area
 * (read-only listing of discovered plugins, themes, and layouts).
 */
class AddExtensionsManagePermission extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            "INSERT INTO permissions (name, description)
             SELECT 'extensions.manage', 'Browse, inspect, and manage application extensions (plugins, themes, layouts)'
             WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'extensions.manage')"
        );

        $this->db->pdo()->exec(
            "INSERT INTO group_permissions (group_id, permission_id)
             SELECT g.id, p.id
             FROM groups g
             CROSS JOIN permissions p
             WHERE g.name = 'admin'
               AND p.name = 'extensions.manage'
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
             WHERE permission_id = (SELECT id FROM permissions WHERE name = 'extensions.manage')"
        );

        $this->db->pdo()->exec(
            "DELETE FROM permissions WHERE name = 'extensions.manage'"
        );
    }
}
