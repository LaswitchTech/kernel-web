<?php

namespace App\Core;

/**
 * Extract standardized user detail variables from available sources.
 *
 * Resolution order (varsFromScope):
 *   1. $scope['user'] — legacy $user variable
 *   2. $scope['principal']['user'] — controller principal
 *   3. $scope['currentUser'] — already-set variable
 *   4. AuthService::user() — fallback (second param)
 *   5. Guest defaults
 *
 * Permissions resolution:
 *   1. $scope['permissions']
 *   2. $scope['principal']['permissions']
 *   3. Empty array
 */
class ViewGlobals
{
    /**
     * Legacy entry point — resolves from globals or AuthService.
     * Kept for compatibility but varsFromScope() is the preferred entry.
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
     * Resolve user variables from an array of scope variables (get_defined_vars result).
     *
     * This is the main entry point when called from layouts where $user, $principal,
     * $permissions may already exist in the local scope (set by controllers).
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

        if ($resolvedUser === null) {
            return self::guestDefaults();
        }

        return self::userToVars($resolvedUser, $permissions);
    }

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
