<?php
/**
 * Chat rooms list — content fragment.
 *
 * Variables set by ChatController::index():
 *   $rooms       (array)   — rows from ChatService::getRoomsForUser(); each row includes
 *                            unread_count (int) — messages unread by the current user
 *   $flash       (?array)  — ['type' => string, 'message' => string] or null
 *   $permissions (array)
 *
 * Uses DataTables for the room list (standard application table convention).
 */

use App\Modules\Chat\Services\ChatService;
?>

<!-- ── Flash ──────────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ── Page heading ───────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Chat</h1>
        <p class="text-muted mb-0 small">
            Real-time conversation rooms for teams and system notifications.
        </p>
    </div>
    <a href="/chat/rooms/create" class="btn btn-sm btn-primary mt-1">
        <i class="bi bi-plus-lg me-1"></i>New Room
    </a>
</div>

<!-- ── Room list ──────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="chat-rooms-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Members</th>
                        <th>Messages</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room):
                        $typeBadge   = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                        if ($room['type'] === 'shared') {
                            $typeBadge = 'bg-success-subtle text-success border border-success-subtle';
                        } elseif ($room['type'] === 'private') {
                            $typeBadge = 'bg-primary-subtle text-primary border border-primary-subtle';
                        } elseif ($room['type'] === 'system') {
                            $typeBadge = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                        }
                        $typeLabel   = ChatService::ROOM_TYPE_LABELS[$room['type']] ?? $room['type'];
                        $userRole    = $room['user_role'] ?? null;
                        $unreadCount = (int) ($room['unread_count'] ?? 0);
                    ?>
                    <tr>
                        <td class="fw-medium">
                            <a href="/chat/rooms/<?= (int) $room['id'] ?>"
                               class="text-decoration-none">
                                <?= htmlspecialchars($room['name']) ?>
                            </a>
                            <?php if ($unreadCount > 0): ?>
                            <span class="badge rounded-pill bg-warning text-dark ms-1"
                                  title="<?= $unreadCount ?> unread message<?= $unreadCount !== 1 ? 's' : '' ?>">
                                <?= $unreadCount ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($userRole === 'owner'): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1"
                                  style="font-size:.65rem;">Owner</span>
                            <?php elseif ($userRole === 'member'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1"
                                  style="font-size:.65rem;">Member</span>
                            <?php endif; ?>
                            <?php if (!empty($room['description'])): ?>
                            <div class="text-muted small mt-1" style="font-weight:normal;">
                                <?= htmlspecialchars(mb_strimwidth($room['description'], 0, 80, '…')) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $typeBadge ?>">
                                <?= htmlspecialchars($typeLabel) ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= (int) $room['member_count'] ?></td>
                        <td class="text-muted small"><?= (int) $room['message_count'] ?></td>
                        <td class="text-muted small" style="white-space:nowrap;">
                            <?= htmlspecialchars(substr($room['created_at'], 0, 10)) ?>
                        </td>
                        <td class="text-end" style="white-space:nowrap;">
                            <a href="/chat/rooms/<?= (int) $room['id'] ?>"
                               class="btn btn-sm btn-outline-secondary"
                               title="Open room">
                                <i class="bi bi-chat-dots"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {
    KernelWeb.dt.init('#chat-rooms-table', {
        order: [[0, 'asc']],
        language: { emptyTable: 'No chat rooms available.' },
        columnDefs: [
            { orderable: false, targets: [5] },
        ]
    });
});
</script>
