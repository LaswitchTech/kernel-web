<?php

namespace App\Core;

use App\Models\AuditLogRepository;

/**
 * Shared executor that enforces the action contract pipeline:
 *   1. Permission check
 *   2. Approval gate (if high-risk + agent)
 *   3. Parameter validation
 *   4. Service invocation
 *   5. Audit logging
 *
 * Both human-facing controllers and agent-facing endpoints delegate
 * through this executor. Agents must not bypass permissions, write
 * directly to DB, or modify config outside approved services.
 */
class ActionExecutor
{
    private DatabaseInterface $db;
    private object $gate;
    private ?Container $container;
    private AuditLogRepository $auditLog;

    public function __construct(
        DatabaseInterface $db,
        object $gate,
        ?Container $container = null,
    ) {
        $this->db = $db;
        $this->gate = $gate;
        $this->container = $container;
        $this->auditLog = new AuditLogRepository($db);
    }

    /**
     * Execute a registered action.
     *
     * @param string $actionId Dot-notation action ID (e.g., 'tasks.create').
     * @param ActionCallContext $context Caller identity and authority.
     * @param array<string, mixed> $input Raw input to validate and pass to the service.
     * @return ActionResult
     */
    public function execute(
        string $actionId,
        ActionCallContext $context,
        array $input,
    ): ActionResult {
        // 1. Retrieve action from registry.
        $action = ActionRegistry::get($actionId);
        if ($action === null) {
            return ActionResult::failure($actionId, "Unknown action: {$actionId}", 'unknown_action');
        }

        // 2. Permission check.
        if (!$this->gate->can($context->getPrincipal(), $action->permission)) {
            return ActionResult::failure(
                $actionId,
                'Permission denied: requires "' . $action->permission . '"',
                'permission_denied',
            );
        }

        // 3. Approval gate (high-risk + agent).
        if ($action->needsApproval() && !$context->isHuman()) {
            $approvalResult = $this->checkApproval($action, $context);
            if ($approvalResult !== null) {
                return $approvalResult;
            }
        }

        // 4. Validate input against parameter schema.
        $validated = $this->validateInput($action->parameters, $input);
        if (!$validated['valid']) {
            return ActionResult::failure(
                $actionId,
                'Validation error: ' . implode(', ', $validated['errors']),
                'validation_error',
            );
        }

        // 5. Resolve and invoke service.
        try {
            $service = $this->resolveService($action->service_class);
            $result = $service->{$action->service_method}($validated['data']);
            $entityId = $this->extractEntityId($result, $action->audit_entity_type);
        } catch (\Throwable $e) {
            return ActionResult::failure(
                $actionId,
                'Execution error: ' . $e->getMessage(),
                'execution_error',
            );
        }

        // 6. Audit log.
        $this->logAudit($context, $action, $entityId, $validated['data'], $result);

        // 7. Return success result.
        return ActionResult::success(
            actionId: $actionId,
            data: $result,
            entityId: $entityId,
            agentId: $context->getAgentId() ?? '',
            agentName: $context->getAgentName() ?? '',
        );
    }

    /**
     * Check approval gate for high-risk actions.
     *
     * Returns null if execution may proceed, or an ActionResult(409) if approval is needed.
     */
    private function checkApproval(
        ActionDefinition $action,
        ActionCallContext $context,
    ): ?ActionResult {
        // TODO: Implement approval persistence via task_activity meta JSON or a
        // dedicated approval table in a follow-up task. For now, agents can always
        // execute — the infrastructure gate exists (requires_approval flag) but
        // the persistence layer is deferred.
        return null;
    }

    /**
     * Validate input against parameter definitions.
     *
     * Applies type coercion, required checks, enum validation, length constraints,
     * and fills defaults for missing optional parameters.
     *
     * @return array{valid: bool, data: array<string, mixed>, errors: string[]}
     */
    private function validateInput(array $parameters, array $input): array
    {
        $errors = [];
        $data = [];

        foreach ($parameters as $param) {
            $name = $param['name'];
            $hasValue = array_key_exists($name, $input);
            $value = $hasValue ? $input[$name] : ($param['default'] ?? null);

            // Required check.
            if ($param['required'] && !$hasValue && $value === null) {
                $errors[] = "Parameter '{$name}' is required.";
                continue;
            }

            // Skip further validation for missing optional params.
            if (!$hasValue && $value === null) {
                continue;
            }

            // Type coercion.
            $value = $this->coerceType($value, $param['type']);

            // Enum check.
            if (isset($param['enum']) && !in_array($value, $param['enum'], true)) {
                $errors[] = "Parameter '{$name}' must be one of: " . implode(', ', $param['enum']);
                continue;
            }

            // Length constraints (string only).
            if (is_string($value)) {
                if (isset($param['min_length']) && mb_strlen($value) < $param['min_length']) {
                    $errors[] = "Parameter '{$name}' must be at least {$param['min_length']} characters.";
                    continue;
                }
                if (isset($param['max_length']) && mb_strlen($value) > $param['max_length']) {
                    $errors[] = "Parameter '{$name}' must be at most {$param['max_length']} characters.";
                    continue;
                }
            }

            $data[$name] = $value;
        }

        return [
            'valid' => empty($errors),
            'data' => $data,
            'errors' => $errors,
        ];
    }

