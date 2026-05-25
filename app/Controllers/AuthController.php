<?php

namespace App\Controllers;

use App\Auth\AuthService;
use App\Auth\EmailVerificationService;
use App\Auth\TwoFactorService;
use App\Core\Controller;
use App\Services\DebugAuditLogger;

class AuthController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /signin — render the login page
    // --------------------------------

    public function loginRedirect(array $params = []): void
    {
        header('Location: /signin', true, 301);
        exit;
    }

    public function loginForm(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');

        // Already authenticated — send to home
        if ($auth->check()) {
            header('Location: /', true, 302);
            exit;
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

        ob_start();
        require $viewsPath . '/auth/login.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    // -------------------------------------------------------------------------
    // POST /auth/login
    // Body: { "identity": "username or email", "password": "..", "remember": "1" }
    // -------------------------------------------------------------------

    public function login(array $params = []): void
    {
        $identity = trim((string) $this->input('identity', ''));
        $password = (string) $this->input('password', '');
        $remember = (bool) $this->input('remember', '0');

        if ($identity === '' || $password === '') {
            $this->json(['error' => 'Identity and password are required'], 400);
            return;
        }

        /** @var AuthService $auth */
        $auth = $this->container->get('auth');

        $user = $auth->login([
            'identity' => $identity,
            'password' => $password,
        ], $remember);

        if ($user === null) {
            // Deliberately vague — do not reveal whether the identity exists
            $this->json(['error' => 'Invalid credentials'], 401);
            return;
        }

        // If user has 2FA enabled, redirect to 2FA form (pending state stored in AuthService)
        if ($auth->hasTwoFactorEnabled($user['id'])) {
            DebugAuditLogger::auth($user['id'], 'login.2fa_required', ['username' => $user['username']]);
            $this->json(['user' => $user, 'two_factor_required' => true]);
            return;
        }

        $this->json(['user' => $user]);
    }

    // -------------------------------------------------------------------
    // POST /auth/logout
    // --------------------------------------------------------------

    public function logout(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $auth->logout();

        $this->json(['success' => true]);
    }

    // -------------------------------------------------------------------
    // GET /auth/me
    // ------------------------------------

    public function me(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        $this->json(['user' => $user]);
    }

    // -------------------------------------------------------------------
    // Password Reset
    // -------------------------------------------------------

    /**
     * GET /auth/forgot-password — render the forgot password form.
     */
    public function forgotForm(array $params = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

        ob_start();
        require $viewsPath . '/auth/forgot-password.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    /**
     * POST /auth/forgot-password — initiate a password reset.
     *
     * Always returns 200 — never leaks whether the account exists.
     */
    public function forgot(array $params = []): void
    {
        $email = trim((string) $this->input('email', ''));

        /** @var PasswordResetService $resetService */
        $resetService   = $this->container->get('password_reset');
        $result         = $resetService->initiate($email);

        // Always return 200 — do not reveal whether the account exists.
        if ($result !== null) {
            $resetUrl = '/auth/reset-password?token=' . urlencode($result['selector']);
            $resetService->sendEmail(
                $result['email'],
                $result['display_name'],
                $resetUrl,
                'noreply@localhost',
                'Kernel-Web'
            );
        }

        $this->json(['success' => true]);
    }

    /**
     * GET /auth/forgot-password/sent — "check your email" confirmation.
     */
    public function forgotSent(array $params = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

        ob_start();
        require $viewsPath . '/auth/forgot-password-sent.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    /**
     * GET /auth/reset-password?token=xxx — render the reset password form.
     */
    public function resetForm(array $params = []): void
    {
        $token = (string) $this->input('token', '');

        if ($token === '') {
            $this->json(['error' => 'Missing reset token'], 400);
            return;
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

        ob_start();
        require $viewsPath . '/auth/reset-password.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    /**
     * POST /auth/reset-password — complete the password reset.
     *
     * Validates the token, updates the password, revokes the token
     * and all remember tokens.
     */
    public function reset(array $params = []): void
    {
        $token  = (string) $this->input('token', '');
        $password = (string) $this->input('password', '');
        $confirm  = (string) $this->input('password_confirm', '');

        if ($token === '') {
            $this->json(['error' => 'Missing reset token'], 400);
            return;
        }

        if (strlen($password) < 8 || $password !== $confirm) {
            $this->json(['error' => 'Passwords do not match or are too short.'], 400);
            return;
        }

        /** @var PasswordResetService $resetService */
        $resetService = $this->container->get('password_reset');
        $validated    = $resetService->validate($token);

        if ($validated === null) {
            $this->json(['error' => 'Invalid or expired reset link.'], 401);
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $resetService->completeReset(
            $validated['user']['id'],
            $passwordHash,
            $validated['token_id']
        );

        // Revoke all remember tokens for this user.
        $authService = $this->container->get('auth');
        $rememberService = $authService->getRememberMeService();
        if ($rememberService !== null) {
            $rememberService->revokeAll($validated['user']['id']);
        }

        $this->json(['success' => true]);
    }

    // -------------------------------------------------------------------
    // Email Verification
    // -------------------------------------------------

    /**
     * GET /auth/verify — show pending-verification banner when user is logged in
     * but has not verified their email.
     */
    public function verifyBanner(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        // Already verified — no banner needed
        if (!empty($user['email_verified_at'])) {
            $this->json(['verified' => true]);
            return;
        }

        $this->json([
            'verified'     => false,
            'email'        => (string) ($user['email'] ?? ''),
            'display_name' => (string) ($user['display_name'] ?? ''),
        ]);
    }

    /**
     * GET /auth/verify/email — validate token and mark email as verified.
     */
    public function verifyEmail(array $params = []): void
    {
        $token = (string) $this->input('token', '');

        if ($token === '') {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');

            $config    = $this->container->get('config');
            $appName   = $config['name'] ?? 'Kernel-Web';
            $viewsPath = __DIR__ . '/../Views';

            ob_start();
            require $viewsPath . '/auth/verify-invalid.php';
            $content = ob_get_clean();

            require $viewsPath . '/layouts/blank.php';
            return;
        }

        /** @var EmailVerificationService $verifyService */
        $verifyService = $this->container->get('email_verification');
        $validated     = $verifyService->validate($token);

        if ($validated === null) {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');

            $config    = $this->container->get('config');
            $appName   = $config['name'] ?? 'Kernel-Web';
            $viewsPath = __DIR__ . '/../Views';

            ob_start();
            require $viewsPath . '/auth/verify-expired.php';
            $content = ob_get_clean();

            require $viewsPath . '/layouts/blank.php';
            return;
        }

        // Success — show verified page
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

        ob_start();
        require $viewsPath . '/auth/verify-success.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    /**
     * POST /auth/verify/resend — resend verification email.
     */
    public function resendVerification(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        if (!empty($user['email_verified_at'])) {
            $this->json(['success' => true, 'already_verified' => true]);
            return;
        }

        /** @var EmailVerificationService $verifyService */
        $verifyService = $this->container->get('email_verification');
        $result        = $verifyService->resend($user['email']);

        if ($result === null) {
            // Enumeration-safe: always report success even if the account
            // doesn't exist or is already verified.
            $this->json(['success' => true]);
            return;
        }

        $verifyUrl = '/auth/verify/email?token=' . urlencode($result['selector']);
        $sent = $verifyService->sendEmail(
            $result['email'],
            $result['display_name'],
            $verifyUrl,
            'noreply@localhost',
            'Kernel-Web'
        );

        $this->json(['success' => true, 'sent' => $sent]);
    }

    // -------------------------------------------------------------------
    // User Registration
    // -------------------------------------------------------------------

    /**
     * GET /auth/register — render the registration form.
     */
    public function registerForm(array $params = []): void
    {
        $config = $this->container->get('config');
        $regConfig = $config['auth']['registration'] ?? [];

        // Disabled — return 404
        if (!($regConfig['enabled'] ?? false)) {
            http_response_code(404);
            exit;
        }

        // Already authenticated — send to home
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        if ($auth->check()) {
            header('Location: /', true, 302);
            exit;
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';
        $requireVerification = $regConfig['require_email_verification'] ?? true;

        ob_start();
        require $viewsPath . '/auth/register.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    /**
     * POST /auth/register — create a new account.
     *
     * Validates fields, creates user, optionally sends verification email,
     * and either auto-logs the user in or redirects to success page.
     */
    public function register(array $params = []): void
    {
        $config = $this->container->get('config');
        $regConfig = $config['auth']['registration'] ?? [];

        // Disabled — return 404
        if (!($regConfig['enabled'] ?? false)) {
            $this->json(['error' => 'Registration is not available'], 404);
            return;
        }

        $displayName = trim((string) $this->input('display_name', ''));
        $username    = trim((string) $this->input('username', ''));
        $email       = trim((string) $this->input('email', ''));
        $password    = (string) $this->input('password', '');
        $passwordConfirm = (string) $this->input('password_confirm', '');

        // Keyed field errors
        $errors = [];

        if (strlen($displayName) < 1 || strlen($displayName) > 100) {
            $errors['display_name'] = 'Display name is required and must be 100 characters or fewer.';
        }

        if (strlen($username) < 3 || strlen($username) > 64 || !preg_match('/^[a-zA-Z0-9-]+$/', $username)) {
            $errors['username'] = 'Username must be 3–64 characters and contain only letters, numbers, and hyphens.';
        }

        if (strlen($email) < 3 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }

        if (strlen($password) < 8 || $password !== $passwordConfirm) {
            $errors['password'] = 'Passwords must match and be at least 8 characters.';
        }

        if (empty($errors)) {
            /** @var UserRepository $userRepo */
            $userRepo = $this->container->get('user_repo');

            // Check duplicates — safe: same generic error regardless of which field is taken
            if ($userRepo->isUsernameTaken($username) || $userRepo->isEmailTaken($email)) {
                $errors['account'] = 'A user with that username or email already exists.';
            }
        }

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        $requireVerification = $regConfig['require_email_verification'] ?? true;
        $isRegistered = true;

        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $userId = $userRepo->create([
                'display_name' => $displayName,
                'username'     => $username,
                'email'        => $email,
                'password_hash' => $passwordHash,
                'is_active'    => 1,
            ]);

            // Send verification email if required
            if ($requireVerification) {
                // Set email_verified_at = NULL explicitly so EmailVerificationService knows
                // the user is unverified
                $emailVerificationService = $this->container->get('email_verification');
                $verifyService = new \App\Auth\EmailVerificationService(
                    new \App\Models\EmailVerificationRepository($this->container->get('db')),
                    $userRepo,
                    $emailVerificationService !== null ? $emailVerificationService : null
                );

                // Generate token for this user
                $tokenResult = $verifyService->generate($userId);

                if ($tokenResult !== null) {
                    // Resend will revoke the generated token and create a fresh one
                    // — that's fine, we just need a token in the DB
                    // Actually: generate() already sent the email in some flows.
                    // The EmailVerificationService doesn't auto-send. We send manually.
                    $verifyService->sendEmail(
                        $email,
                        $displayName,
                        '/auth/verify/email?token=' . urlencode($tokenResult['selector']),
                        'noreply@localhost',
                        $config['name'] ?? 'Kernel-Web'
                    );
                    $isRegistered = false; // Token generated — user will verify later
                }
            }
        } catch (\RuntimeException $e) {
            // DB constraint violation (shouldn't reach here since we check duplicates first)
            $this->json(['errors' => ['account' => 'Registration failed. Please try again.']], 500);
            return;
        }

        // Auto-login if configured
        $autoLogin = $regConfig['auto_login'] ?? true;
        $redirect  = $regConfig['redirect'] ?? '/';

        if ($autoLogin && $isRegistered) {
            /** @var AuthService $auth */
            $auth = $this->container->get('auth');
            $auth->login([
                'identity' => $username,
                'password' => $password,
            ]);

            $this->json([
                'success' => true,
                'redirect' => $redirect,
                'user' => $auth->user(),
            ]);
            return;
        }

        // Verification required — show success page
        $this->json([
            'success' => true,
            'requires_verification' => true,
        ]);
    }

    /**
     * GET /auth/register/sent — "check your email" confirmation.
     */
    public function registerSent(array $params = []): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

        ob_start();
        require $viewsPath . '/auth/register-success.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    // -------------------------------------------------------------------
    // Two-Factor Authentication
    // -----------------------------------------------

    /**
     * GET /auth/2fa — render the 2FA code form.
     *
     * Shown when login credentials are valid but the user has 2FA enabled.
     * A pending 2FA state must exist in the session.
     */
    public function twoFactorForm(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');

        // Start the session (auto-kills zombie) so we can inspect it.
        $hasPending = $auth->hasPendingTwoFactor();
        $hasAccess  = $auth->hasPendingTwoFactorAccess();

        // Must have either pending 2FA state or pending 2FA access (from SessionAuth).
        if (!$hasPending && !$hasAccess) {
            // Show a redirect reason panel.
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#e0e0e0;}h1{color:#f87171;}table{border-collapse:collapse;margin:10px 0;}th,td{border:1px solid #333;padding:8px;text-align:left;}th{background:#16213e;color:#f87171;}a{color:#00d2ff;}</style></head><body>';
            echo "<h1>Redirect to /signin</h1>";
            echo "<table><tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>hasPendingTwoFactor()</td><td>" . ($hasPending ? 'YES' : 'NO') . "</td></tr>";
            echo "<tr><td>hasPendingTwoFactorAccess()</td><td>" . ($hasAccess ? 'YES' : 'NO') . "</td></tr>";
            echo "<tr><td>session_name()</td><td>" . session_name() . "</td></tr>";
            echo "<tr><td>session_id()</td><td>" . htmlspecialchars(session_id()) . "</td></tr>";
            echo "<tr><td>session_status()</td><td>" . session_status() . "</td></tr>";
            echo "<tr><td>session_save_path()</td><td>" . htmlspecialchars(ini_get('session.save_path')) . "</td></tr>";
            echo "<tr><td>\$_SESSION keys</td><td>" . implode(', ', array_keys($_SESSION)) . "</td></tr>";
            echo "<tr><td>\$_COOKIE['PHPSESSID']</td><td>" . htmlspecialchars($_COOKIE['PHPSESSID'] ?? '(not set)') . "</td></tr>";
            echo "<tr><td>\$_COOKIE['kernel_web_session']</td><td>" . htmlspecialchars($_COOKIE['kernel_web_session'] ?? '(not set)') . "</td></tr>";
            echo "</table>";
            echo '<p><a href="' . htmlspecialchars($_SERVER['REQUEST_URI'] . '?_debug_session=1') . '">→ Full session debug</a></p>';
            echo '</body></html>';
            exit;
        }

        $userId = $auth->getPendingTwoFactorUserId();
        // Get user from pending 2FA state (not full session, since user_id is not set yet).
        $user = $userId !== null ? $auth->provider()->getUserById($userId) : null;

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

        // Full session debug panel (via ?_debug_session=1).
        $debugSession = $_GET['_debug_session'] ?? $_POST['_debug_session'] ?? null;
        if ($debugSession !== null) {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            $sid = session_id();
            $sname = session_name();
            $sstatus = session_status();
            $sstatusStr = match($sstatus) {
                PHP_SESSION_NONE => 'NONE (1)',
                PHP_SESSION_ACTIVE => 'ACTIVE (2)',
                default => 'UNKNOWN (' . $sstatus . ')',
            };
            echo '<!DOCTYPE html><html><head><style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#e0e0e0;}h1{color:#00d2ff;}table{border-collapse:collapse;margin:10px 0;}th,td{border:1px solid #333;padding:8px;text-align:left;}th{background:#16213e;color:#00d2ff;}.ok{color:#4ade80;}.bad{color:#f87171;}.warn{color:#fbbf24;}</style></head><body>';
            echo "<h1>2FA Session Debug — /auth/2fa</h1>";
            echo "<h2>Session Info</h2>";
            echo "<table><tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>session_name()</td><td>" . htmlspecialchars($sname) . "</td></tr>";
            echo "<tr><td>session_id()</td><td>" . htmlspecialchars($sid ?: '(empty)') . "</td></tr>";
            echo "<tr><td>session_status()</td><td class=" . ($sstatus === PHP_SESSION_ACTIVE ? '"ok"' : '"bad"') . ">" . $sstatusStr . "</td></tr>";
            echo "<tr><td>session_save_path()</td><td>" . htmlspecialchars(ini_get('session.save_path')) . "</td></tr>";
            echo "</table>";
            echo "<h2>\$_SESSION Contents</h2>";
            echo "<table><tr><th>Key</th><th>Value</th></tr>";
            if (count($_SESSION) === 0) echo "<tr><td colspan='2' class='bad'>EMPTY</td></tr>";
            foreach ($_SESSION as $k => $v) echo "<tr><td>" . htmlspecialchars($k) . "</td><td>" . htmlspecialchars(is_array($v) ? json_encode($v) : $v) . "</td></tr>";
            echo "</table>";
            echo "<h2>Auth Service State</h2>";
            echo "<table><tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>hasPendingTwoFactor()</td><td class=" . ($auth->hasPendingTwoFactor() ? '"ok"' : '"bad"') . ">" . ($auth->hasPendingTwoFactor() ? 'YES' : 'NO') . "</td></tr>";
            echo "<tr><td>hasPendingTwoFactorAccess()</td><td class=" . ($auth->hasPendingTwoFactorAccess() ? '"ok"' : '"warn"') . ">" . ($auth->hasPendingTwoFactorAccess() ? 'YES' : 'NO') . "</td></tr>";
            echo "<tr><td>getPendingTwoFactorUserId()</td><td>" . ($auth->getPendingTwoFactorUserId() ?? 'null') . "</td></tr>";
            echo "<tr><td>hasTwoFactorEnabled($userId)</td><td class=" . ($auth->hasTwoFactorEnabled($userId) ? '"ok"' : '"bad"') . ">" . ($auth->hasTwoFactorEnabled($userId) ? 'YES' : 'NO') . "</td></tr>";
            $loggedUser = $auth->user();
            echo "<tr><td>auth->user() (logged-in)</td><td class=" . ($loggedUser !== null ? '"ok"' : '"bad"') . ">" . ($loggedUser !== null ? 'user_id=' . $loggedUser['id'] : '(null)') . "</td></tr>";
            echo "</table>";
            echo "<h2>Cookie Info</h2>";
            echo "<table><tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>\$_COOKIE[session_name()]</td><td>" . htmlspecialchars($_COOKIE[$sname] ?? '(not set)') . "</td></tr>";
            echo "<tr><td>\$_COOKIE['PHPSESSID']</td><td>" . htmlspecialchars($_COOKIE['PHPSESSID'] ?? '(not set)') . "</td></tr>";
            echo "</table>";
            echo "<h2>Session File</h2>";
            $sdir = ini_get('session.save_path') ?: '/tmp';
            $sessFile = "$sdir/sess_" . $sname;
            echo "<table><tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>Session file</td><td>" . htmlspecialchars($sessFile) . "</td></tr>";
            if (file_exists($sessFile)) {
                $sz = filesize($sessFile);
                echo "<tr><td>File exists</td><td class='ok'>YES (size=$sz)</td></tr>";
                echo "<tr><td>File content (first 200)</td><td>" . htmlspecialchars(substr(file_get_contents($sessFile), 0, 200)) . "</td></tr>";
            } else {
                echo "<tr><td>File exists</td><td class='bad'>NO</td></tr>";
            }
            echo "</table>";
            echo "<h2>Notes</h2>";
            echo "<table><tr><th>Check</th><th>Result</th></tr>";
            if ($sstatus !== PHP_SESSION_ACTIVE) echo "<tr><td class='bad'>Session NOT active</td><td class='bad'>PHP thinks no session is running</td></tr>";
            if (count($_SESSION) === 0) echo "<tr><td class='warn'>Session empty</td><td class='warn'>$_SESSION has no keys</td></tr>";
            if (!isset($_COOKIE[$sname])) echo "<tr><td class='warn'>No session cookie</td><td class='warn'>Browser missing cookie for '$sname'</td></tr>";
            if (isset($_COOKIE['PHPSESSID']) && $_COOKIE[$sname] !== $_COOKIE['PHPSESSID']) echo "<tr><td class='bad'>Cookie mismatch!</td><td class='bad'>PHPSESSID != $sname</td></tr>";
            if (!file_exists($sessFile)) echo "<tr><td class='bad'>Session file missing</td><td class='bad'>Data not persisted</td></tr>";
            echo "</table>";
            echo '<p><a href="' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/auth/2fa') . '">← Back to 2FA form</a></p>';
            echo '<p><a href="' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/auth/2fa') . '?_debug_session=1">⟳ Refresh debug</a></p>';
            echo '</body></html>';
            exit;
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        ob_start();
        require $viewsPath . '/auth/two-factor.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/blank.php';
    }

    /**
     * POST /auth/2fa — verify TOTP code or recovery code.
     *
     * On success: promotes pending 2FA to full session.
     * On failure: returns 401 JSON with generic error.
     */
    public function twoFactor(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');

        // Must have either pending 2FA state or pending 2FA access (from SessionAuth).
        if (!$auth->hasPendingTwoFactor() && !$auth->hasPendingTwoFactorAccess()) {
            header('Location: /signin', true, 302);
            exit;
        }

        $userId = $auth->getPendingTwoFactorUserId();
        if ($userId === null) {
            $this->json(['error' => 'Invalid session'], 401);
            return;
        }

        $code = trim((string) $this->input('code', ''));
        $type = (string) $this->input('type', 'totp'); // 'totp' or 'recovery'

        /** @var TwoFactorService $twoFactor */
        $twoFactor = $this->container->get('two_factor');

        // Collect debug info for failed verification
        $debugTrace = null;
        if (!empty($_GET['_debug_session'])) {
            $debugTrace = [
                'time' => date('Y-m-d H:i:s'),
                'userId' => $userId,
                'code' => $code,
                'code_hex' => bin2hex($code),
                'code_len' => strlen($code),
                'type' => $type,
            ];
            $secret = $twoFactor->getSecretForDebug($userId);
            if ($secret !== null) {
                $debugTrace['secret'] = $secret;
                $debugTrace['secret_hex'] = bin2hex($secret);
            }
        }

        $verified = false;

        if ($type === 'recovery') {
            $verified = $twoFactor->validateRecoveryCode($userId, $code);
        } else {
            $verified = $twoFactor->verifyTotp($userId, $code);
        }

        if ($debugTrace !== null) {
            $debugTrace['verified'] = $verified;
            file_put_contents(
                __DIR__ . '/../../storage/logs/2fa_verify_trace.log',
                json_encode($debugTrace) . "\n",
                FILE_APPEND | LOCK_EX
            );
        }

        if (!$verified) {
            if ($debugTrace !== null) {
                $this->json(['error' => 'Invalid code. Please try again. (DEBUG trace: storage/logs/2fa_verify_trace.log)', 'debug_trace' => $debugTrace], 401);
            } else {
                $this->json(['error' => 'Invalid code. Please try again.'], 401);
            }
            return;
        }

        // Verify user is still active (get from pending 2FA state, not full session).
        $user = $auth->provider()->getUserById($userId);
        if ($user === null || !$user['is_active']) {
            $this->json(['error' => 'Account is not active.'], 401);
            return;
        }

        DebugAuditLogger::auth($userId, 'login.2fa_verified', ['type' => $type]);

        $remember = (bool) $this->input('remember', '0');
        $auth->completeTwoFactor($remember);

        $this->json(['success' => true]);
    }

    // ------ Profile Modal 2FA endpoints (SessionAuth) ------

    /**
     * GET /api/profile/2fa/status
     */
    public function profileTwoFactorStatus(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        /** @var TwoFactorService $twoFactor */
        $twoFactor = $this->container->get('two_factor');
        $enabled   = $twoFactor->isEnabled($user['id']);
        $pending   = $enabled ? false : $twoFactor->hasPendingSetup($user['id']);

        $this->json([
            'enabled' => $enabled,
            'pending' => $pending,
        ]);
    }

    /**
     * POST /api/profile/2fa/generate
     */
    public function profileTwoFactorGenerate(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        /** @var TwoFactorService $twoFactor */
        $twoFactor = $this->container->get('two_factor');
        /** @var array<string, mixed> $config */
        $config = $this->container->get('config');

        // Accept optional setup_id from frontend. If absent, generate one.
        $setupId = (string) ($this->input('setupId', $_GET['setupId'] ?? ''));
        if ($setupId === '') {
            $setupId = bin2hex(random_bytes(16));
        }

        $result = $twoFactor->generateSecretFull($user['id'], $setupId);

        if ($result === null) {
            $this->json(['error' => 'User not found'], 404);
            return;
        }

        // APP_DEBUG diagnostic — safe: sha256 hashes only.
        $debug = [];
        if ($config['debug'] ?? false) {
            $pendingInfo = $twoFactor->getPendingSetupInfo($user['id']);
            $debug = [
                '_debug' => [
                    'user_id'                  => $user['id'],
                    'session_id'               => session_id() ?: 'none',
                    'generated_secret_sha256'  => substr(hash('sha256', $result['secret']), 0, 16),
                    'setup_id'                 => $result['setupId'],
                    'pending_at'               => $pendingInfo['pendingAt'] ?? null,
                    'pending_secret_sha256'    => $pendingInfo ? substr(hash('sha256', $pendingInfo['secret']), 0, 16) : null,
                ],
            ];
        }

        $this->json(array_merge([
            'secret'    => $result['secret'],
            'uri'       => $result['uri'],
            'pending'   => true,
            'setupId'   => $result['setupId'],
        ], $debug));
    }

    /**
     * POST /api/profile/2fa/enable
     */
    public function profileTwoFactorEnable(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        $code = (string) $this->input('code', '');

        /** @var TwoFactorService $twoFactor */
        $twoFactor = $this->container->get('two_factor');
        /** @var array<string, mixed> $config */
        $config = $this->container->get('config');

        // Accept optional setup_id from frontend for stale-QR detection.
        $setupId = (string) ($this->input('setupId', $_GET['setupId'] ?? ''));
        if ($setupId === '') {
            $setupId = null;
        }

        // Accept optional ui_secret from frontend for APP_DEBUG diagnosis.
        $uiSecret = (string) ($this->input('uiSecret', $_GET['uiSecret'] ?? ''));

        // Setup flow: use ±10 window to accommodate time drift during initial setup.
        $debug = $config['debug'] ?? false;

        $secretData = $twoFactor->getPendingSetupInfo($user['id']);
        $pendingSetupId = $secretData['setupId'] ?? null;
        $pendingSecret = $secretData['secret'] ?? null;
        $pendingAt = $secretData['pendingAt'] ?? 'never';
        $enabledAt = $twoFactor->isEnabled($user['id']) ? 'enabled' : 'none';

        // Only enforce setup_id binding if the stored pending secret has one
        // (backward compatible with old pending secrets that lack setup_id).
        $match = null;
        if ($pendingSetupId !== null && $pendingSetupId !== '') {
            $match = $twoFactor->verifyTotpWithSetupCheck($user['id'], $code, 10, $pendingSetupId);
        }
        if ($match === null) {
            $match = $twoFactor->verifyTotpWithInfo($user['id'], $code, 10);
        }

        // Build debug info.
        $debugInfo = null;
        if ($debug || $match === null) {
            $pendingSecretHash = $pendingSecret ? substr(hash('sha256', $pendingSecret), 0, 16) : 'none';
            $uiSecretHash = $uiSecret ? substr(hash('sha256', $uiSecret), 0, 16) : 'none';

            // Compute current TOTP for pending DB secret.
            $pendingTotp = null;
            if ($pendingSecret !== null) {
                $pendingTotpInfo = $twoFactor->verifyTotpWithInfo($user['id'], $code, 10);
                if ($pendingTotpInfo !== null) {
                    $pendingTotp = $pendingTotpInfo['code'];
                }
            }

            // Compute TOTP for UI secret if provided.
            $uiTotp = null;
            if ($uiSecret !== '' && $pendingSecret !== null) {
                $ref = new \ReflectionClass($twoFactor);
                $dec = $ref->getMethod('decodeBase32');
                $hex = $dec->invoke($twoFactor, $uiSecret);
                if ($hex !== false && strlen($hex) > 0) {
                    $bin = hex2bin($hex);
                    if ($bin !== false) {
                        $step = (int) floor(time() / 30);
                        for ($i = -10; $i <= 10; $i++) {
                            $hmac = hash_hmac('sha1', pack('N2', 0, $step + $i), $bin, true);
                            $offset = ord($hmac[19]) & 0x0F;
                            $codeNum = ((ord($hmac[$offset]) & 0x7F) << 24)
                                | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
                                | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
                                | (ord($hmac[$offset + 3]) & 0xFF);
                            $gen = str_pad((string) ($codeNum % 1000000), 6, '0', STR_PAD_LEFT);
                            if ($gen === $code) { $uiTotp = $gen; break; }
                        }
                    }
                }
            }

            $debugInfo = [
                'user_id'                => $user['id'],
                'session_id'             => session_id() ?: 'none',
                'submitted_code'         => $code,
                'submitted_code_sha256'  => substr(hash('sha256', $code), 0, 16),
                'submitted_setup_id'     => $setupId ?? 'none',
                'pending_secret_sha256'  => $pendingSecretHash,
                'pending_setup_id'       => $pendingSetupId ?? 'none',
                'pending_at'             => $pendingAt,
                'enabled_at'             => $secretData['enabledAt'] ?? 'none',
                'setup_id_match'         => ($pendingSetupId !== null && $pendingSetupId === $setupId),
                'code_matches_pending'   => $match !== null,
                'pending_secret_totp'    => $pendingTotp,
                'ui_secret_sha256'       => $uiSecretHash,
                'ui_secret_totp_match'   => $uiTotp,
            ];
        }

        if ($match === null) {
            $this->json([
                'error' => 'Invalid code. Please verify with your authenticator app.',
                '_debug' => $debugInfo,
            ], 400);
            return;
        }

        $result = $twoFactor->enable($user['id']);

        // Return raw codes as strings for frontend rendering (codes are one-time use).
        $rawCodes = array_map(function ($c) { return $c['code']; }, $result['recoveryCodes']);
        $this->json([
            'enabled'       => true,
            'recoveryCodes' => $rawCodes,
        ]);
    }

    /**
     * POST /api/profile/2fa/recovery-codes
     */
    public function profileTwoFactorRegenerateRecoveryCodes(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        /** @var TwoFactorService $twoFactor */
        $twoFactor = $this->container->get('two_factor');

        $result = $twoFactor->enable($user['id']);

        $this->json([
            'recoveryCodes' => $result['recoveryCodes'],
        ]);
    }

    /**
     * POST /api/profile/2fa/disable
     */
    public function profileTwoFactorDisable(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        /** @var TwoFactorService $twoFactor */
        $twoFactor = $this->container->get('two_factor');

        $code = (string) $this->input('code', '');

        // Allow disabling via:
        // 1. Valid TOTP code (enabled 2FA)
        // 2. Valid recovery code
        // 3. Pending secret (unconfirmed setup — no code required)
        if ($code !== '' && $twoFactor->verifyTotp($user['id'], $code)) {
            $twoFactor->disable($user['id']);
            $this->json(['enabled' => false]);
            return;
        }

        if ($code !== '' && $twoFactor->validateRecoveryCode($user['id'], $code)) {
            $twoFactor->disable($user['id']);
            $this->json(['enabled' => false]);
            return;
        }

        // If no code provided or neither TOTP nor recovery matched,
        // allow disabling if the user has a pending setup (no OTP needed).
        if ($twoFactor->hasPendingSetup($user['id'])) {
            $twoFactor->disable($user['id']);
            $this->json(['enabled' => false]);
            return;
        }

        $this->json(['error' => 'Invalid code. Please verify with your authenticator app, recovery code, or start setup fresh.'], 400);
    }

    // --------
    // GET /api/profile
    // -------------------------------------

    public function profile(array $params = []): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        if ($user === null) {
            $this->json(['error' => 'Not authenticated'], 401);
            return;
        }

        // Return only safe fields — never expose password_hash, tokens, or secrets.
        $this->json([
            'user' => [
                'id'           => (int) ($user['id'] ?? 0),
                'username'     => (string) ($user['username'] ?? ''),
                'email'        => (string) ($user['email'] ?? ''),
                'display_name' => (string) ($user['display_name'] ?? ''),
                'created_at'   => (string) ($user['created_at'] ?? ''),
                'updated_at'   => (string) ($user['updated_at'] ?? ''),
            ],
        ]);
    }
}
