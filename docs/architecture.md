# Kernel-Web Architecture

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ (no framework) |
| Database | SQLite (default); MySQL/MariaDB (future) |
| Frontend | JavaScript (fetch API), Bootstrap 5, Bootstrap Icons (not yet implemented) |
| Web server | Apache / nginx (or PHP built-in server for development) |

---

## Architectural Layers

The codebase is divided into three distinct layers. The separation is enforced by **namespace and directory convention**, not a framework rule. Each layer has a clear purpose and portability level.

```
┌──────────────────────────────────────────────────────┐
│               Application Layer                      │
│    app/Controllers/  app/Modules/                    │
│    routes/web.php · config/                          │
├──────────────────────────────────────────────────────┤
│               Module Layer                           │
│    app/Auth/  app/Middleware/  app/Models/           │
│    app/Modules/{ModuleName}/  app/Controllers/       │
│    (reusable feature modules, ship with platform)    │
├──────────────────────────────────────────────────────┤
│               Framework Kernel                       │
│    app/Core/                                         │
│    (zero app-specific knowledge)                     │
└──────────────────────────────────────────────────────┘
```

---

### Layer 1 — Framework Kernel (`app/Core/`)

**Namespace:** `App\Core\`
**Rule:** contains zero application-specific knowledge.
**Portability:** can be lifted into any PHP project without modification.

| Class | Purpose |
|---|---|
| `Env` | Minimal `.env` file parser; populates `$_ENV` and `getenv()` |
| `Container` | Key/value service registry |
| `Config` | Config loader with `.env` + `config/local.php` merge support |
| `Router` | HTTP method + path → middleware stack + controller |
| `Controller` | Abstract base for controllers (`json`, `text`, `input`, `param`) |
| `MiddlewareInterface` | Contract: `handle(array $params, callable $next): void` |
| `ErrorHandler` | Global exception, error, and shutdown handler |
| `Logger` | Append-only file logger (`storage/logs/app-YYYY-MM-DD.log`) |
| `DatabaseInterface` | PDO query contract: `fetch`, `fetchOne`, `execute`, `lastInsertId`, `pdo` |
| `SQLiteDriver` | PDO/SQLite implementation of `DatabaseInterface` |
| `AuthProviderInterface` | Contract for auth credential verification |
| `Gate` | Centralized permission resolver via group membership |
| `Migration` | Abstract base for migration files |
| `MigrationRunner` | Discovers, applies, and rolls back migration files |
| `Installer\EnvironmentChecker` | PHP version + extension checks |
| `Installer\DirectoryChecker` | Writable path checks; attempts to create missing dirs |
| `Installer\ConfigWriter` | In-place `.env` key updater + formatted `local.php` generator |
| `Installer\InstallLock` | Write/check/clear install lock; reads `APP_INSTALLED` env flag |

---

### Layer 2 — Module Layer

Modules are self-contained feature sets that are **reusable across projects** but are not generic enough to belong in the kernel. They depend on `App\Core\` but not on each other or on application code.

#### Bundled modules (ship with this platform)

These modules are already implemented and live directly under `app/`:

| Directory | Namespace | What it provides |
|---|---|---|
| `app/Auth/` | `App\Auth\` | Session auth, API token generation/verification, local password provider |
| `app/Middleware/` | `App\Middleware\` | `SessionAuth`, `TokenAuth`, `WebAuth`, `RequirePermission` |
| `app/Models/` | `App\Models\` | `UserRepository`, `TokenRepository` |
| `app/Controllers/` | `App\Controllers\` | `AuthController`, `TokenController` (shared HTTP layer for Auth module) |
| `app/Views/` | *(no namespace)* | PHP view files: `layouts/app.php` (shell), `auth/login.php`, `dashboard/index.php` |

#### Add-on modules (future, not yet built)

Add-on modules live under `app/Modules/{ModuleName}/` with namespace `App\Modules\{ModuleName}\`.

| Directory | Purpose | Status |
|---|---|---|
| `app/Modules/Setup/` | Installation orchestration — `SetupService` (implemented); web wizard UI | Partial |
| `app/Modules/Notes/` | Attach free-text notes to any entity (polymorphic, entity_type + entity_id) | Implemented — see [notes-module.md](notes-module.md) |
| `app/Modules/Notifications/` | User-facing in-app inbox + email delivery; decoupled from alert dispatch | Implemented (in-app channel) — see [notifications-module.md](notifications-module.md) |
| `app/Modules/FileManager/` | Browse, upload, download files | Planned |
| `app/Modules/DatabaseReader/` | Read and query external databases | Planned |
| `app/Modules/Chat/` | Real-time or async messaging | Planned |
| `app/Modules/Reporting/` | Generate and export reports | Planned |

Each add-on module follows this internal structure:

```
app/Modules/{ModuleName}/
    Controllers/      HTTP controllers for this module
    Services/         Business logic
    Models/           DB repositories specific to this module
    migrations/       Schema migrations for this module (optional)
