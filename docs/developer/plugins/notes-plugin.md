# Notes Plugin Reference

## Overview

The Notes plugin provides polymorphic note annotations attached to any domain entity. It is the first real-world plugin extracted from the Kernel-Web core.

## Structure

```
lib/plugins/notes/
├── plugin.json        — manifest
├── src/
│   ├── NotesController.php  — HTTP endpoints
│   ├── NoteService.php      — validation + orchestration
│   └── NoteRepository.php   — database queries
├── routes.php           — route registration
├── migrations/
│   └── 0021_create_notes_table.php
├── views/
│   └── notes-section.php
└── README.md
```

## Routes

| Method   | Path                 | Handler                          |
|----------|---------------------|--------------|---------|
| GET      | /notes              | NotesController@index     |
| POST     | /notes              | NotesController@add       |
| GET      | /notes/{id}         | NotesController@show      |
| DELETE   | /notes/{id}/delete  | NotesController@delete    |

All routes require session authentication.

## Permissions

- `notes.manage` — required for all operations (declared in plugin.json)

## Plugin Conventions

### Directory Layout

| Directory   | Purpose                              |
|------------|---------|
| `src/`     | PHP classes (autoloaded)            |
| `routes.php` | Route registration hook            |
| `migrations/` | Migration files (relative to plugin root) |
| `views/`   | Plugin-specific views                 |
| `plugin.json` | Manifest (required)              |

### Manifest Fields

- `name` — unique plugin identifier (used in registry)
- `version` — semantic version string
- `enabled` — whether the plugin is active (default: true)
- `permissions` — list of permission strings registered with Gate
- `routes` — route definitions in manifest format
- `services` — service bindings for the DI container
- `migrations` — migration file paths relative to plugin root

### Route Registration

Routes can be declared in two ways:

1. **Manifest routes** — listed in `plugin.json` routes array
2. **routes.php** — included during plugin loading, exposes `$router`

Both methods register routes on the same Router instance.

### Service Registration

Services declared in the manifest's `services` section are registered in the container during plugin enablement. Keys in `args` that match container bindings are resolved as dependencies.

### Handler Resolution

Plugin routes use the handler format:
```
Plugins\{Name}\{Controller}@method
```

The Router resolves this to `App\Plugins\{Name}\{Controller}` automatically.

## Migration Notes

- The `notes` table migration (`0021_create_notes_table.php`) remains in `database/migrations/` (core).
- A copy is included in the plugin for reference.
- Migration auto-execution from plugins is a deferred feature.

## Bootstrap Order

1. Plugin autoloader registered (in `public/index.php`)
2. Plugin discovered during `PluginLoader::load()`
3. Manifest validated, dependencies checked
4. If enabled: `PluginRegistry::enable()` called
   - Services registered from manifest
   - Permissions registered with Gate
5. `registerRoutes()` called — includes `routes.php` and manifest routes
6. Kernel loads its own routes from `web.php`
7. Request dispatched

## What Stayed in Core

- Original `app/Modules/Notes/` files (for backward compatibility with embedded partials)
- `app/Views/partials/notes-section.php` (used by entity views)
- Core migration `database/migrations/0021_create_notes_table.php`

The plugin provides new functionality alongside the existing core module without breaking anything.
