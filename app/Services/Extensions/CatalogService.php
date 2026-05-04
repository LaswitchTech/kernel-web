<?php

namespace App\Services\Extensions;

use App\Models\CatalogExtensionRepository;

/**
 * Catalog service for extension metadata management.
 *
 * Orchestrates create/update/list operations on the catalog_extensions table.
 * Validates data before persisting.  Does NOT handle install/enable workflows,
 * remote downloads, or dependency resolution — those are deferred phases.
 */
class CatalogService
{
    private CatalogExtensionRepository $repo;

    private const VALID_TYPES = ['plugin', 'theme', 'layout'];

    private const VALID_STATUSES = ['pending', 'approved', 'rejected'];

    public function __construct(CatalogExtensionRepository $repo)
    {
        $this->repo = $repo;
    }

    // ------ Create ------

    /**
     * Create a new catalog extension record.
     *
     * @param array $data Fields: name, slug, type, version, description, author, download_url, repo_url, requirements, dependencies
     * @return array{success: bool, id?: int, errors?: string[]}
     */
    public function create(array $data): array
    {
        $errors = $this->validateCreate($data);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $slug = $this->slugify($data['slug'] ?? '');

        $id = $this->repo->create([
            'name'         => $data['name'] ?? '',
            'slug'         => $slug,
            'type'         => $data['type'] ?? 'plugin',
            'version'      => $data['version'] ?? '0.0.0',
            'description'  => $data['description'] ?? '',
            'author'       => $data['author'] ?? '',
            'download_url' => $data['download_url'] ?? '',
            'repo_url'     => $data['repo_url'] ?? null,
            'requirements' => $data['requirements'] ?? '[]',
            'dependencies' => $data['dependencies'] ?? '[]',
            'status'       => 'pending',
            'checksum'     => null,
        ]);

        return ['success' => true, 'id' => (int) $id];
    }

    // ------ Update ------

    /**
     * Update an existing catalog extension record.
     *
     * @param int   $id
     * @param array $data Fields to update (subset of create fields)
     * @return array{success: bool, errors?: string[]}
     */
    public function update(int $id, array $data): array
    {
        $errors = $this->validateUpdate($data);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $result = $this->repo->update($id, $data);
        return $result > 0 ? ['success' => true] : ['success' => false, 'errors' => ['Extension not found']];
    }

    // ------ Read ------

    /**
     * Get a single extension by slug.
     *
     * @return array|null
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->repo->findBySlug($slug);
    }

    /**
     * Get a single extension by ID.
     *
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    /**
     * List all catalog extensions.
     *
     * @return array<int, array>
     */
    public function listAll(): array
    {
        return $this->repo->findAll();
    }

    /**
     * List extensions by type.
     *
     * @param string $type plugin | theme | layout
     * @return array<int, array>
     */
    public function listByType(string $type): array
    {
        return $this->repo->findByType($type);
    }

    /**
     * List approved extensions (available for installation).
     *
     * @return array<int, array>
     */
    public function listApproved(): array
    {
        return $this->repo->findApproved();
    }

    /**
     * List pending extensions awaiting review.
     *
     * @return array<int, array>
     */
    public function listPending(): array
    {
        return $this->repo->findPending();
    }

    // ------ Status Transitions ------

