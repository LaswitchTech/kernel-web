<?php

namespace App\Auth;

use App\Models\TwoFactorRepository;
use App\Models\UserRepository;

/**
 * Manages two-factor authentication (TOTP, RFC 6238).
 *
 * Security model:
 *   - TOTP secret stored in users.totp_secret (never logged)
 *   - Recovery codes stored as SHA-256 hashes (never raw)
 *   - TOTP window: ±1 step (90s total)
 *   - 6-digit codes, 30-second steps
 *   - Disabling clears secret and revokes all recovery codes
 */
class TwoFactorService
{
    private const TOTP_STEP   = 30;
    private const TOTP_DIGITS = 6;
    private const TOTP_WINDOW = 1;
    private const RECOVERY_COUNT = 10;

    public function __construct(
        private TwoFactorRepository $repository,
        private UserRepository      $users,
    ) {}

    /**
     * Generate a TOTP secret for the given user.
     *
     * Stores the secret in a PENDING state (totp_secret + totp_pending_at).
     * 2FA is NOT enabled until enable() is called with a valid OTP confirmation.
     *
     * Returns the Base32-encoded secret. The caller should:
     *   1. Generate a QR code (otpauth:// URI)
     *   2. Show it to the user
     *   3. Ask the user to enter a code from their TOTP app to confirm
     *   4. Call enable() when confirmed.
     */
    public function generateSecret(int $userId): ?string
    {
        $user = $this->users->findByIdAny($userId);
        if ($user === null) {
            return null;
        }

        // Return existing pending secret if one is already set (idempotent).
        // Prevents QR codes from becoming stale when the endpoint is called
        // multiple times (page reload, retry, etc.).
        $existing = $this->repository->getTotpSecret($userId);
        if ($existing !== null && !empty($existing['totp_secret']) && empty($existing['totp_enabled_at'])) {
            return $existing['totp_secret'];
        }

        $secret = $this->encodeSecret(random_bytes(20));
        $this->repository->setTotpSecret($userId, $secret, pending: true);

        return $secret;
    }

