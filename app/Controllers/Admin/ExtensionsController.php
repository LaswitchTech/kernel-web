<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Extensions\ExtensionDiscoveryService;

/**
 * Admin Extensions — read-only listing of discovered extensions.
 *
 * Routes:
 *   GET /admin/extensions     → index()  Extension listing
 *
 * Protected by WebPermission:extensions.manage.
 */
class ExtensionsController extends Controller
{
    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        $basePath = realpath(__DIR__ . '/../../../lib');

        $pageTitle     = 'Extensions';
        $activeSection = 'Admin Extensions';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;

        $extensions = [];
        if ($basePath !== false) {
            $extensions = (new ExtensionDiscoveryService($basePath))->discover();
        }

        ob_start();
        require $viewsPath . '/admin/extensions/index.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }
}
