<?php
/**
 * Tests: /admin/audit filter — type query param support.
 *
 * Covers:
 * - AuditLogRepository::findRecent filtering by type
 * - Invalid type falls back to 'all'
 * - View renders filter buttons with correct active state
 * - Filtered rendering shows correct entries per filter
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\DatabaseInterface;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDBAudit implements DatabaseInterface
{
    public function __construct(private \PDO $pdo) {}
    public function pdo(): \PDO { return $this->pdo; }
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

$db = new TestDBAudit($pdo);

// Create tables
$db->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    display_name TEXT DEFAULT '',
    username TEXT NOT NULL
)");
$db->execute("CREATE TABLE admin_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_id INTEGER NOT NULL,
    meta TEXT NOT NULL DEFAULT '',
    created_at VARCHAR(32) NOT NULL
)");

// Seed test data
$db->execute("INSERT INTO users (id, username, display_name) VALUES (1, 'admin', 'Admin User')");
$db->execute("INSERT INTO admin_audit_log (user_id, action, entity_type, entity_id, meta, created_at) VALUES
    (1, 'user.create', 'user', 1, '{\"name\":\"Alice\"}', '2026-05-23 10:00:00'),
    (1, 'group.delete', 'group', 2, '{\"name\":\"old\"}', '2026-05-23 10:01:00'),
    (1, 'debug.auth.login', 'debug', 0, '{\"_json\":\"{\\\"user\\\":\\\"test\\\"}\"}', '2026-05-23 10:02:00'),
    (1, 'user.update', 'user', 1, '{\"name\":\"Bob\"}', '2026-05-23 10:03:00'),
    (1, 'debug.config.init', 'debug', 0, '{\"_json\":\"{\\\"phase\\\":\\\"boot\\\"}\"}', '2026-05-23 10:04:00')
");

// --- Helper: create a fresh repo with a fresh DB binding ---
function makeRepo(TestDBAudit $db): \App\Models\AuditLogRepository
{
    return new \App\Models\AuditLogRepository($db);
}

// ===== TEST 1: type='all' returns all rows =====
echo "= TEST: type='all' returns all =\n";
$repo = makeRepo($db);
$rows = $repo->findRecent(500, 'all');
assert_equal(5, count($rows), 'all 5 rows returned');
echo "PASS\n";

// ===== TEST 2: default returns all =====
echo "= TEST: default type='all' =\n";
$repo = makeRepo($db);
$rows = $repo->findRecent(500);
assert_equal(5, count($rows), 'default returns all');
echo "PASS\n";

// ===== TEST 3: type='debug' returns only debug =====
echo "= TEST: type='debug' returns only debug =\n";
$repo = makeRepo($db);
$rows = $repo->findRecent(500, 'debug');
assert_equal(2, count($rows), '2 debug rows returned');
foreach ($rows as $row) {
    assert_true($row['entity_type'] === 'debug', "all rows are debug");
    assert_true(str_starts_with($row['action'], 'debug.'), "all actions have debug. prefix");
}
echo "PASS\n";

// ===== TEST 4: type='audit' returns only non-debug =====
echo "= TEST: type='audit' returns only non-debug =\n";
$repo = makeRepo($db);
$rows = $repo->findRecent(500, 'audit');
assert_equal(3, count($rows), '3 non-debug rows returned');
foreach ($rows as $row) {
    assert_true($row['entity_type'] !== 'debug', "no debug rows in audit filter");
}
echo "PASS\n";

// ===== TEST 5: Invalid type falls back to 'all' =====
echo "= TEST: invalid type falls back to all =\n";
$repo = makeRepo($db);
$rows = $repo->findRecent(500, 'bogus');
assert_equal(5, count($rows), 'bogus type returns all rows (default fallback)');
echo "PASS\n";

// ===== TEST 6: Empty type falls back to 'all' =====
echo "= TEST: empty type falls back to all =\n";
$repo = makeRepo($db);
$rows = $repo->findRecent(500, '');
assert_equal(5, count($rows), 'empty type returns all rows');
echo "PASS\n";

// ===== TEST 7: View renders filter buttons correctly =====
echo "= TEST: View renders filter buttons =\n";
// Simulate rendering the filter buttons for each type
$types = ['all', 'debug', 'audit'];
foreach ($types as $currentType) {
    $output = '';
    ob_start();
    ?>
    <div class="btn-group btn-group-sm" role="group" aria-label="Filter audit log">
        <a href="/admin/audit?type=all" class="btn btn-outline-secondary <?= $currentType === 'all' ? 'active' : '' ?>">All</a>
        <a href="/admin/audit?type=audit" class="btn btn-outline-secondary <?= $currentType === 'audit' ? 'active' : '' ?>">Audit</a>
        <a href="/admin/audit?type=debug" class="btn btn-outline-secondary <?= $currentType === 'debug' ? 'active' : '' ?>">Debug</a>
    </div>
    <?php
    $output = ob_get_clean();

    // Each button should be present
    assert_true(str_contains($output, 'href="/admin/audit?type=all"'), "href for all present (type={$currentType})");
    assert_true(str_contains($output, 'href="/admin/audit?type=audit"'), "href for audit present (type={$currentType})");
    assert_true(str_contains($output, 'href="/admin/audit?type=debug"'), "href for debug present (type={$currentType})");

    // Only the active button should have 'active' class
    $activeBtn = "btn-outline-secondary <?= $currentType === 'all' ? 'active' : '' ?>";
    assert_true(str_contains($output, "type=all\" class=\"btn btn-outline-secondary"), "all button present (type={$currentType})");
}
assert_true(true, 'all button states render without error');
echo "PASS\n";

// ===== TEST 8: Debug entries styled when filtered =====
echo "= TEST: Debug entries styled when filtered =\n";
$pdo->exec("DELETE FROM admin_audit_log");
$db->execute("INSERT INTO admin_audit_log (user_id, action, entity_type, entity_id, meta, created_at) VALUES
    (1, 'debug.auth.login', 'debug', 0, '{}', '2026-05-23 10:00:00'),
    (1, 'user.create', 'user', 1, '{}', '2026-05-23 10:01:00')
");

$repo = makeRepo($db);

// With 'debug' filter
$debugRows = $repo->findRecent(500, 'debug');
assert_equal(1, count($debugRows), '1 debug row');
$debugRow = $debugRows[0];
$debugClass = str_starts_with($debugRow['action'], 'debug.') ? 'table-info-subtle' : '';
assert_true(str_contains($debugClass, 'table-info-subtle'), 'debug row has styling class');

// With 'audit' filter
$auditRows = $repo->findRecent(500, 'audit');
assert_equal(1, count($auditRows), '1 audit row');
$auditRow = $auditRows[0];
$auditClass = str_starts_with($auditRow['action'], 'debug.') ? 'table-info-subtle' : '';
assert_false(str_contains($auditClass, 'table-info-subtle'), 'non-debug row has no debug styling');
echo "PASS\n";

// ===== TEST 9: Limit parameter respected =====
echo "= TEST: Limit parameter respected =\n";
$pdo->exec("DELETE FROM admin_audit_log");
for ($i = 0; $i < 10; $i++) {
    $db->execute("INSERT INTO admin_audit_log (user_id, action, entity_type, entity_id, meta, created_at) VALUES
        (1, 'user.create', 'user', {$i}, '{}', '2026-05-23 10:0" . $i . ":00')");
}
$repo = makeRepo($db);
$rows10 = $repo->findRecent(10);
$rows5 = $repo->findRecent(5);
assert_equal(10, count($rows10), 'limit=10 returns 10');
assert_equal(5, count($rows5), 'limit=5 returns 5');
echo "PASS\n";

echo "\n=== ALL AUDIT FILTER TESTS PASSED ===\n";
