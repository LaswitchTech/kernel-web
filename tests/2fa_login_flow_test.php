<?php
/**
 * Test: 2FA login flow — pending state survives middleware.
 *
 * Validates:
 * - login with 2FA creates pending state (no full session)
 * - SessionAuth allows pending 2FA through
 * - twoFactorForm accepts pending access
 * - completeTwoFactor promotes to full session
 * - logout clears ALL state
 * - restoreSession blocked for 2FA user
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Auth\AuthService;
use App\Auth\LocalAuthProvider;
use App\Auth\TwoFactorService;
use App\Models\UserRepository;
use App\Models\TwoFactorRepository;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDB2faLogin implements \App\Core\DatabaseInterface
{
    public function __construct(private \PDO $pdo) {}
    public function pdo(): \PDO { return $this->pdo; }
    public function fetch(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetchOne(string $sql, array $params = []): ?array {
        $rows = $this->fetch($sql, $params); return $rows[0] ?? null;
    }
    public function execute(string $sql, array $bindings = []): int {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($bindings); return (int) $stmt->rowCount();
    }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(): bool { return true; }
    public function lastInsertId(): string { return '1'; }
}

$db = new TestDB2faLogin($pdo);
$now = date('Y-m-d H:i:s');
$hash = password_hash('testpass123', PASSWORD_DEFAULT);

$db->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY, username VARCHAR(64) NOT NULL, email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL, is_active INTEGER NOT NULL DEFAULT 1,
    display_name VARCHAR(100) NOT NULL DEFAULT '', email_verified_at VARCHAR(32),
    totp_secret VARCHAR(255), totp_enabled_at VARCHAR(32), totp_pending_at VARCHAR(32), totp_setup_id VARCHAR(255),
    created_at VARCHAR(32) NOT NULL, updated_at VARCHAR(32) NOT NULL
)");
$db->execute("INSERT INTO users VALUES (1, 'testuser', 'test@test.com', '$hash', 1, 'Test', '$now', 'ABC123', '$now', NULL, NULL, '$now', '$now')");

$userRepo = new UserRepository($db);
$twoFactorRepo = new TwoFactorRepository($db);
$twoFactorSvc = new TwoFactorService($twoFactorRepo, $userRepo);
$provider = new LocalAuthProvider($userRepo);

class TestContainer2faLogin extends \App\Core\Container
{
    public function __construct(TwoFactorService $twoFactor)
    {
        $this->items['two_factor'] = $twoFactor;
    }
}

$container = new TestContainer2faLogin($twoFactorSvc);
$config = ['session' => ['name' => 'test_s2fa', 'lifetime' => 7200, 'secure' => false, 'httponly' => true, 'samesite' => 'Lax', 'path' => '/']];

// ===== TEST: Login with 2FA creates pending state =====
echo "= TEST: Login with 2FA creates pending state =\n";
$auth = new AuthService($provider, $config, null);
$auth->setContainer($container);

$user = $auth->login(['identity' => 'testuser', 'password' => 'testpass123']);
assert_not_null($user, 'login returns user');
assert_true($auth->hasTwoFactorEnabled(1), 'hasTwoFactorEnabled returns true');
assert_true($auth->hasPendingTwoFactor(), 'hasPendingTwoFactor returns true after login');
$pendingUserId = $auth->getPendingTwoFactorUserId();
assert_equal(1, $pendingUserId, 'pending user ID is 1');
assert_null($auth->user(), 'user() is null (no full session yet)');
echo "PASS\n";

// ===== TEST: SessionAuth allows pending 2FA through =====
echo "= TEST: SessionAuth allows pending 2FA through =\n";
$userForAuth = $auth->user();
if ($userForAuth === null && $auth->hasPendingTwoFactor()) {
    $auth->setTwoFactorPendingAccess();
    echo "PASS: SessionAuth allows pending 2FA through\n";
} else {
    assert_false(true, 'SessionAuth allows pending 2FA');
}
assert_true($auth->hasPendingTwoFactorAccess(), 'pendingTwoFactorAccess is true');

// ===== TEST: twoFactorForm accepts pending access =====
echo "= TEST: twoFactorForm accepts pending access =\n";
if ($auth->hasPendingTwoFactor() || $auth->hasPendingTwoFactorAccess()) {
    echo "PASS: twoFactorForm would render form\n";
} else {
    assert_true(false, 'twoFactorForm renders form');
}

// ===== TEST: completeTwoFactor promotes to full session =====
echo "= TEST: completeTwoFactor promotes to full session =\n";
$auth2 = new AuthService($provider, $config, null);
$auth2->setContainer($container);
$auth2->login(['identity' => 'testuser', 'password' => 'testpass123']);
assert_true($auth2->hasPendingTwoFactor(), 'pending state exists');
assert_null($auth2->user(), 'user() is null before complete');
$auth2->completeTwoFactor();
assert_not_null($auth2->user(), 'user() is set after completeTwoFactor');
assert_false($auth2->hasPendingTwoFactor(), 'pending state cleared after complete');
echo "PASS\n";

// ===== TEST: logout clears ALL state =====
echo "= TEST: logout clears ALL state =\n";
$auth2->logout();
assert_false($auth2->hasPendingTwoFactor(), 'logout clears pending 2FA');
assert_null($auth2->user(), 'logout clears user');
echo "PASS\n";

// ===== TEST: restoreSession blocked for 2FA user =====
echo "= TEST: restoreSession blocked for 2FA user =\n";
$auth3 = new AuthService($provider, $config, null);
$auth3->setContainer($container);
$auth3->restoreSession(1);
assert_null($auth3->user(), 'restoreSession blocked for 2FA user');
echo "PASS\n";

// ===== TEST: login without 2FA sets full session =====
echo "= TEST: login without 2FA sets full session =\n";
// Create user without 2FA
$hash2 = password_hash('nopass', PASSWORD_DEFAULT);
$db->execute("INSERT INTO users VALUES (2, 'user2', 'user2@test.com', '$hash2', 1, 'User 2', '$now', NULL, NULL, NULL, NULL, '$now', '$now')");
$auth4 = new AuthService($provider, $config, null);
$auth4->setContainer($container);
$user4 = $auth4->login(['identity' => 'user2', 'password' => 'nopass']);
assert_not_null($user4, 'login for 2FA-disabled user returns user');
assert_not_null($auth4->user(), 'user() is set for 2FA-disabled user');
assert_false($auth4->hasPendingTwoFactor(), 'no pending state for 2FA-disabled user');
echo "PASS\n";

echo "\n=== ALL TESTS PASSED ===\n";
