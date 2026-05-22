<?php

namespace App\Core;

/**
 * Extract standardized user detail variables and global context.
 *
 * The primary entry point is `contextFromContainer()` — it resolves ALL globals
 * from the DI container (guaranteed) + local scope (backward compat).
 *
 * `varsFromScope()` is legacy — it only resolves from get_defined_vars() and
 * is fragile because controllers do not consistently set $config, $auth, $user
 * in the layout scope. It is preserved for backward compatibility during migration.
 *
 * Resolution order (contextFromContainer):
 *   1. Container → AuthService → principal → scope user → guest defaults
 *   2. Container → config → scope config → guest defaults
 *   3. Container → principal permissions → scope permissions → empty array
 */
class ViewGlobals
{
    // ── Layout entry point: safely resolve container from scope ────

    /**
     * Resolve all globals from the scope alone, trying multiple container sources.
     *
     * This is the layout entry point. It safely resolves the container from:
     * 1. $scope['container'] — if controller sets it directly
     * 2. $scope['this']->container — if the controller is passed as $this
     * 3. $GLOBALS['container'] — if set globally
     * 4. Falls back to varsFromScope() when no container is found
     *
     * @param array $scope get_defined_vars() from the layout scope
     * @return array Extractable set of globals
     */
    public static function contextFromScope(array $scope): array
    {
        $container = null;

        // 1. Direct container key in scope
        if (array_key_exists('container', $scope) && $scope['container'] instanceof Container) {
            $container = $scope['container'];
        }

        // 2. Controller's container property ($this)
        if ($container === null && array_key_exists('this', $scope) && is_object($scope['this'])) {
            if ($scope['this'] instanceof Container) {
                $container = $scope['this'];
            } elseif (isset($scope['this']->container) && $scope['this']->container instanceof Container) {
                $container = $scope['this']->container;
            }
        }

        // 3. Global $container
        if ($container === null && isset($GLOBALS['container']) && $GLOBALS['container'] instanceof Container) {
            $container = $GLOBALS['container'];
        }

        // 4. Fallback — no container available
        if ($container === null) {
            // Extract auth from scope if present for legacy compat
            $auth = null;
            if (array_key_exists('auth', $scope) && $scope['auth'] instanceof \App\Auth\AuthService) {
                $auth = $scope['auth'];
            }
            return self::varsFromScope($auth, $scope);
        }

        return self::contextFromContainer($container, $scope);
    }

    // ── Primary entry: guaranteed context from container ────────────────

    /**
     * Resolve ALL globals from the DI container + local scope.
     *
     * This is the single entry point for layouts. Every layout calls this
     * at the top of <body> to guarantee all globals regardless of controller.
     *
     * @param \App\Core\Container $container DI container (set in index.php)
     * @param array $scope get_defined_vars() from the layout scope
     * @return array Extractable set of globals
     */
    public static function contextFromContainer(Container $container, array $scope): array
    {
        // ── Resolve $auth (may not be bound in tests) ─────────────
        $auth = null;
        if ($container->has('auth') && $container->get('auth') instanceof \App\Auth\AuthService) {
            $auth = $container->get('auth');
        }

        // ── Resolve $config (may not be bound in tests) ────────
        $config = [];
        if ($container->has('config')) {
            $c = $container->get('config');
            if (is_array($c)) {
                $config = $c;
            }
        }

        // ── Resolve $principal (if already set by middleware) ──────
        $principal = null;
        if (isset($scope['principal']) && is_array($scope['principal'])) {
            $principal = $scope['principal'];
        }

        // ── Resolve user from container or scope ───────────────────
        $resolvedUser = null;
        $permissions  = [];

        // 1. Container principal (set by WebAuth/SessionAuth middleware)
        if (isset($principal)) {
            if (isset($principal['user']) && is_array($principal['user'])) {
                $resolvedUser = $principal['user'];
            }
            if (isset($principal['permissions']) && is_array($principal['permissions'])) {
                $permissions = $principal['permissions'];
            }
        }

        // 2. Scope fallback (legacy controller-set variables)
        if ($resolvedUser === null) {
            if (isset($scope['user']) && is_array($scope['user']) && isset($scope['user']['id'])) {
                $resolvedUser = $scope['user'];
            } elseif (isset($scope['principal']) && is_array($scope['principal']) && isset($scope['principal']['user'])) {
                $resolvedUser = $scope['principal']['user'];
            } elseif (isset($scope['currentUser']) && is_array($scope['currentUser'])) {
                $resolvedUser = $scope['currentUser'];
            }
        }

        if ($permissions === [] && isset($scope['permissions']) && is_array($scope['permissions'])) {
            $permissions = $scope['permissions'];
        }

        // 3. AuthService fallback
        if ($resolvedUser === null && $auth !== null) {
            try {
                $resolvedUser = $auth->user();
            } catch (\Throwable $e) {
                $resolvedUser = null;
            }
        }

        // ── Build globals ──────────────────────────────────────────
        $userVars = ($resolvedUser !== null)
            ? self::userToVars($resolvedUser, $permissions)
            : self::guestDefaults();

        // Merge scope variables into $config (controller-set overrides container)
        if (isset($scope['config']) && is_array($scope['config'])) {
            $config = array_merge($config, $scope['config']);
        }

        // Derive $appConfig from $config['app']
        $appConfig = is_array($config['app'] ?? null) ? $config['app'] : [];

        // Resolve $appName (scope overrides container)
        $appName = ($scope['appName'] ?? '') !== ''
            ? (string) $scope['appName']
            : (($config['name'] ?? '') !== '' ? (string) $config['name'] : 'Kernel-Web');

        return array_merge(
            $userVars,
            [
                '__container'       => $container,
                '__auth'            => $auth,
                '__principal'       => $principal,
                'Auth'              => $auth,
                'auth'              => $auth,
                'Config'            => $config,
                'config'            => $config,
                'appConfig'         => $appConfig,
                'appName'           => $appName,
                'principal'         => $principal,
            ]
        );
    }

