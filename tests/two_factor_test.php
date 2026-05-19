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
    totp_pending_at VARCHAR(32),
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

// ============= 16. PENDING SECRET — ENABLE IS FALSE (2FA SAFETY) ============
// Critical safety test: generating a TOTP secret must NOT enable 2FA.
// This prevents users from getting locked out just by viewing the 2FA setup tab.

// Reset user 1 — no 2FA
$service->disable(1);
assert_false($service->isEnabled(1), 'user 1 has no 2FA before test');
assert_false($service->hasPendingSetup(1), 'user 1 has no pending setup');

// Generate a secret (simulates opening the 2FA tab)
$pendingSecret = $service->generateSecret(1);
assert_not_null($pendingSecret, 'generateSecret returns secret');

// isEnabled must be false — the secret is pending, not enabled
assert_false($service->isEnabled(1), 'isEnabled returns false for pending secret');
assert_true($service->hasPendingSetup(1), 'hasPendingSetup returns true for pending secret');

// Verify the secret is stored in DB (so it can be used for OTP verification)
$pendingData = $tokenRepo->getTotpSecret(1);
assert_not_null($pendingData['totp_secret'], 'pending secret is stored in DB');
assert_true(!empty($pendingData['totp_pending_at']), 'pending timestamp is stored in DB');
assert_true(empty($pendingData['totp_enabled_at']), 'totp_enabled_at is NOT set for pending secret');

// Verify TOTP code for pending secret works (user can still scan QR and get a code)
$pendingHexSecret = hex2bin($decodeMethod->invoke($service, $pendingSecret));
assert_not_null($pendingHexSecret, 'pending hex secret decoded');
$pendingStep = (int) floor(time() / 30);
$pendingPack = pack('N*', $pendingStep);
$pendingHmac = hash_hmac('sha1', $pendingPack, $pendingHexSecret, true);
$pendingOffset = ord($pendingHmac[19]) & 0x0F;
$pendingCodeNum = ((ord($pendingHmac[$pendingOffset]) & 0x7F) << 24)
              | ((ord($pendingHmac[$pendingOffset + 1]) & 0xFF) << 16)
              | ((ord($pendingHmac[$pendingOffset + 2]) & 0xFF) << 8)
              | (ord($pendingHmac[$pendingOffset + 3]) & 0xFF);
$pendingCode = str_pad((string) ($pendingCodeNum % 1000000), 6, '0', STR_PAD_LEFT);

// The code verifies against the pending secret (but doesn't enable 2FA)
assert_true($service->verifyTotp(1, $pendingCode), 'TOTP code verifies for pending secret');
assert_false($service->isEnabled(1), 'verifyTotp does NOT enable 2FA for pending secret');

// Disable while pending — must work (no OTP code needed)
$service->disable(1);
assert_false($service->isEnabled(1), '2FA not enabled after disable (pending state)');
assert_false($service->hasPendingSetup(1), 'pending setup cleared after disable');
$clearedData = $tokenRepo->getTotpSecret(1);
assert_null($clearedData['totp_secret'], 'totp_secret cleared on disable');
assert_null($clearedData['totp_pending_at'], 'totp_pending_at cleared on disable');
assert_null($clearedData['totp_enabled_at'], 'totp_enabled_at cleared on disable');

// ============= 17. SECRET ONLY ENABLED AFTER VALID OTP CONFIRMATION ============
// The full 2FA enable flow: generate → confirm → enabled.

$service->disable(1);
assert_false($service->isEnabled(1), 'user reset before enable flow test');

// Generate pending secret
$setupSecret = $service->generateSecret(1);
assert_not_null($setupSecret, 'setup secret generated');
assert_false($service->isEnabled(1), 'still not enabled after generate');
assert_true($service->hasPendingSetup(1), 'has pending setup');

// Try to enable with WRONG code — should fail
$wrongCode = '000000';
assert_false($service->verifyTotp(1, $wrongCode), 'wrong code does not match TOTP');
assert_false($service->isEnabled(1), 'still not enabled after wrong code');

