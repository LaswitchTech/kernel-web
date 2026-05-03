<?php

namespace App\Core\Plugins;

/**
 * Plugin manifest — reads and validates a plugin.json file.
 *
 * Required fields: name, version
 * Optional fields: description, enabled, requires, dependencies, permissions,
 *                  routes, migrations, services, hooks, menus
 *
 * The manifest is immutable once constructed — callers cannot modify it.
 */
class PluginManifest
{
    private string $name;
    private string $version;
    private string $path;
    private ?string $description;
    private bool   $enabled;
    private string $minKernelVersion;
    private array  $dependencies;
    private array  $permissions;
    private array  $routes;
    private array  $migrations;
    private array  $services;
    private array  $hooks;
    private array  $menus;
    private array  $lifecycle;

    /**
     * @param array $data  Decoded plugin.json contents
     * @throws PluginException if required fields are missing or invalid
     */
    public function __construct(array $data)
    {
        // --- Required fields ---

        if (!isset($data['name']) || !is_string($data['name']) || $data['name'] === '') {
            throw new PluginException('plugin.json is missing required field: name');
        }

        if (!isset($data['version']) || !is_string($data['version']) || $data['version'] === '') {
            throw new PluginException('plugin.json is missing required field: version');
        }

        // --- Optional fields with defaults ---

        $this->name             = $data['name'];
        $this->version          = $data['version'];
        $this->path             = '';
        $this->description      = isset($data['description']) ? (string) $data['description'] : null;
        $this->enabled          = (bool) ($data['enabled'] ?? true);
        $this->minKernelVersion = isset($data['requires']['kernel']) ? (string) $data['requires']['kernel'] : '';
        $this->dependencies     = isset($data['dependencies']) ? (array) $data['dependencies'] : [];
        $this->permissions      = isset($data['permissions']) ? (array) $data['permissions'] : [];
        $this->routes           = isset($data['routes']) ? (array) $data['routes'] : [];
        $this->migrations       = isset($data['migrations']) ? (array) $data['migrations'] : [];
        $this->services         = isset($data['services']) ? (array) $data['services'] : [];
        $this->hooks            = isset($data['hooks']) ? (array) $data['hooks'] : [];
        $this->menus            = isset($data['menus']) ? (array) $data['menus'] : [];
        $this->lifecycle        = isset($data['lifecycle']) ? (array) $data['lifecycle'] : [];
    }

    // ------ Properties ------

    public function name(): string { return $this->name; }
    public function version(): string { return $this->version; }
    public function path(): string { return $this->path; }
    public function description(): ?string { return $this->description; }
    public function enabled(): bool { return $this->enabled; }
    public function minKernelVersion(): string { return $this->minKernelVersion; }
    public function dependencies(): array { return $this->dependencies; }
    public function permissions(): array { return $this->permissions; }
    public function routes(): array { return $this->routes; }
    public function migrations(): array { return $this->migrations; }
    public function services(): array { return $this->services; }
    public function hooks(): array { return $this->hooks; }
    public function menus(): array { return $this->menus; }
    public function lifecycle(): array { return $this->lifecycle; }

    /**
     * Set the directory path where this plugin's plugin.json lives.
     * (Called by the loader after discovery.)
     */
    public function setPath(string $path): void
    {
        $this->path = rtrim($path, '/\\');
    }

    /**
     * Return the plugin's base directory.
     * Only valid after setPath() has been called.
     */
    public function basePath(): string
    {
        return $this->path;
    }

    /**
     * Return a human-readable status string.
     */
    public function status(): string
    {
        return $this->enabled ? 'enabled' : 'disabled';
    }

    /**
     * Export the manifest as an array (for serialization / debugging).
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'version'     => $this->version,
            'description' => $this->description,
            'enabled'     => $this->enabled,
            'status'      => $this->status(),
            'path'        => $this->path,
            'requires'    => $this->minKernelVersion !== '' ? ['kernel' => $this->minKernelVersion] : [],
            'dependencies'=> $this->dependencies,
            'permissions' => $this->permissions,
            'routes'      => $this->routes,
            'migrations'  => $this->migrations,
            'services'    => $this->services,
            'hooks'       => $this->hooks,
            'menus'       => $this->menus,
            'lifecycle'   => $this->lifecycle,
        ];
    }
}
