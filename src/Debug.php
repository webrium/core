<?php
namespace Webrium;

use Webrium\View;
use Webrium\Event;
use Webrium\Directory;
use Throwable;
use ErrorException;

/**
 * Complete Debug class for comprehensive error and exception handling
 * Supports PHP 8.0+
 * Handles: Errors, Fatal Errors, Exceptions, Parse Errors, Warnings, Notices
 */
class Debug
{
    private static $errorView = false;
    private static $writeErrors = true;
    private static $showErrors = true;
    private static $logPath = false;
    private static $hasError = false;
    private static $htmlOutput = '';
    private static $errorLine = 0;
    private static $errorFile = '';
    private static $errorString = '';
    private static $errorType = 'Error';
    private static $isInitialized = false;
    private static $forceJsonResponse = false; // Force JSON for API responses

    /**
     * Initialize comprehensive error handling
     */
    public static function initialize(): void
    {
        if (self::$isInitialized) {
            return;
        }

        self::$isInitialized = true;
        self::displayErrors(self::$showErrors);
        
        // Register all error handlers
        self::registerErrorHandler();
        self::registerExceptionHandler();
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // For PHP 8.0+, configure assertions
        // Note: zend.assertions can only be set in php.ini
        if (self::$showErrors) {
            @ini_set('assert.active', '1');
            @ini_set('assert.exception', '1');
        }
    }

