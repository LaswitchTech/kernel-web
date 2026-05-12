<?php

namespace App\Core\Mail;

/**
 * File attachment for a mail message.
 *
 * Immutable value object — attachments are cloned into a new MailMessage
 * rather than mutated in place.
 */
readonly class Attachment
{
    public function __construct(
        public string $path,
        public string $name,
        public string $mimeType = '',
        public bool $inline = false,
    ) {
        if (str_contains($this->path, "\0")) {
            throw new \InvalidArgumentException('Attachment path must not contain null bytes');
        }
    }

    /**
     * Auto-detect mime type from file extension.
     */
    public static function file(string $path, ?string $name = null): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Attachment file not found: {$path}");
        }

        $name ??= basename($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeMap = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
        ];

        $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

        return new self($path, $name, $mimeType);
    }
}
