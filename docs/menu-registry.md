# Menu Registry

## Purpose

The Menu Registry provides a central location for registering and rendering named menus. Core and plugins can add items to any registered menu.

## Architecture

```
[ Kernel Core ]
    MenuRegistry
        ├── add(menuName, MenuItem)
        └── get(menuName, userPermissions) → MenuItem[]
```

## Menu Locations

| Menu | Description |
|------|-------------|
| `sidebar` | Main sidebar navigation |
| `topbar` | Top bar actions |
| `user-menu` | User dropdown menu |
| `admin-menu` | Administration submenu |

## MenuItem Fields

| Field | Type | Description |
|------|------|-------------|
| `name` | string | Unique identifier within the menu |
| `label` | string | Display text |
| `url` | string|null | Destination URL |
| `icon` | string|null | Bootstrap Icons class (e.g., `'bi bi-house'`) |
| `styleClass` | string|null | Additional CSS class |
| `permission` | string|null | Required permission (null = no check) |
| `order` | int | Sort priority (lower renders first) |
| `parentId` | string|null | Parent item name for grouping |
| `source` | string | Plugin name or `'core'` identifier |

## Usage

### Registering a Menu Item

```php
use App\Core\MenuItem;
use App\Core\MenuRegistry;

MenuRegistry::add('sidebar', new MenuItem(
    name: 'chat',
    label: 'Chat',
    url: '/chat',
    icon: 'bi bi-chat-dots',
    styleClass: null,
    permission: 'chat.use',
    order: 10,
    parentId: null,
    source: 'core',
));
```

### Rendering a Menu

```php
// In a layout file:
$items = MenuRegistry::get('sidebar', $principal['permissions'] ?? []);
foreach ($items as $item) {
    if ($item->url) {
        echo '<a href="' . htmlspecialchars($item->url) . '">';
        echo '<i class="' . htmlspecialchars($item->icon) . '"></i>';
        echo htmlspecialchars($item->label);
        echo '</a>';
    }
}
```

### Plugin Manifest Format

Plugins can declare menu items in `plugin.json`:

```json
{
    "menus": [
        {
            "menu": "sidebar",
            "item": {
                "name": "tasks",
                "label": "Tasks",
                "url": "/tasks",
                "icon": "bi bi-check2-square",
                "permission": "tasks.manage",
                "order": 20,
                "parentId": null
            }
        }
    ]
}
```

## Implementation

- `MenuItem` — immutable value object (named parameters via constructor)
- `MenuRegistry` — static class for managing menu registrations
- Both live in `app/Core/`

## Filtering

Items are automatically filtered by the user's permissions when retrieved via `get()`. Items with a `permission` field require the user to have that permission string in their permission list.

## Ordering

Items are sorted by `order` (ascending), then by `name` as a tiebreaker.

## Limitations

- No menu grouping UI (parent/child relationships are data-only)
- No menu caching
- No automatic menu item discovery from plugins (requires `registerMenus()` in loader)
- No menu item templates (rendering is left to the caller)

## Future

- Automatic menu item discovery from plugin manifests
- Menu item templates (render hooks per-item)
- Menu grouping UI (nested dropdowns)
- Contextual menus (permission + condition-based visibility)
