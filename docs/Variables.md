# Always-Available View Variables / Global View Context

> **Future state:** Kernel-Web is migrating to a Global View Context layer (see `DESIGN.md` § "Global View Context Design"). The current implementation uses `ViewGlobals::varsFromScope()` which relies on controller-set variables flowing into the layout scope via `get_defined_vars()`. This is fragile — controllers do not consistently set `$config`, `$auth`, or `$user` in the layout scope. The migration target is a single `ViewGlobals::globalContext($auth, $scope)` call per layout that guarantees all globals.

## Current Source

`app/Core/ViewGlobals.php` — called at the top of `<body>` in each layout:

```php
<?php use App\Core\ViewGlobals; $__devScope = get_defined_vars(); $__globals = ViewGlobals::varsFromScope($__auth, $__devScope); extract($__globals); $__devScope = null; unset($__auth); ?>
```

## Current Resolution (Fragile)

Variables flow from controllers → view → layout via PHP scope. Controllers set `$principal`, `$permissions`, `$appName`, `$displayName` in local scope. `ViewGlobals::varsFromScope()` inspects `get_defined_vars()` at the layout include point.

**User resolution order:**
1. `$scope['user']` — legacy `$user` variable (array with `id` key)
2. `$scope['principal']['user']` — controller principal
3. `$scope['currentUser']` — already-set variable
4. `$auth->user()` — AuthService fallback (when `$auth` is in scope)
5. Guest defaults

**Permissions resolution:**
1. `$scope['permissions']` — legacy `$permissions` variable
2. `$scope['principal']['permissions']` — controller principal
3. Empty array

**Known gap:** `$config` is read by controllers from the container (`$this->container->get('config')`) but never extracted into the layout scope. Partial files that depend on `$config` must defensively check for its existence. This is the root cause of the dev-tools disappearing and user menu breaking.

## Global Context (Future Intended Design)

When the migration to `globalContext()` is complete, the following globals will be **guaranteed** in every layout/partials:

### Global Objects

| Object | Type | Source | Purpose |
|--------|------|--------|---------|
| `$Kernel` | `KernelContext` | Container | Kernel services: container, plugins, hooks |
| `$Auth` | `AuthService\|null` | Container | Auth/authorization service |
| `$Config` | `array` | Container | Unified config: app, auth, mail, db, debug |
| `$Layout` | `LayoutContext` | Controller | Layout state: title, activeSection, breadcrumbs |
| `$Router` | `RequestContext` | Router | Routing info: URI, method, params, query |
| `$User` | `array\|null` | `$Auth` or guest | Logged-in user data, guest-safe |
| `$Permissions` | `array` | Principal or `[]` | User permission names |
| `$Helper` | `array` | Registry | Internal + plugin helpers |

### Global Variables

| Variable | Type | Description |
|------|------|-----------|
| `currentUser` | `array\|null` | Full user record or null |
| `currentUserId` | `int` | User ID (0 when guest) |
| `currentUsername` | `string` | Username |
| `currentUserEmail` | `string` | Email address |
| `currentUserDisplayName` | `string` | Display name (display_name → name → username → email) |
| `currentUserGroups` | `array\|null` | User's groups |
| `currentUserPrimaryGroup` | `string\|null` | Primary group name |
| `currentUserPermissions` | `array` | Permission names |
| `currentUserIsAdmin` | `bool` | admin or admin.access check |
| `appName` | `string` | Application name |
| `appConfig` | `array` | App-level config section |
| `pageTitle` | `string` | Page title |
| `breadcrumbs` | `array` | Breadcrumb data |
| `flash` | `array\|null` | Flash message data |

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
<!-- Use standardized variables (always safe — guest defaults) -->
<?= htmlspecialchars($currentUsername) ?>
<?= htmlspecialchars($currentUserDisplayName) ?>
<?= htmlspecialchars($currentUserEmail) ?>

<!-- For multiple fields, use $currentUser -->
<?php if ($currentUser !== null): ?>
    <?= htmlspecialchars($currentUser['display_name']) ?>
<?php endif; ?>

<!-- For global objects (future) -->
<?= $Config['app']['name'] ?>
<?php if ($Auth !== null): $Auth->... ?>
```

## Controller-set variables (Legacy — deprecated)

These continue to be set by controllers for backward compatibility during the migration. They flow into `ViewGlobals::varsFromScope()` via `get_defined_vars()`. Eventually these will be replaced by guaranteed globals.

| Variable | Type | Description |
|---|---|---|
| `$user` | `array\|null` | Controller's copy of the authenticated user |
| `$permissions` | `array` | Permission names from the controller's principal |
| `$displayName` | `string` | Pre-computed display name |
| `$principal` | `array` | Controller principal (`['user' => ..., 'permissions' => ...]`) |
| `$config` | `array\|null` | Config from container (INCONSISTENT — not always in scope) |

## Guest-safe defaults

All user variables are safe when no user is logged in:

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

## Migration to Global Context

The current approach is **fragile**: variables leak or drop out at each boundary (controller → view → layout). The migration target is:

1. `ViewGlobals::globalContext($auth, $scope)` — single call returning all globals
2. `$Kernel`, `$Auth`, `$Config`, `$Layout`, `$Router`, `$User`, `$Permissions`, `$Helper` guaranteed in every layout
3. Partial files access globals directly — no defensive checks needed
4. `ViewGlobals::varsFromScope()` preserved for backward compat but deprecated

See `DESIGN.md` § "Global View Context Design" for the full migration plan.

## Provider note

`currentUserGroups`, `currentUserPrimaryGroup`, and `currentUserIsAdmin` are provider/permission-dependent. For `LocalAuthProvider` without a permission system, groups and primary group are null and `isAdmin` is false. Permissions come from the controller's principal.
