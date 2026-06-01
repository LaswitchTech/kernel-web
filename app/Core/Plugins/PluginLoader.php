<?php

namespace App\Core\Plugins;

use App\Core\Container;
use App\Models\CatalogExtensionRepository;
use App\Services\Extensions\ExtensionDependencyResolver;

/**
 * Holds validated plugin data collected during the discovery phase.
 *
 * Used as an intermediate step between validation and dependency-aware
 * sorting. The candidate is not yet registered until after sorting.
 */
final class PluginCandidate
{
    public function __construct(
        public readonly PluginManifest $manifest,
        public readonly bool $effectiveEnabled,
        public readonly ?bool $catalogState,
    ) {}

    public function name(): string
    {
        return $this->manifest->name();
    }
}

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
     *   3) Check dependencies (catalog + catalog availability)
     *   4) Collect valid plugin candidates
     *   5) Sort candidates by dependency graph
     *   6) Register sorted candidates into registry
     *
     * @return PluginRegistry
     */
    public function load(): PluginRegistry
    {
        if (!is_dir($this->pluginsDir)) {
            return $this->registry;
        }

        $directories = $this->discoverPluginDirs();

        // Phase 1: collect all candidates (validation only, no registry changes yet)
        $candidates = $this->collectPluginCandidates($directories);

        if ($candidates === []) {
            return $this->registry;
        }

        // Phase 2: sort by dependency graph
        $sortedSlugs = $this->sortPluginsByDependencies($candidates);

        // Phase 3: register in sorted order
        $this->registerPluginsInSortedOrder($candidates, $sortedSlugs);

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
     * Execute a named plugin hook after loading.
     *
     * Plugin hooks are bootstrap/lifecycle callbacks that plugins
     * can register during enable() to run at specific points in
     * the boot sequence.
     *
     * @param array<string, mixed> $context
     */
    public function executePluginHooks(string $hookName, array $context = []): void
    {
        $this->registry->executePluginHooks($hookName, $context);
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
     * Load a single plugin and return a candidate (or null if invalid).
     *
     * Invalid plugins are already added to the registry's invalid bucket.
     * Returns null when the plugin is invalid.
     */
    private function loadOne(string $dir): ?PluginCandidate
    {
        $pluginJson = $dir . '/plugin.json';

        $raw = @file_get_contents($pluginJson);
        if ($raw === false) {
            $this->registry->addInvalid(
                new PluginManifest(['name' => basename($dir), 'version' => '0.0.0']),
                'Could not read plugin.json'
            );
            return null;
        }

        $data = json_decode($raw, true);
        if ($data === null || !is_array($data)) {
            $this->registry->addInvalid(
                new PluginManifest(['name' => basename($dir), 'version' => '0.0.0']),
                'plugin.json is not valid JSON'
            );
            return null;
        }

        try {
            $manifest = new PluginManifest($data);
            $manifest->setPath($dir);
        } catch (PluginException $e) {
            $this->registry->addInvalid(
                new PluginManifest(['name' => basename($dir), 'version' => '0.0.0']),
                $e->getMessage()
            );
            return null;
        }

        // --- Dependency check ---

        $catalogSlug = basename($dir);
        $skipReason = '';
        if (!$this->checkDependencies($manifest, $catalogSlug, $skipReason)) {
            $this->registry->addInvalid($manifest, $skipReason);
            return null;
        }

        // --- Kernel compatibility check (boot-time warn) ---

        $kernelConstraint = $manifest->minKernelVersion();
        if ($kernelConstraint !== '' && $this->container !== null && $this->container->has('version_provider')) {
            $kernelVersion = $this->container->get('version_provider')->getKernelVersion();
            if (!ExtensionDependencyResolver::checkKernelCompatibility($kernelVersion, $kernelConstraint)) {
                $this->logLifecycleError(
                    $manifest->name(),
                    'kernel-compat',
                    "Requires kernel {$kernelConstraint}, current kernel is v{$kernelVersion}."
                );
            }
        }

        // --- Catalog enabled-state override ---

        $catalogState = $this->getCatalogEnabledState($catalogSlug);
        $effectiveEnabled = $catalogState ?? $manifest->enabled();

        // Return candidate — actual registry registration happens after sorting.
        $candidate = new PluginCandidate($manifest, $effectiveEnabled, $catalogState);

        // Pre-discover the plugin before sorting so enable() won't fail
        // with "not in discovered bucket". Needed when:
        //   - no catalog entry (catalogState is null) + manifest enabled
        //   - catalog override changes enabled state vs manifest
        //   - catalog says enabled but manifest also says enabled (they agree)
        if ($effectiveEnabled) {
            $this->registry->addDiscovered($manifest);
        }

        return $candidate;
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
     * Check dependencies for a plugin at runtime.
     *
     * Uses ExtensionDependencyResolver to validate that all declared
     * dependencies are installed and enabled in the catalog. Fail-closed:
     * invalid dependency format or unsatisfied dependency blocks the plugin.
     *
     * Returns true if all dependencies are satisfied, false otherwise.
     * Sets $skipReason to a human-readable message on failure.
     */
    private function checkDependencies(PluginManifest $manifest, string $slug, string &$skipReason): bool
    {
        $deps = ExtensionDependencyResolver::parseDependencies($manifest->dependencies());

        if ($deps === null) {
            // Should not happen — PluginManifest validates dependencies in the constructor.
            // This is a safeguard for edge cases (e.g. manifest bypassed).
            $skipReason = 'Invalid dependency format in plugin.json (post-load validation failed).';
            return false;
        }

        if ($deps === []) {
            return true;
        }

        // Fetch catalog entries for all installed extensions
        $catalogEntries = $this->getCatalogEntries();
        if ($catalogEntries === null) {
            // Catalog table doesn't exist yet — cannot validate dependencies at runtime.
            // Fall back to checking the registry for already-loaded plugins.
            return $this->checkDependenciesFallback($manifest, $deps);
        }

        $result = ExtensionDependencyResolver::checkEnable($deps, $catalogEntries);
        if ($result['allowed']) {
            return true;
        }

        // Report the first blocker as the skip reason
        $blockers = $result['blockers'];
        $skipReason = $blockers[0]['message'];
        $this->logLifecycleError($manifest->name(), 'dependency-check', $skipReason);
        return false;
    }

    /**
     * Fallback dependency check when catalog is unavailable.
     *
     * Checks if dependencies are already loaded in the registry (by name).
     * This handles the case where a dependency plugin was loaded earlier
     * in the discovery order. Does NOT check version constraints.
     */
    private function checkDependenciesFallback(PluginManifest $manifest, array $deps): bool
    {
        foreach ($deps as $depKey => $_constraint) {
            $parsed = ExtensionDependencyResolver::parseKey($depKey);
            if ($parsed === null) {
                return false;
            }

            // Check if the dependency is already enabled in the registry
            if ($this->registry->isEnabled($parsed['slug'])) {
                continue;
            }

            // Try name-based lookup for backwards compatibility
            $depPlugin = $this->registry->getByName($parsed['slug']);
            if ($depPlugin !== null) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Get all catalog entries as a flat array.
     *
     * Returns null if the catalog table does not exist or cannot be queried.
     */
    private function getCatalogEntries(): ?array
    {
        if ($this->container === null || !$this->container->has('db')) {
            return null;
        }

        try {
            $repo = new CatalogExtensionRepository($this->container->get('db'));
            $entries = $repo->findAll();

            // findAll may return an array of objects or arrays — normalize to arrays
            $result = [];
            foreach ($entries as $entry) {
                if (is_object($entry) && method_exists($entry, 'toArray')) {
                    $result[] = $entry->toArray();
                } elseif (is_array($entry)) {
                    $result[] = $entry;
                }
            }
            return $result;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Check the catalog for an installed extension's enabled state.
     *
     * Returns the catalog is_enabled value if the extension is installed,
     * or null if no catalog entry exists (falling back to manifest).
     */
    private function getCatalogEnabledState(string $slug): ?bool
    {
        if ($this->container === null || !$this->container->has('db')) {
            return null;
        }

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

    // ------ Boot Order Sorting ------

    /**
     * Collect all valid plugin candidates from discovered directories.
     *
     * Each candidate is validated independently. Invalid plugins are added
     * to the registry's invalid bucket. Valid plugins are collected for
     * dependency-aware sorting.
     *
     * @param string[] $directories Absolute directory paths
     * @return array<string, PluginCandidate> Keyed by plugin name
     */
    private function collectPluginCandidates(array $directories): array
    {
        $candidates = [];

        foreach ($directories as $dir) {
            $candidate = $this->loadOne($dir);
            if ($candidate !== null) {
                $candidates[$candidate->name()] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * Sort valid plugin candidates by dependency graph using Kahn's algorithm.
     *
     * Returns a list of plugin names in dependency order. Plugins with no
     * dependencies among valid candidates are tie-broken alphabetically by name.
     *
     * Cyclic plugins are marked invalid with a descriptive reason.
     *
     * @param array<string, PluginCandidate> $candidates
     * @return string[] Plugin names in sorted order
     */
    private function sortPluginsByDependencies(array $candidates): array
    {
        // Build the graph: only consider dependencies that are also valid candidates.
        $names = array_keys($candidates);
        $inDegree = array_fill_keys($names, 0);
        $adjacency = []; // dep => [dependents that depend on dep]

        foreach ($names as $name) {
            $adjacency[$name] = [];
        }

        foreach ($candidates as $name => $candidate) {
            $deps = ExtensionDependencyResolver::parseDependencies($candidate->manifest->dependencies());
            if ($deps === null || $deps === []) {
                continue;
            }

            foreach ($deps as $depKey => $_constraint) {
                $parsed = ExtensionDependencyResolver::parseKey($depKey);
                if ($parsed === null) {
                    continue;
                }

                $depName = $parsed['slug'];
                // Only create edge if the dependency is in our valid candidate set.
                if (isset($inDegree[$depName])) {
                    $inDegree[$name]++;
                    $adjacency[$depName][] = $name;
                }
            }
        }

        // Kahn's algorithm
        $queue = [];
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }
        sort($queue);

        $sorted = [];
        while ($queue !== []) {
            sort($queue);
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($adjacency[$current] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // Mark cyclic nodes as invalid (nodes not in sorted result)
        $sortedSet = array_flip($sorted);
        $cyclicNames = [];
        foreach ($inDegree as $name => $degree) {
            if (isset($sortedSet[$name]) || $degree === 0) {
                continue;
            }
            $cyclicNames[] = $name;
        }

        if ($cyclicNames !== []) {
            $this->markCyclicAsInvalid($cyclicNames, $candidates);
        }

        // Remove cyclic plugins from candidates, mark dependents invalid, and re-sort.
        if ($cyclicNames !== []) {
            $cyclicSet = array_flip($cyclicNames);
            $newCandidates = [];
            foreach ($candidates as $name => $candidate) {
                if (isset($cyclicSet[$name])) {
                    $this->registry->addInvalid($candidate->manifest, "Dependency is unavailable (circular dependency detected).");
                    continue;
                }

                // Block plugins whose dependency is part of a cycle
                $deps = ExtensionDependencyResolver::parseDependencies($candidate->manifest->dependencies());
                if ($deps !== null && $deps !== []) {
                    foreach ($deps as $depKey => $_) {
                        $parsed = ExtensionDependencyResolver::parseKey($depKey);
                        if ($parsed !== null && isset($cyclicSet[$parsed['slug']])) {
                            $this->registry->addInvalid($candidate->manifest, "Dependency '{$depKey}' is part of a circular dependency.");
                            $deps = null; // mark as blocked
                            break;
                        }
                    }
                }

                if ($deps !== null) {
                    $newCandidates[$name] = $candidate;
                }
            }
            $candidates = $newCandidates;

            // Re-sort without cyclic plugins
            $names = array_keys($candidates);
            if ($names !== []) {
                $inDegree = array_fill_keys($names, 0);
                $adjacency = [];
                foreach ($names as $name) {
                    $adjacency[$name] = [];
                }

                foreach ($candidates as $name => $candidate) {
                    $deps = ExtensionDependencyResolver::parseDependencies($candidate->manifest->dependencies());
                    if ($deps === null || $deps === []) {
                        continue;
                    }

                    foreach ($deps as $depKey => $_constraint) {
                        $parsed = ExtensionDependencyResolver::parseKey($depKey);
                        if ($parsed === null) {
                            continue;
                        }

                        $depName = $parsed['slug'];
                        if (isset($inDegree[$depName])) {
                            $inDegree[$name]++;
                            $adjacency[$depName][] = $name;
                        }
                    }
                }

                $queue = [];
                foreach ($inDegree as $name => $degree) {
                    if ($degree === 0) {
                        $queue[] = $name;
                    }
                }
                sort($queue);

                $sorted = [];
                while ($queue !== []) {
                    sort($queue);
                    $current = array_shift($queue);
                    $sorted[] = $current;

                    foreach ($adjacency[$current] as $dependent) {
                        $inDegree[$dependent]--;
                        if ($inDegree[$dependent] === 0) {
                            $queue[] = $dependent;
                        }
                    }
                }
            }
        }

        return $sorted;
    }

    /**
     * Mark cyclic plugins as invalid in the registry.
     *
     * @param string[] $cyclicNames
     * @param array<string, PluginCandidate> $candidates
     */
    private function markCyclicAsInvalid(array $cyclicNames, array $candidates): void
    {
        if (count($cyclicNames) === 1) {
            // Single node self-cycle (e.g., A depends on A)
            $name = $cyclicNames[0];
            $this->registry->addInvalid($candidates[$name]->manifest, "Circular dependency detected: '{$name}' depends on itself.");
            return;
        }

        // Multi-node cycle
        $names = [];
        foreach ($cyclicNames as $name) {
            $names[] = "'" . $candidates[$name]->manifest->name() . "'";
        }
        $cycleList = implode(', ', $names);

        foreach ($cyclicNames as $name) {
            $this->registry->addInvalid($candidates[$name]->manifest, "Circular dependency detected: [{$cycleList}].");
        }
    }

    /**
     * Register plugins in dependency order.
     *
     * @param array<string, PluginCandidate> $candidates
     * @param string[] $sortedNames Plugin names in dependency order
     */
    private function registerPluginsInSortedOrder(array $candidates, array $sortedNames): void
    {
        foreach ($sortedNames as $name) {
            $candidate = $candidates[$name];
            if ($candidate->effectiveEnabled) {
                $this->registry->enable($name);
            } else {
                $this->registry->addDiscovered($candidate->manifest);
                $this->registry->disable($name);
            }
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
                    $itemData['name'] ?? '',
                    $itemData['label'] ?? '',
                    $itemData['url'] ?? null,
                    $itemData['icon'] ?? null,
                    $itemData['styleClass'] ?? null,
                    $itemData['permission'] ?? null,
                    (int) ($itemData['order'] ?? 0),
                    $itemData['parentId'] ?? null,
                    $plugin->name(),
                    $itemData['sections'] ?? [],
                );

                \App\Core\MenuRegistry::add($menuName, $item);
                $count++;
            }
        }

        return $count;
    }
}
