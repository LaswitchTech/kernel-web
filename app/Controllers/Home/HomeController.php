<?php

namespace App\Controllers\Home;

use App\Core\Controller;

class HomeController extends Controller
{
    /**
     * Public landing page shown before authentication.
     */
    public function index(array $params = []): void
    {
        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        /** @var \App\Auth\AuthService $auth */
        $auth           = $this->container->get('auth');
        $user           = $auth->user();
        $appName        = $config['name'] ?? 'Kernel-Web';
        $isInstalled    = (bool) ($config['installed'] ?? false);
        $installUrl     = '/setup'; // unified install route
        $isAuthenticated = $auth->check();

        ob_start();
        require $viewsPath . '/home/index.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    /**
     * Redirect to the authenticated dashboard.
     */
    public function dashboard(array $params = []): void
    {
        $this->container->get('router')->redirect('/admin');
    }

    /**
     * Redirect to the installer.
     */
    public function install(array $params = []): void
    {
        $this->container->get('router')->redirect('/install');
    }
}
