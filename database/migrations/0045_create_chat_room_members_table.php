<?php

use App\Core\Migration;

/**
 * Create the chat_room_members table.
 *
 * Tracks which users are members of which rooms, their role, when they joined,
 * and when they last read the room (for future unread-count tracking).
 *
 * Columns:
 *   id           — surrogate PK
 *   room_id      — FK to chat_rooms.id; CASCADE on room deletion
 *   user_id      — FK to users.id; CASCADE on user deletion
 *   role         — 'owner' (room creator) or 'member'
 *   joined_at    — ISO-8601 timestamp when membership was created
 *   last_read_at — ISO-8601 timestamp of the user's last visit to this room;
 *                  NULL = never viewed since joining. Used for future unread counting.
 *
 * UNIQUE(room_id, user_id) prevents duplicate memberships.
 *
 * Migration number: 0045
 */
class CreateChatRoomMembersTable extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS chat_room_members (
                id           INTEGER     NOT NULL,
                room_id      INTEGER     NOT NULL,
                user_id      INTEGER     NOT NULL,
                role         VARCHAR(32) NOT NULL DEFAULT 'member',
                joined_at    VARCHAR(32) NOT NULL,
                last_read_at VARCHAR(32),

                PRIMARY KEY (id),
                UNIQUE  (room_id, user_id),
                FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id)      ON DELETE CASCADE
            )
        ");

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS chat_room_members_room_id ON chat_room_members (room_id)'
        );
        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS chat_room_members_user_id ON chat_room_members (user_id)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS chat_room_members');
    }
}
