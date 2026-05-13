# Auth Features Design

> **Status:** Partially implemented (Remember Me done; forgot password, email verification, 2FA pending)
> **Roadmap:** Phase 2
> **Related:** MAILER-1 (Mailer), SETTINGS-1 (SettingsRegistry), PHASE2-1

---

## 1. Current Auth Architecture Summary

### What exists today

| Component | Location | Status |
|-----------|----------|--------|
| `AuthProviderInterface` | `app/Core/AuthProviderInterface.php` | Interface — provider-agnostic |
| `LocalAuthProvider` | `app/Auth/LocalAuthProvider.php` | Username/email + password_hash (bcrypt) |
| `AuthService` | `app/Auth/AuthService.php` | Session management, login/logout, user retrieval |
| `TokenService` | `app/Auth/TokenService.php` | Full API token lifecycle (generate, verify, revoke, list) |
| `TokenRepository` | `app/Models/TokenRepository.php` | `api_tokens` table queries |
| `UserRepository` | `app/Models/UserRepository.php` | `users` table CRUD |
| `GroupRepository` | `app/Models/GroupRepository.php` | `groups`, `user_groups`, `group_permissions` |
| `PermissionRepository` | `app/Models/PermissionRepository.php` | `permissions` |
| `Gate` | `app/Core/Gate.php` | Permission resolution via groups |
| `SessionAuth` | `app/Middleware/SessionAuth.php` | Session-based auth middleware → writes `principal` to container |
| `TokenAuth` | `app/Middleware/TokenAuth.php` | Token-based auth middleware → writes `principal` to container |
| `WebAuth` | `app/Middleware/WebAuth.php` | Any auth gate (session or token) |
| `WebPermission` | `app/Middleware/WebPermission.php` | Permission gate on top of `principal` |
| `AuthController` | `app/Controllers/AuthController.php` | `/signin` (GET), `/auth/login` (POST), `/auth/logout` (POST), `/auth/me` (GET) |
| `config/auth.php` | `config/auth.php` | Provider + session config |
| `blank.php` layout | `app/Views/layouts/blank.php` | Auth pages — local assets, hooks, no sidebar |
| User table | `database/migrations/0002_create_users_table.php` | id, username, email, password_hash, is_active, created_at, updated_at |
| API tokens table | `database/migrations/0007_create_api_tokens_table.php` | id, user_id, name, token_hash, last_used_at, expires_at, revoked_at, created_at |
| Settings | `config/mail.php` | from_address, from_name |

### Session configuration

```php
// config/auth.php
'session' => [
    'name'   => 'kernel_web_session',
    'lifetime' => 7200,  // 2 hours
    'secure' => false,
]
```

### Auth flow (current)

1. User POSTs credentials to `/auth/login`
2. `LocalAuthProvider::attempt()` validates against `users.password_hash`
3. `AuthService::login()` regenerates session ID and stores `user_id` in session
4. `SessionAuth` middleware loads user from `$_SESSION['user_id']`, resolves permissions via `Gate`
5. `principal` array written to container — used by all subsequent middleware

### What is missing (the gap)

- **No Remember Me** — session expires after `lifetime` (7200s). No long-lived option.
- **No Forgot Password** — no recovery mechanism. Admins must reset via `/admin/users`.
- **No Email Verification** — registration does not verify email (registration not yet implemented).
- **No User Registration** — `config/auth.php` has no `registration` key; no registration route exists.
- **No 2FA** — single-factor auth only.

---

## 2. Remember Me

### Purpose

Allow authenticated users to maintain their session beyond the default session lifetime by storing a database-backed long-lived token in a secure cookie.

### Design

