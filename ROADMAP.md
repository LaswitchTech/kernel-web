# Kernel-Web — Project Roadmap

Priority-based roadmap for Kernel-Web development. Tasks are organized by phase rather than chronology.

---

## Phase 1: Immediate / Stabilization

Foundational work that should happen before major feature development.

- [x] Complete extension catalog install workflow (staged install, lifecycle hooks, migration execution)
- [x] Complete extension catalog enable/disable workflow
- [x] Validate full extension lifecycle (install → enable → disable)
- [ ] Verify full browser flow after install (login → admin → extensions → install → verify)
- [x] Standardize DataTables usage in all admin tables
- [x] Add breadcrumbs to panel layout
- [x] Add admin panel shortcut to topbar user menu (admin-only)
- [ ] Ensure all admin pages use panel layout consistently
- [ ] Add tests for route registration, plugin lifecycle, and migrations
- [ ] Document menu registry and hook registry in `/docs`
- [ ] Finalize and stabilize the `panel.php` layout
- [ ] Add a `blank.php` layout override example for auth pages
- [ ] Review and clean up any remaining NetMon-specific code in core

---

## Phase 2: Short-Term Kernel Foundations

Core infrastructure improvements that unlock future feature work.

- [ ] Extension uninstall workflow (reverse of install: run down migrations, disable hooks, remove files)
- [ ] Extension dependency resolver (detect and install/update dependencies automatically or warn)
- [ ] Extension update checks (local → catalog → remote)
- [ ] Documentation plugin (render markdown docs at clean routes)
- [ ] Extensions UI polish (improved listing, filtering, status indicators)
- [ ] Developer mode tools foundation (conditional code paths, permission gates)
- [ ] Plugin/theme/layout scaffold generator (bootstrap new extensions)
- [ ] Remote catalog sync (periodic fetch of extension listings from a remote server)
- [ ] Extension manifest validation improvements (semver, dependency format)
- [ ] Organizations system design (optional, plugin-based data scoping)
- [ ] Theme preview page design (Bootstrap components reference)
- [ ] Organizations plugin foundation (organizations table, organization_users pivot, user membership)
- [ ] Theme preview page implementation (Bootstrap components reference, theme switching)

---

## Phase 3: Mid-Term

Features that depend on Phase 2 foundations being in place.

- [ ] ZIP download + checksum verification for extension installs
- [ ] Extension submission/review improvements (bulk operations, better UX)
- [ ] Kernel update system (version check, download, apply)
- [ ] Application update system (local override patches)
- [ ] Theme/layout runtime management (switch without manual file operations)
- [x] DataTables standardization everywhere (consistent configuration, shared init)
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
| Extension catalog | Partially implemented | Browse, submit, review, approve, install, enable/disable |
| Menu system | Implemented | Registry, sidebar, user-menu, admin-menu locations |
| Layout system | Partially implemented | `app.php` (app), `panel.php` (admin), `blank.php` (base) |
| Theme system | Partially implemented | Bootstrap 5, LESS, dark/light mode |
| Auth system | Partially implemented | Users, groups, permissions, tokens, sessions |
| Organizations | Planned | Design phase — optional, plugin-based scoping |
| Theme preview | Planned | Design phase — Bootstrap components reference |
| Plugin migrations | Implemented | Migration runner, catalog integration |
| Breadcrumbs | Implemented | Documented in /docs/developer/breadcrumbs.md |
| DataTables | Loaded but not standardized | Libraries present in panel layout |
| Developer mode | Not implemented | Gated by `APP_DEBUG` |
| Updates module | Not implemented | Documented for Phase 2 |
| Documentation plugin | Not implemented | Documented for Phase 2 |
| OAuth | Deferred | See Deferred section |
| Licensing | Deferred | See Deferred section |

---

## How to Use This Roadmap

1. **Pick the highest-priority unchecked item** in the earliest phase that is unblocked
2. **Check dependencies** — some tasks unlock others
3. **Update this file** when a task is completed or when scope changes
4. **Move tasks between phases** as new information becomes available
