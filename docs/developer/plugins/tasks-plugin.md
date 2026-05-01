# Tasks Plugin

## Overview

Polymorphic task management plugin. Provides create, assign, track, and link tasks to any domain entity.

Extracted from `app/Modules/Tasks/` into `lib/plugins/tasks/`.

## Plugin Structure

```
lib/plugins/tasks/
├── plugin.json       — manifest (routes, services, hooks, menus, migrations)
├── src/
│   ├── TaskController.php — HTTP endpoints
│   ├── TaskService.php    — Validation and business logic
│   └── TaskRepository.php — Database queries
├── routes.php        — Manual route registration (also in manifest)
├── migrations/       — 9 migration files
├── views/
│   ├── index.php    — Task list with scope filters
│   ├── create.php   — Create task form
│   └── edit.php     — Edit task form
└── README.md
```

## Routes

| Method | Path | Handler | Middleware |
|--------|-- ----|-- ------ |----|
| GET | `/tasks` | `Plugins\Tasks\TaskController@index` | WebAuth, tasks.manage |
| GET | `/tasks/create` | `Plugins\Tasks\TaskController@createForm` | WebAuth, tasks.manage |
| POST | `/tasks` | `Plugins\Tasks\TaskController@store` | WebAuth, tasks.manage |
| GET | `/tasks/{id}/edit` | `Plugins\Tasks\TaskController@editForm` | WebAuth, tasks.manage |
| POST | `/tasks/{id}` | `Plugins\Tasks\TaskController@update` | WebAuth, tasks.manage |
| POST | `/tasks/{id}/delete` | `Plugins\Tasks\TaskController@delete` | WebAuth, tasks.manage |

All routes are declared in `plugin.json` and registered with priority 1 by the kernel.

## Menu Contributions

The plugin registers a sidebar menu item via its manifest:

```json
{
    "menu": "sidebar",
    "item": {
        "name": "tasks",
        "label": "Tasks",
        "url": "/tasks",
        "icon": "bi bi-check2-square",
        "permission": "tasks.manage",
        "order": 20
    }
}
```

The sidebar renders task items from the MenuRegistry instead of a hardcoded link.

## Hook Contributions

The plugin registers a `layout.head` hook (priority 5) for injecting custom CSS/JS assets.

```php
HookRegistry::register('layout.head', [
    'priority' => 5,
    'callback' => 'App\Plugins\tasks\TaskController::headAssets'
]);
```

## Migration Status

All 9 migrations are included in the plugin and will be auto-executed:

| # | File | Description |
|---|---|---|
| 0030 | create_tasks_table.php | Initial table + indexes |
| 0031 | add_tasks_manage_permission.php | tasks.manage permission |
| 0032 | add_task_reminder_fields.php | Reminder tracking columns |
| 0033 | add_tasks_deleted_at.php | Soft delete support |
| 0034 | add_task_assignment_model.php | assigned_type, assigned_id |
| 0035 | backfill_task_assignment_model.php | Migrate assigned_user_id → new model |
| 0036 | drop_tasks_assigned_user_id.php | Remove deprecated column |
| 0037 | add_task_execution_tracking.php | last_run_at/status/message |
| 0043 | add_task_schedule_fields.php | schedule_type, schedule_value |

## Limitations

- Entity types are fixed to `device`, `alert`, `finding` (NetMon-specific)
- Cron task execution requires a separate scheduler process
- No subtask support (parent_id column reserved)
- Reminder system is query-ready but delivery requires a notification backend

## What Stayed in Core

- Kernel-level Tasks routes were removed (now plugin-managed)
- The Tasks sidebar link now renders from MenuRegistry

## What Was Moved

- `app/Modules/Tasks/Controllers/TaskController.php` → `lib/plugins/tasks/src/TaskController.php`
- `app/Modules/Tasks/Services/TaskService.php` → `lib/plugins/tasks/src/TaskService.php`
- `app/Modules/Tasks/Models/TaskRepository.php` → `lib/plugins/tasks/src/TaskRepository.php`
- `app/Views/tasks/*` → `lib/plugins/tasks/views/*`
- `database/migrations/003{0-7},0043_*` → `lib/plugins/tasks/migrations/*`
- Namespace changed from `App\Modules\tasks` to `App\Plugins\tasks`
