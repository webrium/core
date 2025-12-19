<?php

namespace Webrium;

/**
 * HTTP Header management class.
 * 
 * Provides methods for reading, setting, and managing HTTP headers
 * including authentication, CORS, security headers, and caching.
 * 
 * @package Webrium
 */
class Header
{
    /**
     * Cache for parsed headers to avoid multiple lookups
     */
    private static ?array $headerCache = null;

    /**
     * Default CORS configuration
     */
    private static array $corsDefaults = [
        'allowed_origins' => [],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
        'allow_credentials' => false,
        'max_age' => 86400, // 24 hours
        'expose_headers' => [],
    ];


    /**
     * Get all HTTP request headers.
     *
     * @return array Associative array of headers
     */
    public static function all(): array
    {
        if (self::$headerCache !== null) {
            return self::$headerCache;
        }

        if (function_exists('getallheaders')) {
            self::$headerCache = getallheaders();
        } else {
            self::$headerCache = self::parseHeadersFromServer();
        }

        return self::$headerCache;
    }


    /**
     * Parse headers from $_SERVER when getallheaders() is unavailable.
     *
     * @return array
     */
    private static function parseHeadersFromServer(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }


    /**
     * Get a specific header value.
     *
     * @param string $name Header name (case-insensitive)
     * @param mixed $default Default value if header not found
     * @return mixed
     */
    public static function get(string $name, $default = null)
    {
        $headers = self::all();
        
        // Case-insensitive search
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }


    /**
     * Check if a header exists.
     *
     * @param string $name Header name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return self::get($name) !== null;
    }


    /**
     * Set an HTTP response header.
     *
     * @param string $name Header name
     * @param string $value Header value
     * @param bool $replace Whether to replace existing header
     * @return void
     */
    public static function set(string $name, string $value, bool $replace = true): void
    {
        header("$name: $value", $replace);
    }


    /**
     * Set multiple headers at once.
     *
     * @param array $headers Associative array of headers
     * @param bool $replace Whether to replace existing headers
     * @return void
     */
    public static function setMultiple(array $headers, bool $replace = true): void
    {
        foreach ($headers as $name => $value) {
            self::set($name, $value, $replace);
        }
    }


    /**
     * Get the Authorization header value.
     *
     * @return string|null
     */
    public static function getAuthorization(): ?string
    {
        // Check $_SERVER variables
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }

        // Check REDIRECT_HTTP_AUTHORIZATION for some server configs
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        // Try apache_request_headers()
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            
            // Normalize header keys
            $headers = array_change_key_case($headers, CASE_LOWER);