// Generate a valid code and call enable()
$setupHexSecret = hex2bin($decodeMethod->invoke($service, $setupSecret));
assert_not_null($setupHexSecret, 'setup hex secret decoded');
$setupStep = (int) floor(time() / 30);
$setupPack = pack('N*', $setupStep);
$setupHmac = hash_hmac('sha1', $setupPack, $setupHexSecret, true);
$setupOffset = ord($setupHmac[19]) & 0x0F;
$setupCodeNum = ((ord($setupHmac[$setupOffset]) & 0x7F) << 24)
           | ((ord($setupHmac[$setupOffset + 1]) & 0xFF) << 16)
           | ((ord($setupHmac[$setupOffset + 2]) & 0xFF) << 8)
           | (ord($setupHmac[$setupOffset + 3]) & 0xFF);
$validEnableCode = str_pad((string) ($setupCodeNum % 1000000), 6, '0', STR_PAD_LEFT);

// Simulate enable(): verify code THEN enable
assert_true($service->verifyTotp(1, $validEnableCode), 'valid TOTP code for pending setup');
$enableResult = $service->enable(1);
assert_true($service->isEnabled(1), '2FA IS enabled after valid OTP confirmation');
assert_false($service->hasPendingSetup(1), 'pending setup cleared after enable');
assert_true(count($enableResult['recoveryCodes']) > 0, 'enable() generates recovery codes');

// totp_enabled_at must be set
$enabledData = $tokenRepo->getTotpSecret(1);
assert_true(!empty($enabledData['totp_enabled_at']), 'totp_enabled_at is set after enable');
assert_true(empty($enabledData['totp_pending_at']), 'totp_pending_at is cleared after enable');

// ============= 18. DISABLE VIA RECOVERY CODE ============
// User can disable 2FA via recovery code (alternative to TOTP).
// validateRecoveryCode() only consumes the code; disable() is needed to actually disable.

$service->disable(1);
assert_false($service->isEnabled(1), 'clean state for recovery disable test');

// Generate and enable 2FA
$service->generateSecret(1);
$testSec = $service->generateSecret(1);
$testHex = hex2bin($decodeMethod->invoke($service, $testSec));
$testStep = (int) floor(time() / 30);
$testPack = pack('N*', $testStep);
$testHmac = hash_hmac('sha1', $testPack, $testHex, true);
$testOffset = ord($testHmac[19]) & 0x0F;
$testCodeNum = ((ord($testHmac[$testOffset]) & 0x7F) << 24)
           | ((ord($testHmac[$testOffset + 1]) & 0xFF) << 16)
           | ((ord($testHmac[$testOffset + 2]) & 0xFF) << 8)
           | (ord($testHmac[$testOffset + 3]) & 0xFF);
$testCode = str_pad((string) ($testCodeNum % 1000000), 6, '0', STR_PAD_LEFT);
assert_true($service->verifyTotp(1, $testCode), 'valid code for test setup');
$enableResult = $service->enable(1);
assert_true($service->isEnabled(1), '2FA enabled for recovery disable test');

// getRecoveryCodes was called by enable() — use one of them
$recoveryCodeValue = $enableResult['recoveryCodes'][0]['code'];
assert_not_null($recoveryCodeValue, 'recovery code available');

// validateRecoveryCode consumes the code (used for login, not disable)
$success = $service->validateRecoveryCode(1, $recoveryCodeValue);
assert_true($success, 'recovery code consumed');
assert_true($service->isEnabled(1), '2FA still enabled after recovery code validation (only consumes code)');

// disable() is needed to actually disable 2FA
$service->disable(1);
assert_false($service->isEnabled(1), '2FA disabled after disable() call');

// ============= 19. OPENING RENDERING 2FA TAB HAS NO DB ENABLE EFFECT ============
// Simulating the profile modal 2FA tab flow: loadStatus → check enabled → generate secret.

$service->disable(1);
assert_false($service->isEnabled(1), 'user clean before tab simulation');

