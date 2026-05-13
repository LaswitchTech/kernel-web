<?php

namespace App\Controllers;

use App\Auth\AuthService;
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
        $resetService = $this->container->get('password_reset');
        $result       = $resetService->initiate($email);

        // Always return 200 — do not reveal whether the account exists.
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
