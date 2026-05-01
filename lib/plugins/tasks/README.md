# Tasks Plugin

## Overview

Polymorphic task management — create, assign, track, and link tasks to any domain entity.

## Structure

```
lib/plugins/tasks/
├── plugin.json       — manifest (routes, services, hooks, menus)
├── src/
│   ├── TaskController.php — HTTP endpoints
│   ├── TaskService.php    — Validation and business logic
│   └── TaskRepository.php — Database queries
├── routes.php        — Manual route registration (optional)
├── migrations/       — 9 migration files (create table → schedule fields)
├── views/
│   ├── index.php    — Task list with scope filters
│   ├── create.php   — Create task form
│   └── edit.php     — Edit task form
└── README.md
```

## Routes

| Method | Path | Handler | Middleware |
|--------|------|---------|----|
| GET | `/tasks` | `TaskController@index` | WebAuth, tasks.manage |
| GET | `/tasks/create` | `TaskController@createForm` | WebAuth, tasks.manage |
| POST | `/tasks` | `TaskController@store` | WebAuth, tasks.manage |
| GET | `/tasks/{id}/edit` | `TaskController@editForm` | WebAuth, tasks.manage |
| POST | `/tasks/{id}` | `TaskController@update` | WebAuth, tasks.manage |
| POST | `/tasks/{id}/delete` | `TaskController@delete` | WebAuth, tasks.manage |

## Menu

A sidebar menu item is registered via `plugin.json` menus declaration:

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

The menu item is automatically filtered by the user's `tasks.manage` permission.

## Hooks

The plugin registers a `layout.head` hook (priority 5) for injecting custom CSS/JS assets.

```php
HookRegistry::register('layout.head', [
    'priority' => 5,
    'callback' => 'App\Plugins\tasks\TaskController::headAssets'
]);
```

## Services

Registered in the container under `tasks.service`:

```json
{
    "tasks.service": {
        "class": "App\\Plugins\\Tasks\\TaskService",
        "singleton": true
    }
}
```

## Permissions

- `tasks.manage` — required for all task routes

## Limitations

- Entity types are fixed to `device`, `alert`, `finding` (NetMon-specific)
- Cron task execution requires a separate scheduler process
- No subtask support (parent_id column reserved)
- Reminder system is query-ready but delivery requires a notification backend

## Migration Status

All 9 migrations are included in the plugin:

| # | File | Description |
|---|------|-----|
| 0030 | create_tasks_table.php | Initial table + indexes |
| 0031 | add_tasks_manage_permission.php | tasks.manage permission |
| 0032 | add_task_reminder_fields.php | reminder_due_sent_at, reminder_overdue_sent_at |
| 0033 | add_tasks_deleted_at.php | Soft delete support |
| 0034 | add_task_assignment_model.php | assigned_type, assigned_id columns |
| 0035 | backfill_task_assignment_model.php | Migrate assigned_user_id → assigned_type/assigned_id |
| 0036 | drop_tasks_assigned_user_id.php | Remove deprecated assigned_user_id column |
| 0037 | add_task_execution_tracking.php | last_run_at, last_run_status, last_run_message |
| 0043 | add_task_schedule_fields.php | schedule_type, schedule_value |
