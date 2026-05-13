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
     */
    public function getTotpSecret(int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT totp_secret, totp_enabled_at FROM users WHERE id = ? LIMIT 1',
            [$userId]
        );
    }

    /**
     * Set a user's TOTP secret.
     *
     * @param string|null $secret NULL to clear.
     */
    public function setTotpSecret(int $userId, ?string $secret): void
    {
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
    }

    /**
     * Create a recovery code for a user.
     *
     * @return int The new recovery code ID.
     */
    public function createRecoveryCode(int $userId, string $codeHash): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO auth_2fa_recovery_codes (user_id, code_hash, created_at) VALUES (?, ?, ?)',
            [$userId, $codeHash, $now]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Validate and consume a recovery code (single-use).
     *
     * Returns true if the code was found and marked used.
     */
    public function consumeRecoveryCode(int $userId, string $codeHash): bool
    {
        $affected = $this->db->execute(
            'UPDATE auth_2fa_recovery_codes SET used_at = ? WHERE user_id = ? AND code_hash = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $userId, $codeHash]
        );
        return $affected > 0;
    }

    /**
     * Get all unused recovery codes for a user (for display).
     */
    public function getUnusedCodes(int $userId): array
    {
        return $this->db->fetch(
            'SELECT id, code_hash, created_at FROM auth_2fa_recovery_codes
             WHERE user_id = ? AND used_at IS NULL ORDER BY created_at ASC',
            [$userId]
        );
    }

    /**
     * Delete all recovery codes for a user.
     */
    public function deleteAllCodes(int $userId): void
    {
        $this->db->execute('DELETE FROM auth_2fa_recovery_codes WHERE user_id = ?', [$userId]);
    }
}
