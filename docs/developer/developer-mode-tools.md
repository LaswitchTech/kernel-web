# Developer Mode Tools

## Overview

Developer mode tools provide additional administrative capabilities available only during development. They are completely hidden in production deployments.

## Enabling Developer Mode

Developer mode is controlled by the `debug` setting in `config/app.php`, which defaults to `true` when `APP_DEBUG` is set:

```php
// config/app.php
'debug' => (bool) filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
```

To enable:
- Set `APP_DEBUG=true` in your `.env` file, or
- Add to `config/local.php`:
  ```php
  return ['app' => ['debug' => true]];
  ```

To disable (production):
- Set `APP_DEBUG=false` in your `.env` file, or
- Add to `config/local.php`:
  ```php
  return ['app' => ['debug' => false]];
  ```

## Access

- **Route:** `GET /admin/developer`
- **Middleware:** `WebAuth` + `WebPermission:admin`
- **Visibility:** The Developer Tools menu item appears in the admin sidebar only when developer mode is enabled.
- **Page behavior:** When disabled, the page returns a 404 — the route and page do not exist in production.

## Planned Tools

| Tool | Description | Status |
|------|------|--------|
| Create Plugin Scaffold | Generate a new plugin directory structure with manifest, routes.php, and skeleton controllers | Planned |
| Create Theme Scaffold | Generate a new theme directory with theme.json, less/app.less, and Bootstrap token overrides | Planned |
| Create Layout Scaffold | Generate a new layout file with standard panel regions and hook points | Planned |
| Copy Example Code | Copy example extension code (lifecycle hooks, menu registrations, route patterns) to a new extension | Planned |
| Configure Local Repository | Configure a local repository for extension development and testing | Planned |
| Validate Manifests | Validate extension manifests (plugin.json, theme.json, layout.json) for common errors | Planned |
| Run Development Diagnostics | Check plugin loading, theme discovery, layout resolution, and config state | Planned |

## Security

- Developer tools only appear when `debug` is `true`.
- In production, the page returns a 404 — no indication that the route exists.
- No developer tool should perform destructive operations without confirmation.
- No secrets or sensitive server paths are exposed on the page.
