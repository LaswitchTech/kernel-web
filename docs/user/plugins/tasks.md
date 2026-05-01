# Task Management Module

## Purpose

The Task Management module provides reusable task / follow-up tracking across the application.  It is intentionally decoupled from NetMon-specific models and may be reused in any future application built on this platform.

---

## Architecture

### Location

```
app/Modules/Tasks/
  Controllers/
    TaskController.php
  Models/
    TaskRepository.php
  Services/
    TaskService.php
```

Views are under `app/Views/tasks/`.

### Separation of concerns

| Layer | File | Responsibility |
|---|---|---|
| Repository | `TaskRepository.php` | All SQL for `tasks` table; returns raw arrays |
| Service | `TaskService.php` | Field validation, normalisation, status constants |
| Controller | `TaskController.php` | HTTP handling, session flash, audit logging |
| Views | `app/Views/tasks/` | HTML rendering only |

---

## Schema

Core table created by migration `0030_create_tasks_table.php`.  Subsequent migrations have extended it:

| Migration | Additions |
|---|---|
| `0030` | Core table |
| `0032` | `reminder_due_sent_at`, `reminder_overdue_sent_at` |
| `0033` | `deleted_at` (soft delete) |
| `0034` | `assigned_type`, `assigned_id`, `execution_type`, `execution_payload` |
| `0037` | `last_run_at`, `last_run_status`, `last_run_message` |
| `0043` | `schedule_type`, `schedule_value` |

Current full schema (after all migrations):

```sql
CREATE TABLE tasks (
    id                       INTEGER      NOT NULL,
    title                    VARCHAR(255) NOT NULL,
    description              TEXT,
    status                   VARCHAR(32)  NOT NULL DEFAULT 'open',
    assigned_user_id         INTEGER,                   -- legacy assignment FK
    due_at                   VARCHAR(32),
    entity_type              VARCHAR(64),
    entity_id                INTEGER,
    created_by_user_id       INTEGER,
    created_at               VARCHAR(32)  NOT NULL,
    updated_at               VARCHAR(32)  NOT NULL,
    reminder_due_sent_at     VARCHAR(32),
    reminder_overdue_sent_at VARCHAR(32),
    deleted_at               VARCHAR(32),
    assigned_type            TEXT,                      -- new assignment model
    assigned_id              INTEGER,
    execution_type           TEXT,
    execution_payload        TEXT,

    PRIMARY KEY (id),
    FOREIGN KEY (assigned_user_id)   REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
)
```

### Polymorphic entity linkage

`entity_type` and `entity_id` together link a task to any domain entity (device, alert, finding, etc.).  No foreign key is placed on `entity_id` because the target may span multiple tables — application code is responsible for validating that the linked entity exists when displaying or filtering linked tasks.

### User references

`assigned_user_id` and `created_by_user_id` use `ON DELETE SET NULL` so tasks are preserved when an account is removed — they become unassigned or authorless rather than orphaned.

### Indexes

| Index | Columns | Purpose |
|---|---|---|
| `tasks_entity` | `(entity_type, entity_id)` | Fetch all tasks for a given entity |
| `tasks_assigned_user_id` | `(assigned_user_id)` | Legacy assignment queries |
| `tasks_created_by_user_id` | `(created_by_user_id)` | All tasks created by a user |
| `tasks_status` | `(status)` | Filter open/completed/etc. |
| `tasks_due_at` | `(due_at)` | Sort/filter by upcoming deadlines |
| `tasks_deleted_at` | `(deleted_at)` | Sparse index for soft-delete filter |
| `tasks_assigned_type` | `(assigned_type, assigned_id)` | New-model assignment queries |

---

## Assignment Model

Migration: `database/migrations/0034_add_task_assignment_model.php`

### Overview

Tasks support a generic polymorphic assignment model that decouples the assignee type from the task record.

Two new columns were added alongside the legacy `assigned_user_id`:

| Column | Type | Purpose |
|---|---|---|
| `assigned_type` | `TEXT NULL` | Actor type: `'user'` \| `'agent'` \| `'cron'` \| `NULL` |
| `assigned_id` | `INTEGER NULL` | Primary key of the actor in its own table |

