<?php

namespace App\Core;

/**
 * Immutable value object describing a single agent-callable action.
 *
 * Actions expose a service method with structured metadata so that
 * agents can discover, validate, and invoke them safely.
 *
 * Registration example:
 *   ActionRegistry::add(new ActionDefinition(
 *       id: 'tasks.create',
 *       name: 'Create Task',
 *       description: 'Create a new task with the given title and optional metadata.',
 *       service_class: TaskService::class,
 *       service_method: 'createFromInput',
 *       parameters: [...],
 *       permission: 'tasks.manage',
 *       risk_level: 'low',
 *       audit_entity_type: 'task',
 *       source: 'tasks',
 *   ));
 */
readonly class ActionDefinition
{
    /**
     * @param string $id Unique dot-notation identifier (e.g., 'tasks.create').
     * @param string $name Human-readable name.
     * @param string $description One-sentence description for agents.
     * @param string $service_class FQCN of the service class.
     * @param string $service_method Method name on the service.
     * @param array<int, array{
     *       name: string,
     *       type: string,
     *       required: bool,
     *       description: string,
     *       default: mixed,
     *       enum?: list<string|int>,
     *       min_length?: int,
     *       max_length?: int,
     *   }> Parameter definitions.
     * @param string $permission Required permission code (e.g., 'tasks.manage').
     * @param string $risk_level One of: 'low', 'medium', 'high', 'critical'.
     * @param bool $requires_approval Whether human approval is required before execution.
     * @param string $audit_entity_type Entity type for audit log (e.g., 'task').
     * @param array<string, mixed> Agent-facing metadata.
     * @param string $source Plugin name or 'core'.
     * @param int $order Registration order for sorting.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $service_class,
        public string $service_method,
        public array $parameters,
        public string $permission,
        public string $risk_level = 'low',
        public bool $requires_approval = false,
        public string $audit_entity_type = 'action',
        public array $metadata = [],
        public string $source = 'core',
        public int $order = 50,
    ) {
        if (!in_array($risk_level, ['low', 'medium', 'high', 'critical'], true)) {
            throw new \InvalidArgumentException(
                "Invalid risk_level '{$risk_level}'. Must be one of: low, medium, high, critical"
            );
        }
    }

    /**
     * Whether this action requires human approval before agent execution.
     *
     * High and critical risk levels automatically require approval.
     * The `requires_approval` constructor param can be explicitly set to false
     * for testing purposes but should always be true for high/critical.
     */
    public function needsApproval(): bool
    {
        return $this->requires_approval || in_array($this->risk_level, ['high', 'critical'], true);
    }

    /**
     * Serialize to an array suitable for the agent API response.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'service' => [
                'class' => $this->service_class,
                'method' => $this->service_method,
            ],
            'permission' => $this->permission,
            'risk_level' => $this->risk_level,
            'requires_approval' => $this->requires_approval,
            'audit_entity_type' => $this->audit_entity_type,
            'parameters' => $this->parameters,
            'metadata' => $this->metadata,
            'source' => $this->source,
        ];
    }
}
