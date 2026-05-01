<?php

namespace App\Core;

/**
 * In-memory menu registry.
 *
 * Supports multiple named menus. Items are filtered by permission
 * before being returned. Plugins can register items; the kernel
 * registers core items.
 *
 * Example usage:
 *   MenuRegistry::add('sidebar', new MenuItem('dashboard', 'Dashboard', '/', 'bi bi-house', null, null, 0, null, 'core'));
 *   MenuRegistry::render('sidebar', ['admin']);
 */
class MenuRegistry
{
    /** @var array<string, array<int, MenuItem>> */
    private static array $menus = [];

    /**
     * Add a menu item to a named menu.
     *
     * Items are automatically sorted by their order field.
     */
    public static function add(string $menuName, MenuItem $item): void
    {
        self::$menus[$menuName][] = $item;
    }

    /**
     * Get all items for a menu, filtered by the user's permissions.
     *
     * @param string   $menuName  Menu identifier (e.g. 'sidebar')
     * @param string[] $userPermissions Array of permissions the authenticated user has
     * @return MenuItem[] Filtered and sorted items
     */
    public static function get(string $menuName, array $userPermissions = []): array
    {
        $items = self::$menus[$menuName] ?? [];

        // Filter by permission.
        $filtered = [];
        foreach ($items as $item) {
            if ($item->permission !== null && !in_array($item->permission, $userPermissions, true)) {
                continue;
            }
            $filtered[] = $item;
        }

        // Sort by order (lower first), then by name as tiebreaker.
        usort($filtered, function (MenuItem $a, MenuItem $b): int {
            $cmp = $a->order <=> $b->order;
            return $cmp !== 0 ? $cmp : ($a->name <=> $b->name);
        });

        return $filtered;
    }

    /**
     * Check if a menu has any items.
     */
    public static function has(string $menuName): bool
    {
        return isset(self::$menus[$menuName]) && !empty(self::$menus[$menuName]);
    }

    /**
     * Check if a menu has items visible for the given permissions.
     */
    public static function hasVisible(string $menuName, array $userPermissions = []): bool
    {
        return !empty(self::get($menuName, $userPermissions));
    }

    /**
     * Clear all registered menus (for testing / isolation).
     */
    public static function clear(): void
    {
        self::$menus = [];
    }
}
