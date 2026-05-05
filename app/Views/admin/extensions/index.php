<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-3 text-center">
                <div class="h5 mb-0"><?= count($extensions['plugins']) ?></div>
                <div class="text-muted small">
                    <i class="bi bi-plug me-1"></i>Plugins
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-3 text-center">
                <div class="h5 mb-0"><?= count($extensions['themes']) ?></div>
                <div class="text-muted small">
                    <i class="bi bi-palette me-1"></i>Themes
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-3 text-center">
                <div class="h5 mb-0"><?= count($extensions['layouts']) ?></div>
                <div class="text-muted small">
                    <i class="bi bi-layout-sidebar me-1"></i>Layouts
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach (['plugins' => 'Plugins', 'themes' => 'Themes', 'layouts' => 'Layouts'] as $type => $label): ?>
<?php $items = $extensions[$type]; ?>
<?php if (empty($items)): ?>
<div class="card mb-4">
    <div class="card-body p-4 text-center text-muted">
        <i class="bi bi-inbox d-block mb-2" style="font-size:1.5rem;opacity:.4;"></i>
        No <?= strtolower($label) ?> discovered.
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">
            <?= $label ?> (<?= count($items) ?>)
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="admin-extensions-<?= $type ?>-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Version</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $ext): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($ext['name']) ?></td>
                        <td><code><?= htmlspecialchars($ext['slug']) ?></code></td>
                        <td class="text-muted small"><?= $ext['version'] !== null ? htmlspecialchars($ext['version']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                        <td class="text-muted small"><?= $ext['description'] !== null ? htmlspecialchars($ext['description']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                        <td>
                            <?php if ($ext['status'] === 'invalid'): ?>
                            <span class="badge bg-danger" title="<?= htmlspecialchars($ext['reason'] ?? 'Invalid manifest') ?>">
                                <i class="bi bi-x-circle me-1"></i>Invalid
                            </span>
                            <div class="text-muted small mt-1"><?= htmlspecialchars($ext['reason'] ?? '') ?></div>
                            <?php elseif ($ext['status'] === 'enabled'): ?>
                            <span class="badge bg-primary" title="Currently active">
                                <i class="bi bi-lightning me-1"></i>Enabled
                            </span>
                            <?php elseif ($ext['status'] === 'disabled'): ?>
                            <span class="badge bg-secondary" title="Discovered but disabled">
                                <i class="bi bi-pause-circle me-1"></i>Disabled
                            </span>
                            <?php else: ?>
                            <span class="badge bg-success" title="Discovered and available">
                                <i class="bi bi-check-circle me-1"></i>Discovered
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
KernelWeb.dt.init('#admin-extensions-<?= $type ?>-table', {
    order: [[0, 'asc']],
    columnDefs: [
        { orderable: false, targets: [4] }
    ]
});
</script>
<?php endif; ?>
<?php endforeach; ?>

<div class="d-flex gap-2 mt-3">
    <a href="/admin/extensions/catalog" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-database me-1"></i>Browse Catalog
    </a>
    <a href="/admin/extensions/catalog/review" class="btn btn-outline-warning btn-sm">
        <i class="bi bi-eye me-1"></i>Review Submissions
    </a>
</div>

<p class="text-muted small mt-3">
    Extensions are discovered read-only from <code>lib/plugins/</code>, <code>lib/themes/</code>, and <code>lib/layouts/</code>.
    Enable/disable, install, and uninstall are managed through the <a href="/admin/extensions/catalog">extension catalog</a>.
</p>
