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
    private array $discovered = [];
    private array $invalid    = [];
    private array $enabled    = [];
    private array $disabled   = [];

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
     */
    public function enable(string $name): bool
    {
        if (!isset($this->discovered[$name])) {
            return false;
        }

        $plugin = $this->discovered[$name];
        unset($this->discovered[$name]);
        $this->enabled[$name] = $plugin;

        $this->registerPermissions($plugin);
        $this->registerServices($plugin);

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
        // Extension point — no-op by default.
    }
}
