# Settings Plugin Hook System

> **Status:** Designed (not yet implemented)
> **Roadmap:** PHASE2-3
> **Related:** ARCH-3

---

## Purpose

Allow plugins to inject their own settings sections into the admin settings page
(`/admin/settings`) and handle saving their own settings values.

---

## Design Principles

- **Follow existing registry patterns** — mirroring `ProfileModal` / `MenuRegistry`
- **Plugins manage their own keys** — via `SystemSettingService` (already exists)
- **No core changes to setting storage** — all settings share the same `system_settings` table
- **Sensitive values never displayed** — password fields render as masked inputs, values are never echoed back
- **Core is unaware of plugins** — core registers its own sections; plugins register theirs

---

## Architecture

```
[ Kernel Core ]
  SystemSettingsController
    └── SettingsRegistry  (new, App\Core)
    └── SystemSettingService  (existing)

[ Plugin ]
  hooks.php → SettingsRegistry::registerSection(...)
```

---

## SettingsRegistry

A new kernel-core class (`App\Core\SettingsRegistry`) registered alongside
`HookRegistry`, `MenuRegistry`, and `ProfileModal`.

### Class API

```php
namespace App\Core;

class SettingsRegistry
{
    /**
     * Register a settings section provided by core or a plugin.
     *
     * @param array{
     *     id: string,
     *     label: string,
     *     column?: 'left'|'right',
     *     order?: int,
     *     permission?: string|null,
     *     render?: callable,          // fn(array $ctx): string  — returns HTML
     *     keys?: string[],            // setting keys this section manages (for save)
     *     validate?: callable,        // fn(array $input): array<string, string>
     *     save?: callable,            // fn(array $input, SystemSettingService $svc): void
     *     source?: string             // plugin name or 'core'
     * }|SettingsSection $section
     */
    public static function addSection(array|SettingsSection $section): void;

    /**
     * Get all visible sections for the given permissions, sorted by order.
     *
     * @param string[] $userPermissions
     * @return SettingsSection[]
     */
    public static function getSections(array $userPermissions = []): array;

    /**
     * Get a single section by ID.
     */
    public static function getSection(string $id): ?SettingsSection;

    /**
     * Get all setting keys managed by a section.
     *
     * Used by the controller to extract plugin settings from $_POST.
     */
    public static function getSectionKeys(string $id): array;

    /**
     * Validate a section's settings.
     *
     * @param string[] $userPermissions
     * @return array<string, string>  field → error message
     */
    public static function validateSection(string $id, array $input, array $userPermissions = []): array;

    /**
     * Save a section's settings.
     */
    public static function saveSection(string $id, array $input, SystemSettingService $svc): void;

    /**
     * Clear all registered sections (for testing / isolation).
     */
    public static function clear(): void;
}
```

### SettingsSection Value Object

```php
namespace App\Core;

class SettingsSection
{
    public function __construct(
        public string   $id,           // unique within settings page
        public string   $label,         // display text (e.g. "SMTP Settings")
        public string   $column,        // 'left' or 'right'
        public int      $order,         // sort priority (lower first)
        public ?string  $permission,    // required permission (null = anyone)
        public ?callable $render,      // fn(array $ctx): string
        public array    $keys,          // setting keys this section manages
        public ?callable $validate,    // fn(array $input): array<string, string>
        public ?callable $save,        // fn(array $input, SystemSettingService $svc): void
        public string   $source,       // plugin name or 'core'
    ) {}

    /**
     * Render the section's HTML.
     */
    public function render(array $ctx = []): string;

    /**
     * Check if this section is visible for the given permissions.
     */
    public function isVisible(array $userPermissions = []): bool;

    /**
     * Render just the card body (form fields), without the card wrapper.
     *
     * Called by the settings view to inject form content into the column layout.
     */
    public function renderBody(array $ctx = []): string;
}
```

---

## Settings Section Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | `string` | Unique identifier within the settings page. Must be slug-case. |
| `label` | `string` | Display text shown in the card header. |
| `column` | `'left' \| 'right'` | Which column the section renders in. |
| `order` | `int` | Sort priority within the column. Lower renders first. Default: `50`. |
| `permission` | `string\|null` | Required permission to see this section. `null` = visible to all. |
| `render` | `callable` | `fn(array $ctx): string` — returns the full card HTML or just the body. |
| `keys` | `string[]` | Setting keys this section manages (e.g. `['smtp.host', 'smtp.port']`). Used by the controller to extract plugin settings from `$_POST`. |
| `validate` | `callable` | `fn(array $input): array<string, string>` — validates its own inputs. Returns keyed field errors. |
| `save` | `callable` | `fn(array $input, SystemSettingService $svc): void` — persists values via `$svc->set($key, $value)`. |
| `source` | `string` | Plugin name or `'core'` for auditing. |

---

## Section Layout

