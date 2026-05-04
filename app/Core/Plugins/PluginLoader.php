<?php

namespace App\Core\Plugins;

use App\Core\Container;
use App\Models\CatalogExtensionRepository;

/**
 * Discovers, validates, and loads plugins from /lib/plugins/.
 *
 * Discovery flow:
 *   1) Scan each subdirectory of /lib/plugins/ for plugin.json
 *   2) Validate each manifest
 *   3) Check dependencies
 *   4) Build registry (enabled / disabled / invalid)
 *
 * The loader is intentionally non-fatal. A bad plugin never crashes
 * the kernel. Errors are recorded in the registry invalid bucket.
 *
 * This is a minimal foundation. Routes, migrations, and services
 * declared in manifests are collected but not auto-registered yet.
 * Those are future hooks.
 */
class PluginLoader
{
    private string $pluginsDir;
    private Container $container;
    private PluginRegistry $registry;

    public function __construct(string $pluginsDir, Container $container)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
        $this->container  = $container;
        $this->registry   = new PluginRegistry();
    }

    /**
     * Discover and validate all plugins in /lib/plugins/.
     *
     * Discovery flow:
     *   1) Scan each subdirectory of /lib/plugins/ for plugin.json
     *   2) Validate each manifest
     *   3) Check dependencies
     *   4) Check catalog installed state (overrides manifest enabled)
     *   5) Build registry (enabled / disabled / invalid)
     *
     * @return PluginRegistry
     */
    public function load(): PluginRegistry
    {
        if (!is_dir($this->pluginsDir)) {
            return $this->registry;
        }

        $directories = $this->discoverPluginDirs();

        foreach ($directories as $dir) {
            $this->loadOne($dir);
        }

        return $this->registry;
    }

    /**
     * Get the registry (read-only).
     */
    public function getRegistry(): PluginRegistry
    {
        return $this->registry;
    }

    /**
     * Find all subdirectories of /lib/plugins/ that contain a plugin.json.
     *
     * @return string[]  Absolute directory paths
     */
    private function discoverPluginDirs(): array
    {
        $results = [];

        $iterator = new \DirectoryIterator($this->pluginsDir);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $pluginJson = $entry->getPathname() . '/plugin.json';
            if (!is_file($pluginJson)) {
                continue;
            }

            $results[] = $entry->getPathname();
        }

        return $results;
    }

    /**
     * Load a single plugin from its directory.
     */
    private function loadOne(string $dir): void
    {
        $pluginJson = $dir . '/plugin.json';

        $raw = @file_get_contents($pluginJson);
        if ($raw === false) {
            $this->registry->addInvalid(
                new PluginManifest(['name' => basename($dir), 'version' => '0.0.0']),
                'Could not read plugin.json'
            );
            return;
        }

        $data = json_decode($raw, true);
        if ($data === null || !is_array($data)) {
            $this->registry->addInvalid(
                new PluginManifest(['name' => basename($dir), 'version' => '0.0.0']),
                'plugin.json is not valid JSON'
            );
            return;
        }

        try {
            $manifest = new PluginManifest($data);
            $manifest->setPath($dir);
        } catch (PluginException $e) {
            $this->registry->addInvalid(
                new PluginManifest(['name' => basename($dir), 'version' => '0.0.0']),
                $e->getMessage()
            );
            return;
        }

        // --- Dependency check ---

        $deps = $manifest->dependencies();
        foreach ($deps as $depName => $depVersion) {
            if (!$this->registry->isEnabled($depName) && !$this->registry->getByName($depName)) {
                $this->registry->addInvalid($manifest, "Missing required dependency: {$depName}");
                return;
            }
        }

        // --- Kernel version check (deferred) ---

        $minKernel = $manifest->minKernelVersion();
        if ($minKernel !== '' && !version_compare(phpversion(), $minKernel, '>=')) {
            $this->registry->addInvalid(
                $manifest,
                "Requires kernel {$minKernel}, current: " . phpversion()
            );
            return;
        }

        // --- Catalog enabled-state override ---
        // If a catalog entry exists and is installed, the catalog's is_enabled
        // takes precedence over the manifest's enabled field.

        $catalogSlug = basename($dir);
        $catalogState = $this->getCatalogEnabledState($catalogSlug);
        $effectiveEnabled = $catalogState ?? $manifest->enabled();

        // --- Valid plugin ---

        if ($effectiveEnabled) {
            // Catalog says enabled but manifest may have disabled it.
            // Ensure the manifest is in the discovered bucket first so enable() works.
            if ($catalogState !== null && $catalogState !== $manifest->enabled()) {
                $this->registry->addDiscovered($manifest);
            }
            $this->registry->enable($manifest->name());
        } else {
            $this->registry->addDiscovered($manifest);
            $this->registry->disable($manifest->name());
        }
    }

    /**
     * Register routes declared by all enabled plugins.
     *
     * Includes each plugin's routes.php if it exists, then processes
     * the routes array from the manifest. Both mechanisms inject
     * routes into the global $router instance.
     *
     * Plugin routes are registered with priority 1, so they always
     * win over kernel routes (which use priority 0 by default).
     *
     * Returns the number of routes registered.
     */
    public function registerRoutes(): int
    {
        if (!$this->container->has('router')) {
            return 0;
        }

        $router = $this->container->get('router');
        $count = 0;

        foreach ($this->registry->getEnabled() as $plugin) {
            $routesFile = $plugin->basePath() . '/routes.php';
            if (is_file($routesFile)) {
                include $routesFile;
            }

            foreach ($plugin->routes() as $routeDef) {
                [$method, $path, $handler, $middleware] = array_pad($routeDef, 4, []);
                $router->registerRoute($method, $path, $handler, $middleware, 1);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Run migrations declared by all enabled plugins.
     *
     * Each plugin migrations array should contain relative paths
     * to migration files within the plugin directory.
     *
     * Uses the shared MigrationRunner instance from the container.
     *
     * Returns the number of migrations run.
     */
    public function runMigrations(): int
    {
        if (!$this->container->has('db')) {
            return 0;
        }

        $db     = $this->container->get('db');
        $runner = new \App\Core\MigrationRunner($db, '');
        $count  = 0;

        foreach ($this->registry->getEnabled() as $plugin) {
            $baseDir = $plugin->basePath();
            $runner  = new \App\Core\MigrationRunner($db, $baseDir . '/migrations');

            $pending = $runner->pending();
            foreach ($pending as $file) {
                $name  = basename($file, '.php');
                $class = $this->classFromName($name);

                require_once $file;

                if (!class_exists($class)) {
                    throw new \RuntimeException("Migration class '{$class}' not found in {$file}");
                }

                $migration = new $class($db);

                if (!($migration instanceof \App\Core\Migration)) {
                    throw new \RuntimeException("Migration '{$class}' must extend App\\Core\\Migration");
                }

                $migration->up();
                $runner->record($name);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Convert a migration name (e.g. '0001_create_tasks') to a class name.
     */
    private function classFromName(string $name): string
    {
        $stripped = preg_replace('/^\d+_/', '', $name);
        return str_replace('_', '', ucwords($stripped, '_'));
    }

    /**
     * Register services declared by all enabled plugins.
     *
     * Each plugin services array should follow the format:
     *   ['service_key' => 'ClassName', 'singleton' => true]
     *
     * Returns the number of services registered.
     */
    public function registerServices(): int
    {
        $count = 0;

        foreach ($this->registry->getEnabled() as $plugin) {
            foreach ($plugin->services() as $key => $serviceDef) {
                // Service registration deferred to future implementation.
                // The hook point is here -- the container is available.
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check the catalog for an installed extension's enabled state.
     *
     * Returns the catalog is_enabled value if the extension is installed,
     * or null if no catalog entry exists (falling back to manifest).
     */
    private function getCatalogEnabledState(string $slug): ?bool
    {
        try {
            $repo = new CatalogExtensionRepository($this->container->get('db'));
            $entry = $repo->findBySlug($slug);

            if ($entry === null) {
                return null;
            }

            if ((int) ($entry['is_installed'] ?? 0) !== 1) {
                return null;
            }

            return (bool) ($entry['is_enabled'] ?? 0);
        } catch (\Throwable) {
            // Catalog table may not exist yet; fall back to manifest.
            return null;
        }
    }

    /**
     * Run a lifecycle hook for a specific plugin.
     *
     * @param string $pluginName  Plugin name (from manifest)
     * @param string $event       Lifecycle event: install, enable, disable
     * @param array $context      Additional context for the hook
     * @return bool True if the hook exists and executed (or was not defined), false if it failed
     */
    public function runLifecycleHook(string $pluginName, string $event, array $context = []): bool
    {
        // Ensure registry is populated (newly installed plugins may not be loaded yet).
        $this->load();

        $manifest = $this->registry->getByName($pluginName);
        if ($manifest === null) {
            return false;
        }

        $hooks = $manifest->lifecycle();
        if (!isset($hooks[$event])) {
            return true;
        }

        $callback = $hooks[$event];

        // Validate namespace — only allow App\Plugins\ namespace.
        if (!str_starts_with($callback, 'App\\Plugins\\')) {
            $this->logLifecycleError($pluginName, $event, 'Hook namespace is not allowed (must be App\\Plugins\\...).');
            return false;
        }

        // Extract class and method from "Namespace\\Class@method" format.
        $atPos = strrpos($callback, '@');
        if ($atPos === false) {
            $this->logLifecycleError($pluginName, $event, 'Invalid callback format (missing @).');
            return false;
        }

        $class = substr($callback, 0, $atPos);
        $method = substr($callback, $atPos + 1);

        if (!class_exists($class)) {
            // Try to force-load the plugin's src directory.
            $this->discoverPluginSrcForClass($class);
            if (!class_exists($class)) {
                $this->logLifecycleError($pluginName, $event, "Class '{$class}' not found.");
                return false;
            }
        }

        if (!method_exists($class, $method)) {
            $this->logLifecycleError($pluginName, $event, "Method '{$method}' not found in '{$class}'.");
            return false;
        }

        if (!is_callable([$class, $method])) {
            $this->logLifecycleError($pluginName, $event, "Handler '{$class}@{$method}' is not callable.");
            return false;
        }

        try {
            // Inject kernel root into context for reliable path resolution.
            $context['kernelRoot'] = dirname(dirname(dirname($manifest->basePath())));

            // Pass container as last argument if available.
            $args = [$pluginName, $manifest->basePath(), $context];
            if ($this->container !== null) {
                $args[] = $this->container;
            }

            call_user_func_array([$class, $method], $args);
            return true;
        } catch (\Throwable $e) {
            $this->logLifecycleError($pluginName, $event, $e->getMessage());
            return false;
        }
    }

    /**
     * Run lifecycle hooks for all enabled plugins.
     *
     * @return array Plugin name => result map
     */
    public function runAllLifecycleHooks(string $event, array $context = []): array
    {
        $results = [];
        foreach ($this->registry->getEnabled() as $plugin) {
            $results[$plugin->name()] = $this->runLifecycleHook($plugin->name(), $event, $context);
        }
        return $results;
    }

    /**
     * Log a lifecycle hook error.
     */
    private function logLifecycleError(string $plugin, string $event, string $message): void
    {
        if ($this->container !== null && $this->container->has('logger')) {
            $this->container->get('logger')->error("Lifecycle hook '{$event}' for plugin '{$plugin}' failed: {$message}");
        }
    }

    /**
     * Ensure the plugin's src directory is available for autoloading.
     *
     * Called when a lifecycle hook class cannot be found. This helps when
     * a newly installed plugin's src directory hasn't been registered yet.
     */
    private function discoverPluginSrcForClass(string $class): void
    {
        // Strip App\Plugins\ prefix.
        if (!str_starts_with($class, 'App\\Plugins\\')) {
            return;
        }
        $relative = substr($class, strlen('App\\Plugins\\'));
        $parts = explode('\\', $relative);

        // The class might be App\Plugins\LifecycleTest\Lifecycle
        // First, try to find the plugin directory (case-insensitive).
        if (count($parts) < 2) {
            return;
        }
        $className = end($parts);

        foreach (new \DirectoryIterator($this->pluginsDir) as $entry) {
            if (!$entry->isDir() || $entry->isDot()) continue;
            $srcDir = $entry->getPathname() . '/src';
            if (!is_dir($srcDir)) continue;

            // Try at the src root (e.g., src/Lifecycle.php).
            $file = $srcDir . '/' . $className . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }

            // Try nested under plugin dir name (autoloader's default).
            $nested = $srcDir . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($nested)) {
                require $nested;
                return;
            }
        }
    }

    /**
     * Get all permissions declared by all enabled plugins.
     *
     * Each plugin permissions array is a list of permission strings.
     *
     * @return string[]
     */
    public function getDeclaredPermissions(): array
    {
        $permissions = [];

        foreach ($this->registry->getEnabled() as $plugin) {
            $permissions = array_merge(
                $permissions,
                $plugin->permissions()
            );
        }

        return array_unique($permissions);
    }

    /**
     * Register hooks declared by all enabled plugins.
     *
     * Each plugin's hooks[] array should follow the format:
     *   ["hook_name" => "callable", ...]
     * where callable receives a $context array and returns output.
     *
     * Returns the number of hooks registered.
     */
    public function registerHooks(): int
    {
        $count = 0;

        foreach ($this->registry->getEnabled() as $plugin) {
            foreach ($plugin->hooks() as $hookName => $hookContent) {
                $priority = $hookContent['priority'] ?? 0;
                \App\Core\HookRegistry::register($hookName, $hookContent['content'], (int) $priority);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Register menus declared by all enabled plugins.
     *
     * Each plugin's menus[] array should follow the format:
     *   [
     *     ["menu" => "sidebar", "item" => [...MenuItem data...]],
     *   ]
     * where item data contains: name, label, url, icon, styleClass,
     * permission, order, parentId, source.
     *
     * Returns the number of menu items registered.
     */
    public function registerMenus(): int
    {
        $count = 0;

        foreach ($this->registry->getEnabled() as $plugin) {
            foreach ($plugin->menus() as $menuDef) {
                $menuName = $menuDef['menu'] ?? '';
                $itemData = $menuDef['item'] ?? [];
                if ($menuName === '' || empty($itemData)) {
                    continue;
                }

                $item = new \App\Core\MenuItem(
                    name:        $itemData['name'] ?? '',
                    label:       $itemData['label'] ?? '',
                    url:         $itemData['url'] ?? null,
                    icon:        $itemData['icon'] ?? null,
                    styleClass:  $itemData['styleClass'] ?? null,
                    permission:  $itemData['permission'] ?? null,
                    order:       (int) ($itemData['order'] ?? 0),
                    parentId:    $itemData['parentId'] ?? null,
                    source:      $plugin->name(),
                    sections:    $itemData['sections'] ?? [],
                );

                \App\Core\MenuRegistry::add($menuName, $item);
                $count++;
            }
        }

        return $count;
    }
}
