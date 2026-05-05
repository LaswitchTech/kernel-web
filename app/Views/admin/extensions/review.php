<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (empty($pending)): ?>
<div class="card mb-4">
    <div class="card-body p-4 text-center text-muted">
        <i class="bi bi-check-circle d-block mb-2" style="font-size:1.5rem;opacity:.4;"></i>
        No pending submissions.
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">
            Pending Submissions (<?= count($pending) ?>)
        </span>
        <a href="/admin/extensions/catalog" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Catalog
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="admin-review-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Type</th>
                        <th>Version</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th class="text-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $ext): ?>
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
                        <td class="text-muted small"><?= htmlspecialchars($ext['version']) ?></td>
                        <td class="text-muted small"><?= $ext['author'] !== '' ? htmlspecialchars($ext['author']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                        <td>
                            <span class="badge bg-warning text-dark" title="Awaiting review">
                                <i class="bi bi-clock me-1"></i>Pending
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="#approveModal<?= $ext['id'] ?>" class="btn btn-sm btn-success" data-bs-toggle="modal" title="Approve extension">
                                    <i class="bi bi-check-lg me-1"></i>Approve
                                </a>
                                <a href="#rejectModal<?= $ext['id'] ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" title="Reject extension">
                                    <i class="bi bi-x-lg me-1"></i>Reject
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Approve Modal -->
                    <div class="modal fade" id="approveModal<?= $ext['id'] ?>" tabindex="-1" aria-labelledby="approveModalLabel<?= $ext['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/approve" method="POST">
                                    <div class="modal-header">
                                        <h6 class="modal-title" id="approveModalLabel<?= $ext['id'] ?>">Approve Extension</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to approve <strong><?= htmlspecialchars($ext['name']) ?></strong>?</p>
                                        <p class="mb-0 text-muted small">This will set the extension status to <span class="badge bg-success">Approved</span>, making it available for installation.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Approve</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal<?= $ext['id'] ?>" tabindex="-1" aria-labelledby="rejectModalLabel<?= $ext['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="/admin/extensions/catalog/<?= (int) $ext['id'] ?>/reject" method="POST">
                                    <div class="modal-header">
                                        <h6 class="modal-title" id="rejectModalLabel<?= $ext['id'] ?>">Reject Extension</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to reject <strong><?= htmlspecialchars($ext['name']) ?></strong>?</p>
                                        <p class="mb-0 text-muted small">This will set the extension status to <span class="badge bg-danger">Rejected</span>.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-outline-danger">Reject</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
KernelWeb.dt.init('#admin-review-table', {
    order: [[0, 'asc']],
    columnDefs: [
        { orderable: false, targets: [6] }
    ]
});
</script>
<?php endif; ?>

<p class="text-muted small mt-3">
    Approved extensions are available for installation through the catalog. Rejected entries are removed from the catalog.
</p>
