# Messenger Foundation Design

> **Status:** Core implemented (Message, MessengerTransportInterface, Messenger, MessengerException). Telico plugin implemented.
> **Roadmap:** Phase 2 — core done; Telico plugin implemented.
> **Related:** mailer.md, telico-plugin.md

---

## Purpose

Define a pluggable SMS/messaging transport system for Kernel-Web. Core provides the interface and message object; providers implement transports as plugins. This mirrors the mailer foundation's TransportInterface pattern.

---

## Why a Messenger Foundation

- SMS is needed for 2FA backup codes, login verification, and notifications
- Multiple providers (Telico, Twilio, others) have different APIs
- A common interface allows swapping providers without changing callers
- Template strategy keeps message content consistent across providers

---

## Architecture

```
┌──────────────── Kernel Core ──────────────┐
│                                            │
│  Messenger (service)                       │
│    ──▶ MessengerTransportInterface         │
│                  │                         │
│         ┌───────┴───────┐                  │
│         ▼               ▼                  │
│   TelicoTransport  TwilioTransport         │
│   (plugin)       (plugin)                  │
│                                            │
│  Message (value object)                    │
│  TemplateRegistry (optional)               │
└────────────────────────────────────────────┘

Plugin structure (per provider):
  lib/plugins/{provider}/
  ├── plugin.json
  ├── hooks.php           — registers transport
  ├── src/
  │   └── {Provider}Transport.php
  └── views/
      └── settings/
          └── settings.php
```

---

## Messenger Service

```php
namespace App\Core;

class Messenger
{
    // $transport is nullable — null means SMS disabled until a plugin swaps it.
    public function __construct(
        private ?MessengerTransportInterface $transport = null,
    ) {}

    public function send(Message $message): bool
    {
        if ($this->transport === null) {
            throw new MessengerException(
                'No SMS transport configured. Enable a transport plugin first.',
                code: 503,
                transportName: null,
            );
        }
        return $this->transport->send($message);
    }

    public function transportIdentifier(): ?string
    {
        return $this->transport?->identifier();
    }

    public function setTransport(MessengerTransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    public function hasTransport(): bool
    {
        return $this->transport !== null;
    }
}
```

### Usage (example: 2FA via SMS)

```php
$sms = new Message(
    to: $user['phone'] ?? $pendingPhone,
    from: config('sms.from_number'),
    body: "Your verification code: {$code}",
);

$code = $this->twoFactorService->generateCode();
$this->messenger->send($sms);
```

---

## Message Value Object

```php
namespace App\Core;

readonly class Message
{
    public function __construct(
        public string $to,             // recipient phone number (E.164 format)
        public string $from,           // sender number or shortcode
        public string $body,           // plain text body (SMS = 160 chars max)
        public string $template = '',   // template name (optional)
        public array  $context = [],    // template variables
        public array  $media = [],      // media URLs (MMS, deferred)
    ) {}

    public function withMedia(string $url, ?string $mimeType = null): static
    {
        // Returns a new Message (immutability) — PHP readonly classes can't modify cloned properties.
        $media = $this->media;
        $media[] = ['url' => $url, 'mime_type' => $mimeType];
        return new static(to: $this->to, from: $this->from, body: $this->body, template: $this->template, context: $this->context, media: $media);
    }

    public function withBody(string $body): static
    {
        return new static(to: $this->to, from: $this->from, body: $body, template: $this->template, context: $this->context, media: $this->media);
    }
}
```

### Phone Number Format

All phone numbers in **E.164 format**: `+1XXXXXXXXXX` (US) or `+[country code][number]`.

The Messenger layer normalizes input via a helper:

```php
class PhoneNumberHelper
{
    public static function normalize(string $number): string
    {
        // Strip spaces, dashes, parens
        $cleaned = preg_replace('/[()\s-]/', '', $number);
        // If no country code, assume +1 (US)
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+1' . ltrim($cleaned, '0');
        }
        return $cleaned;
    }
}
```

---

## MessengerTransportInterface

```php
namespace App\Core;

interface MessengerTransportInterface
{
    /**
     * Send a message.
     *
     * @throws MessengerException on failure
     */
    public function send(Message $message): bool;

    /**
     * Return the transport identifier (e.g. 'telico', 'twilio').
     */
    public function identifier(): string;
}
```

### MessengerException

```php
namespace App\Core;

class MessengerException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?string $transportName = null,
    ) {
        parent::__construct($message, $code);
    }
}
```

---

## TelicoTransport (Future Plugin)

See `docs/developer/telico-plugin.md` for the full Telico integration design.

The transport wraps the Telico SMS API:

```
POST https://sms.telico.cloud/api/send_sms
Authorization: Basic {base64(username:password)}
Body: {source_did, destination, message}
```

