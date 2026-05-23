<?php

namespace App\Services;

/**
 * APP_DEBUG-gated debug logger that writes to the existing Audit Log table.
 *
 * Usage:
 *   \App\Services\DebugAuditLogger::auth($userId, 'login.start', ['ip' => $ip]);
 *   DebugAuditLogger::config($userId, 'resolved', ['debug' => true, 'console' => false]);
 *   DebugAuditLogger::plugin($userId, 'loaded', ['name' => 'notes']);
 *   DebugAuditLogger::route($userId, 'matched', ['path' => '/admin']);
 *   DebugAuditLogger::generic($userId, 'debug.my.category', ['key' => 'value']);
 */
class DebugAuditLogger
{
    private bool $enabled = false;
    private mixed $container = null;

    /** Keys whose values are redacted (case-insensitive partial match). */
    private array $redactedKeys = [
        'password', 'passwd', 'secret', 'token', 'key',
        'cookie', 'authorization', 'csrf', 'session', 'recovery',
    ];

    /** Maximum serialized payload size (JSON bytes) before truncation. */
    private int $maxPayloadSize = 2000;

    public function __construct(array $appConfig, mixed $container = null)
    {
        $this->enabled = (bool) ($appConfig['debug'] ?? false);
        $this->container = $container;
    }

    // --- Convenience methods (category auto-set) ---

    public static function auth(?int $userId, string $action, array $meta = []): void
    {
        self::getInstance()->log($userId, "auth.{$action}", 'debug', $meta);
    }

    public static function config(?int $userId, string $action, array $meta = []): void
    {
        self::getInstance()->log($userId, "config.{$action}", 'debug', $meta);
    }

    public static function plugin(?int $userId, string $action, array $meta = []): void
    {
        self::getInstance()->log($userId, "plugin.{$action}", 'debug', $meta);
    }

    public static function route(?int $userId, string $action, array $meta = []): void
    {
        self::getInstance()->log($userId, "route.{$action}", 'debug', $meta);
    }

    public static function generic(?int $userId, string $action, array $meta = []): void
    {
        self::getInstance()->log($userId, $action, 'debug', $meta);
    }

    // --- Core ---

    private static function getInstance(): self
    {
        static $instance = null;
        if (!$instance) {
            $instance = new self(['debug' => false]);
        }
        return $instance;
    }

    /**
     * Write a debug entry to the audit log table.
     *
     * When debug is disabled, this is a no-op.
     *
     * The metadata is stored directly — AuditLogRepository handles JSON encoding.
     * A '_truncated' flag is added if the payload was too large.
     */
    public function log(?int $userId, string $action, string $entityType, array $meta): void
    {
        if (!$this->enabled) {
            return;
        }

        $sanitized = $this->sanitize($meta);

        // Check size by temporarily encoding.
        $checkSize = strlen(json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($checkSize > $this->maxPayloadSize) {
            $sanitized['_truncated'] = true;
            // Trim array values to stay within limit.
            foreach ($sanitized as $k => $v) {
                if ($k === '_truncated') continue;
                if (is_string($v) && strlen($v) > 500) {
                    $sanitized[$k] = substr($v, 0, 500) . '...';
                } elseif (is_array($v)) {
                    $sanitized[$k] = array_slice($v, 0, 10, true);
                }
            }
        }

        try {
            $db = $this->resolveDb();
            if ($db === null) {
                return;
            }
            (new \App\Models\AuditLogRepository(
                new DebugAuditLogger_DBConnectionAdapter($db)
            ))->log(
                $userId,
                'debug.' . $action,
                $entityType,
                0, // entity_id — not applicable for debug entries
                $sanitized
            );
        } catch (\Throwable $e) {
            // Never break the application for a debug logging failure.
        }
    }

    /** Resolve DB from container (lazy, to avoid circular issues during bootstrap). */
    private function resolveDb(): mixed
    {
        static $db = null;
        if ($db !== null) {
            return $db;
        }

        if ($this->container !== null && is_object($this->container)
            && method_exists($this->container, 'has') && method_exists($this->container, 'get')) {
            if ($this->container->has('db')) {
                $db = $this->container->get('db');
                return $db;
            }
        }

        return null;
    }

    // --- Sanitization ---

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->sanitizeValue($k, $v);
            }
            return $result;
        }

        if (is_object($value)) {
            return $this->sanitize((array) $value);
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        $lowerKey = strtolower($key);

        foreach ($this->redactedKeys as $pattern) {
            if (str_contains($lowerKey, $pattern)) {
                // For scalar values, replace with placeholder.
                if (is_scalar($value) || $value === null) {
                    return '[redacted]';
                }
                // For arrays/objects, sanitize recursively (may contain nested safe data).
                return $this->sanitize($value);
            }
        }

        return $this->sanitize($value);
    }
}

/**
 * Thin adapter so DebugAuditLogger can write through whatever DB binding
 * is in the container. Defined in global namespace — autoloader won't find it.
 */
class DebugAuditLogger_DBConnectionAdapter implements \App\Core\DatabaseInterface
{
    public function __construct(private mixed $db) {}

    public function pdo(): \PDO { return $this->db->pdo(); }
    public function fetch(string $sql, array $params = []): array {
        return $this->db->fetch($sql, $params);
    }
    public function fetchOne(string $sql, array $params = []): ?array {
        return $this->db->fetchOne($sql, $params);
    }
    public function execute(string $sql, array $bindings = []): int {
        return $this->db->execute($sql, $bindings);
    }
    public function beginTransaction(): bool { return $this->db->beginTransaction(); }
    public function commit(): bool { return $this->db->commit(); }
    public function rollBack(): bool { return $this->db->rollBack(); }
    public function lastInsertId(): string { return $this->db->lastInsertId(); }
}