- **Extends the session** — Remember Me does NOT create a separate auth path. It creates a long-lived session token that renews the session cookie.
- **Database-backed** — Token is stored in a new `auth_remember_tokens` table (not `api_tokens`). This allows revocation, auditing, and expiry control.
- **Cookie lifetime** — configurable via `config/auth.php`, default 30 days.
- **Tied to user** — one active token per user at a time (or N tokens with a limit). Tokens are revocable from the Profile Modal.
- **Token rotation** — on each request with a valid Remember Me token, a new token is generated and the old one is revoked. This prevents token theft window.
- **Cookie attributes** — `HttpOnly`, `Secure` (enforced when `session.secure` is true), `SameSite=Lax`.

### Database schema

```sql
CREATE TABLE auth_remember_tokens (
    id           INTEGER NOT NULL PRIMARY KEY,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash   VARCHAR(255) NOT NULL,
    expires_at   VARCHAR(32) NOT NULL,
    created_at   VARCHAR(32) NOT NULL,
    revoked_at   VARCHAR(32),
    ip_hash      VARCHAR(64),        -- optional: bind to initial IP hash
    user_agent   VARCHAR(255),       -- optional: bind to initial user agent (first 255 chars)
    UNIQUE(token_hash)
);
CREATE INDEX auth_remember_tokens_user_id ON auth_remember_tokens (user_id);
```

### Services

| Service | Purpose |
|---------|---------|
| `RememberMeService` (NEW) | Generate, verify, rotate, revoke remember tokens. Lives in `app/Auth/`. |
| `RememberTokenRepository` (NEW) | CRUD for `auth_remember_tokens`. Lives in `app/Models/`. |

### Controller changes

| Controller | Change |
|-----------|--------|
| `AuthController` | Add `remember` checkbox handling in `login()`. On POST, if checked, generate remember token. |

### View changes

| View | Change |
|------|--------|
| `app/Views/auth/login.php` | Add "Remember Me" checkbox before submit button. |

### Middleware changes

| Middleware | Change |
|-----------|--------|
| `SessionAuth` (NEW or extension) | `RememberMeAuth` — checks for remember token cookie, validates, renews session. Could be a separate middleware or integrated into `SessionAuth`. |

### Integration with `AuthService`

Option A — Extend `AuthService::login()`:
```php
public function login(array $credentials, bool $remember = false): ?array
{
    $user = $this->provider->attempt($credentials);
    if ($user === null) return null;

    $this->startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    if ($remember) {
        $this->createRememberToken($user['id']);
    }

    return $user;
}
```

Option B — New `RememberMeAuth` middleware:
- Fires after SessionAuth fails (user is null)
- Checks for remember token cookie
- Validates token, restores session, writes principal
- Does NOT create a separate auth path — restores the session path

**Decision: Option B** — keeps `AuthService` clean, follows the middleware chain pattern already established (SessionAuth → TokenAuth).

### Cookie configuration

```php
// config/auth.php
'remember_me' => [
    'enabled'  => true,
    'lifetime' => 2592000,  // 30 days in seconds
    'cookie'   => 'kernel_remember',
],
```

### Security rules

- Token is `random_bytes(32)` → hex, hashed with SHA-256 before storage
- Cookie is `HttpOnly`, `Secure` (when `session.secure` is true), `SameSite=Lax`
- Token revocation on password change
- Token revocation on logout (all remember tokens for user)
- Optional IP hash binding (deferred — adds friction for users on dynamic IPs)
- User-agent partial binding (first 255 chars) — optional, deferred
- Token expires automatically after `lifetime` seconds
- Token rotation on each use (old token revoked)

---

## 3. Forgot Password / Password Reset

### Purpose

Allow users who have forgotten their password to reset it via a time-limited, single-use email link.

### Design

