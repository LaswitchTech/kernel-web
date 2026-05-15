# SMTP Mail Plugin Design

> **Status:** Design only — not implemented
> **Roadmap:** Phase 2 required item
> **Related:** mailer.md, settings-hooks.md

---

## Purpose

Replace PHP's built-in `mail()` transport with a reliable SMTP transport for local development (macOS/MAMP lacks an MTA) and production use. The plugin follows the existing SettingsRegistry pattern for configuration.

---

## Why SMTP Is Required for macOS/MAMP

PHP's `mail()` function delegates to the host's MTA (Sendmail, Postfix, etc.). macOS and MAMP do not ship with a configured MTA. Without an MTA:

- `mail()` returns `false` or silently drops messages
- Forgot Password and Email Verification flows deliver nothing
- Developers cannot verify email-related features locally

The SMTP plugin solves this by routing mail through an external SMTP server (local development can use MailHog, Mailtrap, Amazon SES, SendGrid, etc.).

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│               Kernel Core (Mailer)               │
│                                                  │
│  Mailer → TransportInterface                     │
│                  │                               │
│         ┌───────┴───────┐                        │
│         ▼               ▼                        │
│   MailTransport   SmtpTransport                  │
│   (core, default)   (plugin)                     │
└─────────────────────────────────────────────────┘

Configuration:
  config/mail.php        → from_address, from_name
  SettingsRegistry       → smtp host, port, auth, encryption
  Mailer config          → mail.transport = 'smtp' or 'mail'
```

### Core vs Plugin Boundary

| Concern | Location | Rationale |
|---------|-----|-----|
| `TransportInterface` | Core | Abstraction callers depend on |
| `MailTransport` | Core | Zero-dependency default |
| `SmtpTransport` | Plugin | Requires network/credentials |
| SMTP settings | Plugin | Via SettingsRegistry |
| Test-email endpoint | Plugin | Plugin-specific feature |
| Email templates | Core | Transport-agnostic |

---

## Plugin Structure

```
lib/plugins/smtp/
├── plugin.json
├── hooks.php                     — Registers transport override + settings section
├── src/
│   ├── SmtpTransport.php         — SMTP transport implementation
│   ├── SmtpSettingsSection.php   — SettingsRegistry section (optional, inline in hooks.php)
│   └── SmtpMailerController.php  — Test-email endpoint (optional)
└── views/
    └── settings/
        └── smtp-section.php      — SMTP settings form (if not inline)
```

### plugin.json

```json
{
    "name": "smtp",
    "version": "0.1.0",
    "description": "SMTP mail transport for Kernel-Web — replaces default mail() transport for reliable delivery on macOS/MAMP and production.",
    "enabled": false,
    "requires": { "php": "8.1" },
    "permissions": ["settings.smtp"],
    "settings": "src/SmtpSettingsSection.php",
    "hooks": "hooks.php"
}
```

### Settings Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `smtp.host` | string | `localhost` | SMTP server hostname |
| `smtp.port` | int | `587` | SMTP port (587=STARTTLS, 465=SSL, 25=unencrypted) |
| `smtp.user` | string | `""` | Authentication username (empty = no auth) |
| `smtp.pass` | string | `""` | Authentication password (masked in UI) |
| `smtp.encryption` | string | `tls` | Connection encryption: `tls`, `ssl`, or `none` |
| `smtp.from_address` | string | (inherited from config) | Override sender address |
| `smtp.from_name` | string | (inherited from config) | Override sender name |
| `smtp.verify_peer` | bool | `true` | Verify SSL/TLS peer certificate |

---

## SmtpTransport Implementation

```php
namespace Plugins\Smtp;

use App\Core\Mail\MailerException;
use App\Core\Mail\MailMessage;
use App\Core\Mail\TransportInterface;
use App\Services\SystemSettingService;

