# Chat Module

## Purpose

The Chat module provides a reusable, room-based messaging foundation for users of the platform.
Phase 1 implements the infrastructure only: schema, repositories, service, controller, and a minimal
server-rendered UI.

This is **not AI chat**. The module is designed as user-to-user / shared-room chat, with the
data model structured to support future AI agent participation (`author_type = 'agent'`) without
requiring schema changes.

---

## Architecture

### Location

```
app/Modules/Chat/
  Controllers/
    ChatController.php
  Models/
    ChatRoomRepository.php
    ChatRoomMemberRepository.php
    ChatMessageRepository.php
  Services/
    ChatService.php
```

Views are under `app/Views/chat/`.

### Separation of concerns

| Layer | File | Responsibility |
|---|---|---|
| Repository | `ChatRoomRepository.php` | All SQL for `chat_rooms`; returns raw arrays |
| Repository | `ChatRoomMemberRepository.php` | All SQL for `chat_room_members` |
| Repository | `ChatMessageRepository.php` | All SQL for `chat_messages` |
| Service | `ChatService.php` | Validation, access control, business rules |
| Controller | `ChatController.php` | HTTP handling, flash messages, audit logging |
| Views | `app/Views/chat/` | HTML rendering only |

---

## Schema

### Migrations

| Migration | Description |
|---|---|
| `0044` | Create `chat_rooms` table |
| `0045` | Create `chat_room_members` table |
| `0046` | Create `chat_messages` table |
| `0047` | Add `chat.use` permission, grant to admin group |

### chat_rooms

```sql
CREATE TABLE chat_rooms (
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
```

### chat_room_members

```sql
CREATE TABLE chat_room_members (
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
```

### chat_messages

```sql
CREATE TABLE chat_messages (
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
```

---

## Room Model

### Room types

| `type` | Description | Join behavior |
|---|---|---|
| `shared` | Visible to all `chat.use` users | Any user may self-join |
| `private` | Only explicit members may view | Invitation required (deferred) |
| `system` | Automated / agent-generated room | No user join; no user messages |

### Visibility rules

`ChatService::canView(roomId, userId)`:
- `shared` / `system` → any authenticated user with `chat.use` may view
- `private` → only explicit members may view

### Access rules summary

| Action | Who can perform it |
|---|---|
| View `shared` or `system` room | Any `chat.use` user |
| View `private` room | Explicit member only |
| Post a message | Explicit member; room type must not be `system` |
| Join a `shared` room | Any `chat.use` user (self-join) |
| Join a `private` room | Invitation mechanism — **deferred** |
| Create a room (`shared`/`private`) | Any `chat.use` user |
| Create a `system` room | Deferred (automated creation only) |

---

## Membership Model

Membership is stored in `chat_room_members`.

| Column | Type | Description |
|---|---|---|
| `role` | `VARCHAR(32)` | `'owner'` (room creator) or `'member'` |
| `joined_at` | `VARCHAR(32)` | When the membership was created |
| `last_read_at` | `VARCHAR(32) NULL` | Last time the user opened the room — populated on view |

### last_read_at

`ChatService::markRead()` updates `last_read_at` whenever a member opens a room.
This column drives unread-count tracking. It is updated by `ChatService::markRead()` each
time a member opens a room. The update happens **after** messages are fetched — so the count
correctly reflects what the user just saw, and the next visit starts clean.

When `last_read_at` is NULL the user has never opened the room since joining — all qualifying
messages in that room are counted as unread.

### Creator membership

When a user creates a room:
- They are automatically added as `role = 'owner'`
- Exception: `system` rooms have no owner membership — they are machine-managed

### Idempotent join

`ChatService::joinRoom()` is idempotent: calling it when already a member is a no-op.

---

## Author Model

Every message carries an `author_type` field that identifies who sent it.

| `author_type` | `author_user_id` | Description |
|---|---|---|
| `user` | User PK (required) | A human user |
| `system` | NULL | An automated system message |
| `agent` | NULL | A future AI agent |

The `author_user_id` FK uses `ON DELETE SET NULL` so messages from deleted users are
preserved in history with a NULL author — display code should handle this gracefully.

---

## ChatService

`App\Modules\Chat\Services\ChatService`

### Constants

| Constant | Value |
|---|---|
| `ROOM_TYPES` | `['private', 'shared', 'system']` |
| `ROOM_TYPE_LABELS` | Map of type → human label |
| `AUTHOR_TYPES` | `['user', 'system', 'agent']` |
| `MEMBER_ROLES` | `['member', 'owner']` |
| `MAX_ROOM_NAME_LENGTH` | `100` |
| `MAX_MESSAGE_LENGTH` | `10000` |
| `DEFAULT_MESSAGE_LIMIT` | `50` |

