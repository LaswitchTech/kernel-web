# Routing Conventions

## Purpose

Defines the clean URL routing conventions for Kernel-Web. All routes use **path-based parameters** instead of query strings.

## Convention

### Clean URLs

Use path segments for resources and identifiers:

```
/resource              → list all resources
/resource/create       → create form
/resource/{id}         → show single resource
/resource/{id}/edit    → edit form
/resource/{id}/delete  → delete resource
```

### Examples

```
GET /admin/users         → list users
GET /admin/users/create  → new user form
POST /admin/users        → create user
GET /admin/users/{id}/edit → edit user form
POST /admin/users/{id}   → update user
POST /admin/users/{id}/delete → delete user
```

### HTTP Methods

| Method | Purpose |
|--------|---------|
| `GET` | Display pages, forms, list data |
| `POST` | Create, update, delete, actions |
| `PUT` | Reserved for future REST APIs |
| `DELETE` | Reserved for future REST APIs |

### Plugin Routes

Plugins follow the same conventions within their namespace:

```
GET /chat/rooms/create   → create room form
POST /chat/rooms         → create room
POST /chat/rooms/{id}/join → join room
```

### JSON API Routes

JSON endpoints use `/api/` prefix with SessionAuth middleware:

```
GET /api/notifications/count
GET /api/notifications/recent
POST /api/chat/rooms/{id}/messages
```

## Implementation

- Routes are declared in `routes/web.php`
- Router supports `{param}` path patterns
- Clean URLs work via webroot redirect (`.htaccess` routes all requests to `/public/`)
- Controllers orchestrate via middleware (WebAuth, WebPermission, SessionAuth)

## Handler Resolution

Route handlers use the format `"Controller@method"`. The Router resolves the
controller class name as follows:

| Handler format | Resolves to |
|---|---|
| `AuthController@index` | `App\Controllers\AuthController` |
| `Controllers\Admin\UserController@index` | `App\Controllers\Admin\UserController` |
| `Controllers\Home\HomeController@index` | `App\Controllers\Home\HomeController` |
| `Modules\Chat\Controllers\ChatController@index` | `App\Modules\Chat\Controllers\ChatController` |
| `Plugins\Notes\NotesController@index` | `App\Plugins\Notes\NotesController` |

**Convention:** Use bare names for controllers in the root `App\Controllers`
namespace. Prefix with `Controllers\` for sub-namespaced controllers (e.g.
`Controllers\Admin\...`, `Controllers\Home\...`). Use fully qualified paths
(`Modules\...`, `Plugins\...`) for module or plugin controllers.

## Rules

- Routes must be declarative and traceable
- Controllers must only orchestrate (thin layer)
- Business logic belongs in services
- Data access belongs in repositories
- All routes must specify middleware requirements
- Plugin routes must be namespaced under their domain