Key implementation notes from the existing Telico helper:

- **Authentication**: HTTP Basic auth with `username` + `sms_pass`
- **Source DID**: `source_did` from config (the caller ID / sender number)
- **Destination**: E.164 formatted phone number
- **Response**: JSON decode of the API response
- **Error handling**: Map API error codes to MessengerException codes

---

## TwilioTransport (Future Plugin)

Similar pattern to TelicoTransport but using Twilio's REST API:

```
POST https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json
Authorization: Basic {base64(sid:auth_token)}
Body: {From, To, Body}
```

Twilio-specific:
- Requires Account SID + Auth Token (not Basic auth credentials)
- Supports MMS, media URLs, and webhooks natively
- More expensive than Telico but broader coverage

---

## Template Strategy

Messages use the same TemplateRegistry pattern as email:

```php
// Core templates at app/Views/sms/
TemplateRegistry::addPath('core-sms', __DIR__ . '/../app/Views/sms/');

// Plugin templates
TemplateRegistry::addPath('telico', __DIR__ . '/views/sms/');

// Usage
$template = TemplateRegistry::render('verification-code', [
    'code' => $code,
    'expires' => 300,
]);
$message = new Message(to: $phone, from: $from, body: $template);
```

### Core SMS Templates

```
app/Views/sms/
├── verification-code.txt      — "Your code is {code}. Expires in {expires}s."
├── login-verify.txt            — "Your login code is {code}. Do not share this."
├── account-alert.txt           — "Account alert: {alert_message}"
└── two-factor-backup.txt       — "Your 2FA backup code: {code}"
```

Template files are plain `.txt` (SMS = text only). No HTML or rich content.

---

## Queueing (Deferred)

SMS queueing deferred to a later phase. The design supports it via a decorator:

```php
$wrapped = new QueuedMessengerTransport($messenger->transport());
$messenger->setTransport($wrapped);
```

`QueuedMessengerTransport` would:
- Accept `send()` calls and store them in a `sms_queue` table
- Provide a CLI command to process the queue in batches
- Retry failed sends with exponential backoff
- Track delivery receipts via webhook

Not implemented. The `MessengerTransportInterface` is sufficient for future queuing.

---

## Plugin: Telico

```
lib/plugins/telico/
├── plugin.json
├── hooks.php                 — registers transport + settings
├── src/
│   ├── TelicoTransport.php   — MessengerTransportInterface implementation
│   └── TelicoSettings.php    — SettingsRegistry section for SMS config
└── views/
    └── settings/
        └── telico.php        — Telico SMS settings form
```

### Settings Keys

| Key | Type | Default | Description |
|-----|------|-----|------|
| `telico.username` | string | `""` | Telico account username |
| `telico.sms_pass` | string | `""` | Telico SMS password (masked) |
| `telico.callerid` | string | `""` | Sender DID/number (E.164) |

---

## Error Handling

| Scenario | Behavior |
|------|--|--|
| Invalid phone number | `MessengerException` with validation message (before API call) |
| Auth failure | `MessengerException` with 401 code |
| Rate limiting | `MessengerException` with 429 code |
| Invalid sender (DID) | `MessengerException` with 400 code |
| Message too long (>160 chars) | `MessengerException` with truncation warning |
| Network timeout | `MessengerException` with timeout info |

---

## Provider Selection

The active SMS provider is selected via a setting:

```php
// config/sms.php (or SettingsRegistry)
return [
    'provider' => 'telico', // 'telico' | 'twilio' | null (disabled)
    'from_number' => '+1XXXXXXXXXX',
];
```

When `provider` is `null`, SMS is disabled — callers check before sending.

---

## Open Questions

| ID | Question | Decision |
|----|--|--|
| MS-1 | Should core include a default SMS provider? | No — core provides only the interface. Provider selection is a plugin. |
| MS-2 | Should phone number validation be in core or per-provider? | Core — `PhoneNumberHelper` normalizes to E.164. Provider-specific validation is per-provider. |
| MS-3 | Should long messages be auto-chunked? | No — throw `MessengerException` for messages >160 chars. Future: `LongMessage` class with chunking. |
| MS-4 | Should delivery receipts be handled? | Provider-specific webhooks. Core provides a `/api/sms/receipt` endpoint for webhooks. |
| MS-5 | Should the messenger support MMS? | Deferred. Core Message object has a `media` array; transport decides support. |
| MS-6 | Rate limiting — core or per-provider? | Per-provider. Core has no rate limit knowledge. |

---

## Implementation Priorities

1. `MessengerTransportInterface` + `Message` value object
2. `TelicoTransport` plugin (mimic existing Helper.php)
3. Settings section for Telico config
4. TemplateRegistry for SMS templates
5. 2FA integration (SMS backup codes)
6. Queueing (deferred)
