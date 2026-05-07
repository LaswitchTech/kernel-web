# Extension Scaffold Generator

## Overview

The scaffold generator helps developers quickly create new plugins, themes, and layouts using safe, consistent templates. It is part of the Developer Mode tools suite and is only available when `APP_DEBUG=true`.

## Architecture

### Placement

- **Route:** `GET /admin/developer/scaffold`
- **Controller:** `DeveloperController@createScaffold`
- **Layout:** `panel.php` with breadcrumbs
- **Middleware:** `WebAuth` + `WebPermission:admin`
- **Gate:** `APP_DEBUG=true` required (returns 404 otherwise)

### Output Strategy

Scaffolds are written to `/storage/extension-staging/{slug}/` (not directly to `/lib/`).

**Why staging?**

| Factor | Direct `/lib/` | Staging |
|--------|------|------|
| Safety | Corrupted partial dirs in `/lib/` if generation fails | Staging dir is atomic; fails cleanly |
| Review | No preview step | User reviews files before install |
| Catalog integration | Bypasses catalog install flow | Matches existing catalog install workflow |
| Rollback | Impossible without git | Delete staging dir |
| Existing pattern | New convention | Reuses `storage/extension-staging/` from catalog install |

After staging, the extension is installed via the existing catalog install workflow (`POST /admin/extensions/catalog/{id}/install`), which copies files from staging, runs migrations, and executes lifecycle hooks.

## Template System

### Template Location

```
resources/scaffolds/
├── plugin/
│   ├── plugin.json
│   ├── src/
│   │   ├── {{namespace}}Controller.php
│   │   ├── {{namespace}}Service.php
│   ├── routes.php
│   ├── migrations/
│   │   └── 0000_create_{{lower_slug}}_table.php
│   ├── views/
│   │   └── .gitkeep
│   └── README.md
├── theme/
│   ├── theme.json
│   ├── assets/less/
│   │   └── theme.less
│   └── README.md
└── layout/
    ├── layout.json
    ├── views/
    │   └── layout.php
    └── README.md
```

### Placeholder Variables

Templates use `{{variable}}` placeholders:

| Variable | Example | Description |
|------|------|----|
| `{{name}}` | `My Extension` | Display name |
| `{{slug}}` | `my-extension` | URL-safe identifier |
| `{{namespace}}` | `MyExtension` | Class name prefix (PascalCase) |
| `{{author}}` | `Jane Developer` | Author name |
| `{{version}}` | `0.1.0` | Initial version |
| `{{description}}` | `A sample extension` | Short description |
| `{{plural_slug}}` | `my-extensions` | Pluralized slug (for references) |
| `{{lower_slug}}` | `my_extension` | Snake_case slug |
| `{{pascal_slug}}` | `MyExtension` | PascalCase slug (for class names) |

### Template File Examples

#### `resources/scaffolds/plugin/plugin.json`

```json
{
    "name": "{{name}}",
    "version": "{{version}}",
    "description": "{{description}}",
    "enabled": true,
    "requires": {
        "kernel": "8.1"
    },
    "dependencies": {},
    "permissions": [
        "{{slug}}.manage"
    ],
    "dependencies": {},
    "routes": [],
    "migrations": [
        "migrations/0000_create_{{lower_slug}}_table.php"
    ],
    "services": {
        "{{slug}}.service": {
            "class": "App\\Plugins\\{{namespace}}\\{{namespace}}Service",
            "singleton": true
        }
    },
    "hooks": [],
    "menus": []
}
```

#### `resources/scaffolds/plugin/src/{{NameSpace}}Controller.php`

```php
<?php

namespace App\Plugins\{{Namespace}};

use App\Core\Controller;

/**
 * HTTP endpoints for the {{name}} extension.
 */
class {{Namespace}}Controller extends Controller
{
    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $config    = $this->container->get('config');

        $pageTitle     = '{{name}}';
        $activeSection = '{{name}}';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $principal['user']['display_name'] ?? $principal['user']['username'];
        $permissions   = $principal['permissions'];
        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => '{{name}}', 'url' => null],
        ];

        ob_start();
        require __DIR__ . '/../../views/index.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../../Views/layouts/panel.php';
    }
}
```

#### `resources/scaffolds/plugin/src/{{NameSpace}}Service.php`

