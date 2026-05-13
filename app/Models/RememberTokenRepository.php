<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * All database queries for the auth_remember_tokens table.
 */
class RememberTokenRepository
{
    public function __construct(private DatabaseInterface $db) {}

    /**
     * Insert a new remember token record.
     *
     * @return int The new token ID.
     */
    public function create(int $userId, string $selector, string $tokenHash, string $expiresAt): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO auth_remember_tokens (user_id, selector, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $selector, $tokenHash, $expiresAt, $now]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a token by its selector (the public part stored in the cookie).
     */
    public function findBySelector(string $selector): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM auth_remember_tokens WHERE selector = ? LIMIT 1',
            [$selector]
        );
    }

    /**
     * Revoke a specific token by ID.
     * Returns false if the token was not found or already revoked.
     */
    public function revoke(int $tokenId): bool
    {
        $affected = $this->db->execute(
            'UPDATE auth_remember_tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $tokenId]
        );

        return $affected > 0;
    }

    /**
     * Revoke all active tokens for a user.
     *
     * Returns the number of tokens revoked.
     */
    public function revokeAllForUser(int $userId): int
    {
        return (int) $this->db->execute(
            'UPDATE auth_remember_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $userId]
        );
    }

    /**
     * Rotate a token: revoke the current one and insert a new record
     * linked to the same user, copying the selector so the cookie stays valid.
     */
    public function rotate(int $tokenId, string $newSelector, string $newTokenHash, string $newExpiresAt): int
    {
        $this->revoke($tokenId);

        $this->db->execute(
            'INSERT INTO auth_remember_tokens (user_id, selector, token_hash, expires_at, last_used_at, created_at)
             SELECT user_id, ?, ?, ?, last_used_at, created_at
             FROM auth_remember_tokens WHERE id = ?',
            [$newSelector, $newTokenHash, $newExpiresAt, $tokenId]
        );

        return (int) $this->db->lastInsertId();
    }
}
