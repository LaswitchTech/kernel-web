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
|--|----|-|
| Scaffold Generator | Generate a starter plugin, theme, or layout into staging for review and installation | **Implemented** — see [Scaffold Generator Design](../scaffolds.md) |
| Copy Example Code | Copy example extension code (lifecycle hooks, menu registrations, route patterns) to a new extension | Planned |
| Configure Local Repository | Configure a local repository for extension development and testing | Planned |
| Validate Manifests | Validate extension manifests (plugin.json, theme.json, layout.json) for common errors | Planned |
| Run Development Diagnostics | Check plugin loading, theme discovery, layout resolution, and config state | Planned |

## Scaffold Generator

The scaffold generator is implemented and available at `/admin/developer/scaffold`. It consolidates plugin, theme, and layout scaffolding into a single form.

### Usage

1. Navigate to **Developer Tools** → **Scaffold Generator**
2. Select scaffold type (Plugin / Theme / Layout)
3. Fill in metadata (name, slug, version, description, author)
4. Click **Generate Scaffold**
5. Review generated files in `/storage/extension-staging/{slug}/`
6. Install through the Extension Catalog or copy to `/lib/`

### Templates

Templates live in `resources/scaffolds/{type}/` with `{{variable}}` placeholders.

### Input Validation

- Slug: `^[a-z][a-z0-9-]*$`, max 63 chars
- Version: semver `\d+\.\d+\.\d+`
- Name: non-empty, max 100 chars
- Description: non-empty, max 500 chars
- Namespace: PascalCase, auto-generated from name if omitted

### Safety

- Developer mode only (`APP_DEBUG=true`)
- Admin permission required
- No overwriting existing staging directories
- No path traversal allowed
- No secrets generated

### Security

- Developer tools only appear when `debug` is `true`.
- In production, the page returns a 404 — no indication that the route exists.
- No developer tool should perform destructive operations without confirmation.
- No secrets or sensitive server paths are exposed on the page.
