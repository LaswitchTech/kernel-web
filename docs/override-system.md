# Override System

## Purpose

Kernel-Web provides a default experience but **never blocks developers** from replacing it. This document explains how to override the landing page, routes, and layouts.

---

## Route Override

### How It Works

Routes have a **priority** level. Higher priority routes are checked first. If two routes match the same path, the one with the higher priority wins.

| Source | Priority | Precedence |
|--------|----------|------------|
| Plugin routes | `1` | Highest — overrides kernel |
| Default (apps, future) | `0` | Neutral |
| Kernel core routes | `0` | Lowest — always overridable |

### Overriding a Route

Any plugin can override a kernel route by registering the same path with a higher priority:

```php
// In a plugin's routes.php or boot method:
$router->get('/', 'App\Controllers\Home\HomeController@index', [], 1);
```

The plugin route (`priority=1`) will always win over the kernel route (`priority=0`).

### Overriding the Landing Page

To replace the kernel landing page:

1. Create a plugin in `/lib/plugins/CustomHome/`
2. Add `plugin.json` with `"enabled": true`
3. Add `routes.php`:

```php
$router->get('/', 'App\Plugins\CustomHome\HomeController@index', [], 1);
```

The kernel landing page acts as a fallback. If no higher-priority route matches `/`, the kernel page is shown.

### Overriding Core Routes

Same pattern. Override any kernel route (`/`, `/dashboard`, `/install`, etc.) by registering the same path with `priority=1` in a plugin.

### Debugging Route Conflicts

If routes don't override as expected:

1. Check the priority value — higher wins
2. Check the HTTP method — must match exactly
3. Check the path pattern — must match exactly (including `{params}`)

---

## Layout Override

### How It Works

The `LayoutResolver` class resolves layout file paths in this order:

1. **App-level override**: `app/Views/layouts/{name}.php`
2. **Kernel layout**: `lib/layouts/{name}.php`
3. **Fallback**: `app.php` (kernel default)

### Overriding a Layout

To replace a kernel layout, create a file with the same name in `app/Views/layouts/`:

```
app/Views/layouts/
├── app.php        — your custom app layout (overrides kernel)
└── blank.php      — your custom blank layout
```

The app-level file is used automatically. The kernel file is only used as a fallback if no app-level override exists.

### Example: Custom App Layout

To replace the entire page structure:

1. Copy `app/Views/layouts/app.php` to `app/Views/layouts/app.php` (same location, custom edits)
2. Modify the sidebar, topbar, or any region

The LayoutResolver will automatically use your custom version.

---

## Where to Place Custom Code

### Custom Routes
- Plugin routes: `/lib/plugins/{Name}/routes.php`

### Custom Layouts
- App-level: `app/Views/layouts/{name}.php`
- Kernel-level (not for apps): `lib/layouts/{name}.php`

### Custom Controllers
- Plugin controllers: `/lib/plugins/{Name}/src/`
- App controllers: `app/Controllers/`

---

## Design Rules

- Kernel routes are always overridable (priority 0)
- Plugin routes always win over kernel (priority 1)
- Route override is deterministic (priority-based, never random)
- Layout override is explicit (file discovery order, never hidden)
