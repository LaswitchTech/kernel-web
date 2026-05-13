<?php

/**
 * Tests for Forgot Password (password reset) flow.
 *
 * Tests: PasswordResetRepository, PasswordResetService,
 * token validation, email rendering.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\PasswordResetRepository;
use App\Auth\PasswordResetService;
use App\Core\DatabaseInterface;
use App\Core\Mail\TransportInterface;
use App\Core\Mail\MailMessage;
use App\Core\Mail\MailerException;

// --- Bootstrap: in-memory SQLite + FakeTransport ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class FakeTransport implements TransportInterface
{
    public ?MailMessage $lastMessage = null;
    public int $sendCount = 0;

    public function send(MailMessage $message): bool
    {
        $this->lastMessage = $message;
        $this->sendCount++;
        return true;
    }

    public function identifier(): string
    {
        return 'fake';
    }
}

$transport = new FakeTransport();

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

// --- Create tables ---

$db->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    display_name TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");

$db->execute("CREATE TABLE auth_password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at VARCHAR(32) NOT NULL,
    used_at VARCHAR(32),
    created_at TEXT NOT NULL,
    UNIQUE(token_hash)
)");

$db->execute("CREATE INDEX auth_password_resets_user_id ON auth_password_resets (user_id)");

// --- Insert test users ---

$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (1, 'testuser', 'test@example.com', 'Test User', 'hash', 1, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");
$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (2, 'inactive', 'inactive@example.com', 'Inactive User', 'hash', 0, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");

// --- Instantiate repositories and services ---

$tokenRepo     = new PasswordResetRepository($db);
$userRepo      = new \App\Models\UserRepository($db);
$mailer        = new \App\Core\Mail\Mailer($transport);
$resetService  = new PasswordResetService($tokenRepo, $userRepo, $mailer);

// ============= 1. TOKEN GENERATION ============

$token = $resetService->initiate('test@example.com');
assert_not_null($token, 'initiate returns token for active user');
assert_true(strlen($token['selector']) === 64, 'token is 64-char hex');
assert_true($token['userId'] === 1, 'token has correct userId');
assert_true(str_contains($token['email'], 'test@example.com'), 'token includes user email');
assert_true(str_contains($token['display_name'], 'Test User'), 'token includes display name');

// Verify token is stored as hash in DB
$dbRecords = $db->fetch("SELECT * FROM auth_password_resets WHERE user_id = 1");
assert_true(count($dbRecords) > 0, 'token record created');
assert_false($dbRecords[0]['token_hash'] === $token['selector'], 'stored as hash, not raw');

// ============= 2. NO USER (enumeration-safe) ============

$result = $resetService->initiate('nonexistent@example.com');
assert_null($result, 'initiate returns null for nonexistent email');

// ============= 3. INACTIVE USER (cannot initiate) ============

$result = $resetService->initiate('inactive@example.com');
assert_null($result, 'initiate returns null for inactive user');

// ============= 4. VALID TOKEN VERIFICATION ============

// Create a valid token
$validToken = bin2hex(random_bytes(32));
$validHash  = hash('sha256', $validToken);
$futureDate = date('Y-m-d H:i:s', time() + 3600);
$tokenRepo->create(1, $validHash, $futureDate);

$validated = $resetService->validate($validToken);
assert_not_null($validated, 'valid token validates successfully');
assert_true((bool) $validated['user']['is_active'], 'user is active');
assert_true($validated['user']['id'] === 1, 'user id matches');

// ============= 5. EXPIRED TOKEN ============

$expiredToken = bin2hex(random_bytes(32));
$expiredHash  = hash('sha256', $expiredToken);
$pastDate     = '2020-01-01 00:00:00';
$tokenRepo->create(1, $expiredHash, $pastDate);

$validated = $resetService->validate($expiredToken);
assert_null($validated, 'expired token is rejected');

// ============= 6. USED TOKEN ============

$usedToken     = bin2hex(random_bytes(32));
$usedHash      = hash('sha256', $usedToken);
$usedId        = $tokenRepo->create(1, $usedHash, $futureDate);
$tokenRepo->revoke($usedId);

$validated = $resetService->validate($usedToken);
assert_null($validated, 'used token is rejected');

// ============= 7. INACTIVE USER WITH VALID TOKEN ============

$inactiveToken  = bin2hex(random_bytes(32));
$inactiveHash   = hash('sha256', $inactiveToken);
$inactiveTokenId = $tokenRepo->create(2, $inactiveHash, $futureDate);

$validated = $resetService->validate($inactiveToken);
assert_null($validated, 'token for inactive user is rejected');

// ============= 8. INVALID TOKEN ============

$validated = $resetService->validate('nonexistent_token_xyz');
assert_null($validated, 'nonexistent token is rejected');

// ============= 9. PASSWORD UPDATE + REVOCATION ============

// Create a valid token
$resetToken    = bin2hex(random_bytes(32));
$resetTokenHash = hash('sha256', $resetToken);
$resetTokenId  = $tokenRepo->create(1, $resetTokenHash, $futureDate);

$validated = $resetService->validate($resetToken);
assert_not_null($validated, 'token validates before use');

// Update password
$resetService->completeReset(
    $validated['user']['id'],
    password_hash('newpassword123', PASSWORD_DEFAULT),
    $validated['token_id']
);

// Verify token is now revoked
$validated = $resetService->validate($resetToken);
assert_null($validated, 'token is revoked after password update');

// Verify token is revoked and user has no pending tokens
$pending = $db->fetch("SELECT * FROM auth_password_resets WHERE user_id = 1 AND used_at IS NULL");
assert_true(empty($pending), 'user has no pending reset tokens after use');

// ============= 10. EMAIL RENDERING ============

$html = PasswordResetService::renderEmailTemplate('Test User', 'https://example.com/reset?token=abc', 60);
assert_true(str_contains($html, 'Test User'), 'email contains username');
assert_true(str_contains($html, 'abc'), 'email contains token');
assert_true(str_contains($html, '60'), 'email contains expiry');

// ============= 11. EMAIL SENT WITH MAILER ============

$token = $resetService->initiate('test@example.com');
assert_not_null($token, 'token generated');

$sent = $resetService->sendEmail(
    'test@example.com',
    'Test User',
    'https://example.com/reset?token=' . $token['selector'],
    'noreply@test.local',
    'Test Mailer'
);
assert_true($sent, 'email sent successfully');
assert_true($transport->sendCount >= 1, 'transport was called');
assert_not_null($transport->lastMessage, 'message was captured');
assert_true(str_contains($transport->lastMessage->subject, 'Reset'), 'email subject contains Reset');

// ============= 12. NO MAILER ============

$noMailerService = new PasswordResetService($tokenRepo, $userRepo, null);
$sent = $noMailerService->sendEmail('test@example.com', 'Test', 'abc', 'https://example.com');
assert_false($sent, 'sendEmail returns false when mailer is null');

// ============= 13. REVOKE ALL FOR USER ============

$tokenA = bin2hex(random_bytes(32));
$tokenB = bin2hex(random_bytes(32));
$tokenRepo->create(1, hash('sha256', $tokenA), $futureDate);
$tokenRepo->create(1, hash('sha256', $tokenB), $futureDate);

$tokenRepo->revokeAllForUser(1);
$pending = $db->fetch("SELECT * FROM auth_password_resets WHERE user_id = 1 AND used_at IS NULL");
assert_true(empty($pending), 'all pending tokens revoked for user');

// ============= SUMMARY ============
summary();
exit($__FAIL__ > 0 ? 1 : 0);