            if (isset($headers['authorization'])) {
                return trim($headers['authorization']);
            }
        }

        return null;
    }


    /**
     * Get Bearer token from Authorization header.
     *
     * @return string|null
     */
    public static function getBearerToken(): ?string
    {
        $authorization = self::getAuthorization();

        if ($authorization && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }


    /**
     * Get Basic authentication credentials.
     *
     * @return array|null Array with 'username' and 'password' keys, or null
     */
    public static function getBasicAuth(): ?array
    {
        $authorization = self::getAuthorization();

        if ($authorization && preg_match('/^Basic\s+(.+)$/i', $authorization, $matches)) {
            $credentials = base64_decode($matches[1]);
            
            if (strpos($credentials, ':') !== false) {
                list($username, $password) = explode(':', $credentials, 2);
                return [
                    'username' => $username,
                    'password' => $password,
                ];
            }
        }

        return null;
    }


    /**
     * Get API key from custom header.
     *
     * @param string $headerName Header name (default: X-API-Key)
     * @return string|null
     */
    public static function getApiKey(string $headerName = 'X-API-Key'): ?string
    {
        return self::get($headerName);
    }



    /**
     * Get the User-Agent string.
     *
     * @return string|null
     */
    public static function getUserAgent(): ?string
    {
        return self::get('User-Agent');
    }


    /**
     * Get the Referer URL.
     *
     * @return string|null
     */
    public static function getReferer(): ?string
    {
        return self::get('Referer');
    }


    /**
     * Get Content-Type header.
     *
     * @return string|null
     */
    public static function getContentType(): ?string
    {
        return self::get('Content-Type');
    }


    /**
     * Check if request expects JSON response.
     *
     * @return bool
     */
    public static function expectsJson(): bool
    {
        $accept = self::get('Accept', '');
        return strpos($accept, 'application/json') !== false;
    }


    /**
     * Set CORS headers (low-level method).
     * 
     * This is a low-level method for setting CORS headers. 
     * For high-level CORS management, use App::corsMiddleware() instead.
     *
     * @param array $config CORS configuration with keys:
     *                      - allowed_origins: array of allowed origin URLs
     *                      - allowed_methods: array of allowed HTTP methods
     *                      - allowed_headers: array of allowed headers
     *                      - allow_credentials: bool
     *                      - max_age: int (seconds)
     *                      - expose_headers: array of exposed headers
     * @return bool True if headers were set, false if origin not allowed
     */
    public static function cors(array $config = []): bool
    {
        $config = array_merge(self::$corsDefaults, $config);
        $requestOrigin = Url::origin();

        // If no origin header present, skip CORS headers
        if ($requestOrigin === null) {
            return true;
        }

        $requestOrigin = rtrim($requestOrigin, '/');
        $allowedOrigin = null;

        // Check if origin is allowed
        if (in_array('*', $config['allowed_origins'])) {
            // Wildcard - but cannot be used with credentials
            if ($config['allow_credentials']) {
                // Security: Cannot use wildcard with credentials
                return false;
            }
            $allowedOrigin = '*';
        } else {
            // Check exact match or pattern
            foreach ($config['allowed_origins'] as $allowed) {
                $allowed = rtrim($allowed, '/');
                
                // Exact match
                if ($allowed === $requestOrigin) {
                    $allowedOrigin = $requestOrigin;
                    break;
                }
                
                // Pattern match (e.g., https://*.example.com)
                if (strpos($allowed, '*') !== false) {
                    $pattern = str_replace(['*', '.'], ['.*', '\.'], $allowed);
                    if (preg_match('/^' . $pattern . '$/', $requestOrigin)) {
                        $allowedOrigin = $requestOrigin;
                        break;
                    }
                }
            }
        }

        // If origin not allowed, don't set CORS headers
        if ($allowedOrigin === null) {
            return false;
        }

        // Set Access-Control-Allow-Origin
        self::set('Access-Control-Allow-Origin', $allowedOrigin);

        // Set Access-Control-Allow-Credentials
        if ($config['allow_credentials']) {
            self::set('Access-Control-Allow-Credentials', 'true');
        }

        // Set Access-Control-Allow-Methods
        if (!empty($config['allowed_methods'])) {
            $methods = is_array($config['allowed_methods']) 
                ? implode(', ', $config['allowed_methods']) 
                : $config['allowed_methods'];
            self::set('Access-Control-Allow-Methods', $methods);
        }

        // Set Access-Control-Allow-Headers
        if (!empty($config['allowed_headers'])) {
            $headers = is_array($config['allowed_headers']) 
                ? implode(', ', $config['allowed_headers']) 
                : $config['allowed_headers'];
            self::set('Access-Control-Allow-Headers', $headers);
        }

        // Set Access-Control-Max-Age
        if ($config['max_age']) {
            self::set('Access-Control-Max-Age', (string)$config['max_age']);
        }

        // Set Access-Control-Expose-Headers
        if (!empty($config['expose_headers'])) {
            $exposeHeaders = is_array($config['expose_headers']) 
                ? implode(', ', $config['expose_headers']) 
                : $config['expose_headers'];
            self::set('Access-Control-Expose-Headers', $exposeHeaders);
        }

        return true;
    }


    /**
     * Handle CORS preflight request (OPTIONS).
     * 
     * @param array $config CORS configuration
     * @param bool $terminate Whether to terminate after handling preflight
     * @return void
     */
    public static function handlePreflight(array $config = [], bool $terminate = true): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::cors($config);
            
            if ($terminate) {
                http_response_code(204);
                exit;
            }
        }
    }


    /**
     * Set security headers.
     *
     * @param array $options Security header options
     * @return void
     */
    public static function security(array $options = []): void
    {
        $defaults = [
            'hsts' => true,
            'hsts_max_age' => 31536000, // 1 year
            'hsts_subdomains' => true,
            'hsts_preload' => false,
            'nosniff' => true,
            'xss_protection' => true,
            'frame_options' => 'SAMEORIGIN', // DENY, SAMEORIGIN, or false
            'csp' => null, // Content Security Policy
            'referrer_policy' => 'strict-origin-when-cross-origin',
        ];

        $config = array_merge($defaults, $options);

        // HTTP Strict Transport Security
        if ($config['hsts']) {
            $hsts = "max-age={$config['hsts_max_age']}";
            if ($config['hsts_subdomains']) {
                $hsts .= '; includeSubDomains';
            }
            if ($config['hsts_preload']) {
                $hsts .= '; preload';
            }
            self::set('Strict-Transport-Security', $hsts);
        }

        // X-Content-Type-Options
        if ($config['nosniff']) {
            self::set('X-Content-Type-Options', 'nosniff');
        }

        // X-XSS-Protection
        if ($config['xss_protection']) {
            self::set('X-XSS-Protection', '1; mode=block');
        }

        // X-Frame-Options
        if ($config['frame_options']) {
            self::set('X-Frame-Options', $config['frame_options']);
        }

        // Content-Security-Policy
        if ($config['csp']) {
            self::set('Content-Security-Policy', $config['csp']);
        }

        // Referrer-Policy
        if ($config['referrer_policy']) {
            self::set('Referrer-Policy', $config['referrer_policy']);
        }

        // Remove identifying headers
        header_remove('X-Powered-By');
        header_remove('Server');
    }


    /**
     * Set cache control headers.
     *
     * @param int|string $value Cache duration in seconds, or cache directive
     * @param array $options Additional cache options
     * @return void
     */
    public static function cache($value, array $options = []): void
    {
        $defaults = [
            'public' => true,
            'must_revalidate' => false,
            'no_transform' => false,
            's_maxage' => null,
        ];

        $config = array_merge($defaults, $options);

        if (is_numeric($value)) {
            // Set cache with max-age
            $directives = [];
            
            if ($config['public']) {
                $directives[] = 'public';
            } else {
                $directives[] = 'private';
            }

            $directives[] = "max-age=$value";

            if ($config['must_revalidate']) {
                $directives[] = 'must-revalidate';
            }

            if ($config['no_transform']) {
                $directives[] = 'no-transform';
            }

            if ($config['s_maxage'] !== null) {
                $directives[] = "s-maxage={$config['s_maxage']}";
            }

            self::set('Cache-Control', implode(', ', $directives));
            self::set('Expires', gmdate('D, d M Y H:i:s', time() + $value) . ' GMT');
        } else {
            // Set custom cache directive
            self::set('Cache-Control', $value);
        }
    }


    /**
     * Disable caching.
     *
     * @return void
     */
    public static function noCache(): void
    {
        self::set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        self::set('Pragma', 'no-cache');
        self::set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }


    /**
     * Set content type header.
     *
     * @param string $type Content type
     * @param string $charset Character encoding (default: utf-8)
     * @return void
     */
    public static function contentType(string $type, string $charset = 'utf-8'): void
    {
        $value = $type;
        
        if ($charset) {
            $value .= "; charset=$charset";
        }

        self::set('Content-Type', $value);
    }


    /**
     * Set JSON content type.
     *
     * @return void
     */
    public static function json(): void
    {
        self::contentType('application/json');
    }


    /**
     * Set HTML content type.
     *
     * @return void
     */
    public static function html(): void
    {
        self::contentType('text/html');
    }


    /**
     * Set XML content type.
     *
     * @return void
     */
    public static function xml(): void
    {
        self::contentType('application/xml');
    }


    /**
     * Set plain text content type.
     *
     * @return void
     */
    public static function text(): void
    {
        self::contentType('text/plain');
    }


    /**
     * Set file download headers.
     *
     * @param string $filename Filename for download
     * @param string|null $contentType Content type (auto-detect if null)
     * @param int|null $fileSize File size in bytes
     * @return void
     */
    public static function download(string $filename, ?string $contentType = null, ?int $fileSize = null): void
    {
        if ($contentType === null) {
            $contentType = 'application/octet-stream';
        }

        self::set('Content-Type', $contentType);
        self::set('Content-Disposition', 'attachment; filename="' . addslashes($filename) . '"');
        
        if ($fileSize !== null) {
            self::set('Content-Length', (string)$fileSize);
        }

        self::noCache();
    }


    /**
     * Set redirect header.
     *
     * @param string $url Target URL
     * @param int $code HTTP status code (default: 302)
     * @return void
     */
    public static function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        self::set('Location', $url);
        exit();
    }


    /**
     * Set custom HTTP status code.
     *
     * @param int $code HTTP status code
     * @return void
     */
    public static function status(int $code): void
    {
        http_response_code($code);
    }


    /**
     * Check if headers have already been sent.
     *
     * @param string|null &$file Reference to file where headers were sent
     * @param int|null &$line Reference to line where headers were sent
     * @return bool
     */
    public static function sent(?string &$file = null, ?int &$line = null): bool
    {
        return headers_sent($file, $line);
    }


    /**
     * Remove a previously set header.
     *
     * @param string $name Header name
     * @return void
     */
    public static function remove(string $name): void
    {
        header_remove($name);
    }


    /**
     * Clear header cache.
     * Useful when headers might have changed during request processing.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$headerCache = null;
    }
}