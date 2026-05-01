<?php

namespace App\Core\Plugins;

use App\Core\Container;

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

        // --- Valid plugin ---

        if ($manifest->enabled()) {
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
     * Returns the number of routes registered.
     */
    public function registerRoutes(): int
    {
        if (!$this->container->has('router')) {
            return 0;
        }

        $router = $this->container->get('router');

        if (!method_exists($router, 'registerPluginRoutes')) {
            return 0;
        }

        $count = 0;

        foreach ($this->registry->getEnabled() as $plugin) {
            $routesFile = $plugin->basePath() . '/routes.php';
            if (is_file($routesFile)) {
                include $routesFile;
            }

            foreach ($plugin->routes() as $routeDef) {
                $router->registerPluginRoutes($routeDef);
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
     * Returns the number of migrations run.
     */
    public function runMigrations(): int
    {
        if (!$this->container->has('db')) {
            return 0;
        }

        $count = 0;

        foreach ($this->registry->getEnabled() as $plugin) {
            $baseDir = $plugin->basePath();
            foreach ($plugin->migrations() as $migrationFile) {
                $fullPath = $baseDir . '/' . $migrationFile;
                if (is_file($fullPath)) {
                    // Migration execution is deferred to a future hook.
                    // For now, just count declared migrations.
                    $count++;
                }
            }
        }

        return $count;
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
}
