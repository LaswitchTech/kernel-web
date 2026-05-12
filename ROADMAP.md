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
- [ ] Mailer foundation (default mail(), SMTP via plugin, settings hooks, templates)
- [ ] Auth features (remember me, forgot password, email verification, 2FA)
- [x] Settings plugin hooks (extend system settings via registry) — implemented: SettingsRegistry, SettingsSection, controller integration, view loop
- [x] CRUD test coverage (users, groups, permissions, tokens)
- [ ] Remote catalog sync (periodic fetch of extension listings from a remote server)
- [x] Extension manifest validation improvements (semver, dependency format)
- [ ] Organizations system design (optional, plugin-based data scoping)
- [ ] Organizations plugin foundation (organizations table, organization_users pivot, user membership)

---

## Phase 3: Mid-Term

Features that depend on Phase 2 foundations being in place.

- [ ] ZIP download + checksum verification for extension installs
- [ ] Extension submission/review improvements (bulk operations, better UX)
- [ ] Kernel update system (version check, download, apply)
- [ ] Application update system (local override patches)
- [ ] Theme/layout runtime management (switch without manual file operations)
- [x] DataTables standardization everywhere (consistent configuration, shared init)
- [ ] User registration (config toggle, disabled by default)
- [ ] Plugin marketplace foundation (extension listing, version tracking)
- [ ] Developer mode tools implementation (scaffold generator, example templates)
- [ ] Extension installation progress tracking (large extensions)

---

## Phase 4: Long-Term

Major architectural additions requiring significant infrastructure.

- [ ] OAuth server / client integration
- [ ] Licensing server and validation system
- [ ] Extension marketplace with payment processing
- [ ] Online extension submission/review portal
- [ ] Multi-app ecosystem support (kernel shared across applications)
- [ ] Remote update channels (signed release distribution)
- [ ] Plugin signing / checksum verification
- [ ] Distributed authentication sharing (across multiple kernel instances)
- [ ] Extension analytics / telemetry
- [ ] Multi-tenant data scoping (organization-level query filtering, middleware)

---

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
| Extension catalog | Partially implemented | Browse, submit, review, approve, install, enable/disable, uninstall — UI polished with consistent badges, filtering, and action grouping |
| Dependency resolver | Implemented (first slice) | Resolver service + controller integration; version constraints: exact, >=, >, <=, <, ^, ~; install/enable/uninstall/disable blocks; no auto-install — see docs/developer/extensions/dependencies.md |
| Manifest validation | Implemented | Scalar rejection, keyed format validation, per-key constraint validation — see docs/developer/extensions/dependencies.md |
| Menu system | Implemented | Registry, sidebar, user-menu, admin-menu locations |
| Layout system | Implemented | `app.php` (app), `panel.php` (admin), `blank.php` (auth, local assets, hooks) |
| Theme system | Partially implemented | Bootstrap 5, LESS, dark/light mode |
| Auth system | Partially implemented | Users, groups, permissions, tokens, sessions |
| Organizations | Planned | Design phase — optional, plugin-based scoping |
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
| Validation convention | Implemented | Keyed field errors, documented in /docs/developer/kernel/validation.md |
| GitHub workflows | Implemented | CI (PHP lint, JSON validate) + release workflow |
| Contributing docs | Implemented | Documented in /docs/contributing.md |
| Runtime DB safety | Hardened | DB files excluded from public/, .gitignore updated |
| Phase 1 stabilization | Closed | 15/15 tasks done |
| Testing | Partially implemented | Zero-dependency test framework with 4 suites (191 assertions) — router, plugin, migration, auth. CRUD coverage for users/groups/permissions/tokens complete. See docs/developer/testing.md |
| Profile Modal | Implemented, full plugin architecture | API Tokens section with create/list/revoke UI. Section registry (ProfileModal class), /api/profile + /api/profile/sections endpoints, plugin tab rendering via JS, permission-gated sections. |
| OAuth | Deferred | See Deferred section |
| Licensing | Deferred | See Deferred section |
| Registration | Planned | Config toggle (disabled by default) — Phase 3 |

---

## How to Use This Roadmap

1. **Pick the highest-priority unchecked item** in the earliest phase that is unblocked
2. **Check dependencies** — some tasks unlock others
3. **Update this file** when a task is completed or when scope changes
4. **Move tasks between phases** as new information becomes available