`assigned_user_id` is kept for backward compatibility and **must not be removed** until all write paths have been migrated to populate `assigned_type` / `assigned_id`.

### Assignment types

| `assigned_type` | `assigned_id` | Meaning |
|---|---|---|
| `NULL` | `NULL` | Unassigned (no actor) |
| `'user'` | user PK | Assigned to a human user (`users.id`) |
| `'agent'` | agent PK | Assigned to a future AI/automation agent |
| `'cron'` | `NULL` | Reserved for scheduled/automated tasks; no actor ID |

### Validation rules (enforced by TaskService)

- `assigned_type` must be `NULL` or one of `['user', 'agent', 'cron']`
- For `'user'` and `'agent'`: `assigned_id` must be non-null and > 0
- For `'cron'`: `assigned_id` must be `NULL`
- `NULL` / `NULL` is valid — it means unassigned

### Backward compatibility

User-scoped repository queries (`findForUser`, `countOpenForUser`, etc.) use an OR condition covering both legacy and new-model rows:

```sql
WHERE (
    (assigned_type = 'user' AND assigned_id = ?)
    OR (assigned_type IS NULL AND assigned_user_id = ?)
)
```

This ensures tasks written before migration 0034 (which only have `assigned_user_id`) continue to appear in the correct user-scoped views.

### Migration path (future)

1. All new write paths should populate `assigned_type` + `assigned_id` (and keep `assigned_user_id` for now)
2. Once all rows have been backfilled, the OR conditions in queries can be simplified
3. `assigned_user_id` can be retired in a final cleanup migration

---

## Assignment Model — Migration Phase

This section documents the current state of the migration from the legacy
`assigned_user_id` column to the generic `assigned_type` / `assigned_id` model.

### Phase status

| Phase | Description | Status |
|---|---|---|
| 1 — Schema | Add `assigned_type`, `assigned_id`, `execution_type`, `execution_payload` | Done (migration 0034) |
| 2 — Backfill | Set `assigned_type='user'`, `assigned_id=assigned_user_id` for existing rows | Done (migration 0035) |
| 3 — Write path | Update `TaskController::store/update` to send `assigned_type`/`assigned_id` | Done |
| 4 — Query cleanup | Remove legacy OR fallback from reads and reminders | Done |
| 5 — Column retirement | Drop `assigned_user_id` in a final migration | Done (migration 0036) |

### Write path (Phase 3 — Done)

`TaskController::store()` and `TaskController::update()` both:

1. Read `$_POST['assigned_id']` (the user PK, or empty/0 for unassigned).
2. Derive `assigned_type = 'user'` when `assigned_id > 0`, otherwise `null`.
3. Pass only `assigned_type` and `assigned_id` — no `assigned_user_id` sync.

All UI forms (`create.php`, `edit.php`) submit `name="assigned_id"`.

### Query cleanup (Phase 4 — Done)

All repository user-scoped queries use the canonical model exclusively:

```sql
WHERE assigned_type = 'user' AND assigned_id = ?
```

Reminder queries join on:

```sql
JOIN users u ON (t.assigned_type = 'user' AND u.id = t.assigned_id)
```

This naturally excludes future `agent`/`cron` task types from user reminders.

The unassigned queries use `assigned_type IS NULL` as the sole condition.

### Column retirement (Phase 5 — Done)

Migration `0036_drop_tasks_assigned_user_id.php` removes `assigned_user_id` from the tasks table using the table-recreation pattern (SQLite < 3.35.0 compatibility).

The `tasks_assigned_user_id` index is also dropped.  All repository queries, controller inputs, service normalisation, and views have been updated to use only `assigned_type`/`assigned_id`.

`down()` restores the column and repopulates it from the canonical fields:
`assigned_user_id = CASE WHEN assigned_type='user' THEN assigned_id ELSE NULL END`

### Canonical assignment model

The Tasks module now uses exclusively:

| Field | Type | Meaning |
|---|---|---|
| `assigned_type` | `TEXT NULL` | Actor type: `'user'` \| `'agent'` \| `'cron'` \| NULL (unassigned) |
| `assigned_id` | `INTEGER NULL` | PK of the actor in its own table; NULL when `assigned_type` is NULL or `'cron'` |

