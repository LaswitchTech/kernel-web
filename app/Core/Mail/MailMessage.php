<?php

namespace App\Core\Mail;

/**
 * Value object representing a single email message.
 *
 * Mutable clone pattern — withXxx() returns a new instance, allowing chaining
 * without breaking BC for existing callers.
 */
class MailMessage
{
    /** @var Attachment[] */
    public array $attachments = [];
    /** @var string[] */
    public array $cc = [];
    /** @var string[] */
    public array $bcc = [];
    /** @var array<string,string> */
    public array $headers = [];

    /** @var string|null Template name to render (for withTemplate) */
    public ?string $template = null;
    /** @var array<string,mixed> Template context variables */
    public array $context = [];

    public function __construct(
        public string $from,
        public string $fromName,
        public string $to,
        public string $toName = '',
        public string $subject = '',
        public string $bodyHtml = '',
        public string $bodyText = '',
    ) {}

    /**
     * Return a new instance with an attachment added.
     */
    public function withAttachment(Attachment $attachment): static
    {
        $clone = clone $this;
        $clone->attachments[] = $attachment;
        return $clone;
    }

    /**
     * Return a new instance with CC addresses.
     *
     * @param string|string[] $addresses
     */
    public function withCc(string|array $addresses): static
    {
        $clone = clone $this;
        $clone->cc = [...$clone->cc, ...$this->ensureStringArray($addresses)];
        return $clone;
    }

    /**
     * Return a new instance with BCC addresses.
     *
     * @param string|string[] $addresses
     */
    public function withBcc(string|array $addresses): static
    {
        $clone = clone $this;
        $clone->bcc = [...$clone->bcc, ...$this->ensureStringArray($addresses)];
        return $clone;
    }

    /**
     * Return a new instance with a custom header.
     */
    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * Return a new instance with Reply-To address.
     */
    public function withReplyTo(string $email, string $name = ''): static
    {
        return $this->withHeader('Reply-To', $name !== ''
            ? "{$name} <{$email}>"
            : $email);
    }

    private function ensureStringArray(string|array $input): array
    {
        if (is_string($input)) {
            return [$input];
        }
        return $input;
    }
}
