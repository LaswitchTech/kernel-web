<?php
/**
 * Admin — Create location form.
 *
 * Variables:
 *   $allForSelect (array)  — rows from LocationRepository::findAllForSelect()
 *   $errors       (array)  — validation errors keyed by field name
 *   $old          (array)  — repopulation values from a failed submission
 */

$old    = $old    ?? [];
$errors = $errors ?? [];

function locCreateVal(string $key, array $old, string $default = ''): string {
    return htmlspecialchars((string) ($old[$key] ?? $default));
}

$currentType     = $old['type']     ?? 'other';
$currentParentId = isset($old['parentId']) ? (int) $old['parentId'] : 0;

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

        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header fw-semibold">New Location</div>
            <div class="card-body">
                <form method="POST" action="/admin/locations">

                    <!-- Name -->
                    <div class="mb-3">
                        <label for="loc-name" class="form-label">
                            Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               id="loc-name"
                               name="name"
                               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= locCreateVal('name', $old) ?>"
                               maxlength="128"
                               autocomplete="off"
                               autofocus
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

                    <!-- Parent -->
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
                                  rows="3"><?= locCreateVal('description', $old) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Location</button>
                        <a href="/admin/locations" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
