<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center justify-content-between">
            <span class="fw-semibold small">Audit Log</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter audit log">
                <a href="/admin/audit?type=all" class="btn btn-outline-secondary <?= $auditType === 'all' ? 'active' : '' ?>">All</a>
                <a href="/admin/audit?type=audit" class="btn btn-outline-secondary <?= $auditType === 'audit' ? 'active' : '' ?>">Audit</a>
                <a href="/admin/audit?type=debug" class="btn btn-outline-secondary <?= $auditType === 'debug' ? 'active' : '' ?>">Debug</a>
            </div>
        </div>
        <div class="text-muted small mt-1">Last <?= count($auditRows) ?> entries</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="admin-audit-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditRows as $row):
                        $actor = $row['actor_display_name']
                            ? htmlspecialchars($row['actor_display_name'])
                            : ($row['actor_username']
                                ? '<code>' . htmlspecialchars($row['actor_username']) . '</code>'
                                : '<span class="text-muted fst-italic">system</span>');

                        $meta    = json_decode($row['meta'] ?? '{}', true) ?: [];
                        // Safe scalar rendering — arrays/objects become JSON preview, others become string.
                        $summary = implode(', ', array_map(
                            fn($k, $v) => htmlspecialchars($k) . '=' . htmlspecialchars(
                                is_array($v) || is_object($v)
                                    ? substr(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 80)
                                    : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v)
                            ),
                            array_keys($meta),
                            array_values($meta)
                        ));
                        if ($summary === '') $summary = '—';
                    ?>
                    <tr class="<?= str_starts_with($row['action'], 'debug.') ? 'table-info-subtle' : '' ?>">
                        <td class="text-muted small" style="white-space:nowrap;">
                            <?= htmlspecialchars($row['created_at']) ?>
                        </td>
                        <td><?= $actor ?></td>
                        <td>
                            <code class="text-body"><?= htmlspecialchars($row['action']) ?></code>
                            <?= str_starts_with($row['action'], 'debug.') ? '<span class="badge bg-info-subtle text-info ms-1" style="font-size:.7em">debug</span>' : '' ?>
                        </td>
                        <td class="text-muted small" style="white-space:nowrap;">
                            <?= htmlspecialchars($row['entity_type']) ?>
                            <span class="text-muted">#<?= (int) $row['entity_id'] ?></span>
                        </td>
                        <td class="text-muted small" style="max-width:360px; word-break:break-word;">
                            <?= $summary ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($auditRows)): ?>
                    <!-- DataTables will render its own empty state -->
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
KernelWeb.dt.init('#admin-audit-table', {
    order: [[0, 'desc']],
    columnDefs: [
        { orderable: false, targets: [4] }
    ],
    language: {
        emptyTable: 'No data available.'
    }
});
</script>
