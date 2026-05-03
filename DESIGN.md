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

## Plugin System Design

### Manifest Format

Each plugin has a `plugin.json` at its root:

```json
{
    "name": "plugin-name",
    "version": "0.1.0",
    "description": "Short description",
    "enabled": true,
    "requires": { "kernel": "8.1" },
    "dependencies": { "dependency-name": ">=0.1.0" },
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
- dependency resolution UI
- theme switching
- layout switching

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

- `GET /admin/extensions/catalog` — browse catalog extensions
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
