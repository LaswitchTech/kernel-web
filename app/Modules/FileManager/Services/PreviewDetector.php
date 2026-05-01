<?php

namespace App\Modules\FileManager\Services;

/**
 * File type classifier for the File Manager preview feature.
 *
 * Classifies a file as 'text', 'image', 'pdf', or 'none'.
 *
 * ## Detection strategy
 *
 * 1. MIME type (when provided) — most reliable on a server with accurate MIME detection.
 * 2. File extension — fallback when MIME is unavailable or returns a generic type.
 *
 * ## Safety rules
 *
 *   - text/html and text/javascript are NEVER classified as 'text' for inline display.
 *     Callers must not render these as active content. They will return 'none' from the
 *     MIME path, but may return 'text' from the extension path for .html/.js files so that
 *     the preview page can display them as escaped plain text (not executed).
 *   - image/svg+xml is excluded from the 'image' category — SVG can contain active script.
 *     SVGs with a .svg extension fall through to 'none'.
 *   - 'none' is always the conservative default when the type is uncertain.
 *
 * ## Text size limit
 *
 * Text preview is capped at TEXT_SIZE_LIMIT bytes. Controllers should check the file
 * size before reading and treat oversized files as not-previewable for text.
 *
 * This class has no filesystem access and no NetMon-specific dependencies.
 * It can be reused in any application.
 */
class PreviewDetector
{
    /**
     * Maximum byte size for text preview content.
     * Files larger than this should be refused for text rendering.
     */
    const TEXT_SIZE_LIMIT = 524288; // 512 KB

    /**
     * Explicit safe MIME types rendered as escaped plain text.
     * text/html and text/javascript are intentionally absent.
     */
    private const SAFE_TEXT_MIMES = [
        'text/plain',
        'text/csv',
        'text/markdown',
        'text/x-markdown',
        'text/x-log',
        'text/x-sh',
        'text/x-yaml',
        'text/yaml',
        'application/json',
        'application/xml',
        'text/xml',
        'application/x-yaml',
        'application/toml',
    ];

    /**
     * Safe image MIME types for inline browser rendering.
     * image/svg+xml is intentionally absent (SVG can embed active script).
     */
    private const SAFE_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/tiff',
    ];

    /**
     * File extensions that map to text preview.
     * Includes .html/.htm and code extensions — these are displayed as escaped
     * plain text, never executed or rendered as active markup.
     */
    private const TEXT_EXTENSIONS = [
        'txt', 'log', 'csv',
        'json', 'xml', 'yaml', 'yml', 'toml',
        'md', 'markdown',
        'ini', 'conf', 'cfg', 'env',
        'sql',
        'sh', 'bash',
        'py', 'rb', 'php', 'java', 'c', 'cpp', 'h',
        'js', 'ts', 'jsx', 'tsx',
        'css', 'less', 'scss',
        'html', 'htm',
    ];

    /**
     * File extensions that map to safe image rendering.
     * .svg is intentionally absent.
     */
    private const IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Classify a file as 'text', 'image', 'pdf', or 'none'.
     *
     * MIME type is used when provided; extension is the fallback.
     * Conservative: when in doubt, returns 'none'.
     *
     * @param  string      $filename  Basename of the file (used for extension fallback).
     * @param  string|null $mimeType  MIME type string, with or without parameters.
     * @return string  'text' | 'image' | 'pdf' | 'none'
     */
    public static function classify(string $filename, ?string $mimeType = null): string
    {
        if ($mimeType !== null) {
            $result = self::fromMime($mimeType);
            if ($result !== 'none') {
                return $result;
            }
        }

        return self::fromExtension($filename);
    }

    /**
     * Return true when classify() returns something other than 'none'.
     */
    public static function isPreviewable(string $filename, ?string $mimeType = null): bool
    {
        return self::classify($filename, $mimeType) !== 'none';
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function fromMime(string $mime): string
    {
        // Normalize: strip charset / boundary parameters, lowercase.
        $mime = strtolower(trim($mime));
        if (($pos = strpos($mime, ';')) !== false) {
            $mime = trim(substr($mime, 0, $pos));
        }

        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        if (in_array($mime, self::SAFE_IMAGE_MIMES, true)) {
            return 'image';
        }

        if (in_array($mime, self::SAFE_TEXT_MIMES, true)) {
            return 'text';
        }

        // Catch remaining text/* types — but never html or javascript.
        if (
            str_starts_with($mime, 'text/')
            && $mime !== 'text/html'
            && $mime !== 'text/javascript'
        ) {
            return 'text';
        }

        return 'none';
    }

    private static function fromExtension(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return 'pdf';
        }

        if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            return 'image';
        }

        if (in_array($ext, self::TEXT_EXTENSIONS, true)) {
            return 'text';
        }

        return 'none';
    }
}
