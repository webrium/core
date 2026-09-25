<?php

declare(strict_types=1);

namespace Webrium;

use Throwable;

/**
 * File-based logger with PSR-3-style severity levels.
 *
 * Writes one line-delimited entry per call to a per-level, per-day file
 * (e.g. storage/logs/error_2026_09_25.txt). Pure logging concern only — it
 * has no knowledge of error handling, display, or HTTP responses; Debug
 * uses it, but application code can call it directly for any message
 * worth recording (Logger::info('user exported report', ['user_id' => 5])).
 */
class Logger
{
    public const EMERGENCY = 'emergency';
    public const ALERT     = 'alert';
    public const CRITICAL  = 'critical';
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const INFO      = 'info';
    public const DEBUG     = 'debug';

    private static $logPath = false;

    /**
     * Write a log entry at the given severity level.
     *
     * @param string $level   One of the Logger::* level constants (or any
     *                        custom string — the level only affects the
     *                        log filename).
     * @param string $message Human-readable log message.
     * @param array  $context Optional structured data appended to the entry.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        try {
            $date = date('Y_m_d');
            $time = date('H:i:s');
            $name = "{$level}_{$date}.txt";

            $entry = "\n" . str_repeat("=", 80);
            $entry .= "\n[{$date} {$time}] [" . strtoupper($level) . "] {$message}";

            if (!empty($context)) {
                $entry .= "\nContext: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $entry .= "\n" . str_repeat("=", 80) . "\n";

            self::write("{$level}_{$date}.txt", $entry);
        } catch (Throwable $e) {
            // If logging fails, fall back to PHP's own error log rather than
            // throwing out of what is usually a best-effort call site.
            error_log("Logger::log failed: " . $e->getMessage());
        }
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log(self::ALERT, $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log(self::NOTICE, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Directory log files are written to. Defaults to the framework's
     * "logs" directory (via Directory::path()), falling back to a local
     * ../../logs path when Directory isn't available (e.g. standalone use).
     */
    public static function getLogPath(): string
    {
        if (!self::$logPath) {
            self::$logPath = class_exists(Directory::class)
                ? Directory::path('logs')
                : __DIR__ . '/../../logs';

            if (!file_exists(self::$logPath)) {
                mkdir(self::$logPath, 0755, true);
            }
        }

        return self::$logPath;
    }

    /**
     * Override the log directory (e.g. for tests, or a custom deployment
     * layout).
     */
    public static function setLogPath(string $path): void
    {
        self::$logPath = $path;
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    private static function write(string $filename, string $content): void
    {
        $path = self::getLogPath() . '/' . $filename;
        file_put_contents($path, $content, FILE_APPEND | LOCK_EX);
    }
}