    /**
     * Verify a TOTP code for a user's stored secret.
     *
     * Allows ±1 time-step window. Uses SHA-1 as required by RFC 6238.
     *
     * @return bool True if the code is valid.
     */
    public function verifyTotp(int $userId, string $code): bool
    {
        $secretData = $this->repository->getTotpSecret($userId);
        if ($secretData === null || empty($secretData['totp_secret'])) {
            return false;
        }

        $hexSecret = $this->decodeBase32($secretData['totp_secret']);
        if ($hexSecret === false || strlen($hexSecret) === 0) {
            return false;
        }

        $binarySecret = hex2bin($hexSecret);
        if ($binarySecret === false) {
            return false;
        }

        $step = (int) floor(time() / self::TOTP_STEP);

        // Check ±1 window
        for ($i = -self::TOTP_WINDOW; $i <= self::TOTP_WINDOW; $i++) {
            $hmac = hash_hmac(
                'sha1',
                pack('N*', $step + $i),
                $binarySecret,
                true
            );

            $offset = ord($hmac[19]) & 0x0F;
            $codeNum = ((ord($hmac[$offset]) & 0x7F) << 24)
                     | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
                     | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
                     | (ord($hmac[$offset + 3]) & 0xFF);

            $generated = str_pad((string) ($codeNum % 10 ** self::TOTP_DIGITS), self::TOTP_DIGITS, '0', STR_PAD_LEFT);

            if (hash_equals($generated, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate recovery codes for a user.
     *
     * Replaces any existing unused codes.
     *
     * @return array<int, array{code: string, codeHash: string, id: int}> The generated codes with their hashes.
     */
    public function generateRecoveryCodes(int $userId): array
    {
        $this->repository->deleteAllCodes($userId);

        $codes = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $raw = bin2hex(random_bytes(5)); // 10 hex chars
            $hash = hash('sha256', $raw);
            $this->repository->createRecoveryCode($userId, $hash);
            $codes[] = ['code' => $raw, 'codeHash' => $hash, 'id' => (int) $this->repository->getUnusedCodes($userId)[0]['id'] ?? 0];
        }

        return $codes;
    }

    /**
     * Validate a recovery code.
     *
     * Returns true if valid and the code was consumed (single-use).
     */
    public function validateRecoveryCode(int $userId, string $code): bool
    {
        $hash = hash('sha256', $code);
        return $this->repository->consumeRecoveryCode($userId, $hash);
    }

    /**
     * Enable 2FA for a user (final step after secret generation and confirmation).
     *
     * If a pending secret exists, promotes it to enabled (sets totp_enabled_at, clears totp_pending_at).
     * If no secret exists, generates a new one and enables immediately.
     *
     * @return array{secret: string, recoveryCodes: array} The secret and generated recovery codes.
     */
    public function enable(int $userId): array
    {
        $secretData = $this->repository->getTotpSecret($userId);
        $secret = $secretData['totp_secret'] ?? null;

        if ($secret === null) {
            $secret = $this->encodeSecret(random_bytes(20));
            $this->repository->setTotpSecret($userId, $secret, pending: false);
        } else {
            // Promote pending to enabled (set totp_enabled_at, clear totp_pending_at).
            // The existing secret is reused; only the state flags change.
            $this->repository->setTotpSecret($userId, $secret, pending: false);
        }

        $recoveryCodes = $this->generateRecoveryCodes($userId);

        return ['secret' => $secret, 'recoveryCodes' => $recoveryCodes];
    }

    /**
     * Disable 2FA for a user.
     *
     * Clears the TOTP secret, totp_enabled_at, totp_pending_at, and revokes all recovery codes.
     */
    public function disable(int $userId): void
    {
        $this->repository->setTotpSecret($userId, null);
        $this->repository->deleteAllCodes($userId);
    }

    /**
     * Check if a user has 2FA enabled.
     *
     * Requires both a secret AND totp_enabled_at to be non-empty.
     * Pending (unconfirmed) secrets do NOT count as enabled.
     */
    public function isEnabled(int $userId): bool
    {
        $secretData = $this->repository->getTotpSecret($userId);
        return $secretData !== null
            && !empty($secretData['totp_secret'])
            && !empty($secretData['totp_enabled_at']);
    }

    /**
     * Check if a user has a pending (unconfirmed) 2FA setup.
     *
     * Returns true when totp_secret is set but totp_enabled_at is NULL.
     * This indicates the user started setup but never confirmed with OTP.
     */
    public function hasPendingSetup(int $userId): bool
    {
        $secretData = $this->repository->getTotpSecret($userId);
        return $secretData !== null
            && !empty($secretData['totp_secret'])
            && empty($secretData['totp_enabled_at']);
    }

    /**
     * Get the OTPAuth URI for QR code generation.
     */
    public function getOtpauthUri(int $userId, string $appName, string $userEmail): string
    {
        $secretData = $this->repository->getTotpSecret($userId);
        $secret = $secretData['totp_secret'] ?? $this->encodeSecret(random_bytes(20));
        return "otpauth://totp/{$appName}:{$userEmail}?secret={$secret}&issuer={$appName}&algorithm=SHA1&digits=" . self::TOTP_DIGITS . "&period=" . self::TOTP_STEP;
    }

    // ------ PRIVATE ------

    /**
     * Encode binary data as Base32 (RFC 4648).
     */
    private function encodeSecret(string $bytes): string
    {
        $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $chunks = str_split($bits, 5);
        $encoded = '';
        foreach ($chunks as $chunk) {
            $encoded .= $base32Alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $encoded;
    }

    /**
     * Decode a Base32 string to hex for HMAC computation.
     */
    private function decodeBase32(string $base32): false|string
    {
        $base32 = str_replace('=', '', $base32);
        $base32 = strtoupper($base32);

        // Valid base32 characters
        if (!preg_match('/^[A-Z2-7]+$/', $base32)) {
            return false;
        }

        $bits = '';
        for ($i = 0; $i < strlen($base32); $i++) {
            $index = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $base32[$i]);
            if ($index === false) {
                return false;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        for ($i = 0; $i + 7 < strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }

        return bin2hex($bytes);
    }
}
