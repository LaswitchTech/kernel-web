# Kernel-Web — Project Roadmap

Priority-based roadmap for Kernel-Web development. Tasks are organized by phase rather than chronology.

---

## Platform

PHP 8.1+ baseline. Future development may freely use modern PHP 8.x features
(readonly, union types, mixed, match, constructor property promotion).
PHP 7.x compatibility must not be reintroduced.

---

## Phase 1: Immediate / Stabilization

Foundational work that should happen before major feature development.

- [x] Complete extension catalog install workflow (staged install, lifecycle hooks, migration execution)
- [x] Complete extension catalog enable/disable workflow
- [x] Validate full extension lifecycle (install → enable → disable)
- [x] Verify full browser flow after install (login → admin → extensions → install → verify) — manually validated (32 endpoints, all pass)
- [x] Standardize DataTables usage in all admin tables
- [x] Add breadcrumbs to panel layout
- [x] Add admin panel shortcut to topbar user menu (admin-only)
- [x] Ensure all admin pages use panel layout consistently — audited (all 6 controllers use panel.php)
- [x] Add tests for route registration, plugin lifecycle, and migrations — zero-dependency test framework with 3 suites (86 assertions total)
- [x] Document menu registry and hook registry in `/docs` — already documented (menu-registry.md, layout-hook-registry.md)
- [x] Finalize and stabilize the `panel.php` layout — audited (stable, no issues)
- [x] Add a `blank.php` layout override example for auth pages — polished (local assets, hooks)
- [x] Review and clean up any remaining NetMon-specific code in core — none found (core is clean)
- [x] Document validation error convention (keyed field errors) — already documented (docs/developer/kernel/validation.md)
- [x] Design extensible Profile Modal system (UI structure, core sections, plugin hook, API design, security rules)

**Phase 1 Status:** 15 of 15 tasks completed. Phase 1 stabilization is closed.

---

## Phase 2: Short-Term Kernel Foundations

Core infrastructure improvements that unlock future feature work.

- [x] Extension uninstall workflow (reverse of install: run down migrations, disable hooks, remove files)
- [x] Extension dependency resolver — design + first slice (Resolver service + controller integration)
- [x] Dynamic LESS compilation (wikimedia/less.php, /css route, cache, theme/layout/plugin merge)
- [x] Extension update checks (design — local-only, no remote sync, no auto-install)
- [x] Documentation plugin (render markdown docs at clean routes)
- [x] Extensions UI polish (improved listing, filtering, status indicators)
- [x] Developer mode tools foundation (conditional code paths, permission gates)
- [x] Profile Modal tabbed UI foundation (convert static modal to tabs, Overview section)
- [x] Profile Modal section registry / hook system (ProfileModal class, profile.sections hook)
- [x] Profile Modal API Tokens section (integrate existing TokenService into modal)
- [x] Profile Modal plugin-provided sections (tab loading, permission gating)
- [x] Plugin/theme/layout scaffold generator (design complete — see docs/developer/scaffolds.md)
- [x] Mailer foundation (default mail(), SMTP via plugin, settings hooks, templates)
- [x] Auth features: remember me (selector/validator tokens, rotation, auto-login) — design at docs/developer/auth-features.md
- [x] Auth features: forgot password (selector/validator tokens, single-use, 60-minute expiry, email delivery) — design at docs/developer/auth-features.md
- [x] Auth features: email verification (selector/validator tokens, single-use, 24-hour expiry, soft gate, email delivery) — design at docs/developer/auth-features.md
- [x] Auth features: 2FA (TOTP RFC 6238, 160-bit secrets, 10 recovery codes, pending 2FA session state, Profile Modal integration)
- [x] Settings plugin hooks (extend system settings via registry) — implemented: SettingsRegistry, SettingsSection, controller integration, view loop
- [x] CRUD test coverage (users, groups, permissions, tokens)
- [P3] Remote catalog sync (periodic fetch of extension listings from a remote server)
- [x] Extension manifest validation improvements (semver, dependency format)
- [x] Organizations system design (optional, plugin-based data scoping) — design at docs/developer/organizations.md
- [x] Organizations plugin foundation (organizations table, organization_users pivot, user membership)
- [x] Organizations runtime UX (Profile Modal section, AJAX switch/create/list endpoints, slug generation)
- [x] SMTP mail plugin (replace PHP mail() with SMTP for reliable delivery on macOS/MAMP; settings via SettingsRegistry; test-email button)
- [x] Messenger foundation (SMS transport interface, message object, Messenger service; Telico transport plugin with settings, test-sms endpoint)
- [x] Kernel/App/Extension versioning model — design at docs/developer/versioning.md