### Methods

| Method | Description |
|---|---|
| `getRoomsForUser(int $userId): array` | All rooms visible to the user |
| `getRoom(int $id): ?array` | Single room or null |
| `createRoom(array $data, int $creatorUserId): int` | Validate + insert room + add owner member |
| `joinRoom(int $roomId, int $userId): void` | Join shared room (idempotent) |
| `isMember(int $roomId, int $userId): bool` | Check explicit membership |
| `canView(int $roomId, int $userId): bool` | Access control check |
| `getMembersForRoom(int $roomId): array` | Member list with user display fields |
| `markRead(int $roomId, int $userId): void` | Update last_read_at after fetching messages (no-op for non-members) |
| `countUnreadForUser(int $userId): int` | Total unread messages across all memberships (own messages excluded) |
| `getRecentMessages(int $roomId, int $limit): array` | Last N messages, chronological |
| `sendMessage(int $roomId, int $userId, string $body): int` | Validate + insert message |
| `validateRoom(array $data): array` | Returns field => error map; empty = valid |

---

## Routes

### Browser routes

All require `WebAuth` + `WebPermission:chat.use`.

| Method | Path | Handler | Description |
|---|---|---|---|
| GET | `/chat` | `ChatController@index` | Rooms list |
| GET | `/chat/rooms/create` | `ChatController@createForm` | Create room form |
| POST | `/chat/rooms` | `ChatController@store` | Handle create |
| GET | `/chat/rooms/{id}` | `ChatController@show` | Room detail + message feed |
| POST | `/chat/rooms/{id}/join` | `ChatController@join` | Join a shared room |
| POST | `/chat/rooms/{id}/messages` | `ChatController@sendMessage` | Post a message |

Route ordering: `/chat/rooms/create` and action sub-routes are registered before `/chat/rooms/{id}`
to prevent literal segments from being captured as `{id}` parameters.

### JSON API

Uses `SessionAuth` (returns 401 JSON on session failure, not an HTML redirect).
This matches the fetch-based AJAX callers that cannot parse redirect responses.
Permission (`chat.use`) is checked inside the controller method.

| Method | Path | Handler | Response |
|---|---|---|---|
| GET | `/api/chat/unread` | `ChatController@unreadCount` | `{"unread_count": N}` |

#### `GET /api/chat/unread`

Returns the total unread message count across all rooms the authenticated user is a member of.

- Users without `chat.use` permission receive `{"unread_count": 0}` (not 403).
- Uses the same `countUnreadForUser()` logic as the server-rendered sidebar badge.
- No request parameters.

**Polling behaviour (sidebar JS):**
The `js-chat-unread-badge` element in the layout is polled every **30 seconds** via a
`setInterval` loop. The badge is hidden when the count is 0, shown when > 0. Fetch
failures are swallowed silently — the last known count remains until the next successful
poll. The poller only activates when the badge element is present in the DOM (i.e. the
current user has `chat.use`).

---

## Authorization

### Permission

| Permission | Description |
|---|---|
| `chat.use` | Access the Chat module — view rooms and send messages |

Granted to the `admin` group by default (migration 0047).

### Controller-level checks

- All routes: `WebPermission:chat.use` middleware
- `show()`: calls `ChatService::canView()` — returns 403 if access denied
- `sendMessage()`: calls `canView()` first, then service enforces membership before insert

---

## UI

### Rooms list (`/chat`)

- Bootstrap card containing a DataTable
- Columns: Name (with role badge + description), Type (badge), Members, Messages, Created, Actions
- Displays rooms the user can see: all shared/system rooms + private rooms they're a member of

### Create form (`/chat/rooms/create`)

- Fields: Name (required), Description (optional), Type (shared/private)
- `system` type is excluded from the UI form — system rooms are machine-created only
- Validation errors displayed inline

### Room detail (`/chat/rooms/{id}`)

**NOTE: This view does NOT use DataTables.**
A chat message feed is a chronological log, not tabular data. Sorting, filtering, and
DataTables pagination controls would break the chat experience. This is a documented
exemption from the standard DataTables convention.

Layout:
- Left/main column: message feed + send form (or join button for shared rooms)
- Right sidebar: members list with roles

Message display:
- Chronological order, oldest at top, newest at bottom
- System/agent messages visually distinguished
- `nl2br` body rendering (no HTML allowed — output is escaped)
- Page scrolls to bottom on load via JS
- Ctrl+Enter / Cmd+Enter submits the message form

Interaction:
- Member + non-system room: send message form shown
- Non-member + shared room: Join Room button shown
- Non-member + private room: read-only notice
- System room: read-only notice regardless of membership

---

## Deferred Features

