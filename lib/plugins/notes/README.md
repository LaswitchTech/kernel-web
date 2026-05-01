# Notes Plugin

Polymorphic note annotations attached to any domain entity via `(entity_type, entity_id)`.

## Structure

```
lib/plugins/notes/
├── plugin.json        — manifest (name, version, routes, services, migrations)
├── src/
│   ├── NotesController.php  — HTTP endpoints for note CRUD
│   ├── NoteService.php      — validation and write orchestration
│   └── NoteRepository.php   — database queries
├── routes.php           — route registration hook
├── migrations/
│   └── 0021_create_notes_table.php  — notes table schema
└── views/
    └── notes-section.php  — reusable view partial
```

## Routes

| Method   | Path                 | Handler                    |
|----------|---------------------|----------------------------|
| GET      | /notes              | NotesController@index      |
| POST     | /notes              | NotesController@add        |
| GET      | /notes/{id}         | NotesController@show       |
| DELETE   | /notes/{id}/delete  | NotesController@delete     |

All routes require session authentication.

## Permissions

- `notes.manage` — required for all note operations

## Database

The `notes` table uses a polymorphic pattern:

```sql
CREATE TABLE notes (
    id          INTEGER NOT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id   INTEGER NOT NULL,
    user_id     INTEGER,
    content     TEXT NOT NULL,
    created_at  VARCHAR(32) NOT NULL,
    updated_at  VARCHAR(32) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

The table is created by core migration `0021_create_notes_table.php` (not yet auto-loaded from plugins).

## Notes

- The original Notes module files remain in `app/Modules/Notes/` for backward compatibility.
- The `notes-section.php` partial embedded in entity views still uses the core module path.
- The plugin provides the first real-world validation of the plugin system: routes, services, permissions, and migrations.