    /**
     * Approve a pending extension.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function approve(int $id): array
    {
        $extension = $this->repo->findById($id);
        if ($extension === null) {
            return ['success' => false, 'errors' => ['Extension not found']];
        }

        if ($extension['status'] !== 'pending') {
            return ['success' => false, 'errors' => ['Extension is not in pending status']];
        }

        $this->repo->updateStatus($id, 'approved');
        return ['success' => true];
    }

    /**
     * Reject a pending extension.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function reject(int $id, string $reason = ''): array
    {
        $extension = $this->repo->findById($id);
        if ($extension === null) {
            return ['success' => false, 'errors' => ['Extension not found']];
        }

        if ($extension['status'] !== 'pending') {
            return ['success' => false, 'errors' => ['Extension is not in pending status']];
        }

        $this->repo->updateStatus($id, 'rejected');
        return ['success' => true];
    }

    /**
     * Mark an extension as installed.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function markInstalled(int $id): array
    {
        $extension = $this->repo->findById($id);
        if ($extension === null) {
            return ['success' => false, 'errors' => ['Extension not found']];
        }

        $this->repo->markInstalled($id);
        return ['success' => true];
    }

    /**
     * Mark an extension as uninstalled.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function markUninstalled(int $id): array
    {
        $extension = $this->repo->findById($id);
        if ($extension === null) {
            return ['success' => false, 'errors' => ['Extension not found']];
        }

        $this->repo->markUninstalled($id);
        return ['success' => true];
    }

    /**
     * Mark an extension as enabled.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function markEnabled(int $id): array
    {
        $extension = $this->repo->findById($id);
        if ($extension === null) {
            return ['success' => false, 'errors' => ['Extension not found']];
        }

        $this->repo->markEnabled($id);
        return ['success' => true];
    }

    /**
     * Mark an extension as disabled.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function markDisabled(int $id): array
    {
        $extension = $this->repo->findById($id);
        if ($extension === null) {
            return ['success' => false, 'errors' => ['Extension not found']];
        }

        $this->repo->markDisabled($id);
        return ['success' => true];
    }

    // ------ Delete ------

    /**
     * Delete an extension from the catalog.
     *
     * @return array{success: bool, errors?: string[]}
     */
    public function delete(int $id): array
    {
        $extension = $this->repo->findById($id);
        if ($extension === null) {
            return ['success' => false, 'errors' => ['Extension not found']];
        }

        $this->repo->delete($id);
        return ['success' => true];
    }

    // ------ Validation Helpers ------

    /**
     * Parse dependency field into an array of slugs.
     *
     * If the input is a JSON string, it is decoded.
     * If the input is already an array, it is returned as-is.
     * Invalid JSON returns an empty array.
     *
     * @param string|array $value
     * @return array<int, string>
     */
    public function parseDependencies($value): array
    {
        if (is_array($value)) {
            return array_map('strval', array_values($value));
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map('strval', array_values($decoded));
    }

    /**
     * Parse requirements field into an associative array.
     *
     * @param string|array $value
     * @return array<string, string>
     */
    public function parseRequirements($value): array
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[(string) $k] = (string) $v;
            }
            return $result;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $k => $v) {
            $result[(string) $k] = (string) $v;
        }
        return $result;
    }

    // ------ Internal Validation ------

    /**
     * Validate create payload.
     *
     * @param array $data
     * @return string[]
     */
    private function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['name'] ?? '')) {
            $errors['name'] = 'Name is required.';
        }

        $slug = $data['slug'] ?? '';
        if (empty($slug)) {
            $errors['slug'] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_-]*$/', $slug)) {
            $errors['slug'] = 'Slug must start with a lowercase letter and contain only lowercase letters, digits, hyphens, and underscores.';
        } elseif ($this->repo->slugExists($slug)) {
            $errors['slug'] = 'A catalog entry with this slug already exists.';
        }

        $type = $data['type'] ?? '';
        if (!in_array($type, self::VALID_TYPES, true)) {
            $errors['type'] = 'Type must be one of: plugin, theme, layout.';
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $data['version'] ?? '')) {
            $errors['version'] = 'Version must be in semantic versioning format (e.g. 1.0.0).';
        }

        return $errors;
    }

    /**
     * Validate update payload.
     *
     * @param array $data
     * @return string[]
     */
    private function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['slug']) && $data['slug'] !== '') {
            if (!preg_match('/^[a-z][a-z0-9_-]*$/', $data['slug'])) {
                $errors[] = 'Slug must start with a lowercase letter and contain only lowercase letters, digits, hyphens, and underscores.';
            }
        }

        if (isset($data['type']) && !in_array($data['type'], self::VALID_TYPES, true)) {
            $errors[] = 'Type must be one of: plugin, theme, layout.';
        }

        if (isset($data['version']) && !preg_match('/^\d+\.\d+\.\d+$/', $data['version'])) {
            $errors[] = 'Version must be in semantic versioning format (e.g. 1.0.0).';
        }

        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $errors[] = 'Status must be one of: pending, approved, rejected.';
        }

        return $errors;
    }

    /**
     * Convert a string to a URL-safe slug.
     */
    private function slugify(string $string): string
    {
        $slug = strtolower(trim($string));
        $slug = preg_replace('/[^a-z0-9_-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return $slug;
    }
}