The settings page has a two-column layout. Sections are distributed across columns:

- **Left column** (`col-lg-6`): core Application section
- **Right column** (`col-lg-6`): core Mailer + Notifications placeholders

Plugins register sections and declare their preferred column. The controller iterates sections in both columns, sorted by `order`.

```
┌──────────────────────┬──────────────────────┐
│  Application (core)  │  Mailer (core)     │
│                      │                      │
│  {plugin section}    │  {plugin section}    │
│   (if column=left)   │  (if column=right)   │
│                      │                      │
│  Notifications       │  {plugin section}    │
│  (core placeholder)  │                      │
└──────────────────────┴──────────────────────┘
```

---

## Controller Integration

`SystemSettingsController::update()` gains three new steps:

### 1. Extract plugin keys from `$_POST`

```php
// After collecting core input ($input), merge plugin inputs.
$pluginSections = SettingsRegistry::getSections($permissions);
foreach ($pluginSections as $section) {
    $keys = SettingsRegistry::getSectionKeys($section->id);
    foreach ($keys as $key) {
        // Convert key format to form field name: 'smtp.host' → 'smtp_host'
        $field = str_replace('.', '_', $key);
        if (isset($_POST[$field])) {
            $input[$key] = trim($_POST[$field] ?? '');
        }
    }
}
```

### 2. Validate plugin sections

```php
// Run core validation first, then each plugin section.
$errors = [];
$errors += $this->validateCore($input);

foreach ($pluginSections as $section) {
    $keys = SettingsRegistry::getSectionKeys($section->id);
    if (empty($keys)) continue;  // skip sections with no fields

    $sectionInput = [];
    foreach ($keys as $key) {
        if (isset($input[$key])) {
            $sectionInput[$key] = $input[$key];
        }
    }

    $sectionErrors = SettingsRegistry::validateSection($section->id, $sectionInput, $permissions);
    foreach ($sectionErrors as $field => $msg) {
        // Map key format to form field name for error display.
        $errors[str_replace('.', '_', $field)] = $msg;
    }
}
```

### 3. Save plugin sections

```php
// After core save, delegate to each plugin.
foreach ($pluginSections as $section) {
    $keys = SettingsRegistry::getSectionKeys($section->id);
    if (empty($keys)) continue;

    $sectionInput = [];
    foreach ($keys as $key) {
        if (isset($input[$key])) {
            $sectionInput[$key] = $input[$key];
        }
    }

    SettingsRegistry::saveSection($section->id, $sectionInput, $service);
}
```

### 4. Display section values in the view

The `show()` method passes section data to the view:

```php
$sections = SettingsRegistry::getSections($permissions);
// Each $section → $section->id, $section->label, $section->column,
//                  $section->render($ctx), $section->keys, $section->permission
```

---

## View Integration

`settings.php` gains a loop over sections instead of hard-coded cards:

```php
<?php foreach ($sections as $section): ?>
    <?php if ($section->column !== $col): continue; endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold small"><?= htmlspecialchars($section->label) ?></span>
        </div>
        <div class="card-body">
            <?= $section->renderBody($ctx + [
                'errors'      => $errors,
                'settings'    => $settings,  // for pre-populating values
                'service'     => $service,
                'permissions' => $permissions,
            ]) ?>
        </div>
    </div>
<?php endforeach; ?>
```

The core Application and Mailer cards remain as manual `div` blocks. The plugin section loop is inserted at hook points (after Application in left column, after Mailer in right column).

---

## Sensitive Values

Password fields and secrets follow a strict rule: **never display the stored value**.

- Password fields render as `<input type="password">` with an empty `value` attribute
- A helper `form_text_field($name, $value)` renders regular fields
- A helper `form_password_field($name)` renders masked fields (no value pre-filled)
- The `render` callback chooses which to use

```php
// Example in plugin hooks.php:
SettingsRegistry::addSection([
    'id'     => 'smtp',
    'label'  => 'SMTP Settings',
    'column' => 'right',
    'order'  => 10,
    'keys'   => ['smtp.host', 'smtp.port', 'smtp.user', 'smtp.pass', 'smtp.encryption'],
    'permission' => 'settings.smtp',
    'render' => function (array $ctx): string {
        extract($ctx);
        ob_start();
        ?>
        <div class="mb-3">
            <label for="smtp_host" class="form-label">Host</label>
            <?= form_text_field('smtp_host', $settings['smtp.host'] ?? '') ?>
        </div>
        <div class="mb-3">
            <label for="smtp_pass" class="form-label">Password</label>
            <?= form_password_field('smtp_pass') ?>
            <div class="form-text">Leave blank to keep the current password.</div>
        </div>
        <?php
        return ob_get_clean();
    },
    'validate' => function (array $input): array {
        $errors = [];
        if (!empty($input['smtp.host']) && !filter_var($input['smtp.host'], FILTER_VALIDATE_URL)) {
            $errors['smtp.host'] = 'Invalid host.';
        }
        return $errors;
    },
    'save' => function (array $input, SystemSettingService $svc): void {
        if (!empty($input['smtp.host'])) $svc->set('smtp.host', $input['smtp.host']);
        if (!empty($input['smtp.port'])) $svc->set('smtp.port', $input['smtp.port']);
        if (!empty($input['smtp.user'])) $svc->set('smtp.user', $input['smtp.user']);
        // Only update password if a new value was provided.
        if (!empty($input['smtp.pass'])) $svc->set('smtp.pass', $input['smtp.pass']);
        if (!empty($input['smtp.encryption'])) $svc->set('smtp.encryption', $input['smtp.encryption']);
    },
    'source' => 'smtp',
]);
```

