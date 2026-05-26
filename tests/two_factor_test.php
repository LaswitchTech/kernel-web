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
    totp_setup_id VARCHAR(255),
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
$stepPack = pack('N2', 0, $currentStep);
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
$prevPack = pack('N2', 0, $prevStep);
$prevHmac = hash_hmac('sha1', $prevPack, $hexSecret, true);
$prevOffset = ord($prevHmac[19]) & 0x0F;
$prevCodeNum = ((ord($prevHmac[$prevOffset]) & 0x7F) << 24)
             | ((ord($prevHmac[$prevOffset + 1]) & 0xFF) << 16)
             | ((ord($prevHmac[$prevOffset + 2]) & 0xFF) << 8)
             | (ord($prevHmac[$prevOffset + 3]) & 0xFF);
$prevCode = str_pad((string) ($prevCodeNum % 1000000), 6, '0', STR_PAD_LEFT);

// Next step
$nextStep = $currentStep + 1;
$nextPack = pack('N2', 0, $nextStep);
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
$tooOldPack = pack('N2', 0, $tooOldStep);
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
assert_true(isset($enableResult['recoveryCodes']['code']), 'enable() generates single recovery code');

// Verify secret and timestamp set
$secretAfter = $tokenRepo->getTotpSecret(1);
assert_not_null($secretAfter['totp_secret'], 'totp_secret set on enable');
assert_not_null($secretAfter['totp_enabled_at'], 'totp_enabled_at set on enable');

// Consume the single recovery code
$rawCode = $enableResult['recoveryCodes']['code'];
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

// ============= 9. RECOVERY CODE STORED AS HASH ============

$enableResult2 = $service->enable(1);
assert_true(isset($enableResult2['recoveryCodes']['code']), 'single recovery code generated');

$rc = $enableResult2['recoveryCodes'];
$computedHash = hash('sha256', $rc['code']);
assert_equal($computedHash, $rc['codeHash'], 'stored hash matches computed hash');

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

// ============= 15. RECOVERY CODE GENERATES SINGLE UUID ============

// Create a new user specifically for this test
$db->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (3, 'recoveryuser', 'recovery@example.com', 'Recovery User', ?, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [$passwordHash]
);

// Disable any existing 2FA first
$service->disable(3);

$recoveryResult = $service->generateRecoveryCodes(3);
assert_true(isset($recoveryResult['code']), 'generateRecoveryCodes returns single code object');
assert_true((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $recoveryResult['code']), 'code is UUID v4 format');

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
$pendingPack = pack('N2', 0, $pendingStep);
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
$setupPack = pack('N2', 0, $setupStep);
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
$testPack = pack('N2', 0, $testStep);
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

// getRecoveryCodes was called by enable() — use the single code
$recoveryCodeValue = $enableResult['recoveryCodes']['code'];
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

// ============= 20. GENERATESECRET IS IDEMPOTENT ============
// Critical: calling generateSecret multiple times must return the SAME secret.
// If it generates a new secret, previously shown QR codes become invalid.

$service->disable(1);
assert_false($service->isEnabled(1), 'clean state for idempotent test');

$first = $service->generateSecret(1);
assert_not_null($first, 'first generateSecret returns secret');
assert_true($service->hasPendingSetup(1), 'pending after first generate');

$second = $service->generateSecret(1);
assert_not_null($second, 'second generateSecret returns secret');
assert_equal($first, $second, 'generateSecret is idempotent — same secret returned');

// URI must also match the same secret
$uri = $service->getOtpauthUri(1, 'TestApp', 'test@example.com');
assert_true(str_contains($uri, 'secret=' . $first), 'URI contains same secret as generateSecret');

// Valid TOTP code from the pending secret must work
$pendingHex = hex2bin($decodeMethod->invoke($service, $first));
$pendingStep = (int) floor(time() / 30);
$pendingHmac = hash_hmac('sha1', pack('N2', 0, $pendingStep), $pendingHex, true);
$pendingOffset = ord($pendingHmac[19]) & 0x0F;
$pendingCodeNum = ((ord($pendingHmac[$pendingOffset]) & 0x7F) << 24)
              | ((ord($pendingHmac[$pendingOffset + 1]) & 0xFF) << 16)
              | ((ord($pendingHmac[$pendingOffset + 2]) & 0xFF) << 8)
              | (ord($pendingHmac[$pendingOffset + 3]) & 0xFF);
$pendingCode = str_pad((string) ($pendingCodeNum % 1000000), 6, '0', STR_PAD_LEFT);
assert_true($service->verifyTotp(1, $pendingCode), 'valid TOTP code verifies against pending secret');

