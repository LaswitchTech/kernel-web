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
        $versionProvider = $this->container->get('version_provider');
        $versions = $versionProvider->getVersions($config);

        // Count installed extensions with kernel incompatibility.
        $kernelIncompatibleCount = 0;
        $kernelVersion = $versionProvider->getKernelVersion();
        if (isset($versions['updates']['configured']) && $versions['updates']['configured']) {
            // If remote updates are configured, count from the update checker.
            // For now, rely on the update checker to surface kernel blockers.
            // The kernel incompatibility count is computed in the update checker.
        } else {
            // No remote source — count from catalog if available.
            try {
                $catalogRepo = new \App\Models\CatalogExtensionRepository($db);
                $installedExtensions = $catalogRepo->findInstalled();
                foreach ($installedExtensions as $ext) {
                    $requirements = json_decode($ext['requirements'] ?? '[]', true);
                    if (is_array($requirements) && isset($requirements['kernel']) && $requirements['kernel'] !== '') {
                        if (!\App\Services\Extensions\ExtensionDependencyResolver::checkKernelCompatibility(
                            $kernelVersion,
                            $requirements['kernel']
                        )) {
                            $kernelIncompatibleCount++;
                        }
                    }
                }
            } catch (\Throwable) {
                // Catalog may not exist — count stays 0.
            }
        }

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
        ];

        ob_start();
        $kernelIncompatibleCount = $kernelIncompatibleCount ?? 0;
        $kernelIncompatibility = $kernelIncompatibleCount > 0 ? [
            'count' => $kernelIncompatibleCount,
            'message' => $kernelIncompatibleCount === 1 ? '1 extension may be incompatible with this kernel version' : "{$kernelIncompatibleCount} extensions may be incompatible with this kernel version",
        ] : null;
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
