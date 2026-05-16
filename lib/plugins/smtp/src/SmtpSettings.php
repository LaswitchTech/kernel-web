<?php

namespace Plugins\Smtp;

use App\Core\SettingsRegistry;
use App\Core\SettingsSection;
use App\Services\SystemSettingService;

/**
 * Registers the SMTP settings section with SettingsRegistry.
 *
 * Call during kernel boot (after the registry is initialized) so the
 * SMTP configuration is available to the SmtpTransport at runtime.
 */
class SmtpSettings
{
    /**
     * Register the SMTP settings section.
     */
    public static function register(): void
    {
        SettingsRegistry::addSection([
            'id'       => 'smtp',
            'label'    => 'SMTP Settings',
            'column'   => 'right',
            'order'    => 30,  // after Application (10), after Mailer placeholder (20)
            'keys'     => [
                'smtp.host',
                'smtp.port',
                'smtp.encryption',
                'smtp.user',
                'smtp.pass',
                'smtp.verify_peer',
                'smtp.from_address',
                'smtp.from_name',
            ],
            'permission' => null,
            'render'   => [self::class, 'render'],
            'validate' => [self::class, 'validate'],
            'save'     => [self::class, 'save'],
            'source'   => 'smtp',
        ]);
    }

    /**
     * Render the SMTP settings form HTML.
     *
     * Password fields are always empty (never echo stored value).
     */
    public static function render(array $ctx): string
    {
        $settings = $ctx['settings'] ?? [];
        $errors   = $ctx['errors'] ?? [];

        ob_start();
        ?>
        <div class="card">
            <div class="card-header fw-semibold">SMTP Settings</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="smtp_host" class="form-label small">Host</label>
                    <input type="text" class="form-control form-control-sm"
                           name="smtp_host" id="smtp_host"
                           value="<?= htmlspecialchars($settings['smtp.host'] ?? 'localhost') ?>"
                           placeholder="localhost">
                    <div class="form-text">MailHog (dev): localhost:1025 · Mailtrap: smtp.mailtrap.io:2525</div>
                    <?php if (isset($errors['smtp.host'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['smtp.host']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="smtp_port" class="form-label small">Port</label>
                    <input type="text" class="form-control form-control-sm"
                           name="smtp_port" id="smtp_port"
                           value="<?= htmlspecialchars($settings['smtp.port'] ?? '587') ?>"
                           placeholder="587">
                    <div class="form-text">587 (STARTTLS), 465 (SSL), or 25 (none)</div>
                    <?php if (isset($errors['smtp.port'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['smtp.port']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="smtp_encryption" class="form-label small">Encryption</label>
                    <select class="form-select form-select-sm" name="smtp_encryption" id="smtp_encryption">
                        <option value="tls" <?= ($settings['smtp.encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (recommended)</option>
                        <option value="ssl" <?= ($settings['smtp.encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
                        <option value="none" <?= ($settings['smtp.encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (port 25)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="smtp_user" class="form-label small">Username</label>
                    <input type="text" class="form-control form-control-sm"
                           name="smtp_user" id="smtp_user"
                           value="<?= htmlspecialchars($settings['smtp.user'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="smtp_pass" class="form-label small">Password</label>
                    <input type="password" class="form-control form-control-sm"
                           name="smtp_pass" id="smtp_pass" autocomplete="new-password">
                    <div class="form-text">Leave blank to keep the current password.</div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="smtp_verify_peer"
                               id="smtp_verify_peer" value="1"
                               <?= ($settings['smtp.verify_peer'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="smtp_verify_peer">
                            Verify SSL/TLS peer certificate
                        </label>
                    </div>
                    <div class="form-text">Never disable in production.</div>
                </div>

                <hr>

                <div class="mb-3">
                    <label for="smtp_from_address" class="form-label small">Sender Address (override)</label>
                    <input type="email" class="form-control form-control-sm"
                           name="smtp_from_address" id="smtp_from_address"
                           value="<?= htmlspecialchars($settings['smtp.from_address'] ?? '') ?>"
                           placeholder="Leave blank to use config default">
                </div>

                <div class="mb-3">
                    <label for="smtp_from_name" class="form-label small">Sender Name (override)</label>
                    <input type="text" class="form-control form-control-sm"
                           name="smtp_from_name" id="smtp_from_name"
                           value="<?= htmlspecialchars($settings['smtp.from_name'] ?? '') ?>"
                           placeholder="Leave blank to use config default">
                </div>

                <!-- Test email button -->
                <div class="mb-0">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="smtp-test-btn">
                        <i class="bi bi-envelope me-1"></i>Send Test Email
                    </button>
                    <span id="smtp-test-status" class="small ms-2" style="display:none;"></span>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var btn = document.getElementById('smtp-test-btn');
            var status = document.getElementById('smtp-test-status');
            if (!btn || !status) return;

            btn.addEventListener('click', function () {
                btn.disabled = true;
                btn.textContent = 'Sending…';
                status.style.display = 'none';

                fetch('/api/smtp/test-email', {
                    method: 'POST',
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    status.style.display = '';
                    if (data.success) {
                        status.className = 'text-success';
                        status.textContent = data.message || 'Test email sent.';
                        setTimeout(function () { status.style.display = 'none'; }, 4000);
                    } else {
                        status.className = 'text-danger';
                        status.textContent = data.error || 'Test email failed.';
                    }
                })
                .catch(function () {
                    status.style.display = '';
                    status.className = 'text-danger';
                    status.textContent = 'Request failed.';
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-envelope me-1"></i>Send Test Email';
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Validate SMTP settings.
     *
     * @return array<string, string> field → error message
     */
    public static function validate(array $input): array
    {
        $errors = [];

        if (!empty($input['smtp.host'])) {
            // Validate hostname or IP address.
            $host = trim($input['smtp.host']);
            if (
                filter_var($host, FILTER_VALIDATE_IP) === false &&
                !preg_match('/^[a-z0-9][-a-z0-9.]*[a-z0-9]$/i', $host) &&
                $host !== 'localhost'
            ) {
                $errors['smtp.host'] = 'Invalid hostname.';
            }
        }

        if (isset($input['smtp.port']) && $input['smtp.port'] !== '') {
            $port = (int) $input['smtp.port'];
            if ($port < 1 || $port > 65535) {
                $errors['smtp.port'] = 'Port must be between 1 and 65535.';
            }
        }

        return $errors;
    }

    /**
     * Save SMTP settings to the SystemSettingService.
     */
    public static function save(array $input, SystemSettingService $svc): void
    {
        $svc->set('smtp.host', $input['smtp.host'] ?? 'localhost');
        $svc->set('smtp.port', $input['smtp.port'] ?? '587');
        $svc->set('smtp.encryption', $input['smtp.encryption'] ?? 'tls');
        $svc->set('smtp.user', $input['smtp.user'] ?? '');
        $svc->set('smtp.verify_peer', isset($input['smtp.verify_peer']) ? true : false);
        $svc->set('smtp.from_address', $input['smtp.from_address'] ?? '');
        $svc->set('smtp.from_name', $input['smtp.from_name'] ?? '');

        // Only update password if a new value was provided.
        if (!empty($input['smtp.pass'])) {
            $svc->set('smtp.pass', $input['smtp.pass']);
        }
    }
}
