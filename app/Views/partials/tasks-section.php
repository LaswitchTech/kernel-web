<?php
/**
 * Reusable tasks section partial.
 *
 * Variables expected (set by the including view):
 *   $tasks       (array)   — rows from TaskService::getByEntity (may be empty)
 *   $entityType  (string)  — entity type string ('device', 'alert', 'finding')
 *   $entityId    (int)     — primary key of the linked entity
 *   $permissions (array)   — permission names for the authenticated user
 *
 * Intentionally NOT using DataTables: this is an embedded compact section
 * within a detail page, not a standalone table view.  The same rationale
 * applies as for the notes-section partial.
 *
 * The "Add Task" link is only rendered for users with tasks.manage.
 * The "Edit" action per row is also gated on tasks.manage.
 */

use App\Modules\Tasks\Services\TaskService;

$canManageTasks = in_array('tasks.manage', $permissions ?? [], true);
?>

<!-- ── Tasks ─────────────────────────────────────────────────────────────── -->
<div class="card mb-3" id="tasks">
    <div class="card-body">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="section-label mb-0">Tasks</h6>
            <?php if ($canManageTasks): ?>
            <a href="/tasks/create?entity_type=<?= rawurlencode($entityType) ?>&entity_id=<?= (int) $entityId ?>"
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Task
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($tasks)): ?>
        <p class="small text-muted mb-0">No tasks linked to this <?= htmlspecialchars($entityType) ?>.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th style="width:8rem">Status</th>
                        <th style="width:9rem">Assigned To</th>
                        <th style="width:8rem">Due</th>
                        <?php if ($canManageTasks): ?><th style="width:5rem"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tasks as $t):
                    $statusLabel = TaskService::STATUS_LABELS[$t['status']] ?? $t['status'];
                    $badgeClass  = match ($t['status']) {
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
                ?>
                <tr>
                    <td class="small fw-medium"><?= htmlspecialchars($t['title']) ?></td>
                    <td>
                        <span class="badge <?= $badgeClass ?>" style="font-size:.68rem">
                            <?= htmlspecialchars($statusLabel) ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?= $assignedName !== ''
                            ? htmlspecialchars($assignedName)
                            : '<span class="fst-italic">—</span>' ?>
                    </td>
                    <td class="small text-muted font-monospace">
                        <?= $t['due_at'] ? htmlspecialchars(substr($t['due_at'], 0, 10)) : '—' ?>
                    </td>
                    <?php if ($canManageTasks): ?>
                    <td class="text-end" style="white-space:nowrap;">
                        <a href="/tasks/<?= (int) $t['id'] ?>/edit"
                           class="btn btn-sm btn-outline-secondary py-0 px-1"
                           title="Edit task">
                            <i class="bi bi-pencil" style="font-size:.75rem;"></i>
                        </a>
                        <form method="POST"
                              action="/tasks/<?= (int) $t['id'] ?>/delete"
                              class="d-inline ms-1"
                              onsubmit="return confirm('Delete this task? This cannot be undone.');">
                            <button type="submit"
                                    class="btn btn-sm btn-outline-danger py-0 px-1"
                                    title="Delete task">
                                <i class="bi bi-trash" style="font-size:.75rem;"></i>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>
