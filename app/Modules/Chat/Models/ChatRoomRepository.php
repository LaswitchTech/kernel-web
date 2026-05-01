<?php

namespace App\Modules\Chat\Models;

use App\Core\DatabaseInterface;

/**
 * All database queries for the chat_rooms table.
 *
 * Returns raw arrays; no domain objects.
 */
class ChatRoomRepository
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
     * Return all rooms ordered by name.
     *
     * Includes aggregate counts for member_count and message_count.
     *
     * @return array<int, array>
     */
    public function findAll(): array
    {
        return $this->db->fetch(
            "SELECT r.id,
                    r.name,
                    r.description,
                    r.type,
                    r.created_by_user_id,
                    r.created_at,
                    r.updated_at,
                    u.username     AS created_username,
                    u.display_name AS created_display,
                    (SELECT COUNT(*) FROM chat_room_members m   WHERE m.room_id   = r.id) AS member_count,
                    (SELECT COUNT(*) FROM chat_messages     msg WHERE msg.room_id  = r.id) AS message_count
             FROM   chat_rooms r
             LEFT   JOIN users u ON u.id = r.created_by_user_id
             ORDER  BY r.name ASC",
            []
        );
    }

    /**
     * Return all rooms visible to a specific user:
     *   - all 'shared' and 'system' rooms
     *   - 'private' rooms where the user is a member
     *
     * Also returns:
     *   user_role    — the user's role in the room (NULL if not a member)
     *   unread_count — messages since last_read_at not authored by this user;
     *                  0 for non-members (no membership row → no tracking baseline)
     *
     * Unread rules:
     *   - Only messages where author_user_id != $userId count (own messages excluded).
     *   - System/agent messages (author_user_id IS NULL) are always counted.
     *   - When last_read_at IS NULL the user has never opened the room since joining,
     *     so every qualifying message is unread.
     *
     * @return array<int, array>
     */
    public function findForUser(int $userId): array
    {
        return $this->db->fetch(
            "SELECT r.id,
                    r.name,
                    r.description,
                    r.type,
                    r.created_by_user_id,
                    r.created_at,
                    r.updated_at,
                    u.username     AS created_username,
                    u.display_name AS created_display,
                    (SELECT COUNT(*) FROM chat_room_members m   WHERE m.room_id  = r.id) AS member_count,
                    (SELECT COUNT(*) FROM chat_messages     msg WHERE msg.room_id = r.id) AS message_count,
                    mem.role       AS user_role,
                    CASE WHEN mem.user_id IS NOT NULL THEN (
                        SELECT COUNT(*)
                        FROM   chat_messages msg2
                        WHERE  msg2.room_id = r.id
                          AND  (msg2.author_user_id IS NULL OR msg2.author_user_id != ?)
                          AND  (mem.last_read_at IS NULL OR msg2.created_at > mem.last_read_at)
                    ) ELSE 0 END AS unread_count
             FROM   chat_rooms r
             LEFT   JOIN users u ON u.id = r.created_by_user_id
             LEFT   JOIN chat_room_members mem
                         ON mem.room_id = r.id AND mem.user_id = ?
             WHERE  r.type IN ('shared', 'system')
                OR  mem.user_id IS NOT NULL
             ORDER  BY r.name ASC",
            [$userId, $userId]
        );
    }

    /**
     * Find a single room by ID.
     *
     * Returns null if the room does not exist.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT r.id,
                    r.name,
                    r.description,
                    r.type,
                    r.created_by_user_id,
                    r.created_at,
                    r.updated_at,
                    u.username     AS created_username,
                    u.display_name AS created_display,
                    (SELECT COUNT(*) FROM chat_room_members m   WHERE m.room_id   = r.id) AS member_count,
                    (SELECT COUNT(*) FROM chat_messages     msg WHERE msg.room_id  = r.id) AS message_count
             FROM   chat_rooms r
             LEFT   JOIN users u ON u.id = r.created_by_user_id
             WHERE  r.id = ?",
            [$id]
        );
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new chat room.
     *
     * @return int  The new room ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "INSERT INTO chat_rooms (name, description, type, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['description']        ?? null,
                $data['type'],
                $data['created_by_user_id'] ?? null,
                $now,
                $now,
            ]
        );

        return (int) $this->db->lastInsertId();
    }
}
