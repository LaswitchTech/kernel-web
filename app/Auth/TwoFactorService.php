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
 *   - TOTP window: ±1 step (90s total) for login, ±10 for setup
 *   - 6-digit codes, 30-second steps
 *   - Disabling clears secret and revokes all recovery codes
 *   - setup_id binds each QR code to a unique pending secret instance
 */
class TwoFactorService
{
    private const TOTP_STEP   = 30;
    private const TOTP_DIGITS = 6;
    private const TOTP_WINDOW = 1;

    public function __construct(
        private TwoFactorRepository $repository,
        private UserRepository      $users,
    ) {}

    /**
     * Generate a TOTP secret for the given user.
     *
     * Stores the secret in a PENDING state (totp_secret + totp_pending_at + totp_setup_id).
     * 2FA is NOT enabled until enable() is called with a valid OTP confirmation.
     *
     * @param string|null $setupId Unique ID to bind this QR code to.
     *                             Prevents stale QR issues from multiple generate calls.
     * @return string|null The Base32-encoded secret, or null if user not found.
     */
    public function generateSecret(int $userId, ?string $setupId = null): ?string
    {
        $result = $this->generateSecretFull($userId, $setupId);
        return $result !== null ? $result['secret'] : null;
    }

    /**
     * Generate a TOTP secret with full info (for API endpoints).
     *
     * @return array{secret: string, uri: string, setupId: string}|null
     */
    public function generateSecretFull(int $userId, ?string $setupId = null): ?array
    {
        $user = $this->users->findByIdAny($userId);
        if ($user === null) {
            return null;
        }

        $appName = 'Kernel-Web';

        // Return existing pending secret if one is already set (idempotent).
        // Prevents QR codes from becoming stale when the endpoint is called
        // multiple times (page reload, retry, etc.).
        $existing = $this->repository->getTotpSecret($userId);
        if ($existing !== null && !empty($existing['totp_secret']) && empty($existing['totp_enabled_at'])) {
            $secret = $existing['totp_secret'];
            // If no setup_id was provided, generate one for the existing pending state.
            // If setup_id was provided and matches, return existing.
            // If setup_id was provided and differs, treat as new setup.
            if ($setupId !== null && !empty($existing['totp_setup_id']) && $existing['totp_setup_id'] === $setupId) {
                // Reusing existing setup with matching ID — safe to return.
                $uri = $this->buildOtpauthUri($secret, $appName, $user['email'] ?? '');
                return ['secret' => $secret, 'uri' => $uri, 'setupId' => $existing['totp_setup_id']];
            }
            if ($setupId !== null && !empty($existing['totp_setup_id']) && $existing['totp_setup_id'] !== $setupId) {
                // Different setup_id — new setup session, generate new secret.
                $secret = $this->encodeSecret(random_bytes(20));
                $this->repository->setTotpSecret($userId, $secret, pending: true, setupId: $setupId);
                $uri = $this->buildOtpauthUri($secret, $appName, $user['email'] ?? '');
                return ['secret' => $secret, 'uri' => $uri, 'setupId' => $setupId];
            }
            // No setup_id provided or existing pending secret without setup_id.
            // Return existing pending secret (idempotent). Assign setup_id if missing.
            if (empty($existing['totp_setup_id']) && $setupId === null) {
                $setupId = bin2hex(random_bytes(16));
                $this->repository->setTotpSecret($userId, $secret, pending: true, setupId: $setupId);
            }
            $uri = $this->buildOtpauthUri($secret, $appName, $user['email'] ?? '');
            return ['secret' => $secret, 'uri' => $uri, 'setupId' => $existing['totp_setup_id'] ?? $setupId];
        }

        // No pending secret — create new one.
        $secret = $this->encodeSecret(random_bytes(20));
        $this->repository->setTotpSecret($userId, $secret, pending: true, setupId: $setupId);
        $uri = $this->buildOtpauthUri($secret, $appName, $user['email'] ?? '');
        return ['secret' => $secret, 'uri' => $uri, 'setupId' => $setupId];
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
        $result = $this->verifyTotpWithInfo($userId, $code, self::TOTP_WINDOW);
        return $result !== null;
    }

    /**
     * Get the TOTP secret for a user (for debugging).
     */
    public function getSecretForDebug(int $userId): ?string
    {
        $secretData = $this->repository->getTotpSecret($userId);
        return $secretData['totp_secret'] ?? null;
    }

