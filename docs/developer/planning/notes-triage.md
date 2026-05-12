# NOTES.md Triage

> Generated from NOTES.md working notes. Categorized by priority and effort.

---

## Bugs / Fixes (immediate)

### BUG-1: Settings NetMon leftovers
**Severity:** Medium
**Location:** `app/Controllers/Admin/SystemSettingsController.php`
**Status:** FIXED (commit TBD)
**Details:**
- `monitoring.check_interval` — NetMon monitoring. Removed from controller and view.
- `notifications.email_enabled` — notification system exists as stub code in `app/Modules/Notifications/` (controllers, services, channels, repos) but has NO routes, NO migrations, NO plugin manifest. Dead code. Replaced with informational note in settings view.
**Action:** Monitoring card removed from view. Notification section kept but marked as unimplemented. Controller updated to only manage app.name and app.url.

### BUG-2: Scaffold page topbar layout
**Severity:** Medium
**Location:** `app/Views/admin/developer/scaffold.php` / `app/Views/layouts/panel.php`
**Details:** User reports page content rendering above the topbar. Analysis: scaffold.php is a content fragment rendered via panel.php's `#app-page-content` wrapper (padding: 1.5rem). The topbar uses `position: sticky` (not `fixed`) in a flex column. The layout structure is correct. The reported issue is likely CSS-specific. No structural bug found in scaffold view or panel layout.
**Action:** Requires live browser inspection to diagnose. Deferred to sprint 2.

### BUG-3: Settings missing plugin hooks
**Severity:** Medium (feature gap, not a bug)
**Status:** FIXED
**Details:** SystemSettingsController now supports plugin settings sections via SettingsRegistry.
**Action:** Fixed: SettingsRegistry + SettingsSection classes added. Controller merges plugin keys, validates, saves. View loops over sections. Plugin declares `keys`, `validate`, `save`, `render` callbacks.

---

## Tests to Add (validation tasks)

### TEST-1: CRUD test coverage
**Details:** Existing users, groups, permissions, tokens CRUD operations need test coverage.
**Scope:** 
- Users: create, read, update, delete
- Groups: create, read, update, delete
- Permissions: create, read, update, delete
- Tokens: create, list, revoke
**Approach:** Add to existing zero-dependency test framework. Test the service/repository layer.

---

## Phase 2 Candidates (next implementation wave)

### PHASE2-1: Auth features
**Location:** NOTES.md, Auth section
**Items:**
- Remember me functionality (long-lived auth cookie)
- Forgot password (reset link via email)
- Email verification (send link during registration)
- User registration form
- Two-factor authentication (second auth factor)
**Status:** Design phase. Each item needs architectural decisions before implementation.
**Dependencies:** Mailer foundation (PHASE2-2) for forgot password and email verification.

### PHASE2-2: Mailer foundation
**Location:** NOTES.md, Mailer section
**Design:**
- Default mailer uses PHP `mail()` function
- SMTP support via plugin (configured through settings page hooks)
- Email templates for different types (password reset, verification, notifications)
- Plugin extensibility for custom email types
- Error handling (logging, user feedback)
**Status:** **DESIGNED** — see `docs/developer/mailer.md`. Full design including Mailer service, Transport interface (default mail()), TemplateRegistry, Attachment, MailerException, SMTP plugin integration via SettingsRegistry, and implementation priorities.

### PHASE2-3: Settings hooks for plugins
**Location:** BUG-3 + NOTES.md, Settings section
**Design:**
- Add a hook system to SystemSettingsController
- Plugins register settings sections via a registry
- Settings sections render at specific positions in the settings page
**Status:** Needs design doc. Related to MAILER-1 (SMTP settings plugin) and AUTH-2 (forgot password settings).

---

## Phase 3+ Candidates (deferred)

### PHASE3-1: Messenger / SMS foundation
**Location:** NOTES.md, Messenger section
**Items:**
- Messenger class with provider abstraction
- Message templates
- Queue system for bulk messages
- Provider plugins (Twilio, Telico)
- Plugin extensibility for custom providers
**Status:** Deferred. Depends on mailer foundation for multi-channel messaging unification.

### PHASE3-2: Multi-database compatibility
**Location:** NOTES.md, Database Management section
**Items:**
- PostgreSQL support
- MySQL/MariaDB support
- Migration compatibility across dialects
- PDO driver abstraction
**Status:** Deferred. Significant architectural changes to database layer.

---

## Architectural Decisions Needed

### ARCH-1: Mailer abstraction design
**Questions:**
- Should mailer be an interface with multiple implementations, or a class with pluggable transport?
- How do email templates get loaded (filesystem, registry, both)?
- How does the SMTP plugin declare its settings?
- Should the default mailer use `mail()` or throw until configured?
**Recommendation:** Interface + transport plug-in pattern. Default transport = `mail()`. SMTP = plugin. Templates = registry with filesystem fallback.
**Status:** **DESIGNED** — see `docs/developer/mailer.md`. Design covers Mailer service, Transport interface, default mail() transport, TemplateRegistry, Attachment value object, MailerException, SMTP plugin via SettingsRegistry, error handling strategy, and implementation priorities.

