# Always-Available View Variables

These variables are extracted into every layout's scope via `ViewGlobals::varsFromScope()` at the top of `<body>`. They are available to all layouts, views, and partials.

## Source

`app/Core/ViewGlobals.php` — called at the start of each layout's `<body>`:

```php
<?php use App\Core\ViewGlobals; $__devScope = get_defined_vars(); $__globals = ViewGlobals::varsFromScope(null, $__devScope); extract($__globals); $__devScope = null; ?>
```

## Resolution order

ViewGlobals resolves the authenticated user and permissions in this order:

1. `$scope['user']` — legacy `$user` variable set by controllers (array with `id` key)
2. `$scope['principal']['user']` — `$principal` variable set by controllers
3. `$scope['currentUser']` — already-set currentUser variable
4. `$auth->user()` — AuthService (when $auth is available)
5. Guest defaults (no user)

Permissions resolution:
1. `$scope['permissions']` — legacy `$permissions` variable
2. `$scope['principal']['permissions']` — `$principal['permissions']`
3. Empty array (no permissions)

## Variables

| Variable | Type | Description |
|---|---|---|
| `currentUser` | `array\|null` | Full authenticated user record from the resolved source, or `null` if not logged in. Contains `id`, `display_name`, `username`, `email`, `is_active`, `created_at`, `updated_at`. |
| `currentUserId` | `int` | User ID (0 if not logged in) |
| `currentUsername` | `string` | Username (empty string if not logged in) |
| `currentUserEmail` | `string` | Email address (empty string if not logged in) |
| `currentUserDisplayName` | `string` | `display_name` if set, then `name`, then `username`, then `email` (empty if not logged in) |
| `currentUserGroups` | `array\|null` | User's groups (null — provider-specific, not yet available) |
| `currentUserPrimaryGroup` | `string\|null` | Primary group name (null — provider-specific, not yet available) |
| `currentUserPermissions` | `array` | Permission names from `$permissions` or `$principal['permissions']` (empty array if not logged in) |
| `currentUserIsAdmin` | `bool` | true if `admin` or `admin.access` is in permissions (false if not logged in) |

## Guest-safe defaults

All variables are safe to use when no user is logged in. The values default to:

```php
'currentUser'             => null,
'currentUserId'           => 0,
'currentUsername'         => '',
'currentUserEmail'        => '',
'currentUserDisplayName'  => '',
'currentUserGroups'       => null,
'currentUserPrimaryGroup' => null,
'currentUserPermissions'  => [],
'currentUserIsAdmin'      => false,
```

## Usage in views and partials

Always use the standardized variables instead of raw `$user` or `$displayName`:

```php
<!-- Wrong -->
<?= htmlspecialchars($user['username']) ?>

<!-- Right -->
<?= htmlspecialchars($currentUsername) ?>
```

For the full user record:

```php
<!-- Wrong -->
<?php $email = $user['email']; ?>

<!-- Right -->
<?php $email = $currentUserEmail; ?>
```

Or use `$currentUser` when you need multiple fields:

```php
<?php if ($currentUser !== null): ?>
    <?= htmlspecialchars($currentUser['display_name']) ?>
<?php endif; ?>
```

## Controller-set variables

The following variables continue to be set by controllers and are preserved for backward compatibility:

| Variable | Type | Description |
|---|---|---|
| `$user` | `array` | Controller's copy of the authenticated user (same as `$currentUser`) |
| `$permissions` | `array` | Permission names from the controller's principal |
| `$displayName` | `string` | Pre-computed display name |
| `$principal` | `array` | Controller principal (`['user' => ..., 'permissions' => ...]`) |

New code should prefer the standardized `current*` variables.

## Provider note

`currentUserGroups`, `currentUserPrimaryGroup`, and `currentUserIsAdmin` are provider/permission-dependent. For `LocalAuthProvider` without a permission system, groups and primary group are null and `isAdmin` is false. Permissions come from the controller's principal (not from AuthService).
