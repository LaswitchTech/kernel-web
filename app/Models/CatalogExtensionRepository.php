<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * Database access for the catalog_extensions table.
 *
 * Provides CRUD operations for catalog-managed extension records.
 * The service layer (CatalogService) handles validation and orchestration.
 */
class CatalogExtensionRepository
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // ------ Create / Update ------

    /**
     * Create a new catalog extension record.
     *
     * @param array{name: string, slug: string, type: string, version: string, description: string, author: string, download_url: string, repo_url: string|null, requirements: string, dependencies: string, status: string, checksum: string|null} $data
     * @return int Last insert ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO catalog_extensions
             (name, slug, type, version, description, author, download_url, repo_url, requirements, dependencies, status, is_installed, is_enabled, checksum, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?)',
            [
                $data['name'],
                $data['slug'],
                $data['type'],
                $data['version'],
                $data['description'],
                $data['author'],
                $data['download_url'],
                $data['repo_url'],
                $data['requirements'],
                $data['dependencies'],
                $data['status'],
                $data['checksum'],
                $now,
                $now,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing catalog extension record.
     *
     * @param int   $id
     * @param array{slug?: string, type?: string, version?: string, description?: string, author?: string, download_url?: string, repo_url?: string|null, requirements?: string, dependencies?: string, status?: string, checksum?: string|null} $data
     * @return int Affected rows (0 or 1)
     */
    public function update(int $id, array $data): int
    {
        $parts = [];
        $bindings = [];

        foreach ($data as $field => $value) {
            if ($field === 'repo_url' && $value === '') {
                $value = null;
            }
            $parts[] = "{$field} = ?";
            $bindings[] = $value;
        }

        $parts[] = "updated_at = ?";
        $bindings[] = date('Y-m-d H:i:s');
        $bindings[] = $id;

        return $this->db->execute(
            "UPDATE catalog_extensions SET " . implode(', ', $parts) . " WHERE id = ?",
            $bindings
        );
    }

    /**
     * Delete a catalog extension record.
     *
     * @return int Affected rows (0 or 1)
     */
    public function delete(int $id): int
    {
        return $this->db->execute(
            'DELETE FROM catalog_extensions WHERE id = ?',
            [$id]
        );
    }

    // ------ Read ------

    /**
     * Find a single extension by slug.
     *
     * @return array|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM catalog_extensions WHERE slug = ? LIMIT 1',
            [$slug]
        );
    }

    /**
     * Find a single extension by ID.
     *
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM catalog_extensions WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * List all catalog extensions.
     *
     * @return array<int, array>
     */
    public function findAll(): array
    {
        return $this->db->fetch(
            'SELECT * FROM catalog_extensions ORDER BY type ASC, name ASC',
            []
        );
    }

    /**
     * List extensions filtered by type.
     *
     * @param string $type plugin | theme | layout
     * @return array<int, array>
     */
    public function findByType(string $type): array
    {
        return $this->db->fetch(
            'SELECT * FROM catalog_extensions WHERE type = ? ORDER BY name ASC',
            [$type]
        );
    }

    /**
     * List approved extensions.
     *
     * @return array<int, array>
     */
    public function findApproved(): array
    {
        return $this->db->fetch(
            'SELECT * FROM catalog_extensions WHERE status = ? ORDER BY name ASC',
            ['approved']
        );
    }

    /**
     * List extensions pending review.
     *
     * @return array<int, array>
     */
    public function findPending(): array
    {
        return $this->db->fetch(
            'SELECT * FROM catalog_extensions WHERE status = ? ORDER BY created_at DESC',
            ['pending']
        );
    }

    /**
     * List installed extensions.
     *
     * @return array<int, array>
     */
    public function findInstalled(): array
    {
        return $this->db->fetch(
            'SELECT * FROM catalog_extensions WHERE is_installed = 1 ORDER BY name ASC',
            []
        );
    }

    /**
     * Mark an extension as installed.
     *
     * @return int Affected rows
     */
    public function markInstalled(int $id): int
    {
        return $this->db->execute(
            'UPDATE catalog_extensions SET is_installed = 1, is_enabled = 1, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Mark an extension as uninstalled.
     *
     * @return int Affected rows
     */
    public function markUninstalled(int $id): int
    {
        return $this->db->execute(
            'UPDATE catalog_extensions SET is_installed = 0, is_enabled = 0, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Mark an extension as enabled.
     *
     * @return int Affected rows
     */
    public function markEnabled(int $id): int
    {
        return $this->db->execute(
            'UPDATE catalog_extensions SET is_enabled = 1, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Mark an extension as disabled.
     *
     * @return int Affected rows
     */
    public function markDisabled(int $id): int
    {
        return $this->db->execute(
            'UPDATE catalog_extensions SET is_enabled = 0, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Update the review status of an extension.
     *
     * @return int Affected rows
     */
    public function updateStatus(int $id, string $status): int
    {
        return $this->db->execute(
            'UPDATE catalog_extensions SET status = ?, updated_at = ? WHERE id = ?',
            [$status, date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Check whether a slug already exists (for uniqueness validation).
     */
    public function slugExists(string $slug): bool
    {
        $row = $this->db->fetchOne(
            'SELECT 1 FROM catalog_extensions WHERE slug = ? LIMIT 1',
            [$slug]
        );

        return $row !== null;
    }
}
