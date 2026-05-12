<?php

namespace App\Core\Mail;

/**
 * Template path registry for email templates.
 *
 * Core and plugins register template paths; find() searches in order:
 * 1. Core template names
 * 2. Plugin template directories
 *
 * Rendering is plain PHP require with extract — no template engine.
 */
class TemplateRegistry
{
    /** @var array<string, string> Core template names → file paths */
    private static array $core = [];

    /** @var array<array{slug: string, path: string}> Plugin template paths */
    private static array $paths = [];

    /**
     * Register a core template.
     */
    public static function addCore(string $name, string $path): void
    {
        self::$core[$name] = $path;
    }

    /**
     * Register a plugin template directory.
     * Templates in this directory are addressed by filename relative to the dir.
     */
    public static function addPath(string $slug, string $path): void
    {
        if (!str_ends_with($path, '/')) {
            $path .= '/';
        }
        self::$paths[] = ['slug' => $slug, 'path' => $path];
    }

    /**
     * Resolve a template name to its file path.
     *
     * Searches core templates first, then plugin paths.
     *
     * @throws \RuntimeException if the template is not found
     */
    public static function find(string $name): string
    {
        if (isset(self::$core[$name])) {
            return self::$core[$name];
        }

        foreach (self::$paths as $entry) {
            $path = $entry['path'] . $name;
            if (is_file($path)) {
                return $path;
            }
        }

        throw new \RuntimeException("Email template not found: {$name}");
    }

    /**
     * Render a template with the given context variables.
     *
     * All context keys become local variables via extract().
     *
     * @param array<string, mixed> $context
     * @throws \RuntimeException if template not found
     */
    public static function render(string $name, array $context = []): string
    {
        $path = self::find($name);
        extract($context);
        ob_start();
        require $path;
        return ob_get_clean();
    }

    /**
     * Clear all registered templates (for testing).
     */
    public static function clear(): void
    {
        self::$core = [];
        self::$paths = [];
    }
}
