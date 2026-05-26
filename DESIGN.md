# Kernel-Web - Design Document

## Purpose

This document defines the **design decisions, architecture patterns, and structural conventions** of Kernel-Web.

It is separate from `CLAUDE.md`, which focuses on **workflow rules and development behavior**.

Use this file to:
- Document architectural decisions
- Define system design patterns
- Track evolving structure (plugins, themes, layouts, auth, etc.)
- Ensure consistency across implementations

---

## Product Vision

Kernel-Web is an **AI-native business application framework**.

Its purpose is to provide a reusable PHP kernel for building modular business applications that can be operated by both humans and AI agents.

Kernel-Web should support:

- Traditional web UI workflows
- Plugin-based business applications
- Safe automation through services, APIs, tasks, and events
- Agent-readable documentation and metadata
- Strong auditability for business-critical actions
- Self-hosted/private deployments

Primary target application families:

1. **Transport / Logistics**
2. **Customs Consultation**
3. **LaswitchTech operations**
   - Tech blog
   - Product creation
   - Documentation
   - Content workflow
   - YouTube/content planning

## Design Principles

### 1. Kernel First
- The kernel must remain **application-agnostic**
- No domain-specific logic (e.g., NetMon) in core
- Everything reusable should live in kernel or plugins

### 2. Modular by Default
- Features must be:
  - Replaceable
  - Extendable
  - Optional
- Prefer plugins over core expansion

### 3. Explicit over Magic
- Avoid hidden behaviors
- Prefer readable, predictable flows
- No heavy frameworks

### 4. Separation of Concerns
- Controllers → orchestration only
- Services → business logic
- Repositories → data access
- Kernel → infrastructure
- Plugins → features

### 5. Secure by Design
- Assume public deployment
- Deny access by default
- Validate everything server-side

### 6. Agent-Operable by Design

Every major feature should be designed so it can be operated safely by:

- A human through the UI
- An AI agent through explicit services, APIs, tasks, or events

Agent-operable does not mean unrestricted automation.

All agent actions must follow the same rules as human actions:

- Permission checks
- Validation
- CSRF/API-token rules where applicable
- Audit logging
- Clear ownership
- Safe error handling
- No silent destructive behavior

### 7. Business Automation Ready

Kernel-Web should be suitable for building real business applications.

Core systems should support:

- Organizations
- Users and permissions
- Tasks
- Activities
- Documents
- Notifications
- Audit trails
- Configurable workflows
- Plugin-defined entities
- API-driven automation

The kernel must remain domain-neutral, but it should provide the primitives required by domain-specific plugins.

---

## Platform & Runtime

### PHP Version

- **Minimum**: PHP 8.1
- **CI target**: PHP 8.2
- **composer platform**: `8.1`

### Design Decisions

Kernel-Web is designed around modern PHP 8.x. The following are intentional:

- **readonly** value objects — immutability without boilerplate
- **union types** — explicit API contracts (e.g. `array|SettingsSection`)
- **mixed types** — clarity where any type is valid (container, config, hooks)
- **match** expressions — concise value mapping over switch
- **Constructor property promotion** — fewer lines, same semantics
- **Native `str_contains` / `str_starts_with`** — no polyfills

**Rationale:**
- Readability and stronger typing
- Reduced boilerplate
- Cleaner domain/value object modeling
- CI validates against PHP 8.2

PHP 7.x compatibility is not a goal. Any code that reverts modern PHP syntax
for backward compatibility is incorrect.

---

## High-Level Architecture

Kernel-Web is composed of 4 major layers:

```text
[ Kernel Core ]
      ↓
[ Plugins ]
      ↓
[ Layouts ]
      ↓
[ Themes ]
```

### Kernel Core
Provides:
- Routing
- Service container
- Auth system
- Plugin loader
- Theme loader
- Layout renderer
- Database layer
- Config system

### Plugins
Provide:
- Features (Notes, Chat, File Manager, etc.)
- Routes
- Services
- Migrations
- UI components

### Layouts
Provide:
- Page structure
- Reusable UI skeletons

### Themes
Provide:
- Visual styling
- LESS variables/tokens

---

## AI-Native Architecture

Kernel-Web treats AI agents as future first-class operators of the system.

Agents are not special users that bypass the application. They are external or internal operators that interact with Kernel-Web through controlled interfaces.

### Human + Agent Operation Model

```text
Human UI
   ↓
Controllers
   ↓
Services
   ↓
Repositories
   ↓
Database / Files / External APIs

AI Agent
   ↓
API / Action Endpoint / Task Queue
   ↓
Same Services
   ↓
Same Repositories
   ↓
Same Audit + Permission Layer
```

### Core Rule

> Agents must use the same business services as humans. No separate hidden write path.

### Agent-Operable Feature Requirements

A feature is considered agent-operable when it provides:

* Clear service methods
* Explicit permissions
* Validated inputs
* Predictable outputs
* Audit logging
* Error responses suitable for automation
* Documentation describing safe usage
* Optional task/event hooks where useful

### Agent Safety Rules

AI agents must not:

* Bypass permissions
* Write directly to the database
* Modify config files except through approved services
* Perform destructive actions without an explicit service/API path
* Auto-approve their own high-risk actions
* Hide failures or skipped validations

### Agent Auditability

Agent-driven actions should record:

* Actor identity
* Agent/profile name when available
* Action performed
* Target entity
* Input summary
* Result status
* Error message if failed
* Related task/run ID when applicable

## Directory Responsibilities

### `/app`
Core application logic (kernel only)

### `/lib/plugins`
Feature modules

### `/lib/themes`
Visual themes

### `/lib/layouts`
Reusable UI layouts

### `/public`
Only web-accessible directory

### `/storage`
Runtime data (logs, cache, uploads)

### `/data`
Persistent local data (SQLite)

---

## Business Application Plugin Strategy

Kernel-Web business apps should be built as plugins or plugin groups.

Examples:

```text
lib/plugins/Logistics/
lib/plugins/Customs/
lib/plugins/ContentStudio/
lib/plugins/Products/
lib/plugins/Projects/
lib/plugins/Agents/
```

### Business Plugin Rules

* Business logic belongs in plugins, not kernel core
* Plugins define their own entities, routes, services, repositories, permissions, menus, and migrations
* Plugins should expose service-level operations suitable for both UI and automation
* Plugins should document their entities and workflows
* Plugins should integrate with common kernel services instead of duplicating them

### Shared Kernel Primitives

Business plugins should reuse kernel primitives where possible:

* Auth
* Organizations
* Permissions
* Settings
* Audit logs
* Mailer
* Messenger
* Tasks
* Events/hooks
* API tokens
* Profile modal sections
* Admin settings sections

## Plugin System Design

### Manifest Format

Each plugin has a `plugin.json` at its root:

```json
{
    "name": "plugin-name",
    "version": "0.1.0",
    "description": "Short description",
    "enabled": true,
    "requires": { "php": "8.1" },
    "dependencies": { "plugin:notes": ">=0.1.0" },
    "permissions": ["permission.string"],
    "routes": [],
    "migrations": [],
    "services": {},
    "hooks": [],
    "menus": [],
    "lifecycle": {
        "install": "Plugins\\MyPlugin\\Lifecycle@install",
        "enable": "Plugins\\MyPlugin\\Lifecycle@enable",
        "disable": "Plugins\\MyPlugin\\Lifecycle@disable"
    }
}
```

**Required fields:** `name`, `version`
**Optional fields:** `description`, `enabled` (default: `true`), `requires`, `dependencies`, `permissions`, `routes`, `migrations`, `services`, `hooks`, `menus`, `lifecycle`

### Discovery
- Scan `/lib/plugins/*/plugin.json`
- Validate manifest (required fields, valid JSON)
- Build in-memory registry

### Lifecycle
- **discovered** — valid manifest, not yet loaded
- **invalid** — manifest failed validation
- **enabled** — valid manifest, active
- **disabled** — valid manifest, not active

### Boot Flow (implemented)
1. Kernel loads config
2. Kernel discovers plugins from `/lib/plugins/`
3. Validates manifests
4. Checks dependencies
5. Registers plugin-declared permissions with Gate
6. Registers plugin-declared services in container
7. Registers plugin-declared hooks via HookRegistry
8. Registers plugin-declared menus via MenuRegistry
9. Stores registry in container (`$container->set('plugins', $registry)`)
10. Includes `routes.php` and registers manifest-declared routes
11. Kernel loads its own routes (web.php) and dispatches

