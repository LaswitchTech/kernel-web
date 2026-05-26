<?php

/**
 * Messenger (SMS) configuration.
 *
 * Core provides the interface; provider selection is done via
 * plugins that register their own transports.
 *
 * Telico plugin settings are stored under the 'telico' key.
 * Non-sensitive values (username, callerid) are managed via
 * /admin/settings and written to config/local.php.
 * Sensitive values (sms_password) are stored in the database.
 */
return [
    // Active SMS provider: null = disabled, 'telico' = Telico plugin, etc.
    'provider' => null,

    // Default sender number (E.164 format).
    'from_number' => '',

    // Telico SMS settings (non-sensitive — sensitive creds in DB).
    'telico' => [
        'username' => '',
        'sms_pass' => '',
        'callerid' => '',
    ],
];
