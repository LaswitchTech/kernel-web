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

### Catalog Table — Blocked Column

A **Blocked** column shows a red badge with the count of dependency blockers for each extension. Hovering over the badge shows which specific dependencies are blocking actions.

### Lifecycle Blocker Messages

When a lifecycle action is blocked by dependencies, the admin sees:

1. **Flash message** — Red alert listing the extension name and the type of each blocker
2. **Expanded dependency detail** — The expandable row shows each dependency's full status

Example flash messages:

| Action | Example Message |
|--------|----------------|
| **Install** | `Extension "My Plugin" cannot be installed: dependencies not satisfied.<br>Missing: <code>plugin:notes</code><br>Version mismatch: <code>plugin:core-tools</code> |
| **Enable** | `Extension "My Plugin" cannot be enabled: dependencies not satisfied.<br>Missing: <code>plugin:notes</code><br>Missing: <code>theme:default</code> |
| **Disable** | `Extension "Notes" cannot be disabled: dependencies not satisfied.<br>Reverse dependency: <code>plugin:tasks</code> |
| **Uninstall** | `Extension "Notes" cannot be uninstalled: dependencies not satisfied.<br>Reverse dependency: <code>plugin:tasks</code> |

Server-side enforcement remains authoritative — the resolver blocks the action regardless of UI display. The UI messages are informative only.

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

## 11. Runtime Enforcement

The catalog lifecycle blocks install/enable/disable/uninstall actions via `ExtensionDependencyResolver`. However, the catalog can be modified manually (direct DB edits) or files can be copied directly to `/lib/plugins/` without going through the catalog UI. To prevent the kernel from loading plugins with unsatisfied dependencies, **PluginLoader also enforces dependencies at runtime**.

### How It Works

During `PluginLoader::load()`, each discovered plugin undergoes a dependency check:

1. **Parse dependencies** — `ExtensionDependencyResolver::parseDependencies()` normalizes the manifest's dependency declarations
2. **Invalid format** — if parsing returns `null` (unparseable data), the plugin is marked invalid with reason "Invalid dependency format in plugin.json"
3. **Catalog available** — if the catalog table exists, `ExtensionDependencyResolver::checkEnable()` is used with catalog entries as source of truth
4. **Catalog unavailable** — falls back to a simpler check that verifies dependency plugins are already loaded in the registry (no version checking)
5. **Unsatisfied dependency** — the plugin is marked invalid with the first blocker's message. A log entry is emitted via the container logger

### Failure Behavior

| Scenario | Plugin State | Log |
|----------|-------------|-----|
| Invalid dependency format | Invalid | None (format error is self-evident) |
| Dependency not installed | Invalid | Log entry with blocker message |
| Dependency installed but disabled | Invalid | Log entry with blocker message |
| Dependency version mismatch | Invalid | Log entry with version requirement |
| Invalid dependency key format | Invalid | Log entry with key format error |
| Catalog table unavailable | May load (fallback check) | None |

### Fail-Closed Principle

The runtime check follows the same fail-closed principle as the catalog:
- Invalid format → plugin does not load
- Unsatisfied dependency → plugin does not load
- Missing or unavailable catalog → falls back to registry check

### Catalog Unavailable Fallback — Known Limitation

When the catalog table does not exist or the database is unavailable during `PluginLoader::load()`, the resolver falls back to a registry-only check that:

- Validates that dependency keys are valid `type:slug` format
- Checks if the dependency slug is enabled in the registry (by slug match first, then name lookup)
- Does **not** check version constraints
- Does **not** check installed state

This fallback **does not block plugins with unsatisfied dependencies** — it only performs a best-effort check against already-loaded plugins. This is considered acceptable because:

1. The catalog table is created during the kernel's initial database setup, before any plugins are loaded
2. The fallback is only reachable during abnormal conditions (database not yet initialized, corrupt schema)
3. The catalog lifecycle (install/enable) remains fully enforced — the fallback only affects runtime discovery

If the catalog is unavailable and you need to enforce dependencies, ensure the database is properly initialized before plugin loading.

