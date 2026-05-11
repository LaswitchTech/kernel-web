# Testing in Kernel-Web

## Overview

Kernel-Web uses a zero-dependency test framework. No Composer, no PHPUnit, no external packages.

Tests run directly with PHP and discover all `*_test.php` files in the `tests/` directory.

## Running Tests

### Run all suites

```bash
php tests/run.php
```

### Run a single suite

```bash
php tests/run.php router       # route registration tests
php tests/run.php plugin       # plugin manifest and lifecycle tests
php tests/run.php migration    # migration runner tests
```

## Architecture

```text
tests/
  run.php              — Test runner (discovers *_test.php)
  assert.php           — Assertion helpers (zero dependencies)
  bootstrap.php        — Kernel autoloader for tests
  router_test.php      — Route registration, priority ordering, parameter extraction
  plugin_test.php      — Plugin manifest, validation, registry buckets
  migration_test.php   — Migration runner with in-memory SQLite
```

### Test runner

`tests/run.php` discovers all `*_test.php` files in the `tests/` directory and executes each in a separate process via `proc_open`. The positional argument filters by base name (without the `_test.php` suffix).

Failure propagation: each test file must exit with code 0 to pass. The runner exits 1 if any suite fails.

### Assertion helpers

`tests/assert.php` provides the following assertions:

| Function | Description |
|----------|-------------|
| `assert_true($value, $msg)` | Assert value is strictly `true` |
| `assert_false($value, $msg)` | Assert value is strictly `false` |
| `assert_equal($expected, $actual, $msg)` | Assert strict equality |
| `assert_equal_types($expected, $actual, $msg)` | Assert equality with matching types |
| `assert_null($value, $msg)` | Assert value is `null` |
| `assert_not_null($value, $msg)` | Assert value is not `null` |
| `assert_instance_of($obj, $class, $msg)` | Assert object is instance of class |
| `assert_array_has_key($arr, $key, $msg)` | Assert array has key |
| `assert_array_has_length($arr, $len, $msg)` | Assert array length |
| `assert_contains($needle, $haystack, $msg)` | Assert needle in string or array |
| `assert_raises($fn, $exceptionClass, $msg)` | Assert callable throws exception |
| `assert_json_valid($json, $msg)` | Assert JSON is valid |
| `assert_class_exists($class, $msg)` | Assert class or interface exists |

Each assertion increments a pass/fail counter. `summary()` prints all failures with context, then the pass/fail count, then "ALL PASSED" or "FAILED".

### Bootstrap

`tests/bootstrap.php` mirrors the kernel's PSR-0 autoloader so test files can use `App\Core\...` classes without Composer.

### Writing new test files

1. Create `tests/your_feature_test.php`
2. At the top, include the bootstrap and assertions:

```php
<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\YourClass;

// ... assertions using assert_* helpers ...

summary();
exit($__FAIL__ > 0 ? 1 : 0);
```

3. Each test file manages its own state. There is no global test context.
4. The file's base name (without `_test.php`) is used to filter test execution.

## Current Test Suites

### Router tests (`router_test.php`) — 25 assertions

Tests route registration via `Router::get()`, `post()`, `put()`, `delete()`, `registerRoute()`, and `registerPluginRoutes()`. Covers priority ordering, parameter extraction, middleware storage, handler format (bare vs. qualified), and dispatch behavior.

### Plugin tests (`plugin_test.php`) — 47 assertions

Tests `PluginManifest` parsing, validation, and field accessors. Covers required/optional fields, scalar dependency rejection, `PluginRegistry` bucket management (discovered, enabled, disabled, invalid), enable/disable transitions, `toArray()`, and count across all buckets.

### Migration tests (`migration_test.php`) — 14 assertions

Tests `MigrationRunner` with an in-memory SQLite database via a `TestDB` wrapper implementing `DatabaseInterface`. Covers pending detection, file discovery, `run()`, `applied()`, idempotent `run()` on already-applied migrations, invalid class detection, rollback with missing files, and migration name derivation.

## Dependencies

### PHP

Requires PHP 8.0+ (tested with PHP 8.2). No extensions beyond standard library.

### SQLite

Migration tests require the `pdo_sqlite` extension, which is bundled with PHP 8.0+. CI uses the `shivammathur/setup-php@v2` action which includes it by default.

## CI

The GitHub Actions CI workflow (`.github/workflows/ci.yml`) runs the test framework:

```yaml
test-runner:
  name: Run Tests
  runs-on: ubuntu-latest
  steps:
    - name: Checkout
      uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        coverage: none

    - name: Run test suite
      run: php tests/run.php
```

## Temp Directory Behavior

Migration tests create temporary directories under `sys_get_temp_dir()` (typically `/tmp` on Linux, `/var/folders/...` on macOS):

```
/tmp/kernel-test-migrations-{uniqid}/
```

Each test file creates its own unique directory via `uniqid()`. All files are cleaned up at the end of the test via `glob('unlink')` and `rmdir()`. No production database files are written.

## Test Isolation

Each test suite runs in its own process via `proc_open` — globals, class definitions, and SQLite state are never shared between suites. Within a suite, state persists across tests (each file is a sequential script, not individual functions).