---

## Plugin Integration

### Minimal plugin manifest entry

```json
{
    "name": "smtp",
    "hooks": ["settings.sections"],
    "permissions": ["settings.smtp"]
}
```

### Plugin `hooks.php`

```php
<?php

use App\Core\SettingsRegistry;
use App\Services\SystemSettingService;

SettingsRegistry::addSection([
    'id'       => 'smtp',
    'label'    => 'SMTP Settings',
    'column'   => 'right',
    'order'    => 10,
    'keys'     => ['smtp.host', 'smtp.port', 'smtp.user', 'smtp.pass', 'smtp.encryption'],
    'permission' => 'settings.smtp',
    'render'   => fn (array $ctx) => require __DIR__ . '/views/settings/smtp.php',
    'validate' => fn (array $input) => require __DIR__ . '/src/SmtpValidator.php',
    'save'     => fn (array $input, SystemSettingService $svc) => require __DIR__ . '/src/SmtpSaver.php',
    'source'   => 'smtp',
]);
```

The kernel's plugin boot flow (already implemented) loads `hooks.php` for enabled plugins (step 7 in the boot flow: "Registers plugin-declared hooks via HookRegistry"). The settings hooks would work similarly — the kernel scans for `hooks.php` and includes it after the registry is initialized.

**Alternative**: Use a dedicated `settings.php` entry point in the plugin manifest:

```json
{
    "settings": "src/SettingsRegistrar.php"
}
```

This avoids relying on the generic `hooks.php` mechanism and keeps settings code explicit. The controller can call this during `show()` and `update()` (lazy loading).

**Recommended approach**: Dedicated `settings.php` or manifest key. Avoids polluting `hooks.php` with settings-specific registration and keeps settings logic discoverable.

---

## Hook Point (for additional extensibility)

A single hook point for plugins that want to inject logic without registering a full section:

```
settings.section.rendered  — fired after each section renders (for logging, analytics)
settings.before_save       — fired before all sections save (for pre-save validation)
settings.after_save        — fired after all sections save (for post-save actions)
```

These are fired via the existing `HookRegistry` mechanism. Low priority — add only if there's demand.

---

## Roadmap Placement

| Item | Phase | Priority |
|------|-------|----------|
| SettingsRegistry + SettingsSection classes | PHASE2-3 | Medium |
| SystemSettingsController integration | PHASE2-3 | Medium |
| settings.php view refactor | PHASE2-3 | Medium |
| SMTP plugin (example) | PHASE2-3+ | Low |
| `settings.before_save` / `settings.after_save` hooks | PHASE3 | Deferred |

---

## Open Design Questions

| ID | Question | Decision |
|----|----------|----------|
| SQ-1 | Should settings keys follow a naming convention (e.g. `plugin.key`)? | Yes — `<plugin>.<key>` prefix required. Enforced by registry. |
| SQ-2 | Should the controller be aware of plugin keys, or should plugins handle their own POST extraction? | Controller extracts by keys; plugins declare their keys via the registry. |
| SQ-3 | Should sensitive fields be auto-detected (e.g. keys ending in `.pass`, `.secret`) or declared explicitly? | Declared explicitly — the `render` callback chooses the input type. No auto-detection. |
| SQ-4 | How does a plugin discover existing core settings values to display? | Passed via `$ctx['settings']` in the view — a full key→value map. |
| SQ-5 | Can a plugin's section appear before core sections? | Yes — via `order`. Core sections use `order: 10` (Application) and `order: 20` (Mailer). Plugin sections default to `order: 50`. |

---

## Files Affected (at implementation time)

| File | Change |
|------|--------|
| `app/Core/SettingsRegistry.php` | NEW |
| `app/Core/SettingsSection.php` | NEW |
| `app/Controllers/Admin/SystemSettingsController.php` | Merge plugin keys, validate, save |
| `app/Views/admin/settings.php` | Loop over sections |
| `lib/plugins/{name}/plugin.json` | `"settings"` key + `"permissions"` |
| `lib/plugins/{name}/hooks.php` | `SettingsRegistry::addSection()` calls |