    /**
     * Register custom error handler for all error types
     */
    private static function registerErrorHandler(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Skip if error reporting is disabled for this error level
            if (!(error_reporting() & $errno)) {
                return false;
            }

            // Convert error to exception for better handling
            $errorType = self::getErrorTypeName($errno);
            
            try {
                $errfile = View::getOrginalNameByHash($errfile);
            } catch (Throwable $e) {
                // If View class fails, use original file
            }

            self::triggerError($errstr, $errfile, $errline, 500, false, $errorType);
            
            // Don't execute PHP internal error handler
            return true;
        }, E_ALL);
    }

    /**
     * Register exception handler
     */
    private static function registerExceptionHandler(): void
    {
        set_exception_handler(function (Throwable $exception) {
            $file = $exception->getFile();
            
            try {
                $file = View::getOrginalNameByHash($file);
            } catch (Throwable $e) {
                // Use original file if View fails
            }

            self::triggerError(
                $exception->getMessage(),
                $file,
                $exception->getLine(),
                500,
                true,
                get_class($exception)
            );
        });
    }

    /**
     * Get human-readable error type name
     * Supports PHP 8.0+
     */
    private static function getErrorTypeName(int $errno): string
    {
        $errorTypes = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        return $errorTypes[$errno] ?? 'Unknown Error';
    }

    /**
     * Handle fatal errors and shutdown events
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [
            E_ERROR, 
            E_PARSE, 
            E_CORE_ERROR, 
            E_COMPILE_ERROR, 
            E_USER_ERROR,
            E_RECOVERABLE_ERROR
        ])) {
            $errorFile = $error['file'];
            
            try {
                $errorFile = View::getOrginalNameByHash($errorFile);
            } catch (Throwable $e) {
                // Use original file if View fails
            }

            $errorType = self::getErrorTypeName($error['type']);
            self::triggerError($error['message'], $errorFile, $error['line'], 500, true, $errorType);
        }
    }

    /**
     * Create and handle an error
     */
    public static function triggerError(
        string $message, 
        $file = false, 
        $line = false, 
        int $statusCode = 500, 
        bool $isFatal = false,
        string $errorType = 'Error'
    ): void {
        // Allow multiple non-fatal errors
        if (!$isFatal && self::$hasError) {
            // Log additional errors but don't stop execution
            if (self::$writeErrors) {
                $backtrace = self::getFormattedBacktraceForLogging($file);
                self::logError($message, $backtrace, $line, $statusCode, $errorType);
            }
            return;
        }

        // For fatal errors, only process once
        if ($isFatal && self::$hasError) {
            return;
        }

        self::$hasError = true;
        self::$errorString = $message;
        self::$errorLine = $line ?: 0;
        self::$errorFile = $file ?: '';
        self::$errorType = $errorType;

        // Get backtrace information
        $displayBacktrace = self::getFormattedBacktraceForDisplay($file);
        $logBacktrace = self::getFormattedBacktraceForLogging($file);

        // Trigger error event
        if (class_exists(Event::class)) {
            try {
                Event::emit('error', [
                    'message' => self::$errorString,
                    'line' => self::$errorLine,
                    'file' => self::getErrorFile(),
                    'type' => $errorType,
                    'is_fatal' => $isFatal
                ]);
            } catch (Throwable $e) {
                // Event system failed, continue without it
            }
        }

        // Log the error
        if (self::$writeErrors) {
            self::logError($message, $logBacktrace, $line, $statusCode, $errorType);
        }

        // Display the error
        if (self::$showErrors) {
            self::displayError($message, $displayBacktrace, $line, $statusCode, $errorType);
        } else {
            self::setStatusCode($statusCode);
        }

        // For non-fatal errors, don't terminate
        if (!$isFatal) {
            return;
        }

        // For fatal errors without display, show generic message
        if (!self::$showErrors) {
            self::showProductionError();
        }

        exit(1);
    }

    /**
     * Check if current request is an API request
     */
    private static function isApiRequest(): bool
    {
        // Force JSON mode
        if (self::$forceJsonResponse) {
            return true;
        }

        // CLI is never API
        if (PHP_SAPI === 'cli') {
            return false;
        }

        // Check Accept header
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($acceptHeader, 'application/json') !== false) {
            return true;
        }

        // Check Content-Type header (for POST requests)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            return true;
        }

        // Check if request is AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // Check common API URL patterns
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^/api/#i', $requestUri)) {
            return true;
        }

        // Check if POST/PUT/PATCH/DELETE request (likely API)
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Additional check: if not multipart form (file upload), likely API
            if (stripos($contentType, 'multipart/form-data') === false &&
                stripos($contentType, 'application/x-www-form-urlencoded') === false) {
                return true;
            }
        }

        return false;
    }
    private static function showProductionError(): void
    {
        if (PHP_SAPI === 'cli') {
            echo "An error occurred. Please check the logs.\n";
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Check if request expects JSON
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($acceptHeader, 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'An internal server error occurred',
                'status' => 500
            ]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #d32f2f; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>500 - Internal Server Error</h1>
        <p>An error occurred while processing your request.</p>
        <p>Please try again later or contact support if the problem persists.</p>
    </div>
</body>
</html>';
        }
    }

    /**
     * Display error to user
     */
    private static function displayError(
        string $message, 
        string $backtrace, 
        $line = false, 
        int $statusCode = 500,
        string $errorType = 'Error'
    ): void {
        self::setStatusCode($statusCode);

        // CLI output
        if (PHP_SAPI === 'cli') {
            $output = "\n\033[1;31m" . str_repeat("=", 70) . "\033[0m\n";
            $output .= "\033[1;31m{$errorType}: {$message}\033[0m\n";
            if ($line && self::$errorFile) {
                $output .= "\033[0;33mFile: " . self::$errorFile . ":{$line}\033[0m\n";
            }
            $output .= "\033[1;31m" . str_repeat("=", 70) . "\033[0m\n";
            
            if ($backtrace) {
                $cleanBacktrace = strip_tags($backtrace);
                $output .= "\nStack trace:\n{$cleanBacktrace}\n";
            }
            
            echo $output;
            return;
        }

        // Check if this is an API request
        if (self::isApiRequest()) {
            self::displayJsonError($message, $backtrace, $line, $statusCode, $errorType);
            return;
        }

        // Web HTML output
        $displayMessage = "<div style='background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;padding:15px;margin:20px;'>";
        $displayMessage .= "<h2 style='color:#721c24;margin:0 0 10px 0;'>{$errorType}</h2>";
        $displayMessage .= "<p style='color:#721c24;margin:0;'><strong>{$message}</strong></p>";
        
        if ($line && self::$errorFile) {
            $displayMessage .= "<p style='color:#856404;margin:10px 0 0 0;'><small>" . basename(self::$errorFile) . ":{$line}</small></p>";
        }
        
        if ($backtrace) {
            $displayMessage .= "<details style='margin-top:15px;'><summary style='cursor:pointer;color:#004085;'>Stack trace</summary>";
            $displayMessage .= "<div style='background:#fff;padding:10px;margin-top:10px;border-radius:4px;font-family:monospace;font-size:12px;'>{$backtrace}</div>";
            $displayMessage .= "</details>";
        }
        
        $displayMessage .= "</div>";

        self::$htmlOutput = $displayMessage;

        // Use custom error view if set
        if (self::$errorView && class_exists(View::class)) {
            try {
                $view = new View(self::$errorView, [
                    'error_message' => $message,
                    'error_line' => $line,
                    'error_file' => self::$errorFile,
                    'error_backtrace' => $backtrace,
                    'error_type' => $errorType,
                    'status_code' => $statusCode
                ]);
                $view->render();
                return;
            } catch (Throwable $e) {
                // Fall back to default display
            }
        }

        // Default display
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        header('Content-Type: text/html; charset=utf-8');
        echo self::$htmlOutput;
    }

    /**
     * Display error as JSON for API requests
     */
    private static function displayJsonError(
        string $message,
        string $backtrace,
        $line = false,
        int $statusCode = 500,
        string $errorType = 'Error'
    ): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');

        $errorData = [
            'success' => false,
            'error' => $errorType,
            'message' => $message,
            'status' => $statusCode
        ];

        // Add debug info if showing errors
        if (self::$showErrors) {
            $errorData['debug'] = [
                'file' => self::$errorFile,
                'line' => $line,
                'trace' => array_filter(array_map(function($frame) {
                    if (!isset($frame['file'])) {
                        return null;
                    }
                    
                    // Skip Webrium core files
                    if (strpos($frame['file'], DIRECTORY_SEPARATOR . 'webrium' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'src') !== false) {
                        return null;
                    }

                    return [
                        'file' => $frame['file'],
                        'line' => $frame['line'] ?? 0,
                        'function' => (isset($frame['class']) ? $frame['class'] . $frame['type'] : '') . ($frame['function'] ?? '')
                    ];
                }, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)))
            ];
        }

        echo json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Log error to file
     */
    private static function logError(
        string $message, 
        string $backtrace, 
        $line = false, 
        int $statusCode = 500,
        string $errorType = 'Error'
    ): void {
        try {
            $date = date('Y_m_d');
            $time = date('H:i:s');
            $name = "error_{$date}.txt";

            $logMessage = "\n" . str_repeat("=", 80);
            $logMessage .= "\n[{$date} {$time}] [{$statusCode}] {$errorType}";
            $logMessage .= "\nMessage: {$message}";
            
            if ($line) {
                $logMessage .= "\nLine: {$line}";
            }
            
            if ($backtrace) {
                $logMessage .= "\n\nStack trace:\n{$backtrace}";
            }
            
            $logMessage .= "\n" . str_repeat("=", 80) . "\n";

            $logPath = self::getLogPath();
            self::writeLogFile("{$logPath}/{$name}", $logMessage);
        } catch (Throwable $e) {
            // If logging fails, try to write to error_log
            error_log("Debug::logError failed: " . $e->getMessage());
        }
    }

    /**
     * Get formatted backtrace for logging
     */
    private static function getFormattedBacktraceForLogging($initialFile): string
    {
        $trace = [];
        
        if ($initialFile !== false) {
            $trace[] = "  → {$initialFile}";
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $count = 1;
        
        foreach ($backtrace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }

            // Skip Webrium core files
            if (strpos($frame['file'], DIRECTORY_SEPARATOR . 'webrium' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'src') !== false) {
                continue;
            }

            $function = '';
            if (isset($frame['class'])) {
                $function = $frame['class'] . $frame['type'] . $frame['function'] . '()';
            } elseif (isset($frame['function'])) {
                $function = $frame['function'] . '()';
            }

            $trace[] = sprintf(
                "  #%d %s%s",
                $count++,
                $frame['file'] . ':' . ($frame['line'] ?? 0),
                $function ? " - {$function}" : ''
            );
        }

        return implode("\n", $trace);
    }

    /**
     * Get formatted backtrace for display
     */
    private static function getFormattedBacktraceForDisplay($initialFile): string
    {
        $trace = [];
        
        if ($initialFile !== false) {
            $trace[] = "<span style='color:#d32f2f;font-weight:bold;'>→ {$initialFile}</span>";
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $count = 1;
        
        foreach ($backtrace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }

            // Skip Webrium core files
            if (strpos($frame['file'], DIRECTORY_SEPARATOR . 'webrium' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'src') !== false) {
                continue;
            }

            $file = $frame['file'];
            $line = $frame['line'] ?? 0;
            $isVendor = strpos($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
            
            $function = '';
            if (isset($frame['class'])) {
                $function = " <span style='color:#666;'>→ {$frame['class']}{$frame['type']}{$frame['function']}()</span>";
            } elseif (isset($frame['function'])) {
                $function = " <span style='color:#666;'>→ {$frame['function']}()</span>";
            }

            $style = $isVendor ? "color:#999;" : "color:#000;font-weight:bold;";
            $trace[] = "<span style='{$style}'>#{$count} {$file}:{$line}{$function}</span>";
            $count++;
        }

        return implode("<br>", $trace);
    }

    /**
     * Get log path
     */
    private static function getLogPath(): string
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
     * Write to log file safely
     */
    private static function writeLogFile(string $filePath, string $content): void
    {
        file_put_contents($filePath, $content, FILE_APPEND | LOCK_EX);
    }

    /**
     * Set HTTP status code
     */
    public static function setStatusCode(int $code): void
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($code);
        }
    }

    /**
     * Toggle error display
     */
    public static function displayErrors(bool $status): void
    {
        ini_set('display_errors', $status ? '1' : '0');
        ini_set('display_startup_errors', $status ? '1' : '0');
        error_reporting($status ? E_ALL : 0);
        self::$showErrors = $status;
    }

    // Getter methods
    public static function hasError(): bool { return self::$hasError; }
    public static function getHtmlOutput(): string { return self::$htmlOutput; }
    public static function getErrorString(): string { return self::$errorString; }
    public static function getErrorLine(): int { return self::$errorLine; }
    public static function getErrorFile(): string { return self::$errorFile; }
    public static function isDisplayingErrors(): bool { return self::$showErrors; }

    // Configuration methods
    public static function enableErrorLogging(bool $status): void { self::$writeErrors = $status; }
    public static function enableErrorDisplay(bool $status): void { self::displayErrors($status); }
    public static function setErrorView(string $viewPath): void { self::$errorView = $viewPath; }
    public static function setLogPath(string $path): void 
    { 
        self::$logPath = $path;
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Force JSON response mode (useful for API-only applications)
     */
    public static function forceJsonResponse(bool $status = true): void
    {
        self::$forceJsonResponse = $status;
    }

    /**
     * Trigger 404 error
     */
    public static function notFound(string $message = 'Page not found', $file = false, $line = false): void
    {
        self::triggerError($message, $file, $line, 404, false, 'Not Found');
    }

    /**
     * Manual exception throwing for testing
     */
    public static function throwException(string $message): void
    {
        throw new ErrorException($message);
    }
}