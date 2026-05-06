<?php

namespace App\Core;

/**
 * Immutable section definition for the Profile Modal.
 */
final class ProfileModalSection
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly int $order = 50,
        public readonly mixed $callback = null,
        public readonly ?string $permission = null,
        public readonly string $source = 'core',
    ) {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException(
                "Profile section ID must match '^[a-z][a-z0-9_-]*$', got: {$id}"
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
     * Render the section content.
     *
     * @param array<string, mixed> $context Optional view variables
     * @return string
     */
    public function render(array $context = []): string
    {
        if ($this->callback === null) {
            return '';
        }
        $output = call_user_func($this->callback, $context);
        return $output ?? '';
    }

    /**
     * Get section metadata for API responses.
     */
    public function metadata(): array
    {
        return [
            'id'      => $this->id,
            'label'   => $this->label,
            'icon'    => $this->icon,
            'order'   => $this->order,
            'source'  => $this->source,
        ];
    }
}