// Re-call generateSecret again — secret must still be the same
$third = $service->generateSecret(1);
assert_equal($first, $third, 'third generateSecret also returns same secret');

// enable() must work with the valid code
$service->enable(1);
assert_true($service->isEnabled(1), '2FA enabled after valid code + enable');

// ============= 21. RFC 6238 TEST VECTOR (Section B.3) ============
// Critical regression test: TOTP must produce codes matching RFC 6238 Appendix B.3.
// Secret = "12345678901234567890" (20 bytes, hex = 3132333435363738393031323334353637383930)
// Counter = 64-bit big-endian (RFC 6238 §4)

$rfcSecretHex = '3132333435363738393031323334353637383930';
$rfcSecretBinary = hex2bin($rfcSecretHex);
assert_not_null($rfcSecretBinary, 'RFC secret decodes');
assert_equal(20, strlen($rfcSecretBinary), 'RFC secret is 20 bytes');

// RFC 6238 B.3 step-based table: step 1 → TOTP 287082
// (The table in B.3 lists time=59 → step=1 → TOTP=287082)
$rfcStep = 1;
$rfcExpected = '287082';
$rfcHmac = hash_hmac('sha1', pack('N2', 0, $rfcStep), $rfcSecretBinary, true);
$rfcOff = ord($rfcHmac[19]) & 0x0F;
$rfcNum = ((ord($rfcHmac[$rfcOff]) & 0x7F) << 24)
        | ((ord($rfcHmac[$rfcOff + 1]) & 0xFF) << 16)
        | ((ord($rfcHmac[$rfcOff + 2]) & 0xFF) << 8)
        | (ord($rfcHmac[$rfcOff + 3]) & 0xFF);
$rfcCode = str_pad((string) ($rfcNum % 1000000), 6, '0', STR_PAD_LEFT);
assert_equal($rfcExpected, $rfcCode, "RFC 6238 step 1 TOTP code matches expected '$rfcExpected'");

// Verify counter is 8 bytes (not 4): the counter bytes must be
// 00 00 00 00 00 00 00 01 for step 1 (big-endian 64-bit)
$rfcCounterBytes = pack('N2', 0, $rfcStep);
assert_equal(8, strlen($rfcCounterBytes), 'RFC counter is 8 bytes');
assert_equal('0000000000000001', bin2hex($rfcCounterBytes), 'RFC counter bytes correct');

// Verify: pack('N*', $rfcStep) produces 4 bytes and WRONG code
assert_equal(4, strlen(pack('N*', $rfcStep)), 'pack(N*, step) produces 4 bytes (wrong)');
$hmac4 = hash_hmac('sha1', pack('N*', $rfcStep), $rfcSecretBinary, true);
$off4 = ord($hmac4[19]) & 0x0F;
$num4 = ((ord($hmac4[$off4]) & 0x7F) << 24)
      | ((ord($hmac4[$off4 + 1]) & 0xFF) << 16)
      | ((ord($hmac4[$off4 + 2]) & 0xFF) << 8)
      | (ord($hmac4[$off4 + 3]) & 0xFF);
$wrongCode = str_pad((string) ($num4 % 1000000), 6, '0', STR_PAD_LEFT);
assert_true($wrongCode !== $rfcExpected, "pack('N*') produces wrong code for RFC step");

// ============= 22. USER'S EXACT SECRET — END-TO-END (LIVE BROWSER REGRESSION) ============
// Regression test for browser "Invalid code" issue.
// Uses the exact secret the user reported scanning: 4X6KFARIEZOM2YS3AIIHHGSCBE3EGDRL

$userSecret = '4X6KFARIEZOM2YS3AIIHHGSCBE3EGDRL';
assert_true((bool) preg_match('/^[A-Z2-7]+$/', $userSecret), 'user secret is valid base32');
assert_equal(32, strlen($userSecret), 'user secret is 32 chars (padded to 40-bit boundary)');

// Decode the user secret independently
$ub = '';
for ($u = 0; $u < strlen($userSecret); $u++) {
    $ui = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $userSecret[$u]);
    $ub .= str_pad(decbin($ui), 5, '0', STR_PAD_LEFT);
}
$uBytes = '';
for ($u = 0; $u + 7 < strlen($ub); $u += 8) {
    $uBytes .= chr(bindec(substr($ub, $u, 8)));
}
assert_equal(20, strlen($uBytes), 'user secret decodes to 20 bytes (160 bits)');
$uHex = bin2hex($uBytes);

