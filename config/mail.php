<?php

/**
 * Mailer configuration.
 *
 * Default values for the mail() transport. Override via:
 *   - Environment variables (MAIL_FROM_ADDRESS, MAIL_FROM_NAME)
 *   - config/local.php: return ['mail' => ['from_address' => '...']]
 */
return [
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost',
    'from_name'    => getenv('MAIL_FROM_NAME')    ?: 'Kernel-Web',
];
