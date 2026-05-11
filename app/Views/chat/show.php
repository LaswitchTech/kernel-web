<?php
/**
 * Chat room detail — content fragment.
 *
 * Variables set by ChatController::show():
 *   $room     (array)   — room row including name, description, type, member_count
 *   $messages (array)   — chronological message rows (oldest first)
 *   $members  (array)   — member rows including username, display_name, role
 *   $isMember (bool)    — whether the authenticated user is a member of this room
 *   $flash    (?array)  — ['type' => string, 'message' => string] or null
 *
 * NOTE: This view intentionally does NOT use DataTables.
 * A chat message feed is a chronological log, not tabular data.
 * Sorting, filtering, and pagination controls would break the chat UX.
 * This exemption is documented in docs/chat.md.
 *
 * No WebSockets or AJAX polling in this phase — refresh the page to see new messages.
 */

use App\Modules\Chat\Services\ChatService;

$typeLabel = ChatService::ROOM_TYPE_LABELS[$room['type']] ?? $room['type'];
$typeBadge = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
if ($room['type'] === 'shared') {
    $typeBadge = 'bg-success-subtle text-success border border-success-subtle';
} elseif ($room['type'] === 'private') {
    $typeBadge = 'bg-primary-subtle text-primary border border-primary-subtle';
} elseif ($room['type'] === 'system') {
    $typeBadge = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
}

// Determine if the user can post (must be a member; system rooms accept no user messages).
$canPost = $isMember && $room['type'] !== 'system';
// Shared room + not a member → show join button.
$canJoin = !$isMember && $room['type'] === 'shared';
?>

<!-- ── Flash ──────────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ── Back + heading ─────────────────────────────────────────────────── -->
<div class="mb-3">
    <a href="/chat" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Rooms
    </a>
</div>

<div class="d-flex align-items-center gap-2 mb-1">
    <h1 class="h4 fw-semibold mb-0"><?= htmlspecialchars($room['name']) ?></h1>
    <span class="badge <?= $typeBadge ?>"><?= htmlspecialchars($typeLabel) ?></span>
</div>
<?php if (!empty($room['description'])): ?>
<p class="text-muted small mb-3"><?= htmlspecialchars($room['description']) ?></p>
<?php else: ?>
<div class="mb-3"></div>
<?php endif; ?>

<div class="row g-3">

    <!-- ── Message feed (left/main column) ─────────────────────────────── -->
    <div class="col-lg-9">

        <!-- Join callout for non-members of shared rooms -->
        <?php if ($canJoin): ?>
        <div class="alert alert-info d-flex align-items-center justify-content-between gap-3 mb-3">
            <span>
                <i class="bi bi-people me-1"></i>
                You are not a member of this room. Join to start sending messages.
            </span>
            <form method="POST" action="/chat/rooms/<?= (int) $room['id'] ?>/join" class="flex-shrink-0">
                <button type="submit" class="btn btn-sm btn-info">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Join Room
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Message list -->
        <div class="card mb-3">
            <div class="card-body p-0">
                <?php if (empty($messages)): ?>
                <div class="px-4 py-5 text-center text-muted small">
                    <i class="bi bi-chat-dots d-block mb-2" style="font-size:2rem; opacity:.3;"></i>
                    No messages yet.
                    <?php if ($canPost): ?>Be the first to say something!<?php endif; ?>
                </div>
                <?php else: ?>
                <div class="chat-feed" id="chat-feed">
                    <?php foreach ($messages as $msg):
                        if ($msg['author_type'] === 'user') {
                            $authorName = ($msg['display_name'] ?? '') !== ''
                                ? $msg['display_name']
                                : ($msg['username'] ?? 'Unknown');
                        } elseif ($msg['author_type'] === 'agent') {
                            $authorName = 'Agent';
                        } else {
                            $authorName = 'System';
                        }
                        $isSystem = $msg['author_type'] !== 'user';
                        $ts       = substr($msg['created_at'], 0, 16);
                    ?>
                    <div class="chat-message <?= $isSystem ? 'chat-message--system' : '' ?>">
                        <div class="chat-message-meta">
                            <span class="chat-message-author fw-semibold">
                                <?= htmlspecialchars($authorName) ?>
                            </span>
                            <span class="chat-message-ts text-muted small ms-2">
                                <?= htmlspecialchars($ts) ?>
                            </span>
                        </div>
                        <div class="chat-message-body">
                            <?= nl2br(htmlspecialchars($msg['body'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Send message form (members only, non-system rooms) -->
        <?php if ($canPost): ?>
        <div class="card" id="bottom">
            <div class="card-body">
                <form method="POST" action="/chat/rooms/<?= (int) $room['id'] ?>/messages">
                    <div class="mb-2">
                        <textarea name="body"
                                  id="chat-body"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Type a message…"
                                  maxlength="<?= ChatService::MAX_MESSAGE_LENGTH ?>"
                                  required></textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send me-1"></i>Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($room['type'] === 'system'): ?>
        <div class="text-muted small fst-italic text-center py-2">
            System room — messages are posted automatically.
        </div>
        <?php elseif (!$isMember && $room['type'] === 'private'): ?>
        <div class="text-muted small fst-italic text-center py-2">
            You need to be invited to send messages in this private room.
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Members sidebar (right column) ──────────────────────────────── -->
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header py-2">
                <span class="small fw-semibold">
                    <i class="bi bi-people me-1"></i>
                    Members
                    <span class="badge bg-secondary-subtle text-secondary ms-1">
                        <?= (int) $room['member_count'] ?>
                    </span>
                </span>
            </div>
            <div class="list-group list-group-flush"
                 style="max-height:320px; overflow-y:auto;">
                <?php if (empty($members)): ?>
                <div class="list-group-item text-muted small fst-italic">No members yet.</div>
                <?php else: ?>
                <?php foreach ($members as $member):
                    $memberName = ($member['display_name'] ?? '') !== ''
                        ? $member['display_name']
                        : $member['username'];
                ?>
                <div class="list-group-item d-flex align-items-center justify-content-between py-2 px-3">
                    <span class="small"><?= htmlspecialchars($memberName) ?></span>
                    <?php if ($member['role'] === 'owner'): ?>
                    <span class="badge bg-primary-subtle text-primary" style="font-size:.6rem;">Owner</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<style>
/* ── Chat feed ── */
.chat-feed {
    padding: .5rem 0;
}

.chat-message {
    padding: .5rem 1.25rem;
    border-bottom: 1px solid var(--bs-border-color-translucent);
}

.chat-message:last-child {
    border-bottom: none;
}

.chat-message--system {
    background: var(--bs-tertiary-bg);
}

.chat-message-meta {
    margin-bottom: .15rem;
    font-size: .8rem;
    line-height: 1.2;
}

.chat-message-body {
    font-size: .9rem;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>

<script>
// Scroll the message feed to the bottom on load so the most recent messages are visible.
(function () {
    var feed = document.getElementById('chat-feed');
    if (feed) {
        feed.scrollTop = feed.scrollHeight;
    }
    // Submit on Ctrl+Enter / Cmd+Enter in the message textarea.
    var textarea = document.getElementById('chat-body');
    if (textarea) {
        textarea.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                textarea.closest('form').submit();
            }
        });
    }
}());
</script>
