<?php

namespace App\Auth;

use App\Models\RememberTokenRepository;
use App\Models\UserRepository;

/**
 * Manages Remember Me tokens.
 *
 * Security model:
 *   - A "cookie" is composed of two parts: selector + validator
 *   - Only the selector is visible to the user (stored in the cookie)
 *   - The validator is hashed (SHA-256) and stored in the database
 *   - On each request, the selector is used to look up the token, then
 *     the validator is checked via hash_equals() to prevent timing attacks
 *   - On successful use, the token is rotated: old token revoked,
 *     new selector/validator generated, old selector kept in cookie
 *
 * This pattern (split selector/validator) is the same as Laravel's remember
 * me implementation and provides defense-in-depth even if the database is
 * compromised (attacker only gets hashed validators).
 */
readonly class RememberMeService
{
    private const COOKIE_NAME = 'kernel_remember';

    public function __construct(
        private RememberTokenRepository $tokens,
        private UserRepository          $users,
        private array                   $config,
    ) {}

    /**
     * Issue a new remember me token for the given user.
     *
     * Creates a database record, sets the cookie, and returns the raw
     * validator (the caller must never log or store this).
     *
     * @param int $userId
     * @return array{selector: string, validator: string}
     */
    public function issue(int $userId): array
    {
        $selector  = bin2hex(random_bytes(16));  // 32-char hex selector
        $validator = bin2hex(random_bytes(32));   // 64-char hex validator

        $hash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->getLifetime());

        $this->tokens->create($userId, $selector, $hash, $expiresAt);

        $this->setCookie($selector, $validator);

        return ['selector' => $selector, 'validator' => $validator];
    }

    /**
     * Attempt to restore a session from the remember me cookie.
     *
     * Validates the cookie, checks token expiry/revocation/user status,
     * and rotates the token if valid.
     *
     * @return array{user: array, token_id: int}|null
     */
    public function attempt(): ?array
    {
        $cookie = $this->getCookie();
        if ($cookie === null) {
            return null;
        }

        [$selector, $validator] = $cookie;

        $token = $this->tokens->findBySelector($selector);
        if ($token === null) {
            $this->deleteCookie();
            return null;
        }

        // Check if token is revoked
        if ($token['revoked_at'] !== null) {
            $this->deleteCookie();
            return null;
        }

        // Check if token is expired
        if (strtotime($token['expires_at']) <= time()) {
            $this->deleteCookie();
            return null;
        }

        // Validate the validator part via timing-safe comparison
        if (!hash_equals($token['token_hash'], hash('sha256', $validator))) {
            $this->deleteCookie();
            return null;
        }

        // Check if user is still active
        $user = $this->users->findByIdAny((int) $token['user_id']);
        if ($user === null || !$user['is_active']) {
            $this->deleteCookie();
            return null;
        }

        // Rotate the token: revoke old, issue new record with new selector+validator
        $newSelector  = bin2hex(random_bytes(16));
        $newValidator = bin2hex(random_bytes(32));
        $newHash      = hash('sha256', $newValidator);
        $newExpiresAt = date('Y-m-d H:i:s', time() + $this->getLifetime());

        $newTokenId = $this->tokens->rotate(
            (int) $token['id'],
            $newSelector,
            $newHash,
            $newExpiresAt
        );

        // Update the cookie with the new selector:validator
        $this->setCookie($newSelector, $newValidator);

        $safeUser = [
            'id'           => (int) $user['id'],
            'display_name' => $user['display_name'] ?? '',
            'username'     => $user['username'],
            'email'        => $user['email'],
            'is_active'    => (bool) $user['is_active'],
            'created_at'   => $user['created_at'],
            'updated_at'   => $user['updated_at'],
        ];

        return ['user' => $safeUser, 'token_id' => $newTokenId];
    }

    /**
     * Revoke all remember tokens for a user.
     * Called on logout, password change, and admin user deactivation.
     */
    public function revokeAll(int $userId): void
    {
        $this->tokens->revokeAllForUser($userId);
    }

    /**
     * Get the configured cookie lifetime in seconds.
     */
    private function getLifetime(): int
    {
        return (int) ($this->config['remember_me']['lifetime'] ?? self::COOKIE_LIFETIME);
    }

    /**
     * Set the remember me cookie.
     */
    private function setCookie(string $selector, string $validator): void
    {
        $secure = $this->config['session']['secure'] ?? false;

        @setcookie(
            self::COOKIE_NAME,
            $selector . ':' . $validator,
            [
                'expires'  => time() + $this->getLifetime(),
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Read and parse the remember me cookie.
     *
     * @return array{0: string, 1: string}|null
     */
    private function getCookie(): ?array
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($cookie === null || !is_string($cookie)) {
            return null;
        }

        // Split on the last colon to handle edge cases
        $lastColon = strrpos($cookie, ':');
        if ($lastColon === false) {
            return null;
        }

        $selector = substr($cookie, 0, $lastColon);
        $validator = substr($cookie, $lastColon + 1);

        return [$selector, $validator];
    }

    /**
     * Delete the remember me cookie.
     */
    private function deleteCookie(): void
    {
        @setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires'  => time() - 42000,
                'path'     => '/',
                'secure'   => $this->config['session']['secure'] ?? false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
