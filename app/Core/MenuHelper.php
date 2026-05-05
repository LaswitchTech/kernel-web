<?php

namespace App\Core;

/**
 * Helper for rendering menu items from the MenuRegistry.
 *
 * Provides a single method to render a sidebar from registered menu items.
 * Section labels and links are interleaved based on their order fields.
 *
 * Usage in a layout:
 *   echo MenuHelper::renderSidebar($navActive, $permissions, $badges);
 */
class MenuHelper
{
    /**
     * Render the sidebar navigation HTML.
     *
     * @param string    $navActive   Current active page title (for highlighting).
     * @param string[]  $permissions User's permission strings.
     * @param array<string, int> $badges URL-prefix to badge count mapping.
     * @return string
     */
    public static function renderSidebar(string $navActive, array $permissions, array $badges = [], string $menuName = 'sidebar', string $currentPage = ''): string
    {
        $items = MenuRegistry::renderItems($menuName, $permissions);

        $out = '';

        foreach ($items as $item) {
            if ($item['type'] === 'section') {
                $cls = 'sidebar-section-label';
                // Highlight section header when any child URL is active.
                if ($currentPage && !empty($item['children'] ?? [])) {
                    foreach ($item['children'] as $child) {
                        if (rtrim($currentPage, '/') === rtrim($child['url'], '/')) {
                            $cls .= ' active';
                            break;
                        }
                    }
                }
                $out .= '<div class="' . htmlspecialchars($cls) . '">' . htmlspecialchars($item['label']) . "</div>\n";
                continue;
            }

            // Skip section-carrier items (they have no url).
            if ($item['url'] === null) {
                continue;
            }

            $url = $item['url'];
            // Exact match takes priority; then prefix match for nested routes.
            $isExact  = (rtrim($navActive, '/') === rtrim($url, '/'));
            $isPrefix = !$isExact && strpos($navActive, rtrim($url, '/')) === 0;
            $isActive = $isExact || $isPrefix;

            $classes = 'sidebar-link';
            if ($isActive) {
                $classes .= ' active';
            }
            if ($item['styleClass'] !== null) {
                $classes .= ' ' . $item['styleClass'];
            }

            $out .= '<a class="' . htmlspecialchars($classes) . '" href="' . htmlspecialchars($url) . "\">\n";

            if ($item['icon'] !== null) {
                $out .= '    <i class="bi ' . htmlspecialchars($item['icon']) . ' sidebar-link-icon"></i>' . "\n";
            }

            $out .= '    <span class="sidebar-link-label">' . htmlspecialchars($item['label']) . "</span>\n";

            // Render badge if present.
            $badgeKey = $item['url'];
            if (isset($badges[$badgeKey]) && $badges[$badgeKey] > 0) {
                $out .= '    <span class="badge rounded-pill bg-warning text-dark ms-auto">'
                      . htmlspecialchars($badges[$badgeKey])
                      . '</span>' . "\n";
            }

            $out .= "</a>\n";
        }

        return $out;
    }
}
