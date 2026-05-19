<?php

namespace App\Controllers;

use App\Auth\AuthService;
use App\Auth\EmailVerificationService;
use App\Auth\TwoFactorService;
use App\Core\Controller;

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

        if (!$auth->hasPendingTwoFactor()) {
            header('Location: /signin', true, 302);
            exit;
        }

        $userId = $auth->getPendingTwoFactorUserId();
        $user   = $userId !== null ? $auth->user() : null;

        $config    = $this->container->get('config');
        $appName   = $config['name'] ?? 'Kernel-Web';
        $viewsPath = __DIR__ . '/../Views';

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

        if (!$auth->hasPendingTwoFactor()) {
            header('Location: /signin', true, 302);
            exit;
        }

        $userId = $auth->getPendingTwoFactorUserId();
        if ($userId === null) {
            $this->json(['error' => 'Invalid session'], 401);
            return;
        }

        $code = (string) $this->input('code', '');
        $type = (string) $this->input('type', 'totp'); // 'totp' or 'recovery'

        /** @var TwoFactorService $twoFactor */
        $twoFactor = $this->container->get('two_factor');

        $verified = false;

        if ($type === 'recovery') {
            $verified = $twoFactor->validateRecoveryCode($userId, $code);
        } else {
            $verified = $twoFactor->verifyTotp($userId, $code);
        }

        if (!$verified) {
            $this->json(['error' => 'Invalid code. Please try again.'], 401);
            return;
        }

        // Verify user is still active
        $user = $auth->user();
        if ($user === null || !$user['is_active']) {
            $this->json(['error' => 'Account is not active.'], 401);
            return;
        }

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
        $secret = $twoFactor->generateSecret($user['id']);

        if ($secret === null) {
            $this->json(['error' => 'User not found'], 404);
            return;
        }

        $appName = ($this->container->get('config')['name'] ?? 'Kernel-Web');
        $uri = $twoFactor->getOtpauthUri($user['id'], $appName, $user['email']);

        $this->json([
            'secret' => $secret,
            'uri'    => $uri,
            'pending' => true,
        ]);
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

        if (!$twoFactor->verifyTotp($user['id'], $code)) {
            $this->json(['error' => 'Invalid code. Please verify with your authenticator app.'], 400);
            return;
        }

        $result = $twoFactor->enable($user['id']);

        $this->json([
            'enabled'       => true,
            'recoveryCodes' => $result['recoveryCodes'],
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
