# SMTP Mail Plugin

> **Status:** Implemented (first pass)
> **Roadmap:** Phase 2
> **Related:** mailer.md, settings-hooks.md

---

## Purpose

Replace PHP's built-in `mail()` transport with a reliable SMTP transport for local development (macOS/MAMP lacks an MTA) and production use.

---

## Plugin Structure

```
lib/plugins/smtp/
├── plugin.json
├── src/
│   ├── SmtpTransport.php       — SMTP transport implementing TransportInterface
│   ├── SmtpClient.php          — Raw SMTP protocol client (zero dependencies)
│   ├── SmtpSettings.php        — SettingsRegistry section registration
│   ├── SmtpHooks.php           — Bootstrap hook (transport swap + settings registration)
│   └── SmtpMailerController.php — Test-email endpoint
└── tests/
    └── smtp_test.php            — Unit tests (validation, config, protocol)
```

### plugin.json

```json
{
    "name": "smtp",
    "version": "0.1.0",
    "description": "SMTP mail transport — replaces default mail() transport for reliable delivery on macOS/MAMP and production.",
    "enabled": false,
    "requires": { "kernel": "8.1" },
    "permissions": ["settings.smtp"],
    "plugin_hooks": {
        "plugins.bootstrap": {
            "priority": 50,
            "callback": "Plugins\\Smtp\\SmtpHooks::bootstrap"
        }
    },
    "routes": [
        ["POST", "/api/smtp/test-email", "Plugins\\Smtp\\SmtpMailerController@testEmail"]
    ]
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
| `smtp.from_address` | string | (config default) | Override sender address |
| `smtp.from_name` | string | (config default) | Override sender name |
| `smtp.verify_peer` | bool | `true` | Verify SSL/TLS peer certificate |

---

## SmtpTransport

Implements `TransportInterface` using PHP streams for the SMTP protocol. Zero Composer dependency.

```php
namespace Plugins\Smtp;

class SmtpTransport implements TransportInterface
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private bool $verifyPeer;
    private string $fromAddress;
    private string $fromName;

    public function __construct(array $config = []);
    public function send(MailMessage $message): bool;
    public function identifier(): string; // 'smtp'
}
```

Configuration is passed via a plain array. In production, the bootstrap hook builds the config from `SystemSettingService`.

---

## SmtpClient (Raw SMTP)

Zero-dependency SMTP protocol client. Implements the full SMTP command exchange:

- **readGreeting()** — reads 220 response
- **ehlo()** — sends EHLO command
- **startTls()** — initiates STARTTLS (if server supports it)
- **auth(username, password)** — tries AUTH PLAIN first, AUTH LOGIN fallback
- **mailFrom()**, **rcptTo()**, **data()**, **quit()** — SMTP commands

Built on `stream_socket_client` with a 15-second timeout. Multi-line responses are joined on the continuation marker (`-` suffix).

### Authentication Flow

```
1. Client → SERVER: EHLO localhost
2. SERVER → Client: 250-AUTH LOGIN PLAIN ...
3. Client → SERVER: AUTH PLAIN AG<base64("\0user\0pass")>
4. SERVER → Client: 235 Authentication successful
   (or: try AUTH LOGIN)
5. Client → SERVER: AUTH LOGIN
6. SERVER → Client: 334 VXNlcm5hbWU6  (VXNlcm5hbWU6 = "Username:")
7. Client → SERVER: <base64(username)>
8. SERVER → Client: 334 UGFzc3dvcmQ6  (UGFzc3dvcmQ6 = "Password:")
9. Client → SERVER: <base64(password)>
10. SERVER → Client: 235 Authentication successful
```

### Connection Modes

| Encryption | DSN Prefix | Typical Port |
|------------|-----------|--------------|
| TLS (STARTTLS) | `tls://` | 587 |
| SSL | `ssl://` | 465 |
| None | (none) | 25 |

---

## SmtpHooks (Bootstrap)

Registered via `plugin_hooks` in plugin.json. The `plugins.bootstrap` hook runs after plugin loading and before routing:

1. **Register SMTP settings** with `SettingsRegistry::addSection()`
2. **Swap transport** — if `smtp.host` is configured (non-default), create `SmtpTransport` and set it on the existing `Mailer` instance

The transport swap only happens when SMTP is actually configured (host != 'localhost'), ensuring backward compatibility with installations that don't use SMTP.

---

## Test-Email Endpoint

`POST /api/smtp/test-email` with session authentication.

- Requires `settings.smtp` permission
- Sends a test email to the logged-in user's email address (or the `to` POST parameter)
- Uses the current SMTP transport configuration
- Returns JSON: `{"success": true, "message": "Test email sent to user@example.com"}` or `{"success": false, "error": "..."}`

The SMTP settings form includes a "Send Test Email" button that triggers this endpoint via AJAX.

---

## Security Notes

### SMTP Passwords

- **Never displayed in the UI** — password field uses `<input type="password">` with no `value` attribute.
- **Only updated when explicitly provided** — blank password field means "keep existing."
- **Stored in `system_settings` table** — same encryption as other sensitive settings.

### SSL/TLS Verification

- **`smtp.verify_peer` defaults to `true`** — prevents MITM attacks.
- **Should not be disabled in production** — documented as a developer-only shortcut for self-signed certs.

### Input Validation

- **Host**: validated as hostname or IP via `filter_var(FILTER_VALIDATE_IP)` and regex.
- **Port**: validated as integer 1-65535.

---

## Error Handling

| Scenario | Behavior |
|------|------|
| Connection refused | `MailerException` with host/port in message |
| Auth failure | `MailerException` with auth error details |
| TLS handshake failure | `MailerException` with peer verification details |
| Timeout | `MailerException` with timeout info |
| Invalid recipient | SMTP server's error code in `MailerException` |

---

## Tests

`tests/smtp_test.php` — 23 assertions:

- **SmtpSettings validation** — valid hosts/ports, invalid hostnames, boundary ports, edge cases
- **SmtpTransport config** — constructor defaults, identifier
- **TransportInterface contract** — interface implementation verification
- **SmtpClient protocol** — greeting reading, command parsing

No real network calls. Tests use in-memory strings and direct object construction.

---

## Enabling the Plugin

1. Navigate to **Admin → Extensions**
2. Find the SMTP plugin
3. Click **Enable**
4. Navigate to **Admin → Settings → SMTP Settings**
5. Configure host, port, encryption, credentials
6. Click **Save**
7. Test with the "Send Test Email" button

The transport swap is automatic — no restart required.

---

## Open Questions

| ID | Question | Decision |
|----|------|-----|
| SP-1 | Raw SMTP vs PHPMailer? | Implemented raw (zero composer). PHPMailer swap available later. |
| SP-4 | Should test-email allow a custom recipient? | Implemented — accepts `to` POST parameter, falls back to user email. |
