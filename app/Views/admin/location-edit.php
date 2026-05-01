<?php
/**
 * Admin — Edit location form.
 *
 * Variables:
 *   $location     (array)  — current location row from LocationRepository::findById()
 *   $allForSelect (array)  — all locations except self (for parent select)
 *   $errors       (array)  — validation errors keyed by field name
 *   $old          (array)  — repopulation values from a failed submission
 *   $flash        (?array) — ['type' => string, 'message' => string] or null
 */

$old    = $old    ?? [];
$errors = $errors ?? [];

function locEditVal(string $key, array $old, array $location): string {
    $val = array_key_exists($key, $old) ? ($old[$key] ?? '') : ($location[$key] ?? '');
    return htmlspecialchars((string) $val);
}

$currentType     = array_key_exists('type', $old) ? ($old['type'] ?? 'other') : ($location['type'] ?? 'other');
$currentParentId = array_key_exists('parentId', $old)
    ? (int) ($old['parentId'] ?? 0)
    : (int) ($location['parent_id'] ?? 0);

$locationTypes = [
    'site'     => 'Site',
    'building' => 'Building',
    'floor'    => 'Floor',
    'room'     => 'Room',
    'rack'     => 'Rack',
    'other'    => 'Other',
];
?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <div class="mb-3">
            <a href="/admin/locations" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Locations
            </a>
        </div>

        <!-- Flash -->
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header fw-semibold">Edit Location</div>
            <div class="card-body">
                <form method="POST" action="/admin/locations/<?= (int) $location['id'] ?>">

                    <!-- Name -->
                    <div class="mb-3">
                        <label for="loc-name" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               id="loc-name"
                               name="name"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= locEditVal('name', $old, $location) ?>"
                               maxlength="128"
                               autocomplete="off"
                               required>
                        <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Type -->
                    <div class="mb-3">
                        <label for="loc-type" class="form-label">
                            Type <span class="text-danger">*</span>
                        </label>
                        <select id="loc-type" name="type"
                                class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>">
                            <?php foreach ($locationTypes as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>"
                                <?= $currentType === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['type'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Parent (self excluded) -->
                    <div class="mb-3">
                        <label for="loc-parent" class="form-label">Parent Location</label>
                        <select id="loc-parent" name="parent_id"
                                class="form-select <?= isset($errors['parent_id']) ? 'is-invalid' : '' ?>">
                            <option value="">— None (top-level) —</option>
                            <?php foreach ($allForSelect as $l): ?>
                            <option value="<?= (int) $l['id'] ?>"
                                <?= $currentParentId === (int) $l['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($l['type']) ?>] <?= htmlspecialchars($l['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['parent_id'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['parent_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label for="loc-desc" class="form-label">Description</label>
                        <textarea id="loc-desc"
                                  name="description"
                                  class="form-control"
                                  rows="3"><?= locEditVal('description', $old, $location) ?></textarea>
                    </div>

                    <!-- Meta -->
                    <div class="mb-4 text-muted small">
                        <div>Created: <?= htmlspecialchars(substr($location['created_at'], 0, 16)) ?></div>
                        <div>Last updated: <?= htmlspecialchars(substr($location['updated_at'], 0, 16)) ?></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="/admin/locations" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>

        <!-- Danger zone -->
        <div class="card border-danger mt-3">
            <div class="card-body">
                <h6 class="text-danger mb-1">Delete Location</h6>
                <p class="small text-muted mb-3">
                    Locations with assigned devices or child locations cannot be deleted.
                    Reassign or remove them first.
                </p>
                <form method="POST"
                      action="/admin/locations/<?= (int) $location['id'] ?>/delete"
                      onsubmit="return confirm('Delete location &quot;<?= addslashes(htmlspecialchars($location['name'])) ?>&quot;? This cannot be undone.');">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Delete Location
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