    // ── Legacy entry points (backward compat during migration) ─────────

    /**
     * Legacy entry point — resolves from globals or AuthService.
     */
    public static function vars($auth = null, ?array $user = null): array
    {
        if ($user !== null) {
            $resolvedUser = $user;
        } else {
            $resolvedUser = null;
            if ($auth !== null) {
                try {
                    $resolvedUser = $auth->user();
                } catch (\Throwable $e) {
                    $resolvedUser = null;
                }
            }
        }

        if (!isset($resolvedUser)) {
            return self::guestDefaults();
        }

        return self::userToVars($resolvedUser, []);
    }

    /**
     * Legacy: resolve from scope only (fragile — controllers may not set
     * $config, $auth, or $user in the layout scope).
     *
     * Use contextFromContainer() instead.
     */
    public static function varsFromScope($auth, array $scope): array
    {
        $resolvedUser = null;
        $permissions  = [];

        // Resolution order for user.
        if (isset($scope['user']) && is_array($scope['user']) && isset($scope['user']['id'])) {
            $resolvedUser = $scope['user'];
        } elseif (isset($scope['principal']) && is_array($scope['principal']) && isset($scope['principal']['user'])) {
            $resolvedUser = $scope['principal']['user'];
        } elseif (isset($scope['currentUser']) && is_array($scope['currentUser'])) {
            $resolvedUser = $scope['currentUser'];
        } elseif ($auth !== null) {
            try {
                $resolvedUser = $auth->user();
            } catch (\Throwable $e) {
                // AuthService not ready — fall through to guest defaults
            }
        }

        // Permissions resolution.
        if (isset($scope['permissions']) && is_array($scope['permissions'])) {
            $permissions = $scope['permissions'];
        } elseif (isset($scope['principal']) && is_array($scope['principal']) && isset($scope['principal']['permissions'])) {
            $permissions = $scope['principal']['permissions'];
        }

        $userVars = ($resolvedUser !== null)
            ? self::userToVars($resolvedUser, $permissions)
            : self::guestDefaults();

        // Merge scope variables so layout-level vars (appName, Config, etc.)
        // are always available in the extracted context.
        $scopeVars = [];
        foreach ($scope as $k => $v) {
            if (!is_object($v) && !is_resource($v)) {
                $scopeVars[$k] = $v;
            }
        }

        // Ensure $appName always has a default.
        if (($scopeVars['appName'] ?? '') === '') {
            $scopeVars['appName'] = 'Kernel-Web';
        }

        // Ensure $appConfig always exists so dev-tools guard doesn't fail.
        if (!isset($scopeVars['appConfig']) || !is_array($scopeVars['appConfig'])) {
            $scopeVars['appConfig'] = [];
        }

        return array_merge($userVars, $scopeVars);
    }

    // ── Internal helpers ───────────────────────────────────────────────

    /**
     * Convert a user record to the standardized variable set.
     */
    private static function userToVars(array $user, array $permissions): array
    {
        $username   = (string) ($user['username'] ?? '');
        $displayName = '';

        // Prefer display_name, then name, then username, then email.
        if (!empty($user['display_name'])) {
            $displayName = (string) $user['display_name'];
        } elseif (!empty($user['name'])) {
            $displayName = (string) $user['name'];
        }

        if ($displayName === '') {
            $displayName = $username;
        }

        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? '');
        }

        $email   = (string) ($user['email'] ?? '');
        $id      = (int) ($user['id'] ?? 0);
        $isAdmin = in_array('admin', $permissions, true)
                || in_array('admin.access', $permissions, true);

        return [
            'currentUser'             => $user,
            'currentUserId'           => $id,
            'currentUsername'         => $username,
            'currentUserEmail'        => $email,
            'currentUserDisplayName'  => $displayName,
            'currentUserGroups'       => null,
            'currentUserPrimaryGroup' => null,
            'currentUserPermissions'  => $permissions,
            'currentUserIsAdmin'      => $isAdmin,
        ];
    }

    /**
     * Guest-safe defaults when no user is authenticated.
     */
    private static function guestDefaults(): array
    {
        return [
            'currentUser'             => null,
            'currentUserId'           => 0,
            'currentUsername'         => '',
            'currentUserEmail'        => '',
            'currentUserDisplayName'  => '',
            'currentUserGroups'       => null,
            'currentUserPrimaryGroup' => null,
            'currentUserPermissions'  => [],
            'currentUserIsAdmin'      => false,
        ];
    }
}
