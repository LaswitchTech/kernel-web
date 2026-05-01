<?php

namespace App\Modules\Chat\Controllers;

use App\Core\Controller;
use App\Models\AuditLogRepository;
use App\Modules\Chat\Models\ChatRoomRepository;
use App\Modules\Chat\Models\ChatRoomMemberRepository;
use App\Modules\Chat\Models\ChatMessageRepository;
use App\Modules\Chat\Services\ChatService;

/**
 * Chat module controller.
 *
 * Routes:
 *
 *   GET  /chat                       → index()        Rooms list
 *   GET  /chat/rooms/create          → createForm()   Create room form
 *   POST /chat/rooms                 → store()        Handle create
 *   GET  /chat/rooms/{id}            → show()         Room detail + message feed
 *   POST /chat/rooms/{id}/join       → join()         Join a shared room
 *   POST /chat/rooms/{id}/messages   → sendMessage()  Post a message
 *
 * JSON API (SessionAuth — returns 401 JSON on failure, not a redirect):
 *
 *   GET  /api/chat/unread            → unreadCount()  Total unread badge count
 *
 * Browser routes are protected by ['WebAuth', 'WebPermission:chat.use'].
 * The JSON API route uses ['SessionAuth']; permission is checked in the method.
 */
class ChatController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /chat
    // -------------------------------------------------------------------------

    public function index(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $userId  = (int) ($principal['user']['id'] ?? 0);
        $service = $this->service();
        $rooms   = $service->getRoomsForUser($userId);

        $pageTitle     = 'Chat';
        $activeSection = 'Chat';
        $flash         = $this->popFlash();

        ob_start();
        require $viewsPath . '/chat/index.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // GET /chat/rooms/create
    // -------------------------------------------------------------------------

    public function createForm(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $pageTitle     = 'New Room';
        $activeSection = 'Chat';
        $errors        = [];
        $old           = [];

        ob_start();
        require $viewsPath . '/chat/create.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // POST /chat/rooms
    // -------------------------------------------------------------------------

    public function store(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $userId = (int) ($principal['user']['id'] ?? 0);

        $input = [
            'name'        => $_POST['name']        ?? '',
            'description' => $_POST['description'] ?? '',
            'type'        => $_POST['type']         ?? '',
        ];

        $service = $this->service();

        // Validate before calling createRoom so we can return field-level errors.
        $errors = $service->validateRoom($input);

        if (!empty($errors)) {
            $old           = $input;
            $pageTitle     = 'New Room';
            $activeSection = 'Chat';

            ob_start();
            require $viewsPath . '/chat/create.php';
            $content = ob_get_clean();

            http_response_code(422);
            require $viewsPath . '/layouts/app.php';
            return;
        }

        $roomId = $service->createRoom($input, $userId);

        $this->auditLog($userId, 'chat.room.create', 'chat_room', $roomId, [
            'name' => $input['name'],
            'type' => $input['type'],
        ]);

        $this->flash('success', 'Room "' . htmlspecialchars($input['name']) . '" created.');
        header('Location: /chat/rooms/' . $roomId);
        exit;
    }

    // -------------------------------------------------------------------------
    // GET /chat/rooms/{id}
    // -------------------------------------------------------------------------

    public function show(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $userId  = (int) ($principal['user']['id'] ?? 0);
        $roomId  = (int) ($params['id'] ?? 0);
        $service = $this->service();

        if (!$service->canView($roomId, $userId)) {
            http_response_code(403);
            echo '<h1>403 — Access denied</h1>';
            return;
        }

        $room     = $service->getRoom($roomId);
        $messages = $service->getRecentMessages($roomId);
        $members  = $service->getMembersForRoom($roomId);
        $isMember = $service->isMember($roomId, $userId);

        // Update last_read_at for this member — silently no-ops for non-members.
        $service->markRead($roomId, $userId);

        $pageTitle     = htmlspecialchars($room['name']);
        $activeSection = 'Chat';
        $flash         = $this->popFlash();

        ob_start();
        require $viewsPath . '/chat/show.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // POST /chat/rooms/{id}/join
    // -------------------------------------------------------------------------

    public function join(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $userId  = (int) ($principal['user']['id'] ?? 0);
        $roomId  = (int) ($params['id'] ?? 0);
        $service = $this->service();

        try {
            $service->joinRoom($roomId, $userId);
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            header('Location: /chat');
            exit;
        }

        $room = $service->getRoom($roomId);
        $this->auditLog($userId, 'chat.room.join', 'chat_room', $roomId, [
            'name' => $room['name'] ?? '',
        ]);

        $this->flash('success', 'You joined the room.');
        header('Location: /chat/rooms/' . $roomId);
        exit;
    }

    // -------------------------------------------------------------------------
    // POST /chat/rooms/{id}/messages
    // -------------------------------------------------------------------------

    public function sendMessage(array $params = []): void
    {
        [$principal, $config, $viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $userId  = (int) ($principal['user']['id'] ?? 0);
        $roomId  = (int) ($params['id'] ?? 0);
        $service = $this->service();

        if (!$service->canView($roomId, $userId)) {
            http_response_code(403);
            echo '<h1>403 — Access denied</h1>';
            return;
        }

        $body = $_POST['body'] ?? '';

        try {
            $service->sendMessage($roomId, $userId, $body);
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            header('Location: /chat/rooms/' . $roomId);
            exit;
        }

        header('Location: /chat/rooms/' . $roomId . '#bottom');
        exit;
    }

    // -------------------------------------------------------------------------
    // GET /api/chat/unread
    // -------------------------------------------------------------------------

    /**
     * Return the total unread message count for the authenticated user as JSON.
     *
     * Used by the sidebar badge AJAX poller to refresh the count without a page reload.
     * Uses SessionAuth so failure returns 401 JSON (not an HTML redirect), matching
     * the AJAX fetch pattern used throughout the application.
     *
     * Permission check: users without chat.use receive {"unread_count": 0} rather than
     * a 403, because zero is the correct safe default and the badge JS does not need to
     * distinguish "no permission" from "no unread messages".
     *
     * Response: {"unread_count": N}
     */
    public function unreadCount(array $params = []): void
    {
        $principal   = $this->container->get('principal');
        $permissions = $principal['permissions'] ?? [];
        $userId      = (int) ($principal['user']['id'] ?? 0);

        if (!in_array('chat.use', $permissions, true)) {
            $this->json(['unread_count' => 0]);
            return;
        }

        $count = $this->service()->countUnreadForUser($userId);
        $this->json(['unread_count' => $count]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function service(): ChatService
    {
        $db = $this->container->get('db');

        return new ChatService(
            new ChatRoomRepository($db),
            new ChatRoomMemberRepository($db),
            new ChatMessageRepository($db)
        );
    }

    private function ctx(): array
    {
        $principal   = $this->container->get('principal');
        $config      = $this->container->get('config');
        $viewsPath   = __DIR__ . '/../../../Views';
        $appName     = $config['name'] ?? 'Kernel-Web';
        $displayName = $principal['user']['display_name'] ?? $principal['user']['username'];
        $permissions = $principal['permissions'];

        return [$principal, $config, $viewsPath, $appName, $displayName, $permissions];
    }

    private function auditLog(
        ?int   $actorId,
        string $action,
        string $entityType,
        int    $entityId,
        array  $meta = []
    ): void {
        try {
            (new AuditLogRepository($this->container->get('db')))
                ->log($actorId, $action, $entityType, $entityId, $meta);
        } catch (\Throwable) {
            // Intentionally swallowed — audit logging must never abort the main operation.
        }
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['chat_flash'] = ['type' => $type, 'message' => $message];
    }

    private function popFlash(): ?array
    {
        $flash = $_SESSION['chat_flash'] ?? null;
        unset($_SESSION['chat_flash']);
        return $flash;
    }
}
