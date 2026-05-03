<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Imports — resolved at compile time; autoloader handles runtime loading
// ---------------------------------------------------------------------------
use App\Auth\AuthService;
use App\Auth\LocalAuthProvider;
use App\Auth\TokenService;
use App\Core\Config;
use App\Core\Container;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Gate;
use App\Core\Installer\InstallLock;
use App\Core\Logger;
use App\Core\MenuRegistry;
use App\Core\Router;
use App\Core\SQLiteDriver;
use App\Models\TokenRepository;
use App\Models\UserRepository;
use App\Modules\Notifications\Models\NotificationRepository as ModuleNotificationRepository;
use App\Modules\Notifications\Models\NotificationQueueRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Setup\Controllers\SetupController;
use App\Core\Plugins\PluginLoader;

// ---------------------------------------------------------------------------
// Autoloader
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base   = __DIR__ . '/../app/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Plugin autoloader — scans /lib/plugins/{name}/src/ for classes.
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\Plugins\\', strlen('App\\Plugins\\')) !== 0) {
        return;
    }

    $pluginsDir = __DIR__ . '/../lib/plugins';
    if (!is_dir($pluginsDir)) {
        return;
    }

    $iterator = new \DirectoryIterator($pluginsDir);
    foreach ($iterator as $entry) {
        if (!$entry->isDir() || $entry->isDot()) {
            continue;
        }

        $srcDir = $entry->getPathname() . '/src';
        if (!is_dir($srcDir)) {
            continue;
        }

        $relative = substr($class, strlen('App\\Plugins\\'));
        $file     = $srcDir . '/' . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// ---------------------------------------------------------------------------
// Environment — must run before Config or InstallLock
// ---------------------------------------------------------------------------
Env::load(__DIR__ . '/../.env');

// ---------------------------------------------------------------------------
// Installation-state boot guard
//
// Runs before the Container or Router are initialized because the database
// may not yet exist on a fresh deployment.
//
// Cases handled:
//   [A] Not installed + non-setup request  → redirect to /setup
//   [B] Not installed + /setup request     → allow (setup placeholder or wizard)
//   [C] Installed     + /setup request     → 403 Forbidden
//   [D] Installed     + non-setup request  → normal application boot (falls through)
// ---------------------------------------------------------------------------

$installLock = new InstallLock(__DIR__ . '/../storage');

$requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$isSetupPath = str_starts_with($requestPath, '/setup');
$method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!$installLock->isInstalled()) {
    if (!$isSetupPath) {
        // [A] Not installed — send user to the setup wizard
        header('Location: /setup', true, 302);
        exit;
    }

    // [B] Not installed and already on /setup — dispatch to the setup wizard
    $rootPath = realpath(__DIR__ . '/..');
    (new SetupController($rootPath))->dispatch($method, $requestPath);
    exit;
}

if ($isSetupPath) {
    // [C] Installed — block all /setup routes permanently
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>403 Forbidden</title>
      <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    </head>
    <body class="bg-light">
      <div class="container py-5" style="max-width:520px">
        <div class="card shadow-sm">
          <div class="card-body p-4 text-center">
            <h1 class="h4 mb-2">403 Forbidden</h1>
            <p class="text-muted">
              Setup is not available because this application is already installed.
            </p>
            <a href="/" class="btn btn-primary btn-sm mt-2">Go to application</a>
          </div>
        </div>
      </div>
    </body>
    </html>
    HTML;
    exit;
}

// [D] Installed and normal request — continue with full application boot.

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

// Error handling — register before any code that can throw
$logger  = new Logger(__DIR__ . '/../storage/logs');
$appCfg  = Config::load('app');
$handler = new ErrorHandler($logger, (bool) ($appCfg['debug'] ?? false));
$handler->register();

// ---------------------------------------------------------------------------
// Container
// ---------------------------------------------------------------------------
$container = new Container();

$container->set('config', $appCfg);
$container->set('logger', $logger);

