# Extension Update Checks

## Purpose

Compare installed extension versions against catalog versions and surface available updates in the admin UI.

**Phase 2 design — local-only. No remote sync, no auto-install.**

---

## 1. Source of Truth for Installed Version

**Read from the extension's manifest on disk.**

| Type | Path |
|------|------|
| plugin | `lib/plugins/{slug}/plugin.json` → `version` |
| theme | `lib/themes/{slug}/theme.json` → `version` |
| layout | `lib/layouts/{slug}/layout.json` → `version` |

**Why not the catalog?** The catalog's `version` field records what was shipped at install time. Users may manually modify a manifest after install (patch, hotfix, feature branch). The on-disk manifest is the only source that reflects reality.

If the manifest is missing or invalid, the installed version is `null` (catalog entry preserved but extension unusable).

---

## 2. Source of Truth for Latest Available Version

**`catalog_extensions.version` is the latest available version.**

The catalog is a trusted metadata store. An entry's `version` field records the version that an admin approved for installation. When an admin updates the catalog entry's version (e.g., after reviewing a new release), the diff between catalog version and installed version indicates an available update.

**This is local-only.** Remote catalog sync (pulling versions from an external server) is a separate Phase 2/3 task — out of scope for this design.

---

## 3. Local-Only First

**Yes. Phase 2 is local-only.**

- Installed version → on-disk manifest
- Latest version → `catalog_extensions.version`
- No remote network calls

Remote catalog sync is a separate task that plugs into this system later. The design does not require any remote infrastructure.

---

## 4. Update Status Types

| Status | Key | Condition |
|--------|-----|-----------|
| Up-to-date | `up_to_date` | installed version equals catalog version |
| Update available | `update_available` | catalog version is newer than installed version (by semver) |
| Newer than catalog | `newer_than_catalog` | installed version is newer than catalog version |
| No catalog entry | `no_catalog_entry` | installed extension has no catalog record |
| Blocked | `blocked` | catalog version is newer but dependency constraint would not be satisfied |
| Invalid | `invalid` | on-disk manifest is missing or invalid |

**Blocked status rationale:** an update is technically available (catalog version > installed) but the extension declares a dependency constraint (e.g., `^1.2.0`) that the catalog version does not satisfy. The user must update dependencies first.

**Comparison method:** PHP `version_compare()` against the ExtensionDependencyResolver's constraint checker.

---

## 5. Dependency Constraint Effects

A dependency constraint on the installed extension **blocks** the update status only when:

1. The extension declares `dependencies` in its manifest
2. The catalog's current version does **not** satisfy at least one declared constraint
3. The catalog version is otherwise newer than the installed version

If the constraint is satisfied → normal `update_available` status.

If the constraint is not satisfied → `blocked` status with the blocking dependency listed.

**Example:**
```
plugin:notes dependency on theme:default ^1.0.0
Catalog version for theme:default is 0.9.9
0.9.9 does not satisfy ^1.0.0 → blocked
```

**What about constraints the catalog entry declares?** The catalog's own `dependencies` field describes what the catalog entry requires to be installed. It does not affect update checks — it only affects install/enable validation. Update checks compare versions against the *installed extension's declared constraints* (its manifest).

---

## 6. Auto-Install: No

**Default is no auto-install.** Updates are informational only — an admin must trigger the install action manually.

This is consistent with the existing extension management workflow:
- Catalog entries track `is_installed` and `is_enabled`
- Install is a `POST` action with staged file copy
- No change to that workflow — the update check just flags what *could* be updated

---

## 7. UI Changes in /admin/extensions/catalog

### 7a. New columns in catalog table

Add two columns to the existing catalog DataTable:

| Column | Content |
|--------|---------|
| **Local Version** | Installed version from on-disk manifest (or `—` if invalid) |
| **Update** | Status badge: `up_to_date` → green "Up-to-date", `update_available` → yellow "Update", `newer_than_catalog` → blue "Newer", `blocked` → orange "Blocked" with tooltip listing blocking deps |

### 7b. New action button

When status is `update_available` and no blocking deps: add an **"Update"** action button (same workflow as existing install — staged copy from staging directory, just with different flash message: "Extension updated to {version}.").

When status is `blocked`: show **"Update"** button but disabled, with tooltip explaining the blocking dependency.

### 7c. Filesystem extensions page (optional, future)

The filesystem discovery page (`/admin/extensions`) could also show an update indicator column, but this is lower priority. The catalog page is the primary source of truth.

---

