<?php

/**
 * Messenger (SMS) configuration.
 *
 * Core provides the interface; provider selection is done via
 * plugins that register their own transports.
 */
return [
    // Active SMS provider: null = disabled, 'telico' = Telico plugin, etc.
    'provider' => null,

    // Default sender number (E.164 format).
    'from_number' => '',
];
