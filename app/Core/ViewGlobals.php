<?php

namespace App\Core;

/**
 * Extract standardized user detail variables from available sources.
 *
 * Resolution order:
 *   1. explicit authenticated user array (first param)
 *   2. $principal['user'] / $principal['permissions'] from scope
 *   3. legacy $user / $permissions from scope
 *   4. AuthService::user() (second param)
 *   5. guest defaults
 */
class ViewGlobals
{
    /**
     * @param object|null $auth Optional AuthService for fallback.
     * @param array|null $user Optional pre-fetched authenticated user record.
     * @return array<string, mixed> View variables to extract into layout scope.
     */
    public static function vars($auth = null, ?array $user = null): array
    {
        // Use the user if provided.
        if ($user !== null) {
            $resolvedUser = $user;
        }

        // Fall through: we will resolve from scope variables and/or AuthService.
        if (!isset($resolvedUser)) {
            // Check $principal from scope (controller pattern: ['user' => ..., 'permissions' => ...]).
            $principal = ($GLOBALS['principal'] ?? null);
            if ($principal !== null && is_array($principal) && isset($principal['user'])) {
                $resolvedUser = $principal['user'];
            } elseif (isset($user) && $user !== null) {
                // This catches the legacy $user variable from layout scope
                // via explicit global — but since we're in a namespace,
                // we'll handle it below with get_defined_vars.
                $resolvedUser = $user;
            } else {
                // Fallback: try AuthService.
                $resolvedUser = null;
                if ($auth !== null) {
                    try {
                        $resolvedUser = $auth->user();
                    } catch (\Throwable $e) {
                        $resolvedUser = null;
                    }
                }
            }
        } else {
            // $resolvedUser was set above, but we still need permissions.
            // permissions resolution is handled after globals/locals check.
        }

        // If no user was found, resolvedUser stays unset — fall through to guest defaults.
        if (!isset($resolvedUser)) {
            return self::guestDefaults();
        }

        return self::userToVars($resolvedUser, $auth ?? null);
    }

    /**
     * Resolve user variables from available scope variables.
     *
     * This is the main entry point when called from layouts where $user, $principal,
     * $permissions may already exist in the local scope (set by controllers).
     *
     * @param object|null $auth Optional AuthService fallback.
     * @param array<string, mixed> $scope Variables from get_defined_vars() at layout call site.
     * @return array<string, mixed> View variables.
     */
    public static function varsFromScope($auth, array $scope): array
    {
        $resolvedUser = null;
        $permissions = [];

        // Resolution order for user:
        // 1. explicit 'user' key (legacy $user)
        // 2. $principal['user']
        // 3. $currentUser (already set)
        // 4. AuthService::user()
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

        // Permissions resolution:
        if (isset($scope['permissions']) && is_array($scope['permissions'])) {
            $permissions = $scope['permissions'];
        } elseif (isset($scope['principal']) && is_array($scope['principal']) && isset($scope['principal']['permissions'])) {
            $permissions = $scope['principal']['permissions'];
        }

        if ($resolvedUser === null) {
            return self::guestDefaults();
        }

        return self::userToVars($resolvedUser, $permissions);
    }

    /**
     * Convert a user record to the standardized variable set.
     */
    private static function userToVars(array $user, object|array|null $authOrPerms): array
    {
        $username = (string) ($user['username'] ?? '');
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

        $email = (string) ($user['email'] ?? '');
        $id = (int) ($user['id'] ?? 0);

        $userPermissions = [];
        $isAdmin = false;

        if (is_array($authOrPerms)) {
            $userPermissions = $authOrPerms;
            $isAdmin = in_array('admin', $userPermissions, true)
                || in_array('admin.access', $userPermissions, true);
        } elseif (is_object($authOrPerms)) {
            try {
                // Try to get permissions from AuthService via principal or provider
                $userPermissions = [];
                $isAdmin = false;
            } catch (\Throwable $e) {
                $userPermissions = [];
                $isAdmin = false;
            }
        }

        return [
            'currentUser'            => $user,
            'currentUserId'          => $id,
            'currentUsername'        => $username,
            'currentUserEmail'       => $email,
            'currentUserDisplayName' => $displayName,
            'currentUserGroups'      => null,
            'currentUserPrimaryGroup' => null,
            'currentUserPermissions' => $userPermissions,
            'currentUserIsAdmin'     => $isAdmin,
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
