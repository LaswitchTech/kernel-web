# Extensions Management

## Purpose

Admin-facing read-only browsing of discovered application extensions:
plugins, themes, and layouts.

## Route

```
GET /admin/extensions
```

**Middleware:** `WebAuth`, `WebPermission:extensions.manage`

**Controller:** `App\Controllers\Admin\ExtensionsController`

## Extension Types

| Type | Directory | Manifest File |
|------|-----------|------------|
| plugin | `lib/plugins/{Name}/` | `plugin.json` |
| theme | `lib/themes/{Name}/` | `theme.json` |
| layout | `lib/layouts/{Name}/` | `layout.json` |

## Discovery Rules

### Plugins

- Scans `lib/plugins/*/` for `plugin.json`
- Required fields: `name`, `version`
- If manifest exists and is valid: status is `enabled` or `disabled` (from `enabled` field)
- If manifest is missing or invalid: status is `invalid` with a reason

### Themes

- Scans `lib/themes/*/` for `theme.json`
- If manifest exists: reads `name`, `version`, `description`
- If manifest is missing: falls back to directory name

### Layouts

- Scans `lib/layouts/*/` for `layout.json`
- If manifest exists: reads `name`, `version`, `description`
- If manifest is missing: falls back to directory name

## Permissions

### `extensions.manage`

Gates access to the Extensions admin page. Granted to the admin group by default.

Migration: `database/migrations/0048_add_extensions_manage_permission.php`

## Display

The admin page shows:
1. Summary counts (Plugins, Themes, Layouts)
2. Per-type table with columns:
   - **Name** — human-readable extension name
   - **Slug** — directory identifier
   - **Version** — from manifest, or `—`
   - **Description** — from manifest, or `—`
   - **Status** — badge (enabled/disabled/discovered/invalid)

## Read-Only Limitation

This pass is **read-only discovery and display only**.

### Deferred

- Enable / disable plugins
- Install / uninstall extensions
- Upload / marketplace browsing
- Remote updates
- Licensing
- Dependency resolution UI
- Theme switching
- Layout switching

## Catalog vs Filesystem Discovery

Filesystem discovery scans installed directories for JSON manifests.
The **Extension Catalog** stores structured metadata in the `catalog_extensions` table.
See [catalog.md](catalog.md) for the full catalog reference.

- `app/Services/Extensions/ExtensionDiscoveryService.php` — filesystem discovery logic
- `app/Services/Extensions/CatalogService.php` — catalog CRUD service
- `app/Models/CatalogExtensionRepository.php` — catalog repository
- `app/Controllers/Admin/ExtensionsController.php` — admin controller
- `app/Views/admin/extensions/index.php` — admin view template
- `routes/web.php` — route registration with permission middleware
