<?php $flash = $_SESSION['admin_flash'] ?? null; ?>
<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger') ?> alert-dismissible mb-4" role="alert">
    <?= $flash['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($flash); ?>
<?php endif; ?>

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
</div>

<?php if (empty($extensions)): ?>
<div class="card mb-4">
    <div class="card-body p-4 text-center text-muted">
        No catalog extensions found.
    </div>
</div>
<?php else: ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="filter-type" class="visually-hidden">Type</label>
                <select name="type" id="filter-type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="plugin" <?= ($_GET['type'] ?? '') === 'plugin' ? 'selected' : '' ?>>Plugins</option>
                    <option value="theme" <?= ($_GET['type'] ?? '') === 'theme' ? 'selected' : '' ?>>Themes</option>
                    <option value="layout" <?= ($_GET['type'] ?? '') === 'layout' ? 'selected' : '' ?>>Layouts</option>
                </select>
            </div>
            <div class="col-auto">
                <label for="filter-status" class="visually-hidden">Status</label>
                <select name="status" id="filter-status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="rejected" <?= ($_GET['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <label for="filter-installed" class="visually-hidden">Installed</label>
                <select name="installed" id="filter-installed" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="1" <?= ($_GET['installed'] ?? '') === '1' ? 'selected' : '' ?>>Installed</option>
                    <option value="0" <?= ($_GET['installed'] ?? '') === '0' ? 'selected' : '' ?>>Not Installed</option>
                </select>
            </div>
            <div class="col-auto">
                <a href="/admin/extensions/catalog" class="btn btn-sm btn-link text-muted">
                    <i class="bi bi-x-lg"></i> Clear filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Catalog table -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">Catalog Extensions (<?= count($extensions) ?>)</span>
        <div>
            <a href="/admin/extensions/catalog/submit" class="btn btn-sm btn-outline-primary me-1">
                <i class="bi bi-plus-lg me-1"></i>Submit Extension
            </a>
            <a href="/admin/extensions/catalog/review" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-eye me-1"></i>Review Pending
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
                        <th>Local Version</th>
                        <th>Dependencies</th>
                        <th>Status</th>
                        <th>Installed</th>
                        <th>Enabled</th>
                        <th>Blocked</th>
                        <th>Update</th>
                        <th class="text-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($extensions as $ext): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($ext['name']) ?></td>
                        <td><code><?= htmlspecialchars($ext['slug']) ?></code></td>
                        <td>
                            <?php
                            $typeIcons = ['plugin' => 'bi-plug', 'theme' => 'bi-palette', 'layout' => 'bi-layout-sidebar'];
                            $icon = $typeIcons[$ext['type']] ?? 'bi-file-earmark';
                            ?>
                            <i class="bi <?= htmlspecialchars($icon) ?> me-1"></i>
                            <?= ucfirst(htmlspecialchars($ext['type'])) ?>
                        </td>
                        <td class="text-muted small"><?= $ext['version'] !== '0.0.0' ? htmlspecialchars($ext['version']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                        <td class="text-muted small">
                            <?php
                            $update = $updates[$ext['slug']] ?? null;
                            if ($update !== null && $update->installedVersion !== null): ?>
                                <code><?= htmlspecialchars($update->installedVersion) ?></code>
                            <?php else: ?>
                                <span class="text-muted fst-italic">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $deps = $depAnalysis[(int) $ext['id']] ?? [];
                            $depCount = count($deps);
                            $hasIssues = false;
                            $depStatusClass = 'secondary';
                            foreach ($deps as $d) {
                                if ($d['status'] === 'satisfied') {
                                    $depStatusClass = 'success';
                                } elseif ($d['status'] === 'pending') {
                                    $depStatusClass = 'warning text-dark';
                                } elseif ($d['status'] === 'missing' || $d['status'] === 'version-mismatch' || $d['status'] === 'rejected') {
                                    $depStatusClass = 'danger';
                                    $hasIssues = true;
                                }
                            }
                            ?>
                            <?php if ($depCount === 0): ?>
                                <span class="text-muted small">None</span>
                            <?php else: ?>
                                <span class="badge <?= htmlspecialchars($depStatusClass) ?>"><?= $depCount ?></span>
                                <a href="#dep-detail-<?= (int) $ext['id'] ?>"
                                   class="d-inline-block ms-1"
                                   data-bs-toggle="collapse"
                                   data-bs-target="#dep-detail-<?= (int) $ext['id'] ?>"
                                   aria-expanded="false"
                                   aria-label="Toggle dependency details">
                                    <i class="bi bi-chevron-expand text-muted"></i>
                                </a>
                                <div class="collapse" id="dep-detail-<?= (int) $ext['id'] ?>">
                                    <div class="mt-2 p-2 border rounded bg-light">
                                        <table class="table table-sm table-borderless mb-0 small">
                                            <thead>
                                                <tr>
                                                    <th>Dependency</th>
                                                    <th>Constraint</th>
                                                    <th>Installed</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($deps as $d): ?>
                                                <tr>
                                                    <td>
                                                        <?= $d['name'] ?>
                                                        (<code><?= htmlspecialchars($d['key']) ?></code>)
                                                    </td>
                                                    <td class="text-nowrap"><?= htmlspecialchars($d['constraint']) ?></td>
                                                    <td>
                                                        <?php if ($d['installed']): ?>
                                                            <span class="badge bg-info text-dark"><?= htmlspecialchars($d['installedVersion']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusLabels = [
                                                            'satisfied' => ['Success', 'bi-check-circle'],
                                                            'installed' => ['Primary', 'bi-box'],
                                                            'missing' => ['Danger', 'bi-x-circle'],
                                                            'pending' => ['Warning', 'bi-clock'],
                                                            'rejected' => ['Danger', 'bi-x-octagon'],
                                                            'version-mismatch' => ['Danger', 'bi-exclamation-triangle'],
                                                            'invalid-key' => ['Secondary', 'bi-file-break'],
                                                            'invalid-constraint' => ['Secondary', 'bi-file-break'],
                                                            'malformed' => ['Danger', 'bi-file-break'],
                                                        ];
                                                        [$label, $icon] = $statusLabels[$d['status']] ?? ['Secondary', 'bi-question-circle'];
                                                        $bgClass = [
                                                            'Success' => 'success',
                                                            'Primary' => 'primary',
                                                            'Warning' => 'warning text-dark',
                                                            'Danger' => 'danger',
                                                            'Secondary' => 'secondary',
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?= htmlspecialchars($bgClass[$label] ?? 'secondary') ?>">
                                                            <i class="bi <?= htmlspecialchars($icon) ?> me-1"></i><?= htmlspecialchars($label) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ext['status'] === 'approved'): ?>
                            <span class="badge bg-success" title="Approved">
                                <i class="bi bi-check-circle me-1"></i>Approved
                            </span>
                            <?php elseif ($ext['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark" title="Pending review">
                                <i class="bi bi-clock me-1"></i>Pending
                            </span>
                            <?php else: ?>
                            <span class="badge bg-danger" title="Rejected">
                                <i class="bi bi-x-circle me-1"></i>Rejected
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int) $ext['is_installed'] === 1): ?>
                            <span class="badge bg-info text-dark" title="Installed">
                                <i class="bi bi-check-circle me-1"></i>Yes
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary" title="Not installed">
                                <i class="bi bi-dash-circle me-1"></i>—
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int) $ext['is_enabled'] === 1): ?>
                            <span class="badge bg-primary" title="Enabled">
                                <i class="bi bi-lightning me-1"></i>Enabled
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary" title="Disabled">
                                <i class="bi bi-dash-circle me-1"></i>—
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $deps = $depAnalysis[(int) $ext['id']] ?? [];
                            $blocked = [];
                            foreach ($deps as $d) {
                                if ($d['status'] === 'missing' || $d['status'] === 'version-mismatch') {
                                    $blocked[] = ['key' => $d['key'], 'status' => $d['status']];
                                }
                            }
                            ?>
                            <?php if ($blocked !== []): ?>
                            <span class="badge bg-danger" title="<?= count($blocked) ?> dependency blocker(s)">
                                <i class="bi bi-exclamation-triangle me-1"></i><?= count($blocked) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $update = $updates[$ext['slug']] ?? null;
                            if ($update !== null):
                                $badgeClass = 'secondary';
                                $badgeLabel = '&mdash;';
                                $badgeTitle = 'No update check available';
                                $badgeIcon = 'info-circle';
                                if ($update->isUpToDate()):
                                    $badgeClass = 'success';
                                    $badgeLabel = 'Up-to-date';
                                    $badgeTitle = 'Installed ' . htmlspecialchars($update->installedVersion) . ' matches catalog ' . htmlspecialchars($update->catalogVersion);
                                    $badgeIcon = 'check-circle';
                                elseif ($update->isBlocked()):
                                    $badgeClass = 'warning text-dark';
                                    $badgeLabel = 'Blocked';
                                    $badgeTitle = implode("\n", $update->blockers);
                                    $badgeIcon = 'exclamation-triangle';
                                elseif ($update->isInvalid()):
                                    $badgeClass = 'danger';
                                    $badgeLabel = 'Invalid';
                                    $badgeTitle = 'On-disk manifest is missing or invalid';
                                    $badgeIcon = 'x-circle';
                                endif;
                            elseif ((int) $ext['is_installed'] === 1):
                                $badgeClass = 'info text-dark';
                                $badgeLabel = 'Up-to-date';
                                $badgeTitle = 'Installed version matches catalog';
                                $badgeIcon = 'check-circle';
                            endif;
                            ?>
                            <span class="badge bg-<?= htmlspecialchars($badgeClass) ?>" title="<?= htmlspecialchars($badgeTitle) ?>">
                                <i class="bi bi-<?= htmlspecialchars($badgeIcon) ?> me-1"></i><?= $badgeLabel ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <div class="btn-group btn-group-sm" role="group">
                                <?php if ((int) $ext['is_installed'] === 0 && $ext['status'] === 'approved'): ?>
                                <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/install" style="display:inline;" onsubmit="return confirm('Install <?= htmlspecialchars($ext['name']) ?> (v<?= htmlspecialchars($ext['version']) ?>)? Place extension files in /storage/extension-staging/<?= htmlspecialchars($ext['slug']) ?>/ first.');">
                                    <button type="submit" class="btn btn-sm btn-success" title="Install">
                                        <i class="bi bi-download me-1"></i>Install
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ((int) $ext['is_installed'] === 1 && (int) $ext['is_enabled'] === 0): ?>
                                <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/enable" style="display:inline;" onsubmit="return confirm('Enable <?= htmlspecialchars($ext['name']) ?>?')">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Enable">
                                        <i class="bi bi-play-fill me-1"></i>Enable
                                    </button>
                                </form>
                                <?php elseif ((int) $ext['is_installed'] === 1 && (int) $ext['is_enabled'] === 1): ?>
                                <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/disable" style="display:inline;" onsubmit="return confirm('Disable <?= htmlspecialchars($ext['name']) ?>?')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Disable">
                                        <i class="bi bi-pause-fill me-1"></i>Disable
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ((int) $ext['is_installed'] === 1 && (int) $ext['is_enabled'] === 0): ?>
                                <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/uninstall" style="display:inline;" onsubmit="return confirm('Uninstall <?= htmlspecialchars($ext['name']) ?>?\n\nThis will remove all extension files from disk. The catalog record will be preserved for history.');">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Uninstall">
                                        <i class="bi bi-trash me-1"></i>Uninstall
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ((int) $ext['is_installed'] === 0): ?>
                                <form method="POST" action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/reject" style="display:inline;" onsubmit="return confirm('Reject <?= htmlspecialchars($ext['name']) ?>?')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from catalog">
                                        <i class="bi bi-x-circle me-1"></i>Remove
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
KernelWeb.dt.init('#admin-catalog-table', {
    order: [[0, 'asc']],
    columnDefs: [
        { orderable: false, targets: [5, 6, 7, 8, 9, 10] }
    ]
});
</script>
<?php endif; ?>

<div class="d-flex gap-2 mt-3">
    <a href="/admin/extensions" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Extensions
    </a>
</div>

<p class="text-muted small mt-3">
    Catalog extensions are managed through the database. Installed extensions are discovered from <code>lib/plugins/</code>, <code>lib/themes/</code>, and <code>lib/layouts/</code>.
</p>
