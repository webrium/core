<?php
namespace Webrium;

use Webrium\View;
use Webrium\Event;
use Webrium\Directory;

/**
 * Debug class for error handling, logging and displaying errors in Webrium framework
 */
class Debug
{
    /**
     * Custom error view template path
     * If set, this view will be used to display errors instead of default output
     *
     * @var string|bool
     */
    private static $errorView = false;

    /**
     * Flag to determine if errors should be written to log files
     *
     * @var bool
     */
    private static $writeErrors = true;

    /**
     * Flag to determine if errors should be displayed to the user
     *
     * @var bool
     */
    private static $showErrors = true;

    /**
     * Path where error logs will be stored
     *
     * @var string|bool
     */
    private static $logPath = false;

    /**
     * Flag to indicate if an error has occurred
     *
     * @var bool
     */
    private static $hasError = false;

    /**
     * HTML content of the error message to be displayed
     *
     * @var string
     */
    private static $htmlOutput = '';

    /**
     * Line number where the error occurred
     *
     * @var int
     */
    private static $errorLine = 0;

    /**
     * File path where the error occurred
     *
     * @var string
     */
    private static $errorFile = '';

    /**
     * Error message string
     *
     * @var string
     */
    private static $errorString = '';

    /**
     * Initialize error handling for the application
     *
     * @return void
     */
    public static function initialize(): void
    {
        self::displayErrors(self::$showErrors);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Get the current error status
     *
     * @return bool True if an error has occurred, false otherwise
     */
    public static function hasError(): bool
    {
        return self::$hasError;
    }

    /**
     * Get the HTML output for the error
     *
     * @return string HTML content of the error message
     */
    public static function getHtmlOutput(): string
    {
        return self::$htmlOutput;
    }

    /**
     * Toggle display of errors
     *
     * @param bool $status Whether to display errors or not
     * @return void
     */
    public static function displayErrors(bool $status): void
    {
        ini_set('display_errors', $status ? '1' : '0');
        ini_set('display_startup_errors', $status ? '1' : '0');

        if ($status) {
            error_reporting(E_ALL);
        } else {
            error_reporting(0);
        }

        self::registerErrorHandler();
    }

    /**
     * Register the custom error handler
     *
     * @return void
     */
    private static function registerErrorHandler(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Skip if error reporting is disabled for this error level
            if (!(error_reporting() & $errno)) {
                return;
            }

            $errfile = View::getOrginalNameByHash($errfile);
            self::triggerError($errstr, $errfile, $errline, 500, false);
            
            // Don't execute PHP internal error handler
            return true;
        }, E_ALL);
    }

    /**
     * Handle fatal errors and shutdown events
     *
     * @return void
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $errorFile = View::getOrginalNameByHash($error['file']);
            self::triggerError($error['message'], $errorFile, $error['line'], 500, true);
        }
    }

    /**
     * Create and handle an error
     *
     * @param string $message Error message
     * @param string|bool $file File path where error occurred
     * @param int|bool $line Line number where error occurred
     * @param int $statusCode HTTP status code to return
     * @param bool $isFatal Whether this is a fatal error
     * @return void
     */
    public static function triggerError(string $message, $file = false, $line = false, int $statusCode = 500, bool $isFatal = false): void
    {
        // Skip if we've already processed an error
        if (self::$hasError) {
            return;
        }

        self::$hasError = true;
        self::$errorString = $message;
        self::$errorLine = $line ?: 0;
        self::$errorFile = $file ?: '';

        // Get proper backtrace information
        $displayBacktrace = self::getFormattedBacktraceForDisplay($file);
        $logBacktrace = self::getFormattedBacktraceForLogging($file);

        // Trigger error event
        Event::emit('error', [
            'message' => self::$errorString,
            'line' => self::$errorLine,
            'file' => self::getErrorFile(),
            'is_fatal' => $isFatal
        ]);

        // Log the error if enabled
        if (self::$writeErrors) {
            self::logError($message, $logBacktrace, $line, $statusCode);
        }

        // Display the error if enabled
        if (self::$showErrors) {
            self::displayError($message, $displayBacktrace, $line, $statusCode);
        } else {
            // Even if not showing errors, still set the proper HTTP status code
            self::setStatusCode($statusCode);
        }

        // For non-fatal errors, don't terminate script execution
        if (!$isFatal) {
            return;
        }

        // For fatal errors in production without error display, show generic message
        if (!self::$showErrors) {
            if (PHP_SAPI !== 'cli') {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Content-Type: application/json');
                echo json_encode(['error' => 'An internal server error occurred']);
            }
        }

        exit(1);
    }

    /**
     * Get error message string
     *
     * @return string
     */
    public static function getErrorString(): string
    {
        return self::$errorString;
    }

    /**
     * Get error line number
     *
     * @return int
     */
    public static function getErrorLine(): int
    {
        return self::$errorLine;
    }

    /**
     * Get error file path
     *
     * @return string
     */
    public static function getErrorFile(): string
    {
        return self::$errorFile;
    }

    /**
     * Get formatted backtrace for logging
     *
     * @param string|bool $initialFile Initial file to include in trace
     * @return string Formatted backtrace string
     */
    private static function getFormattedBacktraceForLogging($initialFile): string
    {
        $trace = [];
        
        if ($initialFile !== false) {
            $trace[] = "#{$initialFile}";
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            // Skip Webrium core files and frames without file information
            if (!isset($frame['file']) || strpos($frame['file'], DIRECTORY_SEPARATOR . 'webrium' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'src') !== false) {
                continue;
            }

            $trace[] = "#{$frame['file']}:({$frame['line']})";
        }

        return implode("\n", $trace);
    }

    /**
     * Get formatted backtrace for display
     *
     * @param string|bool $initialFile Initial file to include in trace
     * @return string HTML formatted backtrace
     */
    private static function getFormattedBacktraceForDisplay($initialFile): string
    {
        $trace = [];
        
        if ($initialFile !== false) {
            $trace[] = "<span><b><span style=\"color:red\">#</span> {$initialFile}</b></span>";
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            // Skip Webrium core files and frames without file information
            if (!isset($frame['file']) || strpos($frame['file'], DIRECTORY_SEPARATOR . 'webrium' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'src') !== false) {
                continue;
            }

            $file = $frame['file'];
            $line = $frame['line'];
            $displayFile = (strpos($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) 
                ? "<b># {$file}:({$line})</b>" 
                : "<span style=\"color: #3e3e3e;\"># {$file}:({$line})</span>";
            
            $trace[] = $displayFile;
        }

        return implode("<br>", $trace);
    }

    /**
     * Log an error to a file
     *
     * @param string $message Error message
     * @param string $backtrace Backtrace information
     * @param int|bool $line Line number
     * @param int $statusCode HTTP status code
     * @return void
     */
    private static function logError(string $message, string $backtrace, $line = false, int $statusCode = 500): void
    {
        $date = date('Y_m_d');
        $time = date('H_i_s');
        $name = "error_{$date}.txt";

        $logMessage = "## {$date} {$time} [{$statusCode}] Error: {$message}";
        
        if ($line) {
            $logMessage .= " Line: {$line}";
        }
        
        if ($backtrace) {
            $logMessage .= "\nStack trace:\n{$backtrace}";
        }

        $logPath = self::getLogPath();
        self::writeLogFile("{$logPath}/{$name}", $logMessage);
    }

    /**
     * Get the log path, initializing it if not set
     *
     * @return string
     */
    private static function getLogPath(): string
    {
        if (!self::$logPath) {
            self::$logPath = Directory::path('logs');
            
            // Ensure the logs directory exists
            if (!file_exists(self::$logPath)) {
                mkdir(self::$logPath, 0755, true);
            }
        }
        
        return self::$logPath;
    }

    /**
     * Display an error to the user
     *
     * @param string $message Error message
     * @param string $backtrace HTML formatted backtrace
     * @param int|bool $line Line number
     * @param int $statusCode HTTP status code
     * @return void
     */
    private static function displayError(string $message, string $backtrace, $line = false, int $statusCode = 500): void
    {
        $displayMessage = "Error: {$message}";
        
        if ($line && self::$errorFile) {
            $displayMessage .= " (" . basename(self::$errorFile) . ":{$line})";
        }
        
        if ($backtrace) {
            $displayMessage .= "<br>Stack trace:<br>{$backtrace}";
        }

        self::$htmlOutput = $displayMessage;
        self::setStatusCode($statusCode);

        // Use custom error view if set
        if (self::$errorView && class_exists(View::class)) {
            try {
                $view = new View(self::$errorView, [
                    'error_message' => $message,
                    'error_line' => $line,
                    'error_file' => self::$errorFile,
                    'error_backtrace' => $backtrace,
                    'status_code' => $statusCode
                ]);
                $view->render();
                exit;
            } catch (\Exception $e) {
                // Fall back to default error display if view rendering fails
            }
        }

        // Default error display
        if (PHP_SAPI !== 'cli') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: text/html; charset=utf-8');
        }
        
        echo self::$htmlOutput;
    }

    /**
     * Trigger a 404 Not Found error
     *
     * @param string $message Custom error message
     * @param string|bool $file File path
     * @param int|bool $line Line number
     * @return void
     */
    public static function notFound(string $message = 'Page not found', $file = false, $line = false): void
    {
        self::triggerError($message, $file, $line, 404);
    }

    /**
     * Enable or disable error logging to files
     *
     * @param bool $status Whether to log errors
     * @return void
     */
    public static function enableErrorLogging(bool $status): void
    {
        self::$writeErrors = $status;
    }

    /**
     * Enable or disable displaying errors to users
     *
     * @param bool $status Whether to display errors
     * @return void
     */
    public static function enableErrorDisplay(bool $status): void
    {
        self::$showErrors = $status;
    }

    /**
     * Get whether errors are displayed to users
     *
     * @return bool
     */
    public static function isDisplayingErrors(): bool
    {
        return self::$showErrors;
    }

    /**
     * Set custom error view template
     *
     * @param string $viewPath Path to the error view template
     * @return void
     */
    public static function setErrorView(string $viewPath): void
    {
        self::$errorView = $viewPath;
    }

    /**
     * Set the path for error logs
     *
     * @param string $path Path to logs directory
     * @return void
     */
    public static function setLogPath(string $path): void
    {
        self::$logPath = $path;
        
        // Ensure directory exists
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Set HTTP response status code
     *
     * @param int $code HTTP status code
     * @return void
     */
    public static function setStatusCode(int $code): void
    {
        http_response_code($code);
    }

    /**
     * Write content to a log file
     *
     * @param string $filePath Path to the log file
     * @param string $content Content to write
     * @return void
     */
    private static function writeLogFile(string $filePath, string $content): void
    {
        // Use file_put_contents for simplicity and safety
        file_put_contents($filePath, $content . "\r\n", FILE_APPEND | LOCK_EX);
    }
}