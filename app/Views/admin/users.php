<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">All Users</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="admin-users-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Display Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['display_name']) ?></td>
                        <td>
                            <code class="text-muted"><?= htmlspecialchars($u['username']) ?></code>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= htmlspecialchars(substr($u['created_at'], 0, 10)) ?>
                        </td>
                        <td class="text-end" style="white-space:nowrap;">
                            <a href="/admin/users/<?= (int) $u['id'] ?>/edit-account"
                               class="btn btn-sm btn-outline-secondary"
                               title="Edit account details">
                                <i class="bi bi-person-gear"></i>
                            </a>
                            <a href="/admin/users/<?= (int) $u['id'] ?>/edit"
                               class="btn btn-sm btn-outline-secondary"
                               title="Edit group membership">
                                <i class="bi bi-people"></i>
                            </a>
                            <?php if ($u['is_active']): ?>
                            <form method="POST" action="/admin/users/<?= (int) $u['id'] ?>/deactivate"
                                  class="d-inline"
                                  onsubmit="return confirm('Deactivate <?= htmlspecialchars(addslashes($u['display_name'])) ?>? They will no longer be able to log in.')">
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Deactivate">
                                    <i class="bi bi-person-slash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" action="/admin/users/<?= (int) $u['id'] ?>/activate"
                                  class="d-inline">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Activate">
                                    <i class="bi bi-person-check"></i>
                                </button>
                            </form>
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
KernelWeb.dt.init('#admin-users-table', {
    order: [[0, 'asc']],
    columnDefs: [
        { orderable: false, targets: [3, 5] }
    ],
    buttons: [
        {
            text: '<i class="bi bi-person-plus" aria-hidden="true"></i><span class="visually-hidden">New User</span>',
            className: 'btn-primary',
            init: function (dt, node){
                $(node).removeClass('btn-secondary');
            },
            titleAttr: 'New User',
            action: function (e, dt, node, config) {
                window.location.href = '/admin/users/create';
            }
        },
        {
            extend: 'collection',
            text: '<i class="bi-check2-square"></i><span class="visually-hidden">Select</span>',
            buttons: [
                {
                    extend: 'selectAll',
                    text: '<i class="bi-check2-all me-2"></i>All',
                },
                {
                    extend: 'selectNone',
                    text: '<i class="bi-x-square me-2"></i>None',
                },
                {
                    name: 'selectFiltered',
                    text: '<i class="bi-eye me-2"></i>Filtered',
                    action: function (e, dt, node, config) {
                        dt.rows({ selected: true }).deselect();
                        dt.rows({ search: 'applied', page: 'all' }).select();
                    },
                },
                {
                    name: 'selectUnfiltered',
                    text: '<i class="bi-eye-slash me-2"></i>Unfiltered',
                    action: function (e, dt, node, config) {
                        dt.rows({ selected: true }).deselect();
                        dt.rows({ search: 'removed', page: 'all' }).select();
                    },
                },
            ]
        },
        {
            extend: 'collection',
            text: '<i class="bi-arrow-bar-down"></i><span class="visually-hidden">Export</span>',
            buttons: [
                {
                    extend: 'copy',
                    text: '<i class="bi-clipboard me-2"></i>Clipboard',
                    exportOptions: {
                        columns: ':visible:not(:last-child)',
                    },
                },
                {
                    extend: 'excel',
                    text: '<i class="bi-filetype-xlsx me-2"></i>Excel',
                    exportOptions: {
                        columns: ':visible:not(:last-child)',
                    },
                },
                {
                    extend: 'csv',
                    text: '<i class="bi-filetype-csv me-2"></i>CSV',
                    exportOptions: {
                        columns: ':visible:not(:last-child)',
                    },
                },
                {
                    extend: 'pdf',
                    text: '<i class="bi-filetype-pdf me-2"></i>PDF',
                    exportOptions: {
                        columns: ':visible:not(:last-child)',
                    },
                },
            ],
        },
        {
            text: '<i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i><span class="visually-hidden">Column Visibility</span>',
            titleAttr: 'Column Visibility',
            extend: 'colvis'
        },
        {
            text: '<i class="bi bi-list" aria-hidden="true"></i><span class="visually-hidden">Number of rows</span>',
            titleAttr: 'Number of rows',
            extend: 'pageLength'
        }
    ]
});
</script>
