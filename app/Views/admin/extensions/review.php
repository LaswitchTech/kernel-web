<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold small">Pending Submissions (<?= count($pending) ?>)</span>
                <a href="/admin/extensions/catalog" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Catalog
                </a>
            </div>
            <?php if (empty($pending)): ?>
            <div class="card-body p-4 text-center text-muted">
                No pending submissions.
            </div>
            <?php else: ?>
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
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $ext): ?>
                            <tr>
                                <td><?= htmlspecialchars($ext['name']) ?></td>
                                <td>
                                    <code><?= htmlspecialchars($ext['slug']) ?></code>
                                </td>
                                <td class="text-muted small"><?= ucfirst(htmlspecialchars($ext['type'])) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($ext['version']) ?></td>
                                <td class="text-muted small"><?= $ext['author'] !== '' ? htmlspecialchars($ext['author']) : '<span class="text-muted fst-italic">—</span>' ?></td>
                                <td>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                </td>
                                <td class="text-end" style="white-space:nowrap;">
                                    <a href="#approveModal<?= $ext['id'] ?>" class="btn btn-sm btn-success" data-bs-toggle="modal">
                                        <i class="bi bi-check-lg"></i> Approve
                                    </a>
                                    <a href="#rejectModal<?= $ext['id'] ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal">
                                        <i class="bi bi-x-lg"></i> Reject
                                    </a>
                                </td>
                            </tr>

                            <!-- Approve Modal -->
                            <div class="modal fade" id="approveModal<?= $ext['id'] ?>" tabindex="-1" aria-labelledby="approveModalLabel<?= $ext['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="/admin/extensions/catalog/<?= $ext['id'] ?>/approve" method="POST">
                                            <div class="modal-header">
                                                <h6 class="modal-title" id="approveModalLabel<?= $ext['id'] ?>">Approve Extension</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to approve <strong><?= htmlspecialchars($ext['name']) ?></strong>?</p>
                                                <p class="mb-0 text-muted small">This will set the extension status to <span class="badge bg-success">Approved</span>.</p>
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
                                        <form action="/admin/extensions/catalog/<?= $ext['id'] ?>/reject" method="POST">
                                            <div class="modal-header">
                                                <h6 class="modal-title" id="rejectModalLabel<?= $ext['id'] ?>">Reject Extension</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to reject <strong><?= htmlspecialchars($ext['name']) ?></strong>?</p>
                                                <p class="mb-0 text-muted small">This will set the extension extension status to <span class="badge bg-danger">Rejected</span>.</p>
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
            <script>
            KernelWeb.dt.init('#admin-review-table', {
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [6] }
                ]
            });
            </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<p class="text-muted small">
    Approved extensions are available for future installation. Rejected entries are removed from the catalog.
</p>
