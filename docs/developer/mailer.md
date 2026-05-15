# Mailer Foundation Design

> **Status:** Core implemented (Phase 2) — SMTP plugin implemented, queueing and core templates deferred
> **Roadmap:** Phase 2 — done
> **Related:** ARCH-1 (notes-triage.md), PHASE2-2

---

## Purpose

Define the mailer foundation for Kernel-Web: a pluggable transport system with a default `mail()` transport, template support, attachment support, and settings integration via the existing `SettingsRegistry` / `SettingsSection` system.

---

## Design Decisions

### PHP Version

PHP 8.1+ only. Modern features: readonly properties, union types, constructor property promotion, native `str_contains`.

### Core vs Plugin Boundary

| Concern | Location | Rationale |
|---------|----------|-----------|
| Mailer service interface | Core | Core abstraction all callers depend on |
| `mail()` transport | Core | Default, zero-dependency |
| SMTP transport | **Plugin** | Not included in core |
| Template loading | Core | Pluggable, transport-agnostic |
| Attachments | Core | Transport-agnostic value object |
| Error handling | Core | Consistent exception types |
| SMTP settings | **Plugin** | Via SettingsRegistry / SettingsSection |
| Email types (forgot password, verification) | **Plugin** or app layer | Core is agnostic to email purpose |

### Transport Pattern

The transport is the sending mechanism. Core defines the interface; any implementation can be wired into the container at boot.

```
┌─────────────┐       ┌──────────────┐
│  Mailer     │──────▶│  Transport   │
│  (core)     │       │  (interface) │
└─────────────┘       └──────┬───────┘
                              │
              ┌───────────────┼───────────────┐
              │               │               │
       ┌──────▼──────┐  ┌────▼─────┐  ┌──────▼──────┐
       │ Mail()      │  │ SMTP     │  │ Custom      │
       │ Transport   │  │ Transport│  │ Transport   │
       │ (core)      │  │ (plugin) │  │ (plugin)    │
       └─────────────┘  └──────────┘  └─────────────┘
```

### Template Strategy

Templates use a registry with filesystem fallback:

1. Core ships default templates at `app/Views/emails/`
2. Plugins can register template paths via `TemplateRegistry`
3. `find($name)` searches registered paths in order
4. Template files are plain PHP (`.php`) — no template engine dependency

This mirrors the existing layout/theme system: registry-driven path resolution with `.php` view files.

### Attachment Strategy

Attachments are a value object in `MailMessage`. The transport implementation decides how to serialize them (PHP's built-in MIME multipart for `mail()`, PHPMailer's `AddAttachment` for SMTP, etc.).

### Error Strategy

- All send failures throw `MailerException` (extends `\RuntimeException`)
- Callers may catch `MailerException` for graceful fallback
- Transport implementations log errors via `error_log()` or a PSR-3 logger if available
- No silent failures

### Queueing

Deferred. The design leaves room for a `QueuedTransport` wrapper but does not implement queueing.

### Settings Integration

SMTP plugin uses `SettingsRegistry::addSection()` (already designed at `docs/developer/settings-hooks.md`) to register its configuration fields. Core's `mail()` transport requires no settings.

---

## Architecture

```
app/
├── Core/
│   ├── Mailer.php            — facade service
│   ├── MailMessage.php       — value object (message content)
│   ├── Transport.php         — interface
│   ├── MailTransport.php     — default mail() implementation
│   ├── TemplateRegistry.php  — template path registry
│   └── MailerException.php   — error type
├── Views/emails/             — core default templates
└── Controllers/              — uses Mailer via interface
```

Plugin:
```
lib/plugins/{name}/
├── plugin.json
├── src/
│   └── SmtpTransport.php   — SMTP transport implementation
├── views/
│   └── settings/
│       └── smtp.php        — SMTP settings form
└── hooks.php               — registers transport + settings
```

---

## Core Interfaces & Classes

### MailMessage Value Object

