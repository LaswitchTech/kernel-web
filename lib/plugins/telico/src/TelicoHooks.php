<?php

namespace Plugins\Telico;

use App\Core\Config;
use App\Core\Container;
use App\Core\Messenger;
use App\Core\SettingsRegistry;
use App\Services\SystemSettingService;

/**
 * Telico plugin bootstrap hooks.
 *
 * Registered via plugin.json "hooks" field.
 * Called once during plugin enablement to wire up the Telico transport
 * and register the settings section with SettingsRegistry.
 */
class TelicoHooks
{
    /**
     * Bootstrap: register settings section and swap messenger transport.
     *
     * @param Container $container
     * @param array<string, mixed> $context
     */
    public static function bootstrap(Container $container, array $context): void
    {
        self::registerSettings();
        self::swapTransport($container);
    }

    /**
     * Register the Telico settings section with SettingsRegistry.
     */
    private static function registerSettings(): void
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
            'permission' => null,
            'render'   => [TelicoSettings::class, 'render'],
            'validate' => [TelicoSettings::class, 'validate'],
            'save'     => [TelicoSettings::class, 'save'],
            'saveConfig' => [TelicoSettings::class, 'saveConfig'],
            'source'   => 'telico',
        ]);
    }

    /**
     * Swap the default Messenger transport with Telico if configured.
     *
     * Reads config from Config::load('messenger') which merges base
     * messenger.php with config/local.php overrides (including plugin
     * config written by ConfigOverrideService via /admin/settings).
     *
     * Sensitive credentials (password) are read from the DB.
     */
    private static function swapTransport(Container $container): void
    {
        if (!$container->has('messenger')) {
            return;
        }

        $messenger = $container->get('messenger');
        if (!$messenger instanceof Messenger) {
            return;
        }

        // Read non-sensitive config from merged config/local.php.
        $mConfig = Config::load('messenger');
        $telicoConfig = $mConfig['telico'] ?? [];
        $username = $telicoConfig['username'] ?? '';
        $callerId = $telicoConfig['callerid'] ?? '';

        if ($username === '' || $callerId === '') {
            // Credentials required before swapping transport.
            return;
        }

        // Read sensitive credential from DB.
        $dbSvc = null;
        if ($container->has('db')) {
            $dbSvc = new SystemSettingService(
                new \App\Models\SystemSettingRepository($container->get('db'))
            );
        }
        $smsPass = $dbSvc ? $dbSvc->getString('telico.sms_pass', '') : '';

        if ($smsPass === '') {
            // Password required before swapping transport.
            return;
        }

        $config = [
            'telico.username' => $username,
            'telico.sms_pass' => $smsPass,
            'telico.callerid' => $callerId,
        ];

        $telicoTransport = new TelicoTransport($config);
        $messenger->setTransport($telicoTransport);
    }
}
