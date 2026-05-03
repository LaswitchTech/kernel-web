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
| `POST` | `/admin/extensions/catalog/{id}/install` | Staged install of approved entry |
| `POST` | `/admin/extensions/catalog/{id}/enable` | Enable an installed entry |
| `POST` | `/admin/extensions/catalog/{id}/disable` | Disable an installed entry |

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

### Install (Staged)

Approved, not-installed catalog entries show an **Install** button in the Actions column.
Extensions are installed from a trusted local staging directory.

- **Route:** `POST /admin/extensions/catalog/{id}/install`
- **Permission:** `extensions.manage`
- **Controller:** `ExtensionsController::handleInstall()`
- **View:** `app/Views/admin/extensions/catalog.php` (Install button in Actions column)
- **Staging path:** `/storage/extension-staging/{slug}/`
- **Target paths:**
  - plugin → `/lib/plugins/{slug}`
  - theme → `/lib/themes/{slug}`
  - layout → `/lib/layouts/{slug}`

**Staging directory structure:**
```
storage/extension-staging/
├── my-plugin/        ← slug directory
│   ├── plugin.json
│   ├── src/
│   └── assets/
└── my-theme/         ← another extension
    ├── theme.json
    └── css/
```

**Validations:**
1. Entry must exist
2. Entry status must be `approved`
3. Entry `is_installed` must be `0`
4. `type` must be `plugin`, `theme`, or `layout`
5. `slug` must match `/^[a-z][a-z0-9_-]+$/`
6. Staging directory must exist (resolved via `realpath()`)
7. Source resolved path must be under staging base (path traversal guard)
8. Target directory must not already exist (no-overwrite guard)
9. Target resolved path must be under lib base (path traversal guard)

**Install process:**
1. All validations pass
2. Recursive copy from staging to target (symbolic links skipped)
3. On success: mark `is_installed = 1` in catalog
4. On failure: remove any partially created target directory
5. Flash message with result, redirect to `/admin/extensions/catalog`

**File permissions:**
- Directories: `0755`
- Files: `0644`

### Enable / Disable

Installed catalog extensions can be enabled or disabled. This controls the `is_enabled` flag in the catalog database.

- **Enable route:** `POST /admin/extensions/catalog/{id}/enable`
- **Disable route:** `POST /admin/extensions/catalog/{id}/disable`
- **Permission:** `extensions.manage`
- **Controller:** `ExtensionsController::handleEnable()` / `ExtensionsController::handleDisable()`

**UI visibility:**
- **Install** button — `status = approved` AND `is_installed = 0`
- **Enable** button — `is_installed = 1` AND `is_enabled = 0`
- **Disable** button — `is_installed = 1` AND `is_enabled = 1`

**Validations (both):**
1. Entry must exist
2. Entry `is_installed` must be `1`
3. Entry `is_enabled` must be the opposite of the action (not already in target state)
4. `type` must be `plugin`, `theme`, or `layout`
5. `slug` must match `/^[a-z][a-z0-9_-]+$/`
6. Extension directory must exist on disk under `lib/`

**Behavior:**
- **Enable** — sets `is_enabled = 1`, flash success "Extension ... enabled."
- **Disable** — sets `is_enabled = 0`, flash success "Extension ... disabled."
- Redirects back to `/admin/extensions/catalog`

**Installed vs Enabled:**
- `is_installed = 1` — extension files exist on disk in `lib/`
- `is_enabled = 1` — extension is marked as enabled in the catalog database
- These are independent states; an installed extension may be disabled

**Important limitation:**
- The runtime plugin loader does not yet check catalog `is_enabled` state
- Extension activation is still controlled by filesystem discovery
- A later task can reconcile catalog state with runtime extension activation

## Read-Only Limitation

This pass is **read-only discovery and display only**.

### Implemented

- Local extension catalog submission (pending review)
- Approval/rejection review workflow for pending submissions
- Staged install for approved catalog entries (copy from trusted staging directory)
- Enable/disable installed catalog entries (database lifecycle state only)

### Deferred

- Runtime loader integration — catalog enabled state does not yet control plugin activation
- Remote download
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
