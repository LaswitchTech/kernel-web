<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * Database operations for email verification tokens.
 */
class EmailVerificationRepository
{
    public function __construct(private DatabaseInterface $db) {}

    /**
     * Create a new email verification token record.
     */
    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO auth_email_verifications (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?)',
            [$userId, $tokenHash, $expiresAt, $now]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a verification token by its token hash.
     */
    public function findByHash(string $tokenHash): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM auth_email_verifications WHERE token_hash = ? LIMIT 1',
            [$tokenHash]
        );
    }

    /**
     * Mark a verification token as used.
     */
    public function revoke(int $tokenId): bool
    {
        $affected = $this->db->execute(
            'UPDATE auth_email_verifications SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $tokenId]
        );

        return $affected > 0;
    }

    /**
     * Delete a verification token record.
     */
    public function delete(int $tokenId): bool
    {
        $affected = $this->db->execute(
            'DELETE FROM auth_email_verifications WHERE id = ?',
            [$tokenId]
        );

        return $affected > 0;
    }

    /**
     * Revoke all pending verification tokens for a user.
     */
    public function revokeAllForUser(int $userId): void
    {
        $this->db->execute(
            'UPDATE auth_email_verifications SET used_at = ? WHERE user_id = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $userId]
        );
    }
}
