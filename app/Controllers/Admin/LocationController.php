<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\AuditLogRepository;
use App\Models\LocationRepository;

/**
 * Admin location management controller.
 *
 * Owns all location-related admin routes:
 *
 *   GET  /admin/locations              → index()      Location list
 *   GET  /admin/locations/create       → createForm() Create form
 *   POST /admin/locations              → store()      Handle create
 *   GET  /admin/locations/{id}/edit    → editForm()   Edit form
 *   POST /admin/locations/{id}         → update()     Handle edit
 *   POST /admin/locations/{id}/delete  → delete()     Handle delete (guarded)
 *
 * All routes are protected by ['WebAuth', 'WebPermission:admin'].
 *
 * Deletion guard is enforced in LocationRepository::delete():
 *   - Refuse if any non-deleted device references this location.
 *   - Refuse if any child location references this location.
 */
class LocationController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /admin/locations
    // -------------------------------------------------------------------------

    public function index(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $locations = (new LocationRepository($this->container->get('db')))->findAll();

        $pageTitle     = 'Locations';
        $activeSection = 'Admin Locations';
        $flash         = $this->popFlash();

        ob_start();
        require $viewsPath . '/admin/locations.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    // -------------------------------------------------------------------------
    // GET /admin/locations/create
    // -------------------------------------------------------------------------

    public function createForm(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $repo      = new LocationRepository($this->container->get('db'));
        $allForSelect = $repo->findAllForSelect();

        $pageTitle     = 'New Location';
        $activeSection = 'Admin Locations';
        $errors        = [];
        $old           = [];

        ob_start();
        require $viewsPath . '/admin/location-create.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    // -------------------------------------------------------------------------
    // POST /admin/locations
    // -------------------------------------------------------------------------

    public function store(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $name        = trim($_POST['name']        ?? '');
        $type        = trim($_POST['type']         ?? 'other');
        $parentId    = (int) ($_POST['parent_id']  ?? 0) ?: null;
        $description = trim($_POST['description']  ?? '') ?: null;

        $errors = $this->validateLocation($name, $type, $parentId);

        if (!empty($errors)) {
            $repo         = new LocationRepository($this->container->get('db'));
            $allForSelect = $repo->findAllForSelect();

            $pageTitle     = 'New Location';
            $activeSection = 'Admin Locations';
            $old           = compact('name', 'type', 'parentId', 'description');

            ob_start();
            require $viewsPath . '/admin/location-create.php';
            $content = ob_get_clean();

            http_response_code(422);
            require $viewsPath . '/layouts/panel.php';
            return;
        }

        $repo  = new LocationRepository($this->container->get('db'));
        $newId = $repo->create(compact('name', 'type', 'description') + ['parent_id' => $parentId]);

        $actorId = (int) ($this->container->get('principal')['user']['id'] ?? 0);
        $this->auditLog($actorId, 'location.create', 'location', $newId, [
            'name'        => $name,
            'type'        => $type,
            'parent_id'   => $parentId,
        ]);

        $this->flash('success', "Location \"{$name}\" created.");
        header('Location: /admin/locations');
        exit;
    }

    // -------------------------------------------------------------------------
    // GET /admin/locations/{id}/edit
    // -------------------------------------------------------------------------

    public function editForm(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $repo     = new LocationRepository($this->container->get('db'));
        $location = $repo->findById((int) ($params['id'] ?? 0));

        if ($location === null) {
            http_response_code(404);
            echo '<h1>404 — Location not found</h1>';
            return;
        }

        // Exclude self from parent select to prevent circular references.
        $allForSelect = array_values(array_filter(
            $repo->findAllForSelect(),
            fn($l) => (int) $l['id'] !== (int) $location['id']
        ));

        $pageTitle     = 'Edit Location';
        $activeSection = 'Admin Locations';
        $errors        = [];
        $old           = [];
        $flash         = $this->popFlash();

        ob_start();
        require $viewsPath . '/admin/location-edit.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    // -------------------------------------------------------------------------
    // POST /admin/locations/{id}
    // -------------------------------------------------------------------------

    public function update(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $repo     = new LocationRepository($this->container->get('db'));
        $location = $repo->findById((int) ($params['id'] ?? 0));

        if ($location === null) {
            http_response_code(404);
            echo '<h1>404 — Location not found</h1>';
            return;
        }

        $locationId  = (int) $location['id'];
        $name        = trim($_POST['name']        ?? '');
        $type        = trim($_POST['type']         ?? 'other');
        $parentId    = (int) ($_POST['parent_id']  ?? 0) ?: null;
        $description = trim($_POST['description']  ?? '') ?: null;

        // Guard: prevent assigning self as parent.
        if ($parentId === $locationId) {
            $parentId = null;
        }

        $errors = $this->validateLocation($name, $type, $parentId, $locationId);

        if (!empty($errors)) {
            $allForSelect = array_values(array_filter(
                $repo->findAllForSelect(),
                fn($l) => (int) $l['id'] !== $locationId
            ));

            $pageTitle     = 'Edit Location';
            $activeSection = 'Admin Locations';
            $old           = compact('name', 'type', 'parentId', 'description');
            $flash         = null;

            ob_start();
            require $viewsPath . '/admin/location-edit.php';
            $content = ob_get_clean();

            http_response_code(422);
            require $viewsPath . '/layouts/panel.php';
            return;
        }

        $repo->update($locationId, compact('name', 'type', 'description') + ['parent_id' => $parentId]);

        $actorId = (int) ($this->container->get('principal')['user']['id'] ?? 0);
        $this->auditLog($actorId, 'location.update', 'location', $locationId, [
            'name'      => $name,
            'type'      => $type,
            'parent_id' => $parentId,
        ]);

        $this->flash('success', "Location \"{$name}\" updated.");
        header('Location: /admin/locations/' . $locationId . '/edit');
        exit;
    }

    // -------------------------------------------------------------------------
    // POST /admin/locations/{id}/delete
    // -------------------------------------------------------------------------

    public function delete(array $params = []): void
    {
        $repo     = new LocationRepository($this->container->get('db'));
        $location = $repo->findById((int) ($params['id'] ?? 0));

        if ($location === null) {
            $this->flash('error', 'Location not found.');
            header('Location: /admin/locations');
            exit;
        }

        $locationId   = (int) $location['id'];
        $locationName = $location['name'];

        $result = $repo->delete($locationId);

        if ($result !== true) {
            // Guard fired — $result is the error message string.
            $this->flash('error', $result);
            header('Location: /admin/locations/' . $locationId . '/edit');
            exit;
        }

        $principal = $this->container->get('principal');
        $actorId   = (int) ($principal['user']['id'] ?? 0);
        $this->auditLog($actorId, 'location.delete', 'location', $locationId, [
            'name' => $locationName,
        ]);

        $this->flash('success', "Location \"{$locationName}\" deleted.");
        header('Location: /admin/locations');
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function ctx(): array
    {
        $principal   = $this->container->get('principal');
        $config      = $this->container->get('config');
        $viewsPath   = __DIR__ . '/../../Views';
        $appName     = $config['name'] ?? 'Kernel-Web';
        $displayName = $principal['user']['display_name'] ?? $principal['user']['username'];
        $permissions = $principal['permissions'];

        return [$principal, $config, $viewsPath, $appName, $displayName, $permissions];
    }

    /**
     * Valid location types (must stay in sync with LocationRepository schema and views).
     */
    private const LOCATION_TYPES = ['site', 'building', 'floor', 'room', 'rack', 'other'];

    /**
     * Validate location name, type, and parent_id.
     * Returns an array of field => message pairs; empty means valid.
     */
    private function validateLocation(
        string $name,
        string $type,
        ?int $parentId,
        ?int $locationId = null
    ): array {
        $errors = [];
        $repo   = new LocationRepository($this->container->get('db'));

        if ($name === '') {
            $errors['name'] = 'Location name is required.';
        } elseif (strlen($name) > 128) {
            $errors['name'] = 'Location name must be 128 characters or fewer.';
        } elseif ($repo->isNameTaken($name, $parentId, $locationId)) {
            $errors['name'] = 'A location with that name already exists under the same parent.';
        }

        if (!in_array($type, self::LOCATION_TYPES, true)) {
            $errors['type'] = 'Invalid location type.';
        }

        if ($parentId !== null) {
            $parent = $repo->findById($parentId);
            if ($parent === null) {
                $errors['parent_id'] = 'Selected parent location does not exist.';
            }
        }

        return $errors;
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
            // Intentionally swallowed.
        }
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    }

    private function popFlash(): ?array
    {
        $flash = $_SESSION['admin_flash'] ?? null;
        unset($_SESSION['admin_flash']);
        return $flash;
    }
}
