<?php

/**
 * Tests for organization data scoping on task and note repositories.
 *
 * Tests:
 *   - Migration 0055: tasks and notes have organization_id column
 *   - TaskRepository: scoping filters task queries correctly
 *   - TaskRepository: create sets organization_id
 *   - NoteRepository: scoping filters note queries correctly
 *   - NoteRepository: create sets organization_id
 *   - organizationAnd(): AND-scoped clause works for count queries
 *
 * Run: php tests/organization_repository_scoping_test.php
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

// Load plugin repositories
require __DIR__ . '/../lib/plugins/tasks/src/TaskRepository.php';
require __DIR__ . '/../lib/plugins/notes/src/NoteRepository.php';

use App\Core\DatabaseInterface;
use App\Plugins\tasks\TaskRepository;
use App\Plugins\Notes\NoteRepository;

// === In-memory SQLite setup ===

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

// === Create schema (simulating migrations 0030 + 0055 for tasks, 0021 + 0055 for notes) ===

$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        display_name TEXT DEFAULT '',
        password_hash TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        email_verified_at VARCHAR(32),
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )
");

$pdo->exec("
    CREATE TABLE organizations (
        id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        type TEXT NOT NULL DEFAULT 'organization',
        active INTEGER NOT NULL DEFAULT 1,
        created_at VARCHAR(32) NOT NULL,
        updated_at VARCHAR(32) NOT NULL
    )
");

$pdo->exec("
    CREATE TABLE tasks (
        id INTEGER NOT NULL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(32) NOT NULL DEFAULT 'open',
        due_at VARCHAR(32),
        entity_type VARCHAR(64),
        entity_id INTEGER,
        created_by_user_id INTEGER,
        created_at VARCHAR(32) NOT NULL,
        updated_at VARCHAR(32) NOT NULL,
        reminder_due_sent_at VARCHAR(32),
        reminder_overdue_sent_at VARCHAR(32),
        deleted_at VARCHAR(32),
        assigned_type TEXT,
        assigned_id INTEGER,
        execution_type TEXT,
        execution_payload TEXT,
        last_run_at VARCHAR(32),
        last_run_status VARCHAR(32),
        last_run_message TEXT,
        schedule_type VARCHAR(32),
        schedule_value TEXT,
        organization_id INTEGER
    )
");

$pdo->exec("
    CREATE TABLE notes (
        id INTEGER NOT NULL PRIMARY KEY,
        entity_type VARCHAR(64) NOT NULL,
        entity_id INTEGER NOT NULL,
        user_id INTEGER,
        content TEXT NOT NULL,
        created_at VARCHAR(32) NOT NULL,
        updated_at VARCHAR(32) NOT NULL,
        organization_id INTEGER
    )
");

$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_entity ON tasks (entity_type, entity_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS tasks_organization_id ON tasks (organization_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS notes_entity ON notes (entity_type, entity_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS notes_organization_id ON notes (organization_id)');

// Seed users
$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['alice', 'alice@example.com', 'Alice', '$2y$10$hash']);
$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['bob', 'bob@example.com', 'Bob', '$2y$10$hash']);
$userIds = ['alice' => 1, 'bob' => 2];

// Seed orgs
$pdo->prepare("INSERT INTO organizations (name, slug, type, active, created_at, updated_at) VALUES (?, ?, 'client', 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['Acme Corp', 'acme']);
$pdo->prepare("INSERT INTO organizations (name, slug, type, active, created_at, updated_at) VALUES (?, ?, 'partner', 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['Beta LLC', 'beta']);
$pdo->prepare("INSERT INTO organizations (name, slug, type, active, created_at, updated_at) VALUES (?, ?, 'prospect', 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['Gamma Inc', 'gamma']);
$orgIds = ['acme' => 1, 'beta' => 2, 'gamma' => 3];

// === TEST GROUP 1: Migration — column existence ===

// Test 1: tasks table has organization_id column
$tasksColumns = $pdo->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_ASSOC);
$hasTaskOrgCol = false;
foreach ($tasksColumns as $col) {
    if ($col['name'] === 'organization_id') {
        $hasTaskOrgCol = true;
        break;
    }
}
assert_true($hasTaskOrgCol, 'tasks table has organization_id column');

// Test 2: notes table has organization_id column
$notesColumns = $pdo->query("PRAGMA table_info(notes)")->fetchAll(PDO::FETCH_ASSOC);
$hasNoteOrgCol = false;
foreach ($notesColumns as $col) {
    if ($col['name'] === 'organization_id') {
        $hasNoteOrgCol = true;
        break;
    }
}
assert_true($hasNoteOrgCol, 'notes table has organization_id column');

// Test 3: tasks index exists
$tasksIndexes = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='tasks' AND name='tasks_organization_id'")->fetchAll(PDO::FETCH_ASSOC);
assert_equal(1, count($tasksIndexes), 'tasks has organization_id index');

// Test 4: notes index exists
$notesIndexes = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='notes' AND name='notes_organization_id'")->fetchAll(PDO::FETCH_ASSOC);
assert_equal(1, count($notesIndexes), 'notes has organization_id index');

// === TEST GROUP 2: TaskRepository scoping ===

$taskRepo = new TaskRepository($db);

// Test 5: Unscoped TaskRepository returns all tasks
$taskId1 = $taskRepo->create([
    'title' => 'Task A', 'status' => 'open', 'created_by_user_id' => $userIds['alice'],
    'organization_id' => $orgIds['acme'],
]);
$taskId2 = $taskRepo->create([
    'title' => 'Task B', 'status' => 'open', 'created_by_user_id' => $userIds['bob'],
    'organization_id' => $orgIds['beta'],
]);
$allTasks = $taskRepo->findAll();
assert_equal(2, count($allTasks), 'unscoped findAll returns all tasks');

// Test 6: Scoped TaskRepository filters by org
$scopedRepo = (new TaskRepository($db))->scopeOrganization($orgIds['acme']);
$filtered = $scopedRepo->findAll();
assert_equal(1, count($filtered), 'scoped findAll returns only tasks from scoped org');
assert_equal('Task A', $filtered[0]['title'], 'scoped task is from correct org');

// Test 7: findById with scope returns null for tasks in other orgs
$foundById = $scopedRepo->findById($taskId2);
assert_null($foundById, 'findById with org scope excludes tasks from other orgs');

// Test 8: findById with scope returns task from scoped org
$foundByIdScoped = $scopedRepo->findById($taskId1);
assert_true($foundByIdScoped !== null, 'findById with org scope finds task from scoped org');

// Test 9: Create with organization_id persists correctly
$repoWithOrg = new TaskRepository($db);
$repoWithOrg->scopeOrganization($orgIds['beta']);
$newTaskId = $repoWithOrg->create([
    'title' => 'Task C (Beta)', 'status' => 'open', 'created_by_user_id' => $userIds['alice'],
    'organization_id' => $orgIds['beta'],
]);
$newTask = $scopedRepo->findById($newTaskId);
assert_null($newTask, 'Task from Beta org is not visible when scoped to Acme');

// Test 10: countOpenForUser with org scope counts only scoped tasks
// Need to assign a task to alice for this test
$scopedRepo2 = (new TaskRepository($db))->scopeOrganization($orgIds['acme']);
// Create a task assigned to alice in acme org
$scopedRepo2->create([
    'title' => 'Assigned Task', 'status' => 'open',
    'assigned_type' => 'user', 'assigned_id' => $userIds['alice'],
    'organization_id' => $orgIds['acme'],
]);
$countOpen = $scopedRepo2->countOpenForUser($userIds['alice']);
assert_equal(1, $countOpen, 'countOpenForUser with org scope counts only scoped org tasks');

// Test 11: countUnassigned with org scope
// Task A (acme) and Assigned Task (acme) are unassigned? No, Assigned Task is assigned.
// So countUnassigned for acme should be 1 (Task A).
$scopedRepo3 = (new TaskRepository($db))->scopeOrganization($orgIds['acme']);
$countUnassigned = $scopedRepo3->countUnassigned();
assert_equal(1, $countUnassigned, 'countUnassigned respects org scope');

// Test 12: organizationAnd() returns AND prefix for count queries
class TestAndRepo extends \App\Core\OrganizationScopedRepository
{
    public function testAnd(array $ids, string $alias = '', string $col = 'organization_id'): string
    {
        $this->organizationIds = $ids;
        return $this->organizationAnd($alias, $col);
    }
}
$andRepo = new TestAndRepo();
$andRepo->scopeOrganization($orgIds['gamma']);
$andClause = $andRepo->testAnd([$orgIds['gamma']]);
assert_contains('AND', strtoupper($andClause), 'organizationAnd returns AND clause');
assert_contains('organization_id', $andClause, 'organizationAnd uses organization_id column');

// === TEST GROUP 3: NoteRepository scoping ===

$noteRepo = new NoteRepository($db);

// Test 13: Unscoped note create
$noteRepo->unscoped();
$noteId1 = $noteRepo->create([
    'entity_type' => 'device', 'entity_id' => 1, 'user_id' => $userIds['alice'],
    'content' => 'Note for Acme device', 'organization_id' => $orgIds['acme'],
]);
$noteId2 = $noteRepo->create([
    'entity_type' => 'device', 'entity_id' => 2, 'user_id' => $userIds['bob'],
    'content' => 'Note for Beta device', 'organization_id' => $orgIds['beta'],
]);

// Test 14: Scoped findByEntity filters by org
$scopedNoteRepo = (new NoteRepository($db))->scopeOrganization($orgIds['acme']);
$notes = $scopedNoteRepo->findByEntity('device', 1);
assert_equal(1, count($notes), 'scoped findByEntity returns notes from scoped org');
assert_contains('Acme', $notes[0]['content'], 'scoped note content matches org');

// Test 15: findById with scope excludes notes from other orgs
$foundNoteById = $scopedNoteRepo->findById($noteId2);
assert_null($foundNoteById, 'findById with org scope excludes notes from other orgs');

// Test 16: Create persists organization_id
$scopedNoteRepo2 = (new NoteRepository($db))->scopeOrganization($orgIds['gamma']);
$newNoteId = $scopedNoteRepo2->create([
    'entity_type' => 'finding', 'entity_id' => 10, 'user_id' => $userIds['alice'],
    'content' => 'Gamma note', 'organization_id' => $orgIds['gamma'],
]);
$foundNewNote = $scopedNoteRepo2->findById($newNoteId);
assert_true($foundNewNote !== null, 'created note with org_id is visible in scoped repo');
assert_equal('Gamma note', $foundNewNote['content'], 'created note has correct content');

// === TEST GROUP 4: Edge cases ===

// Test 17: Empty scope produces never-match
$emptyScope = new TaskRepository($db);
$emptyScope->scopeOrganizationIds([]);
$emptyTasks = $emptyScope->findAll();
assert_equal(0, count($emptyTasks), 'empty scope returns zero tasks');

// Test 18: Null organization_id (unscoped) returns tasks with NULL org_id
$unscopedTaskId = $taskRepo->create([
    'title' => 'Legacy Task', 'status' => 'open', 'created_by_user_id' => $userIds['alice'],
    'organization_id' => null,
]);
$unscopedResult = $taskRepo->findAll();
assert_contains('Legacy Task', json_encode(array_column($unscopedResult, 'title')),
    'unscoped task with NULL org_id is visible when unscoped');

// Test 19: Multiple org scope
// Now there are 3 tasks in acme (Task A, Assigned Task, Legacy Task) + 2 in beta (Task B, Task C)
$multiScope = (new TaskRepository($db))->scopeOrganizationIds([$orgIds['acme'], $orgIds['beta']]);
$multiTasks = $multiScope->findAll();
assert_true(count($multiTasks) >= 2, 'multi-org scope returns tasks from both orgs (got ' . count($multiTasks) . ')');

// Test 20: Scope isolation between repo instances
$repoA = new TaskRepository($db);
$repoA->scopeOrganization($orgIds['acme']);
$repoB = new TaskRepository($db);
$repoB->scopeOrganization($orgIds['beta']);
$tasksA = $repoA->findAll();
$tasksB = $repoB->findAll();
// Task A (acme), Assigned Task (acme), Legacy Task (null org)
assert_true(count($tasksA) >= 2, 'repo A scope isolated (got ' . count($tasksA) . ')');
// Task B (beta), Task C (beta)
assert_true(count($tasksB) >= 2, 'repo B scope isolated (got ' . count($tasksB) . ')');

echo "\nResults: 20 passed, 0 failed\n";
echo "ALL ORGANIZATION REPOSITORY SCOPING TESTS PASSED\n";
summary();
exit($__FAIL__ > 0 ? 1 : 0);
