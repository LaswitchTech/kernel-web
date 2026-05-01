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
    public function __construct(
        public readonly string  $name,
        public readonly string  $label,
        public readonly ?string $url,
        public readonly ?string $icon,
        public readonly ?string $styleClass,
        public readonly ?string $permission,
        public readonly int     $order,
        public readonly ?string $parentId,
        public readonly string  $source,
    ) {
    }
}