The following are intentionally NOT implemented in Phase 1 but are supported by the schema.

### Message notifications (Notifications module dispatch)

Unread counts are implemented. Per-user notifications on mention / unread are deferred.

Future implementation path:
1. On message send: query members with `last_read_at < message.created_at`
2. Dispatch notifications via `NotificationService::dispatch()` (existing module)
3. Parse `@username` in body → notify mentioned users specifically

---

## Unread Message Tracking

### Definition

A message is **unread** for a user when all three conditions hold:
1. The message is in a room the user is a member of
2. `message.created_at > member.last_read_at` (or `last_read_at IS NULL`)
3. `message.author_user_id != userId` — own messages are never counted as unread

System and agent messages (`author_user_id IS NULL`) are always counted as unread
because they come from non-user sources that the member should be aware of.

Non-members always have an unread count of 0 — there is no membership row to track
against, so no baseline exists.

### Read tracking

`ChatService::markRead(roomId, userId)` updates `last_read_at` to the current timestamp.

It is called in `ChatController::show()` **after** messages are fetched:

```
1. fetch messages          → returns what the user is about to see
2. markRead(roomId, userId) → sets last_read_at = now
```

This ordering means the count correctly reflects the messages the user just saw.
The next page load of the same room will show 0 unread (unless new messages arrived
between the fetch and the markRead).

### Per-room unread count

`ChatRoomRepository::findForUser()` includes an `unread_count` column computed by a
correlated subquery:

```sql
CASE WHEN mem.user_id IS NOT NULL THEN (
    SELECT COUNT(*)
    FROM   chat_messages msg2
    WHERE  msg2.room_id = r.id
      AND  (msg2.author_user_id IS NULL OR msg2.author_user_id != ?)
      AND  (mem.last_read_at IS NULL OR msg2.created_at > mem.last_read_at)
) ELSE 0 END AS unread_count
```

Non-members receive 0. Displayed as a yellow pill badge in the rooms list.

### Total unread count (sidebar badge)

`ChatRoomMemberRepository::countUnreadForUser(int $userId): int` returns the sum across
all memberships using a single flat JOIN query:

```sql
SELECT COUNT(*) AS n
FROM   chat_messages msg
JOIN   chat_room_members m ON m.room_id = msg.room_id AND m.user_id = ?
WHERE  (msg.author_user_id IS NULL OR msg.author_user_id != ?)
  AND  (m.last_read_at IS NULL OR msg.created_at > m.last_read_at)
```

This count drives the `bg-warning` badge on the Chat sidebar link, queried in
`app/Views/layouts/app.php` (same pattern as the topology candidate count).

### Current limitations

- **No live refresh** — unread counts reflect server state at page load. The sidebar badge
  and room list are not updated automatically. Refresh the page to see current counts.
- **No per-message read tracking** — tracking is per-room via `last_read_at`, not per-message.
  All messages before the timestamp are considered read.
- **Race window** — if a new message arrives between the message fetch and the `markRead()`
  call, it will immediately be marked read without the user having seen it. This is a known
  minor inconsistency acceptable in a server-rendered, non-realtime phase.
- **No notification dispatch** — unread counts are visible in the UI but do not trigger
  in-app or email notifications. Deferred to the notifications integration phase.

### Mention support

`@username` mention detection can be added to `ChatService::sendMessage()`:
- Parse body for `@username` patterns
- Look up matched users who are members of the room
- Dispatch a targeted notification to each mentioned user
- Store mention records in a future `chat_mentions` table for inbox filtering

### WebSocket / real-time

The current implementation is server-rendered with full-page reload.
WebSocket/SSE support is deferred. When added:
- `show.php` subscribe to a room event stream
- Append new message DOM nodes without page reload
- Update member presence indicator

### Attachments (File Manager integration)

The schema has no attachment column. When added:
- Add `attachment_id` FK → a future `chat_message_attachments` table or direct File Manager file
- Use `FileManagerService` for upload and path-traversal-safe retrieval
- Preview safe formats in-feed; download link for others

### AI Agent participation

`author_type = 'agent'` is a reserved value in the schema. When the AI Agent module is built:
- Agent messages are inserted via a service method (not the `sendMessage()` user path)
- Agent display name comes from an agents registry (not the `users` table)
- The `author_user_id` column remains NULL for agent messages

### Private room invitations

Currently, `private` rooms can only be joined by the creator.
Future invite workflow:
- `POST /chat/rooms/{id}/invite` → accepts user_id, adds a membership row
- Optionally sends a notification to the invited user via the Notifications module

### Typing indicators

Deferred (requires WebSocket or long-polling infrastructure).

### Message reactions / editing / deletion

All deferred. The `updated_at` column on `chat_messages` is reserved for future edit tracking.
