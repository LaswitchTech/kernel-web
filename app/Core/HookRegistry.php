<?php

namespace App\Core;

/**
 * Renderable hook content — any object implementing this interface can
 * be passed to HookRegistry::register() as hook output.
 *
 * The hook renderer will invoke render() at the appropriate point.
 */
interface HookRenderable
{
    /**
     * Render the hook content.
     *
     * Output may be echoed directly or returned as a string.
     * View variables available via $context are passed as an array.
     */
    public function render(array $context = []): string;
}

/**
 * In-memory hook registry.
 *
 * Supports named hooks with priority-based ordering. Callers register
 * content for a hook name; layouts call render() to output them.
 *
 * Example usage:
 *   HookRegistry::register('layout.head', function($ctx) {
 *       return '<meta name="foo" content="bar">';
 *   });
 *
 *   HookRegistry::render('layout.head');
 */
class HookRegistry
{
    /** @var array<string, array<int, array{priority: int, content: mixed}>> */
    private static array $hooks = [];

    /**
     * Register hook content for a named hook.
     *
     * @param string  $hook   Hook name (e.g. 'layout.head')
     * @param mixed   $content Callable string, or HookRenderable object
     * @param int     $priority Higher values render later (default: 0)
     */
    public static function register(string $hook, mixed $content, int $priority = 0): void
    {
        self::$hooks[$hook][] = [
            'priority' => $priority,
            'content'  => $content,
        ];
    }

    /**
     * Render all registered content for a hook.
     *
     * @param string $hook Hook name
     * @param array  $context View variables available to renderables
     * @return string Rendered output (empty string if nothing registered)
     */
    public static function render(string $hook, array $context = []): string
    {
        $output = '';

        $items = self::$hooks[$hook] ?? [];
        if (empty($items)) {
            return $output;
        }

        // Sort by priority ascending (higher values render later).
        usort($items, function (array $a, array $b): int {
            return $a['priority'] <=> $b['priority'];
        });

        foreach ($items as $item) {
            $content = $item['content'];

            if ($content instanceof HookRenderable) {
                $output .= $content->render($context);
            } elseif (is_callable($content)) {
                $output .= $content($context);
            }
        }

        return $output;
    }

    /**
     * Check if any content is registered for a hook.
     */
    public static function has(string $hook): bool
    {
        return isset(self::$hooks[$hook]) && !empty(self::$hooks[$hook]);
    }

    /**
     * Clear all registered hooks (for testing / isolation).
     */
    public static function clear(): void
    {
        self::$hooks = [];
    }
}
