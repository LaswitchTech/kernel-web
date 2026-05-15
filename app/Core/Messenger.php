<?php

namespace App\Core;

/**
 * Messenger service — wraps a transport and provides a simple send API.
 *
 * The active transport is set via setTransport() or via dependency injection.
 * Transports are registered as plugins; the kernel defaults to no transport
 * (SMS disabled) until a provider plugin is enabled.
 */
class Messenger
{
    public function __construct(
        private ?MessengerTransportInterface $transport = null,
    ) {}

    /**
     * Send a message.
     *
     * @throws MessengerException if no transport is configured or send fails
     */
    public function send(Message $message): bool
    {
        if ($this->transport === null) {
            throw new MessengerException(
                'No SMS transport configured. Enable a transport plugin first.',
                code: 503,
                transportName: null,
            );
        }

        return $this->transport->send($message);
    }

    /**
     * Return the active transport identifier, or null if no transport.
     */
    public function transportIdentifier(): ?string
    {
        return $this->transport?->identifier();
    }

    /**
     * Replace the active transport.
     */
    public function setTransport(MessengerTransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    /**
     * Check if a transport is configured.
     */
    public function hasTransport(): bool
    {
        return $this->transport !== null;
    }
}
