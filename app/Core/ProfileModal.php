<?php

namespace App\Core;

/**
 * In-memory Profile Modal section registry.
 *
 * Core and plugins register sections; the registry enforces uniqueness,
 * ordering, and permission gating.
 *
 * Example usage:
 *   ProfileModal::addSection([
 *       'id' => 'tokens',
 *       'label' => 'API Tokens',
 *       'icon' => 'bi-key',
 *       'order' => 20,
 *       'callback' => [TokenController::class, 'renderTokensSection'],
 *   ]);
 *
 *   ProfileModal::getSections($userPermissions);
 *   ProfileModal::renderSection('tokens', $context);
 */
class ProfileModal
{
    /** @var array<string, ProfileModalSection> */
    private static array $sections = [];

    /**
     * Register a profile modal section.
     *
     * @param array{
     *     id: string,
     *     label: string,
     *     icon?: string,
     *     order?: int,
     *     callback?: mixed,
     *     permission?: string|null,
     *     source?: string
     * }|ProfileModalSection $section
     */
    /**
     * @param array|ProfileModalSection $section
     */
    public static function addSection($section): void
    {
        if (!$section instanceof ProfileModalSection) {
            $section = new ProfileModalSection(
                $section['id'],
                $section['label'],
                $section['icon'] ?? null,
                (int) ($section['order'] ?? 50),
                $section['callback'] ?? null,
                $section['permission'] ?? null,
                $section['source'] ?? 'core',
            );
        }

        if (isset(self::$sections[$section->id])) {
            throw new \InvalidArgumentException(
                "Duplicate profile section ID: '{$section->id}'"
            );
        }

        self::$sections[$section->id] = $section;
    }

    /**
     * Get all visible sections for the given permissions.
     *
     * @param string[] $userPermissions
     * @return ProfileModalSection[]
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
        usort($visible, function (ProfileModalSection $a, ProfileModalSection $b): int {
            $cmp = $a->order <=> $b->order;
            return $cmp !== 0 ? $cmp : ($a->id <=> $b->id);
        });

        return $visible;
    }

    /**
     * Get metadata for all visible sections (for API responses).
     *
     * @param string[] $userPermissions
     * @return array<int, array<string, mixed>>
     */
    public static function getMetadata(array $userPermissions = []): array
    {
        return array_map(
            fn (ProfileModalSection $s) => $s->metadata(),
            self::getSections($userPermissions)
        );
    }

    /**
     * Get a single section by ID.
     *
     * @param string[] $userPermissions Optional — if provided, checks visibility
     * @return ProfileModalSection|null
     */
    public static function getSection(string $id, array $userPermissions = []): ?ProfileModalSection
    {
        $section = self::$sections[$id] ?? null;
        if ($section === null) {
            return null;
        }
        if (!empty($userPermissions) && !$section->isVisible($userPermissions)) {
            return null;
        }
        return $section;
    }

    /**
     * Render a section's content.
     *
     * @param array<string, mixed> $context View variables
     * @return string HTML content
     */
    public static function renderSection(string $id, array $context = []): string
    {
        $section = self::$sections[$id] ?? null;
        if ($section === null) {
            return '';
        }
        return $section->render($context);
    }

    /**
     * Check if a section exists.
     */
    public static function hasSection(string $id): bool
    {
        return isset(self::$sections[$id]);
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
