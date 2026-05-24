<?php

namespace App\Middleware;

use App\Auth\AuthService;
use App\Core\Container;
use App\Core\Gate;
use App\Core\MiddlewareInterface;

/**
 * Requires a valid session.
 *
 * On success: writes a 'principal' to the container:
 *   [
 *     'user'        => [...],
 *     'auth_method' => 'session',
 *     'token'       => null,
 *     'permissions' => ['admin', 'users.view', ...],
 *   ]
 *
 * On failure: terminates with 401 JSON.
 */
class SessionAuth implements MiddlewareInterface
{
    private Container $container;

    public function __construct(Container $container, ?string $arg = null)
    {
        $this->container = $container;
    }

    public function handle(array $params, callable $next): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('auth');
        $user = $auth->user();

        // Allow pending 2FA users to reach the 2FA form without full session auth.
        if ($user === null && $auth->hasPendingTwoFactor()) {
            $auth->setTwoFactorPendingAccess();
            $next($params);
            return;
        }

        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        /** @var Gate $gate */
        $gate        = $this->container->get('gate');
        $permissions = $gate->permissionsForUser($user['id']);

        // System-wide 2FA enforcement: if enabled, users with 2FA must have
        // completed the 2FA challenge (full session). Pending 2FA access is
        // allowed to reach /auth/2fa.
        $enforced = ($this->container->get('config')['auth']['two_factor']['enforced'] ?? false) === true;
        if ($enforced && $auth->hasTwoFactorEnabled($user['id']) && !$auth->hasPendingTwoFactorAccess()) {
            http_response_code(302);
            header('Location: /auth/2fa');
            return;
        }

        $this->container->set('principal', [
            'user'        => $user,
            'auth_method' => 'session',
            'token'       => null,
            'permissions' => $permissions,
        ]);

        $next($params);
    }
}
