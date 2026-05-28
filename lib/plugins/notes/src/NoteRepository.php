<?php

namespace App\Plugins\Notes;

use App\Core\DatabaseInterface;
use App\Core\OrganizationScopedRepository;

/**
 * All database queries for the notes table.
 *
 * Entity-agnostic: knows only about (entity_type, entity_id, user_id, content).
 * Returns raw arrays; no domain objects.
 */
class NoteRepository extends OrganizationScopedRepository
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Return all notes for an entity, newest first.
     *
     * @return array<int, array{
     *   id:             int,
     *   entity_type:    string,
     *   entity_id:      int,
     *   user_id:        int|null,
     *   content:        string,
     *   created_at:     string,
     *   updated_at:     string,
     *   author_name:    string|null,
     *   author_display: string|null
     * }>
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$entityType, $entityId], $this->organizationParams());

        $sql = "SELECT
            n.id, n.entity_type, n.entity_id, n.user_id, n.content,
            n.created_at, n.updated_at,
            u.username     AS author_name,
            u.display_name AS author_display
         FROM notes n
         LEFT JOIN users u ON u.id = n.user_id
         WHERE n.entity_type = ? AND n.entity_id = ?";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        $sql .= " ORDER BY n.created_at DESC";

        return $this->db->fetch($sql, $params);
    }

    /**
     * Find a single note by ID. Returns null if not found.
     *
     * @return array{
     *   id:             int,
     *   entity_type:    string,
     *   entity_id:      int,
     *   user_id:        int|null,
     *   content:        string,
     *   created_at:     string,
     *   updated_at:     string,
     *   author_name:    string|null,
     *   author_display: string|null
     * }|null
     */
    public function findById(int $id): ?array
    {
        $and    = $this->organizationAnd();
        $params = array_merge([$id], $this->organizationParams());

        $sql = "SELECT
            n.id, n.entity_type, n.entity_id, n.user_id, n.content,
            n.created_at, n.updated_at,
            u.username     AS author_name,
            u.display_name AS author_display
         FROM notes n
         LEFT JOIN users u ON u.id = n.user_id
         WHERE n.id = ?";
        if ($and !== '') {
            $sql .= ' ' . $and;
        }

        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Insert a new note row.
     *
     * @param  array{entity_type: string, entity_id: int, user_id: int|null, content: string, organization_id?: int|null} $data
     * @return int The new note ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "INSERT INTO notes (entity_type, entity_id, user_id, content, organization_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['entity_type'],
                $data['entity_id'],
                $data['user_id']        ?? null,
                $data['content'],
                $data['organization_id'] ?? null,
                $now,
                $now,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Hard-delete a note row.
     */
    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM notes WHERE id = ?", [$id]);
    }
}
