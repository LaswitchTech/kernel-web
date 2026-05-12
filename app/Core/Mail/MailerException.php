<?php

namespace App\Core\Mail;

/**
 * Exception thrown by mailer components on failure.
 *
 * All mailer operations throw this type (never untyped exceptions)
 * so callers can catch consistently for graceful fallback.
 */
class MailerException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?string $transportName = null,
    ) {
        parent::__construct($message, $code);
    }
}
