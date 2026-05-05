# CSS Compilation

## Purpose

Kernel-Web compiles CSS from LESS source files and serves it at `GET /css`.

## Architecture

### Hybrid Approach

| Mode | Source | How |
|------|--|---|
| Production | npm-compiled static CSS | `public/assets/css/app.css` |
| Development | Dynamic merge — static kernel CSS + parsed theme/layout/plugin LESS | `GET /css` route |

### Why Hybrid?

The kernel base LESS uses `calc(var(--css-var) / 2)` and other modern CSS
features not supported by wikimedia/less.php (lessphp). The npm/Node.js LESS
compiler handles all features correctly. The PHP layer augments the static
kernel CSS with dynamic theme/layout/plugin LESS.

## Route

| Property | Value |
|----------|-------|
| Path | `GET /css` |
| Content-Type | `text/css` |
| Middleware | None (public) |
| Controller | `CssController@show` |
| Service | `LessCompiler` |
| Cache-Control | `public, max-age=3600` |

## Load Order

1. **Kernel base CSS** — static `public/assets/css/app.css`
2. **Theme LESS** — active theme's `less/app.less`
3. **Layout LESS** — active layout's `app.less`
4. **Plugin LESS** — enabled plugin's `less/app.less`

## Kernel Base LESS Import Order

```
variables          → compile-time constants
themes/dark        → dark theme tokens (:root)
themes/light       → light theme tokens ([data-bs-theme="light"])
base               → global resets
layout             → app shell structure
bootstrap-overrides → Bootstrap component token mappings
components/*       → sidebar, topbar, cards, tables, forms, footer, admin
modules/*          → chat, file-manager
```

## Build Commands

| Command | Description |
|---------|-----|
| `npm run build:css` | Compile LESS to static CSS |
| `npm run watch:css` | Watch and rebuild on changes |

## Layout Integration

All layouts reference `/css` instead of a static file:

```html
<link rel="stylesheet" href="/css">
```

## Files

- `app/Controllers/CssController.php` — route handler
- `app/Services/LessCompiler.php` — CSS merging service
- `public/assets/less/app.less` — LESS entry point
- `public/assets/css/app.css` — compiled output (npm build)
- `storage/css/` — file cache directory
- `composer.json` — `wikimedia/less.php` dependency

## Limitations

- `calc(var(--css-var))` — not supported by lessphp (uses npm build)
- `::-webkit-scrollbar` with nested pseudo-selectors — not supported by lessphp (uses npm build)
- Dynamic theme/layout/plugin LESS is parsed via lessc and merged with static kernel CSS
