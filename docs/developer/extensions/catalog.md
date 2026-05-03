# Extension Catalog

## Purpose

The Extension Catalog provides a centralized database-backed store for extension metadata.
It complements the existing filesystem-based extension discovery system.

### Why Both Systems Exist

| System | Purpose | Data Source |
|--------|---------|-------------|
| **Filesystem Discovery** | Read-only scanning of installed extensions | JSON manifests in `lib/plugins/`, `lib/themes/`, `lib/layouts/` |
| **Extension Catalog** | Structured metadata storage for managed extensions | SQLite `catalog_extensions` table |

Filesystem discovery remains the canonical source for **installed** extensions.
The catalog stores metadata for extensions that may be:
- Submitted by developers for review
- Approved and available for installation
- Synced from a remote catalog server

The two systems will be linked in future phases — approved catalog entries can be installed
to the appropriate `lib/` directory, where filesystem discovery picks them up.

## Schema

**Table: `catalog_extensions`**

| Column | Type | Description |
|--------|------|-------|
| `id` | INTEGER PK | Auto-increment primary key |
| `name` | TEXT NOT NULL | Display name |
| `slug` | TEXT NOT NULL UNIQUE | URL-safe identifier |
| `type` | TEXT NOT NULL | `plugin`, `theme`, or `layout` |
| `version` | TEXT NOT NULL DEFAULT '0.0.0' | Semantic version string |
| `description` | TEXT NOT NULL DEFAULT '' | Short description |
| `author` | TEXT NOT NULL DEFAULT '' | Author / vendor name |
| `download_url` | TEXT NOT NULL DEFAULT '' | URL to zip archive |
| `repo_url` | TEXT NULL | Source repository URL |
| `requirements` | TEXT NOT NULL DEFAULT '[]' | JSON requirements |
| `dependencies` | TEXT NOT NULL DEFAULT '[]' | JSON array of extension slugs |
| `status` | TEXT NOT NULL DEFAULT 'pending' | Review status |
| `is_installed` | INTEGER NOT NULL DEFAULT 0 | Install flag |
| `is_enabled` | INTEGER NOT NULL DEFAULT 0 | Enable flag |
| `checksum` | TEXT NULL | SHA-256 of distribution archive |
| `created_at` | VARCHAR(32) NOT NULL | Submission timestamp |
| `updated_at` | VARCHAR(32) NOT NULL | Last update timestamp |

**Status values:** `pending`, `approved`, `rejected`

**Type values:** `plugin`, `theme`, `layout`

**Boolean flags:** `is_installed` and `is_enabled` use 0/1 (SQLite integers).

## Migration

Created by `database/migrations/0049_create_catalog_extensions_table.php`.

## Repository

**File:** `app/Models/CatalogExtensionRepository.php`

Provides raw database access for the catalog table:

| Method | Description |
|--------|-------------|
| `create(array $data)` | Insert a new catalog extension. Returns new ID. |
| `update(int $id, array $data)` | Update an existing record by ID. |
| `delete(int $id)` | Delete a catalog extension record. |
| `findBySlug(string $slug)` | Find one extension by slug. |
| `findById(int $id)` | Find one extension by ID. |
| `findAll()` | List all catalog extensions. |
| `findByType(string $type)` | List extensions by type. |
| `findApproved()` | List approved extensions. |
| `findPending()` | List pending extensions (newest first). |
| `findInstalled()` | List installed extensions. |
| `slugExists(string $slug)` | Uniqueness check for slug. |
| `markInstalled(int $id)` | Set `is_installed = 1`, `is_enabled = 1`. |
| `markUninstalled(int $id)` | Set `is_installed = 0`, `is_enabled = 0`. |
| `markEnabled(int $id)` | Set `is_enabled = 1`. |
| `markDisabled(int $id)` | Set `is_enabled = 0`. |
| `updateStatus(int $id, string $status)` | Change review status. |

## Service

**File:** `app/Services/Extensions/CatalogService.php`

Orchestrates catalog operations with validation:

| Method | Returns | Description |
|--------|---------|-------------|
| `create(array $data)` | `{success, id?, errors?}` | Create with validation |
| `update(int $id, array $data)` | `{success, errors?}` | Update with validation |
| `getBySlug(string $slug)` | `?array` | Get extension by slug |
| `getById(int $id)` | `?array` | Get extension by ID |
| `listAll()` | `array[]` | List all |
| `listByType(string $type)` | `array[]` | List by type |
| `listApproved()` | `array[]` | List approved |
| `listPending()` | `array[]` | List pending |
| `approve(int $id)` | `{success, errors?}` | Transition pending → approved |
| `reject(int $id, string $reason)` | `{success, errors?}` | Transition pending → rejected |
| `markInstalled(int $id)` | `{success, errors?}` | Mark as installed |
| `markUninstalled(int $id)` | `{success, errors?}` | Mark as uninstalled |
| `markEnabled(int $id)` | `{success, errors?}` | Mark as enabled |
| `markDisabled(int $id)` | `{success, errors?}` | Mark as disabled |
| `delete(int $id)` | `{success, errors?}` | Delete from catalog |
| `parseDependencies($value)` | `string[]` | Parse JSON/array deps |
| `parseRequirements($value)` | `array<string,string>` | Parse requirements |

### Validation Rules (CatalogService)

- **name**: required, non-empty
- **slug**: required, must match `/^[a-z][a-z0-9_-]*$/`, must be unique, auto-slugified
- **type**: must be one of `plugin`, `theme`, `layout`
- **version**: must match semantic versioning (`\d+\.\d+\.\d+`)
- **status**: must be one of `pending`, `approved`, `rejected`
- **dependencies / requirements**: stored as JSON arrays, parsed via helper methods

## Planned Future Usage

### Install Workflow
1. Admin selects an approved extension from the catalog
2. Kernel downloads from `download_url` or uses uploaded archive
3. Verifies `checksum`
4. Extracts to `lib/{type_plural}/{slug}/`
5. Sets `is_installed = 1`, `is_enabled = 1`
6. Runs plugin migrations if declared

### Enable/Disable Workflow
- `is_enabled` controls whether the extension is active
- Future: triggers enable/disable hooks in the plugin lifecycle

### Remote Sync
- Catalog metadata can be fetched from a remote catalog server
- New approved entries appear in local catalog without manual submission
- Local modifications are compared against remote state

### Integration with Filesystem Discovery
- Filesystem discovery scans `lib/` directories for installed extensions
- The catalog stores metadata for extensions in transit (pending review, approved but not yet installed)
- Future: sync installed catalog entries with filesystem findings
