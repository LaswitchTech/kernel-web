<?php

use App\Core\Migration;

class Create{{pascal_slug}}Table extends Migration
{
    public function up(): void
    {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS {{table}} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )"
        );
    }

    public function down(): void
    {
        $this->db->execute('DROP TABLE IF EXISTS {{table}}');
    }
}
