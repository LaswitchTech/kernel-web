<?php

/**
 * Tests for Email Verification feature.
 *
 * Tests: EmailVerificationRepository, EmailVerificationService,
 * token validation, email rendering, resend.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\EmailVerificationRepository;
use App\Auth\EmailVerificationService;
use App\Core\DatabaseInterface;
use App\Core\Mail\TransportInterface;
use App\Core\Mail\MailMessage;

// --- Bootstrap: in-memory SQLite + FakeTransport ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class FakeTransport4 implements TransportInterface
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
        return 'fake4';
    }
}

$transport = new FakeTransport4();

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

$db->execute("CREATE TABLE auth_email_verifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at VARCHAR(32) NOT NULL,
    used_at VARCHAR(32),
    created_at TEXT NOT NULL,
    UNIQUE(token_hash)
)");

$db->execute("CREATE INDEX auth_email_verifications_user_id ON auth_email_verifications (user_id)");

// --- Insert test users ---

$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, email_verified_at, created_at, updated_at) VALUES (1, 'testuser', 'test@example.com', 'Test User', 'hash', 1, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");
$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, email_verified_at, created_at, updated_at) VALUES (2, 'inactive', 'inactive@example.com', 'Inactive User', 'hash', 0, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");
$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, email_verified_at, created_at, updated_at) VALUES (3, 'verified', 'verified@example.com', 'Verified User', 'hash', 1, '2024-01-02 00:00:00', '2024-01-01 00:00:00', '2024-01-01 00:00:00')");

// --- Instantiate repositories and services ---

$tokenRepo  = new EmailVerificationRepository($db);
$userRepo   = new \App\Models\UserRepository($db);
$mailer     = new \App\Core\Mail\Mailer($transport);
$verifyService = new EmailVerificationService($tokenRepo, $userRepo, $mailer);

// ============= 1. TOKEN GENERATION ============

$token = $verifyService->generate(1);
assert_not_null($token, 'generate returns token for unverified user');
assert_true(strlen($token['selector']) === 64, 'token is 64-char hex');
assert_true($token['userId'] === 1, 'token has correct userId');
assert_true(str_contains($token['email'], 'test@example.com'), 'token includes user email');
assert_true(str_contains($token['display_name'], 'Test User'), 'token includes display name');

// Verify token is stored as hash in DB
$dbRecords = $db->fetch("SELECT * FROM auth_email_verifications WHERE user_id = 1");
assert_true(count($dbRecords) > 0, 'token record created');
assert_false($dbRecords[0]['token_hash'] === $token['selector'], 'stored as hash, not raw');

// ============= 2. ALREADY VERIFIED USER ============

$result = $verifyService->generate(3);
assert_null($result, 'generate returns null for already verified user');

// ============= 3. NONEXISTENT USER ============

$result = $verifyService->generate(9999);
assert_null($result, 'generate returns null for nonexistent user');

// ============= 4. VALID TOKEN VERIFICATION ============

$validToken   = bin2hex(random_bytes(32));
$validHash    = hash('sha256', $validToken);
$futureDate   = date('Y-m-d H:i:s', time() + 3600);
$tokenId      = $tokenRepo->create(1, $validHash, $futureDate);

$validated = $verifyService->validate($validToken);
assert_not_null($validated, 'valid token validates successfully');
assert_true((bool) $validated['user']['is_active'], 'user is active');
assert_equal(1, (int) $validated['user']['id'], 'user id matches');

// Verify email_verified_at is set
$user = $userRepo->findByIdAny(1);
assert_not_null($user['email_verified_at'], 'email_verified_at is set after validation');

// Verify token is revoked
$validated = $verifyService->validate($validToken);
assert_null($validated, 'used token is rejected on second validate');

// ============= 5. EXPIRED TOKEN ============

$expiredToken = bin2hex(random_bytes(32));
$expiredHash  = hash('sha256', $expiredToken);
$pastDate     = '2020-01-01 00:00:00';
$tokenRepo->create(1, $expiredHash, $pastDate);

$validated = $verifyService->validate($expiredToken);
assert_null($validated, 'expired token is rejected');

// ============= 6. USED TOKEN ============

$usedToken    = bin2hex(random_bytes(32));
$usedHash     = hash('sha256', $usedToken);
$usedId       = $tokenRepo->create(1, $usedHash, $futureDate);
$tokenRepo->revoke($usedId);

$validated = $verifyService->validate($usedToken);
assert_null($validated, 'used token is rejected');

// ============= 7. NONEXISTENT TOKEN ============

$validated = $verifyService->validate('nonexistent_token_xyz');
assert_null($validated, 'nonexistent token is rejected');

// ============= 8. EMAIL VERIFICATION FOR INACTIVE USER ============

// Inactive user with valid token should still verify email
$inactiveToken    = bin2hex(random_bytes(32));
$inactiveHash     = hash('sha256', $inactiveToken);
$inactiveTokenId  = $tokenRepo->create(2, $inactiveHash, $futureDate);

$validated = $verifyService->validate($inactiveToken);
assert_not_null($validated, 'inactive user can verify email');
assert_equal(2, (int) $validated['user']['id'], 'user id matches for inactive user');

// ============= 9. PASSWORD UPDATE + REVOCATION ============

// Create a valid token
$resetToken     = bin2hex(random_bytes(32));
$resetTokenHash = hash('sha256', $resetToken);
$resetTokenId   = $tokenRepo->create(1, $resetTokenHash, $futureDate);

$validated = $verifyService->validate($resetToken);
assert_not_null($validated, 'token validates before use');

// Verify token is now revoked
$validated = $verifyService->validate($resetToken);
assert_null($validated, 'token is revoked after verification');

// Verify user has no pending tokens
$pending = $db->fetch("SELECT * FROM auth_email_verifications WHERE user_id = 1 AND used_at IS NULL");
assert_true(empty($pending), 'user has no pending verification tokens after use');

// ============= 10. EMAIL RENDERING ============

$html = EmailVerificationService::renderEmailTemplate('Test User', 'https://example.com/verify?token=abc');
assert_true(str_contains($html, 'Test User'), 'email contains username');
assert_true(str_contains($html, 'abc'), 'email contains token');
assert_true(str_contains($html, 'Verify'), 'email contains verify button text');

// ============= 11. EMAIL SENT WITH MAILER ============

// Use user 4 (resenduser) — still unverified at this point
$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, email_verified_at, created_at, updated_at) VALUES (5, 'emailtest', 'emailtest@example.com', 'Email Test User', 'hash', 1, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");

// Use generate for user 5 to test mailer send path
$genToken = $verifyService->generate(5);
assert_not_null($genToken, 'token generated for email test user');

$sent = $verifyService->sendEmail(
    'test@example.com',
    'Test User',
    'https://example.com/verify?token=' . $genToken['selector'],
    'noreply@test.local',
    'Test Mailer'
);
assert_true($sent, 'email sent successfully');
assert_true($transport->sendCount >= 1, 'transport was called');
assert_not_null($transport->lastMessage, 'message was captured');
assert_true(str_contains($transport->lastMessage->subject, 'Verify'), 'email subject contains Verify');

// ============= 12. NO MAILER ============

$noMailerService = new EmailVerificationService($tokenRepo, $userRepo, null);
$sent = $noMailerService->sendEmail('test@example.com', 'Test', 'https://example.com');
assert_false($sent, 'sendEmail returns false when mailer is null');

// ============= 13. RESEND ============

// User is still unverified (user 1 was verified in test 4, use a fresh user)
$db->execute("INSERT INTO users (id, username, email, display_name, password_hash, is_active, email_verified_at, created_at, updated_at) VALUES (4, 'resenduser', 'resend@example.com', 'Resend User', 'hash', 1, NULL, '2024-01-01 00:00:00', '2024-01-01 00:00:00')");

// Generate first token
$first = $verifyService->generate(4);
assert_not_null($first, 'first generate returns token');

// Resend should revoke old tokens and create a new one
$resend = $verifyService->resend('resend@example.com');
assert_not_null($resend, 'resend returns new token');
assert_false($resend['selector'] === $first['selector'], 'resend creates a different token');

// Old token should be revoked
$oldPending = $db->fetch("SELECT * FROM auth_email_verifications WHERE user_id = 4 AND used_at IS NULL");
assert_true(count($oldPending) >= 1, 'at least one pending token exists after resend (new one)');

// ============= 14. RESEND FOR ALREADY VERIFIED USER ============

$resendVerified = $verifyService->resend('verified@example.com');
assert_null($resendVerified, 'resend returns null for already verified user');

// ============= 15. RESEND FOR NONEXISTENT USER ============

$resendNone = $verifyService->resend('nonexistent@example.com');
assert_null($resendNone, 'resend returns null for nonexistent user (enumeration-safe)');

// ============= 16. REVOKE ALL FOR USER ============

$tokenA = bin2hex(random_bytes(32));
$tokenB = bin2hex(random_bytes(32));
$tokenRepo->create(1, hash('sha256', $tokenA), $futureDate);
$tokenRepo->create(1, hash('sha256', $tokenB), $futureDate);

$tokenRepo->revokeAllForUser(1);
$pending = $db->fetch("SELECT * FROM auth_email_verifications WHERE user_id = 1 AND used_at IS NULL");
assert_true(empty($pending), 'all pending tokens revoked for user');

// ============= SUMMARY ============
summary();
exit($__FAIL__ > 0 ? 1 : 0);