// Simulate loadStatus: first check enabled
$enabled = $service->isEnabled(1);
assert_false($enabled, 'not enabled before tab interaction');
assert_false($service->hasPendingSetup(1), 'not pending before tab interaction');

// User opens tab → generateSecret (simulating /api/profile/2fa/generate)
$tabSecret = $service->generateSecret(1);
assert_not_null($tabSecret, 'secret generated for tab');

// Critical: enabled must still be false
assert_false($service->isEnabled(1), 'tab open does NOT enable 2FA');
assert_true($service->hasPendingSetup(1), 'tab open sets pending state');

// User skips setup → disable (simulating /api/profile/2fa/disable with no code)
$service->disable(1);
assert_false($service->isEnabled(1), 'not enabled after skip');
assert_false($service->hasPendingSetup(1), 'pending cleared after skip');

// ============= SUMMARY ============

// ============= 16. MISSING SCHEMA — 2FA GRACEFUL DEGRADATION ============
// Regression test: TwoFactorService::isEnabled() must return false when
// totp_secret/totp_enabled_at columns do NOT exist (migration 0051 not applied).

// Create a fresh DB WITHOUT 2FA columns
$pdoNo2fa = new PDO('sqlite::memory:');
$pdoNo2fa->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDBNo2fa implements DatabaseInterface
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

$dbNo2fa = new TestDBNo2fa($pdoNo2fa);

// Create users table WITHOUT totp_secret/totp_enabled_at columns
$dbNo2fa->execute("CREATE TABLE users (
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

$passwordHash = password_hash('testpass123', PASSWORD_DEFAULT);
$dbNo2fa->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (1, 'userno2fa', 'n2fa@example.com', 'No2fa User', ?, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [$passwordHash]
);

$repoNo2fa = new TwoFactorRepository($dbNo2fa);
$userRepoNo2fa = new App\Models\UserRepository($dbNo2fa);
$serviceNo2fa = new TwoFactorService($repoNo2fa, $userRepoNo2fa);

// isEnabled must NOT throw when columns are missing — must return false
assert_false($serviceNo2fa->isEnabled(1), 'isEnabled returns false when 2FA columns are missing');

// verifyTotp must NOT throw — must return false
assert_false($serviceNo2fa->verifyTotp(1, '123456'), 'verifyTotp returns false when 2FA columns are missing');

// generateSecret must NOT throw — must return null (user created but secret save silently fails)
$secretNo2fa = $serviceNo2fa->generateSecret(1);
// May return a secret string (generation succeeds) but setTotpSecret silently fails.
// The key test is that it does NOT throw.
assert_true($secretNo2fa === null || is_string($secretNo2fa), 'generateSecret does not throw when 2FA columns are missing');

// disable must NOT throw
$serviceNo2fa->disable(1); // should not throw

// AuthService login must NOT throw when 2FA columns are missing
$containerNo2fa = new Container();
$containerNo2fa->set('db', $dbNo2fa);
$containerNo2fa->set('two_factor', new TwoFactorService(new TwoFactorRepository($dbNo2fa), new App\Models\UserRepository($dbNo2fa)));

$fakeProviderNo2fa = new FakeProvider([
    ['id' => 1, 'display_name' => 'No2fa User', 'username' => 'userno2fa',
     'email' => 'n2fa@example.com', 'is_active' => 1,
     'password_hash' => $passwordHash,
     'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'],
]);

$authNo2fa = new AuthService($fakeProviderNo2fa, $config, null);
$authNo2fa->setContainer($containerNo2fa);

// Login must succeed for valid user even when 2FA schema is absent
$userNo2fa = $authNo2fa->login(['identity' => 'userno2fa', 'password' => 'testpass123']);
assert_not_null($userNo2fa, 'login succeeds when 2FA schema is missing');
assert_false($authNo2fa->hasTwoFactorEnabled(1), 'hasTwoFactorEnabled returns false when 2FA columns are missing');
assert_false($authNo2fa->hasPendingTwoFactor(), 'no pending 2FA when 2FA schema is missing');

// ============= SUMMARY ============
summary();
exit($__FAIL__ > 0 ? 1 : 0);
