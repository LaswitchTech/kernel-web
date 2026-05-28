<?php

namespace App\Middleware;

use App\Core\Container;
use App\Core\MiddlewareInterface;

/**
 * Optional middleware that resolves the current user's organization context
 * and injects it into the container as 'org_scope'.
 *
 * Controllers add this to the middleware list on routes that need org scoping.
 * When active, repositories extending OrganizationScopedRepository can call
 * scopeFromContainer() to automatically apply the scope.
 *
 * Safe to use when the organizations plugin is not installed:
 * - Missing OrganizationContext class → no-op
 * - User has no org membership → org_scope = null (unscoped)
 */
class OrganizationScope implements MiddlewareInterface
{
    private Container $container;

    public function __construct(Container $container, ?string $arg = null)
    {
        $this->container = $container;
    }

    public function handle(array $params, callable $next): void
    {
        // Only resolve if the classes exist (plugin installed).
        if (!class_exists(\App\Core\OrganizationContext::class)) {
            $this->container->set('org_scope', null);
            $next($params);
            return;
        }

        $db           = $this->container->get('db');
        $memberRepo   = new \App\Models\OrganizationMemberRepository($db);
        $orgRepo      = new \App\Models\OrganizationRepository($db);

        $context      = new \App\Core\OrganizationContext($memberRepo, $orgRepo);
        $orgId        = $context->currentOrgId();

        $this->container->set('org_scope', $orgId);

        $next($params);
    }
}
