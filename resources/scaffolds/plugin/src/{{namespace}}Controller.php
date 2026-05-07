<?php

namespace App\Plugins\{{namespace}};

use App\Core\Controller;

/**
 * HTTP endpoints for the {{name}} extension.
 */
class {{namespace}}Controller extends Controller
{
    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $config    = $this->container->get('config');

        $pageTitle     = '{{name}}';
        $activeSection = '{{name}}';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $principal['user']['display_name'] ?? $principal['user']['username'];
        $permissions   = $principal['permissions'];
        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => '{{name}}', 'url' => null],
        ];

        ob_start();
        require __DIR__ . '/../../views/index.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../../Views/layouts/panel.php';
    }
}
