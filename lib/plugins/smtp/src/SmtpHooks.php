<?php

namespace Plugins\Smtp;

use App\Core\Container;
use App\Core\Mail\Mailer;
use App\Core\Mail\TransportInterface;
use App\Core\SettingsRegistry;
use App\Services\SystemSettingService;

/**
 * SMTP plugin bootstrap hooks.
 *
 * Registered via plugin.json "hooks" field.
 * Called once during plugin enablement to wire up the SMTP transport
 * and register the settings section with SettingsRegistry.
 */
class SmtpHooks
{
    /**
     * Bootstrap: register settings section and swap SMTP transport.
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
     * Register the SMTP settings section with SettingsRegistry.
     */
    private static function registerSettings(): void
    {
        SettingsRegistry::addSection([
            'id'       => 'smtp',
            'label'    => 'SMTP Settings',
            'column'   => 'right',
            'order'    => 30,
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
            'render'   => [SmtpSettings::class, 'render'],
            'validate' => [SmtpSettings::class, 'validate'],
            'save'     => [SmtpSettings::class, 'save'],
            'source'   => 'smtp',
        ]);
    }

    /**
     * Swap the default Mailer transport with SMTP if configured.
     *
     * Only swaps if:
     *   - The container has a Mailer instance
     *   - SMTP host is configured (non-default)
     */
    private static function swapTransport(Container $container): void
    {
        if (!$container->has('mailer')) {
            return;
        }

        $mailer = $container->get('mailer');
        if (!$mailer instanceof Mailer) {
            return;
        }

        if (!$container->has('settings')) {
            return;
        }

        $settings = $container->get('settings');
        if (!$settings instanceof SystemSettingService) {
            return;
        }

        $host = $settings->getString('smtp.host', 'localhost');
        if ($host === 'localhost') {
            // SMTP not configured — keep default transport.
            return;
        }

        $config = [
            'host'        => $host,
            'port'        => $settings->getInt('smtp.port', 587),
            'encryption'  => $settings->getString('smtp.encryption', 'tls'),
            'user'        => $settings->getString('smtp.user', ''),
            'pass'        => $settings->getString('smtp.pass', ''),
            'verify_peer' => $settings->getBool('smtp.verify_peer', true),
            'from_address' => $settings->getString('smtp.from_address', ''),
            'from_name'   => $settings->getString('smtp.from_name', ''),
        ];

        $smtpTransport = new SmtpTransport($config);
        $mailer->setTransport($smtpTransport);
    }
}