### ARCH-2: Database driver architecture
**Questions:**
- Should core DatabaseInterface support multiple PDO drivers, or should driver selection be in config?
- How do migrations stay dialect-agnostic?
- Should SQLite be the "base" dialect with MySQL/PostgreSQL as extensions?
**Recommendation:** Config-driven driver selection. Core uses PDO's native dialect helpers. Migrations use standard SQL + dialect-specific overrides when needed.

### ARCH-3: Settings hook registry design
**Questions:**
- Should settings hooks work like layout hooks (named hook points)?
- Or should each settings section be a standalone controller/view rendered in a frame?
**Recommendation:** Hook point system. Each registered section gets a render callback that returns HTML. Rendered at specific positions in the settings page.

### ARCH-4: Remember me / forgot password / 2FA design
**Questions:**
- Remember me: database-backed token vs. signed cookie?
- Forgot password: token in DB or email-only flow?
- 2FA: TOTP (Google Authenticator) vs. SMS code?
- How are auth tokens stored and rotated?
**Recommendation:** Remember me = database-backed long-lived token (revocable, visible in profile). Forgot password = DB token + email link. 2FA = TOTP (works without SMS). Auth tokens = existing TokenService with extended expiry.

---

## Related NOTES.md Items (mapped)

| NOTES.md Section | Mapped To | Notes |
|---|---|---|
| Scaffold page topbar | BUG-2 | Quick CSS fix, not architectural |
| Settings NetMon leftovers | BUG-1 | Remove dead config immediately |
| Settings notification leftovers | BUG-1 | Remove if notification system not implemented |
| Settings hooks | PHASE2-3 | Needs design |
| Mailer | PHASE2-2 + ARCH-1 | Designed (DESIGN ONLY, not implemented) — see docs/developer/mailer.md |
| Mailer email templates | PHASE2-2 | Part of mailer design |
| Mailer queue system | PHASE2-2 | Deferred to later sprint |
| Mailer attachments | PHASE2-2 | Deferred to later sprint |
| Mailer SMTP plugin | PHASE2-2 | Depends on PHASE2-3 settings hooks |
| Mailer error handling | PHASE2-2 | Part of mailer implementation |
| Auth remember me | PHASE2-1 + ARCH-4 | Needs design |
| Auth forgot password | PHASE2-1 + ARCH-4 | Depends on mailer |
| Auth email verification | PHASE2-1 + ARCH-4 | Depends on mailer |
| Auth registration | PHASE2-1 | Already partially designed in DESIGN.md |
| Auth 2FA | PHASE2-1 + ARCH-4 | Needs design |
| Messenger SMS | PHASE3-1 | Deferred |
| Messenger templates | PHASE3-1 | Part of messenger design |
| Messenger queue | PHASE3-1 | Deferred |
| Messenger Twilio plugin | PHASE3-1 | Depends on messenger |
| Messenger Telico plugin | PHASE3-1 | Depends on messenger |
| Database compatibility | PHASE3-2 + ARCH-2 | Needs design |
| CRUD test coverage | TEST-1 | Next validation task |

---

## Items from NOTES.md not yet categorized

None. All items have been mapped above.

---

## Open Design Questions Summary

| ID | Question | Priority | Blocked By |
|----|----------|----------|------------|
| ARCH-1 | Mailer abstraction | High (unlocks auth features) | None |
| ARCH-2 | Multi-database architecture | Low (deferred to Phase 3+) | None |
| ARCH-3 | Settings hooks | Medium (needed for SMTP plugin) | None — DESIGNED at `docs/developer/settings-hooks.md` |
| ARCH-4 | Auth token architecture | High (unlocks remember/forgot/2FA) | ARCH-1 |

---

## Action Items

### Immediate (this sprint)
- [x] BUG-1: Remove NetMon leftovers from SystemSettingsController — Monitoring card removed, Notification section replaced with informational note. Controller only manages app.name and app.url.
- [ ] BUG-2: Fix scaffold page topbar margin — requires live browser inspection
- [x] ARCH-3: Settings hooks design complete — see `docs/developer/settings-hooks.md`
- [x] ARCH-1: Mailer design complete — see `docs/developer/mailer.md`

### Next sprint
- [ ] PHASE2-3: Implement settings hook registry
- [ ] PHASE2-2: Implement mailer foundation
- [ ] TEST-1: Add CRUD test coverage

### Later
- [ ] ARCH-4: Draft auth token design in DESIGN.md
- [ ] PHASE2-1: Implement auth features (order: remember me → forgot password → verification → registration → 2FA)
- [ ] ARCH-2: Draft multi-database design
- [ ] PHASE3-1: Messenger foundation
