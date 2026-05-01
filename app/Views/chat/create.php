<?php
/**
 * Create chat room form — content fragment.
 *
 * Variables set by ChatController::createForm() and ChatController::store():
 *   $errors (array)  — field => message pairs from validation
 *   $old    (array)  — repopulation values from a failed submission
 */

use App\Modules\Chat\Services\ChatService;

$old    = $old    ?? [];
$errors = $errors ?? [];

function chatVal(string $key, array $old, string $default = ''): string {
    return htmlspecialchars($old[$key] ?? $default);
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">

        <div class="mb-3">
            <a href="/chat" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Chat
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="/chat/rooms">

                    <!-- Name -->
                    <div class="mb-3">
                        <label for="room-name" class="form-label">
                            Room Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               id="room-name"
                               name="name"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= chatVal('name', $old) ?>"
                               maxlength="100"
                               autocomplete="off"
                               required>
                        <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="room-description" class="form-label">Description</label>
                        <textarea id="room-description"
                                  name="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Optional — what is this room for?"><?= chatVal('description', $old) ?></textarea>
                    </div>

                    <!-- Type -->
                    <div class="mb-4">
                        <label for="room-type" class="form-label">
                            Room Type <span class="text-danger">*</span>
                        </label>
                        <select id="room-type"
                                name="type"
                                class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>">
                            <option value="">— Select a type —</option>
                            <?php foreach (ChatService::ROOM_TYPE_LABELS as $val => $label):
                                // System rooms are machine-managed; exclude from user creation.
                                if ($val === 'system') continue;
                            ?>
                            <option value="<?= htmlspecialchars($val) ?>"
                                <?= ($old['type'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['type'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['type']) ?></div>
                        <?php else: ?>
                        <div class="form-text">
                            <strong>Shared</strong> — visible to all users; anyone can join.<br>
                            <strong>Private</strong> — only invited members can see and post.
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Room</button>
                        <a href="/chat" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
