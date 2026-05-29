# API/Action Contract Conventions

## Overview

Actions are the contract between Kernel-Web plugins and agent callers. An action exposes a service method with structured metadata (permissions, parameters, risk level, audit target) so that both humans and agents can invoke business logic through a single, auditable path.

**Core principle:** agents must use the same services as humans. There is no separate hidden write path.

## Why Actions

Without actions, plugins expose methods directly and agents call them ad-hoc. This leads to:

- No discoverability (agents don't know what's available)
- No parameter validation (agents send wrong data)
- No audit trail (no connection to admin_audit_log)
- No permission enforcement (bypass Gate checks)

Actions solve all four by centralizing metadata and enforcement.

## Architecture

```
Agent/Agent API  →  ActionExecutor  →  Service::method()  →  Repository → DB
                        ↑                    ↑
                    Gate::can()         AuditLogRepository::log()
                        ↑
                 ActionRegistry::get()
```

### Registration

Plugins register actions during `plugins.bootstrap` via a static method:

```php
ActionRegistry::add(new ActionDefinition(
    id: 'tasks.create',
    name: 'Create Task',
    description: 'Create a new task with a title and optional metadata.',
    service_class: TaskService::class,
    service_method: 'createFromInput',
    parameters: [
        ['name' => 'title', 'type' => 'string', 'required' => true,
         'description' => 'Task title', 'min_length' => 1, 'max_length' => 255],
        ['name' => 'status', 'type' => 'string', 'required' => false,
         'description' => 'Initial status', 'default' => 'open',
         'enum' => ['open', 'in_progress', 'completed', 'canceled']],
    ],
    permission: 'tasks.manage',
    risk_level: 'low',
    audit_entity_type: 'task',
    metadata: ['scope' => 'tasks', 'category' => 'crud'],
    source: 'tasks',
    order: 10,
));
```

### Execution Pipeline

The executor runs this pipeline for every action:

1. **Resolve** — Look up the ActionDefinition in ActionRegistry
2. **Permission** — Check `Gate::can(principal, action.permission)`
3. **Approval** — If `requires_approval` is true and caller is an agent, check for pending approval (deferred to follow-up)
4. **Validate** — Validate input against parameter schema (type coercion, required checks, enum, length)
5. **Invoke** — Call `$service->{$method}($validatedInput)`
6. **Audit** — Log to `admin_audit_log` with action ID, risk level, caller type, agent info
7. **Return** — ActionResult (success/failure)

### Risk Levels

| Level | Meaning | Approval Required |
|-------|---------|------------------|
| `low` | Read or safe write (create, list) | No |
| `medium` | Reversible write (update) | No |
| `high` | Irreversible write (delete) | Yes (agent) |
| `critical` | System-level change (config, permissions) | Yes |

`high` and `critical` automatically set `requires_approval = true`. Humans can execute high-risk actions without approval (UI confirmation handles this).

## Agent API

### GET /api/actions

List all actions visible to the caller's permissions.

**Response:**
```json
[
  {
    "id": "tasks.create",
    "name": "Create Task",
    "description": "Create a new task with a title and optional metadata.",
    "service": {
      "class": "App\\Plugins\\Tasks\\TaskService",
      "method": "createFromInput"
    },
    "permission": "tasks.manage",
    "risk_level": "low",
    "requires_approval": false,
    "audit_entity_type": "task",
    "parameters": [
      {
        "name": "title",
        "type": "string",
        "required": true,
        "description": "Task title",
        "min_length": 1,
        "max_length": 255
      }
    ],
    "metadata": {
      "scope": "tasks",
      "category": "crud",
      "input_summary": "title, description, status, priority"
    },
    "source": "tasks"
  }
]
```

### GET /api/actions/{id}

Get a single action's metadata.

**Response:** Same as above but wrapped in an object (not array).

### POST /api/actions/{id}/execute

Execute an action.

**Request body:**
```json
{
  "actor_type": "agent",
  "actor_id": "agent:claude-code",
  "agent_name": "Claude Code",
  "related_task_id": 42,
  "input": {
    "title": "Review PR #123",
    "status": "in_progress"
  }
}
```

**Success response (200):**
```json
{
  "id": "tasks.create",
  "success": true,
  "error": "",
  "error_type": null,
  "entity_id": 123,
  "agent_id": "agent:claude-code",
  "agent_name": "Claude Code",
  "created_at": "2026-05-29 14:30:00",
  "data": 123
}
```

**Permission denied (400):**
```json
{
  "id": "tasks.create",
  "success": false,
  "error": "Permission denied: requires \"tasks.manage\"",
  "error_type": "permission_denied",
  "entity_id": 0,
  "agent_id": "agent:claude-code",
  "agent_name": "Claude Code",
  "created_at": "2026-05-29 14:30:00",
  "data": null
}
```

**Validation error (400):**
```json
{
  "id": "tasks.create",
  "success": false,
  "error": "Validation error: Parameter 'title' is required.",
  "error_type": "validation_error",
  "entity_id": 0,
  "created_at": "2026-05-29 14:30:00",
  "data": null
}
```

