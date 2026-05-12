<?php

namespace App\Core\Mail;

/**
 * Default mail() transport — zero dependencies.
 *
 * Uses PHP's built-in mail() function. Does NOT support attachments;
 * callers with attachments should use an SMTP transport plugin.
 */
class MailTransport implements TransportInterface
{
    public function __construct(
        private string $fromAddress = 'noreply@localhost',
        private string $fromName = 'Kernel-Web',
    ) {}

    public function send(MailMessage $message): bool
    {
        if (!empty($message->attachments)) {
            throw new MailerException(
                'Attachments not supported by mail() transport. Use SMTP transport.',
                code: 422,
                transportName: 'mail',
            );
        }

        $headers = [
            "From: {$this->fromName} <{$this->fromAddress}>",
            "Reply-To: {$this->fromName} <{$this->fromAddress}>",
            'X-Mailer: Kernel-Web',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];

        // Add CC headers (validate to prevent header injection).
        foreach ($message->cc as $cc) {
            if (str_contains($cc, "\r") || str_contains($cc, "\n") || str_contains($cc, "\0")) {
                throw new MailerException("Invalid CC address: contains control characters", code: 422, transportName: 'mail');
            }
            $headers[] = "Cc: {$cc}";
        }

        // BCC: mail() has no native BCC mechanism — reject if any BCC addresses are set.
        if (!empty($message->bcc)) {
            throw new MailerException('BCC not supported by mail() transport. Use SMTP transport for BCC.', code: 422, transportName: 'mail');
        }

        $result = mail(
            $message->to,
            $message->subject,
            $message->bodyHtml,
            implode("\r\n", $headers),
        );

        if (!$result) {
            throw new MailerException(
                "mail() returned false — message not accepted",
                code: 500,
                transportName: 'mail',
            );
        }

        return true;
    }

    public function identifier(): string
    {
        return 'mail';
    }
}