Reserved future types (`agent`, `cron`) are validated at the service layer but have no UI or execution logic yet.

---

## Execution Model

Migrations: `0034_add_task_assignment_model.php`, `0037_add_task_execution_tracking.php`

### Schema

| Column | Type | Purpose |
|---|---|---|
| `execution_type` | `TEXT NULL` | Whitelisted execution identifier |
| `execution_payload` | `TEXT NULL` | Optional JSON payload for the script |
| `last_run_at` | `VARCHAR(32) NULL` | Timestamp of most recent scheduler run |
| `last_run_status` | `VARCHAR(32) NULL` | `'ok'` or `'failed'`; NULL = never run |
| `last_run_message` | `TEXT NULL` | Truncated combined stdout+stderr (≤ 2 000 bytes) |
| `schedule_type` | `VARCHAR(32) NULL` | Recurrence control: `'always'`, `'interval'`, `'cron'`; NULL = always |
| `schedule_value` | `TEXT NULL` | Interval seconds or 5-field cron expression; unused for `always` |

### Allowed execution types

| `execution_type` | Mapped script | Description |
|---|---|---|
| `monitor.run` | `scripts/monitor.php` | Device + service monitoring pass |
| `discover.run` | `scripts/discover.php` | Network discovery scan |
| `cleanup.run` | `scripts/cleanup.php` | Data retention cleanup |
| `task-reminders.run` | `scripts/task-reminders.php` | Send pending task reminders |
| `notify.run` | `scripts/notify.php` | Process notification queue |
| `topology-candidates.generate` | `scripts/generate-topology-candidates.php` | Generate topology candidates from shared segment memberships |

`execution_type` must be `NULL` or one of the values above.  The allow-list is
enforced at the service layer (`TaskService::EXECUTION_TYPES`) and at dispatch
time (scheduler `SCRIPT_MAP`).  No arbitrary values may be stored.

### Payload-aware execution types

Most execution types accept no payload and ignore `execution_payload` entirely.
The following types consume specific payload keys:

#### `topology-candidates.generate`

Optional payload:
```json
{ "segment_id": 123 }
```

| Key | Type | Behavior |
|---|---|---|
| `segment_id` | positive integer | Passes `--segment=<id>` to restrict generation to one segment |
| (absent) | — | Generates candidates across all segments |

All other keys in the payload are silently ignored.  Malformed JSON and
non-positive `segment_id` values are silently ignored (the script runs without
a segment filter).

The `segment_id` value is passed as a discrete `argv` element in the
`proc_open` array command — it is never interpolated into a shell string.

Example task configuration to run weekly, targeting segment 7:
```json
execution_type:    "topology-candidates.generate"
execution_payload: {"segment_id": 7}
assigned_type:     "cron"
```

### Schedule fields

`schedule_type` and `schedule_value` control when the scheduler executes a cron task.
They are only meaningful when `assigned_type = 'cron'`.  Non-cron tasks must leave both NULL.

| `schedule_type` | `schedule_value` | Behavior |
|---|---|---|
| `NULL` | — | Treated as `'always'` (backward-compatible default) |
| `'always'` | `NULL` | Run on every scheduler tick |
| `'interval'` | Positive integer string (seconds) | Run when at least N seconds have elapsed since `last_run_at`; runs immediately when `last_run_at` is NULL (never run) |
| `'cron'` | 5-field cron expression | Run when the current minute matches the expression |

#### Supported cron expression syntax

Field order: `minute  hour  day-of-month  month  day-of-week`

| Syntax | Example | Meaning |
|---|---|---|
| `*` | `* * * * *` | Every tick |
| `*/N` | `*/15 * * * *` | Every Nth unit (minute 0, 15, 30, 45) |
| Integer | `0 2 * * *` | Exact value (2:00 every day) |

Valid combinations:
```
0 2 * * *      — every day at 02:00
*/30 * * * *   — every 30 minutes
0 */6 * * *    — every 6 hours
0 0 * * 0      — every Sunday at midnight
```

