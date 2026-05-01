<?php
// $task    — array from TaskService::getById() — includes execution and run-tracking fields
// $users   — array of active users from UserRepository::findAllActive()
// $errors  — array of field => message pairs
// $old     — repopulation values from failed submission
// $flash   — ?array ['type' => string, 'message' => string]
//
// Assignment model:
//   assigned_type = 'user'  → assigned_id (user PK) required
//   assigned_type = 'cron'  → execution_type required; assigned_id must be null
//   assigned_type = null    → unassigned

use App\Modules\Tasks\Services\TaskService;

$old    = $old    ?? [];
$errors = $errors ?? [];

// When repopulating after a failed submit, prefer $old; otherwise use $task values.
function taskEditVal(string $key, array $old, array $task): string {
    $val = array_key_exists($key, $old) ? ($old[$key] ?? '') : ($task[$key] ?? '');
    return htmlspecialchars((string) $val);
}

$currentStatus = array_key_exists('status', $old) ? ($old['status'] ?? 'open') : ($task['status'] ?? 'open');

// Resolve current assignment type.
$currentAssignedType = array_key_exists('assigned_type', $old)
    ? ($old['assigned_type'] ?? '')
    : ($task['assigned_type'] ?? '');

// Resolve current assigned user (only relevant when assigned_type = 'user').
$currentAssignedId = array_key_exists('assigned_id', $old)
    ? (int) ($old['assigned_id'] ?? 0)
    : (int) ($task['assigned_type'] === 'user' ? ($task['assigned_id'] ?? 0) : 0);

// Resolve current execution type (only relevant when assigned_type = 'cron').
$currentExecutionType = array_key_exists('execution_type', $old)
    ? ($old['execution_type'] ?? '')
    : ($task['execution_type'] ?? '');

// Resolve schedule fields (only relevant when assigned_type = 'cron').
$currentScheduleType = array_key_exists('schedule_type', $old)
    ? ($old['schedule_type'] ?? 'always')
    : ($task['schedule_type'] ?? 'always');
$currentScheduleValue = array_key_exists('schedule_value', $old)
    ? ($old['schedule_value'] ?? '')
    : ($task['schedule_value'] ?? '');
