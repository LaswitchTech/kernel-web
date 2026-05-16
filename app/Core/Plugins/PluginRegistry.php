<?php

namespace App\Core\Plugins;

/**
 * In-memory plugin registry.
 *
 * Organizes plugins into four buckets:
 *   - discovered : valid manifest, not yet loaded
 *   - invalid    : manifest failed validation
 *   - enabled    : valid manifest, enabled by user
 *   - disabled   : valid manifest, disabled by user
 *
 * Extension points are provided as empty methods that the loader or kernel
 * can hook into (registerPermissions, registerServices, etc.).
 */
class PluginRegistry
{
    private ?\App\Core\Container $container = null;

    /**
     * Set the DI container for service registration.
     * Called by the loader before enable() is invoked.
     */
    public function setContainer(\App\Core\Container $container): void
    {
        $this->container = $container;
    }

    private array $discovered = [];
    private array $invalid    = [];
    private array $enabled    = [];
    private array $disabled   = [];

    /** @var callable[][] hook name => list of callables */
    private array $pluginHooks = [];

    /**
     * Add a discovered plugin to the registry.
     */
    public function addDiscovered(PluginManifest $plugin): void
    {
        $this->discovered[$plugin->name()] = $plugin;
    }

    /**
     * Enable a discovered plugin (moves from discovered to enabled).
     *
     * Calls extension hooks (registerPermissions, registerServices)
     * for the newly-enabled plugin.
     *
     * @throws PluginException if the plugin is not in the discovered bucket
     */
    public function enable(string $name): bool
    {
        if (!isset($this->discovered[$name])) {
            throw new \App\Core\Plugins\PluginException(
                "Cannot enable '{$name}': plugin not in discovered bucket."
            );
        }

        $plugin = $this->discovered[$name];
        unset($this->discovered[$name]);
        $this->enabled[$name] = $plugin;

        $this->registerPermissions($plugin);
        $this->registerServices($plugin);
        $this->registerPluginHooks($plugin);

        return true;
    }

    /**
     * Disable an enabled plugin (moves from enabled to disabled).
     */
    public function disable(string $name): bool
    {
        if (!isset($this->enabled[$name])) {
            return false;
        }

        $plugin         = $this->enabled[$name];
        unset($this->enabled[$name]);
        $this->disabled[$name] = $plugin;

        return true;
    }

    /**
     * Mark a plugin as invalid (manifest failed validation).
     */
    public function addInvalid(PluginManifest $plugin, string $reason): void
    {
        $this->invalid[$plugin->name()] = [
            'manifest' => $plugin,
            'reason'   => $reason,
        ];
    }

    // ------ Queries ------

    public function getByName(string $name): ?PluginManifest
    {
        foreach ([$this->enabled, $this->disabled, $this->discovered] as $bucket) {
            if (isset($bucket[$name])) {
                return $bucket[$name];
            }
        }
        return null;
    }

    public function isEnabled(string $name): bool
    {
        return isset($this->enabled[$name]);
    }

    public function getEnabled(): array
    {
        return $this->enabled;
    }

    public function getDisabled(): array
    {
        return $this->disabled;
    }

    public function getDiscovered(): array
    {
        return $this->discovered;
    }

    public function getInvalid(): array
    {
        return $this->invalid;
    }

    public function hasEnabled(): bool
    {
        return !empty($this->enabled);
    }

    public function count(): int
    {
        return count($this->enabled) + count($this->disabled) + count($this->discovered) + count($this->invalid);
    }

    // ------ Extension Points ---

    /**
     * Hook for plugins to register permissions with the kernel.
     * Called during enable(). Override or hook in via the loader.
     */
    protected function registerPermissions(PluginManifest $plugin): void
    {
        // Extension point — no-op by default.
    }

    /**
     * Hook for plugins to register services with the container.
     * Called during enable(). Override or hook in via the loader.
     */
    protected function registerServices(PluginManifest $plugin): void
    {
        if ($this->container === null) {
            return;
        }

        foreach ($plugin->services() as $key => $serviceDef) {
            $class = $serviceDef['class'] ?? '';
            if ($class === '' || !class_exists($class)) {
                continue;
            }

            // Resolve constructor arguments if provided.
            $args = $serviceDef['args'] ?? [];
            $resolved = [];
            foreach ($args as $arg) {
                $resolved[] = is_string($arg) && $this->container->has($arg)
                    ? $this->container->get($arg)
                    : $arg;
            }

            $instance = new $class(...$resolved);
            $this->container->set($key, $instance);
        }
    }

    /**
     * Hook for plugins to register bootstrap/lifecycle hooks.
     * Called during enable(). Plugin hooks are collected and then
     * executed by the loader via executePluginHooks().
     */
    protected function registerPluginHooks(PluginManifest $plugin): void
    {
        foreach ($plugin->pluginHooks() as $hookName => $callbackDef) {
            $callback = $callbackDef['callback'] ?? null;
            $priority = $callbackDef['priority'] ?? 0;

            if ($callback === null) {
                continue;
            }

            // Trigger autoloading for class-based callbacks before is_callable check.
            // is_callable() does NOT trigger autoloading (uses class_exists with autoload=false),
            // so we must explicitly load the class if it hasn't been loaded yet.
            if (str_contains($callback, '::')) {
                $classPart = explode('::', $callback)[0];
                if (!class_exists($classPart, false)) {
                    class_exists($classPart, true);
                }
            }

            if (!is_callable($callback)) {
                continue;
            }

            $this->pluginHooks[$hookName][$priority][] = $callback;
        }
    }

    /**
     * Execute all registered plugin hooks in priority order.
     *
     * @param array<string, mixed> $context
     */
    public function executePluginHooks(string $hookName, array $context = []): void
    {
        if (!isset($this->pluginHooks[$hookName])) {
            return;
        }

        ksort($this->pluginHooks[$hookName]);

        foreach ($this->pluginHooks[$hookName] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $callback($this->container, $context);
                } catch (\Throwable $e) {
                    error_log("Plugin hook '{$hookName}' failed: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get all registered plugin hooks (for execution by the loader).
     *
     * @return callable[][]
     */
    public function getPluginHooks(): array
    {
        return $this->pluginHooks;
    }
}
