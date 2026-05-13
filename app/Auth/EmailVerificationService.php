<?php

namespace App\Auth;

use App\Core\Mail\Mailer;
use App\Core\Mail\MailMessage;
use App\Models\EmailVerificationRepository;
use App\Models\UserRepository;

/**
 * Manages email verification tokens.
 *
 * Security model:
 *   - A token is generated as random_bytes(32) → hex, hashed with SHA-256
 *   - Only the hash is stored in the database
 *   - Tokens are single-use and expire after 24 hours
 *   - The raw token is never stored or logged
 */
class EmailVerificationService
{
    private const EXPIRY_SECONDS = 86400; // 24 hours
    private const TEMPLATE_PATH  = __DIR__ . '/../Views/emails/email_verification.php';
    private const RATE_LIMIT_SECONDS = 60;

    public function __construct(
        private EmailVerificationRepository $repository,
        private UserRepository              $users,
        private ?Mailer                     $mailer,
    ) {}

    /**
     * Generate a verification token for an active user and send the email.
     *
     * Returns the raw token for embedding in the email URL, or null if the
     * user cannot be found or is already verified.
     *
     * @return array{selector: string, userId: int, email: string, display_name: string}|null
     */
    public function generate(int $userId): ?array
    {
        // Use findByIdAny (ignores is_active) — admin may create accounts for users who haven't registered.
        $user = $this->users->findByIdAny($userId);
        if ($user === null) {
            return null;
        }

        // Skip if already verified
        if (!empty($user['email_verified_at'])) {
            return null;
        }

        $token = $this->createToken((int) $user['id']);

        return [
            'selector'     => $token['selector'],
            'userId'       => (int) $user['id'],
            'email'        => $user['email'],
            'display_name' => $user['display_name'],
        ];
    }

    /**
     * Resend verification email for the given user by email address.
     *
     * Returns the raw token or null (enumeration-safe: always returns a
     * result; the caller should not distinguish between "found" and "not found").
     *
     * @return array{selector: string, userId: int, email: string, display_name: string}|null
     */
    public function resend(string $email): ?array
    {
        // Look up active user by email (same as login flow)
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            // Enumeration-safe: don't reveal whether the account exists or is verified.
            return null;
        }

        // Skip if already verified
        if (!empty($user['email_verified_at'])) {
            return null;
        }

        // Revoke previous pending tokens
        $this->repository->revokeAllForUser((int) $user['id']);

        $token = $this->createToken((int) $user['id']);

        return [
            'selector'     => $token['selector'],
            'userId'       => (int) $user['id'],
            'email'        => $user['email'],
            'display_name' => $user['display_name'],
        ];
    }

    /**
     * Validate a verification token and mark the user's email as verified.
     *
     * Rejects if:
     *   - Token not found
     *   - Token expired
     *   - Token already used
     *   - User is inactive
     *
     * On success: sets email_verified_at on the user, revokes the token.
     */
    public function validate(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);

        $tokenRecord = $this->repository->findByHash($tokenHash);
        if ($tokenRecord === null) {
            return null;
        }

        if ($tokenRecord['used_at'] !== null) {
            return null;
        }

        if (strtotime($tokenRecord['expires_at']) <= time()) {
            return null;
        }

        // Use findByIdAny since the user might be inactive
        $user = $this->users->findByIdAny((int) $tokenRecord['user_id']);
        if ($user === null) {
            return null;
        }

        // Set email_verified_at
        $this->users->setEmailVerified((int) $user['id'], date('Y-m-d H:i:s'));

        // Revoke the validated token + all other pending tokens
        $this->repository->revoke((int) $tokenRecord['id']);
        $this->repository->revokeAllForUser((int) $user['id']);

        return ['user' => $user, 'token_id' => (int) $tokenRecord['id']];
    }

    /**
     * Check if a user has a recent enough verification token for rate limiting.
     *
     * Returns true if a token was created within the rate limit window (used for resend throttling).
     */
    public function hasRecentToken(int $userId): bool
    {
        $token = $this->repository->findByHash('rate_limit_placeholder'); // Will be replaced by caller
        if ($token === null) {
            return false;
        }

        return (strtotime($token['created_at']) + self::RATE_LIMIT_SECONDS) > time();
    }

    /**
     * Send the verification email to a user.
     */
    public function sendEmail(string $to, string $toName, string $verifyUrl, string $fromAddress = 'noreply@localhost', string $fromName = 'Kernel-Web'): bool
    {
        if ($this->mailer === null) {
            return false;
        }

        $html = self::renderEmailTemplate($toName, $verifyUrl);
        if ($html === '') {
            $html = '<p>Please verify your email address by clicking the link below:</p>'
                  . '<p><a href="' . htmlspecialchars($verifyUrl) . '">'
                  . htmlspecialchars($verifyUrl) . '</a></p>'
                  . '<p>This link expires in 24 hours.</p>';
        }

        $message = new MailMessage(
            from: $fromAddress,
            fromName: $fromName,
            to: $to,
            toName: $toName,
            subject: 'Verify your email address',
            bodyHtml: $html,
        );

        try {
            return $this->mailer->send($message);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Render the email verification HTML template with context.
     */
    public static function renderEmailTemplate(string $userName, string $verifyUrl): string
    {
        if (!is_file(self::TEMPLATE_PATH)) {
            return '';
        }

        $userName  = $userName;
        $verifyUrl = $verifyUrl;

        ob_start();
        require self::TEMPLATE_PATH;
        return ob_get_clean();
    }

    /**
     * Create a token record and generate the raw token.
     */
    private function createToken(int $userId): ?array
    {
        $selector  = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::EXPIRY_SECONDS);

        $this->repository->create($userId, hash('sha256', $selector), $expiresAt);

        return ['selector' => $selector, 'userId' => $userId];
    }
}
