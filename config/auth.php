<?php

return [
    /*
     * Active authentication provider.
     * Supported: 'local'
     * Future:    'ldap', 'imap'
     */
    'provider' => 'local',

    'session' => [
        'name'     => 'kernel_web_session',
        'lifetime' => 7200,    // seconds (2 hours)
        'secure'   => false,   // set true when serving over HTTPS in production
    ],

    'remember_me' => [
        'enabled'  => true,
        'lifetime' => 2592000, // 30 days in seconds
        'cookie'   => 'kernel_remember',
    ],
];