```php
namespace App\Core;

readonly class MailMessage
{
    public function __construct(
        public string  $from,
        public string  $fromName,
        public string  $to,
        public string  $toName,
        public string  $subject,
        public string  $bodyHtml,
        public string  $bodyText,
        public array   $attachments = [],  // Attachment[]
        public string  $template = '',     // template name (optional)
        public array   $context = [],      // template context vars
    ) {}

    public function withAttachment(Attachment $attachment): static
    {
        $clone = clone $this;
        $clone->attachments[] = $attachment;
        return $clone;
    }

    public function withTemplate(string $name, array $context = []): static
    {
        $clone = clone $this;
        $clone->template = $name;
        $clone->context = $context;
        return $clone;
    }
}
```

### Attachment Value Object

```php
namespace App\Core;

readonly class Attachment
{
    public function __construct(
        public string $path,     // filesystem path to file
        public string $name,     // filename in the email
        public string $mimeType = '', // empty = auto-detect from extension
        public bool   $inline = false, // inline vs. attachment
    ) {}
}
```

### Transport Interface

```php
namespace App\Core;

interface Transport
{
    /**
     * Send a mail message.
     *
     * Returns true on success, throws MailerException on failure.
     */
    public function send(MailMessage $message): bool;

    /**
     * Return the transport identifier (e.g. 'mail', 'smtp').
     */
    public function identifier(): string;
}
```

### Mailer Service (Facade)

```php
namespace App\Core;

class Mailer
{
    public function __construct(
        private Transport $transport,
        private TemplateRegistry|null $templateRegistry = null,
    ) {}

    /**
     * Send a composed message.
     * Resolves templates if $message->template is set.
     */
    public function send(MailMessage $message): bool
    {
        if ($message->template !== '' && $this->templateRegistry !== null) {
            $html = $this->templateRegistry->find($message->template, $message->context);
            // Merge resolved HTML into the message body.
            // The resolved template replaces the body_html field.
            $bodyHtml = $message->bodyHtml;
            // Template resolution replaces bodyHtml for sending.
            // Implementation detail: pass template result to transport.
        }

        return $this->transport->send($message);
    }

    /**
     * Convenience: get the current transport identifier.
     */
    public function transportIdentifier(): string
    {
        return $this->transport->identifier();
    }

    /**
     * Swap the transport at runtime.
     */
    public function setTransport(Transport $transport): void
    {
        $this->transport = $transport;
    }
}
```

### TemplateRegistry

```php
namespace App\Core;

class TemplateRegistry
{
    private static array $paths = [];       // plugin slug → template dir path
    private static array $corePaths = [];   // core template names → file paths

    /**
     * Register a plugin's template directory.
     * Templates in this directory are addressed by filename relative to the dir.
     */
    public static function addPath(string $slug, string $path): void;

    /**
     * Register a core template (name → file path).
     */
    public static function addCoreTemplate(string $name, string $path): void;

    /**
     * Find a template by name. Searches core paths first, then plugin paths.
     *
     * @return string  — the resolved file path
     * @throws \RuntimeException if not found
     */
    public static function find(string $name): string;

    /**
     * Render a template with context.
     *
     * @return string  — rendered HTML
     */
    public static function render(string $name, array $context = []): string;

    /**
     * Clear all registered templates (for testing).
     */
    public static function clear(): void;
}
```

### Default mail() Transport

```php
namespace App\Core;

class MailTransport implements Transport
{
    public function __construct(
        private string $fromAddress = 'noreply@localhost',
        private string $fromName = 'Kernel-Web',
    ) {}

    public function send(MailMessage $message): bool
    {
        $headers = [
            'From: ' . $this->fromAddress,
            'Reply-To: ' . $this->fromAddress,
            'X-Mailer: Kernel-Web',
        ];

        // If attachments present, use a simple MIME wrapper.
        // For now: mail() without attachments only.
        // SMTP transport handles attachments via PHPMailer or similar.
        if (!empty($message->attachments)) {
            throw new MailerException('Attachments not supported by mail() transport. Use SMTP transport.');
        }

        return mail(
            $message->to,
            $message->subject,
            $message->bodyHtml,
            implode("\r\n", $headers)
        );
    }

    public function identifier(): string
    {
        return 'mail';
    }
}
```

