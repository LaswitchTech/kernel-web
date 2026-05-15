<?php

namespace Plugins\Telico;

use App\Core\SettingsRegistry;
use App\Core\SettingsSection;
use App\Services\SystemSettingService;

/**
 * Registers the Telico SMS settings section with SettingsRegistry.
 */
class TelicoSettings
{
    /**
     * Register the Telico settings section.
     */
    public static function register(): void
    {
        SettingsRegistry::addSection([
            'id'       => 'telico',
            'label'    => 'Telico SMS Settings',
            'column'   => 'right',
            'order'    => 40,
            'keys'     => [
                'telico.username',
                'telico.sms_pass',
                'telico.callerid',
            ],
            'permission' => 'settings.telico',
            'render'   => [self::class, 'render'],
            'validate' => [self::class, 'validate'],
            'save'     => [self::class, 'save'],
            'source'   => 'telico',
        ]);
    }

    /**
     * Render the Telico settings form HTML.
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
            <div class="card-header fw-semibold">Telico SMS Settings</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="telico_username" class="form-label small">Username</label>
                    <input type="text" class="form-control form-control-sm"
                           name="telico_username" id="telico_username"
                           value="<?= htmlspecialchars($settings['telico.username'] ?? '') ?>"
                           placeholder="Telico API username">
                    <div class="form-text">Your Telico account username.</div>
                    <?php if (isset($errors['telico.username'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['telico.username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="telico_sms_pass" class="form-label small">SMS API Password</label>
                    <input type="password" class="form-control form-control-sm"
                           name="telico_sms_pass" id="telico_sms_pass" autocomplete="new-password">
                    <div class="form-text">Leave blank to keep the current password. Send-only API key.</div>
                    <?php if (isset($errors['telico.sms_pass'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['telico.sms_pass']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="telico_callerid" class="form-label small">Caller ID (phone number)</label>
                    <input type="text" class="form-control form-control-sm"
                           name="telico_callerid" id="telico_callerid"
                           value="<?= htmlspecialchars($settings['telico.callerid'] ?? '') ?>"
                           placeholder="+1XXXXXXXXXX">
                    <div class="form-text">E.164 format (e.g., +15551234567). This number appears as the sender.</div>
                    <?php if (isset($errors['telico.callerid'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['telico.callerid']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Validate Telico settings.
     *
     * @return array<string, string> field → error message
     */
    public static function validate(array $input): array
    {
        $errors = [];

        if (empty($input['telico.username'])) {
            $errors['telico.username'] = 'Username is required.';
        }

        if (empty($input['telico.sms_pass']) && !isset($input['_telico_sms_pass_preserved'])) {
            $errors['telico.sms_pass'] = 'SMS API password is required.';
        }

        if (!empty($input['telico.callerid'])) {
            $callerId = trim($input['telico.callerid']);
            if (!preg_match('/^\+\d{7,15}$/', $callerId)) {
                $errors['telico.callerid'] = 'Must be E.164 format (e.g., +15551234567).';
            }
        }

        return $errors;
    }

    /**
     * Save Telico settings to the SystemSettingService.
     */
    public static function save(array $input, SystemSettingService $svc): void
    {
        $svc->set('telico.username', $input['telico.username'] ?? '');

        // Only update password if a new value was provided.
        if (!empty($input['telico.sms_pass'])) {
            $svc->set('telico.sms_pass', $input['telico.sms_pass']);
        }

        $svc->set('telico.callerid', $input['telico.callerid'] ?? '');
    }
}
