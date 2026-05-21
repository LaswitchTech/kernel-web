<?php

namespace App\Auth;

use App\Core\AuthProviderInterface;

/**
 * Manages the authentication session.
 *
 * Responsibilities:
 *   - Start and configure the PHP session
 *   - Delegate credential verification to the configured provider
 *   - Store and retrieve the authenticated user ID from the session
 *   - Provide a clean logout (session destruction)
 *   - Issue and revoke Remember Me tokens
 *
 * Not responsible for:
 *   - How credentials are verified (that is the provider's job)
 *   - Route protection (that will be a middleware concern)
 *   - Token-based API auth (separate concern, future work)
 */
class AuthService
{
    private AuthProviderInterface $provider;
    private array                 $config;
    private bool                  $sessionStarted = false;

    /** @var array|null In-request cache — avoid re-querying DB on every call */
    private ?array $cachedUser = null;

    /** @var \App\Core\Container|null DI container (for TwoFactorService access) */
    private ?\App\Core\Container $container = null;

    /** @var bool Whether SessionAuth allowed pending 2FA access through */
    private bool $pendingTwoFactorAccess = false;

    // 5-minute window for pending 2FA state
    private const TWO_FACTOR_PENDING_LIFETIME = 300;

    public function __construct(
        AuthProviderInterface $provider,
        array $config,
        private ?RememberMeService $rememberMe = null,
    ) {
        $this->provider = $provider;
        $this->config   = $config;
    }

    /**
     * Get the configured Remember Me service, or null if disabled.
     */
    public function getRememberMeService(): ?RememberMeService
    {
        return $this->rememberMe;
    }

    /**
     * Get the auth provider (for 2FA flow user lookups).
     */
    public function provider(): AuthProviderInterface
    {
        return $this->provider;
    }

    // ------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Attempt to log in with the given credentials.
     * Returns the safe user array on success, null on failure.
     *
     * If the user has 2FA enabled, stores a pending 2FA state and returns
     * the safe user array for the caller to present the 2FA form.
     *
     * @param bool $remember Whether to issue a Remember Me cookie (only after 2FA completes).
     * @return array|null Safe user array, or null on failure.
     */
    public function login(array $credentials, bool $remember = false): ?array
    {
        $user = $this->provider->attempt($credentials);

        if ($user === null) {
            return null;
        }

        $this->startSession();

        // Check if user has 2FA enabled
        if ($this->hasTwoFactorEnabled($user['id'])) {
            // Store pending 2FA state — not a full session
            $this->storeTwoFactorPending($user['id']);
            return $user;
        }

        $_SESSION['user_id'] = $user['id'];

        $this->cachedUser = $user;

        if ($remember && $this->rememberMe !== null) {
            // Revoke any existing tokens first (one-at-a-time)
            $this->rememberMe->revokeAll($user['id']);
            $this->rememberMe->issue($user['id']);
        }

        return $user;
    }

    /**
     * Complete 2FA verification and promote the pending state to a full session.
     *
     * Called after the user successfully enters their TOTP code or recovery code.
     */
    public function completeTwoFactor(bool $remember = false): void
    {
        // Auto-start session so we can read $_SESSION.
        $this->startSession();
        $userId = (int) ($_SESSION['_2fa_user_id'] ?? 0);
        $expires = $_SESSION['_2fa_expires'] ?? 0;

        // Clear pending state regardless of validity (one-time use)
        $this->clearTwoFactorPending();

        if ($userId === 0 || $expires <= time()) {
            return; // Expired or invalid — don't create session
        }

        $_SESSION['user_id'] = $userId;

        $this->cachedUser = $this->provider->getUserById($userId);

        if ($remember && $this->rememberMe !== null) {
            $this->rememberMe->issue($userId);
        }
    }

