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

## 8. Implementation Plan

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

### Phase C: Extension Compatibility Checks

**Files to modify:**
- `app/Core/Plugins/PluginManifest.php` — accept and validate `requires.kernel`
- `app/Services/Extensions/ExtensionDependencyResolver.php` — add `checkKernelCompatibility()`
- `app/Controllers/Admin/ExtensionsController.php` — block install/enable on kernel mismatch
- `app/Services/Extensions/ExtensionUpdateChecker.php` — include kernel mismatch in blockers
- Manifest validation tests

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

## 9. Design Rules

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

## 10. Deferred

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