- **Email-based** — uses the Mailer foundation to send a reset link.
- **Database-backed token** — single-use token stored in `auth_password_resets` table.
- **Token lifetime** — 60 minutes (short-lived, non-renewable).
- **Flow**:
  1. User visits `/auth/forgot-password`, enters email
  2. System looks up user by email (even if inactive — we don't leak active/inactive status)
  3. If user exists and is active, generate reset token, send email
  4. User clicks link → `/auth/reset-password?token=xxx&email=xxx`
  5. System validates token (exists, not expired, not used, user still active)
  6. User enters new password + confirmation
  7. On success: token revoked, password updated, all remember tokens revoked
  8. Email sent to user confirming password change (optional, deferred)

### Database schema

```sql
CREATE TABLE auth_password_resets (
    id           INTEGER NOT NULL PRIMARY KEY,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash   VARCHAR(255) NOT NULL,
    expires_at   VARCHAR(32) NOT NULL,
    used_at      VARCHAR(32),
    created_at   VARCHAR(32) NOT NULL,
    UNIQUE(token_hash)
);
CREATE INDEX auth_password_resets_user_id ON auth_password_resets (user_id);
```

### Services

| Service | Purpose |
|---------|---------|
| `PasswordResetService` (NEW) | Generate token, verify token, reset password, revoke token. Lives in `app/Auth/`. |
| `PasswordResetRepository` (NEW) | CRUD for `auth_password_resets`. Lives in `app/Models/`. |

### Controller changes

| Controller | Change |
|-----------|--------|
| `AuthController` | New actions: `forgotForm()` (GET /auth/forgot-password), `forgot()` (POST /auth/forgot-password), `resetForm()` (GET /auth/reset-password), `reset()` (POST /auth/reset-password) |

### View changes

| View | Change |
|------|--------|
| `app/Views/auth/forgot-password.php` | Form: email input, submit. Blank layout. |
| `app/Views/auth/reset-password.php` | Form: new password, confirm password, hidden token/email. Blank layout. |
| `app/Views/auth/forgot-password-sent.php` | "Check your email" confirmation page. |

### Mailer integration

| Template name | Purpose |
|--------------|---------|
| `password_reset` | Email body with reset link. Context: `userName`, `resetUrl`, `expiresMinutes` |

```php
// PasswordResetService sends email
$mailer->send((new MailMessage(...))
    ->withTemplate('password_reset', [
        'userName'  => $user['display_name'],
        'resetUrl'  => $url,
        'expiresMinutes' => 60,
    ]));
```

### Security rules

- Token is `random_bytes(32)` → hex, hashed with SHA-256 before storage
- Single-use — `used_at` set on successful password change
- Short-lived — expires after 60 minutes
- Never displayed in logs or settings
- User must still be active to use the token
- All remember tokens revoked on password change
- Even if user doesn't exist, show "If an account exists, you'll receive an email" (no user enumeration)
- Email parameter in reset URL is for display/validation only — token is the source of truth
- Link includes both token and email for UX (showing "Reset for user@example.com")

---

## 4. Email Verification

### Purpose

Verify that a user controls the email address they registered with. Prevents fake/typo email addresses.

### Design

- **Token-based** — single-use token stored in `auth_email_verifications` table.
- **Email-based** — uses the Mailer foundation to send a verification link.
- **Token lifetime** — 24 hours.
- **Flow**:
  1. User registers (or admin creates account)
  2. Verification token generated, email sent
  3. User clicks link → `/auth/verify?token=xxx`
  4. Token validated, `email_verified_at` set, user activated if was inactive
  5. Confirmation message shown

### Database schema

```sql
ALTER TABLE users ADD COLUMN email_verified_at VARCHAR(32);

CREATE TABLE auth_email_verifications (
    id           INTEGER NOT NULL PRIMARY KEY,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash   VARCHAR(255) NOT NULL,
    expires_at   VARCHAR(32) NOT NULL,
    used_at      VARCHAR(32),
    created_at   VARCHAR(32) NOT NULL,
    UNIQUE(token_hash)
);
CREATE INDEX auth_email_verifications_user_id ON auth_email_verifications (user_id);
```

### Services

| Service | Purpose |
|---------|---------|
| `EmailVerificationService` (NEW) | Generate token, verify token, resend token. Lives in `app/Auth/`. |
| `EmailVerificationRepository` (NEW) | CRUD for `auth_email_verifications`. Lives in `app/Models/`. |

### Controller changes

| Controller | Change |
|-----------|--------|
| `AuthController` | New actions: `verifyForm()` (GET /auth/verify), `verify()` (GET /auth/verify — token lookup), `resend()` (POST /auth/verify/resend) |

### View changes

| View | Change |
|------|--------|
| `app/Views/auth/verify-email.php` | "You're logged in but haven't verified your email" prompt. Link to resend. |
| `app/Views/auth/verify-email-sent.php` | "Verification email sent" confirmation. |

### Mailer integration

| Template name | Purpose |
|--------------|---------|
| `email_verification` | Email body with verification link. Context: `userName`, `verifyUrl` |

### Security rules

- Token is `random_bytes(32)` → hex, hashed with SHA-256 before storage
- Single-use — `used_at` set on verification
- Short-lived — expires after 24 hours
- Never displayed in logs
- User must be authenticated (logged in) to use the token
- Resend rate-limited: max 1 resend per 60 seconds (in-memory counter per user)
- `email_verified_at` is NULL until verified

---

## 5. User Registration

### Purpose

Allow new users to create accounts. Controlled by a single configuration toggle. **Disabled by default.**

### Design

- **Config-gated** — `config/auth.php['registration']['enabled']` must be `true`. When false, all registration routes return 404.
- **No open registration without email verification** — if email verification is enabled, user must verify before full activation.
- **Admin can always create users** — admin user management is unaffected.
- **Registration form fields**: username, email, password, password confirmation, display name
- **Server-side validation** — keyed field errors, reuse existing `UserRepository` methods
- **On success**: creates user (inactive if verification pending, active if verification disabled), auto-logs in, redirects to `/`

### Configuration

```php
// config/auth.php
'registration' => [
    'enabled'       => false,
    'require_email_verification' => false,  // requires email_verification feature
    'auto_login'    => true,                // auto-login after registration
    'redirect'      => '/',                  // where to redirect after registration
],
```

### Controller changes

| Controller | Change |
|-----------|--------|
| `AuthController` | New actions: `registerForm()` (GET /auth/register), `register()` (POST /auth/register) |

### View changes

| View | Change |
|------|--------|
| `app/Views/auth/register.php` | Form: display_name, username, email, password, password confirmation. Blank layout. "Already have an account? Sign in" link. |

### Validation

| Field | Rules |
|-------|-------|
| display_name | required, 1-100 chars |
| username | required, 3-64 chars, alphanumeric + hyphens, unique |
| email | required, valid email, unique |
| password | required, min 8 chars, confirmation match |

### Security rules

- Password validated with `password_hash()` (bcrypt, PHP default)
- Rate limiting (deferred) — prevent abuse
- No account enumeration — errors show generically if username or email taken
- User created with `is_active = 0` if email verification required, `1` if not

---

## 6. Two-Factor Authentication (2FA)

### Purpose

Add a second authentication factor to login. **TOTP (RFC 6238)** — compatible with Google Authenticator, Authy, and similar apps. **Not SMS** — requires user to have a TOTP app.

### Design

- **TOTP only** — no SMS support. SMS requires a carrier, costs money, and is insecure (SIM swapping).
- **Per-user** — users enable/disable 2FA independently. Disabled by default.
- **Recovery codes** — 10 one-time-use backup codes generated when 2FA is enabled. Stored as SHA-256 hashes.
- **Login flow**:
  1. User enters credentials → validated by `LocalAuthProvider`
  2. If user has 2FA enabled, prompt for TOTP code
  3. TOTP code validated against stored secret
  4. On success, complete session as normal
- **Profile Modal integration** — 2FA settings in the Profile Modal (new tab or section)
- **Admin override** — admin can reset a user's 2FA (revoke secret + recovery codes)

### Database schema

```sql
ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255);
-- totp_secret is NULL when 2FA is disabled. Stored as base32 (QREncode format).

CREATE TABLE auth_2fa_recovery_codes (
    id         INTEGER NOT NULL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash  VARCHAR(255) NOT NULL,
    used_at    VARCHAR(32),
    created_at VARCHAR(32) NOT NULL,
    UNIQUE(code_hash)
);
CREATE INDEX auth_2fa_recovery_codes_user_id ON auth_2fa_recovery_codes (user_id);
```

### Services

| Service | Purpose |
|---------|---------|
| `TwoFactorService` (NEW) | Generate secret, verify TOTP code, generate recovery codes, validate recovery code, disable 2FA. Lives in `app/Auth/`. |
| `TwoFactorRepository` (NEW) | NOT needed — 2FA data is on the `users` table (totp_secret column) and `auth_2fa_recovery_codes`. |

### Controller changes

| Controller | Change |
|-----------|--------|
| `AuthController` | Add `twoFactorForm()` (GET /auth/two-factor), `twoFactor()` (POST /auth/two-factor) |
| `ProfileModalController` (or new Profile2FAController) | Add 2FA management endpoints |

### View changes

| View | Change |
|------|--------|
| `app/Views/auth/two-factor.php` | TOTP code input during login. Blank layout. |
| `app/Views/profile/two-factor.php` | 2FA management in Profile Modal (QR code, enable/disable, recovery codes). |

### Mailer integration

| Template name | Purpose |
|--------------|---------|
| `two_factor_enabled` | "2FA enabled" confirmation email (optional, deferred) |
| `two_factor_disabled` | "2FA disabled" warning email (optional, deferred) |

### Security rules

- TOTP secret generated with `random_bytes(10)` → base32 (20 chars)
- TOTP window: ±1 step (30s × 3 = 90s total window)
- Recovery codes: `random_bytes(10)` → hex, hashed with SHA-256
- Recovery codes are one-time use — single-use flag
- All recovery codes revoked when 2FA is disabled
- TOTP secret never displayed after initial generation (except as QR code)
- Secret never logged
- If user loses their device, admin can reset 2FA

### TOTP algorithm

```
1. User enables 2FA → secret = base32(random_bytes(10))
2. QR code generated via https://api.qrserver.com/v1/create-qr-code/?data=otpauth://totp/Kernel-Web:user@example.com?secret=XXXXX&size=200x200
3. User scans QR code in their TOTP app
4. On login, TOTP code is verified via:
   $step = floor(time() / 30);
   $hmac = hash_hmac('sha1', pack('N*', $step), hex2bin($hexSecret), true);
   $offset = $hmac[19] & 0x0F;
   $code = (($hmac[$offset] & 0x7F) << 24 | ($hmac[$offset+1] & 0xFF) << 16 | ($hmac[$offset+2] & 0xFF) << 8 | ($hmac[$offset+3] & 0xFF)) % 1000000;
   $verified = str_pad($code, 6, '0', STR_PAD_LEFT) === $inputCode;
```

---

## 7. Required Database Schema Changes

### New tables

| Table | Purpose |
|-------|---------|
| `auth_remember_tokens` | Long-lived remember me tokens |
| `auth_password_resets` | Password reset tokens |
| `auth_email_verifications` | Email verification tokens |
| `auth_2fa_recovery_codes` | 2FA backup codes |

### Column additions

| Table | Column | Type | Notes |
|-------|--------|------|-------|
| `users` | `email_verified_at` | VARCHAR(32) NULL | NULL = not verified |
| `users` | `totp_secret` | VARCHAR(255) NULL | NULL = 2FA disabled |

### Migration ordering

Migrations must run in this order:
1. `users.email_verified_at` (column add)
2. `users.totp_secret` (column add)
3. `auth_email_verifications` (new table)
4. `auth_remember_tokens` (new table)
5. `auth_password_resets` (new table)
6. `auth_2fa_recovery_codes` (new table)

Each migration is a separate file (e.g., `0030_add_email_verified_to_users.php`).

---

## 8. Required Services / Repositories / Controllers / Views

### New files

| File | Purpose |
|------|---------|
| `app/Auth/RememberMeService.php` | Remember me token lifecycle |
| `app/Auth/PasswordResetService.php` | Password reset token lifecycle |
| `app/Auth/EmailVerificationService.php` | Email verification token lifecycle |
| `app/Auth/TwoFactorService.php` | TOTP secret + code management |
| `app/Models/RememberTokenRepository.php` | `auth_remember_tokens` CRUD |
| `app/Models/PasswordResetRepository.php` | `auth_password_resets` CRUD |
| `app/Models/EmailVerificationRepository.php` | `auth_email_verifications` CRUD |
| `app/Middleware/RememberMeAuth.php` | Remember me token middleware |

### Modified files

| File | Change |
|------|--------|
| `app/Controllers/AuthController.php` | Add forgot/reset/register/verify/2FA actions |
| `app/Views/auth/login.php` | Add remember me checkbox + "Forgot Password" link |
| `app/Views/auth/register.php` | NEW — registration form |
| `app/Views/auth/forgot-password.php` | NEW — email input form |
| `app/Views/auth/forgot-password-sent.php` | NEW — confirmation |
| `app/Views/auth/reset-password.php` | NEW — password form |
| `app/Views/auth/verify-email.php` | NEW — pending verification page |
| `app/Views/auth/verify-email-sent.php` | NEW — confirmation |
| `app/Views/auth/two-factor.php` | NEW — TOTP input |
| `config/auth.php` | Add `remember_me`, `registration` sections |

### New email templates

| Template | Purpose |
|----------|---------|
| `password_reset` | Reset password email |
| `email_verification` | Verify email email |

---

## 9. Mailer Integration Points

| Feature | Template | Context variables | When sent |
|---------|----------|-------------------|-----------|
| Forgot Password | `password_reset` | `userName`, `resetUrl`, `expiresMinutes` | After reset token generated |
| Email Verification | `email_verification` | `userName`, `verifyUrl` | After registration or admin-created account |
| 2FA Enabled (optional) | `two_factor_enabled` | `userName`, `enabledAt` | When user enables 2FA |
| 2FA Disabled (optional) | `two_factor_disabled` | `userName`, `disabledAt` | When user disables 2FA |

### Template location

Templates follow the existing `TemplateRegistry` pattern:
- Core templates: `app/Views/emails/` (e.g., `app/Views/emails/password_reset.php`)
- Plugin templates: registered via `TemplateRegistry::addPath()`

### Core templates in first slice

Only `password_reset` and `email_verification` templates ship in the first implementation. 2FA emails are deferred.

---

## 10. Security Rules

### Token generation

All auth tokens follow the same pattern:
- Generate: `random_bytes(32)` → `bin2hex()` → 64-char hex
- Store: `hash('sha256', $raw)`
- Never: display token values in logs, settings, or UI
- Never: store raw token in database

### Cookie security

- `HttpOnly` on all auth cookies
- `Secure` when `config.auth.session.secure` is true
- `SameSite=Lax` on all auth cookies
- `login_csrf` — separate CSRF token for login form (not new, already exists as standard practice)

### Password storage

- `password_hash()` with PHP default algorithm (bcrypt as of PHP 8.x)
- Never: store plaintext passwords anywhere
- Never: log password hashes or verification results
- On password change: revoke ALL remember tokens for the user

### Enumeration prevention

- Login returns "Invalid credentials" (never "user not found")
- Forgot password returns "If an account exists, you'll receive an email" (never "no account found")
- Registration returns generic error on duplicate (never reveals which field was taken)
- Dummy password hash computed for non-existent users (prevents timing-based enumeration — already implemented in `LocalAuthProvider`)

### Email safety

- Verification/reset links include token (source of truth) + email (display only)
- Links expire (60min for reset, 24h for verification)
- Links are single-use
- Links do not expose user ID (token is the only identifier)
- Emails do not contain sensitive data beyond user's display name

### Session security

- Session ID regenerated on every login
- Session destroyed on logout
- `email_verified_at` NULL until verified (respects verification requirement)

---

## 11. Test Plan

### Remember Me tests

| Test | Description |
|------|-------------|
| `remember_token_generate` | Token is 64-char hex, stored as SHA-256 hash |
| `remember_token_verify_valid` | Valid token renews session |
| `remember_token_verify_expired` | Expired token is rejected |
| `remember_token_verify_revoked` | Revoked token is rejected |
| `remember_token_rotate` | Old token revoked on new token generation |
| `remember_token_logout_revokes_all` | Logout revokes all remember tokens |
| `remember_token_password_change_revokes` | Password change revokes all remember tokens |
| `remember_token_cookie_attrs` | Cookie is HttpOnly, Secure (when enabled), SameSite=Lax |

### Forgot Password tests

| Test | Description |
|------|-------------|
| `reset_token_generate` | Token is 64-char hex, stored as SHA-256 |
| `reset_token_verify_valid` | Valid token allows password change |
| `reset_token_verify_expired` | Expired token rejected |
| `reset_token_verify_used` | Used token rejected |
| `reset_token_verify_inactive_user` | Inactive user rejected |
| `reset_token_password_updated` | Password is updated, token revoked |
| `reset_email_sent` | Password reset email sent with correct template |
| `reset_no_user_enumeration` | Non-existent email shows same message as existing |

### Email Verification tests

| Test | Description |
|------|-------------|
| `verify_token_generate` | Token is 64-char hex, stored as SHA-256 |
| `verify_token_verify_valid` | Valid token sets email_verified_at |
| `verify_token_verify_expired` | Expired token rejected |
| `verify_token_verify_used` | Used token rejected |
| `verify_user_activated` | Inactive user activated on verification |
| `verify_email_sent` | Verification email sent with correct template |
| `verify_resend_rate_limit` | Resend blocked within 60s window |

### 2FA tests

| Test | Description |
|------|-------------|
| `totp_secret_generate` | Secret is valid base32, 20 chars |
| `totp_code_verify_valid` | Valid TOTP code accepted |
| `totp_code_verify_wrong` | Wrong code rejected |
| `totp_code_verify_window` | Code within ±1 step accepted |
| `totp_code_verify_expired` | Code outside window rejected |
| `recovery_codes_generate` | 10 unique codes, hashed |
| `recovery_code_consume` | Single-use, revoked after use |
| `recovery_code_wrong` | Wrong code rejected |
| `totp_disable_revokes` | Disabling 2FA revokes secret and codes |

### Registration tests

| Test | Description |
|------|-------------|
| `registration_disabled` | Registration routes return 404 when disabled |
| `registration_enabled` | Registration form renders when enabled |
| `registration_valid` | Valid data creates user, logs in |
| `registration_validation` | Invalid data returns field errors |
| `registration_email_taken` | Duplicate email error |
| `registration_username_taken` | Duplicate username error |
| `registration_verification_pending` | User inactive when verification required |

---

## 12. Implementation Phases

### Phase 1: Remember Me (smallest safe slice)

**Why first**: Unlocks long-lived sessions with zero mailer dependency. Pure database + cookie work.

**Files**:
- Migration: `0030_create_auth_remember_tokens_table.php` (new table + config toggle)
- `app/Auth/RememberMeService.php`
- `app/Models/RememberTokenRepository.php`
- `app/Middleware/RememberMeAuth.php`
- `app/Controllers/AuthController.php` (modify `login()`)
- `app/Views/auth/login.php` (add checkbox)
- `config/auth.php` (add `remember_me` config)
- `tests/auth_test.php` (add remember me tests)

**Dependencies**: None

### Phase 2: Forgot Password + Email Verification

**Why together**: Both use the same token pattern and email templates. Shared infrastructure.

**Files**:
- Migration: `0031_add_email_verification_support.php` (add `email_verified_at` column + `auth_email_verifications` table)
- Migration: `0032_add_password_reset_support.php` (new `auth_password_resets` table)
- `app/Auth/PasswordResetService.php`
- `app/Auth/EmailVerificationService.php`
- `app/Models/PasswordResetRepository.php`
- `app/Models/EmailVerificationRepository.php`
- `app/Controllers/AuthController.php` (forgot/reset/verify actions)
- `app/Views/auth/forgot-password.php`
- `app/Views/auth/forgot-password-sent.php`
- `app/Views/auth/reset-password.php`
- `app/Views/auth/verify-email.php`
- `app/Views/auth/verify-email-sent.php`
- `app/Views/emails/password_reset.php`
- `app/Views/emails/email_verification.php`
- `config/mail.php` (templates section)
- `tests/auth_test.php` (add reset/verify tests)

**Dependencies**: Phase 1 (Remember Me), Mailer foundation (already done)

### Phase 3: User Registration

**Why third**: Registration triggers both email verification and (optionally) auto-login (which depends on Remember Me for long sessions).

**Files**:
- `app/Controllers/AuthController.php` (register actions)
- `app/Views/auth/register.php`
- `config/auth.php` (add `registration` config)
- `tests/auth_test.php` (add registration tests)

**Dependencies**: Phase 2 (email verification), Auth features design

### Phase 4: Two-Factor Authentication

**Why last**: Most complex feature. Requires QR code generation, TOTP algorithm, recovery codes, Profile Modal integration.

**Files**:
- Migration: `0033_add_2fa_support.php` (add `totp_secret` column + `auth_2fa_recovery_codes` table)
- `app/Auth/TwoFactorService.php`
- `app/Controllers/AuthController.php` (2FA actions)
- `app/Views/auth/two-factor.php`
- Profile Modal 2FA section (new tab or section)
- Tests

**Dependencies**: Phases 1-3, TOTP algorithm implementation

---

## 13. Open Questions

| ID | Question | Decision |
|----|----------|----------|
| AF-1 | Should Remember Me support multiple concurrent tokens per user, or one-at-a-time? | **One-at-a-time** (current token is revoked on next use via rotation). Can be extended to N tokens later. |
| AF-2 | Should email verification be a hard gate (user can't log in until verified) or soft (user can log in but sees "verify your email" banner)? | **Soft gate in Phase 1** — user can log in but gets a banner. Hard gate deferred to admin config. |
| AF-3 | Should registration require email verification to be enabled? | **No** — registration and email verification are independent. If verification is disabled, user is auto-activated. |
| AF-4 | Should 2FA use TOTP, webauthn, or both? | **TOTP first** — widest compatibility, no hardware needed. WebAuthn deferred. |
| AF-5 | Should admin be able to bypass 2FA for a user? | **Yes, reset only** — admin can revoke user's TOTP secret + recovery codes. Admin cannot "temporarily bypass" 2FA. |
| AF-6 | Should the reset password link include the email as a parameter? | **Yes, for UX** — the token is the source of truth, email is displayed to confirm the target account. Token validated server-side. |
| AF-7 | Should TOTP codes be 6-digit or 8-digit? | **6-digit** — standard for Google Authenticator, Authy, and all major TOTP apps. |
| AF-8 | Should recovery codes be displayed as a downloadable file or inline? | **Both** — show inline in a code block AND provide a download button. Once dismissed, cannot be re-displayed. |

---

## 14. Deferred / Not in Scope

- **SMS 2FA** — out of scope. Requires SMS gateway, costs money, less secure than TOTP.
- **Magic link login** — not the same pattern as password reset. Deferred.
- **OAuth login** — deferred (separate design).
- **LDAP/IMAP auth** — deferred (separate design).
- **Account lockout after failed attempts** — deferred. Rate limiting on login is a future enhancement.
- **Email notifications for auth events** — deferred (only password reset and email verification emails in Phase 2).
- **Brute force protection** — deferred. Consider IP-based rate limiting on login endpoints.
