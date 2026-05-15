# Kernel / Application / Extension Versioning Model

## Purpose

Define how versioning works across the three layers of the Kernel-Web ecosystem:

1. **Kernel** — the core framework (this repository)
2. **Application** — the consuming app built on top of Kernel-Web
3. **Extension** — plugins, themes, and layouts distributed through the catalog

This design ensures:

- Extensions declare which kernel versions they are compatible with
- Applications can identify their own version independently from the kernel
- The admin UI surfaces version information and update status
- Install and update flows can block incompatible combinations

---

## 1. Version Sources

### 1a. Kernel Version

**Source of truth:** `VERSION` file at repository root (if present), then `composer.json` → `version` field (fallback).

The `VERSION` file is the quickest source — it's a plain text file with no parsing overhead. If absent or empty, the kernel falls back to `composer.json`. This allows the version to be read without parsing JSON (e.g., during early bootstrap or offline installs).

**Why composer.json:** The kernel is consumed via Composer in production. The composer.json version is the source of truth for package managers. A fallback VERSION file handles edge cases where Composer autoloader is unavailable.

**Reading the version:**

```php
class VersionProvider
{
    public function kernelVersion(): string
    {
        // Fallback: read VERSION file
        $versionFile = __DIR__ . '/../../VERSION';
        if (is_file($versionFile)) {
            $v = trim(file_get_contents($versionFile));
            if ($v !== '') return $v;
        }

        // Primary: read composer.json
        $composer = __DIR__ . '/../../composer.json';
        if (is_file($composer)) {
            $data = json_decode(file_get_contents($composer), true);
            if (isset($data['version'])) return $data['version'];
        }

        return '0.0.0-dev'; // unknown
    }
}
```

### 1b. Application Version

**Source of truth:** Application config or environment variable.

The application sets its own version independently. Kernel-Web does not dictate application versions.

```php
// config/app.php
return [
    'name'    => 'My Application',
    'version' => '1.2.0', // application-specific
];
```

Or via environment:

```php
'app_name' => env('APP_NAME', 'Kernel-Web'),
'app_version' => env('APP_VERSION', '0.0.0-dev'),
```

**Default:** When not configured, the application name defaults to `Kernel-Web` and version to `0.0.0-dev` (unversioned development).

### 1c. Extension Version

**Source of truth:** On-disk manifest file (already exists, no change needed).

| Type | Manifest Path | Version Field |
|------|---------------|---------------|
| Plugin | `lib/plugins/{slug}/plugin.json` | `version` |
| Theme | `lib/themes/{slug}/theme.json` | `version` |
| Layout | `lib/layouts/{slug}/layout.json` | `version` |

This is already established. No change to the version source.

**New field — kernel compatibility:**

Extensions declare which kernel versions they support via a new `requires.kernel` field in their manifest:

```json
{
    "name": "my-extension",
    "version": "1.0.0",
    "requires": {
        "php": "8.1",
        "kernel": ">=2.0.0 <4.0.0"
    }
}
```

