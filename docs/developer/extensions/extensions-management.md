# Extensions Management

## Purpose

Admin-facing read-only browsing of discovered application extensions:
plugins, themes, and layouts.

## Routes

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/admin/extensions` | Filesystem-discovered extensions |
| `GET` | `/admin/extensions/catalog` | Catalog-managed extensions |
| `GET` | `/admin/extensions/catalog/submit` | Submission form (new entry) |
| `POST` | `/admin/extensions/catalog/submit` | Create catalog entry (pending) |

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

## Catalog

The catalog page (`GET /admin/extensions/catalog`) shows all catalog-managed extensions from the `catalog_extensions` table:

1. Summary counts by type
2. Single table with columns: Name, Slug, Type, Version, Description, Author, Status (Approved/Pending/Rejected), Installed, Enabled
3. **"Submit Extension"** button to add a new entry
4. **"Browse Catalog"** button linking back to catalog from the filesystem discovery page

### Local Submission

A developer/admin can submit a new extension into the local catalog for review:

- **Route:** `POST /admin/extensions/catalog/submit`
- **Form page:** `GET /admin/extensions/catalog/submit`
- **Permission:** `extensions.manage`
- **Controller:** `ExtensionsController::handleSubmit()`
- **Service:** `CatalogService::create()` (validation via `validateCreate()`)

**Required fields:**
| Field | Rule |
|-------|------|
| `name` | required, non-empty |
| `slug` | required, `/^[a-z][a-z0-9_-]*$/`, must be unique |
| `type` | must be `plugin`, `theme`, or `layout` |
| `version` | must match `\d+\.\d+\.\d+` |

**Optional fields:**
| Field | Default | Rule |
|-------|---------|------|
| `description` | `''` | free text |
| `author` | `''` | free text |
| `download_url` | `''` | URL |
| `repo_url` | `null` | URL, empty becomes null |
| `requirements` | `'[]'` | JSON string |
| `dependencies` | `'[]'` | JSON string |
| `checksum` | `null` | SHA-256 hex string |

**Behavior:**
- Submitted entries are saved with `status = 'pending'`, `is_installed = 0`, `is_enabled = 0`
- Validation errors re-render the form with old values preserved
- Success redirects to `/admin/extensions/catalog` with a flash message
- HTTP 422 is returned on validation failure

## Read-Only Limitation

This pass is **read-only discovery and display only**.

### Implemented

- Local extension catalog submission (pending review)

### Deferred

- Approval / rejection workflow
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
