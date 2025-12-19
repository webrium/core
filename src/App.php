<?php

namespace Webrium;

use Webrium\Url;
use Webrium\Header;
use Webrium\Debug;
use Webrium\File;
use Webrium\Directory;

/**
 * Application class that provides core functionality for the Webrium framework
 * Handles autoloading, environment configuration, request processing, localization, and CORS
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
     * CORS configuration
     *
     * @var array
     */
    private static $corsConfig = [
        'allowed_origins' => [],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
        'allow_credentials' => false,
        'max_age' => 86400,
        'expose_headers' => [],
    ];

    /**
     * Whether CORS has been initialized
     *
     * @var bool
     */
    private static $corsEnabled = false;

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
        Url::enforce();
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
            if (strpos($class, 'Webrium\\') !== 0 && strpos(strtolower($class), 'app\\') !== 0) {
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
     * Configure CORS settings.
     * 
     * This method allows you to customize CORS configuration beyond just origins.
     *
     * @param array $config CORS configuration with keys:
     *                      - allowed_origins: array of allowed origins
     *                      - allowed_methods: array of HTTP methods
     *                      - allowed_headers: array of allowed headers
     *                      - allow_credentials: bool
     *                      - max_age: int (seconds)
     *                      - expose_headers: array of exposed headers
     * @return void
     */
    public static function configureCors(array $config): void
    {
        self::$corsConfig = array_merge(self::$corsConfig, $config);
        
        // Validate configuration
        if (self::$corsConfig['allow_credentials'] && in_array('*', self::$corsConfig['allowed_origins'])) {
            Debug::triggerError('CORS: Cannot use wildcard (*) origin with credentials enabled. This is a security violation.');
        }
    }

    /**
     * Set allowed origins for CORS.
     *
     * @param array|string $origins Single origin or array of allowed origins
     * @return void
     */
    public static function setCorsOrigins($origins): void
    {
        if (is_string($origins)) {
            $origins = [$origins];
        }

        // Normalize origins (remove trailing slashes)
        self::$corsConfig['allowed_origins'] = array_map(function($origin) {
            return rtrim($origin, '/');
        }, $origins);

        // Validate configuration
        if (self::$corsConfig['allow_credentials'] && in_array('*', self::$corsConfig['allowed_origins'])) {
            Debug::triggerError('CORS: Cannot use wildcard (*) origin with credentials enabled. Set allow_credentials to false.');
        }
    }

    /**
     * Add origin(s) to existing CORS configuration.
     *
     * @param array|string $origins Single origin or array of origins to add
     * @return void
     */
    public static function addCorsOrigin($origins): void
    {
        if (is_string($origins)) {
            $origins = [$origins];
        }

        foreach ($origins as $origin) {
            $origin = rtrim($origin, '/');
            if (!in_array($origin, self::$corsConfig['allowed_origins'])) {
                self::$corsConfig['allowed_origins'][] = $origin;
            }
        }
    }

    /**
     * Check if origin is allowed.
     *
     * @param string|null $origin Origin to check (null to use current request origin)
     * @return bool True if origin is allowed
     */
    public static function isOriginAllowed(?string $origin = null): bool
    {
        if ($origin === null) {
            $origin = Url::origin();
        }

        if ($origin === null) {
            return false;
        }

        $origin = rtrim($origin, '/');

        // Check if wildcard is set
        if (in_array('*', self::$corsConfig['allowed_origins'])) {
            return true;
        }

        // Check exact match or pattern
        foreach (self::$corsConfig['allowed_origins'] as $allowed) {
            $allowed = rtrim($allowed, '/');
            
            // Exact match
            if ($allowed === $origin) {
                return true;
            }
            
            // Pattern match (e.g., https://*.example.com)
            if (strpos($allowed, '*') !== false) {
                $pattern = str_replace(['*', '.'], ['.*', '\.'], $allowed);
                if (preg_match('/^' . $pattern . '$/', $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get allowed origins list.
     *
     * @return array List of allowed origins
     */
    public static function getAllowedOrigins(): array
    {
        return self::$corsConfig['allowed_origins'];
    }

    /**
     * Get current CORS configuration.
     *
     * @return array Current CORS configuration
     */
    public static function getCorsConfig(): array
    {
        return self::$corsConfig;
    }

    /**
     * Check if CORS is enabled.
     *
     * @return bool True if CORS has been enabled
     */
    public static function isCorsEnabled(): bool
    {
        return self::$corsEnabled;
    }

    /**
     * Enable CORS with specified origins and configuration.
     * 
     * This is the recommended method for enabling CORS in your application.
     *
     * @param array|string|null $origins Allowed origins (null to allow current domain only)
     * @param array $config Additional CORS configuration
     * @return void
     */
    public static function enableCors($origins = null, array $config = []): void
    {
        // Set default origin if none provided
        if ($origins === null) {
            $origins = [Url::home()];
        }

        // Set origins
        self::setCorsOrigins($origins);

        // Apply additional configuration
        if (!empty($config)) {
            self::configureCors($config);
        }

        // Set CORS headers
        $corsSet = Header::cors(self::$corsConfig);

        // Handle preflight requests
        if (Url::method() === 'OPTIONS') {
            if ($corsSet) {
                http_response_code(204);
                exit;
            } else {
                // Origin not allowed
                http_response_code(403);
                Header::json();
                echo json_encode(['error' => 'CORS policy: Origin not allowed']);
                exit;
            }
        }

        self::$corsEnabled = true;
    }

    /**
     * CORS middleware for protecting all requests.
     * 
     * This method should be called early in your application bootstrap.
     * It will validate the origin and reject requests from unauthorized domains.
     *
     * @param array|string $origins Allowed origins
     * @param array $config Additional CORS configuration
     * @param int $errorCode HTTP status code for unauthorized requests (default: 403)
     * @return void
     */
    public static function corsMiddleware($origins, array $config = [], int $errorCode = 403): void
    {
        // Set origins
        self::setCorsOrigins($origins);

        // Apply additional configuration
        if (!empty($config)) {
            self::configureCors($config);
        }

        // Get request origin
        $requestOrigin = Url::origin();

        // If there's an origin header, validate it
        if ($requestOrigin !== null && !self::isOriginAllowed($requestOrigin)) {
            http_response_code($errorCode);
            Header::json();
            echo json_encode([
                'error' => 'CORS policy: Origin not allowed',
                'origin' => $requestOrigin,
                'allowed_origins' => self::$corsConfig['allowed_origins']
            ]);
            exit;
        }

        // Set CORS headers
        $corsSet = Header::cors(self::$corsConfig);

        // Handle preflight requests
        if (Url::method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        self::$corsEnabled = true;
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