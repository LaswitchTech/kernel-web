<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\ChatRoomRepository;
use App\Modules\Chat\Models\ChatRoomMemberRepository;
use App\Modules\Chat\Models\ChatMessageRepository;

/**
 * Business logic for the Chat module.
 *
 * Responsibilities:
 *   - Room creation and access control
 *   - Membership management (join, isMember, canView)
 *   - Message sending and retrieval
 *   - Validation
 *
 * This service is intentionally free of NetMon-specific dependencies;
 * it can be reused in any application built on this platform.
 */
class ChatService
{
    /** Room visibility types. */
    public const ROOM_TYPES = ['private', 'shared', 'system'];

    /** Human-readable labels for room types. */
    public const ROOM_TYPE_LABELS = [
        'private' => 'Private',
        'shared'  => 'Shared',
        'system'  => 'System',
    ];

    /**
     * Who can author messages.
     *
     * 'user'   — a human user (author_user_id required)
     * 'system' — an automated system message (author_user_id NULL)
     * 'agent'  — a future AI agent (author_user_id NULL)
     */
    public const AUTHOR_TYPES = ['user', 'system', 'agent'];

    /** Membership role values. */
    public const MEMBER_ROLES = ['member', 'owner'];

    /** Maximum room name length (characters). */
    public const MAX_ROOM_NAME_LENGTH = 100;

    /** Maximum message body length (characters). */
    public const MAX_MESSAGE_LENGTH = 10000;

    /** Default number of recent messages to return. */
    public const DEFAULT_MESSAGE_LIMIT = 50;

    private ChatRoomRepository       $rooms;
    private ChatRoomMemberRepository $members;
    private ChatMessageRepository    $messages;

    public function __construct(
        ChatRoomRepository       $rooms,
        ChatRoomMemberRepository $members,
        ChatMessageRepository    $messages
    ) {
        $this->rooms   = $rooms;
        $this->members = $members;
        $this->messages = $messages;
    }

    // -------------------------------------------------------------------------
    // Rooms
    // -------------------------------------------------------------------------

    /**
     * Return all rooms visible to a user:
     *   - all 'shared' and 'system' rooms
     *   - 'private' rooms the user is a member of
     *
     * @return array<int, array>
     */
    public function getRoomsForUser(int $userId): array
    {
        return $this->rooms->findForUser($userId);
    }

    /**
     * Return a single room by ID, or null if not found.
     */
    public function getRoom(int $id): ?array
    {
        return $this->rooms->findById($id);
    }

