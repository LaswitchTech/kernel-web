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
     * Get rendered menu data suitable for sidebar rendering.
     *
     * Returns an array of arrays with keys:
     *   - type: 'section' or 'link'
     *   - label: section label or item label
     *   - url: destination URL (null for sections)
     *   - icon: Bootstrap Icons class (for links)
     *   - styleClass: additional CSS class (for links)
     *   - permission: required permission (for links)
     *   - name: identifier
     *   - source: plugin or 'core'
     *
     * Section carrier items (name starting with '__section__') carry section
     * labels but have no url. They are excluded from the returned array.
     *
     * @param string   $menuName  Menu identifier
     * @param string[] $userPermissions
     * @return array<int, array<string, mixed>>
     */
    public static function renderItems(string $menuName, array $userPermissions = []): array
    {
        $items    = self::get($menuName, $userPermissions);
        $sections = [];

        foreach ($items as $item) {
            foreach ($item->sections ?? [] as $section) {
                $sections[$section['order']] = $section['label'];
            }
        }

        $result = [];
        $lastOrder = -1;

        foreach ($items as $item) {
            // Skip section carrier items (they have no url).
            if (str_starts_with($item->name, '__section__')) {
                continue;
            }

            // Only inject a section if it comes before this item.
            foreach ($sections as $order => $label) {
                if ($order > $lastOrder && $order < $item->order) {
                    $result[] = [
                        'type'  => 'section',
                        'label' => $label,
                        'order' => $order,
                    ];
                    $lastOrder = $order;
                    unset($sections[$order]);
                }
            }

            $result[] = [
                'type'       => 'link',
                'name'       => $item->name,
                'label'      => $item->label,
                'url'        => $item->url,
                'icon'       => $item->icon,
                'styleClass' => $item->styleClass,
                'permission' => $item->permission,
                'source'     => $item->source,
                'order'      => $item->order,
            ];
            $lastOrder = max($lastOrder, $item->order);
        }

        return $result;
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
