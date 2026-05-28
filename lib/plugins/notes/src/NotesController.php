<?php

namespace App\Plugins\Notes;

use App\Core\Controller;

/**
 * HTTP endpoints for the Notes plugin.
 *
 * All routes require session authentication.
 */
class NotesController extends Controller
{
    // ------ GET /notes — list all notes (filtered by entity) ------

    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $config    = $this->container->get('config');

        $entityType = $_GET['entity_type'] ?? '';
        $entityId   = (int) ($_GET['entity_id'] ?? 0);

        if ($entityType === '' || $entityId <= 0) {
            $this->json(['error' => 'entity_type and entity_id are required'], 400);
            return;
        }

        $notes = $this->service()->findByEntity($entityType, $entityId);

        $this->json(['notes' => $notes]);
    }

    // ------ POST /notes — create a new note ------

    public function add(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $userId    = $principal['user']['id'];

        $entityType = trim($_POST['entity_type'] ?? '');
        $entityId   = (int) ($_POST['entity_id'] ?? 0);
        $content    = $_POST['content'] ?? '';

        if ($entityType === '' || $entityId <= 0) {
            $this->json(['error' => 'entity_type and entity_id are required'], 400);
            return;
        }

        if ($content === '') {
            $this->json(['error' => 'Note content cannot be empty'], 400);
            return;
        }

        $orgId = $this->container->get('org_scope');

        try {
            $noteId = $this->service()->addNote($entityType, $entityId, $userId, $content, $orgId);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
            return;
        }

        $this->json(['id' => $noteId, 'success' => true], 201);
    }

    // ------ GET /notes/{id} — show a single note ------

    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['error' => 'Invalid note ID'], 400);
            return;
        }

        $note = $this->service()->findById($id);

        if ($note === null) {
            $this->json(['error' => 'Note not found'], 404);
            return;
        }

        $this->json(['note' => $note]);
    }

    // ------ DELETE /notes/{id}/delete — hard-delete a note ------

    public function delete(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $requestingUserId = $principal['user']['id'];
        $isAdmin = in_array('admin', $principal['permissions'] ?? [], true);

        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['error' => 'Invalid note ID'], 400);
            return;
        }

        try {
            $this->service()->removeNote($id, $requestingUserId, $isAdmin);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 403);
            return;
        }

        $this->json(['success' => true]);
    }

    // ------ Helpers ------

    private function service(): NoteService
    {
        $db = $this->container->get('db');
        $repo = new NoteRepository($db);
        $repo->scopeFromContainer($this->container);
        return new NoteService($repo);
    }
}
