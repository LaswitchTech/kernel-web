<?php

namespace App\Core\Mail;

/**
 * Interface for mail transport implementations.
 *
 * Core defines this interface; any class implementing it can be wired
 * into the container as the default transport.
 */
interface TransportInterface
{
    /**
     * Send a mail message.
     *
     * @throws MailerException on failure
     */
    public function send(MailMessage $message): bool;

    /**
     * Return the transport identifier (e.g. 'mail', 'smtp').
     */
    public function identifier(): string;
}
