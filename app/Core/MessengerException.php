<?php

namespace App\Core;

/**
 * Messenger exception — wraps transport-specific failures with
 * structured metadata for error handling and display.
 */
class MessengerException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?string $transportName = null,
    ) {
        parent::__construct($message, $code);
    }
}