    /**
     * Verify a TOTP code and return diagnostic info about the match.
     *
     * Useful for setup flows where time drift may be larger.
     *
     * @return array{offset: int, step: int, code: string}|null Null if no match.
     */
    public function verifyTotpWithInfo(int $userId, string $code, int $window = self::TOTP_WINDOW): ?array
    {
        $secretData = $this->repository->getTotpSecret($userId);
        if ($secretData === null || empty($secretData['totp_secret'])) {
            return null;
        }

        $hexSecret = $this->decodeBase32($secretData['totp_secret']);
        if ($hexSecret === false || strlen($hexSecret) === 0) {
            return null;
        }

        $binarySecret = hex2bin($hexSecret);
        if ($binarySecret === false) {
            return null;
        }

        $step = (int) floor(time() / self::TOTP_STEP);

        for ($i = -$window; $i <= $window; $i++) {
            $hmac = hash_hmac(
                'sha1',
                pack('N2', 0, $step + $i),
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
                return ['offset' => $i, 'step' => $step + $i, 'code' => $generated];
            }
        }

        return null;
    }

    /**
     * Verify a TOTP code and check that the pending setup_id matches the expected value.
     *
     * Returns null if no match or setup_id mismatch.
     *
     * @return array{offset: int, step: int, code: string, setupId: string}|null
     */
    public function verifyTotpWithSetupCheck(int $userId, string $code, int $window = self::TOTP_WINDOW, ?string $expectedSetupId = null): ?array
    {
        $secretData = $this->repository->getTotpSecret($userId);
        if ($secretData === null || empty($secretData['totp_secret'])) {
            return null;
        }

        // Check setup_id match if expected ID provided.
        if ($expectedSetupId !== null) {
            $storedSetupId = $secretData['totp_setup_id'] ?? '';
            if ($storedSetupId === '' || $storedSetupId !== $expectedSetupId) {
                return null;
            }
        }

        $hexSecret = $this->decodeBase32($secretData['totp_secret']);
        if ($hexSecret === false || strlen($hexSecret) === 0) {
            return null;
        }

        $binarySecret = hex2bin($hexSecret);
        if ($binarySecret === false) {
            return null;
        }

        $step = (int) floor(time() / self::TOTP_STEP);
        $storedSetupId = $secretData['totp_setup_id'] ?? '';

        for ($i = -$window; $i <= $window; $i++) {
            $hmac = hash_hmac(
                'sha1',
                pack('N2', 0, $step + $i),
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
                return ['offset' => $i, 'step' => $step + $i, 'code' => $generated, 'setupId' => $storedSetupId];
            }
        }

        return null;
    }

    /**
     * Generate a single UUID-formatted recovery code for a user.
     *
     * Replaces any existing code.
     *
     * @return array{code: string, codeHash: string} The raw code and its SHA-256 hash.
     */
    public function generateRecoveryCodes(int $userId): array
    {
        $this->repository->deleteAllCodes($userId);

        $raw = $this->generateUUID();
        $hash = hash('sha256', $raw);
        $this->repository->createRecoveryCode($userId, $hash);

        return ['code' => $raw, 'codeHash' => $hash];
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
     * @return array{secret: string, recoveryCodes: array{code: string, codeHash: string}} The secret and generated recovery code.
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
     * Generate a UUID v4 formatted recovery code.
     */
    private function generateUUID(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40); // version 4
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80); // variant RFC 4122
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-'
             . substr($hex, 8, 4) . '-'
             . substr($hex, 12, 4) . '-'
             . substr($hex, 16, 4) . '-'
             . substr($hex, 20, 12);
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
     * Get the pending setup info for a user (for UI display of current setup state).
     *
     * @return array{secret: string, setupId: string, pendingAt: string}|null
     */
    public function getPendingSetupInfo(int $userId): ?array
    {
        $secretData = $this->repository->getTotpSecret($userId);
        if ($secretData === null || empty($secretData['totp_secret']) || !empty($secretData['totp_enabled_at'])) {
            return null;
        }

        return [
            'secret'     => $secretData['totp_secret'],
            'setupId'    => $secretData['totp_setup_id'] ?? '',
            'pendingAt'  => $secretData['totp_pending_at'] ?? '',
        ];
    }

    // ------ PRIVATE ------

    /**
     * Build otpauth:// URI from a raw secret.
     */
    private function buildOtpauthUri(string $secret, string $appName, string $userEmail): string
    {
        return "otpauth://totp/{$appName}:{$userEmail}?secret={$secret}&issuer={$appName}&algorithm=SHA1&digits=" . self::TOTP_DIGITS . "&period=" . self::TOTP_STEP;
    }

    /**
     * Get the OTPAuth URI for QR code generation.
     */
    public function getOtpauthUri(int $userId, string $appName, string $userEmail): string
    {
        $secretData = $this->repository->getTotpSecret($userId);
        $secret = $secretData['totp_secret'] ?? $this->encodeSecret(random_bytes(20));
        return $this->buildOtpauthUri($secret, $appName, $userEmail);
    }

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
