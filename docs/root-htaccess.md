# Root .htaccess Reference

## Purpose

The root `.htaccess` file sits at the project root (outside `/public/`) and controls how external requests are handled before they reach the application.

## Behavior

### Request Redirect

All incoming requests are redirected to `/public/` (the webroot):

```
/example  →  /public/example
```

### Exceptions

The following paths are NOT redirected:

- `/.well-known/*` — Let's Encrypt ACME, DNS-01 validation, service discovery
- `/.htaccess` — avoids infinite redirect loop

### MIME Types

| Extension | Type |
|------|------|
| `.mjs` | `application/javascript` |
| `.env` | `text/html` (blocks direct access) |

The `.env` file is served as HTML (which browsers cannot interpret) to prevent accidental exposure if the web root is misconfigured.

### Proxy Header

When `mod_headers` is available, the `Proxy` request header is unset to prevent HTTP request smuggling in reverse-proxy setups (e.g., nginx → Apache).

## Configuration

No local filesystem paths are hardcoded. All rules are relative to the server root.

For subpath deployments (e.g., `/kernel-web/`), add `RewriteBase` before the redirect rules:

```
RewriteBase /kernel-web/
```

## Requirements

- Apache with `mod_rewrite` (required for redirect)
- Apache with `mod_headers` (optional, for Proxy header removal)
- Apache with `mod_mime` (optional, for MIME type configuration)

## Security

- Blocks direct access to `.env` files by serving them as HTML
- The `/public/` directory's `.htaccess` handles all other sensitive file protection
- Directory listing is disabled (`Options -Indexes`)
