<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * Database operations for two-factor authentication data.
 *
 * Manages:
 *   - users.totp_secret and users.totp_enabled_at
 *   - auth_2fa_recovery_codes table
 */
class TwoFactorRepository
{
    public function __construct(private DatabaseInterface $db) {}

    /**
     * Get a user's TOTP secret (includes the secret column).
     *
     * Returns null if the user has no secret, the user doesn't exist,
     * or the totp_secret/totp_enabled_at columns are missing from the
     * users table (migration not yet applied). In the missing-schema
     * case, treat 2FA as disabled rather than crashing.
     */
    public function getTotpSecret(int $userId): ?array
    {
        try {
            return $this->db->fetchOne(
                'SELECT totp_secret, totp_enabled_at FROM users WHERE id = ? LIMIT 1',
                [$userId]
            );
        } catch (\PDOException $e) {
            // Column may not exist (migration 0051 not applied).
            // Fail closed: treat as if 2FA is disabled.
            error_log('[TwoFactor] Failed to query TOTP columns: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set a user's TOTP secret.
     *
     * Silently no-ops if the totp_secret/totp_enabled_at columns are
     * missing (migration not applied).
     *
     * @param string|null $secret NULL to clear.
     */
    public function setTotpSecret(int $userId, ?string $secret): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            if ($secret === null) {
                $this->db->execute(
                    'UPDATE users SET totp_secret = ?, totp_enabled_at = ?, updated_at = ? WHERE id = ?',
                    [null, null, $now, $userId]
                );
            } else {
                $this->db->execute(
                    'UPDATE users SET totp_secret = ?, totp_enabled_at = ?, updated_at = ? WHERE id = ?',
                    [$secret, $now, $now, $userId]
                );
            }
        } catch (\PDOException $e) {
            // Columns may not exist. Silent no-op.
            error_log('[TwoFactor] Failed to set TOTP secret: ' . $e->getMessage());
        }
    }

    /**
     * Create a recovery code for a user.
     *
     * Silently no-ops if the auth_2fa_recovery_codes table is missing.
     *
     * @return int The new recovery code ID, or 0 on failure.
     */
    public function createRecoveryCode(int $userId, string $codeHash): int
    {
        try {
            $now = date('Y-m-d H:i:s');
            $this->db->execute(
                'INSERT INTO auth_2fa_recovery_codes (user_id, code_hash, created_at) VALUES (?, ?, ?)',
                [$userId, $codeHash, $now]
            );
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log('[TwoFactor] Failed to create recovery code: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Validate and consume a recovery code (single-use).
     *
     * Returns true if the code was found and marked used.
     * Returns false if the table or columns are missing.
     */
    public function consumeRecoveryCode(int $userId, string $codeHash): bool
    {
        try {
            $affected = $this->db->execute(
                'UPDATE auth_2fa_recovery_codes SET used_at = ? WHERE user_id = ? AND code_hash = ? AND used_at IS NULL',
                [date('Y-m-d H:i:s'), $userId, $codeHash]
            );
            return $affected > 0;
        } catch (\PDOException $e) {
            error_log('[TwoFactor] Failed to consume recovery code: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all unused recovery codes for a user (for display).
     *
     * Returns empty array if the table is missing.
     */
    public function getUnusedCodes(int $userId): array
    {
        try {
            return $this->db->fetch(
                'SELECT id, code_hash, created_at FROM auth_2fa_recovery_codes
                 WHERE user_id = ? AND used_at IS NULL ORDER BY created_at ASC',
                [$userId]
            );
        } catch (\PDOException $e) {
            error_log('[TwoFactor] Failed to get recovery codes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete all recovery codes for a user.
     *
     * Silently no-ops if the table is missing.
     */
    public function deleteAllCodes(int $userId): void
    {
        try {
            $this->db->execute('DELETE FROM auth_2fa_recovery_codes WHERE user_id = ?', [$userId]);
        } catch (\PDOException $e) {
            error_log('[TwoFactor] Failed to delete recovery codes: ' . $e->getMessage());
        }
    }
}