### MailerException

```php
namespace App\Core;

class MailerException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $code = 0,
        public readonly ?string $transportName = null,
    ) {
        parent::__construct($message);
    }
}
```

---

## Plugin SMTP Transport Example

```php
// lib/plugins/smtp/src/SmtpTransport.php
namespace Plugins\Smtp;

use App\Core\MailMessage;
use App\Core\Transport;
use App\Core\MailerException;
use App\Services\SystemSettingService;

readonly class SmtpTransport implements Transport
{
    public function __construct(
        private SystemSettingService $settings,
    ) {}

    public function send(MailMessage $message): bool
    {
        $host = $this->settings->get('smtp.host') ?: 'localhost';
        $port = (int) ($this->settings->get('smtp.port') ?: 587);
        $user = $this->settings->get('smtp.user') ?: '';
        $pass = $this->settings->get('smtp.pass') ?: '';
        $encryption = $this->settings->get('smtp.encryption') ?: 'tls';

        // Use PHPMailer or similar to send.
        // Implementation omitted — plugin code.
    }

    public function identifier(): string
    {
        return 'smtp';
    }
}
```

```php
// lib/plugins/smtp/plugin.json
{
    "name": "smtp",
    "version": "0.1.0",
    "description": "SMTP mail transport for Kernel-Web",
    "enabled": true,
    "requires": { "php": "8.1" },
    "dependencies": { "plugin:settings-hooks": ">=0.1.0" },
    "services": {
        "mailer.transport.smtp": {
            "class": "Plugins\\Smtp\\SmtpTransport",
            "singleton": true,
            "args": ["App\\Services\\SystemSettingService"]
        }
    },
    "settings": "src/SettingsRegistrar.php",
    "hooks": ["mailer.transport"]
}
```

```php
// lib/plugins/smtp/src/SettingsRegistrar.php
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
    'render'   => fn(array $ctx) => require __DIR__ . '/../views/settings/smtp.php',
    'validate' => fn(array $input) => [],
    'save'     => fn(array $input, SystemSettingService $svc) => null,
    'source'   => 'smtp',
]);
```

```php
// lib/plugins/smtp/hooks.php
<?php

use App\Core\Container;
use App\Core\Transport;

// Register SMTP transport override in the container.
// The container's 'mailer.transport' binding is replaced by the SMTP transport.
Container::set('mailer.transport', new SmtpTransport(/* ... */));
```

---

## Boot Wiring

At boot, after config is loaded:

1. **Create default MailMessage** — core config (`app.name`, `mail.from_address`, `mail.from_name`)
2. **Create MailTransport** as the default transport
3. **Register default templates** via TemplateRegistry
4. **Bind in container** — `Container::set('mailer', $mailer)` and `Container::set('mailer.transport', $transport)`
5. **Plugins may override** — a plugin's `hooks.php` or `services` block replaces `mailer.transport`
6. **Mailer swaps itself** — if the SMTP plugin is enabled, it overwrites the container binding; or the `mailer.transport` hook fires and core listens

### Config

```php
// config/mail.php
return [
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost',
    'from_name'    => getenv('MAIL_FROM_NAME')    ?: 'Kernel-Web',
];
```

---

## Email Templates

### Core Default Templates

Core ships two base templates in `app/Views/emails/`:

```
app/Views/emails/
├── layout.html.php   — HTML email layout wrapper (doctype, head, body, footer)
├── welcome.html.php  — welcome email (first slice)
└── notification.html.php — generic notification email
```

Template files are plain PHP with `$vars` available:

```php
<!-- app/Views/emails/layout.html.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .footer { font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <?= $body ?>
    </div>
    <div class="footer">
        Sent by <?= htmlspecialchars($appName ?? 'Kernel-Web') ?>
    </div>
</body>
</html>
```

