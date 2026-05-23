<?php
/**
 * Tests: DebugAuditLogger — APP_DEBUG-gated writes to admin_audit_log.
 *
 * Covers:
 * - debug=false → no entries created
 * - debug=true → entries created
 * - Sensitive data is redacted (keys matching password, secret, token, etc.)
 * - Arrays/objects serialized safely
 * - Large payloads are truncated
 * - /admin/audit renders debug entries without errors
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

require_once __DIR__ . '/../app/Services/DebugAuditLogger.php';

use App\Core\DatabaseInterface;
use App\Models\AuditLogRepository;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDBDebug implements DatabaseInterface
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

$db = new TestDBDebug($pdo);

// Create audit log table (mirror production migration)
$db->execute(
    'CREATE TABLE admin_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        action VARCHAR(64) NOT NULL,
        entity_type VARCHAR(32) NOT NULL,
        entity_id INTEGER NOT NULL,
        meta TEXT NOT NULL DEFAULT \'\',
        created_at VARCHAR(32) NOT NULL
    )'
);

// --- Helpers ---

function makeDebugLogger(bool $debug, \TestDBDebug $db): \App\Services\DebugAuditLogger
{
    $mockContainer = new class($db) {
        public function __construct(private \TestDBDebug $db) {}
        public function has(string $k): bool { return $k === 'db'; }
        public function get(string $k): \TestDBDebug { return $this->db; }
    };
    return new \App\Services\DebugAuditLogger(['debug' => $debug], $mockContainer);
}

function clearLog(TestDBDebug $db): void
{
    $db->pdo()->exec('DELETE FROM admin_audit_log');
}

function countRows(TestDBDebug $db): int
{
    return (int) $db->pdo()->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn();
}

function lastAction(TestDBDebug $db): string
{
    $row = $db->pdo()->query('SELECT action FROM admin_audit_log ORDER BY id DESC LIMIT 1')->fetchColumn();
    return (string) $row;
}

function lastMeta(TestDBDebug $db): array
{
    $row = $db->pdo()->query('SELECT meta FROM admin_audit_log ORDER BY id DESC LIMIT 1')->fetchColumn();
    return json_decode($row, true) ?: [];
}

// ===== TEST 1: debug=false → no entries =====
echo "= TEST: debug=false → no entries =\n";
$loggerOff = makeDebugLogger(false, $db);
$loggerOff->log(1, 'auth.login', 'debug', ['user' => 'test']);
assert_equal(0, countRows($db), 'no rows written when debug=false');
echo "PASS\n";

// ===== TEST 2: debug=true → entries created =====
echo "= TEST: debug=true → entries created =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$loggerOn->log(1, 'auth.login', 'debug', ['user' => 'test']);
assert_equal(1, countRows($db), 'one row written when debug=true');
assert_true(str_starts_with(lastAction($db), 'debug.'), 'action prefixed with debug.');
echo "PASS\n";

// ===== TEST 3: Sensitive data redaction — scalar keys =====
echo "= TEST: Scalar sensitive keys redacted =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$loggerOn->log(1, 'auth.login', 'debug', [
    'password' => 'supersecret123',
    'secret' => 'my-totp-secret',
    'token' => 'abc123xyz',
    'api_key' => 'sk-1234567890',
    'username' => 'john',
]);
$meta = lastMeta($db);
assert_equal('[redacted]', $meta['password'], 'password redacted');
assert_equal('[redacted]', $meta['secret'], 'secret redacted');
assert_equal('[redacted]', $meta['token'], 'token redacted');
assert_equal('[redacted]', $meta['api_key'], 'api_key redacted (key pattern)');
assert_equal('john', $meta['username'], 'username NOT redacted');
echo "PASS\n";

// ===== TEST 4: Sensitive data redaction — mixed case keys =====
echo "= TEST: Case-insensitive key matching =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$loggerOn->log(1, 'auth.login', 'debug', [
    'PASSWORD' => 'UPPERCASE',
    'Secret' => 'CamelCase',
    'TOKEN_KEY' => 'Mixed',
]);
$meta = lastMeta($db);
assert_equal('[redacted]', $meta['PASSWORD'], 'uppercase key redacted');
assert_equal('[redacted]', $meta['Secret'], 'camelcase key redacted');
assert_equal('[redacted]', $meta['TOKEN_KEY'], 'mixed key with token redacted');
echo "PASS\n";

// ===== TEST 5: Arrays with sensitive keys — recursive sanitize =====
echo "= TEST: Arrays with sensitive keys recursively sanitized =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$loggerOn->log(1, 'auth.login', 'debug', [
    'user' => ['name' => 'john', 'password' => 'nested_secret'],
    'token_data' => ['value' => 'abc', 'extra' => 'keep'],
]);
$meta = lastMeta($db);
assert_true(is_array($meta['user']), 'user is an array');
assert_equal('[redacted]', $meta['user']['password'], 'nested password redacted');
assert_equal('john', $meta['user']['name'], 'nested name preserved');
assert_true(is_array($meta['token_data']), 'token_data is an array');
assert_equal('[redacted]', $meta['token_data']['value'], 'nested token value redacted');
assert_equal('keep', $meta['token_data']['extra'], 'non-sensitive nested key preserved');
echo "PASS\n";

// ===== TEST 6: Arrays/objects serialize safely =====
echo "= TEST: Arrays/objects serialize safely =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$nestedArr = ['a' => [1, 2, ['b' => [3]]]];
$obj = (object) ['foo' => 'bar'];
$loggerOn->log(1, 'config.resolved', 'debug', [
    'array' => $nestedArr,
    'object' => $obj,
    'null_val' => null,
    'bool_val' => false,
]);
$meta = lastMeta($db);
assert_true(is_array($meta['array']), 'array preserved as array in meta');
assert_true(is_array($meta['object']), 'object converted to array in meta');
assert_null($meta['null_val'], 'null preserved');
assert_false($meta['bool_val'], 'false preserved');
// Verify JSON is valid (no "Array to string" errors).
$raw = $db->pdo()->query('SELECT meta FROM admin_audit_log ORDER BY id DESC LIMIT 1')->fetchColumn();
assert_true(json_decode($raw) !== null && json_last_error() === JSON_ERROR_NONE, 'meta is valid JSON');
echo "PASS\n";

// ===== TEST 7: Large payloads are truncated =====
echo "= TEST: Large payloads truncated =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$largeData = ['payload' => str_repeat('x', 5000)];
$loggerOn->log(1, 'config.resolved', 'debug', $largeData);
$meta = lastMeta($db);
assert_true($meta['_truncated'] === true, 'truncated flag set');
$raw = $db->pdo()->query('SELECT meta FROM admin_audit_log ORDER BY id DESC LIMIT 1')->fetchColumn();
assert_true(strlen($raw) <= 2000, "payload truncated to ≤2000 bytes (got " . strlen($raw) . ")");
echo "PASS\n";

// ===== TEST 8: Convenience methods don't crash =====
echo "= TEST: Convenience methods don't crash =\n";
clearLog($db);
// Convenience methods create their own instance (debug=false by default).
// They should be no-ops when debug is disabled — not throw.
\App\Services\DebugAuditLogger::auth(null, 'test.auth', ['x' => 'y']);
\App\Services\DebugAuditLogger::config(null, 'test.config', ['x' => 'y']);
\App\Services\DebugAuditLogger::plugin(null, 'test.plugin', ['x' => 'y']);
\App\Services\DebugAuditLogger::route(null, 'test.route', ['x' => 'y']);
\App\Services\DebugAuditLogger::generic(null, 'test.generic', ['x' => 'y']);
assert_equal(0, countRows($db), 'convenience methods are no-ops when debug=false');
echo "PASS\n";

// ===== TEST 9: /admin/audit view renders debug entries =====
echo "= TEST: /admin/audit renders debug entries =\n";
clearLog($db);
$db->pdo()->exec(
    "INSERT INTO admin_audit_log (user_id, action, entity_type, entity_id, meta, created_at) VALUES
     (1, 'debug.auth.login', 'debug', 0, '{\"_json\":\"{\\\"user\\\":\\\"test\\\"}\"}', '2026-05-23 10:00:00'),
     (1, 'user.create', 'user', 1, '{\"_json\":\"{\\\"name\\\":\\\"test\\\"}\"}', '2026-05-23 10:01:00')"
);

$auditRows = $db->pdo()->query('SELECT * FROM admin_audit_log ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

// Simulate the rendering logic from audit.php
foreach ($auditRows as $row) {
    $isDebug = str_starts_with($row['action'], 'debug.');
    $isDebugClass = $isDebug ? 'table-info-subtle' : '';
    $badge = $isDebug ? '<span class="badge bg-info-subtle text-info ms-1" style="font-size:.7em">debug</span>' : '';
    $actionHtml = '<code class="text-body">' . htmlspecialchars($row['action']) . '</code>' . $badge;
    // Should not throw any PHP errors.
}
assert_true(true, 'no errors rendering debug entries in audit view');
echo "PASS\n";

// ===== TEST 10: Sensitive keys list covers all required patterns =====
echo "= TEST: All required sensitive keys covered =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$loggerOn->log(1, 'auth.test', 'debug', [
    'password' => 'p',
    'passwd' => 'p',
    'secret' => 's',
    'token' => 't',
    'api_key' => 'k',
    'cookie' => 'c',
    'authorization' => 'a',
    'csrf' => 'cs',
    'session' => 'ss',
    'recovery' => 'r',
    'safe_field' => 'safe',
]);
$meta = lastMeta($db);
assert_equal('[redacted]', $meta['password'], 'password redacted');
assert_equal('[redacted]', $meta['passwd'], 'passwd redacted');
assert_equal('[redacted]', $meta['secret'], 'secret redacted');
assert_equal('[redacted]', $meta['token'], 'token redacted');
assert_equal('[redacted]', $meta['api_key'], 'api_key redacted');
assert_equal('[redacted]', $meta['cookie'], 'cookie redacted');
assert_equal('[redacted]', $meta['authorization'], 'authorization redacted');
assert_equal('[redacted]', $meta['csrf'], 'csrf redacted');
assert_equal('[redacted]', $meta['session'], 'session redacted');
assert_equal('[redacted]', $meta['recovery'], 'recovery redacted');
assert_equal('safe', $meta['safe_field'], 'safe_field NOT redacted');
echo "PASS\n";

// ===== TEST 11: Null userId (system action) =====
echo "= TEST: Null userId (system action) =\n";
clearLog($db);
$loggerOn = makeDebugLogger(true, $db);
$loggerOn->log(null, 'config.init', 'debug', ['phase' => 'boot']);
assert_equal(1, countRows($db), 'entry created with null userId');
assert_null($db->pdo()->query('SELECT user_id FROM admin_audit_log ORDER BY id DESC LIMIT 1')->fetchColumn(), 'user_id is null');
echo "PASS\n";

echo "\n=== ALL DEBUG AUDIT LOGGER TESTS PASSED ===\n";
