<?php

namespace Database\Migrations;

use App\Core\Migration;

/**
 * Create hierarchical locations table.
 *
 * Locations provide a generic way to organize entities within an application
 * (e.g., site -> building -> floor -> room, or any other hierarchy).
 * This is a kernel-level feature, not specific to any application domain.
 */
class Migration_0038_create_locations_table extends Migration
{
    public function up(): void
    {
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS locations (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       VARCHAR(128)  NOT NULL,
                type       VARCHAR(32)   NOT NULL DEFAULT 'other',
                parent_id  INTEGER       NULL REFERENCES locations(id),
                description TEXT,
                created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        $this->db->execute('DROP TABLE IF EXISTS locations');
    }
}
