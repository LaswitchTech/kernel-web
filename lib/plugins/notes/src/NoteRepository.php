<?php

namespace App\Plugins\Notes;

use App\Core\DatabaseInterface;

/**
 * All database queries for the notes table.
 *
 * Entity-agnostic: knows only about (entity_type, entity_id, user_id, content).
 * Returns raw arrays; no domain objects.
 */
class NoteRepository
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
        return $this->db->fetch(
            "SELECT
                n.id, n.entity_type, n.entity_id, n.user_id, n.content,
                n.created_at, n.updated_at,
                u.username     AS author_name,
                u.display_name AS author_display
            FROM notes n
            LEFT JOIN users u ON u.id = n.user_id
            WHERE n.entity_type = ? AND n.entity_id = ?
            ORDER BY n.created_at DESC",
            [$entityType, $entityId]
        );
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
        return $this->db->fetchOne(
            "SELECT
                n.id, n.entity_type, n.entity_id, n.user_id, n.content,
                n.created_at, n.updated_at,
                u.username     AS author_name,
                u.display_name AS author_display
            FROM notes n
            LEFT JOIN users u ON u.id = n.user_id
            WHERE n.id = ?",
            [$id]
        );
    }

    /**
     * Insert a new note row.
     *
     * @param  array{entity_type: string, entity_id: int, user_id: int|null, content: string} $data
     * @return int The new note ID
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            "INSERT INTO notes (entity_type, entity_id, user_id, content, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['entity_type'],
                $data['entity_id'],
                $data['user_id'],
                $data['content'],
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
