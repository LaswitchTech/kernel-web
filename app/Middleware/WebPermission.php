<?php

namespace App\Middleware;

use App\Core\Container;
use App\Core\ErrorPage;
use App\Core\Gate;
use App\Core\MiddlewareInterface;

/**
 * Browser-facing permission guard.
 *
 * Usage in route definitions:
 *   ['WebAuth', 'WebPermission:admin']
 *
 * Must run after WebAuth (which writes the 'principal' to the container).
 *
 * Unlike RequirePermission (which returns JSON), this middleware is designed
 * for HTML browser routes. It:
 *   - Redirects to /auth/login (302) if no principal is present
 *   - Returns an HTML 403 page if the principal lacks the required permission
 *
 * Use RequirePermission for AJAX / JSON API routes.
 * Use WebPermission for routes that serve HTML pages.
 */
class WebPermission implements MiddlewareInterface
{
    private Container $container;
    private string    $permission;

    public function __construct(Container $container, ?string $arg = null)
    {
        $this->container  = $container;
        $this->permission = $arg ?? '';
    }

    public function handle(array $params, callable $next): void
    {
        if ($this->permission === '') {
            http_response_code(500);
            echo '<h1>500 — WebPermission middleware has no permission configured</h1>';
            return;
        }

        if (!$this->container->has('principal')) {
            header('Location: /auth/login', true, 302);
            exit;
        }

        $principal = $this->container->get('principal');

        /** @var Gate $gate */
        $gate = $this->container->get('gate');

        if (!$gate->can($principal, $this->permission)) {
            ErrorPage::render(403, "You do not have the required permission '{$this->permission}' to view this page.");
            return;
        }

        $next($params);
    }
}
