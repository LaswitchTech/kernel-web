# File Manager Module

## Overview

The File Manager is a reusable module that provides safe, browser-based access to files inside explicitly configured storage roots. It has no dependency on any NetMon-specific model or service.

| Property | Value |
|---|---|
| Module path | `app/Modules/FileManager/` |
| Views | `app/Views/file-manager/` |
| Config | `config/filemanager.php` |
| Permission | `files.manage` |
| Routes | `GET/POST /files/*` |

---

## Storage Root Model

Roots are declared in `config/filemanager.php`. Each root is a plain PHP array:

```php
return [
    'roots' => [
        [
            'id'      => 'storage',       // URL-safe slug; appears in route segments
            'label'   => 'Application Storage', // Human-readable name
            'path'    => dirname(__DIR__) . '/storage/files', // Absolute filesystem path
            'enabled' => true,            // false = hidden from UI and all operations refused
        ],
    ],
];
```

**Rules for roots:**

- `id` must be URL-safe: lowercase letters, digits, hyphens, underscores.
- `path` must be an absolute path to an existing, readable/writable directory.
- Disabled roots (`enabled: false`) are invisible to all controller methods.
- Local overrides can be placed in `config/local.php` under the `filemanager` key (see `Config::load()`).

To add a root for a new project:

1. Add the entry to `config/filemanager.php`.
2. Create the directory and set correct permissions.
3. Grant users the `files.manage` permission via Admin → Groups.

---

## Safety Model

Path safety is enforced exclusively by `PathResolver` (`app/Modules/FileManager/Services/PathResolver.php`). Every filesystem call in `FileManagerService` goes through one of two resolver methods before touching the disk.

### `resolve(rootPath, relativePath)` — for existing paths

```
1. realpath(rootPath)      → canonical root (resolves symlinks, .., etc.)
2. realpath(joined path)   → canonical target
3. Verify: target === root  OR  target starts with (root + DIRECTORY_SEPARATOR)
4. If check fails → throw RuntimeException("Path traversal detected")
```

The separator-aware prefix check prevents the false-positive where `/storage/files-extra` would match root `/storage/files`.

### `resolveNew(rootPath, relativeParent, name)` — for paths that don't exist yet

