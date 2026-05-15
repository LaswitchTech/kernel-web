<?php

namespace App\Core;

/**
 * Interface for SMS/messaging transports.
 *
 * Providers implement this interface to wrap their SMS API
 * and register via the Messenger facade/service.
 */
interface MessengerTransportInterface
{
    /**
     * Send a message through this transport.
     *
     * @throws MessengerException on failure
     */
    public function send(Message $message): bool;

    /**
     * Return the transport identifier (e.g. 'telico', 'twilio').
     */
    public function identifier(): string;
}