### Diagnostic Information

When a plugin fails the dependency check, the skip reason is recorded in the registry's invalid bucket and can be viewed in the admin UI. The reason includes:
- Which dependency failed
- Why it failed (missing, disabled, version mismatch)

---

---

## 12. PluginLoader Boot Order Design

### Overview

Currently, plugins are loaded in filesystem discovery order (alphabetical via `DirectoryIterator`). This means the order in which plugins register routes, hooks, and services depends entirely on directory naming. If Plugin A depends on Plugin B's services being available, Plugin A must be discovered after Plugin B — a fragile contract.

**Boot order sorting** uses the dependency graph to load plugins in a deterministic, dependency-aware order: dependencies before dependents.

### Graph Model

**Nodes:** Each discovered plugin with a valid manifest and satisfied dependencies is a node.

**Edges:** A directed edge from plugin X to plugin Y means "X depends on Y" — Y must load before X.

**Graph source of truth:** Merged strategy — manifest dependencies define what the plugin *requires*, catalog entries define what is *available*. Both must agree for an edge to exist in the graph.

- If a dependency is declared in the manifest but not installed in the catalog → edge is **excluded** (dependency not available, but handled by `checkDependencies()` as a blocker)
- If a dependency is installed in the catalog but not declared in the manifest → edge is **excluded** (no requirement, no edge needed)
- If both manifest and catalog agree the dependency exists and is enabled → edge is **included**

**Identifier:** `type:slug` format in the graph. For the initial implementation (plugins only), this maps directly to `plugin:<slug>`. The `type:` prefix prevents cross-type collisions and enables future themes/layouts boot ordering.

**Node identity in the graph:** Use the plugin's **slug** (directory name), not manifest name. The slug is guaranteed to match the catalog's `slug` column, which is the canonical unique identifier. Manifest names are not used as graph identifiers because they can change without catalog updates.

### When Sorting Happens

Sorting occurs **between discovery and registry registration**.

Current `load()` flow:
```
discover() → loadOne() (validate + register to registry) → return registry
```

New `load()` flow:
```
discoverAll() → validateAll() → sortByDependencies() → registerAll() → return registry
```

Splitting `loadOne()` into two phases:
1. **Discovery phase** — scan dirs, read manifests, validate JSON, run dependency checks, collect valid/discovered/invalid entries
2. **Sort phase** — build dependency graph from valid entries, run topological sort, reorder registry buckets
3. **Registration phase** — the registry buckets now contain entries in the correct order; `registerRoutes()`, `registerMigrations()`, etc. iterate in dependency order

This separation is necessary because:
- Sorting requires knowing the complete set of valid plugins
- Registry operations (`enable()`, `disable()`) depend on the ordering
- Hooks, routes, and migrations are registered after `load()` returns, so the registry order is what matters

### Topological Sort Algorithm

Use **Kahn's algorithm** (BFS-based topological sort):

1. **Build in-degree map** — for each plugin in the valid set, count how many of its dependencies are also in the valid set
2. **Seed queue** — add all plugins with in-degree 0 (no dependencies within the valid set)
3. **Process queue** — for each plugin popped from the queue:
   - Add it to the sorted result
   - For each dependent of this plugin (plugins that list it as a dependency): decrement their in-degree
   - If a dependent's in-degree reaches 0, add it to the queue
4. **Detect remainder** — any plugins not in the sorted result are part of a cycle

**Tie-breaking:** When multiple plugins have in-degree 0 (no internal dependencies), they are order-independent. To ensure deterministic output, sort tied plugins alphabetically by slug before adding them to the queue.