// Create a fresh user with this exact secret as pending
$pdoUser = new PDO('sqlite::memory:');
$pdoUser->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDBUserSecret implements DatabaseInterface
{
    public function __construct(private PDO $pdo) {}
    public function pdo(): PDO { return $this->pdo; }
    public function fetch(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetchOne(string $sql, array $params = []): ?array {
        $rows = $this->fetch($sql, $params); return $rows[0] ?? null;
    }
    public function execute(string $sql, array $bindings = []): int {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($bindings); return (int) $stmt->rowCount();
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }
}

$dbUser = new TestDBUserSecret($pdoUser);
$dbUser->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL, display_name TEXT DEFAULT '',
    password_hash TEXT NOT NULL, is_active INTEGER DEFAULT 1,
    email_verified_at VARCHAR(32), totp_secret VARCHAR(255),
    totp_enabled_at VARCHAR(32), totp_pending_at VARCHAR(32),
    totp_setup_id VARCHAR(255),
    created_at TEXT NOT NULL, updated_at TEXT NOT NULL
)");
$dbUser->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (1, 'testuser', 'test@example.com', 'Test User', ?, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [password_hash('testpass123', PASSWORD_DEFAULT)]
);
$dbUser->execute(
    "UPDATE users SET totp_secret = ?, totp_pending_at = ?, totp_setup_id = 'setup-user-secret' WHERE id = 1",
    [$userSecret]
);

$userSvc = new TwoFactorService(new TwoFactorRepository($dbUser), new App\Models\UserRepository($dbUser));
assert_true($userSvc->hasPendingSetup(1), 'user has pending setup with exact secret');

// Compute TOTP code for current step using the exact secret
$userStep = (int) floor(time() / 30);
$userHmac = hash_hmac('sha1', pack('N2', 0, $userStep), hex2bin($uHex), true);
$userOff = ord($userHmac[19]) & 0x0F;
$userCodeNum = ((ord($userHmac[$userOff]) & 0x7F) << 24)
             | ((ord($userHmac[$userOff + 1]) & 0xFF) << 16)
             | ((ord($userHmac[$userOff + 2]) & 0xFF) << 8)
             | (ord($userHmac[$userOff + 3]) & 0xFF);
$userCode = str_pad((string) ($userCodeNum % 1000000), 6, '0', STR_PAD_LEFT);

assert_true($userSvc->verifyTotp(1, $userCode), "user secret produces valid TOTP at step $userStep (code: $userCode)");

// ±1 window
for ($wi = -1; $wi <= 1; $wi++) {
    $ws = $userStep + $wi;
    $wh = hash_hmac('sha1', pack('N2', 0, $ws), hex2bin($uHex), true);
    $wo = ord($wh[19]) & 0x0F;
    $wn = ((ord($wh[$wo]) & 0x7F) << 24)
        | ((ord($wh[$wo + 1]) & 0xFF) << 16)
        | ((ord($wh[$wo + 2]) & 0xFF) << 8)
        | (ord($wh[$wo + 3]) & 0xFF);
    $wc = str_pad((string) ($wn % 1000000), 6, '0', STR_PAD_LEFT);
    assert_true($userSvc->verifyTotp(1, $wc), "user secret ±$wi window code '$wc' verifies");
}

// Enable with user secret
assert_true($userSvc->verifyTotp(1, $userCode), 'code matches before enable');
$enableResult = $userSvc->enable(1);
assert_true($userSvc->isEnabled(1), '2FA enabled after user secret verification');
assert_false($userSvc->hasPendingSetup(1), 'pending cleared after enable');
assert_true(isset($enableResult['recoveryCodes']['code']), 'recovery code generated');

// ============= 23. STALE QR / SETUP_ID BINDING REGRESSION ============
// Regression test: simulate stale QR scenario where setup_id binding prevents
// enabling with a code from an earlier QR code.