```php
<?php

namespace App\Plugins\{{Namespace}};

/**
 * Business logic for the {{name}} extension.
 */
class {{Namespace}}Service
{
    private $db;

    public function __construct(\App\Core\DatabaseInterface $db)
    {
        $this->db = $db;
    }
}
```

#### `resources/scaffolds/plugin/src/{{NameSpace}}Repository.php`

```php
<?php

namespace App\Plugins\{{Namespace}};

/**
 * Database queries for the {{name}} extension.
 */
class {{Namespace}}Repository
{
    private \App\Core\DatabaseInterface $db;

    public function __construct(\App\Core\DatabaseInterface $db)
    {
        $this->db = $db;
    }
}
```

#### `resources/scaffolds/plugin/migrations/0000_create_{{lower_slug}}_table.php`

```php
<?php

use App\Core\Migration;

class Create{{pascal_slug}}Table extends Migration
{
    public function up(): void
    {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS {{table}} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )"
        );
    }

    public function down(): void
    {
        $this->db->execute('DROP TABLE IF EXISTS {{table}}');
    }
}
```

#### `resources/scaffolds/plugin/routes.php`

```php
<?php

/**
 * {{name}} plugin — route registration.
 */

$router->get('/{{slug}}', 'Plugins\\{{namespace}}\\{{namespace}}Controller@index', ['WebAuth']);
```

#### `resources/scaffolds/theme/theme.json`

```json
{
    "name": "{{name}}",
    "version": "{{version}}",
    "description": "{{description}}"
}
```

#### `resources/scaffolds/theme/assets/less/theme.less`

```less
/*
 * {{name}} theme
 * Description: {{description}}
 */

/* Bootstrap token overrides go here.
 * Components consume variables only - never hardcode colors.
 */

/* -- Primary tokens -- */

/* -- Body tokens -- */

/* -- Component tokens -- */
```

#### `resources/scaffolds/layout/layout.json`

```json
{
    "name": "{{name}}",
    "version": "{{version}}",
    "description": "{{description}}"
}
```

#### `resources/scaffolds/layout/views/layout.php`

```php
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{name}}</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="/css">
    <!-- Theme CSS -->
    <link rel="stylesheet" href="/assets/lib/layouts/{{slug}}/less/app.less">

    <?php echo \App\Core\HookRegistry::render('layout.head'); ?>
</head>
<body>
    <?php echo \App\Core\HookRegistry::render('layout.body.start'); ?>

    <div class="app-shell">
        <aside class="app-sidebar" id="app-sidebar">
            <nav class="sidebar-nav">
                <?php echo \App\Core\MenuHelper::renderSidebar($navActive, $permissions ?? [], [], 'admin-sidebar'); ?>
            </nav>
        </aside>

        <div class="app-main" id="app-main">
            <header class="app-topbar">
                <button class="topbar-toggle" id="sidebar-toggle"><i class="bi bi-list" style="font-size:1.25rem;"></i></button>
                <span class="topbar-title">{{title}}</span>
                <div class="topbar-actions">
                    <!-- Theme selector -->
                    <div class="dropdown">
                        <button class="topbar-icon-btn" id="js-theme-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-circle-half"></i>
                        </button>
                        <!-- ... theme dropdown ... -->
                    </div>
                    <!-- User menu -->
                    <div class="dropdown">
                        <!-- ... user menu ... -->
                    </div>
                </div>
            </header>

            <main class="app-content">
                <?php if (isset($breadcrumbs)): ?>
                    <!-- ... breadcrumbs ... -->
                <?php endif; ?>
                <?= $content ?>
            </main>

            <footer class="app-footer">
                <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></span>
                <?php echo \App\Core\HookRegistry::render('panel.footer'); ?>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <?php echo \App\Core\HookRegistry::render('layout.body.end'); ?>
</body>
</html>
```

### PHP Templates

For `.php` template files, use a custom delimiter to avoid conflicts with PHP's own `<?php` tags.

Recommended approach: use `{{php:variable}}` for all template variables inside PHP files.

Alternatively, store PHP templates in a separate `templates/` directory within each scaffold and use a simple `str_replace()` approach.

### Template Loading

Templates are loaded from `resources/scaffolds/{type}/` using recursive directory scanning. The scaffold generator:

