<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-3 text-center">
                <div class="h5 mb-0"><?= count(array_filter($extensions, fn($e) => $e['type'] === 'plugin')) ?></div>
                <div class="text-muted small">Plugins</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-3 text-center">
                <div class="h5 mb-0"><?= count(array_filter($extensions, fn($e) => $e['type'] === 'theme')) ?></div>
                <div class="text-muted small">Themes</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-3 text-center">
                <div class="h5 mb-0"><?= count(array_filter($extensions, fn($e) => $e['type'] === 'layout')) ?></div>
                <div class="text-muted small">Layouts</div>
            </div>
        </div>
    </div>
    <?php if (empty($extensions)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body p-4 text-center text-muted">
                No catalog extensions found.
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold small">Catalog Extensions (<?= count($extensions) ?>)</span>
                <div>
                    <a href="/admin/extensions/catalog/submit" class="btn btn-sm btn-outline-primary me-1">
                        <i class="bi bi-plus-lg"></i> Submit Extension
                    </a>
                    <a href="/admin/extensions/catalog/review" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-eye"></i> Review Pending
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="admin-catalog-table" class="table table-hover mb-0 w-100">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Type</th>
                                <th>Version</th>
                                <th>Description</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th>Actions</th>
                                <th>Uninstall</th>
                                <th>Installed</th>
                                <th>Enabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($extensions as $ext): ?>
                            <tr>
                                <td><?= htmlspecialchars($ext['name']) ?></td>
                                <td>
                                    <code><?= htmlspecialchars($ext['slug']) ?></code>
                                </td>
                                <td class="text-muted small"><?= ucfirst(htmlspecialchars($ext['type'])) ?></td>
                                <td class="text-muted small"><?= $ext['version'] !== '0.0.0' ? htmlspecialchars($ext['version']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                                <td class="text-muted small"><?= $ext['description'] !== '' ? htmlspecialchars($ext['description']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                                <td class="text-muted small"><?= $ext['author'] !== '' ? htmlspecialchars($ext['author']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                                <td>
                                    <?php if ($ext['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                    <?php elseif ($ext['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ((int) $ext['is_installed'] === 0): ?>
                                    <?php if ($ext['status'] === 'approved'): ?>
                                    <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/install" style="display:inline;">
                                        <button type="submit" class="btn btn-sm btn-success"
                                                onclick="return confirm('Install <?= htmlspecialchars($ext['name']) ?> (v<?= htmlspecialchars($ext['version']) ?>) from staging.\n\nPlace extension files in /storage/extension-staging/<?= htmlspecialchars($ext['slug']) ?>/ first.')">
                                            <i class="bi bi-download me-1"></i>Install
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php elseif ((int) $ext['is_enabled'] === 0): ?>
                                    <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/enable" style="display:inline;">
                                        <button type="submit" class="btn btn-sm btn-outline-success"
                                                onclick="return confirm('Enable <?= htmlspecialchars($ext['name']) ?>?')">
                                            <i class="bi bi-play-fill me-1"></i>Enable
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/disable" style="display:inline;">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Disable <?= htmlspecialchars($ext['name']) ?>?')">
                                            <i class="bi bi-pause-fill me-1"></i>Disable
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ((int) $ext['is_installed'] === 1 && (int) $ext['is_enabled'] === 0): ?>
                                    <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/uninstall" style="display:inline;">
                                        <button type="submit" class="btn btn-sm btn-outline-warning"
                                                onclick="return confirm('Uninstall <?= htmlspecialchars($ext['name']) ?>?\n\nThis will remove all extension files from disk. The catalog record will be preserved for history.')">
                                            <i class="bi bi-trash me-1"></i>Uninstall
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    </td>
                                <td class="text-center">
                                    <?php if ((int) $ext['is_installed'] === 1): ?>
                                    <i class="bi bi-check-circle text-success"></i>
                                    <?php else: ?>
                                    <i class="bi bi-dash-circle text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int) $ext['is_enabled'] === 1): ?>
                                    <i class="bi bi-check-circle text-success"></i>
                                    <?php else: ?>
                                    <i class="bi bi-dash-circle text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
    KernelWeb.dt.init('#admin-catalog-table', {
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [7, 8, 9, 10] }
        ]
    });
    </script>
    <?php endif; ?>

    <p class="text-muted small">
        Catalog extensions are managed through the database. Installed extensions are discovered from <code>lib/plugins/</code>, <code>lib/themes/</code>, and <code>lib/layouts/</code>.
    </p>
