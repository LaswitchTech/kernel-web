<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\AuditLogRepository;
use App\Models\GroupRepository;
use App\Models\PermissionRepository;
use App\Models\UserRepository;

/**
 * Admin area controller — landing page, permissions list, and audit log.
 *
 * All routes in this controller are protected by WebAuth + WebPermission:admin.
 *
 * Routes:
 *   GET /admin             → index()        Admin landing page
 *   GET /admin/permissions → permissions()  Permissions list (read-only)
 *   GET /admin/audit       → audit()        Admin audit log
 *
 * User management is handled by UserController.
 * Group management is handled by GroupController.
 */
class AdminController extends Controller
{
    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $db      = $this->container->get('db');
        $users   = (new UserRepository($db))->findAll();
        $groups  = (new GroupRepository($db))->findAll();
        $permList = (new PermissionRepository($db))->findAll();

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        $pageTitle     = 'Administration';
        $activeSection = 'Admin'; // matches sidebar "Overview" link
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;

        $userCount       = count($users);
        $groupCount      = count($groups);
        $permissionCount = count($permList);

        // Version information for admin overview.
        $kernelRoot = realpath(__DIR__ . '/../../../..');
        if ($kernelRoot === false) {
            $kernelRoot = __DIR__ . '/../../../..';
        }
        $versionProvider = new \App\Core\VersionProvider($kernelRoot);
        $versions = $versionProvider->getVersions($config);

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
        ];

        ob_start();
        require $viewsPath . '/admin/index.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    public function permissions(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $db      = $this->container->get('db');
        $permList = (new PermissionRepository($db))->findAll();

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        $pageTitle     = 'Permissions';
        $activeSection = 'Admin Permissions';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Permissions', 'url' => null],
        ];

        ob_start();
        require $viewsPath . '/admin/permissions.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    public function audit(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $db        = $this->container->get('db');
        $auditRows = (new AuditLogRepository($db))->findRecent(500);

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        $pageTitle     = 'Audit Log';
        $activeSection = 'Admin Audit';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Audit Log', 'url' => null],
        ];

        ob_start();
        require $viewsPath . '/admin/audit.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }
}
