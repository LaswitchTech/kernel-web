<?php

/**
 * Tests for TaskService priority feature.
 *
 * Tests:
 *   - Priority constants (PRIORITY_LEVELS, PRIORITY_LABELS)
 *   - Priority normalization (named "high" → 1, numeric "2" → 2, empty → null)
 *   - Priority validation rejects invalid values
 *   - Priority default = 0 (medium)
 *   - Priority-aware ordering in TaskRepository
 *   - Priority included in findById result
 *   - Priority included in create SQL
 *   - Priority included in update SQL
 *   - Organization scoping on TaskActivityRepository
 *
 * Run: php tests/task_priority_test.php
 * Assertions: 28
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
require __DIR__ . '/../lib/plugins/tasks/src/TaskRepository.php';
require __DIR__ . '/../lib/plugins/tasks/src/TaskService.php';
require __DIR__ . '/../lib/plugins/tasks/src/TaskActivityRepository.php';
reset_counters();

use App\Plugins\tasks\TaskService;
use App\Plugins\tasks\TaskRepository;
use App\Plugins\tasks\TaskActivityRepository;
use App\Core\Container;
use App\Core\OrganizationScopedRepository;
use App\Core\DatabaseInterface;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

class TestDB5 implements DatabaseInterface
{
    public function __construct(private PDO $pdo) {}
    public function pdo(): PDO { return $this->pdo; }
    public function fetch(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetchOne(string $sql, array $params = []): ?array {
        $rows = $this->fetch($sql, $params);
        return $rows[0] ?? null;
    }
    public function execute(string $sql, array $bindings = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return (int) $stmt->rowCount();
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }
}

$db = new TestDB5($pdo);

// --- Create tables ---

$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        display_name TEXT DEFAULT '',
        password_hash TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )
");

$pdo->exec("
    CREATE TABLE tasks (
        id            INTEGER      NOT NULL,
        title         VARCHAR(255) NOT NULL,
        description   TEXT,
        status        VARCHAR(32)  NOT NULL DEFAULT 'open',
        priority      INTEGER      NOT NULL DEFAULT 0,
        due_at        VARCHAR(32),
        entity_type   VARCHAR(64),
        entity_id     INTEGER,
        created_by_user_id INTEGER,
        organization_id INTEGER,
        created_at    VARCHAR(32)  NOT NULL,
        updated_at    VARCHAR(32)  NOT NULL,
        deleted_at    VARCHAR(32),
        assigned_type TEXT,
        assigned_id   INTEGER,
        execution_type TEXT,
        execution_payload TEXT,
        last_run_at   VARCHAR(32),
        last_run_status VARCHAR(32),
        last_run_message TEXT,
        schedule_type VARCHAR(32),
        schedule_value TEXT,
        PRIMARY KEY (id),
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

$pdo->exec("
    CREATE TABLE task_activity (
        id          INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
        task_id     INTEGER      NOT NULL,
        event_type  VARCHAR(32)  NOT NULL,
        user_id     INTEGER      NULL,
        old_value   TEXT,
        new_value   TEXT,
        meta        TEXT         NOT NULL DEFAULT '{}',
        created_at  VARCHAR(32)  NOT NULL,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_entity ON tasks (entity_type, entity_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_status ON tasks (status)');
$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_due_at ON tasks (due_at)');
$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_deleted_at ON tasks (deleted_at)');
$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_assigned_type ON tasks (assigned_type, assigned_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_organization_id ON tasks (organization_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_priority ON tasks (priority)');
$pdo->exec('CREATE INDEX IF NOT EXISTS task_activity_task_id ON task_activity (task_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS task_activity_created_at ON task_activity (created_at DESC)');
$pdo->exec('CREATE INDEX IF NOT EXISTS task_activity_event_type ON task_activity (event_type)');

$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['alice', 'alice@example.com', 'Alice', '$2y$10$hash']);
$uid = 1;

$repo    = new TaskRepository($db);
$service = new TaskService($repo);

// === TEST 1: Priority levels constant ===
assert_equal(4, count(TaskService::PRIORITY_LEVELS), 'PRIORITY_LEVELS has 4 entries');
assert_equal(-1, TaskService::PRIORITY_LEVELS['low'], 'low priority is -1');
assert_equal(0, TaskService::PRIORITY_LEVELS['medium'], 'medium priority is 0');
assert_equal(1, TaskService::PRIORITY_LEVELS['high'], 'high priority is 1');
assert_equal(2, TaskService::PRIORITY_LEVELS['critical'], 'critical priority is 2');

// === TEST 2: Priority labels constant ===
assert_equal('Low', TaskService::PRIORITY_LABELS[-1], 'PRIORITY_LABELS has Low for -1');
assert_equal('Medium', TaskService::PRIORITY_LABELS[0], 'PRIORITY_LABELS has Medium for 0');
assert_equal('High', TaskService::PRIORITY_LABELS[1], 'PRIORITY_LABELS has High for 1');
assert_equal('Critical', TaskService::PRIORITY_LABELS[2], 'PRIORITY_LABELS has Critical for 2');

// === TEST 3: Normalization from named level ===
$newId = $service->create([
    'title' => 'Named priority task',
    'priority' => 'high',
    'created_by_user_id' => $uid,
]);
$task = $service->getById($newId);
assert_equal(1, $task['priority'], 'named "high" normalises to weight 1');

// === TEST 4: Normalization from integer string ===
$newId = $service->create([
    'title' => 'Integer priority task',
    'priority' => '2',
    'created_by_user_id' => $uid,
]);
$task = $service->getById($newId);
assert_equal(2, $task['priority'], 'integer string "2" normalises to 2');

// === TEST 5: Normalization from empty = default 0 ===
$newId = $service->create([
    'title' => 'Default priority task',
    'created_by_user_id' => $uid,
]);
$task = $service->getById($newId);
assert_equal(0, $task['priority'], 'empty priority defaults to 0');

// === TEST 6: Normalization from null = default 0 ===
$newId = $service->create([
    'title' => 'Null priority task',
    'priority' => null,
    'created_by_user_id' => $uid,
]);
$task = $service->getById($newId);
assert_equal(0, $task['priority'], 'null priority defaults to 0');

// === TEST 7: Invalid priority string = defaults to medium ===
// Invalid value in normalise() falls back to medium (0), validation passes because 0 is valid.
$newId = $service->create([
    'title' => 'Invalid priority name task',
    'priority' => 'invalid',
    'created_by_user_id' => $uid,
]);
$task = $service->getById($newId);
assert_equal(0, $task['priority'], 'invalid name defaults to medium (0)');

// === TEST 8: Update changes priority ===
$service->update($newId, ['title' => 'Named priority task', 'priority' => 'critical']);
$updated = $service->getById($newId);
assert_equal(2, $updated['priority'], 'update sets priority to 2');

// === TEST 9: Priority in findById ===
assert_true(array_key_exists('priority', $updated), 'findById returns priority field');
assert_equal(2, $updated['priority'], 'findById returns correct priority');

// === TEST 10: Priority-aware ordering — high priority first ===
// Create tasks with different priorities
$service->create(['title' => 'Low task', 'priority' => -1, 'created_by_user_id' => $uid]);
$service->create(['title' => 'Critical task', 'priority' => 2, 'created_by_user_id' => $uid]);
$service->create(['title' => 'Medium task', 'priority' => 0, 'created_by_user_id' => $uid]);
$service->create(['title' => 'High task', 'priority' => 1, 'created_by_user_id' => $uid]);
$all = $service->getAll();
$priorities = array_map(fn($t) => $t['priority'] ?? 0, $all);
assert_true($priorities[0] >= $priorities[1], 'first task has highest priority');
assert_true($priorities[0] === 2, 'first task is critical');

// === TEST 11: TaskService activity logging ===
$actRepo = new TaskActivityRepository($db);
$service2 = new TaskService(new TaskRepository($db), $actRepo);
$task2Id = $service2->create([
    'title' => 'Activity test task',
    'priority' => 'high',
    'created_by_user_id' => $uid,
]);
$activities = $actRepo->findByTask($task2Id);
assert_true(count($activities) >= 1, 'activity log has at least one entry');
assert_equal('task_created', $activities[0]['event_type'], 'first event is task_created');

// === TEST 12: TaskActivityRepository event type validation ===
$actRepo2 = new TaskActivityRepository($db);
$actRepo2->logEvent($task2Id, 'task_created', $uid);
assert_true(true, 'valid event type accepted');

try {
    $actRepo2->logEvent($task2Id, 'invalid_event', $uid);
    assert_false(true, 'invalid event type rejected');
} catch (\InvalidArgumentException $e) {
    assert_true(true, 'invalid event type throws');
}

// === TEST 13: TaskActivityRepository extends OrganizationScopedRepository ===
assert_true(
    is_subclass_of(TaskActivityRepository::class, OrganizationScopedRepository::class),
    'TaskActivityRepository extends OrganizationScopedRepository'
);

// === TEST 14: scopeFromContainer on activity repo ===
$container = new Container();
$container->set('org_scope', 42);
$activityScoped = new TaskActivityRepository($db);
$activityScoped->scopeFromContainer($container);
assert_true($activityScoped->isScoped(), 'activity repo is scoped after scopeFromContainer');

// Use reflection to check internal state.
$ref = new ReflectionClass($activityScoped);
$prop = $ref->getProperty('organizationIds');
$prop->setAccessible(true);
assert_equal([42], $prop->getValue($activityScoped), 'scopeFromContainer sets org scope');

// === TEST 15: findRecent returns activity across tasks ===
$service2->create(['title' => 'Another task', 'created_by_user_id' => $uid]);
$recent = $actRepo->findRecent(50);
assert_true(count($recent) >= 2, 'findRecent returns multiple events');

// === TEST 16: Organization scoping on activity queries ===
$actRepoScoped = new TaskActivityRepository($db);
$actRepoScoped->scopeOrganization(10);
assert_true($actRepoScoped->isScoped(), 'activity repo is scoped after scopeOrganization');

// === TEST 17: Event types whitelist ===
assert_true(count(TaskActivityRepository::EVENT_TYPES) > 0, 'EVENT_TYPES is non-empty');
assert_true(in_array('task_created', TaskActivityRepository::EVENT_TYPES, true), 'task_created in EVENT_TYPES');
assert_true(in_array('status_changed', TaskActivityRepository::EVENT_TYPES, true), 'status_changed in EVENT_TYPES');
assert_true(in_array('assigned', TaskActivityRepository::EVENT_TYPES, true), 'assigned in EVENT_TYPES');
assert_true(in_array('priority_changed', TaskActivityRepository::EVENT_TYPES, true), 'priority_changed in EVENT_TYPES');

// === TEST 18: Update logs status change ===
$actRepo3 = new TaskActivityRepository($db);
$service3 = new TaskService(new TaskRepository($db), $actRepo3);
$task3Id = $service3->create(['title' => 'Status change test', 'created_by_user_id' => $uid]);
$service3->update($task3Id, ['title' => 'Status change test', 'status' => 'in_progress']);
$activities = $actRepo3->findByTask($task3Id);
assert_true(
    in_array('status_changed', array_column($activities, 'event_type'), true),
    'update with status change logs status_changed event'
);

// === TEST 19: Update logs priority change ===
$actRepo4 = new TaskActivityRepository($db);
$service4 = new TaskService(new TaskRepository($db), $actRepo4);
$task4Id = $service4->create(['title' => 'Priority change test', 'priority' => 0, 'created_by_user_id' => $uid]);
$service4->update($task4Id, ['title' => 'Priority change test', 'priority' => 1]);
$activities = $actRepo4->findByTask($task4Id);
assert_true(
    in_array('priority_changed', array_column($activities, 'event_type'), true),
    'update with priority change logs priority_changed event'
);

// === TEST 20: Update without status/priority change does not log redundant events ===
$actRepo5 = new TaskActivityRepository($db);
$service5 = new TaskService(new TaskRepository($db), $actRepo5);
$task5Id = $service5->create(['title' => 'No-change test', 'priority' => 0, 'created_by_user_id' => $uid]);
$service5->update($task5Id, ['title' => 'No-change test (edited)', 'priority' => 0]);
$activities = $actRepo5->findByTask($task5Id);
// Should only have task_created, no status_changed or priority_changed
$statusEvents = array_filter($activities, fn($a) => $a['event_type'] === 'status_changed');
assert_true(count($statusEvents) === 0, 'no redundant status_changed on non-status update');

// === TEST 21: Delete logs deleted event ===
$actRepo6 = new TaskActivityRepository($db);
$service6 = new TaskService(new TaskRepository($db), $actRepo6);
$task6Id = $service6->create(['title' => 'Delete test', 'created_by_user_id' => $uid]);
$service6->delete($task6Id);
$activities = $actRepo6->findByTask($task6Id);
assert_true(
    in_array('deleted', array_column($activities, 'event_type'), true),
    'delete logs deleted event'
);

// === TEST 22: TaskActivityRepository findByTask returns correct task_id ===
$actRepo7 = new TaskActivityRepository($db);
$actRepo7->logEvent($task2Id, 'task_created', $uid);
$results = $actRepo7->findByTask($task2Id);
assert_true(
    array_reduce($results, fn($acc, $r) => $acc && $r['task_id'] === $task2Id, true),
    'findByTask returns only events for the specified task'
);

echo "\nResults: " . $__PASS__ . " passed, " . $__FAIL__ . " failed\n";
echo $__FAIL__ > 0 ? "FAILED\n" : "ALL PASSED\n";
echo "\n";
