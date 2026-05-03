# Extensions Management

## Purpose

Admin-facing read-only browsing of discovered application extensions:
plugins, themes, and layouts.

## Routes

| Method | Path | Description |
|--------|--|--- |
| `GET` | `/admin/extensions` | Filesystem-discovered extensions |
| `GET` | `/admin/extensions/catalog` | Catalog-managed extensions |
| `GET` | `/admin/extensions/catalog/submit` | Submission form (new entry) |
| `POST` | `/admin/extensions/catalog/submit` | Create catalog entry (pending) |
| `GET` | `/admin/extensions/catalog/review` | Review pending submissions |
| `POST` | `/admin/extensions/catalog/{id}/approve` | Approve a pending entry |
| `POST` | `/admin/extensions/catalog/{id}/reject` | Reject a pending entry |
| `POST` | `/admin/extensions/catalog/{id}/install` | Dry-run install of approved entry |

## Extension Types

| Type | Directory | Manifest File |
|------|-----------|------|------|
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
|--|--|
| `name` | required, non-empty |
| `slug` | required, `/^[a-z][a-z0-9_-]*$/`, must be unique |
| `type` | must be `plugin`, `theme`, or `layout` |
| `version` | must match `\d+\.\d+\.\d+` |

**Optional fields:**
| Field | Default | Rule |
|-------|--|--- |
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

### Status Lifecycle

| Status | Description |
|---|---|
| `pending` | Newly submitted entry awaiting review |
| `approved` | Reviewed and approved — available for future installation |
| `rejected` | Reviewed and rejected — entry is removed from the catalog |

**Transitions:**
- `pending` --[approve]--> `approved` (via `CatalogService::approve()`)
- `pending` --[reject]--> `rejected` (via `CatalogService::reject()`)
- Once a status is no longer `pending`, it cannot be changed (non-reversible in current pass)

### Review Page

The review page lists all pending catalog submissions:

- **Route:** `GET /admin/extensions/catalog/review`
- **Permission:** `extensions.manage`
- **Controller:** `ExtensionsController::review()`
- **Service:** `CatalogService::listPending()`
- **View:** `app/Views/admin/extensions/review.php`

**Actions on each entry:**
- **Approve** — sets status to `approved` via `CatalogService::approve()`
- **Reject** — sets status to `rejected` via `CatalogService::reject()`
- Both use Bootstrap modals for confirmation and redirect back to the review page

### Install (Dry-Run)

Approved, not-installed catalog entries show an **Install** button in the Actions column.
This is a dry-run validation pass — it does not copy files or download remote content.

- **Route:** `POST /admin/extensions/catalog/{id}/install`
- **Permission:** `extensions.manage`
- **Controller:** `ExtensionsController::handleInstall()`
- **View:** `app/Views/admin/extensions/catalog.php` (Install button in Actions column)

**Validations:**
1. Entry must exist
2. Entry status must be `approved`
3. Entry `is_installed` must be `0`
4. `type` must be `plugin`, `theme`, or `layout`
5. `slug` must match `/^[a-z][a-z0-9_-]+$/`
6. Resolved target path must be under `lib/` (path traversal guard)
7. Target directory must not already exist (no-overwrite guard)

**Behavior:**
- Reports where the extension would be installed
- No files written, no remote download, no ZIP extraction
- Redirects back to `/admin/extensions/catalog` with flash message
- Install button only shown for `status = 'approved'` and `is_installed = 0`

## Read-Only Limitation

This pass is **read-only discovery and display only**.

### Implemented

- Local extension catalog submission (pending review)
- Approval/rejection review workflow for pending submissions
- Dry-run install for approved catalog entries (validates safety, reports target path)

### Deferred

- Enable / disable plugins
- Actual file copy/install from local staging or remote download
- ZIP archive extraction
- Uninstall
- Upload / marketplace browsing
- Remote updates
- Licensing
- Dependency resolution UI
- Theme switching
- Layout switching
- Non-reversible status transitions (pending --> approved can be changed back)

## Catalog vs Filesystem Discovery

Filesystem discovery scans installed directories for JSON manifests.
The **Extension Catalog** stores structured metadata in the `catalog_extensions` table.
See [catalog.md](catalog.md) for the full catalog reference.

- `app/Services/Extensions/ExtensionDiscoveryService.php` — filesystem discovery logic
- `app/Services/Extensions/CatalogService.php` — catalog CRUD service
- `app/Models/CatalogExtensionRepository.php` — catalog repository
- `app/Controllers/Admin/ExtensionsController.php` — admin controller
- `app/Views/admin/extensions/index.php` — admin view template
- `app/Views/admin/extensions/catalog.php` — catalog view template
- `app/Views/admin/extensions/submit.php` — submission form template
- `app/Views/admin/extensions/review.php` — review page template
- `routes/web.php` — route registration with permission middleware
