<?php

namespace Webrium;

/**
 * URL Helper Class
 * Provides utilities for URL generation, parsing, and manipulation
 * 
 * @package Webrium
 * @requires PHP 8.0+
 */
class Url
{
    private static ?string $baseUrl = null;
    private static ?array $parsedUrl = null;

    /**
     * Get document root path without trailing slash
     *
     * @return string Document root path
     */
    public static function documentRoot(): string
    {
        return self::removeTrailingSlash($_SERVER['DOCUMENT_ROOT'] ?? '');
    }

    /**
     * Get current request scheme (http or https)
     *
     * @param bool $withSeparator Include :// separator
     * @return string Request scheme
     */
    public static function scheme(bool $withSeparator = false): string
    {
        $isSecure = self::isSecure();
        $scheme = $isSecure ? 'https' : 'http';
        
        return $scheme . ($withSeparator ? '://' : '');
    }

    /**
     * Check if current request is secure (HTTPS)
     *
     * @return bool True if HTTPS, false otherwise
     */
    public static function isSecure(): bool
    {
        return match(true) {
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' => true,
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' => true,
            !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on' => true,
            !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443 => true,
            default => false
        };
    }

    /**
     * Get current domain/host
     *
     * @return string Domain name
     */
    public static function domain(): string
    {
        return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    }

    /**
     * Get HTTP request method
     *
     * @return string Request method (GET, POST, etc.)
     */
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Get base URL with scheme and domain
     *
     * @return string Base URL (e.g., https://example.com)
     */
    public static function home(): string
    {
        return self::scheme(true) . self::domain();
    }

    /**
     * Get application base URL (including subdirectory if exists)
     *
     * @return string Application base URL
     */
    public static function base(): string
    {
        if (self::$baseUrl !== null) {
            return self::$baseUrl;
        }

        $documentRoot = self::documentRoot();
        $scriptPath = App::getRootPath();

        // Extract subdirectory path
        $position = strpos($scriptPath, $documentRoot);
        if ($position !== false && $position > 0) {
            $scriptPath = substr($scriptPath, $position);
        }

        $subdirectory = substr($scriptPath, strlen($documentRoot));
        self::$baseUrl = self::home() . $subdirectory;

        return self::$baseUrl;
    }

    /**
     * Generate full URL from relative path
     *
     * @param string $path Relative path
     * @return string Full URL
     */
    public static function to(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return self::base() . ($path ? '/' . $path : '');
    }

    /**
     * Get current full URL
     *
     * @param bool $withQueryString Include query string
     * @return string Current URL
     */
    public static function current(bool $withQueryString = false): string
    {
        return self::home() . self::uri($withQueryString);
    }

    /**
     * Get current URI path (without domain)
     *
     * @param bool $withQueryString Include query string
     * @return string URI path
     */
    public static function uri(bool $withQueryString = false): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!$withQueryString) {
            $uri = strtok($uri, '?');
        }