readonly class SmtpTransport implements TransportInterface
{
    public function __construct(
        private SystemSettingService $settings,
    ) {}

    public function send(MailMessage $message): bool
    {
        $host       = $this->getSetting('smtp.host') ?: 'localhost';
        $port       = (int) ($this->getSetting('smtp.port') ?: 587);
        $user       = $this->getSetting('smtp.user') ?: '';
        $pass       = $this->getSetting('smtp.pass') ?: '';
        $encryption = $this->getSetting('smtp.encryption') ?: 'tls';
        $verifyPeer = (bool) ($this->getSetting('smtp.verify_peer') ?? true);

        // Build stream context based on encryption mode.
        $wrapper = match ($encryption) {
            'ssl'   => 'ssl://',
            'tls'   => 'tls://',
            default => '',
        };

        $dsn = sprintf(
            'smtp%s%s:%d',
            $wrapper,
            $host,
            $port
        );

        // Use PHPMailer or SwiftMailer for proper SMTP handling.
        // These are composer dependencies managed by the plugin.
        // Implementation placeholder — actual transport wiring happens here.
        //
        // For the initial slice, use PHP's stream_socket_client for raw SMTP
        // (no composer dependency). Full SMTP command exchange with AUTH LOGIN/PLAIN.

        $smtp = new RawSmtpClient($dsn, [
            'username' => $user,
            'password' => $pass,
            'verify_peer' => $verifyPeer,
        ]);

        try {
            $smtp->connect();
            $smtp->helo();
            $smtp->auth();
            $smtp->mailFrom($message->from);
            $smtp->rcptTo($message->to);
            $smtp->data($message->buildRaw());
            $smtp->quit();
        } catch (\RuntimeException $e) {
            throw new MailerException(
                "SMTP send failed: {$e->getMessage()}",
                code: 500,
                transportName: 'smtp',
            );
        }

        return true;
    }

    public function identifier(): string
    {
        return 'smtp';
    }

    private function getSetting(string $key): ?string
    {
        return $this->settings->get($key);
    }
}
```

### Raw SMTP Client (No Composer Dependency)

For the first slice, avoid composer dependencies by implementing raw SMTP via PHP streams:

```php
/**
 * Minimal raw SMTP client — no external dependencies.
 * Supports AUTH LOGIN and AUTH PLAIN authentication.
 */
class RawSmtpClient
{
    private int $stream;
    private string $username;
    private string $password;
    private bool $verifyPeer;

    public function __construct(string $dsn, array $options) { ... }
    public function connect(): void { /* stream_socket_client */ }
    public function helo(): void { /* EHLO/HELO exchange */ }
    public function auth(): void { /* AUTH LOGIN or AUTH PLAIN */ }
    public function mailFrom(string $address): void { /* MAIL FROM */ }
    public function rcptTo(string $address): void { /* RCPT TO */ }
    public function data(string $data): void { /* DATA command */ }
    public function quit(): void { /* QUIT command */ }
}
```

This keeps the plugin zero-composer. If later PHPMailer is acceptable, swap the raw client for PHPMailer.

---

## SettingsRegistry Integration

```php
// lib/plugins/smtp/src/SmtpSettingsSection.php
use App\Core\SettingsRegistry;
use App\Core\SettingsSection;

