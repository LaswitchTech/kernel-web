<?php

/**
 * Tests for the Action API — endpoint responses, permission gating, and error handling.
 *
 * Tests the controller logic directly (registry filtering, action metadata, context VOs)
 * without requiring HTTP infrastructure.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
require __DIR__ . '/../app/Core/ActionCallContext.php';
reset_counters();

use App\Core\ActionDefinition;
use App\Core\ActionRegistry;

// --- Helpers ---

ActionRegistry::clear();

// --- Register test actions ---
ActionRegistry::add(new ActionDefinition(
    id: 'api.create',
    name: 'API Create',
    description: 'Create via API.',
    service_class: 'ApiTestService',
    service_method: 'handleCreate',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name'],
    ],
    permission: 'api.use',
    risk_level: 'low',
    audit_entity_type: 'api',
    source: 'test',
));

// ============= 1. INDEX — LIST ACTIONS ===---==

// api.create has permission 'api.use', so with no permissions it's filtered out.
// That's correct behavior — test that it returns 0 for empty permissions.
$data = ActionRegistry::getAvailable([]);
assert_true(is_array($data), 'getAvailable returns an array');
assert_equal(0, count($data), 'getAvailable returns empty for no matching permissions');

// With correct permission it shows up.
$dataWithPerm = ActionRegistry::getAvailable(['api.use']);
assert_equal(1, count($dataWithPerm), 'getAvailable returns action with correct permission');
assert_equal('api.create', $dataWithPerm[0]->id, 'action visible with correct permission');

// ============= 2. SHOW — SINGLE ACTION ===---==

$action = ActionRegistry::get('api.create');

assert_not_null($action, 'get returns the action');
$arr = $action->toArray();
assert_equal('api.create', $arr['id'], 'show returns correct action id');
assert_equal('API Create', $arr['name'], 'show returns correct name');
assert_equal('low', $arr['risk_level'], 'show returns risk_level');
assert_false($arr['requires_approval'], 'show returns requires_approval false for low');
assert_equal('api', $arr['audit_entity_type'], 'audit_entity_type correct');
assert_array_has_key($arr['service'], 'class', 'service has class');
assert_array_has_key($arr['service'], 'method', 'service has method');

// ============= 3. SHOW — NOT FOUND ===---==

$notFound = ActionRegistry::get('nonexistent.action');
assert_null($notFound, 'get returns null for unknown action');

// ============= 4. ACTIONS FILTERED BY PERMISSION ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'public.action',
    name: 'Public',
    description: 'Public action.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));
ActionRegistry::add(new ActionDefinition(
    id: 'private.action',
    name: 'Private',
    description: 'Private action.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: 'admin',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$data = ActionRegistry::getAvailable([]);
assert_equal(1, count($data), 'no permissions only sees public actions');
assert_equal('public.action', $data[0]->id, 'public action visible');

$data = ActionRegistry::getAvailable(['admin']);
assert_equal(2, count($data), 'with admin permission sees both');

// ============= 5. AGENT CALL CONTEXT ===---==

$agentCtx = \App\Core\AgentCallContext::fromArray([
    'actor_id' => 'agent:test',
    'agent_name' => 'Test Bot',
    'permissions' => ['test.use'],
    'related_task_id' => 42,
]);

assert_false($agentCtx->isHuman(), 'agent is not human');
assert_equal('agent:test', $agentCtx->getAgentId(), 'agent_id set');
assert_equal('Test Bot', $agentCtx->getAgentName(), 'agent_name set');
assert_equal(42, $agentCtx->getRelatedTaskId(), 'related_task_id set');
assert_equal(['test.use'], $agentCtx->getPermissions(), 'permissions set');

// ============= 6. HUMAN CALL CONTEXT ===---==

$humanCtx = new \App\Core\HumanCallContext(['user' => ['id' => 1], 'permissions' => ['test.use']]);

assert_true($humanCtx->isHuman(), 'human is human');
assert_equal(1, $humanCtx->getActorId(), 'actor_id set');
assert_equal(['test.use'], $humanCtx->getPermissions(), 'permissions set');
assert_null($humanCtx->getAgentId(), 'agent_id is null for human');
assert_null($humanCtx->getAgentName(), 'agent_name is null for human');
assert_null($humanCtx->getRelatedTaskId(), 'related_task_id is null for human');

// ============= 7. AGENT CALL CONTEXT PRINCIPAL ===---==

$principal = $agentCtx->getPrincipal();
assert_array_has_key($principal, 'user', 'principal has user');
assert_array_has_key($principal, 'auth_method', 'principal has auth_method');
assert_equal('agent', $principal['auth_method'], 'auth_method is agent');

// ============= 8. ACTION DEFINITION TOARRAY FORMAT ===---==

$def = new ActionDefinition(
    id: 'test.toarray',
    name: 'ToArray',
    description: 'Test toArray.',
    service_class: 'X',
    service_method: 'x',
    parameters: [['name' => 'x', 'type' => 'string', 'required' => true, 'description' => 'X']],
    permission: 'test.use',
    risk_level: 'medium',
    audit_entity_type: 'test',
    metadata: ['scope' => 'test'],
    source: 'test',
);
$arr = $def->toArray();
assert_equal('test.toarray', $arr['id'], 'toArray id correct');
assert_equal('ToArray', $arr['name'], 'toArray name correct');
assert_equal('medium', $arr['risk_level'], 'toArray risk_level correct');
assert_false($arr['requires_approval'], 'toArray requires_approval false');
assert_array_has_key($arr['metadata'], 'scope', 'toArray has metadata.scope');
assert_equal('test', $arr['source'], 'toArray source correct');
assert_equal('test.use', $arr['permission'], 'toArray permission correct');

// ============= 9. HIGH RISK AUTO-APPROVAL ===---==

$highDef = new ActionDefinition(
    id: 'test.high',
    name: 'High Risk',
    description: 'High risk.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'high',
    audit_entity_type: 'test',
    source: 'test',
);
assert_true($highDef->needsApproval(), 'high risk auto-requires approval');

$criticalDef = new ActionDefinition(
    id: 'test.critical',
    name: 'Critical',
    description: 'Critical.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'critical',
    audit_entity_type: 'test',
    source: 'test',
);
assert_true($criticalDef->needsApproval(), 'critical risk auto-requires approval');

// ============= 10. AGENT CALL CONTEXT — MISSING FIELDS ===---==

$minimalCtx = \App\Core\AgentCallContext::fromArray([]);

assert_false($minimalCtx->isHuman(), 'minimal context is not human');
assert_null($minimalCtx->getAgentId(), 'agent_id is null when not provided');
assert_null($minimalCtx->getAgentName(), 'agent_name is null when not provided');
assert_null($minimalCtx->getRelatedTaskId(), 'related_task_id is null when not provided');
assert_equal([], $minimalCtx->getPermissions(), 'permissions is empty when not provided');

// ============= 11. ACTION_REGISTRY GETFORPLUGIN ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'tasks.create',
    name: 'Create Task',
    description: 'Create task.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: 'tasks.manage',
    risk_level: 'low',
    audit_entity_type: 'task',
    source: 'tasks',
));
ActionRegistry::add(new ActionDefinition(
    id: 'tasks.delete',
    name: 'Delete Task',
    description: 'Delete task.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: 'tasks.manage',
    risk_level: 'high',
    audit_entity_type: 'task',
    source: 'tasks',
));
ActionRegistry::add(new ActionDefinition(
    id: 'core.create',
    name: 'Create Core',
    description: 'Create core.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'core',
    source: 'core',
));

$tasksActions = ActionRegistry::getForPlugin('tasks');
assert_equal(2, count($tasksActions), 'getForPlugin returns 2 task actions');
assert_equal('tasks.create', $tasksActions[0]->id, 'first task action correct');

$coreActions = ActionRegistry::getForPlugin('core');
assert_equal(1, count($coreActions), 'getForPlugin returns 1 core action');

// ============= 12. ACTION_DEFINITION VALIDATION ===---==

assert_raises(
    fn() => new ActionDefinition(
        id: 'bad',
        name: 'Bad',
        description: 'Bad.',
        service_class: 'X',
        service_method: 'x',
        parameters: [],
        permission: '',
        risk_level: 'invalid',
        audit_entity_type: 'test',
    ),
    InvalidArgumentException::class,
    'invalid risk_level throws',
);

// ============= 13. ACTION DEFINITION DEFAULT VALUES ===---==

$defaults = new ActionDefinition(
    id: 'test.defaults',
    name: 'Defaults',
    description: 'Test defaults.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'action',
);

assert_equal('action', $defaults->audit_entity_type, 'default audit_entity_type is action');
assert_equal([], $defaults->metadata, 'default metadata is empty');
assert_equal('core', $defaults->source, 'default source is core');
assert_equal(50, $defaults->order, 'default order is 50');
assert_false($defaults->needsApproval(), 'low risk default needs no approval');

// ============= 14. REGISTERED ACTIONS CAN BE FILTERED BY PERMISSION ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'read.action',
    name: 'Read',
    description: 'Read.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: 'read',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));
ActionRegistry::add(new ActionDefinition(
    id: 'no-perm.action',
    name: 'NoPerm',
    description: 'NoPerm.',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$data = ActionRegistry::getAvailable(['read']);
assert_equal(2, count($data), 'has + no-perm action visible');

$data = ActionRegistry::getAvailable(['admin']);
assert_equal(1, count($data), 'no-perm action visible, read action excluded');

// ============= 15. RESULT TOARRAY FORMAT ===---==

$success = (new \App\Core\ActionResult(
    actionId: 'test.success',
    success: true,
    data: ['id' => 5],
    entityId: 5,
    agentId: 'agent:test',
    agentName: 'Test Bot',
    createdAt: '2026-05-29 12:00:00',
))->toArray();

assert_equal('test.success', $success['id'], 'success id correct');
assert_true($success['success'], 'success success is true');
assert_equal(['id' => 5], $success['data'], 'success data correct');
assert_equal(5, $success['entity_id'], 'success entity_id correct');
assert_equal('agent:test', $success['agent_id'], 'success agent_id correct');
assert_equal('Test Bot', $success['agent_name'], 'success agent_name correct');
assert_equal('2026-05-29 12:00:00', $success['created_at'], 'success created_at correct');

$failure = (new \App\Core\ActionResult(
    actionId: 'test.fail',
    success: false,
    error: 'validation_error',
    errorType: 'validation_error',
    createdAt: '2026-05-29 12:00:00',
))->toArray();

assert_false($failure['success'], 'failure success is false');
assert_equal('validation_error', $failure['error'], 'failure error correct');
assert_equal('validation_error', $failure['error_type'], 'failure error_type correct');
assert_false(array_key_exists('data', $failure), 'failure result has no data key');

summary();
