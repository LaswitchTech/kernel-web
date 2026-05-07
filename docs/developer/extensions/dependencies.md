# Extension Dependency Resolver

## Purpose

Define how Kernel-Web detects, validates, resolves, and enforces dependencies between extensions (plugins, themes, layouts).

## Design Goals

- **Fail closed** — invalid dependency declarations block the extension, not the kernel
- **No remote installs** — all resolution uses local catalog data
- **No code execution** — dependency declarations are data only
- **Type-safe** — dependencies include their type in the key to prevent cross-type collisions
- **Simple** — minimal version constraint syntax for first implementation

---

## 1. Dependency Data Model

### Manifest Format (installed extensions)

Extensions declare dependencies in their manifest as a keyed object where the key includes the type prefix:

```json
{
    "dependencies": {
        "plugin:notes": ">=0.1.0",
        "theme:default": "^1.0.0",
        "layout:panel": "1.2.3"
    }
}
```

**Key format:** `{type}:{slug}` (e.g., `plugin:notes`)
**Value format:** version constraint string (see section 2)

### Catalog Storage

The `catalog_extensions` table already has `dependencies` as a TEXT column storing JSON. Dependencies are stored in normalized format:

```json
{
    "plugin:notes": ">=0.1.0",
    "theme:default": "^1.0.0"
}
```

**Migration note:** Existing catalog entries with flat slug arrays (e.g., `["notes"]`) are kept as-is during parsing but treated as having **no version constraints** (always satisfied if the extension exists).

### Required vs. Optional Dependencies

All dependencies are **required** by default. An extension will not install or enable if any dependency is unsatisfied.

Optional dependencies (future) may be declared separately as `"optional_dependencies": { ... }` and produce warnings rather than blockers.

---

## 2. Version Constraints

Supported constraint syntax for first implementation:

| Syntax | Example | Meaning |
|--------|---------|---------|
| Exact | `1.2.3` | Must match exactly |
| `>=` | `>=1.2.3` | Greater than or equal |
| `>` | `>1.2.3` | Greater than |
| `<=` | `<=1.2.3` | Less than or equal |
| `<` | `<1.2.3` | Less than |
| `^` | `^1.2.3` | Compatible with major (same major, >= given version) |
| `~` | `~1.2.3` | Compatible with patch (same major.minor, >= given version) |

### Version Comparison Algorithm

Use PHP's `version_compare()` which handles semantic versioning natively. For caret and tilde:

- **Caret** (`^1.2.3`): `>=1.2.3` and `<2.0.0`
- **Tilde** (`~1.2.3`): `>=1.2.3` and `<1.3.0`

### Constraint Parsing

If a constraint string contains no operator, treat it as exact match:

```php
function parseConstraint(string $constraint): array {
    $constraint = trim($constraint);
    if (preg_match('/^[\d]/', $constraint)) {
        return ['operator' => '=', 'version' => $constraint];
    }
    // ... extract operator and version
}
```

Fail closed if the constraint is malformed — the extension is considered unsatisfied.

---

## 3. Install Flow

When installing an extension from the catalog:

1. **Check dependencies are in catalog** — if any dependency is not in the catalog, show an error listing the missing dependencies
2. **Check dependencies are approved** — if any dependency has status `pending` or `rejected`, show an error (auto-approve is not performed)
3. **Check dependency versions** — for dependencies that are already installed, compare the installed version against the constraint. If any constraint is violated, block install with a list of violations
4. **Detect circular dependencies** — if installing this extension would create a dependency cycle, block install

### Install Result Structure

```php
InstallResult {
    bool $allowed;
    list<Blocker> $blockers;
}

Blocker {
    string $type;          // 'missing', 'pending', 'rejected', 'version', 'circular'
    string $dependencyKey; // 'plugin:notes'
    string $reason;        // Human-readable description
}
```

### First Implementation: No Auto-Install

Auto-installation of dependencies is **not** implemented in the first version. If a dependency is not in the catalog or not yet installed, the install is blocked and the user is shown which dependencies must be installed first.

---

## 4. Enable Flow

When enabling an extension:

1. **Check dependencies are installed** — dependency must have `is_installed = 1`
2. **Check dependencies are enabled** — dependency must have `is_enabled = 1` (for plugins; themes/layouts don't have enable/disable lifecycle)
3. **Check dependency versions** — compare installed version against constraint

Any blocker blocks the enable action with a clear error message.

---

## 5. Disable / Uninstall Flow

### Disable

- **Cannot disable** if any **enabled** extension depends on this one
- Error message lists the extensions that would be affected

### Uninstall

- **Cannot uninstall** if any **installed** extension (regardless of enable state) depends on this one
- Error message lists the extensions that would break

This is a hard block — no `force` option in the first implementation.

---

## 6. UI Requirements

### Catalog Listing

Dependency count appears as a small badge next to the extension name:

- `0 dependencies` — no badge
- `1 dependency` — small badge with count
- `2+ dependencies` — small badge with count

No dependency details on the listing page — only the count.

### Extension Detail / Review Page

The dependency list is shown in the extension catalog table. A **Dependencies** column displays:

- **Count badge** — number of dependencies, colored by worst status:
  - `bg-success` — all satisfied
  - `bg-warning text-dark` — at least one pending
  - `bg-danger` — at least one missing/version mismatch/rejected
  - `bg-secondary` — no issues but no dependencies
- **Expand icon** — Bootstrap collapse toggles a detail panel inline
- **Detail panel** — table with columns: Dependency, Constraint, Installed, Status

Status badge meanings (detail panel):

| Status | Badge | Color | Meaning |
|--------|-------|-------|---------|
| `satisfied` | Success | green | Dependency found, installed, enabled, version matches constraint |
| `installed` | Primary | blue | Dependency found, installed but not enabled |
| `missing` | Danger | red | Dependency not found in catalog or not installed |
| `pending` | Warning | yellow | Dependency exists but status is pending review |
| `rejected` | Danger | red | Dependency exists but has been rejected |
| `version-mismatch` | Danger | red | Installed version does not satisfy the declared constraint |
| `invalid-key` | Secondary | gray | Dependency key does not match `type:slug` format |
| `invalid-constraint` | Secondary | gray | Constraint value is not a recognized version constraint |
| `malformed` | Danger | red | Dependency declaration is not valid JSON |

No dependencies → shows "None" (muted text).

### Install Action

- If dependencies are all satisfied, install proceeds normally
- If blocked, the install button is replaced with an explanatory message listing unmet dependencies
- Dependencies are shown as clickable links to their catalog entries where available

### Enable Action

- If dependencies are all satisfied, enable proceeds normally
- If blocked, show a non-interactive alert listing unsatisfied dependencies

### Uninstall Action

- If other extensions depend on this one, the uninstall button is hidden
- A notice explains which extensions depend on this one and that it must remain installed

---

## 7. Resolver Service

### Class Design

```php
class ExtensionDependencyResolver
{
    private CatalogExtensionRepository $catalog;
    private string $libPath;
    private ExtensionRegistry $registry;

    public function __construct(
        CatalogExtensionRepository $catalog,
        string $libPath,
        ExtensionRegistry $registry
    ) {}

    // Install checks: dependencies from manifest + catalog lookup
    public function checkInstall(string $type, string $slug, array $dependencies): InstallResult;

    // Enable check: only for already-installed extensions
    public function checkEnable(string $type, string $slug, array $dependencies): InstallResult;

    // Uninstall check: find reverse dependencies
    public function checkUninstall(string $type, string $slug): InstallResult;

    // Disable check: find reverse dependencies among enabled extensions
    public function checkDisable(string $type, string $slug): InstallResult;

    // Circular dependency detection
    public function detectCircular(array $dependencies): bool;
}
```

### Responsibilities

1. **Parse dependency declarations** — normalize `type:slug` format, validate constraint syntax
2. **Resolve dependencies** — look up each dependency in catalog + installed registry
3. **Validate constraints** — compare version strings using `version_compare()`
4. **Detect cycles** — DFS-based circular dependency detection
5. **Find reverse dependencies** — list which extensions depend on a given extension

### Resolution Algorithm

```php
public function checkInstall(string $type, string $slug, array $dependencies): InstallResult
{
    $blockers = [];

    foreach ($dependencies as $depKey => $constraint) {
        [$depType, $depSlug] = $this->parseKey($depKey);

        // 1. Check catalog existence
        $catalogEntry = $this->catalog->findBySlug($depSlug);
        if (!$catalogEntry || $catalogEntry->type !== $depType) {
            $blockers[] = new Blocker('missing', $depKey, "Extension '$depKey' is not in the catalog.");
            continue;
        }

        // 2. Check approved status
        if ($catalogEntry->status !== 'approved') {
            $blockers[] = new Blocker(
                $catalogEntry->status === 'pending' ? 'pending' : 'rejected',
                $depKey,
                "Extension '$depKey' is " . $catalogEntry->status . '.'
            );
            continue;
        }

        // 3. Check if installed and version matches
        $installedVersion = $this->getInstalledVersion($depType, $depSlug);
        if ($installedVersion !== null) {
            if (!$this->checkVersionConstraint($installedVersion, $constraint)) {
                $blockers[] = new Blocker(
                    'version',
                    $depKey,
                    "Requires $constraint but $depSlug $installedVersion is installed."
                );
            }
        }
        // If not installed, step 1 already added a blocker
    }

    // 4. Check for circular dependencies
    $allDeps = $dependencies; // + self's transitive deps for full cycle check
    if ($this->detectCircular($allDeps)) {
        $blockers[] = new Blocker('circular', $slug, 'Circular dependency detected.');
    }

    return new InstallResult(count($blockers) === 0, $blockers);
}
```

### Reverse Dependency Resolution

```php
public function findDependents(string $type, string $slug): array
{
    $key = "$type:$slug";
    $allExtensions = $this->catalog->listAll();
    $dependents = [];

    foreach ($allExtensions as $ext) {
        $deps = $this->parseDependencies($ext);
        foreach ($deps as $depKey => $_) {
            if ($depKey === $key) {
                $dependents[] = ['name' => $ext->name, 'slug' => $ext->slug];
            }
        }
    }

    return $dependents;
}
```

---

## 8. Security / Safety

### No Remote Auto-Install

Dependencies are never automatically installed. The resolver reports missing dependencies and the user must install them manually.

### No Code Execution During Resolution

Dependency declarations are treated as data. No `eval()`, `include`, or dynamic class loading. The resolver only reads JSON data and performs string comparisons.

### Fail Closed

- Invalid dependency format → extension blocks install/enable
- Malformed version constraint → treated as unsatisfied
- Missing catalog entry for a dependency → block with clear error
- Unresolvable dependency type prefix → block

### Dependency Format Validation

During catalog submission and manifest loading, dependency declarations are validated:

- Keys must match `^[a-z]+:[a-z][a-z0-9_-]*$`
- Values must be non-empty strings (constraint syntax validated loosely — only that it's a string)
- Empty `dependencies: {}` or `dependencies: []` → no dependencies (valid)

---

## 9. Manifest Key Standardization

The dependency key in `plugin.json` should use the `type:slug` format:

```json
"dependencies": {
    "plugin:notes": ">=0.1.0"
}
```

**Why `type:slug` over plain `name`?**

| Approach | Pros | Cons |
|----------|------|------|
| Plain name | Simple, matches existing `PluginLoader` behavior | Name may not be unique across types; name can change without catalog update |
| Plain slug | Unique in catalog; shorter | No type information; assumes slug matches manifest name |
| **type:slug** (chosen) | Explicit type; unique; human-readable | Slightly more verbose; requires parsing |

The `type:slug` format is the safest choice because:
1. Slug is guaranteed unique across the catalog (DB constraint)
2. Type prefix prevents cross-type collisions (e.g., a plugin and theme both named "default")
3. Display name can change without breaking dependency resolution
4. Matches the catalog's `type` + `slug` columns directly

**Migration note:** Existing plugins that use plain names in `dependencies` will have a compatibility layer — the resolver attempts to resolve plain names by looking up the name as a slug first, then falls back to a warning.

---

## 10. Implementation Checklist

### Phase 2 (this design)

- [x] Design document (this file)
- [x] Update `DESIGN.md` with dependency resolver section
- [x] Update `ROADMAP.md` — mark task as in-progress
- [x] Update `plugins.md` dependency format
- [x] Update `scaffolds.md` dependency example
- [x] Update `extensions-management.md` deferred list

### Future (implementation)

- [x] `ExtensionDependencyResolver` service class
- [x] Resolver integration in `ExtensionsController` (install/enable/disable/uninstall)
- [x] Harden: null-parse guard, circular detection key collision fix
- [x] Catalog submission validation for dependency format
- [ ] Catalog UI: dependency count badges
- [ ] Catalog UI: dependency status on detail page
- [ ] Catalog UI: blocker messages on actions
- [ ] PluginLoader version check (currently only checks name)
- [ ] Topological sort for boot order (currently discovery-order dependent)
- [ ] Circular dependency detection in PluginLoader

---

## Appendix: Circular Dependency Detection (DFS)

```php
private function detectCircular(array $dependencies, ?array $visited = null, ?array $stack = null): bool
{
    $visited = $visited ?? [];
    $stack = $stack ?? [];

    foreach ($dependencies as $depKey => $_) {
        [$depType, $depSlug] = explode(':', $depKey, 2);
        $depName = $depSlug; // simplified for graph node

        if (in_array($depName, $stack, true)) {
            return true; // cycle found
        }

        if (!in_array($depName, $visited, true)) {
            $visited[] = $depName;
            $stack[] = $depName;

            $depManifest = $this->getManifest($depType, $depSlug);
            if ($depManifest && isset($depManifest['dependencies'])) {
                if ($this->detectCircular($depManifest['dependencies'], $visited, $stack)) {
                    return true;
                }
            }

            array_pop($stack);
        }
    }

    return false;
}
```
