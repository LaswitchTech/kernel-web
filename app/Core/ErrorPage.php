<?php

namespace App\Core;

/**
 * Renders styled HTML error pages for HTTP status codes.
 *
 * Used by ErrorHandler, middleware (WebPermission), and controllers
 * that need to return a proper error page to browser users.
 */
class ErrorPage
{
    private static array $messages = [
        400  => 'Bad Request',
        401  => 'Unauthorized',
        403  => 'Forbidden',
        404  => 'Not Found',
        405  => 'Method Not Allowed',
        422  => 'Unprocessable Entity',
        423  => 'Locked',
        428  => 'Precondition Required',
        429  => 'Too Many Requests',
        500  => 'Internal Server Error',
        501  => 'Not Implemented',
        502  => 'Bad Gateway',
        503  => 'Service Unavailable',
    ];

    private static array $icons = [
        400  => 'bi-exclamation-triangle',
        401  => 'bi-lock',
        403  => 'bi-x-circle',
        404  => 'bi-search',
        405  => 'bi-arrow-repeat',
        422  => 'bi-exclamation-octagon',
        423  => 'bi-lock-fill',
        428  => 'bi-clock-history',
        429  => 'bi-hourglass-split',
        500  => 'bi-gear-wide-connected',
        501  => 'bi-box',
        502  => 'bi-power',
        503  => 'bi-exclamation-circle',
    ];

    /**
     * Render an error page and terminate.
     *
     * @param int               $status  HTTP status code
     * @param string|null       $message Human-readable detail
     * @param bool              $debug   Show exception trace
     * @param \Throwable|null   $e       Exception for trace display
     */
    public static function render(int $status, ?string $message = null, bool $debug = false, ?\Throwable $e = null): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        $heading = self::$messages[$status] ?? 'Error';
        $icon    = self::$icons[$status] ?? 'bi-exclamation-triangle';

        $trace     = '';
        $location  = '';
        if ($debug && $e) {
            $trace     = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
            $location  = htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8');
        }

        $suggestion = match ($status) {
            404  => 'The page you requested may have been moved or deleted.',
            405  => 'The HTTP method is not allowed for this endpoint.',
            429  => 'Please wait a moment before trying again.',
            503  => 'The service is temporarily unavailable. Please try again later.',
            default => null,
        };

        $action = match ($status) {
            401  => ['href' => '/auth/login', 'label' => 'Sign In'],
            403  => ['href' => '/', 'label' => 'Go Home'],
            404  => ['href' => '/', 'label' => 'Go Home'],
            500, 501, 502, 503 => ['href' => '/', 'label' => 'Go Home'],
            default => null,
        };

        extract([
            'status'   => $status,
            'heading'  => $heading,
            'icon'     => $icon,
            'message'  => $message,
            'debug'    => $debug,
            'trace'    => $trace,
            'location' => $location,
            'suggestion' => $suggestion,
            'action'   => $action,
        ]);

        $viewPath = __DIR__ . '/../Views/errors/show.php';
        require $viewPath;
    }
}
