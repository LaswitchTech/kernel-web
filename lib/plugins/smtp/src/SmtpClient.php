<?php

namespace Plugins\Smtp;

/**
 * Raw SMTP client — zero external dependencies.
 *
 * Implements the full SMTP command exchange using PHP streams.
 * Supports STARTTLS and AUTH LOGIN / AUTH PLAIN authentication.
 */
class SmtpClient
{
    /** @var mixed PHP stream resource (typed as mixed because 'resource' type hint is removed in PHP 8.2+) */
    private mixed $stream;
    private bool $authenticated = false;

    public function __construct(mixed $stream)
    {
        $this->stream = $stream;
        stream_set_timeout($this->stream, 15);
    }

    /**
     * Read the server greeting (220 response).
     */
    public function readGreeting(): void
    {
        $response = $this->readResponse();
        if (!str_starts_with($response, '220')) {
            throw new \RuntimeException("Unexpected greeting: {$response}");
        }
    }

    /**
     * Send EHLO command.
     */
    public function ehlo(): void
    {
        $this->sendCommand('EHLO localhost');
    }

    /**
     * Initiate STARTTLS if supported by the server.
     *
     * @throws \RuntimeException if STARTTLS not supported or fails
     */
    public function startTls(): void
    {
        $response = $this->sendCommand('STARTTLS');
        if (!str_starts_with($response, '220')) {
            throw new \RuntimeException('STARTTLS not supported by server.');
        }

        $crypto = stream_socket_enable_crypto(
            $this->stream,
            true,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
        );

        if (!$crypto) {
            throw new \RuntimeException('Failed to initiate TLS encryption.');
        }
    }

    /**
     * Authenticate via LOGIN or PLAIN method.
     *
     * @throws \RuntimeException if authentication fails
     */
    public function auth(string $username, string $password): void
    {
        // Try AUTH PLAIN first (simpler).
        $plainResponse = $this->tryAuthPlain($username, $password);
        if ($plainResponse !== null) {
            $this->authenticated = true;
            return;
        }

        // Fall back to AUTH LOGIN.
        $this->tryAuthLogin($username, $password);
        $this->authenticated = true;
    }

    /**
     * MAIL FROM command.
     */
    public function mailFrom(string $address): void
    {
        $response = $this->sendCommand("MAIL FROM:<{$address}>");
        if (!str_starts_with($response, '250')) {
            throw new \RuntimeException("MAIL FROM failed: {$response}");
        }
    }

    /**
     * RCPT TO command.
     */
    public function rcptTo(string $address): void
    {
        $response = $this->sendCommand("RCPT TO:<{$address}>");
        if (!str_starts_with($response, '250') && !str_starts_with($response, '251')) {
            throw new \RuntimeException("RCPT TO failed: {$response}");
        }
    }

    /**
     * Send DATA command and body.
     */
    public function data(string $data): void
    {
        $response = $this->sendCommand('DATA');
        if (!str_starts_with($response, '354')) {
            throw new \RuntimeException("DATA rejected: {$response}");
        }

        // Send the body followed by the dot terminator.
        fwrite($this->stream, $data . "\r\n.\r\n");
        $response = $this->readResponse();
        if (!str_starts_with($response, '250')) {
            throw new \RuntimeException("DATA failed: {$response}");
        }
    }

    /**
     * QUIT command.
     */
    public function quit(): void
    {
        try {
            $this->sendCommand('QUIT');
        } catch (\RuntimeException) {
            // Ignore QUIT failures — connection is closing anyway.
        }
    }

    /**
     * Send a command and return the response line.
     */
    private function sendCommand(string $command): string
    {
        fwrite($this->stream, $command . "\r\n");
        return $this->readResponse();
    }

    /**
     * Read a response line from the server.
     *
     * Handles multi-line responses (joined by spaces after continuation markers).
     */
    private function readResponse(): string
    {
        $response = '';

        while (!feof($this->stream)) {
            $line = fgets($this->stream, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;

            // If the line doesn't end with '-', we're done.
            if (!str_ends_with(trim($response), '-')) {
                break;
            }
        }

        return trim($response);
    }

    /**
     * Try AUTH PLAIN authentication.
     *
     * Returns null on failure (server doesn't support it), throws on auth failure.
     */
    private function tryAuthPlain(string $username, string $password): ?string
    {
        $response = $this->sendCommand('AUTH PLAIN AG');

        // Server doesn't support AUTH PLAIN.
        if (str_starts_with($response, '500') || str_starts_with($response, '502')) {
            return null;
        }

        // Server sent a 334 challenge — send the credentials.
        if (str_starts_with($response, '334')) {
            $response = $this->sendCommand(base64_encode("\0{$username}\0{$password}"));
        }

        if (str_starts_with($response, '235')) {
            return $response;
        }

        throw new \RuntimeException("AUTH PLAIN failed: {$response}");
    }

    /**
     * Try AUTH LOGIN authentication.
     *
     * @throws \RuntimeException if LOGIN not supported or auth fails
     */
    private function tryAuthLogin(string $username, string $password): void
    {
        $response = $this->sendCommand('AUTH LOGIN');

        if (str_starts_with($response, '500') || str_starts_with($response, '502')) {
            throw new \RuntimeException('AUTH LOGIN not supported by server.');
        }

        // Send username.
        $response = $this->sendCommand(base64_encode($username));
        if (!str_starts_with($response, '334')) {
            throw new \RuntimeException("AUTH LOGIN username failed: {$response}");
        }

        // Send password.
        $response = $this->sendCommand(base64_encode($password));
        if (!str_starts_with($response, '235')) {
            throw new \RuntimeException("AUTH LOGIN password failed: {$response}");
        }
    }
}