    /**
     * Create a new chat room and add the creator as owner.
     *
     * System rooms do not receive a membership row for the creator
     * — they are machine-managed and have no human owner.
     *
     * @throws \InvalidArgumentException on validation failure
     * @return int  The new room ID
     */
    public function createRoom(array $data, int $creatorUserId): int
    {
        $errors = $this->validateRoom($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $roomId = $this->rooms->create([
            'name'               => trim($data['name']),
            'description'        => trim($data['description'] ?? '') ?: null,
            'type'               => $data['type'],
            'created_by_user_id' => $creatorUserId,
        ]);

        if ($data['type'] !== 'system') {
            $this->members->create([
                'room_id' => $roomId,
                'user_id' => $creatorUserId,
                'role'    => 'owner',
            ]);
        }

        return $roomId;
    }

    // -------------------------------------------------------------------------
    // Membership
    // -------------------------------------------------------------------------

    /**
     * Join a shared room.
     *
     * Idempotent: if the user is already a member the call succeeds silently.
     * Throws on non-shared rooms — private and system rooms cannot be joined
     * via this path (invite / automated mechanisms are deferred).
     *
     * @throws \InvalidArgumentException
     */
    public function joinRoom(int $roomId, int $userId): void
    {
        $room = $this->rooms->findById($roomId);
        if ($room === null) {
            throw new \InvalidArgumentException('Room not found.');
        }
        if ($room['type'] !== 'shared') {
            throw new \InvalidArgumentException('This room cannot be joined directly.');
        }

        // Already a member — nothing to do.
        if ($this->members->findMembership($roomId, $userId) !== null) {
            return;
        }

        $this->members->create([
            'room_id' => $roomId,
            'user_id' => $userId,
            'role'    => 'member',
        ]);
    }

    /**
     * Return true when the user is an explicit member of the room.
     */
    public function isMember(int $roomId, int $userId): bool
    {
        return $this->members->findMembership($roomId, $userId) !== null;
    }

    /**
     * Return true when the user may view the room.
     *
     * Access rules:
     *   - 'shared' and 'system' rooms: any authenticated user with chat.use may view
     *   - 'private' rooms: only explicit members
     *
     * Returns false if the room does not exist.
     */
    public function canView(int $roomId, int $userId): bool
    {
        $room = $this->rooms->findById($roomId);
        if ($room === null) {
            return false;
        }
        if (in_array($room['type'], ['shared', 'system'], true)) {
            return true;
        }
        return $this->isMember($roomId, $userId);
    }

    /**
     * Return all members of a room with their user display fields.
     *
     * @return array<int, array>
     */
    public function getMembersForRoom(int $roomId): array
    {
        return $this->members->findByRoom($roomId);
    }

    /**
     * Return the total number of unread messages across all rooms the user is a member of.
     *
     * Own messages and messages from rooms where the user has no membership are excluded.
     * Used by the sidebar badge.
     */
    public function countUnreadForUser(int $userId): int
    {
        return $this->members->countUnreadForUser($userId);
    }

    /**
     * Update the caller's last_read_at timestamp for the room.
     *
     * Only writes when the user is a member; silently no-ops otherwise.
     * Called after fetching messages in show() so the update reflects
     * exactly the messages the user just saw.
     */
    public function markRead(int $roomId, int $userId): void
    {
        if ($this->isMember($roomId, $userId)) {
            $this->members->updateLastRead($roomId, $userId, date('Y-m-d H:i:s'));
        }
    }

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------

    /**
     * Return the most recent messages for a room, in chronological order.
     *
     * @return array<int, array>
     */
    public function getRecentMessages(int $roomId, int $limit = self::DEFAULT_MESSAGE_LIMIT): array
    {
        return $this->messages->findRecentByRoom($roomId, $limit);
    }

    /**
     * Send a message from a user to a room.
     *
     * Requirements:
     *   - body must be non-empty after trimming
     *   - body must not exceed MAX_MESSAGE_LENGTH characters
     *   - user must be a member of the room
     *
     * @throws \InvalidArgumentException
     * @return int  The new message ID
     */
    public function sendMessage(int $roomId, int $userId, string $body): int
    {
        $body = trim($body);

        if ($body === '') {
            throw new \InvalidArgumentException('Message cannot be empty.');
        }
        if (mb_strlen($body) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                'Message is too long (maximum ' . self::MAX_MESSAGE_LENGTH . ' characters).'
            );
        }

        if ($this->rooms->findById($roomId) === null) {
            throw new \InvalidArgumentException('Room not found.');
        }
        if (!$this->isMember($roomId, $userId)) {
            throw new \InvalidArgumentException('You are not a member of this room.');
        }

        return $this->messages->create([
            'room_id'        => $roomId,
            'author_type'    => 'user',
            'author_user_id' => $userId,
            'body'           => $body,
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate room creation/update input.
     *
     * Returns an array of field => message pairs; empty means valid.
     *
     * @return array<string, string>
     */
    public function validateRoom(array $data): array
    {
        $errors = [];

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Room name is required.';
        } elseif (mb_strlen($name) > self::MAX_ROOM_NAME_LENGTH) {
            $errors['name'] = 'Room name must be ' . self::MAX_ROOM_NAME_LENGTH . ' characters or fewer.';
        }

        $type = $data['type'] ?? '';
        if (!in_array($type, self::ROOM_TYPES, true)) {
            $errors['type'] = 'Invalid room type.';
        }

        return $errors;
    }
}