---

## Phase 2b: Global View Context & User Experience

### Status

The global view context was the root cause of bugs across the app. This has been addressed:

- `ViewGlobals::contextFromScope()` and `contextFromContainer()` provide a guaranteed context layer
- All 3 layouts (panel, app, blank) call `ViewGlobals::contextFromScope()` at the layout entry point
- Guest-safe defaults ensure no variable is ever undefined
- Partial files retain defensive `isset()` checks only where data is optional (not where it's structural)
- Dev tools offcanvas is rendered in all 3 layouts (APP_DEBUG-gated)
- Profile Modal section registry with plugin-provided tabs is implemented
- User menu populates from global context variables

### Remaining Tasks (Phase 2b)

- [x] **Audit and integrate debug logging with Audit Log** — `DebugAuditLogger` service (APP_DEBUG-gated), writes to admin_audit_log with sanitized payloads, visual debug badge in /admin/audit, 2FA call sites wired
- [x] **Add APP_DEBUG logging** — `DebugAuditLogger` wired to container; 2FA login/setup call sites added
- [x] **Add audit log type filtering** — `?type=all|debug|audit` query param on /admin/audit; button-group filter UI
- [x] **Add repository disclaimer** — Development status added to README.md, CLAUDE.md, DESIGN.md
- [x] **Add recovery codes for TOTP** — 10 recovery codes generated, toggle UI in /auth/2fa and Profile Modal
- [x] **Add global 2FA enable/disable and enforce settings** — File-backed config key `auth.two_factor.enforced`, middleware enforcement in SessionAuth, read-only status in /admin/settings, 10 assertions
- [P3] **Make 2FA methods extensible** — TOTP (current); SMS/Email would need a plugin interface
- [P3] **Disable 2FA for users with no selected method** — Defensive guard
- [P4] **Add optional 2FA setup prompt after login** — 30-day skip logic in user preferences
- [P4] **Preserve last opened profile modal tab** — sessionStorage persistence; currently always refreshes all tabs
- [P3] **Fix card-header border-radius to match card radius** — CSS consistency
- [x] **Design universal config-saving system** — ConfigOverrideService writes to config/local.php via dot-notation keys, atomic file writes, boolean/string normalization, 41 assertions
- [P2] **Send real test email** — Test email button exists in SMTP settings but verify it actually sends
- [P3] **Move SMTP settings into Mailer settings** — Selectable mailer provider (mail(), SMTP), provider-based settings
- [P3] **Make SMS settings provider-based** — Extensible via plugins (Telico, Twilio, etc.)
- [P4] **Add application logo upload/selection** — Admin setting + global application across all layouts
- [P4] **Add mailer queue/history admin page** — .eml access, server response logs
- [P4] **Add SMS queue/history admin page** — Provider response logs, delivery status
- [P2] **Fix organization creation from profile tab** — JSON body parsing fixed; verify end-to-end flow
- [P3] **Add organization type JSON field** — Extensible org roles beyond basic type discriminator
- [P4] **Add organization subsidiaries/corporate structure** — Parent-child org relationships
- [P4] **Rename /signin → /auth/login and /signup → /auth/register** — Standardize auth route naming, preserve redirects/aliases
- [P3] **Add Variables.md documentation** — Document always-available variables/objects for future developers

---

## Phase 3: Mid-Term

Features that depend on Phase 2 foundations being in place.

- [P2] ZIP download + checksum verification for extension installs
- [ ] Extension submission/review improvements (bulk operations, better UX)
- [P2] Kernel update system (version check, download, apply)
- [ ] Application update system (local override patches)
- [P3] Theme/layout runtime management (switch without manual file operations)
- [x] DataTables standardization everywhere (consistent configuration, shared init)
- [x] User registration (config toggle, disabled by default)
- [ ] Plugin marketplace foundation (extension listing, version tracking)
- [x] Developer mode tools implementation (scaffold generator, example templates)
- [ ] Extension installation progress tracking (large extensions)

---

## Phase 4: Long-Term

Major architectural additions requiring significant infrastructure.

- [P1] OAuth server / client integration
- [P1] Licensing server and validation system
- [P2] Extension marketplace with payment processing
- [P2] Online extension submission/review portal
- [P3] Multi-app ecosystem support (kernel shared across applications)
- [P3] Remote update channels (signed release distribution)
- [P3] Plugin signing / checksum verification
- [P3] Distributed authentication sharing (across multiple kernel instances)
- [P4] Extension analytics / telemetry
- [P2] Multi-tenant data scoping (organization-level query filtering, middleware)

---

## Next Recommended Tasks

These are the highest-impact items that should be addressed next:

1. **P2: System-wide 2FA enforcement** — Per-user toggle exists but no global enforcement switch ✅ DONE
2. **P2: Universal config-saving system** — ConfigOverrideService foundation ✅ DONE
3. **P2: Multi-tenant data scoping** — Organization-level query filtering middleware (unlocks proper SaaS mode)
4. **P2: Kernel update system** — Version check exists; needs download and apply workflow
5. **P3: Make 2FA methods extensible** — TOTP-only currently; plugin interface for SMS/Email methods
6. **P3: Variables.md documentation** — Document the global view context contract for future developers

## Deferred / Explicitly Not Now

These are planned or requested but are out of scope for the current development cycle.

- [ ] OAuth — pending authentication architecture design
- [ ] Licensing — pending licensing server design
- [ ] Marketplace — pending payment and review infrastructure
- [ ] Remote ZIP install — pending checksum/signature model
- [ ] Multi-instance auth sharing — pending OAuth foundation
- [ ] Plugin signing — pending marketplace infrastructure
- [ ] Distributed architecture — post-1.0 consideration

---

## Current State

| Area | Status | Notes |
|------|--------|-------|
| Plugin system | Implemented | Discovery, manifest, lifecycle hooks, registry |
| Extension catalog | Implemented | Browse, submit, review, approve, install, enable/disable, uninstall — UI polished with consistent badges, filtering, and action grouping |
| Dependency resolver | Implemented (first slice) | Resolver service + controller integration; version constraints: exact, >=, >, <=, <, ^, ~; install/enable/uninstall/disable blocks; no auto-install — see docs/developer/extensions/dependencies.md |
| Manifest validation | Implemented | Scalar rejection, keyed format validation, per-key constraint validation — see docs/developer/extensions/dependencies.md |
| Menu system | Implemented | Registry, sidebar, user-menu, admin-menu locations |
| Layout system | Implemented | `app.php` (app), `panel.php` (admin), `blank.php` (auth, local assets, hooks) |
| Theme system | Partially implemented | Bootstrap 5, LESS, dark/light mode |
| Auth system | Fully implemented | Users, groups, permissions, tokens, sessions, remember me, forgot password, email verification, user registration (config-gated), 2FA (TOTP + recovery codes) |
| Auth features | Complete | All documented auth features implemented: remember me (40 assertions), forgot password (29 assertions), email verification (38 assertions), registration (55 assertions), 2FA (64 assertions). 2FA enforcement (system-wide) remains as remaining Phase 2b task. |
| Organizations | Implemented | Plugin foundation: organizations + organization_users tables, OrganizationRepository, OrganizationMemberRepository, OrganizationContext (session-based default org resolution), Profile Modal integration with AJAX switch/create/list endpoints, slug generation, 90 assertions across organization_test.php and organization_runtime_test.php, /admin/organizations listing page. Design at docs/developer/organizations.md. |
| Theme preview | Implemented | GET /admin/themes/preview, all Bootstrap components, panel layout with breadcrumbs |
| Plugin migrations | Implemented | Migration runner, catalog integration |
| Scaffold generator | Implemented | Routes at /admin/developer/scaffold (GET/POST), templates in resources/scaffolds/, staging output, validation, developer-mode gate |
| Breadcrumbs | Implemented | Documented in /docs/developer/breadcrumbs.md |
| DataTables | Standardized | Consistent configuration across all admin tables |
| Developer mode | Implemented | Admin page at /admin/developer, sidebar menu (debug-gated), planned-tools cards |
| LESS compilation | Implemented | Hybrid: npm build + PHP dynamic merge at /css |
| Extension update checks | Implemented | ExtensionUpdateChecker + ExtensionUpdate VO, controller integration, catalog table UI columns + badges, dependency blocking (Check A + Check B), design doc at docs/developer/extensions/updates.md — local-only comparison, no remote sync, no auto-install. |
| Documentation plugin | Implemented | Plugin at lib/plugins/documentation/, DocsController with lightweight markdown renderer (headers, bold, italic, code, lists, links, images), panel layout with sidebar TOC + prev/next nav, breadcrumbs, Edit on GitHub link — see lib/plugins/documentation/ |
| Setup session | Fixed | Session cookie config applied from auth.php |
| 2FA session split | Fixed | Zombie PHPSESSID session from Apache mod_session.so properly detected and destroyed via session_id('') reset |
| Validation convention | Implemented | Keyed field errors, documented in /docs/developer/kernel/validation.md |
| GitHub workflows | Implemented | CI (PHP lint, JSON validate) + release workflow |
| Contributing docs | Implemented | Documented in /docs/contributing.md |
| Runtime DB safety | Hardened | DB files excluded from public/, .gitignore updated |
| Phase 1 stabilization | Closed | 15/15 tasks done |
| Testing | Partially implemented | Zero-dependency test framework with 33 suites — router, plugin, migration, auth, mailer, remember_me, forgot_password, email_verification, registration, two_factor, two_factor_profile, organization, organization_runtime, smtp, smtp_settings, telico, telico_settings, version, messenger, profile_organizations, global_context, view_globals, layout_context_regression, dev_tools_partial, devtools_controller, devtools_scope, config_runtime, env_config, audit_filter, audit_render, debug_audit_logger, barcode, 2fa_login_flow. CRUD coverage for users/groups/permissions/tokens complete. Mailer + SMTP + Telico foundation implemented. Two-factor auth includes regression test for missing-schema degradation. VersionProvider tests cover kernel/app version resolution. Messenger + Telico tests cover transport interface, message immutability, and API client validation. See docs/developer/testing.md |
| Mailer | Implemented | Core infrastructure: MailMessage, Attachment, TemplateRegistry, TransportInterface, MailTransport (mail()), Mailer facade, MailerException. SMTP transport plugin with settings, test-email endpoint, bootstrap hook transport swap. Config at config/mail.php. 86 assertions (63 mailer + 23 smtp). Design at docs/developer/mailer.md. SMTP docs at docs/developer/smtp-plugin.md. |
| Messenger (SMS) | Implemented | Core infrastructure: Message (immutable readonly VO), MessengerTransportInterface, Messenger service (wired in container), MessengerException. Telico transport plugin with settings (username, SMS password, caller ID), test-sms endpoint, bootstrap hook transport swap. Config at config/messenger.php. 34 assertions (22 messenger + 12 telico). Design at docs/developer/messenger.md. Telico plugin docs at docs/developer/telico-plugin.md. |
| SMTP/Telico settings | Fixed | Bootstrap hooks now register correctly — plugin autoloader updated to handle `Plugins\` namespace (used by SMTP/Telico plugins). Both plugins enabled by default (plugin.json). SMTP manifest semver fix ("8.1" → ">=8.1.0"), SMTP and Telico settings visibility fixed (permission => null). Settings appear in /admin/settings. |
| Profile Modal org creation | Fixed | JSON body parsing now reads `php://input` instead of `$_POST` for application/json requests. Regression test added (profile_organizations_test.php, 27 assertions). |
| 2FA Profile tab | Fixed | Full setup/enable/disable UI rendered via ProfileModal section callback. Tab button added to profile-modal.php with data attributes. Lazy-loaded from /api/profile/sections/two-factor on first activation. |
| Debug logging | Implemented | `DebugAuditLogger` service (APP_DEBUG-gated), writes to admin_audit_log, sanitized payloads, visual debug badge in /admin/audit, 2FA call sites wired |
| Audit log filtering | Implemented | `?type=all|debug|audit` query param on /admin/audit; button-group filter UI; `findRecent()` SQL WHERE clause with bound params |
| Global view context | Implemented | `ViewGlobals::contextFromScope()` + `contextFromContainer()`; all 3 layouts use it; guest-safe defaults for all user variables |
| Dev tools offcanvas | Implemented | Rendered in all 3 layouts; APP_DEBUG-gated; variables inspection panel |
| Settings registry | Implemented | `SettingsRegistry` + `SettingsSection`; controller integration; plugin-provided sections (SMTP, Telico) |
| Profile modal | Implemented, full plugin architecture | Section registry, API Tokens section, 2FA section, organization section; plugin tab rendering via JS |
| OAuth | Deferred | See Deferred section |
| Licensing | Deferred | See Deferred section |
| Registration | Implemented | Config-gated (disabled by default), email verification integration |
| Versioning model | Phase A+B+C complete | Design at docs/developer/versioning.md. VersionProvider (Phase A): kernel/app version resolution with 30 assertions. Admin overview card (Phase B): kernel version, app name/version, "Update check not configured" badge. Phase C: extension kernel compatibility checks with checkKernelCompatibility(), manifest validation, install/enable blocks, boot-time warnings, update blockers, and admin UI (warning badge in overview, Kernel column in catalog table). Phase D (remote updates) deferred. |

---

## How to Use This Roadmap

1. **Pick the highest-priority unchecked item** in the earliest phase that is unblocked
2. **Check dependencies** — some tasks unlock others
3. **Update this file** when a task is completed or when scope changes
4. **Move tasks between phases** as new information becomes available
