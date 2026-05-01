# Layout Hook Registry

## Purpose

The Layout Hook Registry allows plugins and core code to inject small pieces of content into layouts at specific named locations.

## Architecture

```
[ Kernel Core ]
    HookRegistry
        ├── register(hookName, content, priority)
        └── render(hookName) → string
```

## Hook Names

| Hook | Location |
|------|--|
| `layout.head` | Inside `<head>`, after CSS links |
| `layout.body.start` | Immediately after `<body>` |
| `layout.body.end` | Immediately before `</body>` |
| `panel.sidebar.before` | Reserved for future sidebar injection |
| `panel.sidebar.after` | Reserved for future sidebar injection |
| `panel.topbar.left` | Reserved for future topbar injection |
| `panel.topbar.right` | Reserved for future topbar injection |
| `panel.footer` | Inside the page footer |
| `dashboard.widgets` | Reserved for future dashboard widgets |

## Usage

### Registering a Hook

```php
use App\Core\HookRegistry;

// Simple callable hook
HookRegistry::register('layout.head', function ($ctx) {
    return '<meta name="generator" content="Kernel-Web">';
}, 10);

// Renderable object hook
HookRegistry::register('layout.body.end', new MyWidget(), 5);
```

### Implementing HookRenderable

```php
use App\Core\HookRenderable;

class MyWidget implements HookRenderable
{
    public function render(array $context = []): string
    {
        return '<div class="widget">Hello</div>';
    }
}
```

### Rendering a Hook

```php
// Inside a layout file:
echo HookRegistry::render('layout.head');
```

### Priority

Higher priority values render later. Default priority is `0`.

```php
HookRegistry::register('layout.head', $lowPriority, 0);
HookRegistry::register('layout.head', $highPriority, 10);
```

## Implementation

- `HookRegistry` — static class for managing hook registrations
- `HookRenderable` — interface for renderable hook content
- Both live in `app/Core/`

## Limitations

- Hooks are registered at runtime (in-memory only, no persistence)
- No automatic hook discovery from plugins (must be registered explicitly)
- No hook parameter passing beyond `$context` array
- No hook output caching

## Future

- Automatic hook discovery from plugin manifests (`hooks[]` in `plugin.json`)
- Hook grouping (related hooks rendered together)
- Hook output buffering control
