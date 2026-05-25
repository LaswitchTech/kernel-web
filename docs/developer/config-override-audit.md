# Config Override Audit

**Date:** 2026-05-24
**Author:** Claude (audit)
**Scope:** All SystemSettingService / SystemSettingRepository / SettingsRegistry save usages

## Purpose

Audit every usage of the database-backed `SystemSettingService` and `SystemSettingRepository` to classify whether each setting should be migrated to the file-backed `ConfigOverrideService` (config/local.php) or can remain in the DB as runtime/user/plugin state.

**Rule:** Application configuration (defaults, identity, instance settings) belongs in `config/local.php`. Runtime/user data (preferences, per-user state) belongs in the DB.

---

## Audit Table

| File | Key(s) | Current Storage | Classification | Recommended Action |
|------|--------|-----------------|----------------|--------------------|
| `app/Controllers/Admin/SystemSettingsController.php` | `app.name`, `app.url` | DB via `SystemSettingService` | **CONFIG** | Move writes to `ConfigOverrideService` |
| `app/Controllers/Admin/SystemSettingsController.php` | `developer.developer`, `developer.debug`, `developer.dev_console` | DB via `SettingsRegistry::saveSection` | **CONFIG** | Move to `ConfigOverrideService` |
| `app/Core/SettingsRegistry.php` | All plugin keys | DB via `SystemSettingService` (required param) | **PLUGIN SETTING** | Refactor to accept `ConfigOverrideService` or interface for config-type sections; DB remains for runtime-type sections |
| `app/Services/SystemSettingService.php` | `app.name`, `app.url` (KNOWN_KEYS fallback chain) | DB → config/local.php → .env → hardcoded default | **CONFIG** | Deprecate. `ConfigOverrideService` replaces write path. Read path (DB → config → env → default) is legacy. |
| `app/Models/SystemSettingRepository.php` | `system_settings` table (raw CRUD) | `system_settings` DB table | **LEGACY** | Remove when all callers migrated. Keep table for plugin runtime state. |
| `lib/plugins/smtp/src/SmtpSettings.php` | `smtp.host`, `smtp.port`, `smtp.encryption`, `smtp.user`, `smtp.pass`, `smtp.verify_peer`, `smtp.from_address`, `smtp.from_name` | DB via `SystemSettingService` | **PLUGIN SETTING** | Migrate to `ConfigOverrideService` for non-sensitive (host/port/encryption), but sensitive creds (user/pass) should use a plugin-managed secret store (file or encrypted DB) |
| `lib/plugins/telico/src/TelicoSettings.php` | `telico.username`, `telico.sms_password`, `telico.caller_id` | DB via `SystemSettingService` | **PLUGIN SETTING** | Sensitive creds → encrypted secret store. Non-sensitive → `ConfigOverrideService` |
| `lib/plugins/smtp/src/SmtpHooks.php` | `SmtpTransport::loadSettings()` reads from `SystemSettingService` | DB | **PLUGIN SETTING** | Update to read from `ConfigOverrideService` for non-sensitive keys; keep DB for encrypted creds |
| `lib/plugins/telico/src/TelicoHooks.php` | `TelicoTransport::loadSettings()` reads from `SystemSettingService` | DB | **PLUGIN SETTING** | Update to read from `ConfigOverrideService` for non-sensitive keys; keep DB for encrypted creds |

---

## Classification Definitions

### CONFIG
Application-level settings that are instance-wide and should be file-backed (config/local.php). Examples:
- `app.name`, `app.url` — application identity
- `developer.*` — feature flags
- `auth.*` — auth configuration
- `mail.default_transport` — mailer configuration

These should NEVER be stored in the DB. The DB is not version-controlled, not portable, and not recoverable.

### RUNTIME STATE
Per-user or per-session data that changes at runtime and has no meaning outside the application context. Examples:
- User preferences (per-user)
- Plugin runtime state (per-plugin, per-instance)
- Temporary settings (caches, feature toggles active in current session)

These are valid DB usages.

### PLUGIN SETTING
Plugin-provided configuration that should be file-backed for non-sensitive values and use a plugin-managed secret store for sensitive values (passwords, API keys).
- Non-sensitive: `smtp.host`, `smtp.port`, `telico.caller_id` → `ConfigOverrideService`
- Sensitive: `smtp.pass`, `telico.sms_password` → plugin-managed secret store

---

## Migration Impact Summary

| Category | Count | Action |
|----------|-------|--------|
| CONFIG (must migrate) | 3 key groups | Move to `ConfigOverrideService` |
| PLUGIN SETTING (must migrate non-sensitive) | 2 plugins | Non-sensitive → `ConfigOverrideService`; sensitive → secret store |
| RUNTIME STATE | 0 found | N/A |
| LEGACY (remove) | 1 class (`SystemSettingRepository`) | Remove after callers migrated |

---

## Files That Need Changes

1. `app/Services/SystemSettingService.php` — Deprecate `set()` method, mark class legacy
2. `app/Controllers/Admin/SystemSettingsController.php` — Replace `SystemSettingService` writes with `ConfigOverrideService`
3. `app/Core/SettingsRegistry.php` — Add `ConfigOverrideService`-aware save path (or accept interface)
4. `lib/plugins/smtp/src/SmtpHooks.php` — Update `loadSettings()` to read from `ConfigOverrideService`
5. `lib/plugins/telico/src/TelicoHooks.php` — Update `loadSettings()` to read from `ConfigOverrideService`
6. `lib/plugins/smtp/src/SmtpSettings.php` — Update `save()` to accept `ConfigOverrideService`
7. `lib/plugins/telico/src/TelicoSettings.php` — Update `save()` to accept `ConfigOverrideService`
8. `lib/plugins/smtp/src/SmtpTransport.php` — Update `loadSettings()` doc to reference `config/local.php`

---

## Decision Notes

### Why not migrate `app.name`/`app.url` to `ConfigOverrideService` entirely?
These keys are special: they have a layered fallback (DB → config → env → default) because `SystemSettingService` was designed as a runtime override. Now that `ConfigOverrideService` exists, the correct path is:
- Write: `ConfigOverrideService` → config/local.php
- Read: `Config::load('app')` (which already merges config/app.php + config/local.php)
- DB write path is removed (SystemSettingService is legacy)

### What about sensitive plugin credentials (smtp.pass, telico.sms_password)?
Sensitive values should NOT be stored in plain-text config/local.php. Options:
1. Encrypted column in system_settings (DB) — secure but couples to DB
2. Separate encrypted file (e.g., config/local_secrets.php) — file-backed, portable
3. Environment variables (.env) — standard but not admin-configurable
4. Kernel-wide secrets manager (future)

Recommendation: Keep sensitive creds in system_settings for now with encryption at rest. Non-sensitive plugin config goes to config/local.php.
