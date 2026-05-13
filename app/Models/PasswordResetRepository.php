<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * Database operations for password reset tokens.
 */
class PasswordResetRepository
{
    public function __construct(private DatabaseInterface $db) {}

    /**
     * Create a new password reset token record.
     */
    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO auth_password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?)',
            [$userId, $tokenHash, $expiresAt, $now]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a reset token by its token hash.
     */
    public function findByHash(string $tokenHash): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM auth_password_resets WHERE token_hash = ? LIMIT 1',
            [$tokenHash]
        );
    }

    /**
     * Revoke (mark as used) a reset token.
     */
    public function revoke(int $tokenId): bool
    {
        $affected = $this->db->execute(
            'UPDATE auth_password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $tokenId]
        );

        return $affected > 0;
    }

    /**
     * Delete a reset token record.
     */
    public function delete(int $tokenId): bool
    {
        $affected = $this->db->execute(
            'DELETE FROM auth_password_resets WHERE id = ?',
            [$tokenId]
        );

        return $affected > 0;
    }

    /**
     * Revoke all pending reset tokens for a user.
     */
    public function revokeAllForUser(int $userId): void
    {
        $this->db->execute(
            'UPDATE auth_password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $userId]
        );
    }
}
