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

            [$depType, $depSlug] = $parsed;

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

        // Circular dependency check (only for plugins)
        $allDeps = array_merge($dependencies, ['plugin:' . $thisSlug => '0.0.0']);
        if (self::hasCircularDependency($allDeps, $allCatalog)) {
            $blockers[] = ['type' => 'circular', 'message' => 'Circular dependency detected.', 'dependency' => $thisSlug];
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

            [$depType, $depSlug] = $parsed;

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
     * Returns null if the value is completely unparseable.
     *
     * @param string|mixed $value
     * @return array<string, string>|null
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
     * @return bool true if circular dependency found
     */
    private static function hasCircularDependency(array $allDeps, array $catalog, ?string $currentKey = null, array $visited = [], array $stack = []): bool
    {
        if ($currentKey === null) {
            $firstKey = array_key_first($allDeps);
            if ($firstKey === null) {
                return false;
            }
            $currentKey = $firstKey;
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
            if (self::hasCircularDependency($allDeps, $catalog, $depKey, $visited, $stack)) {
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
}
