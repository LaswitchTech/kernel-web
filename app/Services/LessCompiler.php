<?php

namespace App\Services;

/**
 * Dynamic CSS service for Kernel-Web.
 *
 * Merges the static kernel CSS (compiled via npm/less) with
 * dynamic theme/layout/plugin LESS that may not exist in the static build.
 *
 * Approach:
 *   1. Read the static kernel CSS (public/assets/css/app.css) as the base.
 *   2. Parse any active theme LESS via lessc (theme tokens).
 *   3. Parse any active layout LESS via lessc (layout overrides).
 *   4. Parse any enabled plugin LESS via lessc (plugin components).
 *   5. Combine: kernel CSS + theme + layout + plugin.
 *
 * This avoids the need for PHP to compile the full kernel LESS (which uses
 * calc(var(--css-var)) and other features not supported by lessphp) by
 * reusing the static npm build output as the base.
 */
class LessCompiler
{
    /** @var string Path to static kernel CSS. */
    private string $staticCssPath;

    /** @var string Lib base directory. */
    private string $libBase;

    /** @var string|false Cached static CSS. */
    private string|false $staticCss = false;

    /** @var string[] Tracked source file paths. */
    private array $sourcePaths = [];

    public function __construct()
    {
        $this->staticCssPath = dirname(__DIR__, 2) . '/public/assets/css/app.css';
        $this->libBase = rtrim(str_replace(['//', '\.'], '/', __DIR__ . '/../../lib'), '/');
    }

    /**
     * Compile and return CSS string.
     */
    public function compile(): string
    {
        // Load static kernel CSS.
        if (is_file($this->staticCssPath)) {
            $this->staticCss = file_get_contents($this->staticCssPath);
            $this->sourcePaths[] = $this->staticCssPath;
        } else {
            $this->staticCss = "/* Kernel CSS not found — run npm run build:css */\n";
        }

        // Collect dynamic LESS sources.
        $themeFiles = $this->collectExternalDir('themes');
        $layoutFiles = $this->collectExternalDir('layouts');
        $pluginFiles = $this->collectPluginLess();

        foreach (array_merge($themeFiles, $layoutFiles, $pluginFiles) as $path) {
            $this->sourcePaths[] = $path;
        }

        // Parse dynamic sources and merge.
        $parser = new \Less_Parser();

        // Parse dynamic LESS files.
        foreach (array_merge($themeFiles, $layoutFiles, $pluginFiles) as $path) {
            try {
                $parser->parseFile($path);
            } catch (\Throwable $e) {
                // Skip files that can't be parsed.
            }
        }

        // Get merged dynamic CSS.
        $dynamicCss = '';
        try {
            $dynamicCss = $parser->getCss();
        } catch (\Throwable $e) {
            // Dynamic parsing failed — return kernel CSS only.
        }

        // Combine: kernel CSS + dynamic additions.
        // Dynamic CSS may contain tokens/overrides that merge with kernel CSS
        // since both use CSS custom properties.
        if ($dynamicCss !== '') {
            return $this->staticCss . "\n" . $dynamicCss;
        }

        return $this->staticCss;
    }

    /**
     * Collect LESS files from an external directory (themes/layouts).
     *
     * @param string $subDir Directory name under lib/
     * @return array<string> Paths to app.less files found
     */
    private function collectExternalDir(string $subDir): array
    {
        $dir = "{$this->libBase}/{$subDir}";
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $it = new \DirectoryIterator($dir);
        $names = [];

        foreach ($it as $entry) {
            if ($entry->isDir() && !$entry->isDot()) {
                $lessFile = $entry->getRealPath() . '/less/app.less';
                if (is_file($lessFile)) {
                    $names[] = $lessFile;
                }
            }
        }
        sort($names);
        return $names;
    }

    /**
     * Collect LESS files from enabled plugins.
     *
     * @return array<string> Paths to app.less files found
     */
    private function collectPluginLess(): array
    {
        $pluginDir = "{$this->libBase}/plugins";
        if (!is_dir($pluginDir)) {
            return [];
        }

        $files = [];
        $it = new \DirectoryIterator($pluginDir);
        $names = [];

        foreach ($it as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $manifestPath = $entry->getRealPath() . '/plugin.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            $enabled = !isset($manifest['enabled']) || $manifest['enabled'] === true;
            if (!$enabled) {
                continue;
            }

            $lessFile = $entry->getRealPath() . '/less/app.less';
            if (!is_file($lessFile)) {
                continue;
            }

            $names[] = $lessFile;
        }

        sort($names);
        return $names;
    }
}