```php
<!-- app/Views/emails/welcome.html.php -->
<?php require __DIR__ . '/layout.html.php'; ?>

<?php
// Override $body in the template scope.
$body = <<<HTML
<h2>Welcome, <?= htmlspecialchars($userName ?? 'User') ?></h2>
<p>Thank you for signing up.</p>
HTML;
?>

<?= $body ?>
```

Template resolution order:
1. Core default templates (`app/Views/emails/`)
2. Plugin template directories (registered via `TemplateRegistry::addPath()`)

### Plugin Template Registration

```php
// In plugin's bootstrap/hooks.php
TemplateRegistry::addPath('notifications', __DIR__ . '/views/emails');
```

Callers use template names:
```php
$mailer->send((new MailMessage(...))
    ->withTemplate('welcome', ['userName' => 'John']));
```

---

## Auth Feature Integration (Future)

### Forgot Password Flow

```
1. User submits email at /auth/forgot-password
2. App generates a one-time reset token
3. App calls: $mailer->send((new MailMessage(...))->withTemplate('password-reset', ['resetUrl' => $url]))
4. Token stored in DB (auth feature, not mailer)
```

### Email Verification Flow

```
1. User submits registration form (auth feature)
2. App generates a verification token
3. App calls: $mailer->send((new MailMessage(...))->withTemplate('email-verify', ['verifyUrl' => $url]))
4. Token validated at /auth/verify (auth feature, not mailer)
```

### Design Note

The mailer is **purpose-agnostic**. It knows nothing about "forgot password" or "email verification." Those are auth-layer concerns that compose MailMessage with templates. The mailer simply delivers.

---

## Notification Integration (Future)

Notifications compose MailMessage the same way:

```php
// In NotificationChannel service
public function send(string $userId, string $template, array $context): void
{
    $user = $this->userRepo->findById($userId);
    $this->mailer->send((new MailMessage(
        from: config('mail.from_address'),
        fromName: config('mail.from_name'),
        to: $user['email'],
        toName: $user['display_name'],
        subject: $context['subject'],
        bodyHtml: '',
        bodyText: '',
    ))->withTemplate($template, $context));
}
```

---

## Error Handling

```php
try {
    $mailer->send($message);
} catch (MailerException $e) {
    // Log the error (implementation-specific)
    error_log("Mailer failed: {$e->getMessage()} [{$e->transportName}]");

    // Graceful fallback — show user-friendly message
    // Don't expose transport details to the user
}
```

Transport implementations should always throw `MailerException` (never untyped exceptions) so callers can catch consistently.

---

## Queuing (Deferred)

The design supports future queuing via a `QueuedTransport` decorator:

```php
$wrapped = new QueuedTransport($mailer->transport());
$mailer->setTransport($wrapped);
```

`QueuedTransport` would:
- Accept `send()` calls
- Store messages in a DB table or file queue
- Provide a CLI command to process the queue
- Retry failed sends

Not implemented. The `Transport` interface contract is sufficient to add it later without interface changes.

---

## Plugin Extensibility

### Custom Transport

```php
class CustomTransport implements Transport
{
    public function send(MailMessage $message): bool { /* ... */ }
    public function identifier(): string { return 'custom'; }
}
```

Register in plugin's `hooks.php` or `services` block. Overwrites the container binding.

### Custom Templates

```php
TemplateRegistry::addPath('myplugin', __DIR__ . '/views/emails');
```

### Custom Default Settings

Plugin can set `mail.from_address` / `mail.from_name` via the settings system. Core reads from `config/mail.php` as fallback.

### Mail Transport Hook

A single hook point for transport switching:

```
mailer.transport  — fired after transport is resolved.
                   Listeners can replace the transport.
```

```php
// Plugin can hook into this:
HookRegistry::register('mailer.transport', function(Container $c): Transport {
    // Return custom transport if available
    return new PluginTransport();
});
```

