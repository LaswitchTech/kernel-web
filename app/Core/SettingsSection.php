<?php

namespace App\Core;

/**
 * Immutable settings section definition.
 *
 * Used by SettingsRegistry to hold section metadata and callbacks.
 * Plugins and core code create sections and register them via the registry.
 */
final class SettingsSection
{
    public function __construct(
        public readonly string    $id,
        public readonly string    $label,
        public readonly string    $column = 'left',
        public readonly int       $order = 50,
        public readonly ?string   $permission = null,
        /** @var callable|null */
        public readonly mixed     $render = null,
        /** @var string[] */
        public readonly array     $keys = [],
        /** @var callable|null */
        public readonly mixed     $validate = null,
        /** @var callable|null */
        public readonly mixed     $save = null,
        public readonly string    $source = 'core',
    ) {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException(
                "Settings section ID must match '^[a-z][a-z0-9_-]*$', got: {$id}"
            );
        }
        if ($column !== 'left' && $column !== 'right') {
            throw new \InvalidArgumentException(
                "Settings section column must be 'left' or 'right', got: {$column}"
            );
        }
    }

    /**
     * Check if this section is visible for the given permissions.
     */
    public function isVisible(array $userPermissions): bool
    {
        if ($this->permission === null) {
            return true;
        }
        return in_array($this->permission, $userPermissions, true);
    }

    /**
     * Render the section body content.
     *
     * @param array<string, mixed> $context View variables
     * @return string HTML body content (no card wrapper)
     */
    public function renderBody(array $context = []): string
    {
        if ($this->render === null) {
            return '';
        }
        $output = call_user_func($this->render, $context);
        return $output ?? '';
    }
}
