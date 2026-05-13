<?php

/**
 * Tests for Two-Factor Authentication (TOTP).
 *
 * Tests: TwoFactorRepository, TwoFactorService, AuthService pending 2FA flow.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\TwoFactorRepository;
use App\Auth\TwoFactorService;
use App\Auth\AuthService;
use App\Core\DatabaseInterface;
use App\Core\Container;
use App\Core\AuthProviderInterface;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDB3 implements DatabaseInterface
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

$db = new TestDB3($pdo);

// --- Create auth tables ---

$db->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    display_name TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    email_verified_at VARCHAR(32),
    totp_secret VARCHAR(255),
    totp_enabled_at VARCHAR(32),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");

$db->execute("CREATE TABLE auth_2fa_recovery_codes (
    id         INTEGER NOT NULL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash  VARCHAR(255) NOT NULL,
    used_at    VARCHAR(255),
    created_at TEXT NOT NULL,
    UNIQUE(code_hash)
)");

$db->execute("CREATE INDEX auth_2fa_recovery_codes_user_id ON auth_2fa_recovery_codes (user_id)");

// Create test user
$passwordHash = password_hash('testpass123', PASSWORD_DEFAULT);
$db->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (1, 'testuser', 'test@example.com', 'Test User', ?, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [$passwordHash]
);

$db->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (2, 'inactiveuser', 'inactive@example.com', 'Inactive User', ?, 0, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [$passwordHash]
);

// --- Instantiate ---

$tokenRepo = new TwoFactorRepository($db);
$userRepo  = new App\Models\UserRepository($db);
$service   = new TwoFactorService($tokenRepo, $userRepo);

// Fake auth provider
class FakeProvider implements AuthProviderInterface
{
    private array $users;
    public function __construct(array $users) { $this->users = $users; }
    public function attempt(array $credentials): ?array
    {
        $identity = trim($credentials['identity'] ?? '');
        $password = $credentials['password'] ?? '';
        foreach ($this->users as $u) {
            if (($u['username'] === $identity || $u['email'] === $identity) && password_verify($password, $u['password_hash'] ?? '')) {
                return [
                    'id' => $u['id'], 'display_name' => $u['display_name'],
                    'username' => $u['username'], 'email' => $u['email'],
                    'is_active' => $u['is_active'], 'created_at' => $u['created_at'],
                    'updated_at' => $u['updated_at'],
                ];
            }
        }
        return null;
    }
    public function getUserById(int $id): ?array
    {
        foreach ($this->users as $u) {
            if ($u['id'] === $id) {
                return [
                    'id' => $u['id'], 'display_name' => $u['display_name'],
                    'username' => $u['username'], 'email' => $u['email'],
                    'is_active' => $u['is_active'], 'created_at' => $u['created_at'],
                    'updated_at' => $u['updated_at'],
                ];
            }
        }
        return null;
    }
}

$config = [
    'provider' => 'local',
    'session' => ['name' => 'test_session', 'lifetime' => 7200, 'secure' => false],
    'remember_me' => ['enabled' => false],
];

// ============= 1. SECRET GENERATION ============

$secret = $service->generateSecret(1);
assert_not_null($secret, 'generateSecret returns secret for active user');
assert_true((bool) preg_match('/^[A-Z2-7]+$/', $secret), 'secret is valid base32');

// Secret stored in DB
$stored = $tokenRepo->getTotpSecret(1);
assert_not_null($stored, 'secret stored in DB');
assert_equal($secret, $stored['totp_secret'], 'stored secret matches generated');

// Nonexistent user
assert_null($service->generateSecret(9999), 'generateSecret returns null for nonexistent user');

// ============= 2. TOTP VERIFICATION ============

// Use reflection to access private decodeBase32
$reflection = new ReflectionClass($service);
$decodeMethod = $reflection->getMethod('decodeBase32');

// Generate a secret for TOTP testing
$testSecret = $service->generateSecret(1);
assert_not_null($testSecret, 'test secret generated for TOTP verification');

$hexSecret = hex2bin($decodeMethod->invoke($service, $testSecret));
assert_not_null($hexSecret, 'base32 secret decoded to hex');
assert_true(strlen($hexSecret) > 0, 'hex secret is non-empty');

// Generate a valid TOTP code at the current step
$currentStep = (int) floor(time() / 30);
$stepPack = pack('N*', $currentStep);
$hmac = hash_hmac('sha1', $stepPack, $hexSecret, true);
$offset = ord($hmac[19]) & 0x0F;
$codeNum = ((ord($hmac[$offset]) & 0x7F) << 24)
         | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
         | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
         | (ord($hmac[$offset + 3]) & 0xFF);
$validCode = str_pad((string) ($codeNum % 1000000), 6, '0', STR_PAD_LEFT);

assert_true($service->verifyTotp(1, $validCode), 'current TOTP code verifies');

// ============= 3. WRONG CODE FAILS ============

$wrongCode = str_pad((string) (($codeNum + 7) % 1000000), 6, '0', STR_PAD_LEFT);
assert_false($service->verifyTotp(1, $wrongCode), 'wrong TOTP code fails');
assert_false($service->verifyTotp(1, '000000'), 'all-zero code fails');

// ============= 4. ±1 TIME-STEP WINDOW ============

// Previous step
$prevStep = $currentStep - 1;
$prevPack = pack('N*', $prevStep);
$prevHmac = hash_hmac('sha1', $prevPack, $hexSecret, true);
$prevOffset = ord($prevHmac[19]) & 0x0F;
$prevCodeNum = ((ord($prevHmac[$prevOffset]) & 0x7F) << 24)
             | ((ord($prevHmac[$prevOffset + 1]) & 0xFF) << 16)
             | ((ord($prevHmac[$prevOffset + 2]) & 0xFF) << 8)
             | (ord($prevHmac[$prevOffset + 3]) & 0xFF);
$prevCode = str_pad((string) ($prevCodeNum % 1000000), 6, '0', STR_PAD_LEFT);

// Next step
$nextStep = $currentStep + 1;
$nextPack = pack('N*', $nextStep);
$nextHmac = hash_hmac('sha1', $nextPack, $hexSecret, true);
$nextOffset = ord($nextHmac[19]) & 0x0F;
$nextCodeNum = ((ord($nextHmac[$nextOffset]) & 0x7F) << 24)
             | ((ord($nextHmac[$nextOffset + 1]) & 0xFF) << 16)
             | ((ord($nextHmac[$nextOffset + 2]) & 0xFF) << 8)
             | (ord($nextHmac[$nextOffset + 3]) & 0xFF);
$nextCode = str_pad((string) ($nextCodeNum % 1000000), 6, '0', STR_PAD_LEFT);

assert_true($service->verifyTotp(1, $prevCode), 'previous step code verifies (±1 window)');
assert_true($service->verifyTotp(1, $nextCode), 'next step code verifies (±1 window)');

// Step -2 (should fail)
$tooOldStep = $currentStep - 2;
$tooOldPack = pack('N*', $tooOldStep);
$tooOldHmac = hash_hmac('sha1', $tooOldPack, $hexSecret, true);
$tooOldOffset = ord($tooOldHmac[19]) & 0x0F;
$tooOldCodeNum = ((ord($tooOldHmac[$tooOldOffset]) & 0x7F) << 24)
               | ((ord($tooOldHmac[$tooOldOffset + 1]) & 0xFF) << 16)
               | ((ord($tooOldHmac[$tooOldOffset + 2]) & 0xFF) << 8)
               | (ord($tooOldHmac[$tooOldOffset + 3]) & 0xFF);
$tooOldCode = str_pad((string) ($tooOldCodeNum % 1000000), 6, '0', STR_PAD_LEFT);
assert_false($service->verifyTotp(1, $tooOldCode), 'step -2 code fails (outside ±1 window)');

// ============= 5. RECOVERY CODES ============

// Enable 2FA
$enableResult = $service->enable(1);
assert_true($service->isEnabled(1), '2FA enabled after enable()');
assert_true(count($enableResult['recoveryCodes']) > 0, 'enable() generates recovery codes');

// Verify secret and timestamp set
$secretAfter = $tokenRepo->getTotpSecret(1);
assert_not_null($secretAfter['totp_secret'], 'totp_secret set on enable');
assert_not_null($secretAfter['totp_enabled_at'], 'totp_enabled_at set on enable');

// Consume one recovery code
$recoveryCodes = $tokenRepo->getUnusedCodes(1);
$rawCode = null;
foreach ($enableResult['recoveryCodes'] as $rc) {
    if ($rc['codeHash'] === $recoveryCodes[0]['code_hash']) {
        $rawCode = $rc['code'];
        break;
    }
}
assert_not_null($rawCode, 'found raw code for recovery code test');
assert_true($service->validateRecoveryCode(1, $rawCode), 'recovery code validates once');
assert_false($service->validateRecoveryCode(1, $rawCode), 'recovery code consumed — cannot reuse');

// ============= 6. DISABLE CLEARS SECRET AND CODES ============

$service->disable(1);
assert_false($service->isEnabled(1), '2FA disabled after disable()');

$secretCleared = $tokenRepo->getTotpSecret(1);
assert_null($secretCleared['totp_secret'], 'totp_secret cleared on disable');
assert_null($secretCleared['totp_enabled_at'], 'totp_enabled_at cleared on disable');

$codesCleared = $tokenRepo->getUnusedCodes(1);
assert_true(empty($codesCleared), 'all recovery codes cleared on disable');

// verifyTotp fails after disable (no secret)
assert_false($service->verifyTotp(1, $validCode), 'verifyTotp fails after disable (no secret)');

// ============= 7. NON-ENABLED USER VERIFY FAILS ===-==

assert_false($service->isEnabled(1), 'user 1 has no secret');
assert_false($service->verifyTotp(1, $validCode), 'verifyTotp returns false for user without 2FA');

// ============= 8. NON-EXISTENT USER ===---

assert_null($service->generateSecret(9999), 'generateSecret returns null for nonexistent user');
assert_false($service->isEnabled(9999), 'isEnabled returns false for nonexistent user');

// ============= 9. RECOVERY CODES STORED AS HASHES ============

$enableResult2 = $service->enable(1);
assert_true(count($enableResult2['recoveryCodes']) > 0, 'recovery codes generated');

foreach ($enableResult2['recoveryCodes'] as $rc) {
    $computedHash = hash('sha256', $rc['code']);
    assert_equal($computedHash, $rc['codeHash'], 'stored hash matches computed hash');
}

// ============= 10. OTPAUTH URI ===--===

$service->disable(1);
$secret3 = $service->generateSecret(1);
assert_not_null($secret3, 'secret generated');

$uri = $service->getOtpauthUri(1, 'TestApp', 'test@example.com');
assert_true(str_starts_with($uri, 'otpauth://totp/'), 'URI starts with otpauth://totp/');
assert_true(str_contains($uri, 'secret=' . $secret3), 'URI contains the secret');
assert_true(str_contains($uri, 'test@example.com'), 'URI contains the email');
assert_true(str_contains($uri, 'algorithm=SHA1'), 'URI uses SHA1');
assert_true(str_contains($uri, 'digits=6'), 'URI uses 6 digits');
assert_true(str_contains($uri, 'period=30'), 'URI uses 30s period');

// ============= 11. AUTH SERVICE PENDING 2FA FLOW ============

// Reset user 1 — no 2FA
$service->disable(1);

$container = new Container();
$container->set('db', $db);
$container->set('two_factor', new TwoFactorService(new TwoFactorRepository($db), new App\Models\UserRepository($db)));

$fakeProviderForAuth = new FakeProvider([
    ['id' => 1, 'display_name' => 'Test User', 'username' => 'testuser',
     'email' => 'test@example.com', 'is_active' => 1,
     'password_hash' => $passwordHash,
     'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'],
]);

// -- Login without 2FA (no pending state) --
$authService = new AuthService($fakeProviderForAuth, $config, null);
$authService->setContainer($container);

$user = $authService->login(['identity' => 'testuser', 'password' => 'testpass123']);
assert_not_null($user, 'login returns user when no 2FA');
assert_false($authService->hasPendingTwoFactor(), 'no pending 2FA when user has no 2FA');

// ============= 12. AUTH SERVICE WITH 2FA ENABLED ============

// Enable 2FA for user
$service->generateSecret(1);
$service->enable(1);
assert_true($service->isEnabled(1), '2FA enabled for user in auth test');

$authService2 = new AuthService($fakeProviderForAuth, $config, null);
$authService2->setContainer($container);

// Login should return user AND set pending 2FA
$user2 = $authService2->login(['identity' => 'testuser', 'password' => 'testpass123']);
assert_not_null($user2, 'login returns user even when 2FA enabled');
assert_true($authService2->hasPendingTwoFactor(), 'pending 2FA state set on login');

$pendingUserId = $authService2->getPendingTwoFactorUserId();
assert_not_null($pendingUserId, 'pending user ID available');
assert_equal(1, $pendingUserId, 'pending user ID matches login user');

// -- completeTwoFactor: expired pending state --
$_SESSION['_2fa_expires'] = time() - 1;
$authService2->completeTwoFactor();
assert_false($authService2->hasPendingTwoFactor(), 'expired pending state cleared');

// -- completeTwoFactor: valid pending state --
$authService3 = new AuthService($fakeProviderForAuth, $config, null);
$authService3->setContainer($container);

$authService3->login(['identity' => 'testuser', 'password' => 'testpass123']);
assert_true($authService3->hasPendingTwoFactor(), 'fresh pending 2FA state exists');
$authService3->completeTwoFactor();
assert_false($authService3->hasPendingTwoFactor(), 'pending 2FA cleared after complete');
assert_not_null($authService3->user(), 'user available after completeTwoFactor');

// ============= 13. ENABLE/DISABLE WITHOUT GENERATE ============

// Clear user 1 and re-create
$db->execute("DELETE FROM users WHERE id = 1");
$db->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (1, 'testuser', 'test@example.com', 'Test User', ?, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [$passwordHash]
);

$enableDirect = $service->enable(1);
assert_not_null($enableDirect['secret'], 'enable() generates secret when none exists');
assert_true($service->isEnabled(1), '2FA enabled after direct enable()');

// ============= 14. RECOVERY CODES ON DISABLE ===---

$service->generateSecret(1);
$service->enable(1);
$service->disable(1);
$codesAfter = $tokenRepo->getUnusedCodes(1);
assert_true(empty($codesAfter), 'recovery codes cleared on disable');

// ============= 15. RECOVERY CODE GENERATES EXACT COUNT ============

// Create a new user specifically for this test
$db->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (3, 'recoveryuser', 'recovery@example.com', 'Recovery User', ?, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [$passwordHash]
);

// Disable any existing 2FA first
$service->disable(3);

$recoveryResult = $service->generateRecoveryCodes(3);
assert_equal(10, count($recoveryResult), 'generateRecoveryCodes generates 10 codes');

// All should be unique hashes
$hashes = array_column($recoveryResult, 'codeHash');
$uniqueHashes = array_unique($hashes);
assert_equal(count($hashes), count($uniqueHashes), 'all recovery code hashes are unique');

// ============= SUMMARY ============
summary();
exit($__FAIL__ > 0 ? 1 : 0);