Used by mkdir and upload (target doesn't exist yet so `realpath()` would return false).

```
1. Validate name: not empty, not ".", not "..", no "/" or "\" characters, no control chars
2. resolve(rootPath, relativeParent)  → canonical parent (must exist, must be within root)
3. Verify parent is_dir()
4. Construct: parent + DIRECTORY_SEPARATOR + name
5. Check: result starts with (canonicalRoot + DIRECTORY_SEPARATOR)
```

Because the parent is already canonical and within root, and the name contains no separators, the constructed path is guaranteed to be within root without needing `realpath()`.

### What this prevents

| Attack | Prevention |
|---|---|
| `../../../etc/passwd` | `resolve()` catches it via `realpath()` + prefix check |
| `name` containing `/` | `resolveNew()` rejects names with any path separator |
| Symlink escape | `realpath()` resolves symlinks before the prefix check |
| Null bytes in filenames | `resolveNew()` strips control characters from upload names |
| Deleting root | `delete()` explicitly rejects paths that equal the canonical root |

---

## Routes and Controller

**File:** `app/Modules/FileManager/Controllers/FileManagerController.php`

All routes require `['WebAuth', 'WebPermission:files.manage']`.

| Method | Route | Controller method | Description |
|---|---|---|---|
| GET | `/files` | `index()` | Root list — cards for each enabled root |
| GET | `/files/{rootId}` | `browse()` | Directory listing; `?path=rel/path` for subdirs |
| GET | `/files/{rootId}/download` | `download()` | Stream file; `?path=rel/path` |
| GET | `/files/{rootId}/preview` | `preview()` | Preview page for text, image, PDF; `?path=rel/path` |
| GET | `/files/{rootId}/serve` | `serve()` | Inline content stream for image/PDF; `?path=rel/path` |
| POST | `/files/{rootId}/mkdir` | `mkdir()` | Create directory; body: `path`, `name` |
| POST | `/files/{rootId}/upload` | `upload()` | Upload file; multipart; body: `path`, `file` |
| POST | `/files/{rootId}/rename` | `rename()` | Rename entry; body: `path`, `name` |
| POST | `/files/{rootId}/move` | `move()` | Move entry; body: `path`, `destination` |
| POST | `/files/{rootId}/delete` | `delete()` | Delete file or empty directory; body: `path` |

Route ordering: all static sub-paths (`download`, `preview`, `serve`, `mkdir`, `upload`, `rename`, `move`, `delete`) are registered before `{rootId}` to prevent collisions.

### Path flow for a browse request

```
GET /files/storage?path=reports/2024
  → FileManagerController::browse()
    → sanitizeRelativePath("reports/2024")  → "reports/2024"
    → PathResolver::resolve("/project/storage/files", "reports/2024")
      → realpath("/project/storage/files")            = "/project/storage/files"
      → realpath("/project/storage/files/reports/2024") = "/project/storage/files/reports/2024"
      → prefix check: OK
    → FileManagerService::listDirectory("/project/storage/files", "reports/2024")
      → returns entry array
    → browse.php view renders entries
```

---

## Services

### `PathResolver`

**File:** `app/Modules/FileManager/Services/PathResolver.php`

| Method | Purpose |
|---|---|
| `resolve(rootPath, relativePath): string` | Resolve existing path within root |
| `resolveNew(rootPath, relativeParent, name): string` | Resolve new-entry path (mkdir/upload) |
| `relativize(rootPath, absolutePath): string` | Convert absolute path back to root-relative (for URLs) |

### `FileManagerService`

**File:** `app/Modules/FileManager/Services/FileManagerService.php`

| Method | Returns | Description |
|---|---|---|
| `listDirectory(rootPath, relativePath)` | `array[]` | List immediate children; dirs first, alpha |
| `stat(rootPath, relativePath)` | `array` | Metadata for one entry |
| `absolutePath(rootPath, relativePath)` | `string` | Canonical path for download streaming |
| `mkdir(rootPath, relativeParent, name)` | `void` | Create empty directory |
| `upload(rootPath, relativeParent, uploadedFile)` | `void` | Move PHP upload to target |
| `rename(rootPath, relativePath, newName)` | `void` | Rename within same parent; refuses collision |
| `move(rootPath, relativePath, relativeDestDir)` | `void` | Move to different directory; refuses collision and self-move |
| `delete(rootPath, relativePath)` | `void` | Delete file or empty directory |
| `formatBytes(bytes)` | `string` | Static: human-readable size (B/KB/MB/GB) |

### `PreviewDetector`

**File:** `app/Modules/FileManager/Services/PreviewDetector.php`

Classifies a file as `'text'`, `'image'`, `'pdf'`, or `'none'` using MIME type (preferred) and file extension (fallback). Has no filesystem access; reusable outside NetMon.

| Method | Returns | Description |
|---|---|---|
| `classify(filename, ?mimeType)` | `string` | Returns `'text'`, `'image'`, `'pdf'`, or `'none'` |
| `isPreviewable(filename, ?mimeType)` | `bool` | True when classify() returns non-`'none'` |

Constant: `TEXT_SIZE_LIMIT = 524288` (512 KB) — controllers must enforce this before reading file content.

Directory listing entry shape:
```php
[
    'name'     => 'report.pdf',       // Entry name
    'type'     => 'file',             // 'file' or 'dir'
    'size'     => 204800,             // bytes; null for dirs
    'modified' => '2025-04-20 09:30:00',
    'path'     => 'reports/report.pdf', // root-relative, forward slashes (use for URLs)
]
```

---

## Authorization

A single permission gates all File Manager routes:

| Permission | Description | Granted to |
|---|---|---|
| `files.manage` | Browse, upload, download, and delete files in configured storage roots | `admin` group (default) |

The permission is seeded by migration `0027_add_files_manage_permission` and included in `database/seeds/AdminBootstrap.php` for fresh installs.

To grant File Manager access to a non-admin group:
1. Admin → Groups → Edit the group
2. Check the `files.manage` permission checkbox

Future phases may split this into `files.read` and `files.write` if read-only access is needed.

---

## UI

### Root index (`GET /files`)

Shows a card for each enabled root. If no roots are configured, a guidance message is shown.

### Browser (`GET /files/{rootId}`)

- **Breadcrumb navigation** — click any segment to navigate up the tree
- **Flash messages** — success/error from mkdir, upload, rename, move, delete
- **New Folder panel** — collapsible; POST to `mkdir`
- **Upload File panel** — collapsible; multipart POST to `upload`
- **Directory listing** — DataTable with:
  - Hidden sort column keeps directories always before files (`orderFixed: { pre: [[0, 'asc']] }`)
  - Folder names are clickable links that navigate into the subdirectory
  - Download button for files
  - **Rename button** — opens a modal pre-populated with the current name; POST to `rename`
  - **Move button** — opens a modal with a destination path input; POST to `move`
  - Delete button (with JavaScript `confirm()` dialog) for both files and directories

### Rename modal

A shared Bootstrap 5 modal is populated via JavaScript when the Rename button is clicked. The hidden `path` field and the `name` input are set from `data-*` attributes on the button. Submits to `POST /files/{rootId}/rename`.

### Move modal

A shared Bootstrap 5 modal accepts a destination folder path (relative to the storage root). Leave blank to move to the root. Submits to `POST /files/{rootId}/move`. On success, the browser is redirected to the destination directory. On failure, it returns to the item's current parent with an error flash.

### Delete safety

Destructive delete requires two confirmations:
1. The JavaScript `confirm()` dialog in the browser
2. Server-side: `delete()` refuses non-empty directories, preventing accidental mass deletion

---

## File Preview

### Supported types

| Type | Detection | Rendering |
|---|---|---|
| Plain text | `text/*` MIME (excluding `text/html`, `text/javascript`) or known text extension | Escaped `<pre>` block — never executed |
| Image | `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/bmp`, `image/tiff` MIME or matching extension | `<img>` tag via `/serve` endpoint |
| PDF | `application/pdf` MIME or `.pdf` extension | Browser-native `<embed>` via `/serve` endpoint |

`image/svg+xml` is intentionally excluded — SVG files may contain active script.
`text/html` and `text/javascript` are intentionally excluded from the text MIME path — only detectable via extension, and when detected, rendered as escaped plain text (never executed).

### How it works

1. The browse view calls `PreviewDetector::isPreviewable($filename)` (extension-only) to decide whether to show the Preview button per row.
2. `GET /files/{rootId}/preview?path=...` → `FileManagerController::preview()`:
   - Resolves the path through `PathResolver` (same root-scope guarantee as all other operations)
   - Calls `mime_content_type()` if available, then `PreviewDetector::classify()`
   - **Text**: reads content via `file_get_contents()`, passes it through `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE)` — content is never rendered as active HTML
   - **Image/PDF**: passes the serve URL to the view — the browser fetches content from `/serve`
   - Renders a full app-layout preview page
3. `GET /files/{rootId}/serve?path=...` → `FileManagerController::serve()`:
   - Resolves the path
   - Classifies — only `'image'` and `'pdf'` are allowed to proceed; all others return HTTP 403
   - Streams with `Content-Disposition: inline` so the browser renders rather than downloads

### Safety model

| Risk | Mitigation |
|---|---|
| Path traversal | `PathResolver::resolve()` enforces root scope, same as every other operation |
| Active HTML/script execution | `text/html` and `text/javascript` excluded from MIME path; all text rendered via `htmlspecialchars()` |
| SVG script injection | `image/svg+xml` excluded — SVG falls through to `'none'` from MIME path |
| Large file DoS | `TEXT_SIZE_LIMIT` (512 KB) — files exceeding this show an informational message with a download link instead |
| Arbitrary binary serving | `/serve` endpoint returns HTTP 403 for any non-image, non-PDF type |
| Invalid UTF-8 sequences | `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE)` replaces invalid sequences rather than erroring |

### Unsupported cases

- Files the browser has no plugin for (e.g. `.docx`, `.zip`) — shown as "preview not available"
- SVG files — shown as "preview not available" (not classified as image)
- Audio/video — not supported in this phase
- Files larger than 512 KB (text type) — shown with size warning and download link

### Deferred preview improvements

| Item | Notes |
|---|---|
| Syntax highlighting | Client-side library (e.g. Prism.js) for code files |
| SVG preview | Safe inline display after sanitization pass |
| Audio/video preview | HTML5 `<audio>` / `<video>` for common media formats |
| Text truncation (partial preview) | Show first N bytes of oversized text files instead of refusing |
| Thumbnail generation | Server-side image thumbnails for directory listings |

---

## Audit Logging

Write operations are recorded in the shared `admin_audit_log` table via `AuditLogRepository`. Logging is always wrapped in `try/catch` inside `FileManagerController` — a DB failure writing the log entry never causes the file operation to fail or display an error.

### Entity convention

| Field | Value | Reason |
|---|---|---|
| `entity_type` | `file_root` | The storage root is the closest domain boundary |
| `entity_id` | `0` | No DB-backed file record exists; consistent with `settings.update` in the admin area |

### Logged actions

| Action | Controller method | Meta fields |
|---|---|---|
| `filemanager.mkdir` | `mkdir()` | `root_id`, `path` (parent), `name` |
| `filemanager.upload` | `upload()` | `root_id`, `path` (parent), `filename` |
| `filemanager.rename` | `rename()` | `root_id`, `old_path`, `old_name`, `new_name` |
| `filemanager.move` | `move()` | `root_id`, `path`, `name`, `destination` |
| `filemanager.delete` | `delete()` | `root_id`, `path`, `name`, `type` (`file` or `dir`) |

For `delete`, `type` is captured via `FileManagerService::stat()` **before** the delete call so it remains available in the meta after the entry is gone.

### What is NOT logged

- **Downloads** — download is a read-only, non-destructive operation. Not logged in this phase.
- **Browse / directory listing** — read-only.
- **Content diff** — meta records names and paths only, not file contents.

### How to view

Audit entries appear in the shared admin audit log at `GET /admin/audit`. Search by action prefix `filemanager.` to filter File Manager entries.

---

## Phase 1 Constraints

| Constraint | Reason | Future path |
|---|---|---|
| Delete refuses non-empty directories | Prevents accidental mass deletion | Phase 2: opt-in recursive delete with explicit confirmation |
| No syntax highlighting | Keeps preview scope minimal | Future: client-side Prism.js or similar |
| No audio/video preview | Outside safe Phase 1 scope | Future: HTML5 media elements |
| Rename stays within same parent | Keeps rename semantics distinct from move | Intentional — use move to change parent |
| Move uses a text-input destination | Keeps UI simple without a tree picker | Future: directory picker widget |
| Move does not cross roots | Root isolation is a hard safety boundary | By design |
| Single permission (`files.manage`) | Sufficient for Phase 1 | Phase 2: `files.read` + `files.write` split |
| No access log | Not audited | Future: integrate with audit log for write operations |
| No per-root permissions | All enabled roots share `files.manage` | Future: per-root permission binding |
| Config-file roots only | No runtime root management | Future: DB-managed roots with per-root metadata |

---

## What Remains Before Feature-Complete

| Feature | Notes |
|---|---|
| Syntax highlighting | Client-side highlight for code file previews |
| Audio/video preview | HTML5 `<audio>` / `<video>` for common media types |
| Thumbnail generation | Server-side image thumbnails in directory listings |
| Thumbnails | Image thumbnails in directory listing |
| Cross-root move | Move entries between different configured roots |
| Directory picker | Tree-based UI for choosing move destination instead of free-text path |
| Recursive delete | Multi-select + confirmation UI |
| File attachments | Link file manager entries to NetMon entities (devices, alerts) |
| Download audit logging | Optionally log `filemanager.download` for compliance-sensitive roots |
| Per-root permissions | Bind a group permission per root, not just a global flag |
| DB-managed roots | Admin UI to add/edit/disable roots at runtime |
| Multi-file upload | Drag-and-drop zone with multiple file selection |
| Zip download | Package a folder as a .zip for download |
| Search | File search within a root or across all roots |

---

## Extending the Module

### Adding a new storage root

1. Add an entry to `config/filemanager.php`:
   ```php
   [
       'id'      => 'reports',
       'label'   => 'Monthly Reports',
       'path'    => '/var/data/reports',
       'enabled' => true,
   ],
   ```
2. Create the directory with correct web-process permissions.
3. Grant `files.manage` to the intended groups (or rely on admin already having it).

### Adding a new operation

1. Add a method to `FileManagerService` — always resolve the path through `PathResolver` first.
2. Add a controller method in `FileManagerController`.
3. Register the route in `routes/web.php` (static sub-paths before `{rootId}`).
4. Add UI elements to `browse.php`.

### Reusing outside NetMon

`PathResolver` and `FileManagerService` have zero dependencies on NetMon code. To use them in another project:

1. Copy `app/Modules/FileManager/Services/` into the target project.
2. Instantiate: `new FileManagerService(new PathResolver())`.
3. Pass your root path and relative paths; handle `\RuntimeException` from the service.
4. Build a thin controller and config layer appropriate for the target framework.
