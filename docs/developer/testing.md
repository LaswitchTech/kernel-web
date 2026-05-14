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
  auth_test.php        — CRUD tests for Users, Groups, Permissions, Tokens
  remember_me_test.php — Remember Me token repo, service, and AuthService integration
  forgot_password_test.php — Password reset token repo, service, and mailer integration
  email_verification_test.php — Email verification token repo, service, and mailer integration
  registration_test.php — User creation, duplicate detection, validation rules, verification email, config shape
  two_factor_test.php — TOTP code generation/verification, recovery codes, 2FA enable/disable, pending session state
  email_verification_test.php — Email Verification token repo, service, and mailer integration
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

### Auth tests (`auth_test.php`) — 105 assertions

Tests CRUD operations for core auth entities using an in-memory SQLite database with a production-equivalent schema (including `user_id` index on `api_tokens`). Covers `UserRepository` (create, findById, findByUsername, findByEmail, findByIdAny, findAllActive, findAll, update, update password, setActive, isUsernameTaken, isEmailTaken), `GroupRepository` (create, findById, findAll, update, delete, isNameTaken, isSystemGroup, findMembers, syncPermissions), `PermissionRepository` (create, findById, findAll, update, delete, isCodeTaken, isInUse), `TokenService` (generate, verify returns null for expired/inactive, revoke, listForUser with ownership validation), and `Gate` (permissionsForUser, userCan, can with missing permissions key). Constructor sanity checks verify all repos/services accept `DatabaseInterface`. Tests cross-entity behavior: group-user membership via `UserRepository::syncGroups()`, group-permission assignment, and permission inheritance.

### Mailer tests (`mailer_test.php`) — 63 assertions

Tests zero-dependency mailer infrastructure using a `FakeTransport` for capture-based assertions. Covers `MailMessage` construction and validation (from/to/subject/body/clear/bcc/headers), clone pattern for cc/bcc/headers/reply-to (original immutability), `Attachment` value object with `file()` factory and MIME auto-detection, `TemplateRegistry` (addCore, addPath, find core-first, render with context, clear), `MailTransport` (rejects attachments, rejects BCC, rejects header injection in CC, mail() delegation), `Mailer` facade (send, withTemplate, setTransport, transportIdentifier), template rendering via TemplateRegistry, and `MailerException` properties (message, code, transportName, type hierarchy).

### Remember Me tests (`remember_me_test.php`) — 40 assertions

Tests `RememberTokenRepository` and `RememberMeService` with in-memory SQLite. Covers token creation, selector lookup, revoke, revokeAllForUser, token rotation, cookie parsing (null, no-colon, empty, normal, multi-colon), valid token attempt with rotation verification, expired token rejection, revoked token rejection, inactive user rejection, wrong validator rejection, and AuthService integration (login with/without remember, restoreSession).

### Forgot Password tests (`forgot_password_test.php`) — 29 assertions

Tests `PasswordResetRepository` and `PasswordResetService` with in-memory SQLite. Covers token generation (64-char hex, SHA-256 hash storage), active user initiation, nonexistent email rejection (enumeration-safe), inactive user rejection, valid token verification, expired token rejection, used token rejection, inactive user with valid token rejection, nonexistent token rejection, password update with full token revocation, email template rendering, email sending with mailer (FakeTransport), null mailer handling, and revokeAllForUser.

### Email Verification tests (`email_verification_test.php`) — 38 assertions

Tests `EmailVerificationRepository` and `EmailVerificationService` with in-memory SQLite. Covers token generation (64-char hex, SHA-256 hash storage), already-verified user rejection, nonexistent user handling, valid token verification with `email_verified_at` update, expired token rejection, used token rejection, nonexistent token rejection, inactive user email verification, token revocation after verification, email template rendering, email sending with mailer (FakeTransport), null mailer handling, resend (new token replaces old), resend for verified user, resend for nonexistent user (enumeration-safe), and revokeAllForUser.

### Registration tests (`registration_test.php`) — 55 assertions

Tests `UserRepository::create()` (user creation with all fields), password hashing (`PASSWORD_DEFAULT`/bcrypt, `password_verify`), duplicate username and email detection (`isUsernameTaken`/`isEmailTaken`), email verification integration (token generation, email sending via mailer, timestamp setting), already-verified user cannot generate new token, validation rule patterns (display_name, username regex, email format, password length), unique constraint enforcement on both username and email (`RuntimeException`), inactive user creation, inactive user email verification, null mailer graceful handling, `revokeAllForUser`, and registration config shape (`enabled=false`, `require_email_verification=true`, `auto_login=true`, `redirect='/'`).

### Two-Factor Authentication tests (`two_factor_test.php`) — 64 assertions

Tests `TwoFactorRepository`, `TwoFactorService`, and `AuthService` 2FA flow with in-memory SQLite. Covers: secret generation (160-bit entropy, Base32), TOTP code verification (current step, ±1 window, ±2 step rejection), recovery code generation (10 codes, SHA-256 hashes, single-use), enable/disable, otpauth URI (RFC 6221 format, algorithm, digits, period params), pending 2FA session state (login → pending → completeTwoFactor → full session), expired pending state rejection, non-2FA user login without pending state, enable without prior generate, and recovery code uniqueness.

Tests `UserRepository::create()` (user creation with all fields), password hashing (`PASSWORD_DEFAULT`/bcrypt, `password_verify`), duplicate username and email detection (`isUsernameTaken`/`isEmailTaken`), email verification integration (token generation, email sending via mailer, timestamp setting), already-verified user cannot generate new token, validation rule patterns (display_name, username regex, email format, password length), unique constraint enforcement on both username and email (`RuntimeException`), inactive user creation, inactive user email verification, null mailer graceful handling, `revokeAllForUser`, and registration config shape (`enabled=false`, `require_email_verification=true`, `auto_login=true`, `redirect='/'`).

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
