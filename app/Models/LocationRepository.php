<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * Database queries for the locations table.
 *
 * Locations provide a hierarchical structure for organizing entities
 * within any application (sites, buildings, floors, rooms, etc.).
 */
class LocationRepository
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function findAll(): array
    {
        return $this->db->fetch(
            "SELECT * FROM locations ORDER BY name"
        );
    }

    /**
     * Return locations formatted for use in select dropdowns.
     */
    public function findAllForSelect(): array
    {
        return $this->db->fetch(
            "SELECT id, name, type FROM locations ORDER BY name"
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM locations WHERE id = ?",
            [$id]
        );
    }

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO locations (name, type, parent_id, description)
             VALUES (?, ?, ?, ?)",
            [$data['name'], $data['type'] ?? 'other', $data['parent_id'] ?? null, $data['description'] ?? null]
        );
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE locations SET name = ?, type = ?, parent_id = ?, description = ? WHERE id = ?",
            [$data['name'], $data['type'], $data['parent_id'] ?? null, $data['description'] ?? null, $id]
        );
    }

    /**
     * Delete a location. Returns an error message string if deletion is prevented,
     * or true on success.
     */
    public function delete(int $id): bool|string
    {
        // Check for child locations
        $children = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM locations WHERE parent_id = ?",
            [$id]
        );
        if ((int) $children['cnt'] > 0) {
            return "Cannot delete location with child locations.";
        }

        // Check for references (reserved column)
        $deviceRefs = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM devices WHERE location_id = ?",
            [$id]
        );
        if ((int) $deviceRefs['cnt'] > 0) {
            return "Cannot delete location referenced by devices.";
        }

        $this->db->execute('DELETE FROM locations WHERE id = ?', [$id]);
        return true;
    }

    public function isNameTaken(string $name, ?int $parentId = null, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS cnt FROM locations WHERE name = ? AND parent_id = ?";
        $bindings = [$name, $parentId];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $bindings[] = $excludeId;
        }

        $row = $this->db->fetchOne($sql, $bindings);
        return (int) $row['cnt'] > 0;
    }
}