### Plugin Directory Structure
```
lib/plugins/{Name}/
├── plugin.json        — manifest (required)
├── src/               — PHP classes (autoloaded)
│   ├── {Controller}.php
│   ├── {Service}.php
│   └── {Repository}.php
├── routes.php         — optional route registration hook
├── migrations/        — migration files (path relative to plugin root)
├── views/             — plugin-specific views
├── hooks.php          — optional hook registration hook
├── menus.php          — optional menu registration hook
└── README.md          — plugin documentation
```

### Handler Format for Plugin Routes
Plugin routes use the handler format: `Plugins\{Name}\{Controller}@method`
This resolves to `App\Plugins\{Name}\{Controller}` via the Router's handler resolution logic.

### Service Registration Format
```json
"services": {
    "service_key": {
        "class": "App\\Plugins\\{Name}\\{Class}",
        "singleton": true,
        "args": []
    }
}
```
Keys in `args` that match container bindings are resolved as dependencies.

### Lifecycle Hooks

Plugins can declare optional lifecycle hooks in `plugin.json` to execute custom code
at key moments:

```json
"lifecycle": {
    "install": "Plugins\\MyPlugin\\Lifecycle@install",
    "enable": "Plugins\\MyPlugin\\Lifecycle@enable",
    "disable": "Plugins\\MyPlugin\\Lifecycle@disable"
}
```

Each hook is a fully qualified class@method callback. The callback receives:

```php
public static function install(string $name, string $path, array $context, ?Container $container): void
```

| Parameter    | Description                              |
|--------------|------------------------------------------|
| `$name`      | Plugin name from manifest                |
| `$path`      | Plugin base directory                    |
| `$context`   | Additional context (currently empty array) |
| `$container` | Application DI container (optional)      |

#### Execution Timing

| Hook     | When Triggered                                           |
|----------|----------------------------------------------------------|
| `install` | After staged file copy, before catalog `is_installed` update |
| `enable`  | When catalog `is_enabled` transitions from 0 to 1         |
| `disable` | When catalog `is_enabled` transitions from 1 to 0         |

The install hook runs alongside the kernel's migration system. Migrations declared in the plugin's `plugin.json` execute after the lifecycle hook completes.

#### Safety Rules

- Hooks are **optional** — missing or malformed entries are silently ignored
- Callback must be in the `Plugins\` namespace (enforced at execution time)
- Class and method existence are validated before invocation
- Exceptions are caught and logged — hooks never crash the application
- Hooks run **after** the plugin is loaded into the registry (not during discovery)

### Extension Points (deferred)
- Plugin migration auto-execution (hook: `runMigrations()`)
- Plugin autoloader for additional directories
- Plugin activation/deactivation API
- Plugin marketplace / licensing

### Design Rules
- No plugin should break kernel if it fails
- Plugins must declare dependencies
- Plugins must be self-contained
- Plugin manifests are validated before any plugin code runs

---

## Theme System Design

### Goals
- Token-based styling
- Fully decoupled from components
- Easy theme switching

### Strategy
- LESS variables define theme
- Components consume variables only

### Rule
> Components must NEVER hardcode colors

---

## Layout System Design

### Purpose
Define reusable UI structures

### Example Regions
- Sidebar
- Topbar
- Content
- Footer
- Scripts

### Layout Override Resolution

Layout files are resolved in this order:
1. `app/Views/layouts/{name}.php` — app-level override
2. `lib/layouts/{name}.php` — kernel layout (future)
3. `app.php` — safe default

### Rule
> Layouts define structure, NOT behavior
> App-level layouts always override kernel layouts

### Layout Hook Registry

Plugins can inject content into layouts at named hook points.

#### Hook Names
- `layout.head` — inside `<head>`, after CSS links
- `layout.body.start` — immediately after `<body>`
- `layout.body.end` — immediately before `</body>`
- `panel.sidebar.before` — reserved for future sidebar injection
- `panel.sidebar.after` — reserved for future sidebar injection
- `panel.topbar.left` — reserved for future topbar injection
- `panel.topbar.right` — reserved for future topbar injection
- `panel.footer` — inside the page footer
- `dashboard.widgets` — reserved for future dashboard widgets

#### Registration
```php
HookRegistry::register('layout.head', function($ctx) { return '<meta ...>'; }, $priority);
```

#### Renderable Interface
Objects implementing `HookRenderable` can be registered directly:
```php
HookRegistry::register('layout.body.end', $widget);
```

### Rule
> Hooks must never crash the layout — errors are silently swallowed

---

## Menu System Design

### Purpose
Central location for registering and rendering named menus used across the application.

### Menu Locations
- `sidebar` — main sidebar navigation
- `topbar` — top bar actions
- `user-menu` — user dropdown menu
- `admin-menu` — administration submenu

### MenuItem Fields
- `name` — unique identifier within menu
- `label` — display text
- `url` — destination URL
- `icon` — Bootstrap Icons class
- `styleClass` — additional CSS class
- `permission` — required permission string
- `order` — sort priority (lower renders first)
- `parentId` — parent item for grouping
- `source` — plugin name or `core`

### Registration
```php
MenuRegistry::add('sidebar', new MenuItem('tasks', 'Tasks', '/tasks', 'bi bi-check2-square', null, 'tasks.manage', 20, null, 'tasks'));
```

### Plugin Manifest Support
Plugins can declare menu items in `plugin.json`:
```json
{
    "menus": [
        {
            "menu": "sidebar",
            "item": { "name": "tasks", "label": "Tasks", "url": "/tasks", "icon": "bi bi-check2-square", "permission": "tasks.manage", "order": 20 }
        }
    ]
}
```

### Filtering
Items are automatically filtered by the user's permissions at render time.

### Rule
> Menus must never expose items the user cannot access

---

## Authentication Design

### Core Concepts
- Users
- Groups
- Permissions
- Tokens

### Future Compatibility
- OAuth Server
- OAuth Client
- LDAP

### Rule
> Auth must be provider-agnostic

### Organization Scoping (Designed — Plugin Foundation)

Organizations are a designed optional plugin for grouping users and data.
Full design at [`docs/developer/organizations.md`](docs/developer/organizations.md).

**Design Constraints:**
- Organizations must NOT be mandatory in kernel core
- Auth system must remain compatible with optional organization scoping
- Kernel users table must NOT require organization columns at install time
- Organization scoping belongs in a plugin or application layer, not kernel core
- Future multi-tenant behavior must be compatible with optional organization support

**Concepts to support:**
- Internal organizations, Prospects, Clients, Freight forwarders
- Customs brokers, Customs offices, Vendors, Partners, Other business entities

**Database model:**
- `organizations` table (name, slug, type discriminator, active flag)
- `organization_users` pivot table (many-to-many, `is_default` for session scoping)
- `organization_roles` table (deferred to Phase 3)

**Scoping strategy:**
- Repository pattern (explicit `scopeOrganization()` on repositories) as default
- Optional middleware (`OrganizationMiddleware`) for cross-cutting scoping (opt-in)
- Session stores `org_default_{userId}` for active org resolution

**Key design decisions:**
- Multiple organizations per user (many-to-many, not single)
- Organization roles as a mapping layer over kernel permissions (not a replacement)
- Prospect/clients/vendors as `type` column on organizations (simple discriminator)
- API tokens remain organization-agnostic; scoping via request header or default org
- NOT full SaaS multi-tenancy (application-level scoping only, no database isolation)

**File structure:**
```
lib/plugins/Organizations/
├── plugin.json
├── src/
│   ├── OrganizationRepository.php
│   ├── OrganizationService.php
│   ├── OrganizationUserRepository.php
│   ├── OrganizationScoper.php
│   ├── OrganizationContext.php
│   └── OrganizationMigration.php
├── migrations/0001_create_organizations_tables.php
├── controllers/
└── views/
```

---

## Auth Features Design

The following auth features are designed in `docs/developer/auth-features.md`:

| Feature | Status | Phase |
|------|-----|---|-----|
| **Remember Me** | Implemented | Phase 2 (first slice) |
| **Forgot Password / Reset** | Implemented | Phase 2 |
| **Email Verification** | Implemented | Phase 2 |
| **User Registration** | Implemented | Phase 2 |
| **Two-Factor Authentication (2FA)** | Implemented | Phase 2 |

Design doc: [`docs/developer/auth-features.md`](docs/developer/auth-features.md)

### Core design decisions

- **Remember Me**: Database-backed long-lived token, cookie lifetime configurable, token rotation on each use, one-at-a-time.
- **Password Reset**: Email-based, single-use token, 60-minute expiry, stored in `auth_password_resets` table.
- **Email Verification**: Email-based, single-use token, 24-hour expiry, stored in `auth_email_verifications` table. Soft gate in Phase 1.
- **Registration**: Config-gated (`config/auth.php['registration']['enabled']`), disabled by default.
- **2FA**: TOTP (RFC 6238) only, per-user, 10 recovery codes, QR code onboarding.

### Mailer integration

All email-based features use the Mailer foundation. Templates: `password_reset`, `email_verification`.

---

## Registration Design

### Rule
> Registration is **disabled by default**. When enabled, it is controlled by a single configuration option.

### Configuration
- `config/auth.php` includes `'registration' => ['enabled' => false]`
- When `enabled` is `false`, the registration route returns 404
- When `enabled` is `true`, a registration form is shown at `/auth/register`

### Form Design (when enabled)
- Uses `blank.php` layout (same as login)
- Fields: username, email, password, password confirmation
- Server-side validation with keyed field errors
- On success: creates user, auto-logins, redirects to `/`
- On failure: re-renders form with error messages

### Security Considerations
- Rate limiting is planned (future)
- Email verification is planned (future)
- No open registration without admin approval (planned)

### Roadmap
- Phase 3: Basic registration form + config toggle
- Phase 4: Email verification + rate limiting

---

## Mailer Foundation

### Purpose

A pluggable mail transport system for Kernel-Web. Default transport uses PHP `mail()`. SMTP provided by a plugin.

### Architecture

```
[Messenger]
  Mailer → Transport (interface)
           ├── MailTransport (core, mail())
           └── SmtpTransport (plugin)

