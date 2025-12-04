<?php

namespace Webrium;

use Webrium\Url;
use Webrium\Debug;
use Webrium\File;
use Webrium\Directory;

/**
 * Application class that provides core functionality for the Webrium framework
 * Handles autoloading, environment configuration, request processing, and localization
 */
class App
{
    /**
     * Root path of the application
     *
     * @var string|false
     */
    private static $rootPath = false;

    /**
     * Current locale for localization
     *
     * @var string
     */
    private static $locale = 'en';

    /**
     * Cached environment variables
     *
     * @var array
     */
    private static $env = [];

    /**
     * Cached language strings
     *
     * @var array
     */
    private static $langStore = [];

    /**
     * Initialize the application with the given root directory
     *
     * @param string $dir Root directory path
     * @return void
     */
    public static function initialize(string $dir): void
    {
        self::setRootPath($dir);
        self::registerAutoloader();
        self::loadHelperFunctions();
        Url::confirmUrl();
    }

    /**
     * Register the class autoloader
     *
     * @return void
     */
    private static function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            // Skip classes not in our namespace
            if (strpos($class, 'Webrium\\') !== 0 && strpos($class, 'App\\') !== 0) {
                return;
            }

            // Convert namespace to path
            $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);

            // Handle App namespace (convert to lowercase 'app' directory)
            if (strpos($class, 'App\\') === 0) {
                $path = 'app' . substr($path, 3);
            }

            $filePath = self::getRootPath() . DIRECTORY_SEPARATOR . $path . '.php';

            if (File::exists($filePath)) {
                File::runOnce($filePath);
                return;
            }

            // Only throw error if in debug mode
            if (Debug::isDisplayingErrors()) {
                Debug::triggerError("Class '{$class}' not found at path: " . str_replace(self::getRootPath(), '', $filePath));
            }
        });
    }

    /**
     * Load helper functions file
     *
     * @return void
     */
    private static function loadHelperFunctions(): void
    {
        File::runOnce(__DIR__ . '/lib/Helper.php');
    }

    /**
     * Set the application root path
     *
     * @param string $dir Directory path
     * @return void
     */
    public static function setRootPath(string $dir): void
    {
        $realPath = realpath($dir);
        if ($realPath === false) {
            Debug::triggerError("Directory '{$dir}' does not exist or is not accessible");
            return;
        }

        self::$rootPath = rtrim(str_replace('\\', '/', $realPath), '/') . '/';
    }

    /**
     * Get the application root path
     *
     * @return string Root path
     */
    public static function getRootPath(): string
    {
        if (self::$rootPath === false) {
            Debug::triggerError('Root path has not been initialized. Call App::initialize() first.');
        }

        return rtrim(self::$rootPath, '/');
    }

    /**
     * Get request input data
     *
     * @param string|null $key Key to retrieve, null to get all input
     * @param mixed $default Default value if key is not found
     * @return mixed Request data or default value
     */
    public static function input(?string $key = null, $default = null)
    {
        static $requestData = null;

        // Parse request data only once
        if ($requestData === null) {
            $requestData = [];
            $method = Url::method();
            $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
            $isJson = strpos($contentType, 'application/json') !== false;

            // Handle GET requests
            if ($method === 'GET') {
                $requestData = $_GET;
            }
            // Handle POST/PUT/DELETE requests
            elseif (in_array($method, ['POST', 'PUT', 'DELETE'])) {
                if ($isJson) {
                    $jsonInput = file_get_contents('php://input');
                    $decoded = json_decode($jsonInput, true);

                    // Check for JSON decoding errors
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Debug::triggerError('Invalid JSON input: ' . json_last_error_msg(), false, false, 400);
                    } else {
                        $requestData = $decoded ?? [];
                    }
                } else {
                    $requestData = $_POST;
                }
            }
        }

        if ($key === null) {
            return $requestData;
        }

        return $requestData[$key] ?? $default;
    }

    /**
     * Return data with appropriate content type headers
     *
     * @param mixed $data Data to return (array, object, or string)
     * @param int $statusCode HTTP status code (default: 200)
     * @return void
     */
    public static function returnData($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);

        if (is_array($data) || is_object($data)) {
            header('Content-Type: application/json; charset=utf-8');
            $data = json_encode($data);

            // Check for JSON encoding errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                Debug::triggerError('JSON encoding error: ' . json_last_error_msg(), false, false, 500);
                $data = json_encode(['error' => 'Internal server error']);
            }
        }

        echo $data;
        exit;
    }

    /**
     * Get an environment variable
     *
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed Environment value or default
     */
    public static function env(string $key, $default = null)
    {
        // Load environment variables if not already loaded
        if (empty(self::$env)) {
            self::loadEnvironmentVariables();
        }

        return self::$env[$key] ?? $default;
    }

    /**
     * Load environment variables from .env file
     *
     * @return void
     */
    private static function loadEnvironmentVariables(): void
    {
        $envPath = self::getRootPath() . '/.env';

        if (!File::exists($envPath)) {
            // Don't throw error in production mode
            if (Debug::isDisplayingErrors()) {
                Debug::triggerError('Environment file .env not found at: ' . $envPath);
            }
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key-value pairs
            if (strpos($line, '=') !== false) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove quotes if present
                if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // Handle special values
                if ($value === 'true')
                    $value = true;
                elseif ($value === 'false')
                    $value = false;
                elseif ($value === 'null')
                    $value = null;

                self::$env[$name] = $value;
            }
        }
    }

    /**
     * Set the application locale
     *
     * @param string $locale Locale identifier (e.g., 'en', 'fa')
     * @return void
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    /**
     * Check if the current locale matches the given locale
     *
     * @param string $locale Locale to check
     * @return bool True if matches current locale
     */
    public static function isLocale(string $locale): bool
    {
        return $locale === self::$locale;
    }

    /**
     * Get the current application locale
     *
     * @return string Current locale
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Disable browser caching for the response
     *
     * @return void
     */
    public static function disableCache(): void
    {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    /**
     * Get a localized string
     *
     * @param string $key Translation key in format 'file.key'
     * @param array $replacements Key-value pairs to replace in the translation
     * @return string|false Translated string or false if not found
     */
    public static function trans(string $key, array $replacements = [])
    {
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            Debug::triggerError("Invalid translation key format: {$key}. Expected format 'file.key'");
            return false;
        }

        [$file, $translationKey] = $parts;
        $locale = self::getLocale();
        $cacheKey = "{$locale}.{$file}";

        // Load language file if not cached
        if (!isset(self::$langStore[$cacheKey])) {
            $langPath = Directory::path('langs');
            $filePath = "{$langPath}/{$locale}/{$file}.php";

            if (!File::exists($filePath)) {
                Debug::triggerError("Language file not found: {$filePath}");
                return false;
            }

            $translations = include $filePath;

            // Validate language file returns an array
            if (!is_array($translations)) {
                Debug::triggerError("Language file must return an array: {$filePath}");
                return false;
            }

            self::$langStore[$cacheKey] = $translations;
        }

        // Get translation with fallback to key if not found
        $translation = self::$langStore[$cacheKey][$translationKey] ?? $translationKey;

        // Replace placeholders
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace(":{$placeholder}", $value, $translation);
        }

        return $translation;
    }


    /**
     * Initialize the debugging system and execute the application's routing logic.
     * This method should be called after initialization to start processing the current request.
     *
     * @return void
     */
    public static function run()
    {
        Debug::initialize();
        Route::run();
    }
}