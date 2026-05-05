<?php
namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Admin Theme preview controller — serves Bootstrap component preview page.
 */
class ThemeController extends Controller
{
    public function preview(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        $pageTitle     = 'Theme Preview';
        $activeSection = 'Admin Themes';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;
        $flash         = $this->popFlash();

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Theme Preview', 'url' => null],
        ];

        ob_start();
        require $viewsPath . '/admin/themes/preview.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }
}
