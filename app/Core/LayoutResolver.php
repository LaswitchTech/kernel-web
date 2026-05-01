<?php

namespace App\Core;

/**
 * Resolves layout file paths with override support.
 *
 * Resolution order:
 *   1. app-level layout override: app/Views/layouts/{name}.php
 *   2. kernel layout: lib/layouts/{name}.php
 *   3. fallback: app.php (kernel default)
 *
 * This enables developers to replace individual layouts without
 * modifying kernel code. Themes will later extend this chain.
 */
class LayoutResolver
{
    private string $appViewsDir;
    private string $kernelLayoutsDir;
    private string $fallback;

    public function __construct(
        string $appViewsDir,
        string $kernelLayoutsDir,
        string $fallback = 'app.php'
    ) {
        $this->appViewsDir = rtrim($appViewsDir, '/\\');
        $this->kernelLayoutsDir = rtrim($kernelLayoutsDir, '/\\');
        $this->fallback = $fallback;
    }

    /**
     * Resolve a layout file path.
     *
     * @param string $name Layout name without extension (e.g., 'blank', 'app')
     * @return string Absolute path to the layout file
     */
    public function resolve(string $name): string
    {
        $file = $name . '.php';

        // 1. App-level override
        $appPath = $this->appViewsDir . '/layouts/' . $file;
        if (is_file($appPath)) {
            return $appPath;
        }

        // 2. Kernel layout
        $kernelPath = $this->kernelLayoutsDir . '/' . $file;
        if (is_file($kernelPath)) {
            return $kernelPath;
        }

        // 3. Fallback (returns the fallback name; caller handles resolution)
        return $this->fallback;
    }

    /**
     * Check if a layout exists at any level.
     */
    public function exists(string $name): bool
    {
        $file = $name . '.php';

        if (is_file($this->appViewsDir . '/layouts/' . $file)) {
            return true;
        }

        if (is_file($this->kernelLayoutsDir . '/' . $file)) {
            return true;
        }

        return false;
    }
}