```php
private function sortByDependencies(array $validPlugins): array
{
    // Build adjacency list and in-degree map
    $inDegree = [];
    $adjacency = []; // dep => [plugins that depend on dep]

    foreach ($validPlugins as $plugin) {
        $slug = $plugin->name(); // or catalog slug
        $inDegree[$slug] = 0;
        $adjacency[$slug] = [];
    }

    foreach ($validPlugins as $plugin) {
        $slug = $plugin->name();
        $deps = ExtensionDependencyResolver::parseDependencies($plugin->dependencies());
        if ($deps === null || $deps === []) continue;

        foreach ($deps as $depKey => $constraint) {
            $parsed = ExtensionDependencyResolver::parseKey($depKey);
            if ($parsed === null) continue;

            $depSlug = $parsed['slug'];
            // Only create edge if the dependency is in the valid set
            if (isset($inDegree[$depSlug])) {
                $inDegree[$slug]++;
                $adjacency[$depSlug][] = $slug;
            }
        }
    }

    // Kahn's algorithm
    $queue = [];
    foreach ($inDegree as $slug => $degree) {
        if ($degree === 0) {
            $queue[] = $slug;
        }
    }
    sort($queue); // Deterministic tie-breaking

    $sorted = [];
    while ($queue !== []) {
        sort($queue); // Re-sort on each iteration for determinism
        $current = array_shift($queue);
        $sorted[] = $current;

        foreach ($adjacency[$current] as $dependent) {
            $inDegree[$dependent]--;
            if ($inDegree[$dependent] === 0) {
                $queue[] = $dependent;
            }
        }
    }

    return $sorted;
}
```

### Circular Dependency Behavior

**Detection:** Kahn's algorithm naturally detects cycles — nodes not included in the sorted result form the cycle. After the sort completes, any plugin with `in-degree > 0` is part of a cycle.

**Example:** If A depends on B and B depends on A:
1. Both start with in-degree 1
2. Queue starts empty
3. Sort result: `[]` (no valid order)
4. Cyclic nodes: `[A, B]`

**Handling:** All plugins in a cycle are **marked invalid** with reason `"Circular dependency detected: [<plugin names>]"`. This is a hard block — no plugin in the cycle loads.

**Rationale:** Boot order is a safety mechanism. A cycle means there is no valid boot order. Rather than picking an arbitrary order (which could cause silent failures), we fail closed.

**Edge case:** If A depends on B (cycle) and C depends on A (no cycle):
1. A and B are cyclic; C depends on A
2. A and B are excluded from sorted result
3. C's dependency on A is an edge to an excluded node — C's in-degree is decremented or its dependency is marked unsatisfied

**Treatment:** C should be marked invalid with reason `"Dependency 'plugin:A' is part of a circular dependency"`. This is because C cannot load without A, and A cannot be ordered.

### Missing Dependency Behavior

**Pre-handled by `checkDependencies()`:** The existing runtime dependency check in `loadOne()` (commit `c328946`) already blocks plugins with unsatisfied dependencies. If a dependency is missing from the catalog or has a version mismatch, the plugin is added to the invalid bucket before the sort phase runs.

**In the sort graph:** Only plugins with **all dependencies satisfied** participate in the graph. A plugin whose dependency exists in the catalog but is not being loaded this time (e.g., filtered out by catalog state) simply doesn't get an edge for that dependency — the missing dependency is already handled by the earlier validation phase.

**If a dependency is excluded from the graph due to being cyclic:**
- The dependent's in-degree in the sort is not incremented for the cyclic dep
- But the dependent is also excluded because its dependency cannot be satisfied
- Treated as: `"Dependency 'plugin:X' is unavailable (circular dependency)"`

### Interaction with Runtime Enforcement (commit c328946)

The two features are **complementary**, not redundant:

| Feature | Purpose | When |
|---------|---------|------|
| **Runtime enforcement** | Blocks loading if dependencies are unsatisfied | Per-plugin during discovery |
| **Boot order sorting** | Ensures plugins load in dependency order | Post-discovery, pre-registration |

**Example scenario without sorting:** Plugin A (depends on B) and Plugin B load in discovery order. Both pass dependency checks because B happens to be alphabetically after A. Routes/hooks/migrations register in wrong order.

**Example scenario with sorting:** Plugin A and B both pass dependency checks. Sort reorders to `[B, A]`. Routes/hooks/migrations register in correct dependency order.

