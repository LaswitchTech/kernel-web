<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * Database operations for the organizations table.
 */
class OrganizationRepository
{
    public function __construct(private DatabaseInterface $db) {}

    /**
     * Create a new organization.
     *
     * @param array{
     *     name: string,
     *     slug: string,
     *     type: string
     * } $data
     * @return int The new organization ID.
     * @throws \RuntimeException On duplicate slug.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO organizations (name, slug, type, active, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, ?)',
            [$data['name'], $data['slug'], $data['type'] ?? 'organization', $now, $now]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Find by primary key.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM organizations WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * Find by slug.
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM organizations WHERE slug = ? LIMIT 1',
            [$slug]
        );
    }

    /**
     * Check whether a slug is already taken.
     */
    public function isSlugTaken(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            return $this->db->fetchOne(
                'SELECT id FROM organizations WHERE slug = ? AND id != ? LIMIT 1',
                [$slug, $excludeId]
            ) !== null;
        }
        return $this->db->fetchOne(
            'SELECT id FROM organizations WHERE slug = ? LIMIT 1',
            [$slug]
        ) !== null;
    }

    /**
     * Return all organizations.
     *
     * @return array<int, array{id: int, name: string, slug: string, type: string, active: int, created_at: string, updated_at: string}>
     */
    public function findAll(): array
    {
        return $this->db->fetch(
            'SELECT * FROM organizations ORDER BY name ASC',
            []
        );
    }

    /**
     * Return active organizations.
     */
    public function findAllActive(): array
    {
        return $this->db->fetch(
            'SELECT * FROM organizations WHERE active = 1 ORDER BY name ASC',
            []
        );
    }

    /**
     * Update an organization's name.
     */
    public function updateName(int $id, string $name): void
    {
        $this->db->execute(
            'UPDATE organizations SET name = ?, updated_at = ? WHERE id = ?',
            [$name, date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Deactivate an organization.
     */
    public function setActive(int $id, bool $active): void
    {
        $this->db->execute(
            'UPDATE organizations SET active = ?, updated_at = ? WHERE id = ?',
            [$active ? 1 : 0, date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Delete an organization and all its memberships.
     */
    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM organization_users WHERE organization_id = ?', [$id]);
        $this->db->execute('DELETE FROM organizations WHERE id = ?', [$id]);
    }
}