    /**
     * Coerce a value to the declared type.
     */
    private function coerceType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_array($value) ? $value : [],
            'nullable_string' => $value === null ? null : (string) $value,
            'nullable_int' => $value === null ? null : (int) $value,
            default => $value,
        };
    }

    /**
     * Resolve a service class to an instance.
     *
     * Checks the container first (wired by plugins during bootstrap),
     * then falls back to direct instantiation with DB.
     */
    private function resolveService(string $serviceClass): object
    {
        // Check the container (keyed by a service name, not the FQCN).
        if ($this->container !== null) {
            // Try common key patterns for this class.
            foreach (["{$serviceClass}", strtolower((new \ReflectionClass($serviceClass))->getShortName())] as $key) {
                if ($this->container->has($key)) {
                    return $this->container->get($key);
                }
            }
        }

        // Fall back to direct instantiation with DB as first argument.
        $reflection = new \ReflectionClass($serviceClass);
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() === 1) {
            $firstParam = $constructor->getParameters()[0];
            if ($firstParam->getType() !== null && $firstParam->getType()->getName() === DatabaseInterface::class) {
                return new $serviceClass($this->db);
            }
        }

        return new $serviceClass();
    }

    /**
     * Extract the entity ID from the service return value.
     *
     * Handles common return patterns:
     *   - int (the new entity ID directly)
     *   - array with 'id' key
     *   - null (no entity)
     */
    private function extractEntityId(mixed $result, string $entityType): int
    {
        if (is_int($result)) {
            return $result;
        }
        if (is_array($result) && isset($result['id'])) {
            return (int) $result['id'];
        }
        return 0;
    }

    /**
     * Log the action to the admin audit log.
     *
     * Audit failures are silently ignored — the main operation must not be
     * affected by audit logging failures.
     */
    private function logAudit(
        ActionCallContext $context,
        ActionDefinition $action,
        int $entityId,
        array $input,
        mixed $result,
    ): void {
        try {
            $actorId = $context->getActorId();

            // Derive the audit action from the action ID.
            $auditAction = $this->deriveAuditAction($action->id);

            $meta = [
                'action_id' => $action->id,
                'action_name' => $action->name,
                'risk_level' => $action->risk_level,
                'input_summary' => $this->summarizeInput($action, $input),
            ];

            if ($context->getAgentId() !== null) {
                $meta['agent_id'] = $context->getAgentId();
                $meta['agent_name'] = $context->getAgentName();
                $meta['caller_type'] = 'agent';
            } else {
                $meta['caller_type'] = 'human';
            }

            if ($context->getRelatedTaskId() !== null) {
                $meta['related_task_id'] = $context->getRelatedTaskId();
            }

            // Entity ID 0 means no entity (e.g., a read-only action).
            if ($entityId > 0) {
                $this->auditLog->log(
                    $actorId,
                    $result->auditAction(),
                    $action->audit_entity_type,
                    $entityId,
                    $meta,
                );
            }
        } catch (\Throwable $e) {
            // Audit failures must never break the main operation.
            error_log("[ActionExecutor] Audit log failed for {$action->id}: " . $e->getMessage());
        }
    }

    /**
     * Derive the audit action string from the action ID.
     *
     * 'tasks.create' → 'task.create'
     * 'users.delete' → 'user.delete'
     */
    private function deriveAuditAction(string $actionId): string
    {
        $parts = explode('.', $actionId, 2);
        if (count($parts) !== 2) {
            return $actionId;
        }

        // Singularize the domain (tasks → task, users → user).
        $domain = rtrim($parts[0], 's');

        return "{$domain}.{$parts[1]}";
    }

    /**
     * Create a short summary of the input for audit purposes.
     */
    private function summarizeInput(ActionDefinition $action, array $input): string
    {
        // Use the metadata input_summary if provided, otherwise list keys.
        if (isset($action->metadata['input_summary'])) {
            return $action->metadata['input_summary'];
        }

        $keys = array_keys($input);
        return implode(', ', $keys);
    }
}
