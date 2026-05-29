<?php

namespace App\Core;

/**
 * Interface describing who is calling an action and with what authority.
 *
 * Two implementations:
 *   - HumanCallContext  — from a web request (principal in container)
 *   - AgentCallContext  — from the agent API (explicit agent identity)
 */
interface ActionCallContext
{
    /** Whether this caller is a human via web UI. */
    public function isHuman(): bool;

    /** Actor/user ID of the caller, or null if unknown. */
    public function getActorId(): ?int;

    /** Permission list of the caller. */
    public function getPermissions(): array;

    /** Full principal array. */
    public function getPrincipal(): array;

    /** Agent identifier string (e.g., 'agent:claude-code'), or null for humans. */
    public function getAgentId(): ?string;

    /** Agent display name, or null for humans. */
    public function getAgentName(): ?string;

    /** Related task ID from the caller context, or null. */
    public function getRelatedTaskId(): ?int;
}

/**
 * Human call context built from the principal stored in the container.
 */
final readonly class HumanCallContext implements ActionCallContext
{
    public function __construct(
        private array $principal,
    ) {
    }

    public function isHuman(): bool
    {
        return true;
    }

    public function getActorId(): ?int
    {
        return $this->principal['user']['id'] ?? null;
    }

    public function getPermissions(): array
    {
        return $this->principal['permissions'] ?? [];
    }

    public function getPrincipal(): array
    {
        return $this->principal;
    }

    public function getAgentId(): ?string
    {
        return null;
    }

    public function getAgentName(): ?string
    {
        return null;
    }

    public function getRelatedTaskId(): ?int
    {
        return null;
    }
}

/**
 * Agent call context built from an agent-facing API request payload.
 */
final readonly class AgentCallContext implements ActionCallContext
{
    private function __construct(
        private ?string $agentId,
        private ?string $agentName,
        private ?int $actorId,
        private array $permissions,
        private ?int $relatedTaskId,
    ) {
    }

    public function isHuman(): bool
    {
        return false;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getPrincipal(): array
    {
        return [
            'user' => ['id' => $this->actorId],
            'auth_method' => 'agent',
            'permissions' => $this->permissions,
        ];
    }

    public function getAgentId(): ?string
    {
        return $this->agentId;
    }

    public function getAgentName(): ?string
    {
        return $this->agentName;
    }

    public function getRelatedTaskId(): ?int
    {
        return $this->relatedTaskId;
    }

    /**
     * Build an agent context from an API request payload.
     *
     * Expected payload keys:
     *   - actor_type: 'agent' (required for agent context)
     *   - actor_id: agent identifier string
     *   - agent_name: display name
     *   - related_task_id: optional task ID
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            agentId: isset($payload['actor_id']) ? (string) $payload['actor_id'] : null,
            agentName: $payload['agent_name'] ?? null,
            actorId: isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            permissions: $payload['permissions'] ?? [],
            relatedTaskId: isset($payload['related_task_id']) ? (int) $payload['related_task_id'] : null,
        );
    }
}
