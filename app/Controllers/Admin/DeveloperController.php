<?php
namespace App\Controllers\Admin;

use App\Core\Controller;

/**
 * Developer Tools admin controller — reads-only tools page.
 *
 * Only accessible when debug mode is enabled (APP_DEBUG=true).
 * When disabled, returns 404 to avoid exposing the page existence.
 */
class DeveloperController extends Controller
{
    public function tools(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        // Check if debug mode is enabled.
        // config['app']['debug'] mirrors config/app.php 'debug' key.
        $debugConfig = $config['app']['debug'] ?? $config['debug'] ?? false;
        $debug       = (bool) filter_var($debugConfig, FILTER_VALIDATE_BOOLEAN);

        if (!$debug) {
            // Return 404 — don't expose that this route exists in production.
            http_response_code(404);
            $this->handle404();
            return;
        }

        $pageTitle     = 'Developer Tools';
        $activeSection = 'Developer Tools';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;
        $flash         = $this->popFlash();

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Developer Tools', 'url' => null],
        ];

        // List of planned developer tools.
        $plannedTools = [
            [
                'name'       => 'Create Plugin Scaffold',
                'icon'       => 'bi bi-plug',
                'status'     => 'Planned',
                'description' => 'Generate a new plugin directory structure with manifest, routes.php, and skeleton controllers.',
            ],
            [
                'name'       => 'Create Theme Scaffold',
                'icon'       => 'bi bi-palette',
                'status'     => 'Planned',
                'description' => 'Generate a new theme directory with theme.json, less/app.less, and Bootstrap token overrides.',
            ],
            [
                'name'       => 'Create Layout Scaffold',
                'icon'       => 'bi bi-layout-sidebar',
                'status'     => 'Planned',
                'description' => 'Generate a new layout file with standard panel regions and hook points.',
            ],
            [
                'name'       => 'Copy Example Code',
                'icon'       => 'bi bi-file-earmark-code',
                'status'     => 'Planned',
                'description' => 'Copy example extension code (lifecycle hooks, menu registrations, route patterns) to a new extension.',
            ],
            [
                'name'       => 'Configure Local Repository',
                'icon'       => 'bi bi-gear',
                'status'     => 'Planned',
                'description' => 'Configure a local repository for extension development and testing.',
            ],
            [
                'name'       => 'Validate Manifests',
                'icon'       => 'bi bi-clipboard-check',
                'status'     => 'Planned',
                'description' => 'Validate extension manifests (plugin.json, theme.json, layout.json) for common errors.',
            ],
            [
                'name'       => 'Run Development Diagnostics',
                'icon'       => 'bi bi-clipboard-data',
                'status'     => 'Planned',
                'description' => 'Run diagnostics: check plugin loading, theme discovery, layout resolution, and config state.',
            ],
        ];

        ob_start();
        require $viewsPath . '/admin/developer/tools.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    /**
     * Return a 404 response using existing error page behavior.
     */
    private function handle404(): void
    {
        \App\Core\ErrorPage::render(404, 'The developer tools page is not available in production mode.');
    }
}