**Approval required (409):**
```json
{
  "id": "tasks.delete",
  "success": false,
  "error": "approval_required",
  "error_type": "approval_required",
  "entity_id": 0,
  "agent_name": "Claude Code",
  "created_at": "2026-05-29 14:30:00",
  "data": {
    "approval_id": "appr_xxx"
  }
}
```

## Writing Actions for a New Plugin

### Step 1: Add bootstrap hook to plugin.json

```json
{
    "hooks": [
        {
            "hook": "plugins.bootstrap",
            "content": {
                "priority": 5,
                "callback": "App\\Plugins\\{Namespace}\\{Service}::registerActions"
            }
        }
    ]
}
```

### Step 2: Create registerActions() in your service

```php
use App\Core\ActionDefinition;
use App\Core\ActionRegistry;

public static function registerActions(): void
{
    $prefix = 'myplugin';

    ActionRegistry::add(new ActionDefinition(
        id: "{$prefix}.do_something",
        name: 'Do Something',
        description: 'Do the thing.',
        service_class: self::class,
        service_method: 'doSomethingFromInput',
        parameters: [
            ['name' => 'target', 'type' => 'string', 'required' => true,
             'description' => 'Target identifier', 'max_length' => 255],
        ],
        permission: 'myplugin.manage',
        risk_level: 'low',
        audit_entity_type: 'my_entity',
        metadata: ['scope' => 'myplugin', 'category' => 'action'],
        source: 'myplugin',
        order: 10,
    ));
}
```

### Step 3: Implement the service method

```php
public static function doSomethingFromInput(array $input): array
{
    // Build your service chain here.
    $db = \App\Core\Config::load('database');
    // ... create repo, service, call method ...
    return ['success' => true];
}
```

### Step 4: Register permission in plugin.json

```json
{
    "permissions": ["myplugin.manage"]
}
```

## Supported Parameter Types

| Type | Coercion | Nullable |
|------|----------|----------|
| `string` | `(string)` | No |
| `int` / `integer` | `(int)` | No |
| `float` / `double` | `(float)` | No |
| `bool` / `boolean` | `(bool)` | No |
| `array` | `is_array() ? $v : []` | No |
| `nullable_string` | `(string)` or `null` | Yes |
| `nullable_int` | `(int)` or `null` | Yes |

## Parameter Schema Fields

Each parameter in the `parameters` array supports:

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `name` | string | Yes | — | Parameter name (used in error messages) |
| `type` | string | Yes | — | Type for coercion (see table above) |
| `required` | bool | Yes | `false` | Whether the parameter must be present |
| `description` | string | Yes | `''` | Description for agents |
| `default` | mixed | No | `null` | Default value if not present |
| `enum` | list | No | — | Allowed values |
| `min_length` | int | No | — | Minimum string length |
| `max_length` | int | No | — | Maximum string length |

## Audit Logging

Every successful action execution is logged to `admin_audit_log` with:

- `action` — derived from action ID (`tasks.create` → `task.create`)
- `entity_type` — from `audit_entity_type` field
- `entity_id` — extracted from the service return value (int or array['id'])
- `meta` — includes `action_id`, `action_name`, `risk_level`, `input_summary`, `caller_type`, `agent_id`, `agent_name`, `related_task_id`

## Testing Actions

### Unit test for registry

```php
ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(/* ... */));
$actions = ActionRegistry::getAvailable(['tasks.manage']);
assert(count($actions) === 1);
assert($actions[0]->id === 'tasks.create');
```

### Unit test for executor

```php
$executor = new ActionExecutor($db, $gate);
$context = new AgentCallContext('agent:test', 'Test', 1, ['tasks.manage'], null);
$result = $executor->execute('tasks.create', $context, ['title' => 'Test']);
assert($result->success === true);
```

### Integration test for agent API

```php
// POST /api/actions/tasks.create/execute
// With auth token → expect 200 + entity_id
// Without auth token → expect 401
// With wrong permission → expect 400 + permission_denied
```

## Design Decisions

### Why a static registry, not a manifest field?

Actions need runtime introspection (discovery API). A manifest field is static; the registry enables dynamic query surfaces. The `plugins.bootstrap` hook is the established pattern for runtime registration (used by SMTP, Telico, etc.).

### Why not auto-generate API routes?

Auto-generation introduces magic. Plugin authors explicitly register routes in `routes.php` (existing convention). The action registration tells the executor *how* to call the service; the route tells the router *where* to expose it.

### Why static method wrappers instead of container resolution?

Service classes (like TaskService) are instantiated by the plugin loader with injected dependencies (TaskRepository, TaskActivityRepository). The executor doesn't know these dependencies. Static wrappers with internal service construction are explicit and self-contained.

### Why does the executor not require the container?

The container is optional — it allows the executor to work outside of bootstrap (e.g., in CLI scripts or tests). When the container is provided, the executor tries to resolve services from it first.

### Why is `high`/`critical` risk level auto-approved for humans?

Humans have UI confirmation (modal dialogs, double-checks). The approval gate is specifically for agents that need an explicit approval step. The `requires_approval` flag is automatically set for `high` and `critical` but only enforced for non-human callers.
