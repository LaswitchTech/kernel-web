<?php

namespace App\Modules\Chat\Models;

use App\Core\DatabaseInterface;

/**
 * All database queries for the chat_messages table.
 *
 * Returns raw arrays; no domain objects.
 */
class ChatMessageRepository
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Return the most recent N messages for a room in chronological order
     * (oldest first, newest last — suitable for chat display).
     *
     * Fetches the last $limit rows ordered DESC, then reverses in PHP so the
     * caller always receives chronological order regardless of limit.
     *
     * JOINs users so callers receive author display name without an extra query.
     *
     * @return array<int, array>
     */
    public function findRecentByRoom(int $roomId, int $limit = 50): array
    {
        $rows = $this->db->fetch(
            "SELECT msg.id,
                    msg.room_id,
                    msg.author_type,
                    msg.author_user_id,
                    msg.body,
                    msg.created_at,
                    msg.updated_at,
                    u.username,
                    u.display_name
             FROM   chat_messages msg
             LEFT   JOIN users u ON u.id = msg.author_user_id
             WHERE  msg.room_id = ?
             ORDER  BY msg.created_at DESC
             LIMIT  ?",
            [$roomId, $limit]
        );

        return array_reverse($rows);
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new message row.
     *
     * @return int  The new message ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "INSERT INTO chat_messages (room_id, author_type, author_user_id, body, created_at)
             VALUES (?, ?, ?, ?, ?)",
            [
                $data['room_id'],
                $data['author_type'],
                $data['author_user_id'] ?? null,
                $data['body'],
                $now,
            ]
        );

        return (int) $this->db->lastInsertId();
    }
}
