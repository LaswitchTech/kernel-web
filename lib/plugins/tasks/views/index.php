<?php
/**
 * Tasks list — content fragment.
 *
 * Variables set by TaskController::index():
 *   $tasks         (array)   — rows from TaskService for the active scope
 *   $scope         (string)  — active scope: 'all' | 'mine' | 'overdue' | 'due-today'
 *   $countOpen       (int)     — open + in_progress tasks assigned to current user
 *   $countOverdue    (int)     — overdue tasks assigned to current user
 *   $countDueToday   (int)     — due-today tasks assigned to current user
 *   $countUnassigned (int)     — active tasks with no assignee (system-wide)
 *   $flash           (?array)  — ['type' => string, 'message' => string] or null
 *   $permissions     (array)   — permission names for the authenticated user
 */

use App\Plugins\tasks\TaskService;

$emptyMessages = [
    'all'        => 'No tasks yet.',
    'mine'       => 'You have no active tasks assigned to you.',
    'overdue'    => 'No overdue tasks assigned to you.',
    'due-today'  => 'No tasks due today assigned to you.',
    'unassigned' => 'No unassigned open tasks.',
];
$emptyMsg = $emptyMessages[$scope] ?? 'No tasks.';
?>

<!-- ── Flash ──────────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ── Page heading ───────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Tasks</h1>
        <p class="text-muted mb-0 small">
            Track and manage follow-up tasks linked to devices, alerts, and findings.
        </p>
    </div>
    <a href="/tasks/create" class="btn btn-sm btn-primary mt-1">
        <i class="bi bi-plus-lg me-1"></i>New Task
    </a>
</div>

<!-- ── Summary cards ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- My open tasks -->
    <div class="col-sm-6 col-xl-3">
        <a href="/tasks?scope=mine" class="text-decoration-none">
            <div class="card h-100 <?= $scope === 'mine' ? 'border-primary' : '' ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded p-2 bg-primary bg-opacity-10 text-primary flex-shrink-0">
                        <i class="bi bi-list-task fs-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-muted small">My Open Tasks</div>
                        <div class="fw-semibold fs-5"><?= $countOpen ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- My overdue -->
    <div class="col-sm-6 col-xl-3">
        <a href="/tasks?scope=overdue" class="text-decoration-none">
            <div class="card h-100 <?= $scope === 'overdue' ? 'border-danger' : '' ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded p-2 <?= $countOverdue > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-secondary bg-opacity-10 text-secondary' ?> flex-shrink-0">
                        <i class="bi bi-exclamation-circle fs-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-muted small">My Overdue</div>
                        <div class="fw-semibold fs-5 <?= $countOverdue > 0 ? 'text-danger' : '' ?>"><?= $countOverdue ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Due today (mine) -->
    <div class="col-sm-6 col-xl-3">
        <a href="/tasks?scope=due-today" class="text-decoration-none">
            <div class="card h-100 <?= $scope === 'due-today' ? 'border-warning' : '' ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded p-2 <?= $countDueToday > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-secondary bg-opacity-10 text-secondary' ?> flex-shrink-0">
                        <i class="bi bi-calendar-check fs-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-muted small">Due Today</div>
                        <div class="fw-semibold fs-5 <?= $countDueToday > 0 ? 'text-warning' : '' ?>"><?= $countDueToday ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Unassigned (system-wide) -->
    <div class="col-sm-6 col-xl-3">
        <a href="/tasks?scope=unassigned" class="text-decoration-none">
            <div class="card h-100 <?= $scope === 'unassigned' ? 'border-secondary' : '' ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded p-2 <?= $countUnassigned > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-secondary bg-opacity-10 text-secondary' ?> flex-shrink-0">
                        <i class="bi bi-person-dash fs-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-muted small">Unassigned</div>
                        <div class="fw-semibold fs-5 <?= $countUnassigned > 0 ? 'text-warning' : '' ?>"><?= $countUnassigned ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>

</div>

