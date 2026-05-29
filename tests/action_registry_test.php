<?php

/**
 * Tests for ActionRegistry — add, get, getAvailable, has, clear, getForPlugin.
 *
 * Tests: registration, retrieval, permission filtering, duplicate detection,
 * plugin filtering, and ordering.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\ActionDefinition;
use App\Core\ActionRegistry;

// --- Helpers ---

function makeAction(string $id, string $permission = '', string $source = 'core', int $order = 50): ActionDefinition
{
    return new ActionDefinition(
        id: $id,
        name: ucfirst($id),
        description: "Test action {$id}",
        service_class: 'App\\Core\\TestService',
        service_method: 'handle',
        parameters: [],
        permission: $permission,
        risk_level: 'low',
        audit_entity_type: 'test',
        source: $source,
        order: $order,
    );
}

// Clean slate.
ActionRegistry::clear();

// ============= 1. ADD AND GET ===---==

ActionRegistry::add(makeAction('core.action_a', 'core.view'));
$action = ActionRegistry::get('core.action_a');

assert_not_null($action, 'get returns action');
assert_equal('core.action_a', $action->id, 'get returns correct action');
assert_equal('core.view', $action->permission, 'permission preserved');
assert_equal('core', $action->source, 'source preserved');

// ============= 2. HAS ===---==

assert_true(ActionRegistry::has('core.action_a'), 'has returns true for registered action');
assert_false(ActionRegistry::has('nonexistent'), 'has returns false for unregistered action');

// ============= 3. DUPLICATE DETECTION ===---==

assert_raises(
    fn() => ActionRegistry::add(makeAction('core.action_a', 'core.view')),
    RuntimeException::class,
    'duplicate add throws RuntimeException',
);

// ============= 4. GETAVAILABLE — PERMISSION FILTERING ===---==

ActionRegistry::add(makeAction('core.action_b', 'core.write'));
ActionRegistry::add(makeAction('core.action_c', '')); // no permission required
ActionRegistry::add(makeAction('tasks.create', 'tasks.manage', 'tasks'));

$available = ActionRegistry::getAvailable(['core.view', 'admin']);

// action_a has 'core.view' which is in the list → included
// action_b has 'core.write' which is NOT in the list → excluded
// action_c has '' (no permission) → included
// tasks.create has 'tasks.manage' which is NOT in the list → excluded
assert_equal(2, count($available), 'getAvailable filters by permissions');
$ids = array_map(fn($a) => $a->id, $available);
assert_contains('core.action_a', $ids, 'action with matching permission included');
assert_contains('core.action_c', $ids, 'action with no permission included');
assert_false(in_array('core.action_b', $ids), 'action with non-matching permission excluded');
assert_false(in_array('tasks.create', $ids), 'task action excluded without tasks.manage permission');

// ============= 5. GETAVAILABLE — FULL PERMISSIONS ===---==

$allAvailable = ActionRegistry::getAvailable(['core.view', 'core.write', 'admin', 'tasks.manage']);
assert_equal(4, count($allAvailable), 'getAvailable with full permissions returns all');

// ============= 6. GETFORPLUGIN ===---==

ActionRegistry::add(makeAction('myplugin.action_x', 'myplugin.use', 'myplugin'));
$pluginActions = ActionRegistry::getForPlugin('myplugin');
assert_equal(1, count($pluginActions), 'getForPlugin returns only myplugin actions');
assert_equal('myplugin.action_x', $pluginActions[0]->id, 'getForPlugin returns correct action');

$otherActions = ActionRegistry::getForPlugin('core');
assert_equal(3, count($otherActions), 'getForPlugin returns core actions');

// ============= 7. ORDERING ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'z_action',
    name: 'Z',
    description: 'Z',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'core',
    order: 100,
));
ActionRegistry::add(new ActionDefinition(
    id: 'a_action',
    name: 'A',
    description: 'A',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'core',
    order: 10,
));
ActionRegistry::add(new ActionDefinition(
    id: 'm_action',
    name: 'M',
    description: 'M',
    service_class: 'X',
    service_method: 'x',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'core',
    order: 50,
));

$ordered = ActionRegistry::getAvailable([]);
assert_equal('a_action', $ordered[0]->id, 'ordered by order field ascending');
assert_equal('m_action', $ordered[1]->id, 'second item correct');
assert_equal('z_action', $ordered[2]->id, 'third item correct');

// ============= 8. TOARRAY SERIALIZATION ===---==

$testAction = new ActionDefinition(
    id: 'test.serialize',
    name: 'Serialize Test',
    description: 'Test description',
    service_class: 'Test\\Service',
    service_method: 'handle',
    parameters: [
        ['name' => 'foo', 'type' => 'string', 'required' => true, 'description' => 'Foo param'],
    ],
    permission: 'test.use',
    risk_level: 'medium',
    audit_entity_type: 'test',
    metadata: ['scope' => 'test'],
    source: 'test',
);

$arr = $testAction->toArray();
assert_equal('test.serialize', $arr['id'], 'toArray includes id');
assert_equal('test.use', $arr['permission'], 'toArray includes permission');
assert_equal('medium', $arr['risk_level'], 'toArray includes risk_level');
assert_false($arr['requires_approval'], 'toArray includes requires_approval');
assert_equal('test', $arr['source'], 'toArray includes source');
assert_array_has_key($arr['service'], 'class', 'toArray includes service.class');
assert_equal('Test\\Service', $arr['service']['class'], 'service class preserved');
assert_equal('handle', $arr['service']['method'], 'service method preserved');
assert_equal(1, count($arr['parameters']), 'parameters preserved in toArray');
assert_equal('foo', $arr['parameters'][0]['name'], 'parameter name preserved');

// ============= 9. GETALL ===---==

ActionRegistry::clear();
ActionRegistry::add(makeAction('a', ''));
ActionRegistry::add(makeAction('b', ''));
$all = ActionRegistry::getAll();
assert_equal(2, count($all), 'getAll returns all registered');

// ============= 10. CLEAR ===---==

ActionRegistry::clear();
assert_false(ActionRegistry::has('a'), 'clear removes all actions');
assert_equal(0, count(ActionRegistry::getAll()), 'getAll returns empty after clear');

summary();
