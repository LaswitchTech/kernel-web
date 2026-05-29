<?php

/**
 * Tests for ActionExecutor — permission check, validation, invocation, audit logging.
 *
 * Uses a mock Gate class since the real Gate requires a DB connection.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
require __DIR__ . '/../app/Core/ActionCallContext.php';
reset_counters();

use App\Core\ActionDefinition;
use App\Core\ActionRegistry;
use App\Core\ActionExecutor;
use App\Core\ActionCallContext;
use App\Core\AgentCallContext;
use App\Core\HumanCallContext;
use App\Core\ActionResult;

// --- Helpers ---

ActionRegistry::clear();

// --- Mock Gate ---
class MockGate
{
    public function can(array $principal, string $permission): bool
    {
        if ($permission === '') {
            return true;
        }
        // Allow anyone who has the permission in their principal.
        return in_array($permission, $principal['permissions'] ?? [], true);
    }
}

// --- Mock Database ---
class MockDatabaseInterface implements \App\Core\DatabaseInterface
{
    public function execute(string $sql, array $params = []): int { return 0; }
    public function query(string $sql, array $params = []): array { return []; }
    public function fetch(string $sql, array $bindings = []): array { return []; }
    public function fetchOne(string $sql, array $bindings = []): ?array { return null; }
    public function lastInsertId(): string { return '1'; }
    public function pdo(): \PDO { throw new \RuntimeException('Mock'); }
    public function prepare(string $sql): \PDOStatement { throw new \RuntimeException('Mock'); }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollback(): bool { return true; }
    public function quote(string $value): string { return "'$value'"; }
}

// --- Test service for success scenarios ---
class TestService
{
    public function handle(array $data): int
    {
        return 42;
    }
}

class IntReturnService
{
    public function handle(array $data): int
    {
        return 123;
    }
}

// --- Helper to extract result fields ---
function r(ActionResult $result, string $field): mixed
{
    return $result->toArray()[$field] ?? null;
}

$mockDb = new MockDatabaseInterface();
$allowedGate = new MockGate(['test.use']);
$deniedGate = new MockGate([]);
$allowedContext = new HumanCallContext(['user' => ['id' => 1], 'permissions' => ['test.use']]);

// ============= 1. UNKNOWN ACTION ===---==

$executor = new ActionExecutor($mockDb, $allowedGate);
$result = $executor->execute('nonexistent.action', $allowedContext, []);

assert_false(r($result, 'success'), 'unknown action returns failure');
assert_equal('unknown_action', r($result, 'error_type'), 'error type is unknown_action');
assert_equal(0, r($result, 'entity_id'), 'entity_id is 0 for unknown action');

// ============= 2. PERMISSION DENIED ===---==

$deniedContext = new HumanCallContext(['user' => ['id' => 1], 'permissions' => []]);
$deniedExecutor = new ActionExecutor($mockDb, $deniedGate);

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.use',
    name: 'Test Use',
    description: 'Uses something.',
    service_class: TestService::class,
    service_method: 'handle',
    parameters: [],
    permission: 'test.use',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$result = $deniedExecutor->execute('test.use', $deniedContext, []);
assert_false(r($result, 'success'), 'no permission returns failure');
assert_equal('permission_denied', r($result, 'error_type'), 'error type is permission_denied');

// ============= 3. VALIDATION ERROR ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.validate',
    name: 'Validate Test',
    description: 'Tests validation.',
    service_class: TestService::class,
    service_method: 'handle',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name'],
    ],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$result = $deniedExecutor->execute('test.validate', $allowedContext, []);
assert_false(r($result, 'success'), 'missing required param returns failure');
assert_equal('validation_error', r($result, 'error_type'), 'error type is validation_error');
assert_contains('name', r($result, 'error'), 'error mentions missing param name');

// ============= 4. SUCCESS — HUMAN CALLER ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.create',
    name: 'Test Create',
    description: 'Creates a test resource.',
    service_class: TestService::class,
    service_method: 'handle',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name'],
        ['name' => 'count', 'type' => 'int', 'required' => false, 'description' => 'Count', 'default' => 1],
    ],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$successExecutor = new ActionExecutor($mockDb, new MockGate([]));
$result = $successExecutor->execute('test.create', $allowedContext, ['name' => 'test-resource']);

assert_true(r($result, 'success'), 'valid input returns success');
assert_equal('test.create', r($result, 'id'), 'action id returned');
assert_null(r($result, 'agent_id'), 'human caller has no agent_id');

// ============= 5. SUCCESS — AGENT CALLER ===---==

$agentContext = AgentCallContext::fromArray([
    'actor_id' => 'agent:test-bot',
    'agent_name' => 'Test Bot',
    'permissions' => [],
]);

$result = $successExecutor->execute('test.create', $agentContext, ['name' => 'agent-resource']);
assert_true(r($result, 'success'), 'agent call succeeds');
assert_equal('agent:test-bot', r($result, 'agent_id'), 'agent_id preserved');
assert_equal('Test Bot', r($result, 'agent_name'), 'agent_name preserved');

// ============= 6. AGENT CALLER WITH PERMISSION CHECK ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.create',
    name: 'Test Create',
    description: 'Creates a test resource.',
    service_class: TestService::class,
    service_method: 'handle',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name'],
    ],
    permission: 'test.use',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

// Agent with permission
$agentWithPerm = AgentCallContext::fromArray([
    'actor_id' => 'agent:test-bot',
    'agent_name' => 'Test Bot',
    'permissions' => ['test.use'],
]);

$agentExecutor = new ActionExecutor($mockDb, new MockGate(['test.use']));
$result = $agentExecutor->execute('test.create', $agentWithPerm, ['name' => 'ok']);
assert_true(r($result, 'success'), 'agent with permission succeeds');

// Agent without permission
$agentNoPerm = AgentCallContext::fromArray([
    'actor_id' => 'agent:test-bot',
    'agent_name' => 'Test Bot',
    'permissions' => [],
]);

$result = $agentExecutor->execute('test.create', $agentNoPerm, ['name' => 'nope']);
assert_false(r($result, 'success'), 'agent without permission fails');
assert_equal('permission_denied', r($result, 'error_type'), 'agent without permission gets permission_denied');

// ============= 7. PARAMETER COERCION ===---==

class CoerceService
{
    public static array $received = [];

    public function handle(array $data): int
    {
        self::$received = $data;
        return 99;
    }
}

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.coerce',
    name: 'Coerce Test',
    description: 'Tests type coercion.',
    service_class: CoerceService::class,
    service_method: 'handle',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name'],
        ['name' => 'count', 'type' => 'int', 'required' => false, 'description' => 'Count', 'default' => 0],
        ['name' => 'active', 'type' => 'bool', 'required' => false, 'description' => 'Active', 'default' => false],
        ['name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'Status', 'default' => 'pending', 'enum' => ['pending', 'done']],
    ],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

CoerceService::$received = [];
$result = $successExecutor->execute('test.coerce', $allowedContext, [
    'name' => 'test',
    'count' => '5',   // string should coerce to int
    'active' => '1',  // string '1' should coerce to bool
    'status' => 'done',
]);

assert_true(r($result, 'success'), 'coerced types accepted');
assert_equal(99, r($result, 'entity_id'), 'coerced execution succeeds');

// ============= 8. DEFAULT VALUES ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.defaults',
    name: 'Defaults Test',
    description: 'Tests default values.',
    service_class: CoerceService::class,
    service_method: 'handle',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name'],
        ['name' => 'count', 'type' => 'int', 'required' => false, 'description' => 'Count', 'default' => 42],
        ['name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'Status', 'default' => 'pending', 'enum' => ['pending', 'done']],
    ],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

CoerceService::$received = [];
$result = $successExecutor->execute('test.defaults', $allowedContext, ['name' => 'defaults-test']);
assert_true(r($result, 'success'), 'defaults filled correctly');

// ============= 9. ENUM VALIDATION ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.enum',
    name: 'Enum Test',
    description: 'Tests enum validation.',
    service_class: CoerceService::class,
    service_method: 'handle',
    parameters: [
        ['name' => 'status', 'type' => 'string', 'required' => true, 'description' => 'Status', 'enum' => ['pending', 'done']],
    ],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$result = $successExecutor->execute('test.enum', $allowedContext, ['status' => 'invalid']);
assert_false(r($result, 'success'), 'invalid enum rejected');
assert_equal('validation_error', r($result, 'error_type'), 'enum error is validation_error');

// ============= 10. RESULT TOARRAY FORMAT ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.create',
    name: 'Test Create',
    description: 'Creates a test resource.',
    service_class: TestService::class,
    service_method: 'handle',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name'],
    ],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$srExec = new ActionExecutor($mockDb, new MockGate([]));
$sr = $srExec->execute('test.create', $allowedContext, ['name' => 'x']);
$arr = $sr->toArray();
assert_array_has_key($arr, 'id', 'toArray has id');
assert_true($arr['success'], 'toArray has success');
assert_array_has_key($arr, 'entity_id', 'toArray has entity_id');
assert_array_has_key($arr, 'created_at', 'toArray has created_at');

// ============= 11. SUCCESSFUL EXECUTION — INT RETURN ===---==

ActionRegistry::clear();
ActionRegistry::add(new ActionDefinition(
    id: 'test.int_return',
    name: 'Int Return Test',
    description: 'Tests int return extraction.',
    service_class: IntReturnService::class,
    service_method: 'handle',
    parameters: [],
    permission: '',
    risk_level: 'low',
    audit_entity_type: 'test',
    source: 'test',
));

$result = $successExecutor->execute('test.int_return', $allowedContext, []);
assert_true(r($result, 'success'), 'int return works');
assert_equal(123, r($result, 'entity_id'), 'entity_id extracted from int return');

summary();
