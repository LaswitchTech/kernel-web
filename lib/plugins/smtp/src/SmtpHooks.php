<?php

namespace Plugins\Smtp;

use App\Core\Config;
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
            'saveConfig' => [SmtpSettings::class, 'saveConfig'],
            'source'   => 'smtp',
        ]);
    }

    /**
     * Swap the default Mailer transport with SMTP if configured.
     *
     * Reads config from Config::load('mail') which merges base mail.php
     * with config/local.php overrides (including plugin config written
     * by ConfigOverrideService via /admin/settings).
     *
     * Sensitive credentials (password) are read from the DB
     * (SystemSettingService) since they are not stored in config files.
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

        // Read non-sensitive config from merged config/local.php.
        $mailConfig = Config::load('mail');
        $host = $mailConfig['smtp.host'] ?? 'localhost';
        if ($host === 'localhost') {
            // SMTP not configured — keep default transport.
            return;
        }

        // Read sensitive credential from DB.
        $dbSvc = null;
        if ($container->has('db')) {
            $dbSvc = new SystemSettingService(
                new \App\Models\SystemSettingRepository($container->get('db'))
            );
        }

        $config = [
            'host'        => $host,
            'port'        => (int) ($mailConfig['smtp.port'] ?? 587),
            'encryption'  => $mailConfig['smtp.encryption'] ?? 'tls',
            'user'        => $mailConfig['smtp.user'] ?? '',
            'pass'        => $dbSvc ? $dbSvc->getString('smtp.pass', '') : '',
            'verify_peer' => (bool) ($mailConfig['smtp.verify_peer'] ?? true),
            'from_address' => $mailConfig['smtp.from_address'] ?? '',
            'from_name'   => $mailConfig['smtp.from_name'] ?? '',
        ];

        $smtpTransport = new SmtpTransport($config);
        $mailer->setTransport($smtpTransport);
    }
}
