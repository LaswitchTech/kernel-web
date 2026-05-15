<?php

namespace Plugins\Smtp;

use App\Core\Mail\MailerException;
use App\Core\Mail\MailMessage;
use App\Core\Mail\TransportInterface;

/**
 * SMTP transport — sends email via SMTP protocol.
 *
 * Implements the TransportInterface so it can be wired as the Mailer's
 * transport in place of the default MailTransport (PHP mail()).
 *
 * Zero Composer dependency — uses PHP streams for the SMTP protocol
 * with AUTH LOGIN and AUTH PLAIN support.
 *
 * Settings keys (stored in system_settings):
 *   smtp.host       — SMTP server hostname (default: localhost)
 *   smtp.port       — SMTP port (default: 587)
 *   smtp.encryption — 'tls', 'ssl', or 'none' (default: 'tls')
 *   smtp.user       — auth username (empty = no auth)
 *   smtp.pass       — auth password (masked in UI)
 *   smtp.verify_peer — '1'/'0' whether to verify SSL/TLS peer (default: 1)
 *   smtp.from_address — sender address override (optional)
 *   smtp.from_name   — sender name override (optional)
 */
class SmtpTransport implements TransportInterface
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private bool $verifyPeer;
    private string $fromAddress;
    private string $fromName;

    public function __construct(array $config = [])
    {
        $this->host          = $config['host'] ?? 'localhost';
        $this->port          = (int) ($config['port'] ?? 587);
        $this->encryption    = $config['encryption'] ?? 'tls';
        $this->username      = $config['user'] ?? '';
        $this->password      = $config['pass'] ?? '';
        $this->verifyPeer    = isset($config['verify_peer']) ? (bool) $config['verify_peer'] : true;
        $this->fromAddress   = $config['from_address'] ?? '';
        $this->fromName      = $config['from_name'] ?? '';
    }

    /**
     * Send a message via SMTP.
     *
     * @throws MailerException on any failure
     */
    public function send(MailMessage $message): bool
    {
        $dsn = $this->buildDsn();

        $stream = @stream_socket_client(
            $dsn,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $this->streamContext()
        );

        if (!$stream) {
            throw new MailerException(
                "SMTP connection failed: {$errstr} (errno: {$errno})",
                code: 0,
                transportName: 'smtp',
            );
        }

        $client = new SmtpClient($stream);

        try {
            $client->readGreeting();
            $client->ehlo();

            if ($this->encryption === 'tls') {
                $client->startTls();
                $client->ehlo();
            }

            if ($this->username !== '') {
                $client->auth($this->username, $this->password);
            }

            $from = $this->fromAddress !== ''
                ? $this->fromAddress
                : $message->from;

            $client->mailFrom($from);
            $client->rcptTo($message->to);

            $rawHeaders = $this->buildRawHeaders($message);
            $client->data($rawHeaders . $message->bodyHtml);
        } catch (\RuntimeException $e) {
            throw new MailerException(
                "SMTP send failed: {$e->getMessage()}",
                code: 500,
                transportName: 'smtp',
            );
        } finally {
            $client->quit();
            fclose($stream);
        }

        return true;
    }

    public function identifier(): string
    {
        return 'smtp';
    }

    private function buildDsn(): string
    {
        $wrapper = match ($this->encryption) {
            'ssl'   => 'ssl://',
            'tls'   => 'tls://',
            default => '',
        };

        return sprintf('%s%s:%d', $wrapper, $this->host, $this->port);
    }

    private function streamContext(): ?\STREAM\Context
    {
        $params = [];
        if ($this->encryption !== 'none') {
            $params['ssl'] = [
                'verify_peer' => $this->verifyPeer,
                'verify_depth' => 3,
                'cafile' => $this->verifyPeer ? null : null, // Let PHP use system CA bundle
            ];
        }
        return !empty($params) ? stream_context_create($params) : null;
    }

    /**
     * Build raw email headers for the DATA command.
     */
    private function buildRawHeaders(MailMessage $message): string
    {
        $headers = [];

        $fromAddr = $this->fromAddress !== '' ? $this->fromAddress : $message->from;
        $fromName = $this->fromName !== '' ? $this->fromName : $message->fromName;

        $headers[] = "From: {$fromName} <{$fromAddr}>";
        $headers[] = "Reply-To: {$message->fromName} <{$message->from}>";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "X-Mailer: Kernel-Web/SMTP";

        if ($message->cc !== []) {
            $headers[] = 'Cc: ' . implode(', ', $message->cc);
        }

        // Add custom headers
        foreach ($message->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return implode("\r\n", $headers) . "\r\n\r\n";
    }
}
