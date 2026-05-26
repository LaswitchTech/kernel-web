<?php

/**
 * Mailer configuration.
 *
 * Base defaults for the mail() transport and SMTP plugin settings.
 * Override non-sensitive SMTP settings via:
 *   - config/local.php: return ['mail' => ['smtp.host' => '...']]
 *
 * Sensitive SMTP credentials (password) are stored in the database
 * (system_settings table) and read separately at runtime.
 */
return [
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost',
    'from_name'    => getenv('MAIL_FROM_NAME')    ?: 'Kernel-Web',

    // SMTP plugin settings (non-sensitive — managed via /admin/settings).
    // Sensitive credentials (smtp.pass) stored in DB.
    'smtp.host'       => 'localhost',
    'smtp.port'       => 587,
    'smtp.encryption' => 'tls',
    'smtp.user'       => '',
    'smtp.verify_peer' => true,
    'smtp.from_address' => '',
    'smtp.from_name'    => '',
];
