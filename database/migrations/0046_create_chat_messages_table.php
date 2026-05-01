<?php

use App\Core\Migration;

/**
 * Create the chat_messages table.
 *
 * Stores all messages in all chat rooms.
 *
 * Columns:
 *   id             — surrogate PK
 *   room_id        — FK to chat_rooms.id; CASCADE on room deletion
 *   author_type    — who wrote the message:
 *                      'user'   — a human user (author_user_id required)
 *                      'system' — an automated system message (author_user_id NULL)
 *                      'agent'  — a future AI agent (author_user_id NULL)
 *   author_user_id — FK to users.id; SET NULL on user deletion.
 *                    Only populated when author_type = 'user'.
 *   body           — message text (plaintext; max length enforced at service layer)
 *   created_at     — ISO-8601 send timestamp
 *   updated_at     — ISO-8601 last-edit timestamp; NULL = never edited (reserved for future)
 *
 * Migration number: 0046
 */
class CreateChatMessagesTable extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id             INTEGER     NOT NULL,
                room_id        INTEGER     NOT NULL,
                author_type    VARCHAR(32) NOT NULL DEFAULT 'user',
                author_user_id INTEGER,
                body           TEXT        NOT NULL,
                created_at     VARCHAR(32) NOT NULL,
                updated_at     VARCHAR(32),

                PRIMARY KEY (id),
                FOREIGN KEY (room_id)        REFERENCES chat_rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (author_user_id) REFERENCES users(id)      ON DELETE SET NULL
            )
        ");

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS chat_messages_room_id    ON chat_messages (room_id)'
        );
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS chat_messages_room_ts    ON chat_messages (room_id, created_at)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS chat_messages');
    }
}
