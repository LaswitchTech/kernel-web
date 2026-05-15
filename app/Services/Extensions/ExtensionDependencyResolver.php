<?php

namespace App\Services\Extensions;

/**
 * Extension Dependency Resolver.
 *
 * Detects, validates, and enforces dependencies between extensions
 * (plugins, themes, layouts). All resolution uses the catalog as source of truth.
 *
 * Dependency format:
 *   {"plugin:notes": ">=0.1.0", "theme:default": "^1.0.0"}
 *
 * Key: {type}:{slug}
 * Value: version constraint string
 *
 * Fail closed on any invalid data.
 */
class ExtensionDependencyResolver
{
    private const ALLOWED_TYPES = ['plugin', 'theme', 'layout'];

    private const SLUG_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    // ---------------------------------------------------------------------
    // Public API
    /**
     * Validate a dependency key in type:slug format.
     *
     * Returns true if the key is a valid "type:slug" where type is one of
     * plugin/theme/layout and slug matches ^[a-z][a-z0-9_-]*$.
     */
    public static function isValidDependencyKey(string $key): bool
    {
        $parsed = self::parseKey($key);
        return $parsed !== null;
    }

    /**
     * Validate a version constraint string.
     *
     * Accepted formats:
     *   - Exact: 1.2.3
     *   - >=, >, <=, < prefixed: >=1.0.0
     *   - Caret: ^1.2.3
     *   - Tilde: ~1.2.3
     *   - Empty string (always valid, means "any")
     *
     * Returns false for malformed constraints (e.g. invalid operator,
     * non-semver version, or any other unrecognized format).
     */
    public static function isValidConstraint(string $constraint): bool
    {
        $c = trim($constraint);
        if ($c === '') {
            return true;
        }

        // Caret
        if (preg_match('/^\^(.+)$/', $c, $m)) {
            return self::isValidVersion($m[1]);
        }

        // Tilde
        if (preg_match('/^\~(.+)$/', $c, $m)) {
            return self::isValidVersion($m[1]);
        }

        // Comparison operator
        if (preg_match('/^(>=|>|<=|<)(.+)$/', $c, $m)) {
            return self::isValidVersion($m[2]);
        }

        // Exact version
        return self::isValidVersion($c);
    }

    /**
     * Validate a dependency map (array or JSON string).
     *
     * Accepted values:
     *   - null, empty string, empty array, "{}" → valid (no dependencies)
     *   - Non-empty JSON object with string keys and string values → validated per-key
     *
     * Rejected:
     *   - Non-empty string that is not valid JSON
     *   - JSON array (flat list)
     *   - Non-object JSON (e.g. number, boolean)
     *   - Key not matching type:slug format
     *   - Constraint not matching a supported version constraint format
     *   - Non-string constraint value
     *
     * Returns list of human-readable error messages. Empty array means valid.
     *
     * @param string|mixed $value
     * @return string[]
     */
    public static function validateDependencyMap($value): array
    {
        $errors = [];

        // Empty / null / empty array → valid
        if ($value === null) {
            return $errors;
        }
        if (is_array($value) && $value === []) {
            return $errors;
        }
        if (is_string($value) && trim($value) === '') {
            return $errors;
        }

        if (is_array($value)) {
            return self::validateDependencyMapFromArray($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                // Non-object JSON (array, number, boolean, etc.) or invalid JSON
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'Dependencies must be a valid JSON object (e.g. {"plugin:notes": ">=0.1.0"}) or left empty.';
                } else {
                    $errors[] = 'Dependencies must be a JSON object, not a list. Use {"type:slug": "constraint"} format.';
                }
                return $errors;
            }
            return self::validateDependencyMapFromArray($decoded);
        }

