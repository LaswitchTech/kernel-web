<?php
/**
 * Admin — Location list.
 *
 * Variables:
 *   $locations  (array)  — rows from LocationRepository::findAll()
 *   $flash      (?array) — ['type' => string, 'message' => string] or null
 *   $permissions (array)
 */
?>

<!-- ── Flash ──────────────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ── Page heading ───────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Locations</h1>
        <p class="text-muted mb-0 small">
            Manage the physical location hierarchy used to organise devices.
        </p>
    </div>
    <a href="/admin/locations/create" class="btn btn-sm btn-primary mt-1">
        <i class="bi bi-plus-lg me-1"></i>New Location
    </a>
</div>

<!-- ── Location table ─────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="locations-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th style="width:9rem">Type</th>
                        <th>Parent</th>
                        <th>Description</th>
                        <th class="text-end" style="width:8rem">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td class="fw-medium"><?= htmlspecialchars($loc['name']) ?></td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:.75rem">
                                <?= htmlspecialchars($loc['type']) ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= $loc['parent_name'] !== null
                                ? htmlspecialchars($loc['parent_name'])
                                : '<span class="fst-italic">—</span>' ?>
                        </td>
                        <td class="text-muted small">
                            <?= $loc['description'] !== null
                                ? htmlspecialchars(mb_strimwidth($loc['description'], 0, 80, '…'))
                                : '—' ?>
                        </td>
                        <td class="text-end" style="white-space:nowrap;">
                            <a href="/admin/locations/<?= (int) $loc['id'] ?>/edit"
                               class="btn btn-sm btn-outline-secondary"
                               title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST"
                                  action="/admin/locations/<?= (int) $loc['id'] ?>/delete"
                                  class="d-inline ms-1"
                                  onsubmit="return confirm('Delete location &quot;<?= addslashes(htmlspecialchars($loc['name'])) ?>&quot;? This cannot be undone.');">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    window.addEventListener('DOMContentLoaded', function () {
        KernelWeb.dt.init('#locations-table', {
            order: [[1, 'asc'], [0, 'asc']],
            language: { emptyTable: 'No locations defined yet.' },
            columnDefs: [
                { orderable: false, targets: [4] },
            ]
        });
    });
}());
</script>
