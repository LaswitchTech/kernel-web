<?php

namespace App\Modules\Chat\Models;

use App\Core\DatabaseInterface;

/**
 * All database queries for the chat_room_members table.
 *
 * Returns raw arrays; no domain objects.
 */
class ChatRoomMemberRepository
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
     * Return all members of a room, ordered by join date ascending.
     *
     * JOINs users so callers receive username and display_name without
     * an additional query.
     *
     * @return array<int, array>
     */
    public function findByRoom(int $roomId): array
    {
        return $this->db->fetch(
            "SELECT m.id,
                    m.room_id,
                    m.user_id,
                    m.role,
                    m.joined_at,
                    m.last_read_at,
                    u.username,
                    u.display_name
             FROM   chat_room_members m
             JOIN   users u ON u.id = m.user_id
             WHERE  m.room_id = ?
             ORDER  BY m.joined_at ASC",
            [$roomId]
        );
    }

    /**
     * Find a specific user's membership in a room.
     *
     * Returns null if the user is not a member.
     */
    public function findMembership(int $roomId, int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT id, room_id, user_id, role, joined_at, last_read_at
             FROM   chat_room_members
             WHERE  room_id = ? AND user_id = ?",
            [$roomId, $userId]
        );
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new membership row.
     *
     * @return int  The new membership ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "INSERT INTO chat_room_members (room_id, user_id, role, joined_at)
             VALUES (?, ?, ?, ?)",
            [
                $data['room_id'],
                $data['user_id'],
                $data['role'] ?? 'member',
                $now,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Return the total number of unread messages across all rooms the user is a member of.
     *
     * Unread definition:
     *   - message.created_at > last_read_at  (last_read_at IS NULL → all messages unread)
     *   - author_user_id != $userId          (own messages excluded)
     *   - author_user_id IS NULL             (system/agent messages are always counted)
     *
     * Used by the sidebar badge to show a global unread indicator.
     * Only counts rooms where the user has an explicit membership row.
     */
    public function countUnreadForUser(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n
             FROM   chat_messages msg
             JOIN   chat_room_members m ON m.room_id = msg.room_id AND m.user_id = ?
             WHERE  (msg.author_user_id IS NULL OR msg.author_user_id != ?)
               AND  (m.last_read_at IS NULL OR msg.created_at > m.last_read_at)",
            [$userId, $userId]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Update the last_read_at timestamp for a membership row.
     *
     * Called whenever a member opens a room, enabling unread-count tracking.
     */
    public function updateLastRead(int $roomId, int $userId, string $now): void
    {
        $this->db->execute(
            "UPDATE chat_room_members
             SET last_read_at = ?
             WHERE room_id = ? AND user_id = ?",
            [$now, $roomId, $userId]
        );
    }
}
