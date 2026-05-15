<?php if ($flash = $flash ?? null): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="mb-3">
    <a href="/admin" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Administration
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">Organizations (<?= count($allOrgs ?? []) ?>)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($allOrgs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-folder2-open d-block mb-2" style="font-size:2rem;opacity:.3;"></i>
                <p class="mb-0 small">No organizations yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:5%;">#</th>
                            <th style="width:30%;">Name</th>
                            <th style="width:15%;">Slug</th>
                            <th style="width:10%;">Type</th>
                            <th style="width:15%;">Members</th>
                            <th style="width:15%;">Status</th>
                            <th style="width:10%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allOrgs as $i => $org): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($org['name'] ?? '—') ?></td>
                            <td><code><?= htmlspecialchars($org['slug'] ?? '—') ?></code></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($org['type'] ?? 'personal') ?></span></td>
                            <td>
                                <?php
                                $count = 0;
                                if (isset($memberRepo)) {
                                    try {
                                        $members = $memberRepo->findMembers((int) $org['id']);
                                        $count = count($members);
                                    } catch (\Throwable) { $count = 0; }
                                }
                                ?>
                                <span class="text-muted"><?= $count ?></span>
                            </td>
                            <td>
                                <?php if ($org['is_active'] ?? false): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="/admin/organizations/<?= (int) $org['id'] ?>/toggle" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars("Toggle status of " . $org['name']) ?>?')">
                                    <button type="submit" class="btn btn-sm btn-outline-<?php echo ($org['is_active'] ?? false) ? 'warning text-dark' : 'success' ?>">
                                        <?= ($org['is_active'] ?? false) ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