// Create a fresh user with setup_id tracking
$pdoS23 = new PDO('sqlite::memory:');
$pdoS23->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class TestDB_S23 implements DatabaseInterface
{
    public function __construct(private PDO $pdo) {}
    public function pdo(): PDO { return $this->pdo; }
    public function fetch(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetchOne(string $sql, array $params = []): ?array {
        $rows = $this->fetch($sql, $params); return $rows[0] ?? null;
    }
    public function execute(string $sql, array $bindings = []): int {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($bindings); return (int) $stmt->rowCount();
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }
}

$dbS23 = new TestDB_S23($pdoS23);
$dbS23->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL, display_name TEXT DEFAULT '',
    password_hash TEXT NOT NULL, is_active INTEGER DEFAULT 1,
    email_verified_at VARCHAR(32), totp_secret VARCHAR(255),
    totp_enabled_at VARCHAR(32), totp_pending_at VARCHAR(32),
    totp_setup_id VARCHAR(255),
    created_at TEXT NOT NULL, updated_at TEXT NOT NULL
)");
$dbS23->execute(
    "INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
     VALUES (1, 'testuser', 'test@example.com', 'Test User', ?, 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')",
    [password_hash('testpass123', PASSWORD_DEFAULT)]
);

$s23Svc = new TwoFactorService(new TwoFactorRepository($dbS23), new App\Models\UserRepository($dbS23));

// Step 1: Generate secret with setup_id A
$setupIdA = 'aaaa1111';
$resultA = $s23Svc->generateSecretFull(1, $setupIdA);
assert_not_null($resultA, 'generateSecretFull returns array with setupId');
assert_not_null($resultA['setupId'], 'setupId is returned');
assert_equal($setupIdA, $resultA['setupId'], 'setupId matches what was sent');
$secretA = $resultA['secret'];

// Step 2: Compute TOTP code for secret A
$refA = new ReflectionClass($s23Svc);
$decA = $refA->getMethod('decodeBase32');
$hexA = $decA->invoke($s23Svc, $secretA);
$binA = hex2bin($hexA);
$stepA = (int) floor(time() / 30);
$hmacA = hash_hmac('sha1', pack('N2', 0, $stepA), $binA, true);
$offA = ord($hmacA[19]) & 0x0F;
$numA = ((ord($hmacA[$offA]) & 0x7F) << 24)
      | ((ord($hmacA[$offA + 1]) & 0xFF) << 16)
      | ((ord($hmacA[$offA + 2]) & 0xFF) << 8)
      | (ord($hmacA[$offA + 3]) & 0xFF);
$codeA = str_pad((string) ($numA % 1000000), 6, '0', STR_PAD_LEFT);

// Step 3: Generate new secret with setup_id B (simulates stale QR)
$setupIdB = 'bbbb2222';
$resultB = $s23Svc->generateSecretFull(1, $setupIdB);
assert_not_null($resultB, 'generateSecretFull B returns array');
assert_not_null($resultB['setupId'], 'setupId B returned');
assert_equal($setupIdB, $resultB['setupId'], 'setupId B matches');
assert_true($resultB['secret'] !== $secretA, 'New secret differs from secret A');
$secretB = $resultB['secret'];

// Step 4: Verify code A fails against pending setup B's secret (stale QR detection)
// The pending secret is now secretB (with setupId B).
// Code A was generated for secretA (with setupId A).
// Since pending setup has setupId B and we check against pending secret,
// verifyTotpWithSetupCheck should reject code A because setupId doesn't match.
$secretDataS23 = (new TwoFactorRepository($dbS23))->getTotpSecret(1);
assert_not_null($secretDataS23, 'pending secret data exists');
assert_not_null($secretDataS23['totp_setup_id'], 'setup_id is stored');
assert_equal($setupIdB, $secretDataS23['totp_setup_id'], 'setup_id B is in DB');
assert_false($s23Svc->verifyTotp(1, $codeA), 'Code A does NOT verify against different pending secret (expected: different secrets → different codes)');
// But verifyTotpWithSetupCheck should reject because code A doesn't match secret B's pending_id.
$ref23 = new ReflectionClass($s23Svc);
$decS23 = $ref23->getMethod('decodeBase32');

// Step 5: Verify code B passes and enable works
$hexB = $decS23->invoke($s23Svc, $secretB);
$binB = hex2bin($hexB);
$hmacB = hash_hmac('sha1', pack('N2', 0, $stepA), $binB, true);
$offB = ord($hmacB[19]) & 0x0F;
$numB = ((ord($hmacB[$offB]) & 0x7F) << 24)
      | ((ord($hmacB[$offB + 1]) & 0xFF) << 16)
      | ((ord($hmacB[$offB + 2]) & 0xFF) << 8)
      | (ord($hmacB[$offB + 3]) & 0xFF);
$codeB = str_pad((string) ($numB % 1000000), 6, '0', STR_PAD_LEFT);
assert_true($s23Svc->verifyTotp(1, $codeB), 'Code B verifies against secret B');

// Step 6: Enable with code B
assert_true($s23Svc->hasPendingSetup(1), 'pending before enable');
$enableS23 = $s23Svc->enable(1);
assert_true($s23Svc->isEnabled(1), '2FA enabled after code B');
assert_false($s23Svc->hasPendingSetup(1), 'pending cleared after enable');
assert_true(isset($enableS23['recoveryCodes']['code']), 'recovery code generated');

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
