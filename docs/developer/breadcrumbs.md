# Breadcrumbs

## Overview

Breadcrumbs provide hierarchical navigation context in the admin panel layout. They appear as a Bootstrap breadcrumb bar above the page content and help users understand their location within the admin hierarchy.

## How It Works

The breadcrumbs system has two parts:

1. **Controller** — passes a `$breadcrumbs` array to the view
2. **Panel layout** — renders the breadcrumb bar

### Controller Pattern

Each admin controller method that renders a panel layout view should define `$breadcrumbs` before calling the view:

```php
$breadcrumbs = [
    ['label' => 'Administration', 'url' => '/admin'],
    ['label' => 'Extensions', 'url' => '/admin/extensions'],
    ['label' => 'Catalog', 'url' => null],  // null = current page (active)
];
```

**Rules:**
- First entry is always the admin landing page (`/admin`), shown as a home icon link
- Middle entries are clickable links back to parent pages
- Last entry has `url => null` — it is the current page and renders as active (non-clickable)
- All labels must be HTML-escaped in the layout; controllers pass raw strings
- Breadcrumbs are optional — omit `$breadcrumbs` entirely for pages that don't need them

### Layout Rendering

The panel layout (`app/Views/layouts/panel.php`) checks for `$breadcrumbs` and renders:

```php
<?php if (isset($breadcrumbs) && is_array($breadcrumbs) && !empty($breadcrumbs)): ?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="/admin" class="text-decoration-none">
                <i class="bi bi-house"></i> Admin
            </a>
        </li>
        ...
    </ol>
</nav>
<?php endif; ?>
```

## Breadcrumb Convention

Use a 2-level or 3-level hierarchy:

**2 levels (flat admin section):**
```php
$breadcrumbs = [
    ['label' => 'Administration', 'url' => '/admin'],
    ['label' => 'Groups', 'url' => null],
];
```

**3 levels (nested within a section):**
```php
$breadcrumbs = [
    ['label' => 'Administration', 'url' => '/admin'],
    ['label' => 'Extensions', 'url' => '/admin/extensions'],
    ['label' => 'Submit Extension', 'url' => null],
];
```

## Affected Controllers

All admin controllers that use the panel layout should include breadcrumbs:

| Controller    | Methods with breadcrumbs                |
|---------------|-----------------------------------------|
| AdminController    | index, permissions, audit              |
| UserController     | index, createForm, editForm, editAccountForm |
| GroupController    | index, createForm, editForm, error paths |
| PermissionController | index, createForm, editForm, error paths |
| ExtensionsController | index, catalog, submitForm, review     |
| SystemSettingsController | show, error path               |

## Accessibility

- Uses `<nav aria-label="breadcrumb">` for screen reader context
- Active (current) page has `aria-current="page"`
- All text is HTML-escaped to prevent XSS