Day-of-week: 0 = Sunday … 6 = Saturday.

Range validation (e.g. hour 0–23) is not enforced at the service layer — the scheduler
matches the expression against the current minute via `shouldRunTask()` which uses PHP's
`date()` functions and is bounded by real clock values.

#### Scheduler behaviour

The scheduler (`scripts/scheduler.php`) calls `shouldRunTask($task, $now)` for each
candidate task before executing it.  Tasks that are not due are silently skipped (shown
with `[SCHED-SKIP]` in verbose mode).

Fail-open rules (tasks execute rather than stall on misconfiguration):
- `schedule_type = NULL` → treated as `always`
- `schedule_type = 'interval'` with invalid value → executes
- Unparseable `last_run_at` timestamp → executes
- Unknown `schedule_type` value → executes

### Assignment rules for cron tasks

- `assigned_type = 'cron'` + `execution_type` set → valid, scheduler will run it
- `assigned_type = 'cron'` + `execution_type = NULL` → **invalid**, rejected by `TaskService::validate()`
- `execution_type` set + `assigned_type ≠ 'cron'` → **invalid**, rejected
- `execution_payload` must be valid JSON when non-null; null is always allowed

### Scheduler — `scripts/scheduler.php`

`scripts/scheduler.php` is the **single centralized entry point** for all cron task execution.

**Intended host cron schedule (every minute):**
```
* * * * * php /path/to/scripts/scheduler.php >> /path/to/storage/logs/scheduler.log 2>&1
```

**How it works:**
1. Queries `TaskRepository::findRunnableCron()` — tasks where `assigned_type='cron'`, `status IN ('open','in_progress')`, `execution_type IS NOT NULL`
2. For each task, looks up the script in `SCRIPT_MAP`; skips + marks `failed` if not found
3. Resolves the absolute path via `realpath()` and validates it is within `scripts/` (path-traversal guard)
4. Runs the script with `proc_open([PHP_BINARY, $scriptPath], ...)` — array command, no shell string
5. Captures combined stdout+stderr (capped at 2 000 bytes); records `last_run_at`, `last_run_status`, `last_run_message`
6. Audit-logs `task.execute`, then `task.execute_completed` (exit 0) or `task.execute_failed` (non-zero)

**Flags:** `--verbose` / `-v`, `--dry-run`

**Safety guarantees:**
- No user-supplied data reaches the command line
- Only whitelisted `execution_type` values produce execution
- Scripts are located via a static map, not stored paths in the database
- Audit log is fail-silent and never aborts execution

**Current limitation:** no per-task timeout in this phase — a long-running script blocks subsequent tasks. Timeout enforcement is deferred.

### Execution tracking fields

`last_run_at`, `last_run_status`, `last_run_message` are populated only for `assigned_type='cron'` tasks. They remain `NULL` for user-assigned and unassigned tasks.

### Future work

- Per-task cron schedule expressions (e.g. `0 9 * * *`)
- Timeout enforcement per task
- Full execution history table (vs. single-row last-run tracking)
- Agent assignment and execution

---

## Soft Delete

Migration: `database/migrations/0033_add_tasks_deleted_at.php`

Tasks support soft deletion via a `deleted_at` column (nullable `VARCHAR(32)`).

### Behavior

| State | `deleted_at` value | Visible in default queries |
|---|---|---|
| Active | `NULL` | Yes |
| Deleted | `YYYY-MM-DD HH:MM:SS` | No |

- Tasks are **never hard-deleted**.  Historical integrity is preserved.
- All default read queries in `TaskRepository` include `WHERE deleted_at IS NULL`.
- Reminder queries (`findDueTodayPendingReminder`, `findOverduePendingReminder`) also exclude deleted tasks.
- The `delete()` action in `TaskController` sets `deleted_at` and `updated_at` to the current timestamp.

### Query rule

Every query that returns tasks to the application must include:

```sql
WHERE deleted_at IS NULL
```

The only exception is an explicit administrative `findDeleted()` query (deferred to a future phase).

### Audit

`task.delete` is logged with title, entity_type, and entity_id via `AuditLogRepository::log()` (fail-silent pattern).

### Redirect after delete

