<?php

namespace App\Services\Extensions;

/**
 * Discovers extensions from known directories.
 *
 * Reads three extension types:
 *   - plugin: lib/plugins/{Name}/plugin.json
 *   - theme:  lib/themes/{Name}/theme.json (or dir name as fallback)
 *   - layout: lib/layouts/{Name}/layout.json (or dir name as fallback)
 *
 * Manifest format (theme/layout):
 *   {
 *     "name": "My Theme",
 *     "version": "0.1.0",
 *     "description": "Short description"
 *   }
 *
 * Returns data suitable for admin listing (read-only discovery).
 */
class ExtensionDiscoveryService
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        // basePath should point to the lib/ directory.
        $this->basePath = rtrim($basePath, '/\\');
    }

    /**
     * Discover all extensions across all types.
     *
     * @return array<string, array<int, array<string, mixed>>>
     *   Keys: 'plugins', 'themes', 'layouts'
     *   Each value: array of extension data arrays
     */
    public function discover(): array
    {
        return [
            'plugins' => $this->discoverPlugins(),
            'themes'  => $this->discoverThemes(),
            'layouts' => $this->discoverLayouts(),
        ];
    }

    /**
     * Discover plugins from lib/plugins/.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discoverPlugins(): array
    {
        $items = [];
        $dir = $this->basePath . '/plugins';
        if (!is_dir($dir)) {
            return $items;
        }

        $iterator = new \DirectoryIterator($dir);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $pluginJson = $entry->getPathname() . '/plugin.json';
            if (!is_file($pluginJson)) {
                $items[] = [
                    'name'      => $entry->getFilename(),
                    'slug'      => $entry->getFilename(),
                    'type'      => 'plugin',
                    'version'   => null,
                    'description' => null,
                    'status'    => 'invalid',
                    'reason'    => 'Missing plugin.json manifest',
                    'path'      => $entry->getPathname(),
                ];
                continue;
            }

            $data = json_decode(file_get_contents($pluginJson), true);
            $valid = !empty($data)
                && isset($data['name']) && is_string($data['name'])
                && isset($data['version']) && is_string($data['version']);

            if (!$valid) {
                $items[] = [
                    'name'      => $entry->getFilename(),
                    'slug'      => $entry->getFilename(),
                    'type'      => 'plugin',
                    'version'   => null,
                    'description' => null,
                    'status'    => 'invalid',
                    'reason'    => 'Invalid plugin.json manifest',
                    'path'      => $entry->getPathname(),
                ];
                continue;
            }

            $status = $data['enabled'] ?? true ? 'enabled' : 'disabled';

            $items[] = [
                'name'        => $data['name'],
                'slug'        => $entry->getFilename(),
                'type'        => 'plugin',
                'version'     => $data['version'],
                'description' => $data['description'] ?? null,
                'status'      => $status,
                'enabled'     => (bool) ($data['enabled'] ?? true),
                'path'        => $entry->getPathname(),
            ];
        }

        return $items;
    }

    /**
     * Discover themes from lib/themes/.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discoverThemes(): array
    {
        $items = [];
        $dir = $this->basePath . '/themes';
        if (!is_dir($dir)) {
            return $items;
        }

        $iterator = new \DirectoryIterator($dir);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $themeJson = $entry->getPathname() . '/theme.json';
            if (is_file($themeJson)) {
                $data = json_decode(file_get_contents($themeJson), true);
                if (!empty($data)) {
                    $items[] = [
                        'name'        => $data['name'] ?? $entry->getFilename(),
                        'slug'        => $entry->getFilename(),
                        'type'        => 'theme',
                        'version'     => $data['version'] ?? null,
                        'description' => $data['description'] ?? null,
                        'status'      => 'discovered',
                        'path'        => $entry->getPathname(),
                    ];
                    continue;
                }
            }

            // Fallback to directory name.
            $items[] = [
                'name'        => $entry->getFilename(),
                'slug'        => $entry->getFilename(),
                'type'        => 'theme',
                'version'     => null,
                'description' => null,
                'status'      => 'discovered',
                'path'        => $entry->getPathname(),
            ];
        }

        return $items;
    }

    /**
     * Discover layouts from lib/layouts/.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discoverLayouts(): array
    {
        $items = [];
        $dir = $this->basePath . '/layouts';
        if (!is_dir($dir)) {
            return $items;
        }

        $iterator = new \DirectoryIterator($dir);
        foreach ($iterator as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $layoutJson = $entry->getPathname() . '/layout.json';
            if (is_file($layoutJson)) {
                $data = json_decode(file_get_contents($layoutJson), true);
                if (!empty($data)) {
                    $items[] = [
                        'name'        => $data['name'] ?? $entry->getFilename(),
                        'slug'        => $entry->getFilename(),
                        'type'        => 'layout',
                        'version'     => $data['version'] ?? null,
                        'description' => $data['description'] ?? null,
                        'status'      => 'discovered',
                        'path'        => $entry->getPathname(),
                    ];
                    continue;
                }
            }

            // Fallback to directory name.
            $items[] = [
                'name'        => $entry->getFilename(),
                'slug'        => $entry->getFilename(),
                'type'        => 'layout',
                'version'     => null,
                'description' => null,
                'status'      => 'discovered',
                'path'        => $entry->getPathname(),
            ];
        }

        return $items;
    }
}