// Database
$dbConfig = Config::load('database');

if ($dbConfig['driver'] === 'sqlite') {
    $container->set('db', new SQLiteDriver($dbConfig['sqlite']['path']));
} else {
    throw new \RuntimeException("Unsupported database driver: {$dbConfig['driver']}");
}

// Auth
$authConfig = Config::load('auth');
$userRepo   = new UserRepository($container->get('db'));
$tokenRepo  = new TokenRepository($container->get('db'));
$gate       = new Gate($container->get('db'));

$container->set('auth',   new AuthService(new LocalAuthProvider($userRepo), $authConfig));
$container->set('gate',   $gate);
$container->set('tokens', new TokenService($tokenRepo, $userRepo, $gate));

// Notifications module — reusable in-app inbox service
// NotificationService is registered here so it is available to web controllers
// (inbox reads, dispatch for future web-triggered events, etc.).
// Channel delivery is handled asynchronously by the worker (scripts/notify.php);
// no channel instances are needed in the web process.
$moduleNotifRepo = new ModuleNotificationRepository($container->get('db'));
$queueRepo       = new NotificationQueueRepository($container->get('db'));
$notifService    = new NotificationService($moduleNotifRepo, $queueRepo);
$container->set('notifications', $notifService);

// ---------------------------------------------------------------------------
// Routing — create router before plugin loading so plugin routes
// can be injected into it during the registration phase.
// ---------------------------------------------------------------------------
$router = new Router($container);
$container->set('router', $router);

// ---------------------------------------------------------------------------
// Plugin Loader
// ---------------------------------------------------------------------------
// Discover and load plugins from /lib/plugins/ before routing.
// Gracefully no-ops if /lib/plugins/ does not exist yet.

$pluginsDir = realpath(__DIR__ . '/../lib/plugins');

