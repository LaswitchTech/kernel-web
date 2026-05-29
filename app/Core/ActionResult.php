<?php

namespace App\Core;

/**
 * Immutable value object representing the result of an action execution.
 *
 * Returned by ActionExecutor::execute() to the caller (agent API, controller, etc.).
 */
readonly class ActionResult
{
    public function __construct(
        public string $actionId,
        public bool $success,
        public mixed $data = null,
        public string $error = '',
        public string $errorType = 'error',
        public int $entityId = 0,
        public ?string $agentId = null,
        public ?string $agentName = null,
        public string $createdAt = '',
    ) {
        if ($this->createdAt === '') {
            $this->createdAt = date('Y-m-d H:i:s');
        }
    }

    /**
     * Derive the audit action from the action ID.
     *
     * 'tasks.create' → 'task.create'
     */
    public function auditAction(): string
    {
        // Derive from action ID: "tasks.create" → "task.create"
        $parts = explode('.', $this->actionId, 2);
        if (isset($parts[1])) {
            // Singularize the domain (tasks → task, users → user).
            $domain = rtrim($parts[0], 's');
            return "{$domain}.{$parts[1]}";
        }
        return $this->actionId;
    }

    /**
     * Create a successful result.
     */
    public static function success(
        string $actionId,
        mixed $data,
        int $entityId = 0,
        string $agentId = '',
        string $agentName = '',
    ): self {
        return new self(
            actionId: $actionId,
            success: true,
            data: $data,
            entityId: $entityId,
            agentId: $agentId !== '' ? $agentId : null,
            agentName: $agentName !== '' ? $agentName : null,
            createdAt: date('Y-m-d H:i:s'),
        );
    }

    /**
     * Create a failure result.
     */
    public static function failure(
        string $actionId,
        string $error,
        string $errorType = 'error',
        mixed $data = null,
    ): self {
        return new self(
            actionId: $actionId,
            success: false,
            data: $data,
            error: $error,
            errorType: $errorType,
            createdAt: date('Y-m-d H:i:s'),
        );
    }

    /**
     * Create an approval-required result (HTTP 409).
     */
    public static function requiresApproval(
        string $actionId,
        string $approvalId,
        string $agentName = '',
    ): self {
        return new self(
            actionId: $actionId,
            success: false,
            data: ['approval_id' => $approvalId],
            error: 'approval_required',
            errorType: 'approval_required',
            agentName: $agentName !== '' ? $agentName : null,
            createdAt: date('Y-m-d H:i:s'),
        );
    }

    /**
     * Serialize to an array suitable for the agent API response.
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->actionId,
            'success' => $this->success,
            'error' => $this->error,
            'error_type' => $this->errorType,
            'entity_id' => $this->entityId,
            'created_at' => $this->createdAt,
        ];

        if ($this->success) {
            $result['data'] = $this->data;
        }

        if ($this->agentId !== null) {
            $result['agent_id'] = $this->agentId;
        }

        if ($this->agentName !== null) {
            $result['agent_name'] = $this->agentName;
        }

        return $result;
    }
}