    /**
     * Check if the user has 2FA enabled.
     *
     * Fails closed: returns false if the two_factor service is unavailable,
     * the database schema is missing 2FA columns, or any error occurs.
     * This ensures login never crashes because optional 2FA schema is absent.
     */
    public function hasTwoFactorEnabled(int $userId): bool
    {
        if (!isset($this->container)) {
            return false;
        }
        try {
            $service = $this->container->get('two_factor');
            return $service->isEnabled($userId);
        } catch (\Throwable $e) {
            error_log('[Auth] hasTwoFactorEnabled failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if there is a pending 2FA state.
     */
    public function hasPendingTwoFactor(): bool
    {
        if (!isset($this->container)) {
            return false;
        }

        // Auto-start session so we can read $_SESSION (caller may not have started one).
        $this->startSession();

        // DIAG
        $sid = session_id();
        $sf = session_save_path() . '/sess_' . $sid;
        file_put_contents(
            __DIR__ . '/../../storage/logs/2fa_debug.log',
            "hasPending: name=" . session_name() . " id=$sid keys=" . implode(',', array_keys($_SESSION)) .
            " file=$sf exists=" . (file_exists($sf) ? 'yes' : 'no') . "\n",
            FILE_APPEND | LOCK_EX
        );

        $userId = (int) ($_SESSION['_2fa_user_id'] ?? 0);
        $expires = (int) ($_SESSION['_2fa_expires'] ?? 0);

        if ($userId === 0 || $expires <= time()) {
            $this->clearTwoFactorPending();
            return false;
        }

        return true;
    }

    /**
     * Get the pending 2FA user ID.
     */
    public function getPendingTwoFactorUserId(): ?int
    {
        // Auto-start session so we can read $_SESSION.
        $this->startSession();
        $userId = (int) ($_SESSION['_2fa_user_id'] ?? 0);
        return $userId > 0 ? $userId : null;
    }

    /**
     * Signal that SessionAuth allowed pending 2FA access through.
     * Used by SessionAuth middleware to allow pending 2FA users through.
     */
    public function setTwoFactorPendingAccess(): void
    {
        $this->pendingTwoFactorAccess = true;
    }

    /**
     * Check whether pending 2FA access is allowed.
     * Returns true when SessionAuth explicitly allowed a pending 2FA user through.
     * Note: this is NOT the same as hasPendingTwoFactor() — it requires an explicit
     * setTwoFactorPendingAccess() call to return true.
     */
    public function hasPendingTwoFactorAccess(): bool
    {
        return $this->pendingTwoFactorAccess;
    }

    /**
     * Log out the current user and destroy their session.
     */
    public function logout(): void
    {
        $this->startSession();

        // Clear ALL session state (full auth + pending 2FA).
        $_SESSION = [];

        // Expire the session cookie immediately
        if (@ini_get('session.use_cookies')) {
            $params = @session_get_cookie_params();
            @setcookie(
                @session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? true
            );
        }

        @session_destroy();

        // Revoke all remember tokens before clearing session state.
        $logoutUserId = $_SESSION['user_id'] ?? null;
        if ($logoutUserId !== null && $this->rememberMe !== null) {
            $this->rememberMe->revokeAll((int) $logoutUserId);
        }

        $this->sessionStarted = false;
        $this->cachedUser     = null;
    }

    /**
     * Return the currently authenticated user, or null if not logged in.
     * Re-fetches from the provider on the first call per request.
     */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $this->startSession();

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $user = $this->provider->getUserById((int) $_SESSION['user_id']);

        // If the user no longer exists or was deactivated, clear the session
        if ($user === null) {
            unset($_SESSION['user_id']);
            return null;
        }

        $this->cachedUser = $user;

        return $user;
    }

    /**
     * Return true if a user is currently logged in.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    // -------------------------------------------------------------------------
    // Session management
    // -------------------------------------------------------------------------

    /**
     * Restore a session from a user ID (used by Remember Me flow).
     */
    public function restoreSession(int $userId): void
    {
        $this->startSession();

        // Do NOT restore full session if 2FA is enabled.
        if ($this->hasTwoFactorEnabled($userId)) {
            return; // 2FA blocks auto-login — user must enter TOTP code first.
        }

        $_SESSION['user_id'] = $userId;
        $this->cachedUser = $this->provider->getUserById($userId);
    }

    /**
     * Set the DI container for 2FA checks.
     */
    public function setContainer(\App\Core\Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Store a pending 2FA state for the given user.
     */
    private function storeTwoFactorPending(int $userId): void
    {
        $_SESSION['_2fa_user_id'] = $userId;
        $_SESSION['_2fa_expires'] = time() + self::TWO_FACTOR_PENDING_LIFETIME;
    }

    /**
     * Clear the pending 2FA state.
     */
    private function clearTwoFactorPending(): void
    {
        unset($_SESSION['_2fa_user_id'], $_SESSION['_2fa_expires']);
    }

    private function startSession(): void
    {
        static $started = false;
        if ($started) {
            return;
        }
        $started = true;

        $preId    = session_id();
        $preName  = session_name();
        $configName = $this->config['session']['name'] ?? 'kernel_web_session';

        if ($preName !== $configName) {
            // Session name mismatch — a zombie/external session is active (from mod_session.so).
            // We need to replace it with our own session.
            // IMPORTANT: if the browser already has a kernel_web_session cookie from a
            // previous login, use THAT ID so we can find our pending state. Otherwise
            // generate a fresh one.
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
                file_put_contents(
                    __DIR__ . '/../../storage/logs/2fa_debug.log',
                    "startSession writeCloseZombie: oldName=$preName oldId=$preId status_after=" . session_status() . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }

            // Check if browser already has our cookie — if so, use it as session ID
            // to preserve any pending state we might have.
            $cookieSessionId = $_COOKIE[$configName] ?? null;
            if ($cookieSessionId !== null && strlen($cookieSessionId) > 10) {
                $newId = $cookieSessionId;
                file_put_contents(
                    __DIR__ . '/../../storage/logs/2fa_debug.log',
                    "startSession useCookie: cookieId=$newId\n",
                    FILE_APPEND | LOCK_EX
                );
            } else {
                $newId = bin2hex(random_bytes(16));
                file_put_contents(
                    __DIR__ . '/../../storage/logs/2fa_debug.log',
                    "startSession generateNew: newId=$newId\n",
                    FILE_APPEND | LOCK_EX
                );
            }

            @session_id($newId);
            @session_name($configName);
            file_put_contents(
                __DIR__ . '/../../storage/logs/2fa_debug.log',
                "startSession mismatch: newId=$newId id_after=" . session_id() . " name_after=" . session_name() . " status=" . session_status() . "\n",
                FILE_APPEND | LOCK_EX
            );
        } else {
            session_name($configName);
            file_put_contents(
                __DIR__ . '/../../storage/logs/2fa_debug.log',
                "startSession no-mismatch: name_after=" . session_name() . " status=" . session_status() . "\n",
                FILE_APPEND | LOCK_EX
            );
        }

        session_set_cookie_params([
            'lifetime' => (int) ($this->config['session']['lifetime'] ?? 7200),
            'path'     => '/',
            'secure'   => (bool) ($this->config['session']['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Reject unrecognised session IDs (helps prevent session fixation)
        @ini_set('session.use_strict_mode', '1');

        @session_start();

        // DIAG: verify session loaded correctly
        file_put_contents(
            __DIR__ . '/../../storage/logs/2fa_debug.log',
            "after_start: name=" . session_name() . " id=" . session_id() .
            " keys=" . implode(',', array_keys($_SESSION)) . "\n",
            FILE_APPEND | LOCK_EX
        );

        // Explicit setcookie to ensure the browser always has the session cookie,
        // even if PHP skips it because $_COOKIE already has it.
        setcookie(
            $configName,
            session_id(),
            [
                'expires'  => time() + ($this->config['session']['lifetime'] ?? 7200),
                'path'     => '/',
                'secure'   => (bool) ($this->config['session']['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        $this->sessionStarted = true;
    }
}
