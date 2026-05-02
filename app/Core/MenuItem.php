<?php

namespace App\Core;

/**
 * Immutable menu item value object.
 *
 * Fields:
 *   name        — unique identifier within the menu
 *   label       — display text
 *   url         — destination URL
 *   icon        — Bootstrap Icons class (e.g. 'bi bi-house')
 *   styleClass  — additional CSS class for styling
 *   permission  — required permission string (null = no check)
 *   order       — sort priority (lower renders first)
 *   parentId    — parent menu item name for grouping (null = top-level)
 *   source      — plugin name or 'core' identifier
 */
class MenuItem
{
    /**
     * @param string $name
     * @param string $label
     * @param string|null $url
     * @param string|null $icon
     * @param string|null $styleClass
     * @param string|null $permission
     * @param int $order
     * @param string|null $parentId
     * @param string $source
     * @param array<int, array{label: string, order: int}> $sections Section labels interleaved with items
     */
    public function __construct(
        public readonly string                        $name,
        public readonly string                        $label,
        public readonly ?string                       $url,
        public readonly ?string                       $icon,
        public readonly ?string                       $styleClass,
        public readonly ?string                       $permission,
        public readonly int                           $order,
        public readonly ?string                       $parentId,
        public readonly string                        $source,
        public readonly array                         $sections = [],
    ) {
    }
}
