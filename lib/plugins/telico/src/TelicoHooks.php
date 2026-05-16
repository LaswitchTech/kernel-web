<?php

namespace Plugins\Telico;

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
            'source'   => 'telico',
        ]);
    }

    /**
     * Swap the default Messenger transport with Telico if configured.
     *
     * Only swaps if:
     *   - The container has a Messenger instance
     *   - Telico credentials are configured (non-empty username)
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

        if (!$container->has('settings')) {
            return;
        }

        $settings = $container->get('settings');
        if (!$settings instanceof SystemSettingService) {
            return;
        }

        $username = $settings->getString('telico.username', '');
        $smsPass  = $settings->getString('telico.sms_pass', '');
        $callerId = $settings->getString('telico.callerid', '');

        if ($username === '' || $smsPass === '' || $callerId === '') {
            // All three credentials required before swapping transport.
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
