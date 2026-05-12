<?php

namespace App\Core\Mail;

/**
 * Mailer service facade — the main entry point for sending email.
 *
 * Composes MailMessage, delegates to Transport, optionally resolves
 * templates via TemplateRegistry.
 */
class Mailer
{
    public function __construct(
        private TransportInterface $transport,
    ) {}

    /**
     * Send a composed message.
     *
     * If $message->bodyHtml is empty and a template is set, renders the
     * template as the HTML body. Otherwise sends the message as-is.
     *
     * @throws MailerException on send failure
     */
    public function send(MailMessage $message): bool
    {
        if ($message->bodyHtml === '' && $message->bodyText === '') {
            $templateName = $message->template ?? null;
            if ($templateName !== null) {
                $message = clone $message;
                $message->bodyHtml = TemplateRegistry::render($templateName, $message->context);
            }
        }

        return $this->transport->send($message);
    }

    /**
     * Create a message with a template (no body).
     *
     * @param array<string, mixed> $context
     */
    public function withTemplate(string $template, array $context = []): MailMessage
    {
        $message = new MailMessage('', '', '', '', '');
        $message->template = $template;
        $message->context = $context;
        return $message;
    }

    /**
     * Get the current transport identifier.
     */
    public function transportIdentifier(): string
    {
        return $this->transport->identifier();
    }

    /**
     * Swap the transport at runtime.
     */
    public function setTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
    }
}
