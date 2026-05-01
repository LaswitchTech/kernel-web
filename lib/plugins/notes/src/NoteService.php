<?php

namespace App\Plugins\Notes;

/**
 * Thin validation and write orchestration for the Notes plugin.
 *
 * Enforces content rules and ownership checks before delegating to
 * NoteRepository.  Has no dependency on any domain-specific class.
 */
class NoteService
{
    public const MAX_CONTENT_LENGTH = 10000;

    private NoteRepository $repo;

    public function __construct(NoteRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Validate and create a note.
     *
     * @param  string $entityType  e.g. 'device', 'alert', 'finding'
     * @param  int    $entityId    Primary key of the target entity
     * @param  int|null $userId    Authenticated user ID; null = anonymous
     * @param  string $content     Note body (will be trimmed before storage)
     * @return int                 New note ID
     * @throws \InvalidArgumentException
     */
    public function addNote(string $entityType, int $entityId, ?int $userId, string $content): int
    {
        $content = trim($content);

        if ($content === '') {
            throw new \InvalidArgumentException('Note content cannot be empty.');
        }

        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new \InvalidArgumentException(
                'Note content must be ' . number_format(self::MAX_CONTENT_LENGTH) . ' characters or fewer.'
            );
        }

        return $this->repo->create([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'user_id'     => $userId,
            'content'     => $content,
        ]);
    }

    /**
     * Remove a note, enforcing simple ownership rules.
     *
     * @param  int      $noteId            The note to delete
     * @param  int|null $requestingUserId  The authenticated user's ID
     * @param  bool     $isAdmin           Whether the requesting user is an admin
     * @throws \InvalidArgumentException
     */
    public function removeNote(int $noteId, ?int $requestingUserId, bool $isAdmin = false): void
    {
        $note = $this->repo->findById($noteId);

        if ($note === null) {
            throw new \InvalidArgumentException('Note not found.');
        }

        $isAuthor = $requestingUserId !== null && (int) $note['user_id'] === $requestingUserId;

        if (!$isAuthor && !$isAdmin) {
            throw new \InvalidArgumentException('You do not have permission to delete this note.');
        }

        $this->repo->delete($noteId);
    }

    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->repo->findByEntity($entityType, $entityId);
    }

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }
}
