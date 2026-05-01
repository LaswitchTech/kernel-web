<?php

namespace App\Modules\Tasks\Controllers;

use App\Core\Controller;
use App\Models\AuditLogRepository;
use App\Models\UserRepository;
use App\Modules\Tasks\Models\TaskRepository;
use App\Modules\Tasks\Services\TaskService;

/**
 * Task Management module controller.
 *
 * Routes:
 *
 *   GET  /tasks              → index()       Task list (DataTable)
 *   GET  /tasks/create       → createForm()  Create form
 *   POST /tasks              → store()       Handle create
 *   GET  /tasks/{id}/edit    → editForm()    Edit form
 *   POST /tasks/{id}/delete  → delete()      Handle soft delete
 *   POST /tasks/{id}         → update()      Handle edit
 *
 * All routes are protected by ['WebAuth', 'WebPermission:tasks.manage'].
 */
class TaskController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /tasks
    // -------------------------------------------------------------------------

    public function index(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $service   = new TaskService(new TaskRepository($this->container->get('db')));
        $userId    = (int) ($principal['user']['id'] ?? 0);

        // Allowed scope values; default to 'all'.
        $validScopes = ['all', 'mine', 'overdue', 'due-today', 'unassigned'];
        $scope       = trim($_GET['scope'] ?? 'all');
        if (!in_array($scope, $validScopes, true)) {
            $scope = 'all';
        }

        // Fetch the task list for the active scope.
        $tasks = match ($scope) {
            'mine'       => $service->getForUser($userId),
            'overdue'    => $service->getOverdueForUser($userId),
            'due-today'  => $service->getDueTodayForUser($userId),
            'unassigned' => $service->getUnassigned(),
            default      => $service->getAll(),
        };

        // Summary counts are always for the logged-in user regardless of scope.
        $countOpen       = $service->countOpenForUser($userId);
        $countOverdue    = $service->countOverdueForUser($userId);
        $countDueToday   = $service->countDueTodayForUser($userId);
        $countUnassigned = $service->countUnassigned();

        $pageTitle     = 'Tasks';
        $activeSection = 'Tasks';
        $flash         = $this->popFlash();

        ob_start();
        require $viewsPath . '/tasks/index.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // GET /tasks/create
    // -------------------------------------------------------------------------

    public function createForm(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $users = (new UserRepository($this->container->get('db')))->findAllActive();

        // Accept optional entity context from query string so that "Add Task"
        // links on entity detail pages pre-fill the linkage fields.
        $entityType = trim($_GET['entity_type'] ?? '');
        $entityId   = (int) ($_GET['entity_id'] ?? 0);

        // Silently discard invalid entity types — don't expose an error for GET params.
        if (!in_array($entityType, \App\Modules\Tasks\Services\TaskService::ENTITY_TYPES, true)) {
            $entityType = '';
            $entityId   = 0;
        }
        if ($entityId <= 0) {
            $entityType = '';
            $entityId   = 0;
        }

        $entityBackUrl = $entityType !== '' ? $this->entityUrl($entityType, $entityId) : null;

        $pageTitle     = 'New Task';
        $activeSection = 'Tasks';
        $errors        = [];
        $old           = [];

        ob_start();
        require $viewsPath . '/tasks/create.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // POST /tasks
    // -------------------------------------------------------------------------

    public function store(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        // Derive assignment fields from the form submission.
        // The form posts assigned_type (select: user|cron), and conditionally
        // assigned_id (user PK), execution_type, execution_payload.
        // 'agent' is not exposed in the web UI; any unexpected value is silently
        // treated as unassigned so it cannot be injected via POST manipulation.
        $rawAssignedType = trim($_POST['assigned_type'] ?? '');
        if (!in_array($rawAssignedType, ['user', 'cron'], true)) {
            $rawAssignedType = '';
        }

        if ($rawAssignedType === 'user') {
            $assignedType     = 'user';
            $rawAssignedId    = (int) ($_POST['assigned_id'] ?? 0);
            $assignedId       = $rawAssignedId > 0 ? $rawAssignedId : null;
            $executionType    = null;
            $executionPayload = null;
        } elseif ($rawAssignedType === 'cron') {
            $assignedType     = 'cron';
            $assignedId       = null;
            $executionType    = trim($_POST['execution_type'] ?? '') ?: null;
            $executionPayload = trim($_POST['execution_payload'] ?? '') ?: null;
        } else {
            $assignedType     = null;
            $assignedId       = null;
            $executionType    = null;
            $executionPayload = null;
        }

        $input = [
            'title'              => $_POST['title']       ?? '',
            'description'        => $_POST['description'] ?? '',
            'status'             => $_POST['status']      ?? 'open',
            'assigned_type'      => $assignedType,
            'assigned_id'        => $assignedId,
            'execution_type'     => $executionType,
            'execution_payload'  => $executionPayload,
            'due_at'             => $_POST['due_at']      ?? null,
            'entity_type'        => $_POST['entity_type'] ?? null,
            'entity_id'          => $_POST['entity_id']   ?? null,
            'created_by_user_id' => (int) ($principal['user']['id'] ?? 0) ?: null,
        ];

        $service = new TaskService(new TaskRepository($this->container->get('db')));

        try {
            $newId = $service->create($input);
        } catch (\InvalidArgumentException $e) {
            $entityType    = (string) ($input['entity_type'] ?? '');
            $entityId      = (int) ($input['entity_id'] ?? 0);
            $entityBackUrl = $entityType !== '' && $entityId > 0
                ? $this->entityUrl($entityType, $entityId)
                : null;

            $users  = (new UserRepository($this->container->get('db')))->findAllActive();
            $errors = ['general' => $e->getMessage()];
            $old    = $input;

            $pageTitle     = 'New Task';
            $activeSection = 'Tasks';

            ob_start();
            require $viewsPath . '/tasks/create.php';
            $content = ob_get_clean();

            http_response_code(422);
            require $viewsPath . '/layouts/app.php';
            return;
        }

        $actorId = (int) ($principal['user']['id'] ?? 0);
        $this->auditLog($actorId, 'task.create', 'task', $newId, [
            'title'       => $input['title'],
            'status'      => $input['status'],
            'entity_type' => $input['entity_type'],
            'entity_id'   => $input['entity_id'],
        ]);

        // Redirect back to the originating entity page when the task is linked;
        // otherwise fall back to the generic task list.
        $this->flash('success', 'Task created.');
        $redirect = ($input['entity_type'] && $input['entity_id'])
            ? $this->entityUrl((string) $input['entity_type'], (int) $input['entity_id'])
            : '/tasks';
        header('Location: ' . $redirect);
        exit;
    }

    // -------------------------------------------------------------------------
    // GET /tasks/{id}/edit
    // -------------------------------------------------------------------------

    public function editForm(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $service = new TaskService(new TaskRepository($this->container->get('db')));
        $task    = $service->getById((int) ($params['id'] ?? 0));

        if ($task === null) {
            http_response_code(404);
            echo '<h1>404 — Task not found</h1>';
            return;
        }

        $users = (new UserRepository($this->container->get('db')))->findAllActive();

        $pageTitle     = 'Edit Task';
        $activeSection = 'Tasks';
        $errors        = [];
        $old           = [];
        $flash         = $this->popFlash();

        ob_start();
        require $viewsPath . '/tasks/edit.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // POST /tasks/{id}
    // -------------------------------------------------------------------------

    public function update(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $db      = $this->container->get('db');
        $service = new TaskService(new TaskRepository($db));
        $taskId  = (int) ($params['id'] ?? 0);
        $task    = $service->getById($taskId);

        if ($task === null) {
            http_response_code(404);
            echo '<h1>404 — Task not found</h1>';
            return;
        }

        $rawAssignedType = trim($_POST['assigned_type'] ?? '');
        if (!in_array($rawAssignedType, ['user', 'cron'], true)) {
            $rawAssignedType = '';
        }

        if ($rawAssignedType === 'user') {
            $assignedType     = 'user';
            $rawAssignedId    = (int) ($_POST['assigned_id'] ?? 0);
            $assignedId       = $rawAssignedId > 0 ? $rawAssignedId : null;
            $executionType    = null;
            $executionPayload = null;
        } elseif ($rawAssignedType === 'cron') {
            $assignedType     = 'cron';
            $assignedId       = null;
            $executionType    = trim($_POST['execution_type'] ?? '') ?: null;
            $executionPayload = trim($_POST['execution_payload'] ?? '') ?: null;
        } else {
            $assignedType     = null;
            $assignedId       = null;
            $executionType    = null;
            $executionPayload = null;
        }

        $input = [
            'title'             => $_POST['title']       ?? '',
            'description'       => $_POST['description'] ?? '',
            'status'            => $_POST['status']      ?? 'open',
            'assigned_type'     => $assignedType,
            'assigned_id'       => $assignedId,
            'execution_type'    => $executionType,
            'execution_payload' => $executionPayload,
            'due_at'            => $_POST['due_at']      ?? null,
        ];

        try {
            $service->update($taskId, $input);
        } catch (\InvalidArgumentException $e) {
            $users  = (new UserRepository($db))->findAllActive();
            $errors = ['general' => $e->getMessage()];
            $old    = $input;

            $pageTitle     = 'Edit Task';
            $activeSection = 'Tasks';
            $flash         = null;

            ob_start();
            require $viewsPath . '/tasks/edit.php';
            $content = ob_get_clean();

            http_response_code(422);
            require $viewsPath . '/layouts/app.php';
            return;
        }

        $actorId = (int) ($principal['user']['id'] ?? 0);
        $this->auditLog($actorId, 'task.update', 'task', $taskId, [
            'title'  => $input['title'],
            'status' => $input['status'],
        ]);

        // Reload the task to pick up its entity linkage for the redirect decision.
        $saved = $service->getById($taskId);
        $this->flash('success', 'Task updated.');
        $redirect = ($saved && $saved['entity_type'] && $saved['entity_id'])
            ? $this->entityUrl((string) $saved['entity_type'], (int) $saved['entity_id'])
            : '/tasks/' . $taskId . '/edit';
        header('Location: ' . $redirect);
        exit;
    }

    // -------------------------------------------------------------------------
    // POST /tasks/{id}/delete
    // -------------------------------------------------------------------------

    public function delete(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $service = new TaskService(new TaskRepository($this->container->get('db')));
        $taskId  = (int) ($params['id'] ?? 0);

        try {
            $task = $service->delete($taskId);
        } catch (\InvalidArgumentException $e) {
            // Task not found — redirect to task list with a notice.
            $this->flash('error', 'Task not found or already deleted.');
            header('Location: /tasks');
            exit;
        }

        $actorId = (int) ($principal['user']['id'] ?? 0);
        $this->auditLog($actorId, 'task.delete', 'task', $taskId, [
            'title'       => $task['title'],
            'entity_type' => $task['entity_type'],
            'entity_id'   => $task['entity_id'],
        ]);

        $this->flash('success', 'Task deleted.');
        $redirect = ($task['entity_type'] && $task['entity_id'])
            ? $this->entityUrl((string) $task['entity_type'], (int) $task['entity_id'])
            : '/tasks';
        header('Location: ' . $redirect);
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the canonical URL for an entity page.
     *
     * Used for post-create / post-update redirects when a task is entity-linked,
     * and for "back" links on the create form.
     *
     * Supported entity types mirror TaskService::ENTITY_TYPES.
     */
    private function entityUrl(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'device'  => '/devices/'   . $entityId . '#tasks',
            'alert'   => '/alerts/'    . $entityId,
            'finding' => '/discovery/' . $entityId,
            default   => '/tasks',
        };
    }

    private function ctx(): array
    {
        $principal   = $this->container->get('principal');
        $config      = $this->container->get('config');
        $viewsPath   = __DIR__ . '/../../../Views';
        $appName     = $config['name'] ?? 'Kernel-Web';
        $displayName = $principal['user']['display_name'] ?? $principal['user']['username'];
        $permissions = $principal['permissions'];

        return [$principal, $config, $viewsPath, $appName, $displayName, $permissions];
    }

    private function auditLog(
        ?int   $actorId,
        string $action,
        string $entityType,
        int    $entityId,
        array  $meta = []
    ): void {
        try {
            (new AuditLogRepository($this->container->get('db')))
                ->log($actorId, $action, $entityType, $entityId, $meta);
        } catch (\Throwable $e) {
            // Intentionally swallowed — audit logging must never abort the main operation.
        }
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['tasks_flash'] = ['type' => $type, 'message' => $message];
    }

    private function popFlash(): ?array
    {
        $flash = $_SESSION['tasks_flash'] ?? null;
        unset($_SESSION['tasks_flash']);
        return $flash;
    }
}