`TaskController::delete()` uses the same smart-redirect logic as create/update:

- If the task was linked to an entity → redirect to the entity page
- Otherwise → redirect to `/tasks`

### Future: restore

Restore / undo is **deferred**.  The schema already supports it — set `deleted_at = NULL` to un-delete a task.  No UI or route exists for this in Phase 1.

---

## Status Lifecycle

| Status | Label | Description |
|---|---|---|
| `open` | Open | Default; not yet started |
| `in_progress` | In Progress | Actively being worked on |
| `completed` | Completed | Done |
| `canceled` | Canceled | Will not be completed |

There is no enforced state-machine at the database level.  Any status may be set directly.  `TaskService::STATUSES` is the authoritative list of valid values.

---

## Permission

Migration: `database/migrations/0031_add_tasks_manage_permission.php`

| Permission | Description |
|---|---|
| `tasks.manage` | Create, view, and update tasks across the application |

Granted to the `admin` group by default.

---

## Routes

All routes require `WebAuth` + `WebPermission:tasks.manage`.

| Method | Path | Handler | Description |
|---|---|---|---|
| GET | `/tasks[?scope=]` | `TaskController@index` | Task dashboard — summary cards + scoped list |
| GET | `/tasks/create` | `TaskController@createForm` | Create form |
| POST | `/tasks` | `TaskController@store` | Handle create |
| GET | `/tasks/{id}/edit` | `TaskController@editForm` | Edit form |
| POST | `/tasks/{id}/delete` | `TaskController@delete` | Soft delete |
| POST | `/tasks/{id}` | `TaskController@update` | Handle edit |

Route ordering: `/tasks/create` is registered before `/tasks/{id}` so the literal `create` segment is never captured as an `{id}` parameter.  `/tasks/{id}/delete` is registered before `/tasks/{id}` for the same reason.

---

## Task Dashboard

`GET /tasks[?scope=all|mine|overdue|due-today|unassigned]`

The task list page doubles as a personal task dashboard.  It shows:

### Summary cards

Four summary cards are always rendered.  The first three are computed for the logged-in user; Unassigned is system-wide:

| Card | Scope link | Condition |
|---|---|---|
| My Open Tasks | `?scope=mine` | `status IN ('open','in_progress')` AND assigned to me |
| My Overdue | `?scope=overdue` | active AND `DATE(due_at) < DATE('now')` AND assigned to me |
| Due Today | `?scope=due-today` | active AND `DATE(due_at) = DATE('now')` AND assigned to me |
| Unassigned | `?scope=unassigned` | active AND no assignee (system-wide) |

Cards are clickable links that activate the corresponding scope.  The active scope card receives a colored border highlight.  Non-zero warning counts use colored text/icons.

### Scope filter

A scope toggle is injected into the DataTables button area (same technique as the Alerts filter):

| Scope | Behaviour |
|---|---|
| `all` (default) | All non-deleted tasks, newest first |
| `mine` | Active tasks assigned to the current user, sorted soonest-due first |
| `overdue` | Active tasks assigned to current user whose due date is in the past |
| `due-today` | Active tasks assigned to current user due today |
| `unassigned` | Active tasks with no assignee (system-wide) |

All scopes exclude soft-deleted tasks (`WHERE deleted_at IS NULL`).

The `mine`, `overdue`, and `due-today` scopes use the backward-compatible OR assignment filter (see Assignment Model).

### Entity column

The task list includes a **Linked To** column showing the entity type and ID as a hyperlink (e.g. `Device #4`).  Unlinked tasks show `—`.

### Overdue highlighting

Due dates that are strictly in the past (and not for completed/canceled tasks) are rendered in red bold text in the Due column.

---

## TaskService

`App\Modules\Tasks\Services\TaskService`

### Constants

| Constant | Value | Purpose |
|---|---|---|
| `STATUSES` | `['open','in_progress','completed','canceled']` | Valid status values |
| `STATUS_LABELS` | map of status → human label | UI display |
| `ENTITY_TYPES` | `['device','alert','finding']` | Allow-listed entity types for polymorphic linkage |
| `MAX_TITLE_LENGTH` | `255` | Title character limit |
| `MAX_DESCRIPTION_LENGTH` | `10000` | Description character limit |