<!-- ── Task list ──────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tasks-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Linked To</th>
                        <th>Due</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $t):
                        $statusLabel = TaskService::STATUS_LABELS[$t['status']] ?? $t['status'];
                        $badgeClass = match ($t['status']) {
                            'open'        => 'bg-primary-subtle text-primary border border-primary-subtle',
                            'in_progress' => 'bg-warning-subtle text-warning border border-warning-subtle',
                            'completed'   => 'bg-success-subtle text-success border border-success-subtle',
                            'canceled'    => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                            default       => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                        };
                        $assignedName = '';
                        if ($t['assigned_type'] === 'user' && !empty($t['assigned_id'])) {
                            $assignedName = ($t['assigned_display'] ?? '') !== ''
                                ? $t['assigned_display']
                                : ($t['assigned_username'] ?? '');
                        } elseif ($t['assigned_type'] === 'cron') {
                            $assignedName = 'System';
                            if (!empty($t['execution_type'])) {
                                $assignedName .= ' (' . htmlspecialchars($t['execution_type']) . ')';
                            }
                        }
                        // Build entity link cell
                        $entityCell = '—';
                        if (!empty($t['entity_type']) && !empty($t['entity_id'])) {
                            $entityUrl = match ($t['entity_type']) {
                                'device'  => '/devices/'   . (int) $t['entity_id'],
                                'alert'   => '/alerts/'    . (int) $t['entity_id'],
                                'finding' => '/discovery/' . (int) $t['entity_id'],
                                default   => null,
                            };
                            $entityLabel = ucfirst(htmlspecialchars($t['entity_type'])) . ' #' . (int) $t['entity_id'];
                            $entityCell  = $entityUrl
                                ? '<a href="' . $entityUrl . '" class="text-decoration-none small">' . $entityLabel . '</a>'
                                : '<span class="small text-muted">' . $entityLabel . '</span>';
                        }
                        // Highlight overdue due dates
                        $dueCell = '—';
                        if ($t['due_at']) {
                            $dueStr    = htmlspecialchars(substr($t['due_at'], 0, 10));
                            $isOverdue = strtotime($t['due_at']) < strtotime('today')
                                && !in_array($t['status'], ['completed', 'canceled'], true);
                            $dueCell = $isOverdue
                                ? '<span class="text-danger fw-medium font-monospace small">' . $dueStr . '</span>'
                                : '<span class="font-monospace small">' . $dueStr . '</span>';
                        }
                    ?>
                    <tr>
                        <td class="fw-medium"><?= htmlspecialchars($t['title']) ?></td>
                        <td>
                            <span class="badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= $assignedName !== '' ? htmlspecialchars($assignedName) : '<span class="fst-italic">Unassigned</span>' ?>
                        </td>
                        <td><?= $entityCell ?></td>
                        <td style="white-space:nowrap;"><?= $dueCell ?></td>
                        <td class="text-muted small" style="white-space:nowrap;">
                            <?= htmlspecialchars(substr($t['created_at'], 0, 10)) ?>
                        </td>
                        <td class="text-end" style="white-space:nowrap;">
                            <a href="/tasks/<?= (int) $t['id'] ?>/edit"
                               class="btn btn-sm btn-outline-secondary"
                               title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST"
                                  action="/tasks/<?= (int) $t['id'] ?>/delete"
                                  class="d-inline ms-1"
                                  onsubmit="return confirm('Delete this task? This cannot be undone.');">
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
    var scope = <?= json_encode($scope) ?>;

    window.addEventListener('DOMContentLoaded', function () {
        var dt = KernelWeb.dt.init('#tasks-table', {
            order: [[5, 'desc']],
            language: { emptyTable: <?= json_encode($emptyMsg) ?> },
            columnDefs: [
                { orderable: false, targets: [6] },
            ]
        });

        // Inject scope toggle into the DataTables buttons area.
        var scopes = [
            { key: 'all',        label: 'All' },
            { key: 'mine',       label: 'Mine' },
            { key: 'overdue',    label: 'Overdue' },
            { key: 'due-today',  label: 'Due Today' },
            { key: 'unassigned', label: 'Unassigned' },
        ];
        var buttons = scopes.map(function (s) {
            var cls = s.key === scope ? 'btn-primary' : 'btn-outline-secondary';
            return '<a href="/tasks?scope=' + s.key + '" class="btn ' + cls + '">' + s.label + '</a>';
        }).join('');
        dt.buttons().container().prepend(
            '<div class="btn-group btn-group-sm me-2" role="group" aria-label="Task scope">' + buttons + '</div>'
        );
    });
}());
</script>