if ($pluginsDir !== false && is_dir($pluginsDir)) {
    $loader = new PluginLoader($pluginsDir, $container);
    $loader->load();

    // Register plugin-declared permissions (if Gate supports it).
    $permissions = $loader->getDeclaredPermissions();
    if (!empty($permissions) && method_exists($gate, 'registerPermissions')) {
        $gate->registerPermissions($permissions);
    }

    // Register plugin services from manifest (before routes).
    $loader->getRegistry()->setContainer($container);
    $loader->registerServices();

    // Register plugin hooks and menus.
    $loader->registerHooks();
    $loader->registerMenus();

    // Register core menu items (after plugins so plugin items sort correctly).
    $coreMenus = [
        ['name' => 'dashboard', 'label' => 'Dashboard', 'url' => '/', 'icon' => 'bi bi-speedometer2', 'permission' => null, 'order' => 0, 'sections' => []],

        // Tools section — visible only when user has any tool permission.
        ['name' => '__section__tools', 'label' => 'Tools', 'url' => null, 'icon' => null, 'permission' => null, 'order' => 10,
         'sections' => [['label' => 'Tools', 'order' => 10]]],
        // Chat and File Manager are kernel modules (not plugins yet).
        ['name' => 'chat', 'label' => 'Chat', 'url' => '/chat', 'icon' => 'bi bi-chat-dots', 'permission' => 'chat.use', 'order' => 25, 'sections' => []],
        ['name' => 'files', 'label' => 'File Manager', 'url' => '/files', 'icon' => 'bi bi-folder2', 'permission' => 'files.manage', 'order' => 30, 'sections' => []],

        // Administration section.
        ['name' => '__section__admin', 'label' => 'Administration', 'url' => null, 'icon' => null, 'permission' => null, 'order' => 50,
         'sections' => [['label' => 'Administration', 'order' => 50]]],

        ['name' => 'admin-overview', 'label' => 'Overview', 'url' => '/admin', 'icon' => 'bi bi-gear', 'permission' => 'admin', 'order' => 51, 'sections' => []],
        ['name' => 'admin-users', 'label' => 'Users', 'url' => '/admin/users', 'icon' => 'bi bi-people', 'permission' => 'admin', 'order' => 52, 'sections' => []],
        ['name' => 'admin-groups', 'label' => 'Groups', 'url' => '/admin/groups', 'icon' => 'bi bi-collection', 'permission' => 'admin', 'order' => 53, 'sections' => []],
        ['name' => 'admin-permissions', 'label' => 'Permissions', 'url' => '/admin/permissions', 'icon' => 'bi bi-shield-check', 'permission' => 'admin', 'order' => 54, 'sections' => []],
        ['name' => 'admin-audit', 'label' => 'Audit Log', 'url' => '/admin/audit', 'icon' => 'bi bi-journal-text', 'permission' => 'admin', 'order' => 55, 'sections' => []],
        ['name' => 'admin-settings', 'label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'bi bi-sliders', 'permission' => 'admin', 'order' => 56, 'sections' => []],
        ['name' => 'admin-extensions', 'label' => 'Extensions', 'url' => '/admin/extensions', 'icon' => 'bi bi-boxes', 'permission' => 'extensions.manage', 'order' => 60, 'sections' => []],
    ];

    foreach ($coreMenus as $menuDef) {
        MenuRegistry::add('sidebar', new \App\Core\MenuItem(
            name:        $menuDef['name'],
            label:       $menuDef['label'],
            url:         $menuDef['url'],
            icon:        $menuDef['icon'],
            styleClass:  null,
            permission:  $menuDef['permission'],
            order:       $menuDef['order'],
            parentId:    null,
            source:      'core',
            sections:    $menuDef['sections'],
        ));
    }

    // Admin sidebar — only administration items (no Dashboard, Tools, Chat, Files).
    $adminMenus = [
        ['name' => 'admin-overview', 'label' => 'Overview', 'url' => '/admin', 'icon' => 'bi bi-gear', 'permission' => 'admin', 'order' => 10, 'sections' => []],
        ['name' => 'admin-users', 'label' => 'Users', 'url' => '/admin/users', 'icon' => 'bi bi-people', 'permission' => 'admin', 'order' => 20, 'sections' => []],
        ['name' => 'admin-groups', 'label' => 'Groups', 'url' => '/admin/groups', 'icon' => 'bi bi-collection', 'permission' => 'admin', 'order' => 30, 'sections' => []],
        ['name' => 'admin-permissions', 'label' => 'Permissions', 'url' => '/admin/permissions', 'icon' => 'bi bi-shield-check', 'permission' => 'admin', 'order' => 40, 'sections' => []],
        ['name' => 'admin-audit', 'label' => 'Audit Log', 'url' => '/admin/audit', 'icon' => 'bi bi-journal-text', 'permission' => 'admin', 'order' => 50, 'sections' => []],
        ['name' => 'admin-settings', 'label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'bi bi-sliders', 'permission' => 'admin', 'order' => 60, 'sections' => []],
        ['name' => 'admin-extensions', 'label' => 'Extensions', 'url' => '/admin/extensions', 'icon' => 'bi bi-boxes', 'permission' => 'extensions.manage', 'order' => 70, 'sections' => []],
    ];

    foreach ($adminMenus as $menuDef) {
        MenuRegistry::add('admin-sidebar', new \App\Core\MenuItem(
            name:        $menuDef['name'],
            label:       $menuDef['label'],
            url:         $menuDef['url'],
            icon:        $menuDef['icon'],
            styleClass:  null,
            permission:  $menuDef['permission'],
            order:       $menuDef['order'],
            parentId:    null,
            source:      'core',
            sections:    $menuDef['sections'],
        ));
    }

    // Store registry in container for later access (e.g. admin UI).
    $container->set('plugins', $loader->getRegistry());

    // Register plugin routes.
    $loader->registerRoutes();
}

// ---------------------------------------------------------------------------
// Application Routes
// ---------------------------------------------------------------------------
require __DIR__ . '/../routes/web.php';

$router->dispatch($method, $requestPath);