## 8. Service for Comparison

New service: `app/Services/Extensions/ExtensionUpdateChecker.php`

Pure service, no state. Dependencies injected via constructor:

```php
class ExtensionUpdateChecker
{
    public function __construct(
        private CatalogExtensionRepository $catalogRepo,
        private string $libBase,          // '/lib' resolved via realpath
    ) {}

    /**
     * Check all installed catalog extensions.
     *
     * @return ExtensionUpdate[] keyed by slug
     */
    public function checkAll(): array;

    /**
     * Check a single installed extension.
     */
    public function check(string $slug): ?ExtensionUpdate;
}
```

**Value object:**

```php
readonly class ExtensionUpdate
{
    public function __construct(
        public string $slug,
        public string $type,
        public ?string $installedVersion,  // null if manifest invalid
        public string $catalogVersion,     // always present (catalog record exists)
        public string $status,             // one of the status keys
        public array $blockers = [],       // [] if not blocked
    ) {}
}
```

**Comparison logic:**
1. Read installed version from on-disk manifest
2. Fetch catalog entry for the extension
3. `version_compare($installedVersion, $catalogVersion)` to determine direction
4. If catalog version is newer: check manifest dependencies against catalog version
5. Set status accordingly

**Reading the manifest:** use `PluginManifest` for plugins, a thin reader for themes/layouts (or reuse PluginManifest with a generic "manifest" key). Since themes/layouts have the same `{type}.json` format with `name`/`version`, a shared reader is safe.

---

## 9. Smallest Safe Implementation Slice

### Scope for this iteration:

1. **Migration** — add `available_version` column to `catalog_extensions` (defaults to same as `version`, for future remote sync compatibility)
2. **Repository** — add `findAllInstalledWithCatalog()` method to fetch all installed catalog entries
3. **UpdateChecker service** — the core comparison logic
4. **Controller** — `GET /admin/extensions/catalog/updates` endpoint that returns the update statuses (called by JS or page render)
5. **UI** — add "Local Version" and "Update" columns to the catalog table; add update action button
6. **Docs** — create this file (updates.md)

### Out of scope for this slice:

- Remote catalog sync
- ZIP download for updates
- Updates admin module (separate section in admin sidebar)
- Auto-install
- Filesystem extensions page update indicators
- Version history / changelog display

---

## Data Model Changes

### `catalog_extensions` table — new column

| Column | Type | Default | Description |
|--------|------|---------|-----|
| `available_version` | TEXT | `''` | Latest available version (synced from remote in future; locally set to match `version`) |

This column is the bridge between local and future remote sync. When remote sync is implemented, `available_version` will be updated by the sync process while `version` reflects the last locally approved version. For local-only mode, `available_version = version`.

---

## Files to Create / Modify

| File | Action |
|------|--------|
| `app/Services/Extensions/ExtensionUpdateChecker.php` | **Create** — core comparison service |
| `app/Models/CatalogExtensionRepository.php` | **Modify** — add `findAllInstalledWithCatalog()` |
| `app/Controllers/Admin/ExtensionsController.php` | **Modify** — add `handleUpdates()` + `renderUpdates()` |
| `app/Views/admin/extensions/catalog.php` | **Modify** — add columns + update button |
| `database/migrations/0050_add_available_version_to_catalog_extensions.php` | **Create** — new migration |
| `routes/web.php` | **Modify** — add update check route(s) |
| `docs/developer/extensions/updates.md` | **Create** — this file |
| `DESIGN.md` | **Modify** — add Updates module section |
| `ROADMAP.md` | **Modify** — mark extension update checks as done in Phase 2 |
| `extensions-management.md` | **Modify** — add "Update Checks" section, remove "Remote updates" from deferred |

---

## Design Rules

- Update checks are read-only comparisons — no filesystem writes
- Manifest reads use realpath prefix validation to prevent traversal
- Invalid manifests produce `invalid` status (not a crash)
- Catalog version always wins for "latest available" in local-only mode
- No network calls in this phase
- Dependency constraint checking uses existing `ExtensionDependencyResolver`
- Update action reuses the existing staged install workflow (no new install logic)
- Admin must have `extensions.manage` permission for all update operations

---

## Deferred

- Remote catalog sync (periodic fetch from external server)
- Remote version comparison (remote available_version vs installed)
- ZIP download + extraction for updates
- Update notifications (email, in-app banner)
- Update history / changelog
- Batch update operations
- Update preview (diff of changes before installing)
- Version downgrade support
