<!-- Version info bar -->
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <small class="text-muted">
                <i class="bi bi-box"></i> <?= htmlspecialchars($versions['application']['name']) ?> v<?= htmlspecialchars($versions['application']['version']) ?>
            </small>
            <small class="text-muted">
                <i class="bi bi-cube"></i> Kernel v<?= htmlspecialchars($versions['kernel']['version']) ?>
            </small>
            <span class="badge bg-secondary">Update check not configured</span>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-4">
        <a href="/admin/users" class="card text-decoration-none h-100 admin-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="admin-stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="admin-stat-value"><?= $userCount ?></div>
                    <div class="admin-stat-label">Users</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-4">
        <a href="/admin/groups" class="card text-decoration-none h-100 admin-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="admin-stat-icon">
                    <i class="bi bi-collection"></i>
                </div>
                <div>
                    <div class="admin-stat-value"><?= $groupCount ?></div>
                    <div class="admin-stat-label">Groups</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-4">
        <a href="/admin/permissions" class="card text-decoration-none h-100 admin-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="admin-stat-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <div class="admin-stat-value"><?= $permissionCount ?></div>
                    <div class="admin-stat-label">Permissions</div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold small">Quick Navigation</span>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="/admin/users" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-0">
                        <i class="bi bi-people text-muted"></i>
                        <div>
                            <div class="small fw-semibold">Users</div>
                            <div class="text-muted" style="font-size:.8rem;">View all user accounts and their active status</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted small"></i>
                    </a>
                    <a href="/admin/groups" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-0">
                        <i class="bi bi-collection text-muted"></i>
                        <div>
                            <div class="small fw-semibold">Groups</div>
                            <div class="text-muted" style="font-size:.8rem;">View groups and their member/permission counts</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted small"></i>
                    </a>
                    <a href="/admin/permissions" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-0">
                        <i class="bi bi-shield-check text-muted"></i>
                        <div>
                            <div class="small fw-semibold">Permissions</div>
                            <div class="text-muted" style="font-size:.8rem;">View all available permissions and group assignments</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted small"></i>
                    </a>
                    <a href="/admin/extensions" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-0">
                        <i class="bi bi-boxes text-muted"></i>
                        <div>
                            <div class="small fw-semibold">Extensions</div>
                            <div class="text-muted" style="font-size:.8rem;">Browse discovered plugins, themes, and layouts</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted small"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
