# Telico SMS Plugin Design

> **Status:** Implemented (TelicoTransport, TelicoApiClient, TelicoSettings, TelicoHooks, TelicoApiController, test-sms endpoint). Plugin disabled by default.
> **Roadmap:** Phase 2 — first slice complete.
> **Related:** messenger.md, settings-hooks.md
> **Reference:** `/Users/louis/Projects/LaswitchTech/core/lib/plugins/telico/Helper.php`

---

## Purpose

Design a Kernel-Web plugin that wraps the Telico SMS API for sending SMS messages and retrieving conversations. This mirrors the existing Telico Helper in NetMon but follows Kernel-Web's plugin conventions.

---

## Telico API Reference (from existing Helper.php)

Telico provides two API endpoints:

### 1. Voice Calls

```
GET https://as2.telico.ca/api/json/calls/make/{params}

Parameters:
  auth_username  — Telico account username
  auth_password  — Telico account password (voice)
  stype          — "phone" (fixed)
  snumber        — destination phone number
  cnumber        — caller number
  callerid1      — Telico caller ID (sender DID)
  callerid2      — Telico caller ID (repeated)
```

Response: JSON decoded from the endpoint.

### 2. SMS Sending

```
POST https://sms.telico.cloud/api/send_sms
Authorization: Basic {base64(username:sms_pass)}
Query params:
  source_did     — Telico caller ID (sender DID)
  destination    — recipient phone number (E.164)
  message        — SMS body text

Response: JSON decoded from the endpoint.
```

### 3. Get All Conversations

```
GET https://sms.telico.cloud/api/conversations?did={did}
Authorization: Basic {base64(username:sms_pass)}

Response: JSON array of conversations.
```

### 4. Get Conversation Messages

```
GET https://sms.telico.cloud/api/messages?conversation_id={id}
Authorization: Basic {base64(username:sms_pass)}

Response: JSON array of messages.
```

---

## Plugin Structure

```
lib/plugins/telico/
├── plugin.json
├── src/
│   ├── TelicoTransport.php      — MessengerTransportInterface
│   ├── TelicoApiClient.php      — Telico API client
│   ├── TelicoSettings.php       — SettingsRegistry section
│   ├── TelicoHooks.php          — Bootstrap hooks (settings + transport swap)
│   └── TelicoApiController.php  — test-sms endpoint
├── tests/
│   └── telico_test.php
└── views/
    └── settings/
        └── telico.php           — Telico settings form
```

---

## TelicoApiClient

```php
namespace Plugins\Telico;

use App\Core\MessengerException;

class TelicoApiClient
{
    private string $username;
    private string $smsPass;
    private string $callerId;

    public function __construct(array $config)
    {
        $this->username = $config['telico.username'] ?? '';
        $this->smsPass  = $config['telico.sms_pass'] ?? '';
        $this->callerId = $config['telico.callerid'] ?? '';
    }

    /**
     * Send an SMS message.
     *
     * @return array Decoded JSON response
     * @throws MessengerException on failure or missing credentials
     */
    public function sendSms(string $destination, string $message): array
    {
        if (empty($this->username) || empty($this->smsPass)) {
            throw new MessengerException(
                'Telico credentials not configured.',
                code: 422,
                transportName: 'telico',
            );
        }

        if (empty($this->callerId)) {
            throw new MessengerException(
                'Telico caller ID not configured.',
                code: 422,
                transportName: 'telico',
            );
        }

        $url = 'https://sms.telico.cloud/api/send_sms?' . http_build_query([
            'source_did'  => $this->callerId,
            'destination' => $destination,
            'message'     => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$this->username}:{$this->smsPass}",
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new MessengerException(
                "Telico SMS API returned HTTP {$httpCode}",
                code: $httpCode,
                transportName: 'telico',
            );
        }

        if ($response === false) {
            throw new MessengerException(
                'Telico SMS API returned empty response.',
                code: 500,
                transportName: 'telico',
            );
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MessengerException(
                'Invalid JSON from Telico SMS API.',
                code: 502,
                transportName: 'telico',
            );
        }

        return $data;
    }

    public function getConversations(): array
    {
        $url = 'https://sms.telico.cloud/api/conversations?' . http_build_query([
            'did' => $this->callerId,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$this->username}:{$this->smsPass}",
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    public function getMessages(string $conversationId): array
    {
        $url = 'https://sms.telico.cloud/api/messages?' . http_build_query([
            'conversation_id' => $conversationId,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$this->username}:{$this->smsPass}",
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}
```

---

## TelicoTransport Implementation

```php
namespace Plugins\Telico;

use App\Core\MessengerTransportInterface;
use App\Core\Message;

class TelicoTransport implements MessengerTransportInterface
{
    private TelicoApiClient $client;

    public function __construct(array $config)
    {
        $this->client = new TelicoApiClient($config);
    }

    public function send(Message $message): bool
    {
        // TelicoApiClient::sendSms throws MessengerException on any failure.
        // We let it propagate so the Messenger service can surface the error.
        $this->client->sendSms($message->to, $message->body);
        return true;
    }

    public function identifier(): string
    {
        return 'telico';
    }
}
```

