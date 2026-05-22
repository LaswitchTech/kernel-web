# Always-Available View Variables

These variables are extracted into every layout's scope via `ViewGlobals::varsFromScope()` at the top of `<body>`. They are available to all layouts, views, and partials.

## Source

`app/Core/ViewGlobals.php` — called at the start of each layout's `<body>`:

```php
<?php use App\Core\ViewGlobals; $__devScope = get_defined_vars(); $__globals = ViewGlobals::varsFromScope($__auth, $__devScope); extract($__globals); $__devScope = null; unset($__auth); ?>
```

## Variable initialization

`ViewGlobals::varsFromScope()` resolves user data by inspecting `get_defined_vars()` at the call site (the layout's scope). Variables set by controllers (via `require $viewsPath . '/layouts/...'`) are automatically captured.

Resolution order:

1. `$scope['user']` — legacy `$user` variable (array with `id` key)
2. `$scope['principal']['user']` — controller principal
3. `$scope['currentUser']` — already-set variable
4. `$auth->user()` — AuthService fallback (when `$auth` is in scope)
5. Guest defaults

Permissions resolution:

1. `$scope['permissions']` — legacy `$permissions` variable
2. `$scope['principal']['permissions']` — controller principal
3. Empty array

If no user is found, guest defaults are returned for all variables.

## Variables

| Variable | Type | Description |
|---|---|---|
| `currentUser` | `array\|null` | Full authenticated user record, or `null` if not logged in. Contains `id`, `display_name`, `username`, `email`, `is_active`, `created_at`, `updated_at`. |
| `currentUserId` | `int` | User ID (0 if not logged in) |
| `currentUsername` | `string` | Username (empty string if not logged in) |
| `currentUserEmail` | `string` | Email address (empty string if not logged in) |
| `currentUserDisplayName` | `string` | `display_name` if set, then `name`, then `username`, then `email` (empty if not logged in) |
| `currentUserGroups` | `array\|null` | User's groups (null — provider-specific, not yet available) |
| `currentUserPrimaryGroup` | `string\|null` | Primary group name (null — provider-specific, not yet available) |
| `currentUserPermissions` | `array` | Permission names (empty array if not logged in) |
| `currentUserIsAdmin` | `bool` | true if `admin` or `admin.access` in permissions |

## Guest-safe defaults

All variables are safe when no user is logged in:

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

## Usage

```php
<!-- Use standardized variables -->
<?= htmlspecialchars($currentUsername) ?>
<?= htmlspecialchars($currentUserDisplayName) ?>

<!-- For multiple fields, use $currentUser -->
<?php if ($currentUser !== null): ?>
    <?= htmlspecialchars($currentUser['display_name']) ?>
<?php endif; ?>
```

## Controller-set variables

These continue to be set by controllers for backward compatibility:

| Variable | Type | Description |
|---|---|---|
| `$user` | `array` | Controller's copy of the authenticated user |
| `$permissions` | `array` | Permission names from the controller's principal |
| `$displayName` | `string` | Pre-computed display name |
| `$principal` | `array` | Controller principal (`['user' => ..., 'permissions' => ...]`) |

## Layout initialization

Each layout ensures `$config` is available:

- Controllers set `$config` via `$this->container->get('config')` before including the layout
- `dev-tools-offcanvas.php` defensively resolves `$config` with `isset()` checks
- `$appConfig` is derived from `$config['app'] ?? $config` in the dev tools partial

## Provider note

`currentUserGroups`, `currentUserPrimaryGroup`, and `currentUserIsAdmin` are provider/permission-dependent. For `LocalAuthProvider` without a permission system, groups and primary group are null and `isAdmin` is false. Permissions come from the controller's principal.