?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <div class="mb-3">
            <a href="/tasks" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Tasks
            </a>
        </div>

        <!-- Flash -->
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="/tasks/<?= (int) $task['id'] ?>">

                    <!-- Title -->
                    <div class="mb-3">
                        <label for="task-title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text"
                               id="task-title"
                               name="title"
                               class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                               value="<?= taskEditVal('title', $old, $task) ?>"
                               maxlength="255"
                               autocomplete="off"
                               required>
                        <?php if (isset($errors['title'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="task-desc" class="form-label">Description</label>
                        <textarea id="task-desc"
                                  name="description"
                                  class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                                  rows="4"><?= taskEditVal('description', $old, $task) ?></textarea>
                        <?php if (isset($errors['description'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['description']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Status -->
                        <div class="col-sm-6">
                            <label for="task-status" class="form-label">Status</label>
                            <select id="task-status" name="status" class="form-select">
                                <?php foreach (TaskService::STATUS_LABELS as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"
                                    <?= $currentStatus === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Assignment Type -->
                        <div class="col-sm-6">
                            <label for="task-assigned-type" class="form-label">Assignment</label>
                            <select id="task-assigned-type" name="assigned_type" class="form-select">
                                <option value="" <?= ($currentAssignedType === '' || $currentAssignedType === null) ? 'selected' : '' ?>>— Unassigned —</option>
                                <?php foreach (TaskService::ASSIGNED_TYPE_LABELS as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"
                                    <?= $currentAssignedType === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- User panel (visible when assigned_type = 'user') -->
                    <div id="panel-user" class="mb-3" style="display:none">
                        <label for="task-assigned-user" class="form-label">Assigned User</label>
                        <select id="task-assigned-user" name="assigned_id" class="form-select">
                            <option value="">— Select a user —</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"
                                <?= $currentAssignedId === (int) $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($u['display_name'] ?? '') !== '' ? $u['display_name'] : $u['username']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cron panel (visible when assigned_type = 'cron') -->
                    <div id="panel-cron" style="display:none">
                        <div class="mb-3">
                            <label for="task-execution-type" class="form-label">
                                Execution Type <span class="text-danger">*</span>
                            </label>
                            <select id="task-execution-type" name="execution_type" class="form-select">
                                <option value="">— Select execution type —</option>
                                <?php foreach (TaskService::EXECUTION_TYPE_LABELS as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"
                                    <?= $currentExecutionType === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">The system script that will run when this task is dispatched by the scheduler.</div>
                        </div>
                        <div class="mb-3">
                            <label for="task-execution-payload" class="form-label">Execution Payload</label>
                            <textarea id="task-execution-payload"
                                      name="execution_payload"
                                      class="form-control font-monospace"
                                      rows="3"
                                      placeholder="{}"><?= taskEditVal('execution_payload', $old, $task) ?></textarea>
                            <div class="form-text">Optional JSON payload passed to the execution script.</div>
                        </div>

                        <!-- Schedule Type -->
                        <div class="mb-3">
                            <label for="task-schedule-type" class="form-label">Schedule</label>
                            <select id="task-schedule-type" name="schedule_type" class="form-select <?= isset($errors['schedule_type']) ? 'is-invalid' : '' ?>">
                                <?php foreach (TaskService::SCHEDULE_TYPE_LABELS as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"
                                    <?= $currentScheduleType === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['schedule_type'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['schedule_type']) ?></div>
                            <?php else: ?>
                            <div class="form-text">Controls when the scheduler will execute this task.</div>
                            <?php endif; ?>
                        </div>

                        <!-- Interval sub-panel -->
                        <div id="panel-schedule-interval" class="mb-3" style="display:none">
                            <label for="task-schedule-interval" class="form-label">Interval <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number"
                                       id="task-schedule-interval"
                                       name="schedule_value"
                                       class="form-control <?= (isset($errors['schedule_value']) && $currentScheduleType === 'interval') ? 'is-invalid' : '' ?>"
                                       min="1"
                                       placeholder="3600"
                                       value="<?= $currentScheduleType === 'interval' ? htmlspecialchars($currentScheduleValue) : '' ?>">
                                <span class="input-group-text">seconds</span>
                                <?php if (isset($errors['schedule_value']) && $currentScheduleType === 'interval'): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['schedule_value']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-text">Run every N seconds after the previous run completes. Example: <code>3600</code> = every hour.</div>
                        </div>

                        <!-- Cron expression sub-panel -->
                        <div id="panel-schedule-cron" class="mb-3" style="display:none">
                            <label for="task-schedule-cron-expr" class="form-label">Cron Expression <span class="text-danger">*</span></label>
                            <input type="text"
                                   id="task-schedule-cron-expr"
                                   name="schedule_value"
                                   class="form-control font-monospace <?= (isset($errors['schedule_value']) && $currentScheduleType === 'cron') ? 'is-invalid' : '' ?>"
                                   placeholder="* * * * *"
                                   autocomplete="off"
                                   value="<?= $currentScheduleType === 'cron' ? htmlspecialchars($currentScheduleValue) : '' ?>">
                            <?php if (isset($errors['schedule_value']) && $currentScheduleType === 'cron'): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['schedule_value']) ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                5-field expression: <code>minute hour day month weekday</code><br>
                                Examples: <code>*/5 * * * *</code> (every 5 min) &nbsp; <code>0 * * * *</code> (hourly) &nbsp; <code>0 0 * * *</code> (daily midnight)
                            </div>
                        </div>
                    </div>

                    <!-- Due Date -->
                    <div class="mb-4">
                        <label for="task-due" class="form-label">Due Date</label>
                        <input type="datetime-local"
                               id="task-due"
                               name="due_at"
                               class="form-control"
                               value="<?= taskEditVal('due_at', $old, $task) ?>">
                        <div class="form-text">Optional. Leave blank if there is no deadline.</div>
                    </div>

                    <!-- Meta -->
                    <div class="mb-4 text-muted small">
                        <?php if ($task['entity_type']): ?>
                        <div>Linked to: <strong><?= htmlspecialchars($task['entity_type']) ?></strong> #<?= (int) $task['entity_id'] ?></div>
                        <?php endif; ?>
                        <div>Created: <?= htmlspecialchars(substr($task['created_at'], 0, 16)) ?></div>
                        <div>Last updated: <?= htmlspecialchars(substr($task['updated_at'], 0, 16)) ?></div>
                        <?php if ($task['assigned_type'] === 'cron' && $task['last_run_at']): ?>
                        <div class="mt-1">
                            Last run: <?= htmlspecialchars(substr($task['last_run_at'], 0, 16)) ?>
                            — <span class="<?= $task['last_run_status'] === 'ok' ? 'text-success' : 'text-danger' ?>">
                                <?= htmlspecialchars($task['last_run_status'] ?? '—') ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="/tasks" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    const typeSelect     = document.getElementById('task-assigned-type');
    const scheduleSelect = document.getElementById('task-schedule-type');
    const panelUser      = document.getElementById('panel-user');
    const panelCron      = document.getElementById('panel-cron');
    const panelInterval  = document.getElementById('panel-schedule-interval');
    const panelCronExpr  = document.getElementById('panel-schedule-cron');
    const inputInterval  = document.getElementById('task-schedule-interval');
    const inputCronExpr  = document.getElementById('task-schedule-cron-expr');

    function updateSchedulePanels() {
        const isCron     = typeSelect.value === 'cron';
        const sv         = scheduleSelect.value;
        const showIntv   = isCron && sv === 'interval';
        const showCronEx = isCron && sv === 'cron';

        panelInterval.style.display = showIntv   ? '' : 'none';
        panelCronExpr.style.display = showCronEx ? '' : 'none';
        inputInterval.disabled = !showIntv;
        inputCronExpr.disabled = !showCronEx;
    }

    function updatePanels() {
        const v = typeSelect.value;
        panelUser.style.display = v === 'user' ? '' : 'none';
        panelCron.style.display = v === 'cron' ? '' : 'none';
        updateSchedulePanels();
    }

    typeSelect.addEventListener('change', updatePanels);
    scheduleSelect.addEventListener('change', updateSchedulePanels);
    updatePanels();
}());
</script>
