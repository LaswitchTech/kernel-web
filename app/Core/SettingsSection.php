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
    /** @var string */
    public $id;
    /** @var string */
    public $label;
    /** @var string */
    public $column;
    /** @var int */
    public $order;
    /** @var string|null */
    public $permission;
    /** @var callable|null */
    public $render;
    /** @var string[] */
    public $keys;
    /** @var callable|null */
    public $validate;
    /** @var callable|null */
    public $save;
    /** @var string */
    public $source;

    public function __construct(
        string $id,
        string $label,
        string $column = 'left',
        int $order = 50,
        ?string $permission = null,
        $render = null,
        array $keys = [],
        $validate = null,
        $save = null,
        string $source = 'core'
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->column = $column;
        $this->order = $order;
        $this->permission = $permission;
        $this->render = $render;
        $this->keys = $keys;
        $this->validate = $validate;
        $this->save = $save;
        $this->source = $source;
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