### Methods

| Method | Description |
|---|---|
| `getAll(): array` | All tasks, newest first |
| `getByEntity(string $type, int $id): array` | Tasks for a specific entity |
| `getById(int $id): ?array` | Single task or null |
| `create(array $data): int` | Validate and insert; returns new ID |
| `update(int $id, array $data): void` | Validate and update; throws on not-found |
| `delete(int $id): array` | Soft-delete; returns task before deletion |
| `getForUser(int $userId): array` | Active tasks assigned to user, soonest-due first |
| `getOverdueForUser(int $userId): array` | Active overdue tasks assigned to user |
| `getDueTodayForUser(int $userId): array` | Active due-today tasks assigned to user |
| `countOpenForUser(int $userId): int` | Count of open+in_progress tasks for user |
| `countOverdueForUser(int $userId): int` | Count of overdue tasks for user |
| `countDueTodayForUser(int $userId): int` | Count of due-today tasks for user |
| `getUnassigned(): array` | Active tasks with no assignee (system-wide) |
| `countUnassigned(): int` | Count of active unassigned tasks |

`create()` and `update()` both throw `\InvalidArgumentException` on validation failure.

---

## TaskRepository

`App\Modules\Tasks\Models\TaskRepository`

All queries JOIN users twice (assigned + created_by) to return display names without a second query:

- `assigned_username`, `assigned_display`
- `created_username`, `created_display`

Columns `assigned_display` / `created_display` come from `users.display_name`; they may be `null` when the user account has been deleted (FK ON DELETE SET NULL).

### Soft-delete rule

**Every read query in this repository includes `WHERE deleted_at IS NULL`** unless explicitly documented otherwise.  This is a blanket invariant — any future query added to this repository must include this filter unless it is specifically an administrative "show deleted" query.

### User-scoped query methods

| Method | Returns | Sort |
|---|---|---|
| `findForUser(int $userId)` | Active tasks assigned to user | Soonest-due first, no-due-date last |
| `findOverdueForUser(int $userId)` | Active tasks assigned to user, `DATE(due_at) < DATE('now')` | Due date ASC |
| `findDueTodayForUser(int $userId)` | Active tasks assigned to user, `DATE(due_at) = DATE('now')` | Due date ASC |
| `countOpenForUser(int $userId)` | `int` count | — |
| `countOverdueForUser(int $userId)` | `int` count | — |
| `countDueTodayForUser(int $userId)` | `int` count | — |
| `findUnassigned()` | Active tasks with no assignee (system-wide) | Newest first |
| `countUnassigned()` | `int` count | — |

All user-scoped methods exclude completed, canceled, and soft-deleted tasks.  User-scoped queries use the backward-compatible OR assignment filter (see Assignment Model section).

---

## Audit Logging

Each mutating action writes an audit log entry via `AuditLogRepository::log()` with the following actions:

| Action | Entity Type | Logged Fields |
|---|---|---|
| `task.create` | `task` | title, status |
| `task.update` | `task` | title, status |

Audit log failures are silently swallowed — they must never abort the main operation.

---

## Entity Integration

Tasks may be linked to any entity in `TaskService::ENTITY_TYPES`.  Each integrated entity provides:
- A read-side task section (partial) embedded in its detail page
- A contextual "Add Task" link that pre-fills entity context
- A redirect back to the originating entity page after create or update

### Supported entity types

| `entity_type` | Entity | Detail page | Redirect after create/update |
|---|---|---|---|
| `device` | Device record | `/devices/{id}` | `/devices/{id}#tasks` |
| `alert` | Alert record | `/alerts/{id}` | `/alerts/{id}` |
| `finding` | Discovery finding | `/discovery/{id}` | `/discovery/{id}` |

### Contextual create flow

1. User clicks "Add Task" on an entity detail page
2. Browser navigates to `/tasks/create?entity_type=X&entity_id=Y`
3. `TaskController::createForm()` reads and validates the query params
4. Invalid or unknown entity types are silently discarded (no error shown)
5. The create form shows a linkage notice and passes entity context as hidden POST fields
6. On success, user is redirected to the originating entity page (see table above)
7. On validation failure, the form re-renders with entity context preserved

