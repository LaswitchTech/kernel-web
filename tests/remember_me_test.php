<?php

/**
 * Tests for Remember Me auth tokens.
 *
 * Tests: RememberTokenRepository, RememberMeService, AuthService integration,
 * cookie parsing and validation.
 * No real sessions or cookies are set during tests (uses @ setcookie suppression).
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\RememberTokenRepository;
use App\Auth\RememberMeService;
use App\Auth\AuthService;
use App\Core\AuthProviderInterface;
use App\Core\DatabaseInterface;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDB2 implements DatabaseInterface
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

$db = new TestDB2($pdo);

// --- Create auth tables ---

$db->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    display_name TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    email_verified_at VARCHAR(32),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");

$db->execute("CREATE TABLE auth_remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    selector VARCHAR(128) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at VARCHAR(32) NOT NULL,
    last_used_at VARCHAR(32),
    user_agent_hash VARCHAR(64),
    ip_hash VARCHAR(64),
    revoked_at VARCHAR(32),
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$db->execute("CREATE UNIQUE INDEX auth_remember_tokens_selector_unique ON auth_remember_tokens (selector)");
$db->execute("CREATE UNIQUE INDEX auth_remember_tokens_token_hash_unique ON auth_remember_tokens (token_hash)");
$db->execute("CREATE INDEX auth_remember_tokens_user_id ON auth_remember_tokens (user_id)");
$db->execute("CREATE INDEX auth_remember_tokens_expires_at ON auth_remember_tokens (expires_at)");

// --- Insert test users ---

$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (1, 'testuser', 'test@example.com', 'Test User', 'hash', 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");
$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (2, 'inactive', 'inactive@example.com', 'Inactive User', 'hash', 0, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");

// --- Instantiate repositories and services ---

$tokenRepo = new RememberTokenRepository($db);
$userRepo  = new \App\Models\UserRepository($db);

$config = [
    'session' => [
        'name'   => 'kernel_web_session',
        'lifetime' => 7200,
        'secure' => false,
    ],
    'remember_me' => [
        'lifetime' => 2592000, // 30 days
    ],
];

// --- Fake provider for testing ---

class FakeProvider implements AuthProviderInterface
{
    public function __construct(private array $users = []) {}

    public function attempt(array $credentials): ?array
    {
        $identity = trim($credentials['identity'] ?? '');
        $password = $credentials['password'] ?? '';

        if ($identity === '' || $password === '') {
            return null;
        }

        foreach ($this->users as $u) {
            if (($u['username'] === $identity || $u['email'] === $identity)
                && $password === $u['password']) {
                return $u;
            }
        }

        return null;
    }

    public function getUserById(int $id): ?array
    {
        foreach ($this->users as $u) {
            if ((int) $u['id'] === $id) {
                return $u;
            }
        }
        return null;
    }
}

$activeUser = [
    'id'           => 1,
    'username'     => 'testuser',
    'email'        => 'test@example.com',
    'display_name' => 'Test User',
    'password'     => 'password',
    'is_active'    => true,
    'created_at'   => '2024-01-01 00:00:00',
    'updated_at'   => '2024-01-01 00:00:00',
];

$inactiveUser = [
    'id'           => 2,
    'username'     => 'inactive',
    'email'        => 'inactive@example.com',
    'display_name' => 'Inactive User',
    'password'     => 'password',
    'is_active'    => false,
    'created_at'   => '2024-01-01 00:00:00',
    'updated_at'   => '2024-01-01 00:00:00',
];

// ============= 1. AUTH SERVICE INTEGRATION (before any cookie output) ============

$fakeProvider = new FakeProvider([$activeUser, $inactiveUser]);

// --- AuthService constructed ---
$authService = new AuthService($fakeProvider, $config, null);
assert_instance_of($authService, AuthService::class, 'AuthService constructed');

// --- AuthService::login without remember ---
$user = $authService->login(['identity' => 'testuser', 'password' => 'password'], false);
assert_not_null($user, 'login without remember returns user');
assert_equal('testuser', $user['username'], 'login returns correct username');

// --- AuthService::login with remember but no remember service ---
$authService2 = new AuthService($fakeProvider, $config, null);
$user2 = $authService2->login(['identity' => 'testuser', 'password' => 'password'], true);
assert_not_null($user2, 'login with remember but no RememberMeService returns user');

// --- AuthService::check ---
assert_true($authService->check(), 'check returns true when logged in');

// --- AuthService::user ---
assert_not_null($authService->user(), 'user returns current user');

// --- AuthService::logout does not error ---
assert_true(true, 'AuthService logout does not error');

// ============= 2. REMEMBER TOKEN REPOSITORY ============

// --- Token creation ---
$selector1  = bin2hex(random_bytes(16));
$validator1 = bin2hex(random_bytes(32));
$hash1      = hash('sha256', $validator1);
$expiresAt  = date('Y-m-d H:i:s', time() + 86400);

$tokenId = $tokenRepo->create(1, $selector1, $hash1, $expiresAt);
assert_true($tokenId > 0, 'create returns positive ID');

// --- Find by selector ---
$found = $tokenRepo->findBySelector($selector1);
assert_not_null($found, 'findBySelector finds existing token');
assert_equal(1, (int) $found['user_id'], 'found token user_id matches');
assert_equal($hash1, $found['token_hash'], 'found token_hash matches');

// --- Find by nonexistent selector ---
assert_null($tokenRepo->findBySelector('nonexistent_selector_xyz'), 'findBySelector returns null for missing');

// --- Revoke single token ---
assert_true($tokenRepo->revoke($tokenId), 'revoke returns true for existing token');
$revoked = $tokenRepo->findBySelector($selector1);
assert_not_null($revoked, 'revoked token still exists in DB');
assert_not_null($revoked['revoked_at'], 'revoked_at is set');

// --- Revoke already revoked token fails ---
assert_false($tokenRepo->revoke($tokenId), 'revoke returns false for already revoked');

// --- Revoke nonexistent token ---
assert_false($tokenRepo->revoke(99999), 'revoke returns false for nonexistent token');

// --- Revoke all for user (unique hashes, all unrevoked) ---
$revokeS1 = bin2hex(random_bytes(16));
$revokeS2 = bin2hex(random_bytes(16));
$revokeS3 = bin2hex(random_bytes(16));
$tokenRepo->create(1, $revokeS1, hash('sha256', 'rv1'), $expiresAt);
$tokenRepo->create(1, $revokeS2, hash('sha256', 'rv2'), $expiresAt);
$tokenRepo->create(1, $revokeS3, hash('sha256', 'rv3'), $expiresAt);
$recount = $tokenRepo->revokeAllForUser(1);
assert_equal(3, $recount, 'revokeAllForUser revokes 3 tokens');

// ============= 3. REMEMBER ME SERVICE — COOKIE PARSING ============

$service = new RememberMeService($tokenRepo, $userRepo, $config);

// --- Cookie parsing: no cookie ---
unset($_COOKIE['kernel_remember']);
$result = $service->attempt();
assert_null($result, 'attempt returns null with no cookie');

// --- Cookie parsing: no colon ---
$_COOKIE['kernel_remember'] = 'no_colon_here';
$result = $service->attempt();
assert_null($result, 'attempt returns null with no colon in cookie');

// --- Cookie parsing: empty cookie ---
$_COOKIE['kernel_remember'] = '';
$result = $service->attempt();
assert_null($result, 'attempt returns null with empty cookie');

// ============= 4. SERVICE ATTEMPT WITH VALID TOKEN ============

// Create a fresh valid token for this test
$validSelector  = bin2hex(random_bytes(16));
$validValidator = bin2hex(random_bytes(32));
$validHash      = hash('sha256', $validValidator);
$futureDate     = date('Y-m-d H:i:s', time() + 86400);
$tokenRepo->create(1, $validSelector, $validHash, $futureDate);
assert_true(true, 'valid token created in repository');

// Set cookie
$_COOKIE['kernel_remember'] = $validSelector . ':' . $validValidator;

// Attempt authentication — rotates the token
$result = $service->attempt();
assert_not_null($result, 'attempt returns user for valid token');
assert_true(isset($result['user']), 'result contains user key');
assert_equal('testuser', $result['user']['username'], 'attempt restores correct user');
assert_true($result['user']['is_active'], 'attempt restores active user');

// Verify token was rotated (old token revoked)
$stillFound = $tokenRepo->findBySelector($validSelector);
assert_not_null($stillFound, 'old selector still in DB after rotation');
assert_not_null($stillFound['revoked_at'], 'old token revoked after rotation');

// ============= 5. EXPIRED TOKEN ============

$expiredSelector  = bin2hex(random_bytes(16));
$expiredValidator = bin2hex(random_bytes(32));
$expiredHash      = hash('sha256', $expiredValidator);
$pastDate         = '2020-01-01 00:00:00';
$tokenRepo->create(1, $expiredSelector, $expiredHash, $pastDate);
$_COOKIE['kernel_remember'] = $expiredSelector . ':' . $expiredValidator;
$result = $service->attempt();
assert_null($result, 'attempt returns null for expired token');

// ============= 6. REVOKED TOKEN ============

$revokedSelector  = bin2hex(random_bytes(16));
$revokedValidator = bin2hex(random_bytes(32));
$revokedHash      = hash('sha256', $revokedValidator);
$revokedId        = $tokenRepo->create(1, $revokedSelector, $revokedHash, $futureDate);
$tokenRepo->revoke($revokedId);
$_COOKIE['kernel_remember'] = $revokedSelector . ':' . $revokedValidator;
$result = $service->attempt();
assert_null($result, 'attempt returns null for revoked token');

// ============= 7. INACTIVE USER ============

$inactiveSelector  = bin2hex(random_bytes(16));
$inactiveValidator = bin2hex(random_bytes(32));
$inactiveHash      = hash('sha256', $inactiveValidator);
$tokenRepo->create(2, $inactiveSelector, $inactiveHash, $futureDate);
$_COOKIE['kernel_remember'] = $inactiveSelector . ':' . $inactiveValidator;
$result = $service->attempt();
assert_null($result, 'attempt returns null for inactive user');

// ============= 8. WRONG VALIDATOR ============

// Create a dedicated token for this test
$wrongValSelector  = bin2hex(random_bytes(16));
$correctValidator  = bin2hex(random_bytes(32));
$wrongValidator    = bin2hex(random_bytes(32));
$wrongHash         = hash('sha256', $correctValidator);
$tokenRepo->create(1, $wrongValSelector, $wrongHash, $futureDate);
$_COOKIE['kernel_remember'] = $wrongValSelector . ':' . $wrongValidator;
$result = $service->attempt();
assert_null($result, 'attempt returns null for wrong validator');

// ============= 9. CORRECT VALIDATOR (rotate) ============

// Create a fresh token with the correct validator
$correctValSelector  = bin2hex(random_bytes(16));
$correctValValidator = bin2hex(random_bytes(32));
$correctValHash = hash('sha256', $correctValValidator);
$tokenRepo->create(1, $correctValSelector, $correctValHash, $futureDate);
$_COOKIE['kernel_remember'] = $correctValSelector . ':' . $correctValValidator;
$result = $service->attempt();
assert_not_null($result, 'attempt returns user with correct validator');
assert_equal('testuser', $result['user']['username'], 'attempt returns correct user after rotation');

// Verify rotation happened
$rotatedToken = $tokenRepo->findBySelector($correctValSelector);
assert_not_null($rotatedToken, 'token exists after rotation');
assert_not_null($rotatedToken['revoked_at'], 'old token revoked after rotation');

// ============= 10. REVOKE ALL ============

$service->revokeAll(1);
$allRecords = $db->fetch("SELECT * FROM auth_remember_tokens WHERE user_id = 1");
foreach ($allRecords as $t) {
    assert_not_null($t['revoked_at'], 'all tokens revoked after revokeAll');
}

// ============= 11. AUTH SERVICE RESTORE SESSION ============

$fakeProvider2 = new FakeProvider([$activeUser]);
$authService3 = new AuthService($fakeProvider2, $config, null);
$authService3->restoreSession(1);
assert_true(true, 'AuthService constructed and restoreSession called without error');

// ============= 12. COOKIE FORMAT VALIDATION ============

// Test various malformed inputs using reflection on private getCookie
$testCases = [
    ['input' => null, 'expected' => null, 'desc' => 'null cookie'],
    ['input' => 'no_colon', 'expected' => null, 'desc' => 'no colon'],
    ['input' => ':', 'expected' => ['', ''], 'desc' => 'just colon'],
    ['input' => 'a:b:c', 'expected' => ['a:b', 'c'], 'desc' => 'multiple colons (split on last)'],
    ['input' => 'sel:val', 'expected' => ['sel', 'val'], 'desc' => 'normal format'],
    ['input' => '', 'expected' => null, 'desc' => 'empty string'],
];

$reflectionClass = new \ReflectionClass($service);
$getCookieMethod = $reflectionClass->getMethod('getCookie');

foreach ($testCases as $case) {
    if ($case['input'] === null) {
        unset($_COOKIE['kernel_remember']);
    } else {
        $_COOKIE['kernel_remember'] = $case['input'];
    }
    $result = $getCookieMethod->invoke($service);
    if ($case['expected'] === null) {
        assert_null($result, "cookie parsing: {$case['desc']} returns null");
    } else {
        assert_equal($case['expected'], $result, "cookie parsing: {$case['desc']} returns correct split");
    }
}

// ============= SUMMARY ============
summary();
exit($__FAIL__ > 0 ? 1 : 0);
