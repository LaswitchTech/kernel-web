<?php

namespace App\Controllers;

use App\Core\ActionRegistry;
use App\Core\ActionCallContext;
use App\Core\AgentCallContext;
use App\Core\Controller;

/**
 * Agent-facing API for action discovery and execution.
 *
 * Routes:
 *   GET    /api/actions             — list available actions
 *   GET    /api/actions/{id}        — get single action
 *   POST   /api/actions/{id}/execute — execute an action
 *
 * All endpoints require SessionAuth (JSON 401 on failure).
 */
class ActionApiController extends Controller
{
    /**
     * GET /api/actions — List available actions filtered by caller's permissions.
     */
    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $actions = ActionRegistry::getAvailable($principal['permissions'] ?? []);
        $this->json(array_map(fn($a) => $a->toArray(), $actions));
    }

    /**
     * GET /api/actions/{id} — Get a single action's metadata.
     */
    public function show(array $params = []): void
    {
        $action = ActionRegistry::get($params['id'] ?? '');
        if ($action === null) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        $this->json($action->toArray());
    }

    /**
     * POST /api/actions/{id}/execute — Execute an action with input + caller context.
     */
    public function execute(array $params = []): void
    {
        $actionId = $params['id'] ?? '';
        $action = ActionRegistry::get($actionId);
        if ($action === null) {
            $this->json(['error' => 'not_found', 'action_id' => $actionId], 404);
            return;
        }

        // Build agent call context from request payload.
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $context = AgentCallContext::fromArray($payload);

        // Get executor from container.
        if (!$this->container->has('action_executor')) {
            $this->json(['error' => 'action_executor_not_configured'], 500);
            return;
        }
        $executor = $this->container->get('action_executor');

        $result = $executor->execute($actionId, $context, $payload['input'] ?? []);
        $code = $result->error_type === 'approval_required' ? 409 : ($result->success ? 200 : 400);
        $this->json($result->toArray(), $code);
    }
}
