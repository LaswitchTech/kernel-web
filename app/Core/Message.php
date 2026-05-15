<?php

namespace App\Core;

/**
 * Immutable message value object for SMS/messaging.
 *
 * All phone numbers in E.164 format: +1XXXYYYZZZZ.
 * Body is plain text (SMS = 160 chars max — enforced by the transport).
 */
readonly class Message
{
    public function __construct(
        public string $to,
        public string $from,
        public string $body,
        public string $template = '',
        public array $context = [],
        public array $media = [],
    ) {}

    /**
     * Return a new message with an additional media attachment.
     */
    public function withMedia(string $url, ?string $mimeType = null): static
    {
        $attachment = ['url' => $url, 'mime_type' => $mimeType];
        $media = $this->media;
        $media[] = $attachment;
        return new static(
            to: $this->to,
            from: $this->from,
            body: $this->body,
            template: $this->template,
            context: $this->context,
            media: $media,
        );
    }

    /**
     * Return a new message with a different body (for template rendering).
     */
    public function withBody(string $body): static
    {
        return new static(
            to: $this->to,
            from: $this->from,
            body: $body,
            template: $this->template,
            context: $this->context,
            media: $this->media,
        );
    }
}
