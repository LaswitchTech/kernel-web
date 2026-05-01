<?php

use App\Core\Migration;

/**
 * Create the chat_rooms table.
 *
 * Columns:
 *   id                 — surrogate PK
 *   name               — human-readable room name (max 100 chars)
 *   description        — optional free-text description
 *   type               — room visibility model:
 *                          'shared'  — any authenticated user with chat.use may view and join
 *                          'private' — only members may view; membership is explicit
 *                          'system'  — automated/agent-generated; users cannot join via UI
 *   created_by_user_id — FK to users.id; SET NULL on user deletion
 *   created_at         — ISO-8601 creation timestamp
 *   updated_at         — ISO-8601 last-update timestamp
 *
 * Migration number: 0044
 */
class CreateChatRoomsTable extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS chat_rooms (
                id                 INTEGER      NOT NULL,
                name               VARCHAR(100) NOT NULL,
                description        TEXT,
                type               VARCHAR(32)  NOT NULL DEFAULT 'shared',
                created_by_user_id INTEGER,
                created_at         VARCHAR(32)  NOT NULL,
                updated_at         VARCHAR(32)  NOT NULL,

                PRIMARY KEY (id),
                FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS chat_rooms_type ON chat_rooms (type)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS chat_rooms');
    }
}