**The existing `checkDependencies()` is the gatekeeper.** The sort is the re-ordering step. Without the sort, `checkDependencies()` only prevents loading — it doesn't fix the order.

### Skipped/Invalid Reasons

Plugins excluded from the sorted result (cyclic or with cyclic dependencies) are added to the invalid bucket:

| Reason | When |
|--------|------|
| `"Circular dependency detected: [A, B]"` | Plugin is part of a cycle |
| `"Dependency 'plugin:A' is part of a circular dependency"` | Plugin's dependency is cyclic |
| `"Missing dependency: plugin:X"` | Handled by existing checkDependencies() |

### Themes and Layouts — Deferred

**Current scope: plugins only.**

**Why defer:**
1. Themes and layouts do not register hooks, routes, or services — they are passive resources
2. Boot order primarily affects code execution (route registration, hook registration, service initialization)
3. A future boot-order concern could be: "theme must load before layout" — but this is not currently required
4. The graph model (`type:slug`) already supports cross-type dependencies; the implementation just needs to filter to `type === 'plugin'` for now

**Future:** When themes/layouts register hooks or services, the same graph model extends naturally by including all `type:slug` nodes.

### Minimum Safe Implementation Slice

After this design, the implementation should be done as:

1. **Phase 1 (smallest safe slice):**
   - Add `PluginLoader::sortByDependencies()`: build graph from valid plugins, run Kahn's algorithm, return sorted slug list
   - In `load()`, after all discovery/validation, call `sortByDependencies()` and reorder the registry's enabled/discovered buckets
   - Mark cyclic nodes as invalid with clear reason
   - Document the behavior

2. **Phase 2 (follow-on):**
   - Validate that hooks, routes, and migrations register in sorted order
   - Test with multi-dependency chains (A → B → C)
   - Add regression test for 3-node cycles

3. **Phase 3 (future):**
   - Extend to themes/layouts
   - Add boot-order diagnostics (log sorted order)

### Implementation — Phase 1 (completed)

The following were implemented in `PluginLoader`:

- `PluginCandidate` — immutable value object holding manifest + effectiveEnabled + catalogState
- `collectPluginCandidates()` — replaces the `loadOne()` loop; validates each plugin, returns `PluginCandidate[]` keyed by name
- `loadOne()` — refactored to return `?PluginCandidate`; invalid plugins are added to registry's invalid bucket
- `sortPluginsByDependencies()` — Kahn's algorithm with alphabetical tie-breaking; detects and reports cyclic nodes
- `markCyclicAsInvalid()` — marks cyclic nodes with descriptive reasons; handles self-cycles vs multi-node cycles
- `registerPluginsInSortedOrder()` — registers candidates in dependency order

Cyclic dependency handling:
- Cyclic nodes are marked invalid first
- Plugins depending on cyclic nodes are also marked invalid in the re-sort phase
- Self-cycles (A → A) and multi-node cycles (A ↔ B ↔ C) are handled identically

### Limitations

- **Discovery order still affects which plugins are in the graph** — if a plugin's manifest can't be read, it's not a node. This is correct: a broken plugin can't participate in the graph.
- **The sort only considers installed/enabled plugins** — disabled plugins are not nodes, even if declared as dependencies. Their absence is handled by the earlier dependency check.
- **Cyclic plugins are entirely blocked** — the algorithm does not try to break cycles. This is intentional: breaking a cycle arbitrarily could load dependencies in the wrong order for the remaining plugins.
- **Determinism** — tie-breaking is alphabetical by slug. This is sufficient for correctness (any valid topological order works) and provides reproducible behavior.

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
- [x] PluginLoader runtime dependency check (uses checkEnable + catalog lookup)
- [ ] Catalog UI: dependency count badges
- [ ] Catalog UI: dependency status on detail page
- [ ] Catalog UI: blocker messages on actions
- [x] Boot order design (Kahn's algorithm, cycle handling, design doc)
- [x] Topological sort for boot order (Kahn's algorithm, alphabetical tie-breaking)
- [x] Circular dependency detection in PluginLoader (Kahn's remainder, cyclic nodes marked invalid)

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