### Redirect behavior

`TaskController` uses `entityUrl(string $entityType, int $entityId): string` to build the post-action redirect URL.  After a successful `update()`, the saved task's `entity_type`/`entity_id` is re-loaded to decide the redirect — so tasks moved to a different entity (via direct field edit) will redirect to the new entity, not the original one.

### Entity validation

`TaskService::validate()` enforces:
- If `entity_type` is non-null, it must be in `ENTITY_TYPES`
- If `entity_type` is set, `entity_id` must also be non-null and > 0
- (Both-or-neither rule — partial linkage is rejected)

The validator does **not** query the database to confirm the target entity exists.  Application code should ensure the linked entity is valid before displaying linked tasks.

### Shared partial

`app/Views/partials/tasks-section.php`

Expected variables: `$tasks`, `$entityType`, `$entityId`, `$permissions`

Used by:
- `app/Views/devices/show.php` (inside the Tasks tab)
- `app/Views/alerts/show.php` (inline section)
- `app/Views/discovery/show.php` (inline section)

Intentionally **not** using DataTables — embedded compact sections in detail pages are exempt from the DataTables default (same rationale as `notes-section.php`).

---

## UI

### Task list (`/tasks`)

- Bootstrap card containing a DataTable
- Columns: Title, Status (badge), Assigned To, Due, Created, Actions (Edit)
- Status badges use Bootstrap `*-subtle` color system so they adapt correctly to dark and light themes
- Default sort: Created descending

### Create form (`/tasks/create`)

- Title (required), Description, Status (select), Assignment type (select), Due Date (datetime-local)
- Validation errors displayed inline
- **Assignment panels** (JS show/hide based on assignment type):
  - `user` → Assigned User select
  - `cron` → Execution Type select + Execution Payload textarea + Schedule controls

**Schedule controls** (visible only when `assigned_type = cron`):
- Schedule type select: Always / Interval / Cron expression
- Interval sub-panel (shown when type = interval): numeric seconds input with "seconds" addon
- Cron expression sub-panel (shown when type = cron): monospace text input with 5-field syntax help
- Both sub-panels use `disabled` on hidden inputs to prevent ghost values from being submitted
- Values are preserved on validation failure (repopulated from `$old`)

### Edit form (`/tasks/{id}/edit`)

- Same fields as create
- Shows linked entity (entity_type + entity_id) read-only if present
- Shows created_at and updated_at timestamps
- Shows last-run date, status, and exit details (cron tasks only) in the meta section
- Schedule field repopulation falls back from `$old` → `$task` using the same `array_key_exists` pattern as other edit fields

---

## Navigation

Tasks appear in the **Tools** sidebar section, visible to users with `tasks.manage` permission.

---

## Reminder Notifications

Task reminders are dispatched by the CLI worker `scripts/task-reminders.php`.  This worker uses the reusable Notifications module and has no knowledge of devices, alerts, or any NetMon-specific class.

### Reminder conditions

| Condition | SQL predicate | Fires |
|---|---|---|
| Due today | `DATE(due_at) = DATE('now') AND reminder_due_sent_at IS NULL` | Once, on the due date |
| Overdue | `DATE(due_at) < DATE('now') AND reminder_overdue_sent_at IS NULL` | Once, when first overdue |

Tasks with `status IN ('completed', 'canceled')` are excluded from both queries.

### Recipient rule

A reminder is dispatched only when `assigned_user_id` is set on the task.  Unassigned tasks are silently skipped.  If the assigned user's account has been deleted (`ON DELETE SET NULL` → `assigned_user_id = NULL`), the task is excluded by the INNER JOIN in the repository query.

### Deduplication

Two nullable tracking columns on `tasks` (added by migration 0032):

| Column | Set when | Effect |
|---|---|---|
| `reminder_due_sent_at` | "Due today" reminder dispatched | Not sent again (IS NULL guard) |
| `reminder_overdue_sent_at` | "Overdue" reminder dispatched | Not sent again (IS NULL guard) |

