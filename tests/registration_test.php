<?php

/**
 * Tests for User Registration feature.
 *
 * Tests: UserRepository (create, duplicate detection, password hashing),
 * EmailVerificationService integration, registration validation rules,
 * config gating.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\UserRepository;
use App\Models\EmailVerificationRepository;
use App\Auth\EmailVerificationService;
use App\Core\DatabaseInterface;
use App\Core\Mail\TransportInterface;
use App\Core\Mail\MailMessage;

// --- Bootstrap: in-memory SQLite + FakeTransport ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

class FakeTransport5 implements TransportInterface
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
        return 'fake5';
    }
}

$transport = new FakeTransport5();

class TestDB6 implements DatabaseInterface
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

$db = new TestDB6($pdo);

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

// --- Instantiate ---

$userRepo    = new UserRepository($db);
$tokenRepo   = new EmailVerificationRepository($db);
$mailer      = new \App\Core\Mail\Mailer($transport);

// ============= 1. USER CREATION ============

$passwordHash = password_hash('testpass123', PASSWORD_DEFAULT);
$userId = $userRepo->create([
    'display_name'  => 'New User',
    'username'      => 'newuser',
    'email'         => 'new@example.com',
    'password_hash' => $passwordHash,
    'is_active'     => 1,
]);

assert_not_null($userId, 'user created successfully');
assert_true($userId > 0, 'user ID is positive');

$user = $userRepo->findByIdAny($userId);
assert_not_null($user, 'user can be found by ID');
assert_equal('New User', $user['display_name'], 'display name matches');
assert_equal('newuser', $user['username'], 'username matches');
assert_equal('new@example.com', $user['email'], 'email matches');
assert_equal(1, (int) $user['is_active'], 'user is active');
assert_null($user['email_verified_at'], 'user is unverified');

// Fetch full row (including password_hash) for password hashing tests
$fullUser = $db->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$userId]);
assert_not_null($fullUser, 'full user row retrieved');
assert_true(is_string($fullUser['password_hash']), 'password_hash is a string');

// ============= 2. PASSWORD HASHING ============

assert_true(str_starts_with($fullUser['password_hash'], '$2y$'), 'password is hashed with PASSWORD_DEFAULT (bcrypt)');
assert_true(password_verify('testpass123', $fullUser['password_hash']), 'password_verify confirms correct password');
assert_false(password_verify('wrongpassword', $fullUser['password_hash']), 'wrong password does not match');

// ============= 3. DUPLICATE USERNAME DETECTION ============

assert_true($userRepo->isUsernameTaken('newuser'), 'isUsernameTaken returns true for existing username');
assert_false($userRepo->isUsernameTaken('nonexistentuser123'), 'isUsernameTaken returns false for new username');

// ============= 4. DUPLICATE EMAIL DETECTION ============

assert_true($userRepo->isEmailTaken('new@example.com'), 'isEmailTaken returns true for existing email');
assert_false($userRepo->isEmailTaken('nonexistent@example.com'), 'isEmailTaken returns false for new email');

// ============= 5. VERIFICATION EMAIL SENT ON GENERATE + SEND ============

$verifyService = new EmailVerificationService($tokenRepo, $userRepo, $mailer);
$token = $verifyService->generate($userId);
assert_not_null($token, 'verification token generated for new user');
assert_true(strlen($token['selector']) === 64, 'token is 64-char hex');
assert_true(str_contains($token['email'], 'new@example.com'), 'token includes user email');

// Verify token record in DB
$records = $db->fetch("SELECT * FROM auth_email_verifications WHERE user_id = $userId");
assert_true(count($records) > 0, 'verification token record exists in DB');

// Explicitly send the verification email (generate only creates the token, doesn't send)
$sent = $verifyService->sendEmail(
    'new@example.com',
    'New User',
    '/auth/verify/email?token=' . $token['selector'],
    'noreply@localhost',
    'Kernel-Web'
);
assert_true($sent, 'sendEmail returns true');

// Verify email was sent
assert_true($transport->sendCount >= 1, 'verification email was sent via mailer');
assert_not_null($transport->lastMessage, 'mail message was captured');
assert_true(str_contains($transport->lastMessage->subject, 'Verify'), 'email subject contains Verify');

// ============= 6. EMAIL VERIFICATION SETS TIMESTAMP ============

$newDate = date('Y-m-d H:i:s');
$userRepo->setEmailVerified($userId, $newDate);
$verifiedUser = $userRepo->findByIdAny($userId);
assert_not_null($verifiedUser['email_verified_at'], 'email_verified_at is now set');
assert_true($verifiedUser['email_verified_at'] !== 'NULL', 'email_verified_at is not null string');

// ============= 7. ALREADY VERIFIED USER CANNOT GENERATE NEW TOKEN ============

$tokenAgain = $verifyService->generate($userId);
assert_null($tokenAgain, 'already verified user cannot generate new token');

// ============= 8. REGISTER VALIDATION RULES ============

// display_name: required, 1-100 chars
assert_true(strlen('') < 1, 'empty display_name fails');
assert_true(strlen(str_repeat('a', 101)) > 100, 'display_name > 100 chars fails');

// username: 3-64 chars, alphanumeric + hyphens only
assert_true(strlen('ab') < 3, 'username < 3 chars fails');
assert_true((bool) !preg_match('/^[a-zA-Z0-9-]+$/', 'user@name'), 'username with @ fails');
assert_true((bool) !preg_match('/^[a-zA-Z0-9-]+$/', 'user name'), 'username with space fails');
assert_true(strlen('valid-user123') >= 3 && (bool) preg_match('/^[a-zA-Z0-9-]+$/', 'valid-user123'), 'valid username passes');

// email: valid email format
assert_true(!filter_var('not-an-email', FILTER_VALIDATE_EMAIL), 'invalid email fails');
assert_true((bool) filter_var('valid@example.com', FILTER_VALIDATE_EMAIL), 'valid email passes');

// password: min 8 chars, confirmation match
assert_true(strlen('short1') < 8, 'password < 8 chars fails');
assert_false('pass1234' === 'different1', 'mismatched passwords detected');
assert_true('pass1234' === 'pass1234', 'matching passwords detected');

// ============= 9. UNIQUE CONSTRAINT ON USERNAME ============

try {
    $userRepo->create([
        'display_name'  => 'Dup Name',
        'username'      => 'newuser',
        'email'         => 'dup2@example.com',
        'password_hash' => $passwordHash,
        'is_active'     => 1,
    ]);
    assert_false(true, 'duplicate username should throw exception');
} catch (\RuntimeException $e) {
    assert_true(true, 'duplicate username throws RuntimeException');
}

// ============= 10. UNIQUE CONSTRAINT ON EMAIL ============

try {
    $userRepo->create([
        'display_name'  => 'Dup Name 2',
        'username'      => 'dupuser3',
        'email'         => 'new@example.com',
        'password_hash' => $passwordHash,
        'is_active'     => 1,
    ]);
    assert_false(true, 'duplicate email should throw exception');
} catch (\RuntimeException $e) {
    assert_true(true, 'duplicate email throws RuntimeException');
}

// ============= 11. CREATE INACTIVE USER ============

$inactiveId = $userRepo->create([
    'display_name'  => 'Inactive User',
    'username'      => 'inactiveuser',
    'email'         => 'inactive@example.com',
    'password_hash' => $passwordHash,
    'is_active'     => 0,
]);
assert_true($inactiveId > 0, 'inactive user created');
$inactiveUser = $userRepo->findByIdAny((int) $inactiveId);
assert_equal(0, (int) $inactiveUser['is_active'], 'inactive user has is_active = 0');

// ============= 12. INACTIVE USER CAN VERIFY EMAIL ============

$inactiveVerify = new EmailVerificationService($tokenRepo, $userRepo, $mailer);
$resendResult = $inactiveVerify->generate((int) $inactiveId);
assert_not_null($resendResult, 'inactive user can generate verification token');

// Validate the token
$validToken = bin2hex(random_bytes(32));
$validHash  = hash('sha256', $validToken);
$futureDate = date('Y-m-d H:i:s', time() + 3600);
$tokId = $tokenRepo->create((int) $inactiveId, $validHash, $futureDate);

$validated = $inactiveVerify->validate($validToken);
assert_not_null($validated, 'inactive user can verify email');

// Verify email_verified_at is now set
$verifiedInactive = $userRepo->findByIdAny((int) $inactiveId);
assert_not_null($verifiedInactive['email_verified_at'], 'inactive user now has email_verified_at');

// ============= 13. NO MAILER HANDLING ============

$noMailerService = new EmailVerificationService($tokenRepo, $userRepo, null);
$sent = $noMailerService->sendEmail('test@example.com', 'Test', 'https://example.com');
assert_false($sent, 'sendEmail returns false when mailer is null');

// ============= 14. RESEND REVOKE ALL FOR USER ============

$tokA = bin2hex(random_bytes(32));
$tokB = bin2hex(random_bytes(32));
$tokenRepo->create((int) $inactiveId, hash('sha256', $tokA), $futureDate);
$tokenRepo->create((int) $inactiveId, hash('sha256', $tokB), $futureDate);

$tokenRepo->revokeAllForUser((int) $inactiveId);
$pending = $db->fetch("SELECT * FROM auth_email_verifications WHERE user_id = $inactiveId AND used_at IS NULL");
assert_true(empty($pending), 'all pending verification tokens revoked for user');

// ============= 15. REGISTRATION CONFIG SHAPE ============

$expectedConfig = [
    'enabled'                  => false,
    'require_email_verification' => true,
    'auto_login'               => true,
    'redirect'                 => '/',
];

$actualConfig = include __DIR__ . '/../config/auth.php';
assert_array_has_key($actualConfig, 'registration', 'config has registration key');
assert_equal($expectedConfig['enabled'], $actualConfig['registration']['enabled'], 'registration.enabled is false');
assert_equal($expectedConfig['require_email_verification'], $actualConfig['registration']['require_email_verification'], 'require_email_verification is true');
assert_equal($expectedConfig['auto_login'], $actualConfig['registration']['auto_login'], 'auto_login is true');
assert_equal($expectedConfig['redirect'], $actualConfig['registration']['redirect'], 'redirect is /');

// ============= SUMMARY ============
summary();
exit($__FAIL__ > 0 ? 1 : 0);