**Format:** `kernel` uses a space-separated constraint string (same semantics as Composer's version constraints). Supported operators: `>=`, `>`, `<=`, `<`, `=`, `^`, `~`. Multiple constraints are space-separated (logical AND).

**If omitted:** The extension is assumed compatible with all kernel versions (no restriction). This preserves backward compatibility with existing extensions that don't declare kernel requirements.

---

## 2. Catalog Compatibility Model

### 2a. Existing Schema

The `catalog_extensions` table already has a `requirements` TEXT column storing JSON. This field is used for PHP version requirements (existing). The new `kernel` requirement plugs into this same field.

**Current catalog entry format:**

```json
{
    "PHP": "8.1",
    "kernel": ">=2.0.0 <4.0.0"
}
```

**Constraint format for catalog entries:** Same as manifest — space-separated constraint string.

### 2b. Compatibility Check During Install

When installing an extension, the kernel checks:

1. **PHP version** — existing check (already implemented via `requires.php`)
2. **Kernel version** — NEW: compare `versionProvider->kernelVersion()` against `requirements.kernel`
3. **Extension dependencies** — existing check via `ExtensionDependencyResolver`

**Block if kernel version falls outside the declared range.** Show a clear error:

> "Extension 'My Extension' requires kernel >=2.0.0 <4.0.0. Current kernel version is 1.5.0."

**No catalog change needed.** The existing `requirements` JSON field in `catalog_extensions` is the right place for kernel compatibility data.

---

## 3. Admin Overview — Version Display

### 3a. Admin Landing Page (`/admin`)

The admin landing page should display version information in the stat cards or a dedicated info row:

| Display | Source | Notes |
|---------|--------|-------|
| Kernel version | `VersionProvider::kernelVersion()` | Shown as `vX.Y.Z` |
| Application name + version | `config/app.php` (`name` / `version`) | Shown as `AppName vX.Y.Z` |
| Extension count | Count from `catalog_extensions` (approved, installed) | Total installed extensions |
| Extension updates available | `ExtensionUpdateChecker::checkAll()` | Count of `update_available` items (no blockers) |
| Extension updates blocked | `ExtensionUpdateChecker::checkAll()` | Count of `blocked` items (tooltip on hover) |
| Kernel update status | Compare kernel version to catalog entries where `type='kernel'` (if applicable) | "Update available" badge or "Update check not configured" |
| Extension compatibility warnings | Count of extensions with kernel mismatch warnings | Shown only on admin landing page |

### 3b. Update Status Labels

| Status | Label | Color |
|--------|-------|-------|
| `up_to_date` | "Up-to-date" | Success (green) |
| `update_available` | "Update available" | Warning (yellow) |
| `newer_than_catalog` | "Newer" | Info (blue) |
| `no_catalog_entry` | "No catalog" | Secondary (gray) |
| `blocked` | "Blocked" | Danger (red) with tooltip |
| `invalid` | "Invalid" | Secondary (gray) |

### 3c. "Update Check Not Configured" State

When no remote catalog source is configured (local-only mode), display:

> "Update check not configured — running local catalog only. Set a remote catalog source for automatic update checks."

This message appears next to the kernel version display when:
- No remote sync is configured
- The local catalog is the only source of truth

---

## 4. Kernel Compatibility Check Implementation

### 4a. Version Constraint Comparison

Reuse `ExtensionDependencyResolver`'s existing version constraint logic. It already supports all needed operators via `version_compare()`.

Add a `checkVersionConstraint(string $installed, string $constraint): bool` helper that handles:

- Space-separated constraints (AND logic)
- Single constraints (existing behavior)
- Missing constraints (always return true — backward compatible)

### 4b. Where the Check Runs

| Location | When | Action |
|----------|------|--------|
| `ExtensionsController::handleInstall()` | Before install proceeds | Block if kernel mismatch |
| `PluginLoader::load()` | At boot time | Optional: log warning for incompatible plugins |
| `ExtensionUpdateChecker::check()` | During update check | Include kernel mismatch as a blocker |

### 4c. Warning vs. Block

| Scenario | Behavior |
|----------|----------|
| Install with kernel mismatch | **Block** — show error, refuse to install |
| Enable with kernel mismatch | **Block** — show error, refuse to enable |
| Boot with kernel mismatch | **Warn** — log entry, allow loading (admin can review) |
| Update check shows kernel mismatch | **Warn** — display warning alongside update status |

**Rationale:** Install/enable are admin-triggered actions with clear intent — the admin sees the block message before proceeding. Boot-time mismatches may affect running installations during kernel upgrade — log and warn rather than crash.

---

## 5. Version Provider Service

### Class Design

```php
readonly class VersionProvider
{
    private string $kernelRoot;

    public function __construct(string $kernelRoot)
    {
        $this->kernelRoot = rtrim($kernelRoot, '/');
    }

    public function kernelVersion(): string
    {
        // VERSION file first, then composer.json
    }

    public function kernelName(): string
    {
        // composer.json name field or "Kernel-Web"
    }

    public function appVersion(): string
    {
        // config/app.php['version'] or '0.0.0-dev'
    }

    public function appName(): string
    {
        // config/app.php['name'] or 'Kernel-Web'
    }

    public function checkKernelCompatibility(string $requiredKernel): bool
    {
        // Reuse version_compare logic
    }
}
```

### Registration

Registered in the container during bootstrap:

```php
$container->set('version_provider', new VersionProvider(dirname(__DIR__)));
```

### Testing

- VERSION file present → returns file contents
- composer.json present → returns version field
- Neither present → returns `0.0.0-dev`
- composer.json without version → falls back to `0.0.0-dev`

---

## 6. Admin Overview — Implementation

### 6a. Controller Changes

Add to `ExtensionsController` or a new `AdminController`:

```php
public function index(Container $container): string
{
    $versionProvider = $container->get('version_provider');
    $updateChecker = $container->get('update_checker');

    $stats = [
        'kernelVersion'    => $versionProvider->kernelVersion(),
        'appName'          => $versionProvider->appName(),
        'appVersion'       => $versionProvider->appVersion(),
        'extensionsTotal'  => $catalogRepo->countApproved(),
        'extensionsUpdate' => count(array_filter($updates, fn($u) => $u->status === 'update_available')),
        'extensionsBlocked'=> count(array_filter($updates, fn($u) => $u->status === 'blocked')),
        'updates'          => $updateChecker->checkAll(),
    ];

    return $this->render('admin/index', $stats);
}
```

### 6b. View Changes

Add a version info card to the admin landing page view (`app/Views/admin/index.php`):

```html
<div class="card mb-3">
    <div class="card-body py-2">
        <small class="text-muted">
            <i class="bi bi-box"></i> {{ $appName }} v{{ $appVersion }}
            &middot;
            <i class="bi bi-cube"></i> Kernel v{{ $kernelVersion }}
            @if($extensionsUpdate > 0)
                <span class="badge bg-warning ms-2">{{ $extensionsUpdate }} update{{ $extensionsUpdate > 1 ? 's' : '' }} available</span>
            @endif
            @if($extensionsBlocked > 0)
                <span class="badge bg-danger ms-2" data-bs-toggle="tooltip" title="{{ $extensionsBlocked }} extension(s) blocked by dependencies">{{ $extensionsBlocked }} blocked</span>
            @endif
        </small>
    </div>
</div>
```

### 6c. Remote Update Check Status

When no remote source is configured:

```html
<small class="text-muted ms-2">(Update check not configured)</small>
```

When remote sync is configured:

```html
<small class="text-muted ms-2">Last checked: {{ $lastCheckTime }}</small>
```

---

## 7. Manifest Format Updates

### 7a. New `requires.kernel` Field

```json
{
    "name": "my-plugin",
    "version": "1.0.0",
    "requires": {
        "php": "8.1",
        "kernel": ">=2.0.0 <4.0.0"
    }
}
```

**Field is optional.** If omitted, the extension is assumed compatible with all kernel versions.

### 7b. Manifest Validation

The existing manifest validation (in `PluginManifest` and `ExtensionDependencyResolver`) should:

1. Accept `requires.kernel` as a valid field
2. Validate that it is a non-empty string if present
3. Validate that each constraint uses a recognized operator
4. Reject the manifest if the kernel constraint is malformed

### 7c. Catalog Submission

When submitting an extension to the catalog, the `requirements` JSON should include the kernel constraint:

```json
{
    "PHP": "8.1",
    "kernel": ">=2.0.0 <4.0.0"
}
```

This is stored in `catalog_extensions.requirements` as-is.

---

## 8. Manifest Format Design

### 8a. `requires.kernel` — Kernel Compatibility Constraint

Extensions declare their compatible kernel version range via `requires.kernel` in the manifest:

```json
{
    "requires": {
        "kernel": ">=2.0.0 <4.0.0"
    }
}
```

**Constraint format:** space-separated (AND logic), operators: `>=`, `>`, `<=`, `<`, `=`, `^`, `~`. Same semantics as Composer's version constraints.

**Empty string `""` or missing field** = compatible with all kernel versions (backward compatible).

**Validation:** The constraint value must match a supported version constraint pattern. Malformed constraints are rejected at manifest validation time (manifest becomes "invalid" status).

### 8b. `requires.php` — PHP Version Constraint

Already exists in the manifest. Extensions declare `requires.php` as `"8.1"` (exact version). This is handled independently from `requires.kernel` — both must be satisfied.

### 8c. Application Version Constraint — Not Included

**Decision: No application version constraint.** Extensions consume the kernel layer, not the application layer. Application versions are independent concerns.

**Rationale:**
- The kernel is the stable contract between extensions and the platform
- Applications may run on many kernel versions during their lifecycle
- An extension's compatibility is determined by kernel API stability, not app features
- Apps can still gate their own extensions via app-specific config if needed (out of scope)

### 8d. Catalog Storage

The existing `catalog_extensions.requirements` TEXT column (JSON) stores both PHP and kernel constraints:

```json
{
    "PHP": "8.1",
    "kernel": ">=2.0.0 <4.0.0"
}
```

No new columns or schema changes needed.

---

## 9. Compatibility Checking Design

### 9a. Constraint Parsing

Reuse `ExtensionDependencyResolver::isValidConstraint()` for format validation and `ExtensionDependencyResolver::checkVersionConstraint()` for range checking.

A kernel constraint like `">=2.0.0 <4.0.0"` is parsed as two space-separated constraints (AND logic):

1. `>=2.0.0` — version must be >= 2.0.0
2. `<4.0.0` — version must be < 4.0.0

Both must be satisfied. A single constraint like `"^3.0.0"` is treated as-is (no parsing needed).

**Algorithm:**

```php
public static function checkKernelCompatibility(string $kernelVersion, string $requiredKernel): bool
{
    $requiredKernel = trim($requiredKernel);
    if ($requiredKernel === '') {
        return true; // No constraint = compatible
    }

    // Validate kernel version format
    if (!preg_match('/^\d+\.\d+\.\d+$/', $kernelVersion)) {
        return false; // Unknown kernel version = incompatible
    }

    // Space-separated constraints are AND logic
    $constraints = preg_split('/\s+/', $requiredKernel);
    foreach ($constraints as $constraint) {
        if (!self::checkVersionConstraint($kernelVersion, $constraint)) {
            return false;
        }
    }

    return true;
}
```

### 9b. Install-Time Check — **BLOCK**

**Location:** `ExtensionsController::handleInstall()` — before the staged copy.

**When:** When admin clicks "Install" on a catalog extension.

**Behavior:** Read `requires.kernel` from the catalog entry's `requirements` JSON. If present and non-empty, compare current kernel version against the constraint. Block install with a flash message:

> "Extension 'My Extension' requires kernel >=2.0.0 <4.0.0. Current kernel version is 1.5.0."

**Implementation:** Call the compatibility check after existing dependency checks, before the file copy step.

### 9c. Enable-Time Check — **BLOCK**

**Location:** `ExtensionsController::handleEnable()` — before marking enabled.

**When:** When admin clicks "Enable" on an installed extension.

**Behavior:** Same as install-time check. Read `requires.kernel` from the catalog entry's `requirements` JSON. Block enable with a flash message.

**Rationale:** An admin might manually modify the kernel version (e.g., pull a new kernel release) without reinstalling extensions. The enable gate catches this.

### 9d. Boot-Time Check — **WARN**

**Location:** `PluginLoader::load()` — during plugin discovery, for plugins that are installed/enabled.

**When:** On every kernel boot, for each loaded plugin.

**Behavior:** Read `requires.kernel` from the on-disk manifest. Compare against current kernel version. If mismatched, log a warning:

> "[PluginLoader] Plugin 'my-extension' requires kernel >=2.0.0 <4.0.0 but running v1.5.0."

Allow the plugin to load (don't block). The admin can see the warning in logs and take action.

**Rationale:** Boot-time mismatch may affect running installations during a kernel upgrade. Blocking would break the site. Warning allows graceful degradation while alerting the admin.

### 9e. Update Check — **WARN (displayed)**

**Location:** `ExtensionUpdateChecker::check()` — when computing update status for a catalog extension.

**When:** During `checkAll()` iteration.

**Behavior:** If the extension has `requires.kernel` and the kernel version does not satisfy the constraint, set the update status to `"blocked"` with a `kernel` blocker:

```php
[
    'type'     => 'kernel',
    'message'  => 'Requires kernel >=2.0.0 <4.0.0. Current kernel is v1.5.0.',
    'dependency' => 'kernel',
]
```

The admin sees the extension as "Blocked" in the catalog table with a tooltip showing the kernel mismatch.

---

## 10. Admin UX Design

### 10a. Admin Landing Page — Kernel Compatibility Warning

Add a kernel compatibility row to the version info card when extensions have kernel mismatches:

```html
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <small class="text-muted">
                <i class="bi bi-box"></i> <?= htmlspecialchars($appName) ?> v<?= htmlspecialchars($appVersion) ?>
            </small>
            <small class="text-muted">
                <i class="bi bi-cube"></i> Kernel v<?= htmlspecialchars($kernelVersion) ?>
            </small>
            <?php if ($kernelIncompatibleCount > 0): ?>
                <span class="badge bg-warning text-dark"
                      data-bs-toggle="tooltip"
                      title="<?= $kernelIncompatibleCount ?> extension(s) may be incompatible with this kernel version">
                    <i class="bi bi-exclamation-triangle"></i> <?= $kernelIncompatibleCount ?> incompatible
                </span>
            <?php endif; ?>
            <span class="badge bg-secondary">Update check not configured</span>
        </div>
    </div>
</div>
```

The warning badge only appears when `kernelIncompatibleCount > 0` — counted from all installed extensions.

### 10b. Catalog Table — Kernel Compatibility Badge

Add a "Kernel" column to the catalog table showing kernel compatibility status:

| Status | Badge | Meaning |
|--------|-------|---------|
| Compatible (no constraint) | Green checkmark `✓` or no badge | Extension has no kernel constraint or is compatible |
| Compatible (has constraint) | Green checkmark `✓` | Extension has constraint that is satisfied |
| Incompatible | Orange/yellow exclamation `⚠` | Extension has constraint not satisfied by current kernel |
| No manifest | Gray `—` | Installed extension has no manifest to read |
| Invalid manifest | Red `✕` | Installed extension manifest is invalid |

**Implementation:** In `ExtensionsController::catalog()`, for each catalog entry, compute kernel compatibility using the same logic as the update checker. Pass to view for rendering.

### 10c. Catalog Detail Page — Kernel Compatibility Section

In the extension detail page, add a "Compatibility" section showing:

- PHP requirement (from `requirements.PHP`)
- Kernel requirement (from `requirements.kernel`)
- Current kernel version
- Compatibility result (compatible/incompatible)

For installed extensions, also show on-disk manifest kernel constraint.

### 10d. Extension Update Status — Kernel Mismatch Blocker

When an extension has an available update (catalog version > installed version), but the catalog version's kernel constraint is not satisfied:

- Status: `"blocked"`
- Blocker type: `"kernel"`
- Tooltip: "Requires kernel >=X.Y.Z. Current kernel is vA.B.C."

---

## 11. Service/API Placement

### Decision: Add to `ExtensionDependencyResolver`

The compatibility check belongs in `ExtensionDependencyResolver` because:

1. **It is a constraint check** — `checkVersionConstraint()` already exists and handles the exact algorithm needed
2. **It follows the existing pattern** — `checkInstall()`, `checkEnable()` are on this class; kernel compatibility is another kind of constraint check
3. **`VersionProvider` is for resolution only** — it knows nothing about constraints or catalogs. Adding constraint logic there would violate separation of concerns
4. **Catalog entries store constraints in `requirements` JSON** — the resolver already reads and parses requirements/dependencies from the catalog

### New method signature

```php
/**
 * Check if a kernel version satisfies a kernel compatibility constraint.
 *
 * @param string $kernelVersion Current kernel version (e.g. "3.1.0")
 * @param string $requiredKernel Constraint from requires.kernel (e.g. ">=2.0.0 <4.0.0")
 * @return bool true if compatible
 */
public static function checkKernelCompatibility(string $kernelVersion, string $requiredKernel): bool
```

---

## 12. Phase C Implementation Plan

### Scope

1. **`ExtensionDependencyResolver`** — add `checkKernelCompatibility(string, string): bool` static method
2. **`PluginManifest`** — accept and validate `requires.kernel` in manifest validation (reject malformed constraints)
3. **`ExtensionsController`** — kernel compatibility check in `handleInstall()` and `handleEnable()`
4. **`PluginLoader`** — boot-time warning for loaded plugins with kernel mismatch
5. **`ExtensionUpdateChecker`** — include kernel mismatch as a blocker in update status
6. **Admin UI** — kernel compatibility badge in catalog table, warning badge in admin overview
7. **Tests** — `version_test.php` additions or `kernel_compatibility_test.php`

### Files to modify

| File | Change |
|------|--|
| `app/Services/Extensions/ExtensionDependencyResolver.php` | Add `checkKernelCompatibility()` |
| `app/Core/Plugins/PluginManifest.php` | Validate `requires.kernel` field |
| `app/Controllers/Admin/ExtensionsController.php` | Kernel check in handleInstall/handleEnable |
| `app/Core/Plugins/PluginLoader.php` | Boot-time warning |
| `app/Services/Extensions/ExtensionUpdateChecker.php` | Kernel blocker in checkAll() |
| `app/Views/admin/extensions/catalog.php` | Kernel compatibility column |
| `app/Views/admin/index.php` | Kernel incompatibility warning badge |
| `tests/version_test.php` | Kernel compatibility test cases |

### Test cases

| Test | Expected |
|------|---------|
| `">=2.0.0 <4.0.0"` with kernel `"3.1.0"` | Compatible |
| `">=2.0.0 <4.0.0"` with kernel `"1.5.0"` | Incompatible |
| `"^3.0.0"` with kernel `"3.1.0"` | Compatible |
| `"^3.0.0"` with kernel `"2.9.9"` | Incompatible |
| Empty string `""` | Compatible (any) |
| Missing field | Compatible (any) |
| `"~2.1.0"` with kernel `"2.1.5"` | Compatible |
| `"~2.1.0"` with kernel `"2.2.0"` | Incompatible |
| Malformed constraint `"abc"` | Manifest rejected (invalid) |
| `">=2.0.0 <4.0.0"` with kernel `"dev"` (unknown) | Incompatible |
| `"4.0.0"` with kernel `"4.0.0"` | Compatible (exact match) |
| `"4.0.0"` with kernel `"3.9.9"` | Incompatible (exact mismatch) |

---

## 13. Implementation Plan

### Phase A: VersionProvider Service — Implemented

**Files created:**
- `app/Core/VersionProvider.php` — version source resolution
- Registered in container during bootstrap (`$container->set('version_provider', ...)`)

**Implemented:**
- Kernel version from VERSION file or composer.json (VERSION takes priority)
- Application name/version from config (`name`/`version` or `app_name`/`app_version`)
- `getVersions()` — unified structure for admin overview
- `getKernelName()` — kernel name from composer.json or default
- Unit tests: `tests/version_test.php` (20 assertions covering all resolution paths)

**Deliverable:** `VersionProvider` service with full resolution chain and test coverage.

### Phase B: Admin Overview Display — Implemented

**Files modified:**
- `app/Controllers/Admin/AdminController.php` — pass version data to index view
- `app/Views/admin/index.php` — add version info card
- `config/app.php` — add `version` field (defaults to "dev")
- `public/index.php` — wire VersionProvider into container

**Implemented:**
- Version info card on admin landing page showing kernel version, app name/version
- "Update check not configured" badge (local-only mode)
- Bootstrap 5 compatible, dark-theme friendly
- No remote sync dependency

**Deliverable:** Admin landing page shows kernel version, app version, and update status.

### Phase C: Extension Compatibility Checks — Implemented

**Files modified:**
- `app/Core/Plugins/PluginManifest.php` — validate `requires.kernel` format in constructor
- `app/Services/Extensions/ExtensionDependencyResolver.php` — add `checkKernelCompatibility()`
- `app/Controllers/Admin/ExtensionsController.php` — block install/enable on kernel mismatch
- `app/Services/Extensions/ExtensionUpdateChecker.php` — include kernel mismatch in blockers
- `app/Core/Plugins/PluginLoader.php` — boot-time warning for kernel mismatch
- `tests/version_test.php` — kernel compatibility and manifest validation tests

**Scope:**
- Validate `requires.kernel` in manifest
- Compare installed kernel version against extension requirements
- Block install/enable when kernel version is outside the required range
- Include kernel mismatch as a blocker in update checks
- Warn at boot time for loaded plugins with mismatched kernel requirements

**Deliverable:** Extensions cannot be installed/enabled if their kernel requirements are not met. Update checker surfaces kernel mismatches.

### Phase D: Remote Update Checks

**Out of scope for this design.** This task (remote catalog sync, periodic fetch of extension listings from an external server) is tracked in ROADMAP.md as a Phase 3 item.

The versioning model does not block remote sync — all version data flows through the same fields (`requirements.kernel` in catalog, `catalog_extensions.version` as latest available).

---

## 14. Design Rules

- Kernel version comes from `composer.json` (primary) or `VERSION` file (fallback)
- Application version is set by the consuming application, not the kernel
- Extension kernel compatibility is declared in `requires.kernel` (optional)
- Missing `requires.kernel` means "compatible with all versions" (backward compatible)
- Catalog uses existing `requirements` JSON field — no new database columns
- Install/enable blocks on kernel mismatch; boot-time mismatch warns
- Admin overview shows version info + update status without requiring remote sync
- No new database schema required
- Reuse existing `ExtensionDependencyResolver` for constraint comparison
- Kernel version string must be valid semantic version (or `0.0.0-dev` for unknown)

---

## 15. Deferred

- Kernel version auto-release notes (changelog display on update available)
- Extension version history (past versions of installed extensions)
- Downgrade support (installing an older extension version)
- Signed kernel releases (verification of kernel download integrity)
- Extension signed releases (verification of extension download integrity)
- Semantic versioning enforcement on catalog submission (reject non-semver versions)
- Application-level version bumps (CI/CD integration)

---

## Files to Create / Modify

| File | Action | Phase |
|------|--|------|
| `app/Services/VersionProvider.php` | **Create** | A |
| `app/Controllers/Admin/ExtensionsController.php` | **Modify** — add version stats | B |
| `app/Views/admin/index.php` | **Modify** — add version info card | B |
| `public/index.php` | **Modify** — wire VersionProvider | A |
| `app/Core/Plugins/PluginManifest.php` | **Modify** — validate `requires.kernel` | C |
| `app/Services/Extensions/ExtensionDependencyResolver.php` | **Modify** — add kernel check | C |
| `app/Services/Extensions/ExtensionUpdateChecker.php` | **Modify** — kernel blockers | C |
| `docs/developer/versioning.md` | **Create** — this file | — |
| `DESIGN.md` | **Modify** — add versioning section | — |
| `ROADMAP.md` | **Modify** — add versioning tasks | — |
| `docs/index.md` | **Modify** — add versioning doc link | — |
