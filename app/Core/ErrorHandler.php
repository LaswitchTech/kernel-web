<?php

namespace App\Core;

class ErrorHandler
{
    private Logger $logger;
    private bool   $debug;

    public function __construct(Logger $logger, bool $debug = false)
    {
        $this->logger = $logger;
        $this->debug  = $debug;
    }

    /**
     * Install exception, error, and shutdown handlers.
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    // -------------------------------------------------------------------------
    // Exception handler
    // -------------------------------------------------------------------------

    public function handleException(\Throwable $e): void
    {
        $status  = $this->statusFromException($e);
        $message = $e->getMessage();

        $this->logger->error($message, [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $this->debug ? $e->getTraceAsString() : null,
        ]);

        $this->respond($status, $message, $e);
    }

    // -------------------------------------------------------------------------
    // Error handler — converts PHP errors to ErrorException where appropriate
    // -------------------------------------------------------------------------

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Respect the @ operator
        if (!(error_reporting() & $severity)) {
            return false;
        }

        // Convert recoverable errors into exceptions
        $throw = E_ERROR | E_WARNING | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR;

        if ($severity & $throw) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }

        // Log non-thrown notices / deprecations but do not interrupt execution
        $this->logger->warning($message, [
            'severity' => $severity,
            'file'     => $file,
            'line'     => $line,
        ]);

        return true;
    }

    // -------------------------------------------------------------------------
    // Shutdown handler — catches fatal errors missed by set_error_handler
    // -------------------------------------------------------------------------

    public function handleShutdown(): void
    {
        $err = error_get_last();

        $fatals = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

        if ($err && ($err['type'] & $fatals)) {
            $this->logger->error('Fatal error: ' . $err['message'], [
                'file' => $err['file'],
                'line' => $err['line'],
            ]);

            // Headers may already be sent on a fatal during output — best effort
            if (!headers_sent()) {
                $this->respond(500, 'Internal Server Error', null);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Map exception types to HTTP status codes.
     */
    private function statusFromException(\Throwable $e): int
    {
        if ($e instanceof \InvalidArgumentException
            || $e instanceof \OverflowException
            || $e instanceof \UnderflowException
            || $e instanceof \RangeException
            || $e instanceof \OutOfRangeException
            || $e instanceof \LengthException
        ) {
            return 400;
        }
        if ($e instanceof \DomainException) {
            return 422;
        }
        if ($e instanceof \OutOfBoundsException) {
            return 404;
        }
        if ($e instanceof \UnexpectedValueException) {
            return 422;
        }
        return 500;
    }

    /**
     * Decide whether this request expects a JSON response.
     * Criteria: Accept header contains application/json, or URI starts with /api/.
     */
    private function isApiRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';

        return str_contains($accept, 'application/json')
            || str_starts_with($uri, '/api/');
    }

    /**
     * Send the appropriate response format and terminate.
     */
    private function respond(int $status, string $message, ?\Throwable $e): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }

        if ($this->isApiRequest()) {
            $this->respondJson($status, $message, $e);
        } else {
            $this->respondHtml($status, $message, $e);
        }
    }

    private function respondJson(int $status, string $message, ?\Throwable $e): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $body = [
            'error'  => true,
            'status' => $status,
            'message' => $this->debug ? $message : $this->genericMessage($status),
        ];

        if ($this->debug && $e !== null) {
            $body['exception'] = get_class($e);
            $body['file']      = $e->getFile();
            $body['line']      = $e->getLine();
            $body['trace']     = explode("\n", $e->getTraceAsString());
        }

        echo json_encode($body);
    }

    private function respondHtml(int $status, string $message, ?\Throwable $e): void
    {
        ErrorPage::render($status, $this->debug ? $message : null, $this->debug, $e);
    }

    private function genericMessage(int $status): string
    {
        return $this->genericMessageFromStatus($status);
    }

    private function genericMessageFromStatus(int $status): string
    {
        if ($status === 400) {
            return 'Bad Request';
        }
        if ($status === 401) {
            return 'Unauthorized';
        }
        if ($status === 403) {
            return 'Forbidden';
        }
        if ($status === 404) {
            return 'Not Found';
        }
        if ($status === 405) {
            return 'Method Not Allowed';
        }
        if ($status === 422) {
            return 'Unprocessable Entity';
        }
        if ($status === 500) {
            return 'Internal Server Error';
        }
        return 'Error';
    }
}