Both columns are updated immediately after `NotificationService::dispatch()` returns.  A `--dry-run` pass does not set them.

**Why tracking fields on `tasks` rather than a separate log table?**  Phase 1 has exactly two reminder types with no repeat schedule.  A separate log table is justified when reminders become recurring, configurable, or multi-type.  The tracking fields keep the schema minimal and queries simple for the current scope.

### Notification content

| Condition | Title | Body |
|---|---|---|
| Due today | `Task due today: {title}` | `Task "{title}" is due today ({YYYY-MM-DD}).` |
| Overdue | `Task overdue: {title}` | `Task "{title}" was due {YYYY-MM-DD} and is now overdue.` |

If the task has an entity context (`entity_type` / `entity_id`), a second line is appended: `Linked to: {Type} #{id}.`

`source_type = 'task'`, `source_id = task.id` on the notification row.

### Channels

`['in_app', 'email']` — subject to per-user channel preferences (`notification_preferences` table).  A user who has disabled email will receive an in-app notification only.

### Delivery

`dispatch()` enqueues items into `module_notification_queue`.  Actual delivery is handled by `scripts/notify.php` (run separately — typically every minute via cron).

### Script usage

```bash
php scripts/task-reminders.php             # run one reminder pass
php scripts/task-reminders.php --verbose   # show per-task detail
php scripts/task-reminders.php --dry-run   # report without dispatching
```

Recommended cron schedule (once per day is sufficient):

```cron
0 8 * * * php /path/to/scripts/task-reminders.php >> /path/to/storage/logs/task-reminders.log 2>&1
```

---

## Phase 1 Constraints

| Feature | Status |
|---|---|
| Basic CRUD (create, edit, list) | Implemented |
| Polymorphic entity linkage | Implemented — allow-listed types: device, alert, finding |
| Contextual create from entity detail pages | Implemented |
| Smart redirect after create/update | Implemented |
| Status transitions | Any → any; no enforced state machine |
| Due today + overdue reminder notifications | Implemented — `scripts/task-reminders.php` |
| Task soft deletion | Implemented — `POST /tasks/{id}/delete`; sets `deleted_at` |
| Task dashboard with summary cards | Implemented — `GET /tasks?scope=` |
| My Tasks / overdue / due-today / unassigned scoped views | Implemented — `?scope=mine|overdue|due-today|unassigned` |
| Entity link column in task list | Implemented |
| Overdue date highlighting in task list | Implemented |
| Generic assignment model schema + validation | Implemented (migration 0034) — write UI deferred |
| Assignment model backfill | Implemented (migration 0035) — existing rows set to `assigned_type='user'` |
| Execution model schema + validation | Implemented (migration 0034) |
| Schedule fields (`schedule_type`, `schedule_value`) | Implemented (migration 0043) |
| Scheduler `shouldRunTask()` — always / interval / cron | Implemented |
| Cron execution + schedule UI (create/edit forms) | Implemented — schedule_type select, interval seconds, cron expression sub-panels |
| `assigned_user_id` deprecation | In progress — column deprecated, kept until write path migrated |
| Recurring reminders | Deferred |
| Configurable reminder schedule (`reminder_at` column) | Deferred — column reserved for future use |
| Subtasks / parent_id | Deferred — column reserved for future use |
| Recurring tasks | Deferred |
| SLA tracking | Deferred |
| Activity history | Deferred |
| Task restore (undo) | Deferred — schema ready (`deleted_at IS NULL`), no UI yet |
| Populate `assigned_type`/`assigned_id` from web UI | Deferred — schema ready, write path uses legacy `assigned_user_id` for now |
| Execution engine for `execution_type = 'cron'` | Deferred — columns reserved |

---

## Future Considerations

- `reminder_at` — configurable one-off reminder timestamp (query and dispatch via task-reminders.php)
- `priority` — integer priority weight
- `parent_id` — self-referential subtask relationship
- `completed_at` — explicit completion timestamp
- Recurring tasks
- Checklist / subtask display
- SLA deadlines and breach alerts
- Task restore / undo (soft delete already in place; only needs UI)
- Per-entity task counts in list views (e.g. open task badge on devices list)
