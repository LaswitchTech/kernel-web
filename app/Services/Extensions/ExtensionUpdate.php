<?php

namespace App\Services\Extensions;

/**
 * Immutable value object representing the update status of a single installed extension.
 */
readonly class ExtensionUpdate
{
    public function __construct(
        public string $slug,
        public string $type,
        public ?string $installedVersion,  // null if manifest invalid
        public string $catalogVersion,     // always present (catalog record exists)
        public string $status,             // up_to_date | update_available | newer_than_catalog | blocked | invalid
        /** @var list<string> */
        public array $blockers = [],
    ) {}

    public function isUpToDate(): bool
    {
        return $this->status === 'up_to_date';
    }

    public function hasUpdate(): bool
    {
        return $this->status === 'update_available';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isInvalid(): bool
    {
        return $this->status === 'invalid';
    }
}