        return urldecode($uri);
    }

    /**
     * Get server IP address
     *
     * @return string Server IP
     */
    public static function serverIp(): string
    {
        return $_SERVER['SERVER_ADDR'] ?? '';
    }

    /**
     * Get client IP address (with proxy support)
     *
     * @return string Client IP
     */
    public static function clientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle multiple IPs (take first one)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Get query string
     *
     * @return string Query string
     */
    public static function queryString(): string
    {
        return $_SERVER['QUERY_STRING'] ?? '';
    }

    /**
     * Get current URL with query string
     *
     * @return string Full current URL
     */
    public static function full(): string
    {
        return self::current(true);
    }

    /**
     * Get current path segments as array
     *
     * @return array Path segments
     */
    public static function segments(): array
    {
        $url = self::current();
        $scheme = self::scheme(true);
        
        // Remove scheme and domain
        $path = substr($url, strlen($scheme . self::domain()));
        
        return array_values(array_filter(explode('/', $path)));
    }

    /**
     * Get specific segment by index
     *
     * @param int $index Segment index (0-based)
     * @param mixed $default Default value if segment doesn't exist
     * @return mixed Segment value or default
     */
    public static function segment(int $index, mixed $default = null): mixed
    {
        $segments = self::segments();
        return $segments[$index] ?? $default;
    }

    /**
     * Get $_SERVER variable
     *
     * @param string|null $key Server variable key
     * @param mixed $default Default value
     * @return mixed Server variable value
     */
    public static function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_SERVER;
        }

        return $_SERVER[$key] ?? $default;
    }

    /**
     * Check if current URL matches pattern
     *
     * @param string $pattern URL pattern (supports wildcards *)
     * @return bool True if matches
     */
    public static function is(string $pattern): bool
    {
        $currentUrl = self::current();
        $patternUrl = self::to($pattern);

        // Handle wildcard matching
        if (str_contains($patternUrl, '*')) {
            $patternUrl = str_replace('*', '', $patternUrl);
            return str_starts_with($currentUrl, $patternUrl) && strlen($currentUrl) > strlen($patternUrl);
        }

        return $currentUrl === $patternUrl;
    }

    /**
     * Check if current URL matches any of the given patterns
     *
     * @param array $patterns Array of URL patterns
     * @return bool True if any pattern matches
     */
    public static function isAny(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::is($pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove trailing slash from string
     *
     * @param string $value String to process
     * @return string String without trailing slash
     */
    public static function removeTrailingSlash(string $value): string
    {
        return rtrim($value, '/');
    }

    /**
     * Check if string has trailing slash
     *
     * @param string $value String to check
     * @return bool True if has trailing slash
     */
    public static function hasTrailingSlash(string $value): bool
    {
        return str_ends_with($value, '/');
    }

    /**
     * Add trailing slash if not exists
     *
     * @param string $value String to process
     * @return string String with trailing slash
     */
    public static function addTrailingSlash(string $value): string
    {
        return self::removeTrailingSlash($value) . '/';
    }

    /**
     * Redirect to URL without trailing slash (SEO friendly)
     *
     * @param int $statusCode HTTP status code (301 or 302)
     * @return void
     */
    public static function redirectWithoutTrailingSlash(int $statusCode = 301): void
    {
        $currentUrl = self::full();
        $baseUrl = self::base() . '/';

        // Don't redirect if it's the base URL
        if (self::current() === $baseUrl) {
            return;
        }

        if (self::hasTrailingSlash(self::current())) {
            $queryString = self::queryString();
            $newUrl = self::removeTrailingSlash(self::current());
            
            if ($queryString) {
                $newUrl .= '?' . $queryString;
            }

            redirect($newUrl, $statusCode);
        }
    }

    /**
     * Enforce URL standards (trailing slash removal)
     *
     * @return void
     */
    public static function enforce(): void
    {
        self::redirectWithoutTrailingSlash(301);
    }

    /**
     * Parse URL into components
     *
     * @param string|null $url URL to parse (null for current URL)
     * @return array URL components
     */
    public static function parse(?string $url = null): array
    {
        $url = $url ?? self::full();
        
        $parsed = parse_url($url);
        
        return [
            'scheme' => $parsed['scheme'] ?? '',
            'host' => $parsed['host'] ?? '',
            'port' => $parsed['port'] ?? null,
            'path' => $parsed['path'] ?? '/',
            'query' => $parsed['query'] ?? '',
            'fragment' => $parsed['fragment'] ?? '',
        ];
    }

    /**
     * Build URL from components
     *
     * @param array $components URL components
     * @return string Built URL
     */
    public static function build(array $components): string
    {
        $url = '';

        if (!empty($components['scheme'])) {
            $url .= $components['scheme'] . '://';
        }

        if (!empty($components['host'])) {
            $url .= $components['host'];
        }

        if (!empty($components['port'])) {
            $url .= ':' . $components['port'];
        }

        if (!empty($components['path'])) {
            $url .= $components['path'];
        }

        if (!empty($components['query'])) {
            $url .= '?' . $components['query'];
        }

        if (!empty($components['fragment'])) {
            $url .= '#' . $components['fragment'];
        }

        return $url;
    }

    /**
     * Add or update query parameters
     *
     * @param array $params Parameters to add/update
     * @param string|null $url Base URL (null for current)
     * @return string URL with updated parameters
     */
    public static function withQuery(array $params, ?string $url = null): string
    {
        $url = $url ?? self::current();
        $parts = self::parse($url);
        
        parse_str($parts['query'], $existingParams);
        $mergedParams = array_merge($existingParams, $params);
        
        $parts['query'] = http_build_query($mergedParams);
        
        return self::build($parts);
    }

    /**
     * Remove query parameters
     *
     * @param array $keys Parameter keys to remove
     * @param string|null $url Base URL (null for current)
     * @return string URL without specified parameters
     */
    public static function withoutQuery(array $keys, ?string $url = null): string
    {
        $url = $url ?? self::current();
        $parts = self::parse($url);
        
        parse_str($parts['query'], $params);
        
        foreach ($keys as $key) {
            unset($params[$key]);
        }
        
        $parts['query'] = http_build_query($params);
        
        return self::build($parts);
    }

    /**
     * Get previous URL from referer
     *
     * @param string|null $default Default URL if no referer
     * @return string Previous URL
     */
    public static function previous(?string $default = null): string
    {
        return $_SERVER['HTTP_REFERER'] ?? $default ?? self::base();
    }

    /**
     * Check if request is AJAX
     *
     * @return bool True if AJAX request
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Reset cached values (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$baseUrl = null;
        self::$parsedUrl = null;
    }
}