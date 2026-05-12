<?php

namespace App\Core;

use App\Services\SystemSettingService;

/**
 * In-memory settings section registry.
 *
 * Core and plugins register sections; the registry enforces uniqueness,
 * ordering, and permission gating.
 *
 * Example usage:
 *   SettingsRegistry::addSection([
 *       'id' => 'smtp',
 *       'label' => 'SMTP Settings',
 *       'column' => 'right',
 *       'order' => 10,
 *       'keys' => ['smtp.host', 'smtp.port', 'smtp.user', 'smtp.pass'],
 *       'permission' => 'settings.smtp',
 *       'render' => fn($ctx) => require __DIR__.'/views/smtp.php',
 *       'validate' => fn($input) => [...],
 *       'save' => fn($input, $svc) => { ... },
 *       'source' => 'smtp',
 *   ]);
 *
 *   SettingsRegistry::getSections($userPermissions);
 */
class SettingsRegistry
{
    /** @var array<string, SettingsSection> */
    private static array $sections = [];

    /**
     * Register a settings section.
     *
     * Plugin keys must use the format `<source>.<key>` where source
     * matches the section's `source` field (enforced at registration).
     *
     * @param array{
     *     id: string,
     *     label: string,
     *     column?: 'left'|'right',
     *     order?: int,
     *     permission?: string|null,
     *     render?: callable,
     *     keys?: string[],
     *     validate?: callable,
     *     save?: callable,
     *     source?: string
     * }|SettingsSection $section
     */
    /**
     * @param array|SettingsSection $section
     */
    public static function addSection(array|SettingsSection $section): void
    {
        if (!$section instanceof SettingsSection) {
            $section = new SettingsSection(
                $section['id'],
                $section['label'],
                $section['column'] ?? 'left',
                (int) ($section['order'] ?? 50),
                $section['permission'] ?? null,
                $section['render'] ?? null,
                $section['keys'] ?? [],
                $section['validate'] ?? null,
                $section['save'] ?? null,
                $section['source'] ?? 'core',
            );
        }

        if (isset(self::$sections[$section->id])) {
            throw new \InvalidArgumentException(
                "Duplicate settings section ID: '{$section->id}'"
            );
        }

        // Enforce key prefix convention (SQ-1): every plugin key must use `<source>.<key>`.
        if ($section->source !== 'core') {
            foreach ($section->keys as $key) {
                if (!str_starts_with($key, $section->source . '.')) {
                    throw new \InvalidArgumentException(
                        "Section '{$section->id}' key '{$key}' must use prefix '{$section->source}.'"
                    );
                }
            }
        }

        self::$sections[$section->id] = $section;
    }

    /**
     * Get all visible sections for the given permissions, sorted by order.
     *
     * @param string[] $userPermissions
     * @return SettingsSection[]
     */
    public static function getSections(array $userPermissions = []): array
    {
        $sections = array_values(self::$sections);

        $visible = [];
        foreach ($sections as $section) {
            if (!$section->isVisible($userPermissions)) {
                continue;
            }
            $visible[] = $section;
        }

        // Sort by order ascending, then ID as tiebreaker.
        usort($visible, function (SettingsSection $a, SettingsSection $b): int {
            $cmp = $a->order <=> $b->order;
            return $cmp !== 0 ? $cmp : ($a->id <=> $b->id);
        });

        return $visible;
    }

    /**
     * Get a single section by ID.
     */
    public static function getSection(string $id): ?SettingsSection
    {
        return self::$sections[$id] ?? null;
    }

    /**
     * Get all setting keys managed by a section.
     */
    public static function getSectionKeys(string $id): array
    {
        return self::$sections[$id]->keys ?? [];
    }

    /**
     * Validate a section's settings.
     *
     * @param string[] $userPermissions
     * @return array<string, string>  field → error message
     */
    public static function validateSection(string $id, array $input, array $userPermissions = []): array
    {
        $section = self::$sections[$id] ?? null;
        if ($section === null || $section->validate === null) {
            return [];
        }
        return call_user_func($section->validate, $input);
    }

    /**
     * Save a section's settings.
     */
    public static function saveSection(string $id, array $input, SystemSettingService $svc): void
    {
        $section = self::$sections[$id] ?? null;
        if ($section === null || $section->save === null) {
            return;
        }
        call_user_func($section->save, $input, $svc);
    }

    /**
     * Check if any sections are registered.
     */
    public static function hasSections(): bool
    {
        return !empty(self::$sections);
    }

    /**
     * Clear all registered sections (for testing / isolation).
     */
    public static function clear(): void
    {
        self::$sections = [];
    }
}