```

Routes for a module are registered in `routes/web.php` using the qualified handler syntax:

```php
$router->get('/files', 'Modules\FileManager\Controllers\BrowserController@index', ['SessionAuth']);
```

---

### Layer 3 — Application Layer (`app/Controllers/`, `app/Modules/`)

**Namespace:** `App\Controllers\`, `App\Modules\`
**Rule:** Kernel-level controllers and reusable feature modules.

| Directory | Purpose |
|---|---|
| `app/Controllers/` | Kernel HTTP controllers (auth, profile, admin) |
| `app/Controllers/Admin/` | Admin area controllers (users, groups, permissions, settings, locations) |
| `app/Modules/` | Reusable feature modules (future plugins) |
| `app/Modules/Chat/` | Chat rooms and messaging |
| `app/Modules/FileManager/` | File browser |
| `app/Modules/Notifications/` | Notification inbox with channels |
| `app/Modules/Tasks/` | Task management |
| `app/Modules/Notes/` | Entity notes |
| `app/Modules/Setup/` | Installation wizard |

---

## Directory Structure

```
/
├── app/
│   ├── Auth/                   Bundled auth module
│   │   ├── AuthService.php
│   │   ├── LocalAuthProvider.php
│   │   └── TokenService.php
│   ├── Controllers/            Shared module HTTP controllers
│   │   ├── AuthController.php
│   │   └── TokenController.php
│   ├── Core/                   Framework kernel
│   │   ├── AuthProviderInterface.php
│   │   ├── Config.php
│   │   ├── Container.php
│   │   ├── Controller.php
│   │   ├── DatabaseInterface.php
│   │   ├── ErrorHandler.php
│   │   ├── Gate.php
│   │   ├── Logger.php
│   │   ├── MiddlewareInterface.php
│   │   ├── Migration.php
│   │   ├── MigrationRunner.php
│   │   ├── Router.php
│   │   └── SQLiteDriver.php
│   ├── Middleware/             Bundled middleware
│   │   ├── RequirePermission.php
│   │   ├── SessionAuth.php
│   │   └── TokenAuth.php
│   ├── Models/                 Shared module repositories
│   │   ├── TokenRepository.php
│   │   └── UserRepository.php
│   ├── Modules/                Reusable modules (chat, file-manager, notifications, etc.)
│   ├── Controllers/Admin/      Kernel admin controllers (users, groups, permissions, etc.)
│   └── Services/               Global shared services (SystemSettingService, etc.)
├── config/
│   ├── app.php
│   ├── auth.php
│   └── database.php
├── data/
│   └── app.db
├── database/
│   ├── migrations/
│   └── seeds/
├── docs/
├── public/
│   ├── assets/
│   │   ├── css/
│   │   ├── icons/
│   │   └── js/
│   └── index.php
├── routes/
│   └── web.php
├── scripts/
│   ├── migrate.php
│   ├── seed.php
│   └── test.sh
├── storage/
│   ├── cache/
│   └── logs/
└── tests/
```

---

## Request Lifecycle

```
Browser / API client
        │
        ▼
public/index.php
  1. Register autoloader  (App\ → app/)
  2. Load config          (app, database, auth)
  3. ErrorHandler::register()
  4. Build Container — register: config, logger, db, gate, auth, tokens
  5. Router($container)
  6. require routes/web.php
  7. Router::dispatch($method, $uri)
        │
        ├─ No match  → OutOfBoundsException → ErrorHandler → 404
        │
        └─ Match → middleware stack → controller
```

### Controller handler resolution

The Router resolves handler strings to PHP classes:

| Handler string | Resolved FQCN |
|---|---|
| `'AuthController@login'` | `App\Controllers\AuthController` |
| `'Controllers\Admin\AdminController@index'` | `App\Controllers\Admin\AdminController` |
| `'Modules\FileManager\Controllers\BrowserController@index'` | `App\Modules\FileManager\Controllers\BrowserController` |

**Rule:** bare names (`ClassName@method`) resolve to `App\Controllers\`. Qualified names (`Namespace\Class@method`) resolve to `App\{Namespace}\{Class}`.

---

## Module Development Guide

When building a new module (e.g. FileManager):

### 1. Create the directory structure

```
app/Modules/FileManager/
    Controllers/
    Services/
    Models/
```

### 2. Use the module namespace

```php
namespace App\Modules\FileManager\Controllers;

use App\Core\Controller;