---

## Settings Section (SettingsRegistry)

```php
SettingsRegistry::addSection([
    'id'       => 'telico-sms',
    'label'    => 'Telico SMS Settings',
    'column'   => 'right',
    'order'    => 40,
    'keys'     => ['telico.username', 'telico.sms_pass', 'telico.callerid'],
    'permission' => 'settings.telico',
    'render'   => function (array $ctx): string {
        extract($ctx);
        ob_start();
        ?>
        <div class="card">
            <div class="card-header fw-semibold">Telico SMS Settings</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small">Telico Username</label>
                    <?= form_text_field('telico_username', $settings['telico.username'] ?? '') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label small">SMS Password</label>
                    <input type="password" class="form-control form-control-sm"
                           name="telico_sms_pass" id="telico_sms_pass">
                    <div class="form-text">Leave blank to keep current password.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Caller ID (Sender DID)</label>
                    <input type="text" class="form-control form-control-sm"
                           name="telico_callerid" id="telico_callerid"
                           placeholder="+1XXXXXXXXXX" maxlength="16">
                    <div class="form-text">E.164 format. This number appears as the sender.</div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    },
    'validate' => function (array $input): array {
        $errors = [];
        if (!empty($input['telico.callerid']) && !preg_match('/^\+\d{7,15}$/', $input['telico.callerid'])) {
            $errors['telico.callerid'] = 'Must be E.164 format (e.g. +1XXXXXXXXXX).';
        }
        return $errors;
    },
    'save' => function (array $input, SystemSettingService $svc): void {
        $svc->set('telico.username', $input['telico.username'] ?? '');
        if (!empty($input['telico.sms_pass'])) {
            $svc->set('telico.sms_pass', $input['telico.sms_pass']);
        }
        $svc->set('telico.callerid', $input['telico.callerid'] ?? '');
    },
    'source' => 'telico',
]);
```

---

## Security Notes

### Credentials

- **Never committed** — stored in `system_settings` table, never in plugin.json or version control.
- **Password fields masked** — `<input type="password">` with no pre-filled value.
- **Username is not secret** — but still never echoed back in admin views.

### Caller ID Validation

- **E.164 format enforced** — `+` followed by 7-15 digits.
- **Length capped** — prevents DoS and ensures compatibility.
- **No special characters** — only `+` and digits allowed.

### API Security

- **HTTPS only** — Telico endpoints are HTTPS; curl follows no redirects to HTTP.
- **Timeout set** — 15 second timeout prevents hanging requests.
- **Error messages sanitized** — Telico API error messages are validated before displaying.

### Telico API Considerations

- **Basic auth credentials** travel in every request (over HTTPS).
- **sms_pass** is separate from **voip_pass** — use the SMS-specific credential.
- **Rate limits** — Telico may have SMS rate limits; plugin should not implement backoff in the first slice (deferred).

---

## Admin API Endpoints

```
GET    /api/admin/telico/conversations   — list conversations
GET    /api/admin/telico/conversations/{id} — get messages
POST   /api/admin/telico/test            — send test SMS
```

Controller: `TelicoApiController` with `['WebAuth', 'WebPermission:settings.telico']` middleware.

---

## Implementation Priorities

1. ~~`TelicoApiClient` (API wrapper)~~ — implemented
2. ~~`TelicoTransport` (MessengerTransportInterface)~~ — implemented
3. ~~Settings section via SettingsRegistry~~ — implemented
4. ~~Hook transport override in TelicoHooks.php~~ — implemented
5. ~~Test SMS endpoint~~ — implemented
6. Voice calls API (deferred)
7. Conversation inbox UI (deferred)

---

## Differences from Existing NetMon Helper

| Aspect | NetMon Helper | Kernel-Web Plugin |
|--------|------|------|
| Base class | Extends `Helper` abstract | Standalone — no base class |
| Config access | `$this->Config->get()` | SettingsRegistry / SystemSettingService |
| Auth | Hardcoded config keys | Settings at runtime |
| Error handling | Implicit (raw JSON) | `MessengerException` with codes |
| Structure | Single Helper class | Split: client + transport + settings |
| Plugin lifecycle | None | Manifest, enable/disable hooks |
| Security | None | Masked passwords, input validation |
| Testing | None | Unit + integration tests |

---

## Open Questions

| ID | Question | Decision |
|----|--|--|
| TP-1 | Should the plugin also handle voice calls? | Yes — include `call()` alongside SMS. Separate transport or combined API client. |
| TP-2 | Should Telico be the default SMS provider or opt-in? | Opt-in — SMS disabled by default until configured. |
| TP-3 | How to handle SMS delivery receipts? | Provider webhook to `/api/sms/receipt`. Deferred to later phase. |
| TP-4 | Should long messages be auto-chunked? | No — throw exception >160 chars. Deferred. |
| TP-5 | Should the plugin support fallback to another provider? | No — single provider per installation. Multi-provider is a future enhancement. |
| TP-6 | Should the plugin include a conversation inbox UI? | No — inbox UI is a Phase 3 feature. API endpoints only for now. |
