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
| `POST` | `/admin/extensions/catalog/{id}/uninstall` | Uninstall an installed entry |

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

**Runtime integration:**
- The plugin loader checks catalog `is_enabled` when a catalog entry exists for an installed extension
- Catalog `is_enabled` takes precedence over the manifest's `enabled` field
- Extensions without a catalog entry (no catalog record or not installed) fall back to manifest `enabled`
- The override is implemented in `PluginLoader::getCatalogEnabledState()`

### Uninstall

Uninstall removes an installed extension's files from disk while preserving the catalog record for history.

- **Route:** `POST /admin/extensions/catalog/{id}/uninstall`
- **Permission:** `extensions.manage`
- **Controller:** `ExtensionsController::handleUninstall()`
- **Service:** `CatalogService::canUninstall()` (validation) + `CatalogService::markAsUninstalled()` (metadata)
- **View:** `app/Views/admin/extensions/catalog.php` (Uninstall button in new Uninstall column)

**UI visibility:**
- **Uninstall** button — `is_installed = 1` AND `is_enabled = 0` (installed but disabled only)
- Button is **not** shown when enabled — extensions must be disabled first

**Preconditions (all must pass):**
1. Catalog entry exists
2. `is_installed = 1`
3. `is_enabled = 0` — uninstall blocked if extension is enabled
4. `type` must be `plugin`, `theme`, or `layout`
5. `slug` must match `/^[a-z][a-z0-9_-]+$/`
6. Extension directory exists under `lib/{type}s/{slug}`
7. Resolved path is under trusted `lib/` base (`realpath()` prefix check)

**Uninstall process:**
1. All preconditions validated
2. Run optional `uninstall` lifecycle hook (for plugins only — themes/layouts have no lifecycle hooks)
3. If uninstall hook fails: abort, flash error, files may remain on disk
4. Recursively remove extension directory from disk (symbolic links skipped)
5. Set `is_installed = 0` and `is_enabled = 0` in catalog (record preserved)
6. Flash success "Extension ... uninstalled.", redirect to `/admin/extensions/catalog`

**Catalog record preservation:**
- The catalog entry is **never deleted** during uninstall
- `is_installed` and `is_enabled` are set to `0`
- The record remains as an audit trail

**Safety rules:**
- `realpath()` used on all directory paths — never trust user input
- Path prefix check ensures removal only under `lib/` base
- Symbolic links are skipped during removal to prevent traversal
- Kernel/core paths are outside `lib/` — never affected
- Catalog records are preserved (no `DELETE` from `catalog_extensions`)

## Lifecycle Hooks

Plugins can declare lifecycle hooks in `plugin.json` to execute custom code at key moments:

```json
{
    "lifecycle": {
        "install": "Plugins\\MyPlugin\\Lifecycle@install",
        "enable": "Plugins\\MyPlugin\\Lifecycle@enable",
        "disable": "Plugins\\MyPlugin\\Lifecycle@disable",
        "uninstall": "Plugins\\MyPlugin\\Lifecycle@uninstall"
    }
}
```

### Callback Signature

Each hook receives four arguments:

```php
public static function install(string $name, string $path, array $context, ?Container $container): void
```

| Parameter    | Description                                    |
|--------------|------|--------------------|
| `$name`      | Plugin name from manifest                      |
| `$path`      | Plugin base directory on disk                  |
| `$context`   | Additional context (currently empty array)     |
| `$container` | Application DI container (optional, may be null) |

### Install Hook

Triggered after staged file copy succeeds but before `is_installed` is updated in the catalog.

- Runs **before** the kernel's migration system (migrations declared in `plugin.json` run after the hook)
- Use for: initializing plugin data, setting up defaults, custom setup logic
- Migrations declared in `plugin.json` run after the install hook completes

### Enable Hook

Triggered when catalog `is_enabled` transitions from `0` to `1`.

- The plugin must already be loaded into the registry
- Use for: registering routes, registering menu items, caching warmup

### Disable Hook

Triggered when catalog `is_enabled` transitions from `1` to `0`.

- The plugin remains in the registry (catalog state controls activation)
- Use for: unregistering hooks, clearing caches, releasing resources

### Uninstall Hook

Triggered when an installed catalog extension is uninstalled.

- Only runs for plugins — themes/layouts have no lifecycle hooks
- The hook is optional — missing entries are silently skipped (treated as success)
- If the uninstall hook fails: the uninstall is aborted, files may remain on disk
- The hook runs **before** files are removed (so the plugin can perform cleanup)
- Use for: removing plugin data, clearing user data, cleanup tasks

### Safety

- Lifecycle hooks are **optional** — missing entries are silently skipped
- Callback must be in the `Plugins\` namespace
- Class and method existence are validated before invocation
- Exceptions are caught and logged — hooks never crash the application
- Hooks are never triggered during the discovery phase

## Implemented

- Local extension catalog submission (pending review)
- Approval/rejection review workflow for pending submissions
- Staged install for approved catalog entries (copy from trusted staging directory)
- Enable/disable installed catalog entries (database lifecycle state + runtime integration)
- Runtime plugin activation controlled by catalog `is_enabled` for installed catalog extensions
- Uninstall installed catalog entries (disable-first rule, lifecycle hook, file removal, catalog preservation)
- Extension dependency resolution (resolver service, install/enable/disable/uninstall enforcement via flash messages)
- Extension update checks design (local-only: on-disk manifest vs catalog version, status types, dependency blocking, UI columns — see updates.md)

## Deferred

- Remote download
- ZIP archive extraction
- Upload / marketplace browsing
- Remote updates (remote catalog sync — Phase 3)
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
