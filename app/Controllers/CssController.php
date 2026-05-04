<?php

namespace App\Controllers;

use App\Core\Config;

/**
 * Serves dynamically compiled CSS at GET /css.
 *
 * Priority:
 *   1. production mode — serve npm-compiled public/assets/css/app.css (existing build)
 *   2. development mode — dynamically compile LESS via LessCompiler service
 *
 * The npm build (npm run build:css) is the source of truth for production CSS.
 * The PHP compiler handles theme/layout/plugin extensions not yet in the static build.
 */
class CssController
{
    /**
     * Serve compiled CSS.
     *
     * Content-Type is set to text/css.
     * A Cache-Control header enables browser caching (1 hour).
     */
    public function show(): void
    {
        header('Content-Type: text/css');
        header('Cache-Control: public, max-age=3600');

        $debug = (bool) Config::get('app', 'debug', true);

        // Production: serve the npm-compiled CSS if it exists.
        // This is the canonical build output and supports all LESS features
        // (calc with CSS vars, pseudo-element nesting, etc.).
        $staticCss = dirname(__DIR__, 2) . '/public/assets/css/app.css';
        if (!$debug && is_file($staticCss)) {
            echo file_get_contents($staticCss);
            return;
        }

        // Development: dynamically compile via PHP.
        // Falls back to static CSS if the compiler fails.
        try {
            $compiler = new \App\Services\LessCompiler();
            $css = $compiler->compile();
            echo $css;
        } catch (\Throwable $e) {
            // Fallback: serve static CSS if available.
            if (is_file($staticCss)) {
                echo file_get_contents($staticCss);
                return;
            }
            echo "/* CSS unavailable: {$e->getMessage()} */";
        }
    }
}
