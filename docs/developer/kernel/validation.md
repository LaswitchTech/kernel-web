# Validation Error Convention

All form validation in Kernel-Web uses **keyed error arrays**.

## Rule

When validation errors are displayed beside form fields, the error array must use **field names as keys**:

```php
$errors = [];
if ($name === '') {
    $errors['name'] = 'Name is required.';
}
if ($email === '') {
    $errors['email'] = 'Email is required.';
}
```

For errors that apply to the entire form (not a specific field), use `_global`:

```php
$errors['_global'] = 'An unexpected error occurred.';
```

## Patterns

### Controller validation

```php
$errors = $this->validate($input);

if (!empty($errors)) {
    // Re-render form with 422 — pass $errors directly to the view
    http_response_code(422);
    return;
}
```

Never reassign `$errors = []` between validation and view rendering — it discards all error messages.

### Service validation

Service validation methods should also return keyed errors:

```php
// Returns: array<string, string>  Field => message; empty = valid.
public function validate(array $data): array
{
    $errors = [];
    if (empty($data['name'])) {
        $errors['name'] = 'Name is required.';
    }
    return $errors;
}
```

### View rendering

```php
<input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
       value="<?= htmlspecialchars($old['name'] ?? '') ?>">
<?php if (isset($errors['name'])): ?>
    <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
<?php endif; ?>
```

## Affected Code

All admin CRUD controllers, SetupController, and CatalogService use keyed errors. The following are consistent with this convention:

- `UserController::store()`, `UserController::updateAccount()`
- `GroupController::validateGroup()`
- `PermissionController::validatePermission()`
- `SystemSettingsController::validate()`
- `CatalogService::validateCreate()`, `CatalogService::validateUpdate()`
- `ChatService::validateRoom()`
- `SetupController::validateAppSettings()`, `SetupController::validateAdminFields()`
- `SetupService::validateAdmin()`

## Common Bugs

- `$errors = []` after validation — discards all field errors before the view renders
- Flat array `['Error message']` — the view expects `$errors['field']` and cannot match them
- Returning 200 or redirecting on validation failure — should return 422 and re-render the form