SettingsRegistry::addSection([
    'id'       => 'smtp',
    'label'    => 'SMTP Settings',
    'column'   => 'right',
    'order'    => 30,  // after Application, before Notifications placeholder
    'keys'     => [
        'smtp.host', 'smtp.port', 'smtp.user',
        'smtp.pass', 'smtp.encryption', 'smtp.verify_peer',
    ],
    'permission' => 'settings.smtp',
    'render'   => function (array $ctx): string {
        extract($ctx);
        ob_start();
        ?>
        <div class="card">
            <div class="card-header fw-semibold">SMTP Settings</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small">Host</label>
                    <?= form_text_field('smtp_host', $settings['smtp.host'] ?? 'localhost') ?>
                    <div class="form-text">MailHog (dev): localhost:1025, Mailtrap: smtp.mailtrap.io:2525</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Port</label>
                    <?= form_text_field('smtp_port', $settings['smtp.port'] ?? '587') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Encryption</label>
                    <select class="form-select form-select-sm" name="smtp_encryption">
                        <option value="tls" <?= ($settings['smtp.encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (recommended)</option>
                        <option value="ssl" <?= ($settings['smtp.encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($settings['smtp.encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Username</label>
                    <?= form_text_field('smtp_user', $settings['smtp.user'] ?? '') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Password</label>
                    <input type="password" class="form-control form-control-sm" name="smtp_pass"
                           id="smtp_pass" autocomplete="new-password">
                    <div class="form-text">Leave blank to keep current password.</div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="smtp_verify_peer"
                               id="smtp_verify_peer" <?= ($settings['smtp.verify_peer'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="smtp_verify_peer">
                            Verify SSL/TLS peer certificate
                        </label>
                    </div>
                </div>
                <!-- Test email button -->
                <div class="mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="smtp-test-btn">
                        Send Test Email
                    </button>
                    <span id="smtp-test-status" class="small ms-2" style="display:none;"></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    },
    'validate' => function (array $input): array {
        $errors = [];
        if (!empty($input['smtp.host']) && filter_var($input['smtp.host'], FILTER_VALIDATE_IP) === false
            && filter_var('http://' . $input['smtp.host'], FILTER_VALIDATE_URL) === false
            && !preg_match('/^[a-z0-9][-a-z0-9.]*[a-z0-9]$/i', $input['smtp.host'])) {
            $errors['smtp.host'] = 'Invalid hostname.';
        }
        if (!empty($input['smtp.port'])) {
            $port = (int) $input['smtp.port'];
            if ($port < 1 || $port > 65535) {
                $errors['smtp.port'] = 'Port must be 1-65535.';
            }
        }
        return $errors;
    },
    'save' => function (array $input, SystemSettingService $svc): void {
        foreach (['host', 'port', 'user', 'encryption', 'verify_peer'] as $key) {
            $fullKey = "smtp.{$key}";
            if (!empty($input[$fullKey]) || $fullKey === 'smtp.user' || $fullKey === 'smtp.encryption') {
                $svc->set($fullKey, $input[$fullKey]);
            }
        }
        // Only update password if a new value was provided.
        if (!empty($input['smtp.pass'])) {
            $svc->set('smtp.pass', $input['smtp.pass']);
        }
    },
    'source' => 'smtp',
]);
```

---

## Transport Override

The plugin overrides the core's default `mail()` transport via the kernel's service container or transport hook:

```php
// lib/plugins/smtp/hooks.php
use App\Core\Container;
use App\Core\Mail\TransportInterface;
use Plugins\Smtp\SmtpTransport;

// Wire SMTP transport into the container, replacing the default.
if (class_exists(Plugins\Smtp\SmtpTransport::class)) {
    Container::set('mailer.transport', new SmtpTransport(
        Container::get('settings')
    ));
}
```

Alternatively, use the existing `mailer.transport` hook point if one exists. The kernel's mailer initialization should check if a plugin has registered a transport override.

---

## Test-Email Endpoint

```php
// lib/plugins/smtp/src/SmtpMailerController.php
class SmtpMailerController extends Controller
{
    public function testEmail(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user = $principal['user'];

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        // Permission gate
        if (!in_array('settings.smtp', $principal['permissions'] ?? [])) {
            $this->json(['error' => 'Permission denied'], 403);
            return;
        }

        $to = $user['email'];
        $message = (new MailMessage(
            from: config('mail.from_address'),
            fromName: config('mail.from_name'),
            to: $to,
            toName: $user['display_name'] ?? 'User',
            subject: 'Kernel-Web SMTP Test',
            bodyHtml: '<p>This is a test email from Kernel-Web. If you received this, SMTP is configured correctly.</p>',
        ));

        try {
            $mailer = $this->container->get('mailer');
            $mailer->send($message);
            $this->json([
                'success' => true,
                'message' => 'Test email sent to ' . $to,
            ]);
        } catch (MailerException $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'transport' => $e->transportName ?? 'unknown',
            ], 500);
        }
    }
}
```

Route: `POST /api/smtp/test-email` with `['WebAuth', 'WebPermission:settings.smtp']`.

---

## Security Notes

### SMTP Passwords

- **Never displayed in the UI** — always `<input type="password">` with no `value` attribute.
- **Only updated when explicitly provided** — blank password field means "keep existing."
- **Stored in `system_settings` table** — same encryption as other sensitive settings.
- **Not included in `getAll()` responses** — excluded from admin settings list views.

### SSL/TLS Verification

- **`smtp.verify_peer` defaults to `true`** — prevents MITM attacks on SMTP connections.
- **Should not be disabled in production** — documented as a developer-only shortcut for self-signed certs.

### Input Validation

- **Host**: validated as hostname or IP (not URL to prevent protocol injection).
- **Port**: validated as integer 1-65535.
- **Username/password**: no length limit (some providers use long tokens), but capped at 512 chars to prevent DoS.

### Credentials in Plugin Manifest

- Credentials are **never** stored in `plugin.json` or version control.
- Configuration is runtime-only via `SettingsRegistry` + `system_settings` table.
- The plugin manifest's `"enabled": false` means SMTP is opt-in.

---

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Connection refused | `MailerException` with host/port in message |
| Auth failure | `MailerException` with 401 code |
| TLS handshake failure | `MailerException` with peer verification details |
| Timeout | `MailerException` with timeout info |
| Invalid recipient | Let SMTP server return the error (4xx/5xx codes) |
| Large attachment rejection | `MailerException` with size info |

All errors thrown as `MailerException` so callers can catch consistently. No untyped exceptions escape the transport.

---

## Implementation Priorities

1. `SmtpTransport` + raw SMTP client (no composer)
2. SettingsRegistry section with validation
3. Transport override in hooks.php
4. Test-email endpoint
5. Composer dependency option (PHPMailer swap)
6. Attachment support via SMTP

---

## Open Questions

| ID | Question | Decision |
|----|----------|----------|
| SP-1 | Raw SMTP vs PHPMailer? | Start raw (zero composer). PHPMailer swap later. |
| SP-2 | Should the plugin auto-detect if mail() works? | No — explicit opt-in. Admin configures SMTP and enables plugin. |
| SP-3 | How to handle attachments in raw SMTP? | MIME multipart encoding. More complex but possible without composer. |
| SP-4 | Should test-email allow a custom recipient? | Yes — add a modal input for custom email address. Default: logged-in user's email. |
| SP-5 | Should the plugin support multiple SMTP servers? | No — single SMTP server per installation is sufficient. Multi-server is a future enhancement. |
