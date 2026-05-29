<?php

namespace App\Core;

/**
 * In-memory action registry.
 *
 * Agents (and the executor) discover available actions through this registry.
 * Plugins register actions via a `plugins.bootstrap` hook; the kernel registers
 * core actions during initialization.
 *
 * Registration example:
 *   ActionRegistry::add(new ActionDefinition(
 *       id: 'tasks.create',
 *       name: 'Create Task',
 *       ...
 *   ));
 *
 * Discovery example:
 *   $actions = ActionRegistry::getAvailable($userPermissions);
 */
class ActionRegistry
{
    /** @var array<string, ActionDefinition> keyed by action ID */
    private static array $actions = [];

    /**
     * Register an action.
     *
     * @throws \RuntimeException if an action with the same ID is already registered.
     */
    public static function add(ActionDefinition $action): void
    {
        if (isset(self::$actions[$action->id])) {
            throw new \RuntimeException(
                "Action '{$action->id}' is already registered by '" . self::$actions[$action->id]->source . "'."
            );
        }
        self::$actions[$action->id] = $action;
    }

    /**
     * Get a single action by ID.
     *
     * @return ActionDefinition|null Null if not found.
     */
    public static function get(string $id): ?ActionDefinition
    {
        return self::$actions[$id] ?? null;
    }

    /**
     * Get all actions visible to the given permissions.
     *
     * Actions whose `permission` field is non-empty are filtered: only actions
     * whose permission code appears in $userPermissions are included.
     *
     * @param string[] $userPermissions
     * @return ActionDefinition[]
     */
    public static function getAvailable(array $userPermissions): array
    {
        $result = [];
        foreach (self::$actions as $action) {
            if ($action->permission !== '' && !in_array($action->permission, $userPermissions, true)) {
                continue;
            }
            $result[] = $action;
        }

        // Sort by order (lower first), then by ID as tiebreaker.
        usort($result, function (ActionDefinition $a, ActionDefinition $b): int {
            $cmp = $a->order <=> $b->order;
            return $cmp !== 0 ? $cmp : ($a->id <=> $b->id);
        });

        return $result;
    }

    /**
     * Get all actions from a specific plugin.
     *
     * @return ActionDefinition[]
     */
    public static function getForPlugin(string $plugin): array
    {
        return array_values(array_filter(
            self::$actions,
            fn(ActionDefinition $a): bool => $a->source === $plugin,
        ));
    }

    /**
     * Check if an action is registered.
     */
    public static function has(string $id): bool
    {
        return isset(self::$actions[$id]);
    }

    /**
     * Get all registered actions (no filtering).
     *
     * @return ActionDefinition[]
     */
    public static function getAll(): array
    {
        return self::$actions;
    }

    /**
     * Clear all registered actions (for testing / isolation).
     */
    public static function clear(): void
    {
        self::$actions = [];
    }
}