[Mailer → Templates]
  Mailer → TemplateRegistry → Template paths (core + plugins)

[Mailer → Settings]
  SMTP plugin → SettingsRegistry → system_settings table
```

### Design Decisions

- **Core** provides: `Mailer`, `MailMessage`, `Attachment`, `Transport` interface, `MailTransport` (mail()), `TemplateRegistry`, `MailerException`
- **Plugin** provides: SMTP transport, SMTP settings (via SettingsRegistry), email templates
- **Purpose-agnostic**: mailer knows nothing about forgot-password, verification, or notifications — those are auth/notification-layer concerns that compose `MailMessage` with templates
- **Error handling**: all send failures throw `MailerException`
- **Attachments**: supported by transport-agnostic `Attachment` value object; mail() transport throws if attachments requested
- **Queueing**: deferred — design supports a `QueuedTransport` decorator later

For the full design, see `docs/developer/mailer.md`.

---

## Landing Page Design

### Public Landing Page
- Shown at `/` before authentication
- Displays kernel status, documentation links, and next steps
- Minimal layout (no sidebar/topbar)
- Route: `GET /` → `HomeController@index` (priority 0, overridable)
- Install redirect: `GET /install` → `/install`
- Dashboard redirect: `GET /dashboard` → `/admin`

### Landing Page Override
- A plugin can override the landing page by registering `GET /` with priority 1
- The kernel landing page acts as a fallback only
- No hardcoded app-level override — any plugin can replace it

### Rule
> The landing page must remain public and lightweight
> The landing page must be overridable by plugins without kernel changes

### Goal
- Support paid plugins/apps

### Approach
- Central `LicensingService`
- Plugin-declared requirements

### Rule
> Licensing must NOT block development mode

---

## Routing Design

### Strategy
- Central router
- Plugin route injection

### Clean URL Convention
- Use path-based parameters: `/resource/{id}` instead of `/resource?id=1`
- RESTful structure: `/resource/create`, `/resource/{id}/edit`, `/resource/{id}/delete`
- JSON API routes use `/api/` prefix
- All routes declared in `routes/web.php` with middleware list

### Route Override Precedence

Routes have integer priority levels. Higher values win.

| Source | Default Priority |
|--------|-----------------|
| Plugin routes | `1` |
| Kernel core routes | `0` |

On dispatch, routes are sorted by priority (descending) before matching.
Same-path routes: higher priority always wins.

### Rule
> Routes must be declarative and traceable
> Kernel routes must always be overridable by plugins

---

## Service Container Design

### Goals
- Simple dependency injection
- No heavy framework

### Rule
> Services must be explicitly registered

---

## Database Design

### Default
- SQLite

### Future
- MySQL/MariaDB

### Rules
- Use PDO
- Use migrations
- Keep drivers isolated

---

## Security Design

### Web Root
- Only `/public` accessible

### Root .htaccess
- Redirects all requests to `/public/`
- Exempts `/.well-known/*` (Let's Encrypt, DNS-01)
- Sets `.env` MIME type to `text/html` to block direct access
- Unsets `Proxy` request header (prevents HTTP request smuggling)
- Adds `.mjs` MIME type for ES modules

### Protection
- `.htaccess` blocks sensitive dirs

### Validation
- Always validate:
  - input
  - file paths
  - permissions

---

## Documentation Organization

### Structure
```
docs/
├── developer/     — developer-facing documentation
│   ├── kernel/    — kernel architecture, schema, services
│   ├── plugins/   — plugin development docs
│   ├── layouts/   — layout/theme development docs
│   ├── templates/ — template docs (future)
│   ├── themes/    — theme docs (future)
│   └── installation/ — installation guides
├── user/          — user-facing documentation
│   ├── kernel/    — kernel user guides
│   ├── plugins/   — plugin usage docs
│   ├── layouts/   — layout usage docs (future)
│   ├── templates/ — template usage docs (future)
│   └── themes/    — theme usage docs (future)
└── reference/     — HTML reference docs
```

### Index Files
- `docs/index.md` — master documentation index
- `docs/developer/index.md` — developer docs index
- `docs/user/index.md` — user docs index

### Conventions Docs
- `docs/routing.md` — routing conventions

### Public Documentation Rendering (Planned)

A future **Documentation plugin** will render markdown from `/docs` into the
application at clean routes (`/docs/user/...`, `/docs/developer/...`).

It will:
- Replace broken markdown file links on the landing page
- Provide navigation for both user and developer docs
- Serve HTML output instead of raw `.md` files
- Live under `lib/plugins/documentation/`

Until implemented, landing page documentation cards that link to
Architecture, User Guide, and Plugins are placeholders.

### Conventions Docs (continued)
- `docs/override-system.md` — route and layout override
- `docs/menu-registry.md` — menu registry reference
- `docs/layout-hook-registry.md` — hook registry reference
- `docs/root-htaccess.md` — root .htaccess reference
- `docs/settings-hooks.md` — settings plugin hook system design

To be expanded later:

- OAuth server design
- Plugin marketplace
- Theme marketplace
- Distributed app architecture
- Multi-instance auth sharing
- Remote agents

---

## Post-Cleanup Architecture (Current)

After the NetMon cleanup pass, the kernel contains:

### Core (Kernel)
- Routing, service container, config system
- Database layer (PDO abstraction + SQLite driver)
- Migration system
- Authentication (users, groups, permissions, API tokens, sessions)
- Installer system
- Hook registry (layout.body.*, panel.* hook points)
- Menu registry (sidebar, topbar, user-menu, admin-menu)

### Plugins (Extracted from Core)
- **Notes** (`lib/plugins/notes/`) — polymorphic note annotations (first real plugin)
- **Tasks** (`lib/plugins/tasks/`) — polymorphic task management (second real plugin)

### Plugin Migrations
- Plugin migrations run via `SetupService::runPluginMigrations()` during install.
- Each plugin declares its migrations in `plugin.json` under the `migrations` key.
- Migrations are discovered by scanning each plugin's `migrations/` subdirectory.
- Migration runner (`MigrationRunner`) executes pending migrations in filename order.
- Kernel migrations run first, then plugin migrations.

### Modules (Future Plugins)
- Chat, Notifications, FileManager, Setup

### Admin (Kernel-Level)
- User management, Group management, Permission management
- System settings management

### Future
- Organizations (optional, plugin-based) — **plugin foundation implemented** in kernel core. Migrated to plugin on promotion.

All NetMon-specific infrastructure (devices, monitoring, alerts, discovery, topology) has been removed.
NetMon is a future application built on Kernel-Web, not part of the kernel itself.

For the full cleanup history, see `/docs/kernel-status.md`.

---

## Extensions System

### Purpose

Central admin area for browsing and managing application extensions:
- **Plugins** — feature modules in `lib/plugins/`
- **Themes** — visual styling modules in `lib/themes/`
- **Layouts** — page structure modules in `lib/layouts/`

### Discovery

Extensions are discovered read-only from their respective directories:

| Type | Directory | Manifest | Fallback |
|------|-----------|----------|----------|
| plugin | `lib/plugins/{Name}/` | `plugin.json` | directory name |
| theme | `lib/themes/{Name}/` | `theme.json` | directory name |
| layout | `lib/layouts/{Name}/` | `layout.json` | directory name |

Manifest format (theme/layout):
```json
{
    "name": "My Theme",
    "version": "0.1.0",
    "description": "Short description"
}
```

### Plugin Manifest

Plugins use `plugin.json` (already documented in Plugin System Design):
- `name` (required), `version` (required)
- `description`, `enabled`, `requires`, `dependencies`, `permissions`, `routes`, `migrations`, `services`, `hooks`, `menus` (optional)
- Invalid manifests show "Invalid" status with reason

### Status

| Status | Meaning |
|--------|---------|
| enabled | Valid plugin, currently active |
| disabled | Valid plugin, user disabled |
| discovered | Theme or layout found, no lifecycle management |
| invalid | Manifest missing or malformed |

### Admin Area

- Route: `GET /admin/extensions`
- Middleware: `WebAuth` + `WebPermission:extensions.manage`
- Displays summary counts, per-type tables with name, slug, version, description, status

### Read-Only Limitation (Current)

This pass is **read-only discovery and display only**. The following are **deferred**:
- enable / disable
- install / uninstall
- upload / marketplace
- remote updates
- licensing
- dependency resolution UI (blocking behavior on install/enable/disable/uninstall)
- theme switching
- layout switching

The **read-only dependency status display** (status badges on the catalog listing) is implemented and does not affect lifecycle behavior.

### Extension Catalog (Future Design)

The current Extensions system relies on loose JSON manifest files discovered from `lib/` directories.
A future centralized **Extension Catalog** will provide structured extension metadata storage,
online submission, review, and installation workflows.

#### Catalog Database

Stored in the same SQLite database as the rest of the kernel.
Created by migration `0049_create_catalog_extensions_table.php`.

**Table: `catalog_extensions`**

| Column | Type | Description |
|--------|------|-------|
| `id` | INTEGER PK | Auto-increment primary key |
| `name` | TEXT NOT NULL | Display name |
| `slug` | TEXT NOT NULL UNIQUE | URL-safe identifier |
| `type` | TEXT NOT NULL | `plugin`, `theme`, or `layout` (check constraint) |
| `version` | TEXT NOT NULL DEFAULT '0.0.0' | Semantic version string |
| `description` | TEXT NOT NULL DEFAULT '' | Short description |
| `author` | TEXT NOT NULL DEFAULT '' | Author / vendor name |
| `download_url` | TEXT NOT NULL DEFAULT '' | URL to zip archive |
| `repo_url` | TEXT NULL | Source repository URL |
| `requirements` | TEXT NOT NULL DEFAULT '[]' | JSON — PHP version, kernel version requirements |
| `dependencies` | TEXT NOT NULL DEFAULT '[]' | JSON — array of extension slugs required |
| `status` | TEXT NOT NULL DEFAULT 'pending' | `pending`, `approved`, or `rejected` (check constraint) |
| `is_installed` | INTEGER NOT NULL DEFAULT 0 | 0 or 1 |
| `is_enabled` | INTEGER NOT NULL DEFAULT 0 | 0 or 1 |
| `checksum` | TEXT NULL | SHA-256 of the distribution archive |
| `created_at` | VARCHAR(32) NOT NULL | Submission timestamp |
| `updated_at` | VARCHAR(32) NOT NULL | Last update timestamp |

**Constraints:**
- `slug` is unique across all types
- `type` is restricted to `plugin`, `theme`, `layout`
- `status` is restricted to `pending`, `approved`, `rejected`
- `is_installed` and `is_enabled` are boolean flags (0 or 1)
- `dependencies`, `requirements` stored as JSON strings

#### Online Submission Flow

1. **Submission** — developers submit extensions through an online admin form (requires `extensions.manage` permission)
   - Provide name, slug, type, description, version, author, download_url
   - Upload zip archive (stored temporarily)
   - Optional: repo_url, requirements, dependencies

2. **Validation** — kernel validates the submission:
   - Check manifest integrity (valid JSON)
   - Check version format (semantic versioning)
   - Check slug uniqueness
   - Verify zip archive structure matches declared type
   - Compute checksum of the archive

3. **Review** — submitted extensions enter `pending` review status
   - Reviewers inspect manifest, archive structure, dependency declarations
   - Status transitions: `pending` → `approved` or `rejected`
   - Approved extensions are visible in the catalog for install

4. **Installation** — approved extensions can be installed via the admin UI
   - Download from `download_url` or use uploaded archive
   - Verify checksum against stored checksum
   - Install to appropriate `lib/` directory
   - Set `is_installed = 1` and `is_enabled = 1`
   - Run migrations if declared in manifest

5. **Sync / Import** — catalog metadata can be synced from a remote catalog server
   - Remote server maintains authoritative extension listings
   - Local kernel periodically fetches updated catalog
   - New approved extensions appear in local catalog without manual submission

#### Catalog Admin UI (Future)

- `GET /admin/extensions/catalog` — browse catalog extensions (dependency status display implemented)
- `GET /admin/extensions/catalog/submit` — submission form
- `GET /admin/extensions/catalog/{slug}` — extension detail
- `POST /admin/extensions/catalog/{slug}/install` — install extension
- `POST /admin/extensions/catalog/{slug}/uninstall` — uninstall extension
- `GET /admin/extensions/catalog/review` — review pending submissions (admin-only)

### Design Rules

- Extensions must never crash the kernel if they fail
- Discovery must be read-only
- Admin UI must gate on `extensions.manage` permission

---

## Extension Dependency Resolver

### Purpose

Detect, validate, resolve, and enforce dependencies between extensions (plugins, themes, layouts).

### Dependency Key Format

Dependency keys use `type:slug` format in manifest and catalog:

```json
"dependencies": {
    "plugin:notes": ">=0.1.0",
    "theme:default": "^1.0.0"
}
```

**Rationale:** slug is unique in catalog (DB constraint); type prefix prevents cross-type collisions; display name can change without breaking resolution.

### Version Constraints

| Syntax | Example | Meaning |
|--------|---------|---------|
| Exact | `1.2.3` | Must match exactly |
| `>=` | `>=1.2.3` | Greater than or equal |
| `>` | `>1.2.3` | Greater than |
| `<=` | `<=1.2.3` | Less than or equal |
| `<` | `<1.2.3` | Less than |
| `^` | `^1.2.3` | Compatible with major (same major, >= given version) |
| `~` | `~1.2.3` | Compatible with patch (same major.minor, >= given version) |

Implemented via PHP's `version_compare()`. Malformed constraints are treated as unsatisfied (fail closed).

### Enforcement Rules

| Action | Check | Block if |
|--------|-------|-----|
| **Install** | dependency in catalog + approved | missing from catalog, pending, rejected, version mismatch, circular |
| **Enable** | dependency installed + enabled + version match | not installed, disabled, version mismatch |
| **Uninstall** | no installed extension depends on this one | another installed extension depends on it |
| **Disable** | no enabled extension depends on this one | another enabled extension depends on it |

### Resolver Service

`ExtensionDependencyResolver` — pure resolution service:

- `checkInstall()` — validate all dependencies before install
- `checkEnable()` — validate all dependencies before enable
- `checkUninstall()` — find reverse dependencies (installed extensions depending on this one)
- `checkDisable()` — find reverse dependencies (enabled extensions depending on this one)
- `detectCircular()` — DFS-based cycle detection

Returns `InstallResult { allowed: bool, blockers: list<Blocker> }`.

### Auto-Install

Not implemented in the first version. Missing dependencies produce blockers listed in the UI. The user must install them manually.

### Security

- No remote auto-install
- No code execution during resolution (data only)
- Fail closed on invalid format
- Dependency format validation on catalog submission

For full design, see `docs/developer/extensions/dependencies.md`.

---

## UI Design Standards

### DataTables Table Standard

Any interface that displays tabular data should use DataTables by default.

**Rules:**
- All admin and plugin tables should initialize DataTables where appropriate
- Table actions such as "Create", "Add", "Export", or similar should prioritize DataTables Buttons where appropriate
- Avoid placing unrelated action buttons randomly outside the table when they belong to table-level actions
- DataTables libraries are already loaded in the panel layout (jQuery 3.7.1, DataTables 2.3.8 + Buttons 3.2.6 + Responsive 3.0.8 + Select 3.1.3 + StateRestore 1.4.3 + RowGroup 1.6.0 + Scroller 2.4.3 + ColumnControl 1.2.1)
- Initialize via `$.fn.dataTable()` or the shared `datatables-init.js` helper

**Exception:** Lists that are not tabular (e.g., notification feeds, chat messages) do not require DataTables.

### Topbar User Menu

The panel layout includes a user menu in the topbar (topbar-actions dropdown, right side).

**Currently implemented:**
- User avatar (first letter of display name)
- Username display
- Profile trigger button (opens modal at `/api/profile`)
- Admin panel shortcut (visible only to users with `admin` or `admin.access` permission, points to `/admin`)
- Sign out button (`/auth/logout`, POST)

**Rendering:**
- Rendered via `partials/user-menu.php` (used by both `panel.php` and `app.php`)
- Profile button triggers the Bootstrap modal (not a page navigation)
- Modal content loaded via `/api/profile` (safe user fields)

The user menu is rendered in `panel.php` and populated via `MenuRegistry` with a `user-menu` menu location.

### Panel Breadcrumbs

**Currently implemented.**

The reusable `panel` layout renders breadcrumbs via direct Bootstrap breadcrumb markup (not `MenuHelper`).

**Rules:**
- Controllers pass `$breadcrumbs` to the view as an array of `['label' => string, 'url' => string|null]`
- `url => null` marks the current (active) page
- First entry is the admin landing page (`/admin`), rendered as a home icon link
- Admin pages provide breadcrumb data from their controller
- Application/plugin pages also provide breadcrumb data
- Breadcrumbs are shown consistently near the top of the content area
- Breadcrumb generation remains simple and explicit — no dynamic breadcrumb registry

### Layout Regions (Summary)

| Region | Location | Purpose |
|--------|----------|---------|
| Sidebar | Left panel | Navigation (via MenuRegistry `sidebar`) |
| Topbar | Top bar | Title, theme toggle, notifications, user menu |
| Content | Main area | Page-specific content |
| Footer | Bottom of page | Copyright, plugin hooks |

### Profile Modal

A Bootstrap modal (not a navigation-based page) displays user profile information and extends via plugins.

**Modal Structure (UI Choice — Tabs)**

Use Bootstrap tabs inside the modal body. Tabs are preferred over accordions because:
- Users can switch between sections without collapsing current content
- Tab state persists naturally via Bootstrap's JS
- Plugins can add tabs without layout conflicts
- Fits the modal's constrained height better than scrollable accordion

| Tab | Name | Content | Source |
|-----|------|---------|--------|
| Overview | `overview` | Basic user info (username, email, display_name, created_at, updated_at) | Kernel core (always present) |
| API Tokens | `tokens` | Active tokens list with create/revoke | Kernel core (always present) |
| `{slug}` | `{slug}` | Plugin-provided sections (e.g. Notification Preferences) | Plugins (optional) |

**Plugin Hook: `profile.sections`**

Plugins register additional tabs via a new registry:

```php
ProfileModal::addSection('notifications', 'Notifications', 'notifications', 20);
```

The `profile.sections` collection is rendered server-side during modal init. Each section declares:
- `name` — unique slug
- `label` — tab display text
- `content` — view path or callback that returns HTML
- `order` — sort priority (lower renders first)
- `permission` — optional required permission
- `source` — plugin name or `core`

**Safe Data Shape (Overview tab)**

The `/api/profile` endpoint returns an explicit whitelist of user fields:

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | User ID |
| `username` | string | Login name |
| `email` | string | Email address |
| `display_name` | string | Display name (may be empty) |
| `created_at` | string | Account creation timestamp |
| `updated_at` | string | Last modification timestamp |

No other user fields are returned. `password_hash`, `token`, and `secret` columns are never included. If new fields are added, review against: could this expose sensitive data?

**User Isolation**

Each user can only view their own profile data. The `/api/profile` endpoint always returns the authenticated user — there is no user ID parameter. Cross-user profile access is impossible because the endpoint does not accept a user selector.

**Content Loading Strategy**

- The **Overview** tab content renders server-side (always available)
- Additional tabs load via AJAX on first tab switch (lazy)
- Cached in DOM after first load (no repeated requests)
- Falls back to "Could not load section" on error

**Token Data Security**

Token list endpoints (GET `/api/profile/tokens`) never return the raw token string. Only the hashed display value and metadata are shown. The raw token is only returned once at creation time (POST `/api/profile/tokens`), consistent with `TokenService::generate()`'s design. This prevents raw token exposure through API responses, logs, or localStorage.

**API Design**

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/profile` | GET | SessionAuth | Returns safe user fields |
| `/api/profile/sections/{slug}` | GET | SessionAuth | Returns HTML fragment for section tab |
| `/api/profile/tokens` | GET | SessionAuth | Returns active token list (JSON) |
| `POST /api/profile/tokens` | POST | SessionAuth | Creates new token (delegates to TokenService) |
| `DELETE /api/profile/tokens/{id}` | DELETE | SessionAuth | Revokes token (delegates to TokenService) |

The `/api/profile` endpoint (already implemented in `AuthController@profile`) returns safe user fields. No additional fields should be added without reviewing for sensitive data exposure.

**Security Rules**

- Server-side **safe field filter** — only explicitly allowed fields are returned (never `password_hash`, `token`, or `secret` columns)
- All API endpoints require **SessionAuth** middleware
- Token operations verify **user ownership** before allowing create/revoke
- Plugin sections gate on **declared permission** (rendered conditionally)
- Modal JS never stores sensitive data in `localStorage` or cookies
- Error responses return generic messages (never leak stack traces or field names)

**Extensibility**

Third-party plugins can add profile sections:

```json
// plugin.json
{
    "hooks": ["profile.sections"],
    "permissions": ["profile.notifications"]
}
```

```php
// hooks.php
ProfileModal::addSection(
    name: 'notifications',
    label: 'Notifications',
    content: fn() => require __DIR__.'/views/profile/notifications.php',
    order: 20,
    permission: 'profile.notifications',
    source: 'notifications'
);
```

The modal renders plugin sections as additional tabs. Permission filtering happens at render time — hidden tabs never appear in the DOM.

**Modal JavaScript**

- Triggered by the `js-profile-trigger` button in the user menu
- Loads Overview immediately, other sections lazily on tab click
- Stores loaded section HTML in a `Map` keyed by slug (cache in DOM)
- Displays loading spinner during AJAX fetch
- Handles errors gracefully with inline alerts

**Design Patterns**

- Use Bootstrap 5 components where available
- Use Bootstrap Icons for icons
- Dark mode support via `data-bs-theme` attribute
- Theme persistence via `localStorage`
- Maintain responsive design across breakpoints
- Follow the modal structure and plugin hook system documented here
- All user-facing profiles use the modal — not navigation-based pages

### Theme Preview Page

A theme preview page provides a comprehensive view of Bootstrap components rendered with the current theme.

**Purpose:**
- Help developers review styling during theme development
- Help users/admins preview themes before applying them
- Serve as a visual regression / smoke-test page for themes
- Provide a consistent reference for component styling across themes

**Design Direction:**
- May live under admin or theme preview routes (e.g., `/admin/themes/preview` or `/preview`)
- Should be available outside developer mode if safe (no sensitive data exposure)
- Should not expose sensitive data (real user data, passwords, tokens, etc.)
- Should support theme switching via a query parameter (e.g., `?theme=dark`) to compare themes without applying
- Should use the panel/layout/theme system for rendering
- All components should use placeholder/safe data only

**Bootstrap Components to Include:**

| Category | Components |
|---|---|
| Typography | Headings, lead text, blockquote, list group, code, table |
| Buttons | Primary/secondary/success/danger/warning/info/light/dark, outline variants, sizes, pill/toggle |
| Alerts | Primary/success/danger/warning/info/light/dark, dismissible, with icons |
| Badges | All contextual variants, pills |
| Cards | Text, images, horizontal, grid layouts |
| Forms | Text input, textarea, select, checkbox, radio, switch, range, file input, input group |
| Tables | Striped, hover, responsive |
| Navs | Tabs, pills, vertical tabs, breadcrumb, pagination |
| Modals | Basic, centered, scrollable, form-in-modal |
| Dropdowns | Menu items, divider, button dropdown |
| Accordions | Collapsible sections |
| Progress | Basic, striped, animated |
| Toasts | Dismissible toast notifications |

**Theme Switching (Future):**
- Query parameter `?theme={slug}` swaps the active theme for preview only
- Does not persist or modify any server-side state
- Useful for comparing multiple themes side-by-side during development

**Security:**
- All data must be safe placeholders
- No real user names, emails, or sensitive content
- No authentication required if served publicly, but the page should be usable without auth for theme development

---

## CSS Compilation System

### Overview

Kernel-Web uses a hybrid approach for CSS delivery:

| Mode | Source | How |
|------|--------|-----|
| Production | npm-compiled static CSS (`public/assets/css/app.less → app.css`) | `npm run build:css` |
| Development | Dynamic merge — static kernel CSS + parsed theme/layout/plugin LESS | `GET /css` route |

### Route

- **Path:** `GET /css`
- **Auth:** None (public route — stylesheets must be loadable by unauthenticated visitors)
- **Content-Type:** `text/css`
- **Cache-Control:** `public, max-age=3600`
- **Controller:** `CssController@show`
- **Service:** `LessCompiler` (in `app/Services/LessCompiler.php`)

### Load Order

1. **Kernel base CSS** — static `public/assets/css/app.css` (compiled from `public/assets/less/app.less`)
2. **Theme LESS** — active theme's `less/app.less` (parsed and merged)
3. **Layout LESS** — active layout's `app.less` (parsed and merged)
4. **Plugin LESS** — enabled plugin's `less/app.less` (parsed and merged)

### Import Order (kernel base LESS source)

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

### Why Hybrid?

The kernel base LESS uses `calc(var(--css-var) / 2)` and other modern CSS
features not supported by wikimedia/less.php (lessphp). The npm/Node.js LESS
compiler handles all features correctly. The PHP layer augments the static
kernel CSS with dynamic theme/layout/plugin LESS.

### Layout Integration

All layouts reference `/css` instead of a static file:

```html
<link rel="stylesheet" href="/css">
```

Files updated:
- `app/Views/layouts/panel.php`
- `app/Views/layouts/app.php`
- `app/Views/layouts/blank.php`
- `app/Views/auth/login.php`

### Build Commands

| Command | Description |
|---------|-------------|
| `npm run build:css` | Compile LESS → static CSS (production) |
| `npm run watch:css` | Watch and rebuild on changes (development) |

---

## Administration System Design

### Purpose

The administration system provides user and group management, permission management, system settings, and extension management.

### Admin Layout

- Route prefix: `/admin/*`
- Middleware: `WebAuth` + `WebPermission:admin.access` (or equivalent per-resource)
- Uses the `panel.php` layout
- Admin sidebar populated via MenuRegistry (`admin-sidebar`)

### Updates Module (Partial — Extension Update Checks)

Extension update checks compare installed extension versions against catalog versions and surface available updates in the admin UI.

**Scope:** Local-only (Phase 2). No remote sync, no auto-install.

#### Update Status Types

| Status | Key | Condition |
|--------|-----|-----------|
| Up-to-date | `up_to_date` | installed version equals catalog version |
| Update available | `update_available` | catalog version is newer (by semver) |
| Newer than catalog | `newer_than_catalog` | installed version is newer than catalog version |
| No catalog entry | `no_catalog_entry` | installed extension has no catalog record |
| Blocked | `blocked` | catalog version newer but dependency constraint not satisfied |
| Invalid | `invalid` | on-disk manifest is missing or invalid |

#### Data Model

No new columns in Phase 2. The existing `catalog_extensions.version` field is used as the latest available version for local-only mode.

A future `available_version` column (for remote sync) is deferred to Phase 3. See `docs/developer/extensions/updates.md`.

#### Service

`ExtensionUpdateChecker` — pure comparison service:

- `checkAll()` → `ExtensionUpdate[]` keyed by slug
- `check(string $slug)` → `?ExtensionUpdate`
- Reads installed version from on-disk manifest
- Reads latest version from `catalog_extensions.version` (local-only)
- Uses `version_compare()` + `ExtensionDependencyResolver` for constraint checking
- Checks both installed extension's dependencies and new version's dependencies against current catalog state

#### UI

- "Local Version" column on catalog table (from on-disk manifest)
- "Update" status badge column
- "Update" action button (only when `update_available` + no blockers)
- Same staged install workflow, just triggered by update button

#### Design Rules

- Update checks are read-only — no filesystem writes
- No network calls in local-only mode
- Admin must have `extensions.manage` permission
- Auto-install is **not** enabled — updates are informational only
- Dependency constraints can block update status (both installed extension's deps and new version's deps)
- Update action reuses existing staged install workflow
- Update preserves the existing `is_enabled` state

For full design, see `docs/developer/extensions/updates.md`.

---

## Developer Mode Design

### Purpose

Developer mode provides additional tools available only during development.

### Trigger

Developer mode is activated when `config/app.php` `debug` is `true` (controlled by `APP_DEBUG` environment variable).

### Developer Tools

When enabled, the following tools may be available:

| Tool | Description |
|------|------|------|
| Plugin scaffold | Create a new plugin scaffold |
| Theme scaffold | Create a new theme scaffold |
| Layout scaffold | Create a new layout scaffold |
| Example templates | Copy example code/templates |
| Repository config | Configure or initialize a local repository |
| Extension assist | Assist with extension development |

**Rules:**
- Developer tools should only appear in developer mode
- Developer tools should not appear in production
- Developer tools must not expose unsafe actions publicly
- Developer tools should be gated behind `extensions.manage` or a dedicated `dev.tools` permission
- No developer tool should perform destructive operations without confirmation

### Debug Logging

When debug mode is enabled, the `DebugAuditLogger` service captures developer diagnostics into the `admin_audit_log` table.

**Service:** `App\Services\DebugAuditLogger` — registered in the container via `public/index.php` after config is loaded.

**API:**
- `DebugAuditLogger::auth($userId, $action, $meta)` — auth category (e.g., `login.2fa_required`)
- `DebugAuditLogger::config($userId, $action, $meta)` — config category
- `DebugAuditLogger::plugin($userId, $action, $meta)` — plugin category
- `DebugAuditLogger::route($userId, $action, $meta)` — route category
- `DebugAuditLogger::generic($userId, $action, $meta)` — custom category

**Security:**
- All sensitive keys are redacted: password, passwd, secret, token, key, cookie, authorization, csrf, session, recovery
- Large payloads (>2000 bytes) are truncated with a `_truncated` flag
- Debug entries have `entity_type = 'debug'` and `entity_id = 0`

**UI:** Debug entries in `/admin/audit` are visually distinguished with a `table-info-subtle` row background and a "debug" badge.

---

## Scaffold Generator Design

### Purpose

Accelerate creation of new extensions (plugins, themes, layouts) by generating consistent, safe template scaffolds.

### Output Location

Scaffolds are written to `/storage/extension-staging/{slug}/` (not directly to `/lib/`).

**Rationale:**
- Staging is atomic — partial generation doesn't corrupt `/lib/`
- Matches the existing catalog install workflow (which expects files in staging)
- User can review before install
- Rollback is safe (delete staging dir)

### Template Location

Templates live in `/resources/scaffolds/{type}/` (version-controlled):

```
resources/scaffolds/
├── plugin/          — plugin.json, src/, routes.php, migrations/, views/, README.md
├── theme/           — theme.json, less/app.less, README.md
└── layout/          — layout.json, app.php
```

### Placeholder Variables

Templates use `{{variable}}` placeholders:

| Variable | Example | Description |
|--|--|----|
| `{{name}}` | `My Extension` | Display name |
| `{{slug}}` | `my-extension` | URL-safe identifier (lowercase, hyphens) |
| `{{Namespace}}` | `MyExtension` | PascalCase class prefix |
| `{{author}}` | `Jane Developer` | Author name |
| `{{version}}` | `0.1.0` | Initial version |
| `{{description}}` | `A sample extension` | Short description |
| `{{plural_slug}}` | `my-extensions` | Pluralized slug for table names |
| `{{lower_slug}}` | `my_extension` | Snake_case slug for DB names |

### Slug Validation

Slugs must match `^[a-z][a-z0-9-]*$`, 1-63 chars, no existing directory at target path.

### Safety Rules

1. Developer mode only (`APP_DEBUG=true`)
2. Admin permission required (`WebAuth` + `WebPermission:admin`)
3. No path traversal (slug regex + directory check)
4. No overwriting existing directories
5. No secrets in templates
6. No automatic git commits
7. Preview before generate (file list for review)
8. No filesystem paths exposed in UI

### Future UI Flow

1. `GET /admin/developer/scaffold` — choose scaffold type
2. Fill metadata form (name, slug, version, description, author)
3. (Optional) Fill type-specific fields (permissions, routes, menus, etc.)
4. Preview files to be generated
5. Confirm generation
6. Show success with generated file list and next steps

For full design, see `docs/developer/scaffolds.md`.

---

## Kernel / Application / Extension Versioning

### Version Sources

| Layer | Source | Notes |
|------|--------|------|
| **Kernel** | `VERSION` file (primary), `composer.json` → `version` (fallback) | Canonical version for the framework |
| **Application** | `config/app.php` (`name` / `version`) or `env()` | Independent from kernel; set by consuming app |
| **Extension** | On-disk manifest (`plugin.json`, `theme.json`, `layout.json`) → `version` | Already established, no change |

### Kernel Compatibility

Extensions declare their supported kernel version range via a new `requires.kernel` field in their manifest:

```json
{
    "requires": {
        "php": "8.1",
        "kernel": ">=2.0.0 <4.0.0"
    }
}
```

**Optional field** — if omitted, the extension is assumed compatible with all kernel versions.

**Constraint format:** space-separated (AND logic), operators: `>=`, `>`, `<=`, `<`, `=`, `^`, `~`. Same semantics as Composer's version constraints.

**Catalog storage:** The existing `catalog_extensions.requirements` JSON field stores the kernel constraint alongside PHP requirements.

### Compatibility Enforcement

| Scenario | Behavior |
|------|-----|
| Install with kernel mismatch | **Block** — clear error message |
| Enable with kernel mismatch | **Block** — clear error message |
| Boot with kernel mismatch | **Warn** — log entry, allow loading |
| Update check shows mismatch | **Warn** — display alongside update status |

### Version Provider

A `VersionProvider` service resolves kernel version (VERSION file → composer.json fallback) and application version (config → defaults). It also provides kernel compatibility checking against extension constraints.

Full design at [`docs/developer/versioning.md`](docs/developer/versioning.md).

### Admin Overview Display

The admin landing page shows:
- Kernel version (vX.Y.Z)
- Application name + version
- Extension update count (available / blocked badges)
- "Update check not configured" when no remote source

### Design Rules

- Kernel version from composer.json (primary) or VERSION file (fallback)
- Application version is independent from kernel
- Missing `requires.kernel` = compatible with all versions (backward compatible)
- No new database columns needed (use existing `requirements` JSON)
- Install/enable blocks on mismatch; boot-time warns
- Reuse `ExtensionDependencyResolver` for constraint comparison
- Admin overview works in local-only mode

---

## Global View Context Design

### Problem

Controllers set `$principal`, `$permissions`, `$appName`, `$displayName` etc. in local scope via `ctx()`, but these values are **not consistently available** to layouts and partials. Key gaps:

- `$config` is set by controllers locally (from container) but never extracted into the layout scope
- `$auth` / `AuthService` is in the container, never in view scope
- `$user` is set by some controllers but not all
- `ViewGlobals::varsFromScope()` captures `$principal` and `$permissions` from `get_defined_vars()`, but relies on `$config` being in scope for dev-tools and other partials
- Defensive fallbacks in partials silently return on missing globals, masking the real problem

The core framework (`/Users/louis/Projects/LaswitchTech/core/src/Bootstrap.php`) solves this with **global objects**:

```php
global $CONFIG, $AUTH, $REQUEST, $OUTPUT, $DATABASE, $STYLE, $BUILDER,
        $HELPER, $MODEL, $CSRF, $LOG, $NET, $SMS, $SMTP, $IMAP,
        $UUID, $ENCRYPTION, $SLS, $INSTALLER, $ROUTER, $API, $CLI;
```

Each layout template accesses these as instance properties: `$this->Config`, `$this->Auth`, `$this->Request`, etc. (in the old core), or as global variables. This is a **centralized, guaranteed** pattern — every template gets the same objects.

### Design: Global View Context Layer

Kernel-Web needs a **single entry point at the top of every layout** that guarantees all views and partials receive the same baseline context. This replaces the scattered `ViewGlobals::varsFromScope()` approach.

#### Global Objects (inspired by core framework)

| Object | Class | Purpose |
|--------|-------|---------|
| `$Kernel` | KernelContext | Kernel-level services: container, plugin registry, hook registry |
| `$Auth` | AuthService / null | Authentication and authorization service (null when guest) |
| `$Config` | array (merged) | Unified config: app, auth, mail, db, debug flags |
| `$Layout` | LayoutContext | Layout/view helpers and state: title, activeSection, breadcrumbs, displayName |
| `$Router` | RequestContext | Routing info: URI, method, params, query |
| `$User` | array\|null | Logged-in user data, guest-safe. Always an array with `id`, `username`, `email`, `display_name`, `is_active` or `null`. |
| `$Permissions` | array | User's permissions (empty array when guest) |
| `$Helper` | array | Internal helpers and extension/plugin helpers registry |

#### Global Variables (derived from objects)

| Variable | Type | Source | Description |
|----------|------|--------|-------------|
| `currentUserId` | int | `$User` | User ID (0 when guest) |
| `currentUsername` | string | `$User` | Username |
| `currentUserEmail` | string | `$User` | Email |
| `currentUserDisplayName` | string | `$User` | display_name → name → username → email chain |
| `currentUserPermissions` | array | `$Permissions` | Permission names |
| `currentUserIsAdmin` | bool | `$Permissions` | admin or admin.access check |
| `appName` | string | `$Config` | Application name |
| `appConfig` | array | `$Config['app']` | App-level config |
| `pageTitle` | string | `$Layout` | Page title |
| `breadcrumbs` | array | `$Layout` | Breadcrumb data |
| `flash` | array\|null | Session | Flash message data |

#### Resolution Order (same as current ViewGlobals for backward compat)

**User resolution:**
1. `$scope['user']` — legacy `$user` variable
2. `$scope['principal']['user']` — controller principal
3. `$scope['currentUser']` — already-set variable
4. `$Auth->user()` — AuthService fallback
5. Guest defaults

**Permissions resolution:**
1. `$scope['permissions']` — legacy `$permissions` variable
2. `$scope['principal']['permissions']` — controller principal
3. Empty array

#### Layout Entry Point Pattern

Every layout begins with a single guaranteed-context block:

```php
<?php
// ── Guaranteed Global View Context ──
use App\Core\{ViewGlobals, KernelContext, LayoutContext};

$__ctx = get_defined_vars();

// Resolve $Auth from container (set in public/index.php)
$__auth = ($$__ctx['container'] ?? null)?->get('auth') ?? null;

// Extract all globals in one call
$__globals = ViewGlobals::globalContext($__auth, $__ctx);
extract($__globals);
?>
```

`ViewGlobals::globalContext()` returns the full set of global objects and variables in one call. Every layout uses the same pattern.

#### Layout `$Layout` State

Each layout sets `$Layout` before including the view:

```php
$Layout = new LayoutContext([
    'title' => $pageTitle,
    'activeSection' => $activeSection ?? null,
    'breadcrumbs' => $breadcrumbs ?? [],
    'displayName' => $displayName ?? null,
]);
```

#### Kernel `$Kernel` State

Available at `$Kernel` — provides access to container, plugins, hooks:

```php
$Kernel = new KernelContext($container);
// $Kernel->container() — access DI container
// $Kernel->plugins() — plugin registry
// $Kernel->hooks() — hook registry
```

### Why This Fixes the Bugs

1. **dev-tools disappeared**: `$config` / `$Config` is now always available as a global (merged from container), no more missing config
2. **User menu breaks**: `$currentUserDisplayName`, `$currentUserEmail`, `$currentUserPermissions` are always populated from the guaranteed context
3. **$config not available**: `$Config` is resolved from container at the top of every layout
4. **No more scattered fallbacks**: Partial should not need to defensively check for every variable — the context layer guarantees them
5. **Consistent across controllers**: Every controller route goes through the same layout entry point

### Migration from Current Approach

- `ViewGlobals::varsFromScope()` is preserved for backward compatibility but deprecated in favor of `ViewGlobals::globalContext()`
- Controllers continue to set `$principal`, `$permissions`, `$config` via `ctx()` — these flow into `globalContext()` via `get_defined_vars()`
- Partial files (user-menu, dev-tools) access globals directly: `$Config`, `$Auth`, `$User`, `$currentUserDisplayName`, etc.
- No controller changes required — the fix is at the layout layer

### Design Rules

1. **No partial should silently return when a global is missing** — the context layer guarantees globals. If a partial encounters a missing global, that's a context bug to fix, not a partial bug to work around.
2. **All layouts use the same entry point pattern** — no custom scope resolution per layout
3. **Guest-safe always** — `$User` is always a valid array (with safe defaults) or null; `$Permissions` is always an array
4. **Container is the source of truth** — `$Auth` comes from container, `$Config` comes from container; never re-resolve from session
5. **`$Helper` is lazy** — helper objects are instantiated on first access, not eagerly loaded

---

## Future Agents System

Kernel-Web will eventually support an Agents plugin or agent-management subsystem.

### Purpose

The Agents system will coordinate AI-assisted work across Kernel-Web applications.

Possible roles:

- Project Manager Agent
- Coder Agent
- Reviewer Agent
- Documentation Agent
- Infrastructure Agent
- Business Operations Agent
- Content Agent

### Initial Data Concepts

```text
agents
agent_profiles
agent_permissions
agent_runs
agent_messages
agent_tasks
agent_artifacts
agent_approvals
```

### Agent Run Tracking

Each agent run should be traceable:

* Agent/profile
* Project/application context
* Prompt/input
* Output/summary
* Tool activity
* Files touched, if applicable
* Tests run, if applicable
* Approval status
* Error/failure state
* Timestamps

### Approval Gates

High-risk actions should require explicit human approval:

* Git commits
* Deployments
* Deleting records
* Sending external emails/SMS
* Changing security settings
* Modifying billing/payment records
* Updating production configuration

### Relationship to External Agents

Kernel-Web does not need to replace tools like Claude Code, Hermes, Ollama, or n8n.

Instead, Kernel-Web should become the coordination and state layer:

```text
Kernel-Web PM App
   ↓
Task / Run / Approval Records
   ↓
External Agents
   ↓
Results / Logs / Summaries
   ↓
Kernel-Web Audit + Roadmap State
```

## Design Evolution Rule

Whenever a structural or architectural change is made:

1. Update this file
2. Update `/docs` if needed
3. Keep consistency across modules

---

## Relationship with CLAUDE.md

- `CLAUDE.md` → HOW to work
- `DESIGN.md` → WHAT we are building

Both must stay aligned.

---

## Project Maturity

Kernel-Web is actively under development. The following subsystems have been implemented but are not yet considered production-stable.

### Plugin system
Implemented: discovery, manifest validation, lifecycle hooks (install/enable/disable), dependency resolver, update checker, scaffold generator, catalog install workflow. **Not frozen**: manifest fields, hook names, and lifecycle signatures may change.

### Theme system
Implemented: Bootstrap 5 base, LESS variables, dark/light mode, theme preview page. Dynamic LESS compilation via `/css` route. **Not frozen**: variable names, token structure, and compilation pipeline may change.

### Layouts
Implemented: `panel.php` (admin), `app.php` (app), `blank.php` (auth). Global View Context stabilized. **Not frozen**: layout regions, hook points, and context variables may change.

### Audit / Debug tooling
Implemented: `admin_audit_log` table with append-only rows, `DebugAuditLogger` service (APP_DEBUG-gated), debug filter in `/admin/audit`. **Not frozen**: log format and filtering API may change.

### Configuration pipeline
Implemented: `config/app.php` (env-driven) → `config/local.php` (array_replace_recursive) → `.env` → hardcoded defaults. Flat and nested config supported. Debug flags are file-backed, not database-driven. **Not frozen**: config resolution order and key format may change.

### Admin-editable config
Implemented: `/admin/settings` writes core config keys (app.name, app.url, developer.*) and 2FA enforcement to `config/local.php` via `ConfigOverrideService`. AJAX toggle at `POST /admin/settings/toggle`. Plugin sections still use SystemSettingService (deferred to later Phase 2b migration). **Not frozen**: migration path for plugin sections not yet implemented.

### Config override system
Implemented: `ConfigOverrideService` (`app/Services/ConfigOverrideService.php`) writes admin-driven settings to `config/local.php` using dot-notation keys (e.g. `auth.two_factor.enforced`). All UI-driven overrides MUST write here — never to the database, never to `.env`, never to base config files. Atomic write via temp file + rename.

**Admin UI** (`/admin/settings`): Core config keys (app.name, app.url, developer.*), 2FA enforcement toggle (`auth.two_factor.enforced` via AJAX POST `/admin/settings/toggle`), and plugin sections (still via SystemSettingService — deferred migration). Card-based layout with icons, consistent spacing, and AJAX toggle feedback. **Not frozen**: write format may change.

### Configuration vs. Runtime State

All application configuration MUST be file-backed. The database (`system_settings` table) is deprecated for config storage and should only be used for true runtime/user state.

| Category | Storage | Examples | Who writes |
|----------|---------|----------|------------|
| Application config | `config/local.php` | `app.name`, `app.url`, `app.debug`, `auth.*`, `mail.*` | Admin (UI → ConfigOverrideService) or manual |
| Environment values | `.env` | `APP_NAME`, `APP_DEBUG`, `APP_URL` | Deployer |
| Plugin config (non-sensitive) | `config/local.php` | `smtp.host`, `smtp.port`, `telico.caller_id` | Admin (UI → ConfigOverrideService) |
| Plugin secrets (sensitive) | Encrypted DB column or `config/local_secrets.php` | `smtp.pass`, `telico.sms_password` | Admin (UI → encrypted storage) |
| User runtime state | `system_settings` table (per-user) | `app.preferences.theme` | User session / user prefs |
| Plugin runtime state | `system_settings` table | `notifications.last_sent_at` | Plugin service |

**Rule:** If a setting applies to the entire instance (not per-user) and does not contain secrets, it is CONFIG and must go in `config/local.php`. The `system_settings` table is reserved for per-user runtime state only.

See `/docs/developer/config-override-audit.md` for the full audit.

### Config precedence reference

| Layer | Purpose | Example key format | Who controls |
|-------|---------|--------------------|--------------|
| `config/app.php` | Defaults (committed to VCS) | `app.debug` | Developer (code) |
| `.env` | Environment/bootstrap values | `APP_DEBUG` | Deployer (env) |
| `config/local.php` | Instance/admin overrides | `auth.two_factor.enforced` | Admin (UI or manual) |
| SystemSettingService | DB runtime settings (deprecated in favor of file overrides) | `app.name` | Admin (DB, legacy) |

`config/local.php` overrides `config/app.php` via `array_replace_recursive`. It is generated from the admin settings page and should not be edited by hand except for emergency recovery.