        $errors[] = 'Dependencies must be a JSON object or left empty.';
        return $errors;
    }

    /**
     * @param array $deps
     * @return string[]
     */
    private static function validateDependencyMapFromArray(array $deps): array
    {
        $errors = [];

        foreach ($deps as $key => $constraint) {
            // Key must be a non-empty string
            if (!is_string($key) || $key === '') {
                $errors[] = "Dependency key must be a non-empty string in 'type:slug' format.";
                continue;
            }

            // Key format: type:slug
            if (!self::isValidDependencyKey($key)) {
                $errors[] = "Invalid dependency key '{$key}'. Must be 'type:slug' where type is plugin/theme/layout and slug matches ^[a-z][a-z0-9_-]*\$.";
                continue;
            }

            // Constraint must be a non-empty string
            if (!is_string($constraint)) {
                $errors[] = "Dependency '{$key}' constraint must be a string, not " . gettype($constraint) . ".";
                continue;
            }

            // Validate constraint format
            if (!self::isValidConstraint($constraint)) {
                $errors[] = "Dependency '{$key}' has an invalid constraint '{$constraint}'. Supported: exact (1.2.3), >=, >, <=, <, ^ (caret), ~ (tilde).";
                continue;
            }
        }

        return $errors;
    }

    // ---------------------------------------------------------------------

    /**
     * Check dependencies for install action.
     *
     * Blocks if:
     *   - dependency declaration is invalid
     *   - dependency is missing from catalog
     *   - dependency is not approved
     *   - dependency version constraint not satisfied
     *
     * @param array<string, string> $dependencies Dependency map: "type:slug" => constraint
     * @param array<int, array> $allCatalog All catalog entries for lookup
     * @param string $thisSlug Slug of the extension being installed (for circular detection)
     * @return array{allowed: bool, blockers: array<int, array{type: string, message: string, dependency: string}>}
     */
    public static function checkInstall(array $dependencies, array $allCatalog, string $thisSlug): array
    {
        $blockers = [];

        foreach ($dependencies as $depKey => $constraint) {
            $parsed = self::parseKey($depKey);
            if ($parsed === null) {
                $blockers[] = ['type' => 'invalid', 'message' => "Invalid dependency key: '{$depKey}'.", 'dependency' => $depKey];
                continue;
            }

            $depType = $parsed['type'];
            $depSlug = $parsed['slug'];

            // Find dependency in catalog
            $catalogEntry = null;
            foreach ($allCatalog as $ext) {
                if ($ext['type'] === $depType && $ext['slug'] === $depSlug) {
                    $catalogEntry = $ext;
                    break;
                }
            }

            if ($catalogEntry === null) {
                $blockers[] = ['type' => 'missing', 'message' => "Extension '{$depKey}' is not in the catalog.", 'dependency' => $depKey];
                continue;
            }

            if ($catalogEntry['status'] !== 'approved') {
                $blockers[] = ['type' => $catalogEntry['status'], 'message' => "Extension '{$depKey}' is {$catalogEntry['status']}.", 'dependency' => $depKey];
                continue;
            }

            // If version constraint exists and dependency is installed, check version
            if ($constraint !== '' && (int) $catalogEntry['is_installed'] == 1) {
                $installedVersion = $catalogEntry['version'] ?? '0.0.0';
                if (!self::checkVersionConstraint($installedVersion, $constraint)) {
                    $blockers[] = [
                        'type'     => 'version',
                        'message'  => "Requires {$depKey} {$constraint}, but {$catalogEntry['version']} is installed.",
                        'dependency' => $depKey,
                    ];
                }
            }
        }

        // Circular dependency check: only among deps that exist in the catalog.
        $catalogKeys = [];
        foreach ($dependencies as $depKey => $_) {
            $parsed = self::parseKey($depKey);
            if ($parsed === null) {
                continue; // Invalid key already added as blocker
            }
            $depType = $parsed['type'];
            $depSlug = $parsed['slug'];
            // Check if this dep exists in catalog
            $found = false;
            foreach ($allCatalog as $ext) {
                if ($ext['type'] === $depType && $ext['slug'] === $depSlug) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $catalogKeys[] = $depKey;
            }
        }

        if ($catalogKeys !== []) {
            // Build full reachable subgraph from installer's direct deps
            // (includes transitive deps that exist in catalog)
            $reachable = self::buildReachableKeys($catalogKeys, $allCatalog);
            if (self::detectCircularInSet($reachable, $allCatalog)) {
                $blockers[] = ['type' => 'circular', 'message' => 'Circular dependency detected.', 'dependency' => $thisSlug];
            }
        }

        return ['allowed' => count($blockers) === 0, 'blockers' => $blockers];
    }

    /**
     * Check dependencies for enable action.
     *
     * Blocks if:
     *   - dependency is missing
     *   - dependency is not installed
     *   - dependency is not enabled
     *   - dependency version constraint not satisfied
     *
     * @param array<string, string> $dependencies Dependency map: "type:slug" => constraint
     * @param array<int, array> $allCatalog All catalog entries for lookup
     * @return array{allowed: bool, blockers: array<int, array{type: string, message: string, dependency: string}>}
     */
    public static function checkEnable(array $dependencies, array $allCatalog): array
    {
        $blockers = [];

        foreach ($dependencies as $depKey => $constraint) {
            $parsed = self::parseKey($depKey);
            if ($parsed === null) {
                $blockers[] = ['type' => 'invalid', 'message' => "Invalid dependency key: '{$depKey}'.", 'dependency' => $depKey];
                continue;
            }

            $depType = $parsed['type'];
            $depSlug = $parsed['slug'];

            // Find dependency in catalog
            $depRecord = null;
            foreach ($allCatalog as $ext) {
                if ($ext['type'] === $depType && $ext['slug'] === $depSlug) {
                    $depRecord = $ext;
                    break;
                }
            }

            if ($depRecord === null) {
                $blockers[] = ['type' => 'missing', 'message' => "Extension '{$depKey}' is not installed.", 'dependency' => $depKey];
                continue;
            }

            if ((int) $depRecord['is_installed'] !== 1) {
                $blockers[] = ['type' => 'not_installed', 'message' => "Extension '{$depKey}' is not installed.", 'dependency' => $depKey];
                continue;
            }

            if ((int) $depRecord['is_enabled'] !== 1) {
                $blockers[] = ['type' => 'not_enabled', 'message' => "Extension '{$depKey}' is installed but not enabled.", 'dependency' => $depKey];
                continue;
            }

            // Version constraint check
            if ($constraint !== '' && (int) $depRecord['is_installed'] == 1) {
                $installedVersion = $depRecord['version'] ?? '0.0.0';
                if (!self::checkVersionConstraint($installedVersion, $constraint)) {
                    $blockers[] = [
                        'type'     => 'version',
                        'message'  => "Requires {$depKey} {$constraint}, but {$depRecord['version']} is installed.",
                        'dependency' => $depKey,
                    ];
                }
            }
        }

        return ['allowed' => count($blockers) === 0, 'blockers' => $blockers];
    }

    /**
     * Check if an extension can be disabled.
     *
     * Blocks if another enabled extension depends on this one.
     *
     * @param array<string, string> $dependencies Dependency map of this extension
     * @param array<int, array> $allCatalog All catalog entries for lookup
     * @param string $thisType Type of the extension being disabled
     * @param string $thisSlug Slug of the extension being disabled
     * @return array{allowed: bool, blockers: array<int, array{type: string, message: string, dependency: string}>}
     */
    public static function checkDisable(array $dependencies, array $allCatalog, string $thisType, string $thisSlug): array
    {
        return self::findDependentsByStatus($allCatalog, $thisType, $thisSlug, 'enabled');
    }

    /**
     * Check if an extension can be uninstalled.
     *
     * Blocks if another installed extension depends on this one.
     *
     * @param array<string, string> $dependencies Dependency map of this extension
     * @param array<int, array> $allCatalog All catalog entries for lookup
     * @param string $thisType Type of the extension being uninstalled
     * @param string $thisSlug Slug of the extension being uninstalled
     * @return array{allowed: bool, blockers: array<int, array{type: string, message: string, dependency: string}>}
     */
    public static function checkUninstall(array $dependencies, array $allCatalog, string $thisType, string $thisSlug): array
    {
        return self::findDependentsByStatus($allCatalog, $thisType, $thisSlug, 'installed');
    }

    /**
     * Parse a dependency declaration and return normalized keyed format.
     *
     * Handles both the new keyed format {"plugin:notes": ">=0.1.0"}
     * and legacy flat format ["notes"] (converted to no-constraint keys).
     *
     * Returns [] for null, empty string, empty array, or valid empty JSON.
     * Returns null ONLY if the value is a non-empty string that contains
     * unparseable (non-JSON) data — callers MUST treat null as invalid.
     *
     * @param string|mixed $value
     * @return array<string, string>|null null means invalid/unparseable
     */
    public static function parseDependencies($value): ?array
    {
        if (is_array($value)) {
            return self::normalizeArrayDeps($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return null;
            }
            return self::normalizeArrayDeps($decoded);
        }

        return [];
    }

    /**
     * Validate a dependency key in type:slug format.
     *
     * @param string $key
     * @return array{type: string, slug: string}|null
     */
    public static function parseKey(string $key): ?array
    {
        if (!preg_match('/^([a-z]+):([a-z][a-z0-9_-]*)$/', $key, $matches)) {
            return null;
        }

        $type = $matches[1];
        $slug = $matches[2];

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }

        return ['type' => $type, 'slug' => $slug];
    }

    /**
     * Check if an installed version satisfies a version constraint.
     *
     * Supports: exact, >=, >, <=, <, ^ (caret), ~ (tilde)
     * Fails closed on malformed versions or constraints.
     */
    public static function checkVersionConstraint(string $installedVersion, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '') {
            return true;
        }

        // Validate installed version is a valid semver
        if (!preg_match('/^\d+\.\d+\.\d+$/', $installedVersion)) {
            return false;
        }

        if (preg_match('/^\^(.+)$/', $constraint, $matches)) {
            $version = $matches[1];
            if (!self::isValidVersion($version)) {
                return false;
            }
            // ^X.Y.Z: >=X.Y.Z and <(X+1).0.0
            $upper = self::getUpperBound($version);
            if ($upper === null) {
                return false;
            }
            return version_compare($installedVersion, $version, '>=') && version_compare($installedVersion, $upper, '<');
        }

        if (preg_match('/^\~(.+)$/', $constraint, $matches)) {
            $version = $matches[1];
            if (!self::isValidVersion($version)) {
                return false;
            }
            // ~X.Y.Z: >=X.Y.Z and <X.(Y+1).0
            $upper = self::getTildeUpper($version);
            if ($upper === null) {
                return false;
            }
            return version_compare($installedVersion, $version, '>=') && version_compare($installedVersion, $upper, '<');
        }

        if (preg_match('/^(>=|>|<=|<)(.+)$/', $constraint, $matches)) {
            $op = $matches[1];
            $version = $matches[2];
            if (!self::isValidVersion($version)) {
                return false;
            }
            return version_compare($installedVersion, $version, $op);
        }

        // Exact match
        if (!self::isValidVersion($constraint)) {
            return false;
        }
        return version_compare($installedVersion, $constraint, '=');
    }

    /**
     * Build the set of all keys reachable from a set of seed keys.
     *
     * Follows dependencies transitively as long as each dep exists in the catalog.
     *
     * @param array<int, string> $seeds Starting keys
     * @param array<int, array> $catalog
     * @return array<string, string> All reachable keys
     */
    private static function buildReachableKeys(array $seeds, array $catalog): array
    {
        $reachable = [];
        $queue = $seeds;

        while ($queue !== []) {
            $key = array_shift($queue);
            if (isset($reachable[$key])) {
                continue;
            }
            $reachable[$key] = '';

            $parsed = self::parseKey($key);
            if ($parsed === null) {
                continue;
            }

            $depType = $parsed['type'];
            $depSlug = $parsed['slug'];

            // Find this key in catalog
            $depRecord = null;
            foreach ($catalog as $ext) {
                if ($ext['type'] === $depType && $ext['slug'] === $depSlug) {
                    $depRecord = $ext;
                    break;
                }
            }

            if ($depRecord === null) {
                continue;
            }

            $depDeps = self::parseDependencies($depRecord['dependencies'] ?? '[]');
            if ($depDeps === null) {
                continue;
            }

            foreach ($depDeps as $depKey => $_) {
                if (!isset($reachable[$depKey])) {
                    $queue[] = $depKey;
                }
            }
        }

        return $reachable;
    }

    /**
     * Detect circular dependencies in a set of catalog-resolvable keys.
     *
     * This is a pure detection method — it never adds blockers.
     * Only traverses keys that exist in the catalog.
     *
     * @param array<string, string> $keys Keys known to exist in the catalog
     * @param array<int, array> $catalog
     * @return bool true if circular dependency found
     */
    private static function detectCircularInSet(array $keys, array $catalog): bool
    {
        return self::detectCircularHelper($keys, $catalog, array_keys($keys), [], null);
    }

    /**
     * Detect circular dependencies among a set of catalog-resolvable keys only.
     *
     * This is a pure detection method — it never adds blockers.
     * Only traverses dependencies that exist in the catalog.
     *
     * @param array<string, string> $deps Dependency map (only keys that exist in catalog)
     * @param array<int, array> $catalog
     * @param string|null $targetKey If set, check for cycles passing through this key
     * @return bool true if circular dependency found
     */
    private static function detectCircularWithTarget(array $deps, array $catalog, ?string $targetKey = null): bool
    {
        if ($targetKey !== null) {
            // Start with target first so we detect cycles passing through it
            $targets = [$targetKey];
            $targets = array_merge($targets, array_keys($deps));
        } else {
            $targets = array_keys($deps);
        }
        return self::detectCircularHelper($deps, $catalog, $targets, [], $targetKey);
    }

    /**
     * DFS helper for circular dependency detection.
     *
     * @param array<string, string> $deps All known dependencies
     * @param array<int, array> $catalog
     * @param array<int, string> $targets Remaining keys to process
     * @param array<int, string> $visited
     * @param string|null $targetKey If set, check for cycles through this key
     * @return bool true if circular dependency found
     */
    private static function detectCircularHelper(array $deps, array $catalog, array $targets, array $visited, ?string $targetKey = null): bool
    {
        if ($targets === []) {
            return false;
        }

        $key = array_shift($targets);
        if (in_array($key, $visited, true)) {
            return self::detectCircularHelper($deps, $catalog, $targets, $visited, $targetKey);
        }

        $visited[] = $key;

        $parsed = self::parseKey($key);
        if ($parsed === null) {
            return self::detectCircularHelper($deps, $catalog, $targets, $visited, $targetKey);
        }

        $depType = $parsed['type'];
        $depSlug = $parsed['slug'];

        // Find this dependency in catalog
        $depRecord = null;
        foreach ($catalog as $ext) {
            if ($ext['type'] === $depType && $ext['slug'] === $depSlug) {
                $depRecord = $ext;
                break;
            }
        }

        if ($depRecord === null) {
            return self::detectCircularHelper($deps, $catalog, $targets, $visited, $targetKey);
        }

        $depDeps = self::parseDependencies($depRecord['dependencies'] ?? '[]');
        if ($depDeps === null) {
            return self::detectCircularHelper($deps, $catalog, $targets, $visited, $targetKey);
        }

        // Check for cycles: any dep that points back to a key in the visited set
        foreach ($depDeps as $depKey => $_) {
            if (in_array($depKey, $visited, true)) {
                return true;
            }
            // Only follow if this dep is in our known set
            if (isset($deps[$depKey])) {
                $targets[] = $depKey;
            }
        }

        // Also check if any dep points to the installer (target) for installer-specific cycles
        if ($targetKey !== null) {
            foreach ($depDeps as $depKey => $_) {
                if ($depKey === $targetKey) {
                    return true;
                }
            }
        }

        return self::detectCircularHelper($deps, $catalog, $targets, $visited);
    }

    /**
     * Find all extensions that depend on a given extension.
     *
     * @param array<int, array> $catalog All catalog entries
     * @param string $type
     * @param string $slug
     * @param string $statusFilter 'installed' or 'enabled'
     * @return array<int, array{name: string, slug: string, status: string}>
     */
    public static function findDependents(array $catalog, string $type, string $slug, string $statusFilter = 'installed'): array
    {
        $key = "{$type}:{$slug}";
        $dependents = [];

        foreach ($catalog as $ext) {
            if ((int) $ext['is_installed'] !== 1) {
                continue;
            }

            $deps = self::parseDependencies($ext['dependencies'] ?? '[]');
            if ($deps === null) {
                continue;
            }

            foreach ($deps as $depKey => $_) {
                if ($depKey === $key) {
                    $dependents[] = [
                        'name'   => $ext['name'],
                        'slug'   => $ext['slug'],
                        'status' => (int) $ext['is_enabled'] === 1 ? 'enabled' : 'installed',
                    ];
                    break;
                }
            }
        }

        return $dependents;
    }

    // ------ Kernel compatibility

    /**
     * Check if a kernel version satisfies a kernel compatibility constraint.
     *
     * Supports the same constraint formats as checkVersionConstraint() (exact,
     * >=, >, <=, <, ^, ~) plus space-separated AND logic for compound ranges.
     *
     * Returns true for empty/missing constraints (compatible with all).
     * Returns false for malformed kernel versions or unsatisfied constraints.
     */
    public static function checkKernelCompatibility(string $kernelVersion, ?string $requiredKernel = null): bool
    {
        $requiredKernel = trim($requiredKernel ?? '');
        if ($requiredKernel === '') {
            return true;
        }

        // Validate kernel version format
        if (!preg_match('/^\d+\.\d+\.\d+$/', $kernelVersion)) {
            return false;
        }

        // Space-separated constraints are AND logic
        $constraints = preg_split('/\s+/', $requiredKernel);
        if ($constraints === false || $constraints === []) {
            return false;
        }

        foreach ($constraints as $constraint) {
            if (!self::checkVersionConstraint($kernelVersion, $constraint)) {
                return false;
            }
        }

        return true;
    }

    // ------ Internal helpers
    // --

    private static function isValidVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }

    /**
     * Get upper bound for caret constraint.
     *
     * ^1.2.3 → 2.0.0, ^0.2.3 → 0.3.0, ^0.0.3 → 0.0.4
     */
    private static function getUpperBound(string $version): ?string
    {
        $parts = explode('.', $version);
        if (count($parts) !== 3) {
            return null;
        }
        [$major, $minor, $patch] = $parts;
        if (!is_numeric($major) || !is_numeric($minor) || !is_numeric($patch)) {
            return null;
        }

        if ((int) $major > 0) {
            return ($major + 1) . '.0.0';
        }
        if ((int) $minor > 0) {
            return '0.' . ($minor + 1) . '.0';
        }
        return '0.0.' . ((int) $patch + 1);
    }

    /**
     * Get upper bound for tilde constraint.
     *
     * ~1.2.3 → 1.3.0
     */
    private static function getTildeUpper(string $version): ?string
    {
        $parts = explode('.', $version);
        if (count($parts) !== 3) {
            return null;
        }
        [$major, $minor, $patch] = $parts;
        if (!is_numeric($major) || !is_numeric($minor)) {
            return null;
        }
        return $major . '.' . ((int) $minor + 1) . '.0';
    }

    /**
     * Normalize a parsed dependency array to keyed format.
     *
     * If keys contain ':', they are type:slug format.
     * If keys are flat values, they are converted to no-constraint keys.
     *
     * @param array $deps
     * @return array<string, string>
     */
    private static function normalizeArrayDeps(array $deps): array
    {
        $result = [];
        foreach ($deps as $key => $value) {
            if (is_string($key) && strpos($key, ':') !== false) {
                // Already keyed: "plugin:notes" => ">=0.1.0"
                $result[$key] = is_string($value) ? $value : '';
            } elseif (is_string($value) && strpos($value, ':') !== false) {
                // Flat array where value contains ':' — treat as keyed
                $result[$value] = '';
            } else {
                // Flat array: ["notes", "theme-default"]
                $item = is_string($key) ? $key : $value;
                if (strpos($item, ':') !== false) {
                    $result[$item] = '';
                } else {
                    // Legacy: plain name, no type info
                    $result[$item] = '';
                }
            }
        }
        return $result;
    }

    /**
     * DFS-based circular dependency detection.
     *
     * @param array<string, string> $allDeps
     * @param array<int, array> $catalog
     * @param string|null $currentKey
     * @param array<int, string> $visited
     * @param array<int, string> $stack
     * @param string|null $skipKey Key to skip (e.g. temp self-reference key)
     * @return bool true if circular dependency found
     */
    private static function hasCircularDependency(array $allDeps, array $catalog, ?string $currentKey = null, array $visited = [], array $stack = [], ?string $skipKey = null): bool
    {
        if ($currentKey === null) {
            $firstKey = array_key_first($allDeps);
            if ($firstKey === null) {
                return false;
            }
            $currentKey = $firstKey;
        }

        // Skip artificial keys (e.g. temp self-reference for circular check)
        if ($skipKey !== null && $currentKey === $skipKey) {
            return false;
        }

        $parsed = self::parseKey($currentKey);
        if ($parsed === null) {
            return false;
        }

        [$depType, $depSlug] = $parsed;

        // Cycle found
        if (in_array($currentKey, $stack, true)) {
            return true;
        }

        // Already visited
        if (in_array($currentKey, $visited, true)) {
            return false;
        }

        $visited[] = $currentKey;
        $stack[] = $currentKey;

        // Get dependencies of this dependency from catalog
        $depRecord = null;
        foreach ($catalog as $ext) {
            if ($ext['type'] === $depType && $ext['slug'] === $depSlug) {
                $depRecord = $ext;
                break;
            }
        }

        if ($depRecord === null) {
            array_pop($stack);
            return false;
        }

        $depDeps = self::parseDependencies($depRecord['dependencies'] ?? '[]');
        if ($depDeps === null) {
            array_pop($stack);
            return false;
        }

        foreach ($depDeps as $depKey => $_) {
            if ($depKey === $currentKey) {
                continue; // Skip self
            }
            if (self::hasCircularDependency($allDeps, $catalog, $depKey, $visited, $stack, $skipKey)) {
                return true;
            }
        }

        array_pop($stack);
        return false;
    }

    /**
     * Find dependents that block disable/uninstall.
     *
     * @param array<int, array> $catalog
     * @param string $thisType
     * @param string $thisSlug
     * @param string $statusFilter 'enabled' or 'installed'
     * @return array{allowed: bool, blockers: array<int, array{type: string, message: string, dependency: string}>}
     */
    private static function findDependentsByStatus(array $catalog, string $thisType, string $thisSlug, string $statusFilter): array
    {
        $key = "{$thisType}:{$thisSlug}";
        $dependents = [];

        foreach ($catalog as $ext) {
            // Skip self
            if ($ext['type'] === $thisType && $ext['slug'] === $thisSlug) {
                continue;
            }

            $isInstalled = (int) $ext['is_installed'] === 1;
            $isEnabled = (int) $ext['is_enabled'] === 1;
            $isDependent = false;

            $deps = self::parseDependencies($ext['dependencies'] ?? '[]');
            if ($deps === null) {
                continue;
            }

            foreach ($deps as $depKey => $_) {
                if ($depKey === $key) {
                    $isDependent = true;
                    break;
                }
            }

            if (!$isDependent) {
                continue;
            }

            if ($statusFilter === 'enabled' && !$isEnabled) {
                continue;
            }
            if ($statusFilter === 'installed' && !$isInstalled) {
                continue;
            }

            $dependents[] = [
                'name' => $ext['name'],
                'slug' => $ext['slug'],
            ];
        }

        if ($dependents !== []) {
            $names = [];
            foreach ($dependents as $dep) {
                $names[] = "'{$dep['name']}'";
            }
            $filterLabel = $statusFilter === 'enabled' ? 'enabled' : 'installed';
            return [
                'allowed' => false,
                'blockers' => [[
                    'type'     => 'dependent',
                    'message'  => "{$filterLabel} extensions that depend on this: " . implode(', ', $names) . '.',
                    'dependency' => $key,
                ]],
            ];
        }

        return ['allowed' => true, 'blockers' => []];
    }

    /**
     * Analyze dependencies for display on catalog listing/detail pages.
     *
     * For each dependency, resolves it against the catalog and returns
     * structured data suitable for rendering status badges and detail rows.
     *
     * @param string|array $dependenciesValue Raw dependency field (JSON string or array)
     * @param array<int, array> $allCatalog All catalog entries for lookup
     * @return array<int, array{key: string, constraint: string, status: string, statusClass: string, name: string, installedVersion: string, installed: bool, enabled: bool, catalogId: int|null, catalogSlug: string|null}>
     */
    public static function analyzeDependencies($dependenciesValue, array $allCatalog): array
    {
        $parsed = self::parseDependencies($dependenciesValue);

        // Malformed/unparseable
        if ($parsed === null) {
            return [[
                'key' => '',
                'constraint' => '',
                'status' => 'malformed',
                'statusClass' => 'danger',
                'name' => 'Invalid dependency format',
                'installedVersion' => '—',
                'installed' => false,
                'enabled' => false,
                'catalogId' => null,
                'catalogSlug' => null,
            ]];
        }

        // No dependencies
        if ($parsed === []) {
            return [];
        }

        $results = [];

        foreach ($parsed as $depKey => $constraint) {
            // Validate key format
            if (!self::isValidDependencyKey($depKey)) {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'invalid-key',
                    'statusClass' => 'secondary',
                    'name' => htmlspecialchars($depKey),
                    'installedVersion' => '—',
                    'installed' => false,
                    'enabled' => false,
                    'catalogId' => null,
                    'catalogSlug' => null,
                ];
                continue;
            }

            [$depType, $depSlug] = explode(':', $depKey, 2);

            // Look up in catalog
            $catalogEntry = null;
            foreach ($allCatalog as $entry) {
                if ($entry['type'] === $depType && $entry['slug'] === $depSlug) {
                    $catalogEntry = $entry;
                    break;
                }
            }

            // Not found in catalog
            if ($catalogEntry === null) {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'missing',
                    'statusClass' => 'danger',
                    'name' => htmlspecialchars($depSlug),
                    'installedVersion' => '—',
                    'installed' => false,
                    'enabled' => false,
                    'catalogId' => null,
                    'catalogSlug' => $depSlug,
                ];
                continue;
            }

            $entryId = (int) $catalogEntry['id'];
            $entryName = htmlspecialchars($catalogEntry['name'] ?? $depSlug);
            $entryVersion = htmlspecialchars($catalogEntry['version'] ?? '0.0.0');
            $entryInstalled = (int) ($catalogEntry['is_installed'] ?? 0) === 1;
            $entryEnabled = (int) ($catalogEntry['is_enabled'] ?? 0) === 1;
            $entryStatus = $catalogEntry['status'] ?? 'pending';

            // Pending status
            if ($entryStatus === 'pending') {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'pending',
                    'statusClass' => 'warning',
                    'name' => $entryName,
                    'installedVersion' => $entryVersion,
                    'installed' => $entryInstalled,
                    'enabled' => $entryEnabled,
                    'catalogId' => $entryId,
                    'catalogSlug' => $depSlug,
                ];
                continue;
            }

            // Rejected status
            if ($entryStatus === 'rejected') {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'rejected',
                    'statusClass' => 'danger',
                    'name' => $entryName,
                    'installedVersion' => $entryVersion,
                    'installed' => false,
                    'enabled' => false,
                    'catalogId' => $entryId,
                    'catalogSlug' => $depSlug,
                ];
                continue;
            }

            // Not installed
            if (!$entryInstalled) {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'missing',
                    'statusClass' => 'danger',
                    'name' => $entryName,
                    'installedVersion' => $entryVersion,
                    'installed' => false,
                    'enabled' => false,
                    'catalogId' => $entryId,
                    'catalogSlug' => $depSlug,
                ];
                continue;
            }

            // Installed but not enabled
            if (!$entryEnabled) {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'installed',
                    'statusClass' => 'primary',
                    'name' => $entryName,
                    'installedVersion' => $entryVersion,
                    'installed' => true,
                    'enabled' => false,
                    'catalogId' => $entryId,
                    'catalogSlug' => $depSlug,
                ];
                continue;
            }

            // Invalid constraint format
            if (!self::isValidConstraint($constraint)) {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'invalid-constraint',
                    'statusClass' => 'secondary',
                    'name' => $entryName,
                    'installedVersion' => $entryVersion,
                    'installed' => true,
                    'enabled' => $entryEnabled,
                    'catalogId' => $entryId,
                    'catalogSlug' => $depSlug,
                ];
                continue;
            }

            // Version mismatch
            if ($constraint !== '' && !self::checkVersionConstraint($entryVersion, $constraint)) {
                $results[] = [
                    'key' => htmlspecialchars($depKey),
                    'constraint' => htmlspecialchars($constraint),
                    'status' => 'version-mismatch',
                    'statusClass' => 'danger',
                    'name' => $entryName,
                    'installedVersion' => $entryVersion,
                    'installed' => true,
                    'enabled' => $entryEnabled,
                    'catalogId' => $entryId,
                    'catalogSlug' => $depSlug,
                ];
                continue;
            }

            // Satisfied
            $results[] = [
                'key' => htmlspecialchars($depKey),
                'constraint' => htmlspecialchars($constraint),
                'status' => 'satisfied',
                'statusClass' => 'success',
                'name' => $entryName,
                'installedVersion' => $entryVersion,
                'installed' => true,
                'enabled' => $entryEnabled,
                'catalogId' => $entryId,
                'catalogSlug' => $depSlug,
            ];
        }

        return $results;
    }
}