---

## Implementation (done — commit fd93d4c)

1. **Core files (7 files)**:
   - `app/Core/Mailer.php` — facade service
   - `app/Core/Mail/MailMessage.php` — value object (clone pattern)
   - `app/Core/Mail/Attachment.php` — readonly value object with `file()` factory
   - `app/Core/Mail/TransportInterface.php` — interface
   - `app/Core/Mail/MailTransport.php` — default `mail()` transport
   - `app/Core/Mail/MailerException.php` — error type
   - `app/Core/Mail/TemplateRegistry.php` — template path registry

2. **Config**: `config/mail.php` (from_address, from_name)

3. **Test suite (1 file)**: `tests/mailer_test.php` (61 assertions → 63 after security fixes)

**Not implemented**: queueing, core email templates (`app/Views/emails/`), container wiring (deferred to auth features phase). SMTP plugin implemented — see `docs/developer/smtp-plugin.md`.

---

## macOS / MAMP Considerations

PHP's `mail()` function requires a local MTA (Sendmail, Postfix, etc.) to function. macOS and MAMP **do not ship with a configured MTA**, so `mail()` will always fail or silently drop messages.

**For local development on macOS/MAMP:**

1. Install an SMTP plugin (see `docs/developer/smtp-plugin.md`)
2. Configure it to use a local mail catcher (MailHog, Mailtrap, etc.)
3. Enable the plugin — it replaces `MailTransport` at boot

**For production:**

1. Use SMTP with a real mail service (Amazon SES, SendGrid, Mailgun, etc.)
2. The SMTP plugin supports SSL/TLS with peer verification
3. Never disable `smtp.verify_peer` in production

---

## Open Questions

| ID | Question | Decision |
|----|----------|----------|
| ML-1 | Should MailMessage be immutable (readonly) or mutable (clone)? | Mutable clone pattern (like PHPMailer). Allows chaining without breaking BC. |
| ML-2 | Should core templates ship as `.html.php` or `.php`? | `.html.php` for clarity — signals HTML email template, not a page view. |
| ML-3 | Should the default transport use `mail()` or a minimal HTTP API? | `mail()` — zero dependencies, works out of the box on any PHP host. SMTP plugin for advanced use. |
| ML-4 | Should TemplateRegistry resolve templates by name (e.g. `welcome`) or by full key (e.g. `plugin:welcome`)? | Name-based with registry prefix resolution. Core templates searched first, then plugin paths. |
| ML-5 | Should attachments be supported by the mail() transport? | Not in the first slice. mail() transport throws if attachments requested. SMTP plugin handles attachments via PHPMailer or similar. |
| ML-6 | How does the SMTP plugin discover config? | Via SystemSettingService (SettingsRegistry integration) — same pattern as other plugin settings. |
| ML-7 | Should the mailer handle charset / encoding? | Yes — hardcoded to `UTF-8` for body, `Content-Type: text/html; charset=UTF-8` in headers. Configurable charset deferred. |
| ML-8 | Should core provide a CLI command for testing mail? | Deferred. First slice is service + transport only. CLI can be added later. |

---

## Files Created (this task)

| File | Purpose |
|------|---------|
| `docs/developer/mailer.md` | This file — full mailer foundation design |

## Files Updated (this task)

| File | Change |
|------|--------|
| `DESIGN.md` | Add "Mailer Foundation" section linking to `docs/developer/mailer.md` |
| `ROADMAP.md` | Mark mailer foundation as "designed" in Current State table |
| `docs/developer/planning/notes-triage.md` | Mark ARCH-1 as designed |

---

## Implementation Priorities (future)

1. Core interface + default transport (6 core files) — **done**
2. Config + container wiring — **done**
3. TemplateRegistry + core templates — deferred
4. SMTP plugin — **done** (see smtp-plugin.md)
5. SMTP settings via SettingsRegistry — **done**
6. Auth feature integration (forgot password, verification) — later Phase 2
7. Queueing — deferred