1. Reads all files from the template directory
2. Applies `str_replace()` for each `{{variable}}` placeholder
3. Renames files based on variable names (e.g., `{{NameSpace}}Controller.php` → `MyController.php`)
4. Writes to staging directory

## Input Fields

### Common Fields (All Types)

| Field | Required | Validation |
|------|----------|----|
| name | Yes | Non-empty, 1-100 chars |
| slug | Yes | Regex: `^[a-z][a-z0-9-]*$`, 1-63 chars |
| type | Yes | `plugin`, `theme`, or `layout` |
| version | Yes | Semantic version (semver format) |
| description | Yes | Non-empty, max 500 chars |
| author | No | Non-empty if provided, max 100 chars |

### Plugin-Specific Fields

| Field | Required | Notes |
|------|----------|-------|
| permissions | No | Comma-separated permission strings |
| routes | No | Optional route definitions (URL, method, middleware) |
| services | No | Optional service bindings |
| hooks | No | Optional lifecycle hook registrations |
| menus | No | Optional menu item registrations |
| migrations | No | Whether to include a scaffold migration |

### Theme-Specific Fields

| Field | Required | Notes |
|------|----------|-------|
| darkMode | No | Include dark mode token overrides |
| variables | No | Custom LESS variable overrides |

### Layout-Specific Fields

| Field | Required | Notes |
|------|----------|-------|
| regions | No | Which regions to include (sidebar, topbar, content, footer) |
| hooks | No | Which hook points to include |

## Slug Validation

Slugs must match `^[a-z][a-z0-9-]*$` (lowercase, letters, numbers, hyphens, starting with a letter).

On submission, validate:
- Regex match
- Length: 1-63 characters
- No existing directory at target path (`/lib/{type}s/{slug}` or `/storage/extension-staging/{slug}`)
- No catalog extension with the same slug (prevent collision)

## Safety Rules

1. **Developer mode only** — Scaffold generator only accessible when `APP_DEBUG=true`
2. **Admin permission required** — Gated with `WebAuth` + `WebPermission:admin`
3. **Slug validation** — Strict regex, length limit, no path traversal
4. **No overwriting** — Check if target directory exists before generating; reject if it does
5. **No secrets** — Templates never contain secrets, keys, or credentials
6. **No automatic git** — Never commit generated files automatically
7. **Staging only** — Write to `/storage/extension-staging/{slug}/` only
8. **Preview before generate** — Show generated files list for review before writing
9. **Destructive warning** — Confirm before deleting staging directory
10. **No filesystem exposure** — Do not expose real server paths on the UI

## Future UI Flow

1. Navigate to `/admin/developer/scaffold`
2. Choose scaffold type: Plugin / Theme / Layout
3. Fill in metadata form (name, slug, version, description, author)
4. (Optional) Fill in type-specific fields (permissions, routes, menus, etc.)
5. Preview files to be generated (file list with paths)
6. Confirm generation
7. Show success message with:
   - List of generated files
   - Next steps (install via catalog, review files, etc.)
   - Link to staging directory listing

## Next Steps After Generation

After a scaffold is generated:

1. Review generated files
2. Customize as needed
3. Submit via the extension catalog (or manually place in `/lib/`)
4. Install via catalog (triggers migrations and lifecycle hooks)
5. Enable/disable via catalog

## Implementation Notes

- The scaffold generator does NOT replace the catalog submission/install workflow
- It accelerates the *initial* scaffolding step
- Generated files should be human-readable and include placeholders/comments where customization is needed
- The existing `resources/scaffolds/` directory should be version-controlled
- Use `file_get_contents()` + `file_put_contents()` for file I/O (no composer dependencies)

## Implementation Status (First Slice)

**Implemented:**
- Route: `GET/POST /admin/developer/scaffold` (admin + debug-gated)
- Form: single unified scaffold generator with type selector (plugin/theme/layout)
- Templates: `resources/scaffolds/{type}/` with `{{variable}}` placeholders
- Generation: recursive file read, `str_replace()` substitution, file rename, write to staging
- Validation: slug regex, semver, name/description length, PascalCase namespace
- Safety: staging only, no overwrites, no path traversal, no secrets

**Remaining:**
- Optional type-specific fields (permissions, routes, menus, hooks, services for plugins; dark mode for themes; regions for layouts)
- Preview before generation
- Staging directory management (delete existing scaffolds)
- Catalog integration link