class BrowserController extends Controller
{
    public function index(array $params = []): void
    {
        $this->json(['files' => []]);
    }
}
```

### 3. Register routes with the qualified handler

```php
// routes/web.php
$router->get('/files',        'Modules\FileManager\Controllers\BrowserController@index', ['SessionAuth']);
$router->post('/files/upload','Modules\FileManager\Controllers\BrowserController@upload',['SessionAuth', 'RequirePermission:files.upload']);
```

### 4. Register services in the container (if needed)

```php
// public/index.php — in the service registration block
use App\Modules\FileManager\Services\FileService;
$container->set('files', new FileService($container->get('db'), $storageConfig));
```

### 5. Add migrations (if schema is needed)

Name migration files as `{NNNN}_create_{module}_*.php` and place them in `database/migrations/`. Use the same format as existing migrations.

---

## Generic vs Application-Specific

| What | Location | Generic? |
|---|---|---|
| Container, Router, Config, Logger | `app/Core/` | Yes — zero app knowledge |
| DatabaseInterface + SQLiteDriver | `app/Core/` | Yes |
| ErrorHandler | `app/Core/` | Yes — API detection by `Accept` header / URI prefix |
| AuthService, TokenService | `app/Auth/` | Yes — any app needing auth can use these |
| SessionAuth, TokenAuth, RequirePermission | `app/Middleware/` | Yes |
| UserRepository, TokenRepository | `app/Models/` | Yes — tied to the auth schema, not to any domain application |
| AuthController, TokenController | `app/Controllers/` | Yes — HTTP layer for auth module |
| Gate (permission resolution) | `app/Core/` | Yes |
| MigrationRunner, Migration | `app/Core/` | Yes |
| Future Modules (FileManager, Chat…) | `app/Modules/` | Yes — reusable across projects |
| AuthController, TokenController | `app/Controllers/` | Yes — HTTP layer for auth module |
| Admin controllers (User, Group, Permission…) | `app/Controllers/Admin/` | Yes — kernel admin infrastructure |
| `config/app.php` name/url values | `config/` | No — project-specific |
| `routes/web.php` | `routes/` | No — project-specific |

---

## Design Principles

- **No heavy framework.** The kernel is custom and minimal.
- **Explicit over magic.** Middleware is listed per-route. Services are registered by name.
- **Thin controllers.** Business logic belongs in `Services/`, not controllers.
- **Modular auth.** Providers implement `AuthProviderInterface`; swapping takes two lines.
- **Portable DB layer.** All queries go through `DatabaseInterface`; swap the driver, not the queries.
- **Layer discipline.** Application code (`app/Controllers/`, `app/Modules/`) may use modules and kernel. Modules may only use the kernel. The kernel knows nothing about either.

---

---

## UI Navigation Areas

The browser shell (`app/Views/layouts/app.php`) divides the application into three navigational areas with distinct ownership rules.

### Sidebar — application navigation only

The sidebar contains links to application-level features (monitoring, discovery, alerting) and, for administrators, the Administration section. Personal utility items — notifications, tokens — **must not** live in the sidebar.

| Section | Links | Visibility |
|---|---|---|
| (root) | Dashboard | All users |
| Monitoring | Devices, Alerts, Discovery | All users |
| Administration | Overview, Users, Groups, Permissions | Users with `admin` permission only |

### Topbar — system-wide chrome

The topbar contains the page title, notification bell, theme switcher, and user menu.

| Element | Purpose |
|---|---|
| Bell icon (dropdown) | Recent in-app notifications panel (AJAX, fetches `/api/notifications/recent`) |
| Theme switcher | Dark / light / auto |
| User menu (dropdown) | Profile link, Sign out |

### Profile page (`/profile`) — user-owned settings

The Profile page is the single place where a user manages personal settings. It is reached via the topbar user menu. Controllers for this page live in `app/Controllers/ProfileController.php`.

| Section | What it contains |
|---|---|
| Account summary | Display name, username, email |
| Notification preferences | Per-channel enable/disable (`notification_preferences` table) |
| API tokens | List, create, revoke via existing `/api/tokens` endpoints (JS-driven) |

### Admin area (`/admin`) — system-managed settings

The Admin area is the place where administrators manage system-level configuration. It lives at `/admin` and is gated by the `admin` permission via `WebPermission` middleware. See [admin.md](admin.md) for full details.

| Section | URL | Status |
|---|---|---|
| Overview | `/admin` | Implemented |
| Users | `/admin/users` | Implemented (full CRUD) |
| Groups | `/admin/groups` | Implemented (full CRUD + permission sync) |
| Permissions | `/admin/permissions` | Implemented (full CRUD) |
| Audit Log | `/admin/audit` | Implemented |
| System Settings | `/admin/settings` | Implemented (Phase 1 — 4 keys) |

System settings are stored in the `system_settings` table and resolved through a
layered fallback chain: DB → `config/local.php` → `.env` → hardcoded default.
See `app/Services/SystemSettingService.php` and [admin.md](admin.md).

---

## See Also

- [auth.md](auth.md) — Authentication and authorization
- [dashboard.md](dashboard.md) — Dashboard shell, view rendering pattern, adding new pages
- [config.md](config.md) — Configuration loading order, .env, local.php
- [database.md](database.md) — Database abstraction layer
- [migrations.md](migrations.md) — Migration system and CLI
- [schema.md](schema.md) — Full database schema reference
- [api.md](api.md) — HTTP API endpoint reference
- [install.md](install.md) — Installation guide (CLI and web wizard)
- [setup-wizard.md](setup-wizard.md) — Web wizard UX and endpoint flow
- [installer-architecture.md](installer-architecture.md) — Installer code architecture blueprint
- [admin.md](admin.md) — Admin area structure, authorization, and deferred roadmap
