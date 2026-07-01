<?php

declare(strict_types=1);

namespace Webrium;

/**
 * Session Manager Class
 * 
 * A comprehensive session management utility that provides a clean, 
 * object-oriented interface for working with PHP sessions.
 * 
 * Features:
 * - Session lifecycle management (start, destroy, regenerate)
 * - Data storage and retrieval with default values
 * - Array manipulation helpers
 * - Security features (session regeneration, lifetime control)
 *
 * Note: one-request "flash" data (messages, validation errors, old input)
 * is handled by the {@see Flash} class, which builds on Session::set()/once().
 * 
 * @package Webrium
 */
class Session
{
    /**
     * Indicates whether the session has been started
     *
     * @var bool
     */
    private static $started = false;

    /**
     * Custom session save path
     *
     * @var string|null
     */
    private static $savePath = null;

    /**
     * Set the custom path for storing session files
     * 
     * Must be called before session_start() to take effect.
     * 
     * @param string $path The directory path to store session files
     * @return void
     */
    public static function setSavePath(string $path): void
    {
        self::$savePath = $path;
    }

    /**
     * Start a new session or resume an existing one
     * 
     * If the session has already been started, this method does nothing.
     * Automatically manages flash data lifecycle.
     * 
     * @return bool True if session was started, false if already started
     */
    public static function start(): bool
    {
        if (self::$started) {
            return false;
        }

        if (self::$savePath !== null) {
            session_save_path(self::$savePath);
        }

        session_start();
        self::$started = true;

        return true;
    }

    /**
     * Check if session has been started
     * 
     * @return bool True if session is active, false otherwise
     */
    public static function isStarted(): bool
    {
        return self::$started || session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Get or set the session ID.
     *
     * Setting an ID is only permitted before the session has started, and the
     * provided value must match the configured session id format. This prevents
     * an attacker-supplied identifier from being accepted (session fixation).
     *
     * @param string|null $id Optional session ID to set
     * @return string The current session ID
     * @throws \RuntimeException If setting an ID after the session has started
     * @throws \InvalidArgumentException If the ID has an invalid format
     */
    public static function id(?string $id = null): string
    {
        if ($id !== null) {
            if (self::isStarted()) {
                throw new \RuntimeException('Cannot set session ID after the session has started.');
            }

            if (!self::isValidId($id)) {
                throw new \InvalidArgumentException('Invalid session ID format.');
            }

            session_id($id);
        }

        return session_id();
    }

    /**
     * Validate a session ID against the characters and length allowed by the
     * current session id configuration.
     *
     * @param string $id The session ID to validate
     * @return bool True if the ID is well-formed
     */
    private static function isValidId(string $id): bool
    {
        $bitsPerChar = (int) (ini_get('session.sid_bits_per_character') ?: 4);
        $length      = (int) (ini_get('session.sid_length') ?: 32);

        $charset = match ($bitsPerChar) {
            6 => 'A-Za-z0-9,\-',
            5 => 'a-v0-9',
            default => 'a-f0-9',
        };

        return $id !== ''
            && strlen($id) <= $length
            && preg_match('/^[' . $charset . ']+$/', $id) === 1;
    }

    /**
     * Get or set the session name
     * 
     * @param string|null $name Optional session name to set
     * @return string The current session name
     */
    public static function name(?string $name = null): string
    {
        if ($name !== null) {
            session_name($name);
        }
        return session_name();
    }

    /**
     * Regenerate the session ID
     * 
     * Important for security to prevent session fixation attacks.
     * Should be called after authentication or privilege elevation.
     * 
     * @param bool $deleteOldSession Whether to delete the old session file
     * @return bool True on success, false on failure
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::start();
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Session fingerprint key.
     *
     * @var string
     */
    private const FINGERPRINT_KEY = '_fingerprint';

    /**
     * Bind the current session to a fingerprint of the request.
     *
     * Stores a fingerprint derived from the client's User-Agent so it can be
     * checked on later requests with verifyFingerprint(). Call this after
     * authentication, alongside regenerate(), to make stolen session IDs
     * harder to reuse from a different client.
     *
     * @return void
     */
    public static function bind(): void
    {
        self::start();
        $_SESSION[self::FINGERPRINT_KEY] = self::fingerprint();
    }

    /**
     * Verify the current request matches the bound session fingerprint.
     *
     * Returns true when no fingerprint has been set, so it is safe to call on
     * sessions that were never bound. Returns false when a stored fingerprint
     * does not match the current request.
     *
     * @return bool True if the session is unbound or the fingerprint matches
     */
    public static function verifyFingerprint(): bool
    {
        self::start();

        if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
            return true;
        }

        return hash_equals($_SESSION[self::FINGERPRINT_KEY], self::fingerprint());
    }

    /**
     * Compute a fingerprint for the current request.
     *
     * @return string The fingerprint hash
     */
    private static function fingerprint(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    /**
     * Set one or multiple session variables
     * 
     * @param string|array $key The key name or associative array of key-value pairs
     * @param mixed $value The value to set (ignored if $key is an array)
     * @return void
     */
    public static function set($key, $value = null): void
    {
        self::start();

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_SESSION[$k] = $v;
            }
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Get a session variable value
     * 
     * @param string $key The session variable key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The session value or default value
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Get a value and remove it from session (alias for pull)
     * 
     * @param string $key The session variable key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The session value or default value
     */
    public static function once(string $key, $default = null)
    {
        return self::pull($key, $default);
    }

    /**
     * Retrieve a value and remove it from the session
     * 
     * @param string $key The session variable key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The session value or default value
     */
    public static function pull(string $key, $default = null)
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /**
     * Check if a session variable exists.
     *
     * Reports whether the key is present, regardless of its value. A key whose
     * value is null still counts as existing; use exists() to also require a
     * non-null value.
     *
     * @param string|array $key Single key or array of keys to check
     * @return bool True if the key (or all keys) exist, false otherwise
     */
    public static function has($key): bool
    {
        self::start();

        if (is_array($key)) {
            foreach ($key as $k) {
                if (!array_key_exists($k, $_SESSION)) {
                    return false;
                }
            }
            return true;
        }

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Check if a session variable exists and is not null
     * 
     * @param string|array $key Single key or array of keys to check
     * @return bool True if key exists and is not null
     */
    public static function exists($key): bool
    {
        self::start();

        if (is_array($key)) {
            foreach ($key as $k) {
                if (!isset($_SESSION[$k]) || $_SESSION[$k] === null) {
                    return false;
                }
            }
            return true;
        }

        return isset($_SESSION[$key]) && $_SESSION[$key] !== null;
    }

    /**
     * Get all session variables
     * 
     * @return array Associative array of all session data
     */
    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }

    /**
     * Remove one or multiple session variables
     * 
     * @param string|array $keys Single key or array of keys to remove
     * @return bool True if at least one key was removed
     */
    public static function remove($keys): bool
    {
        return self::forget($keys);
    }

    /**
     * Remove session variables (alias for remove with better naming)
     * 
     * @param string|array $keys Single key or array of keys to remove
     * @return bool True if at least one key was removed
     */
    public static function forget($keys): bool
    {
        self::start();

        $removed = false;
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            if (array_key_exists($key, $_SESSION)) {
                unset($_SESSION[$key]);
                $removed = true;
            }
        }

        return $removed;
    }

    /**
     * Push a value onto a session array
     * 
     * @param string $key The session array key
     * @param mixed $value The value to push
     * @return void
     */
    public static function push(string $key, $value): void
    {
        self::start();

        if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $_SESSION[$key][] = $value;
    }

    /**
     * Remove all session variables and destroy the session
     * 
     * @return bool True on success
     */
    public static function clear(): bool
    {
        return self::destroy();
    }

    /**
     * Destroy the session completely
     * 
     * Removes all session data and destroys the session.
     * 
     * @return bool True on success
     */
    public static function destroy(): bool
    {
        self::start();
        
        $_SESSION = [];

        // Delete session cookie, preserving its original attributes so the
        // browser reliably matches and clears it.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        $result = session_destroy();
        self::$started = false;

        return $result;
    }

    /**
     * Flush all session data but keep session active
     * 
     * @return void
     */
    public static function flush(): void
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * Set the session cookie lifetime.
     *
     * Must be called before the session is started, since the lifetime is
     * applied when the session cookie is sent. Calling it after the session
     * has started has no effect and raises a warning.
     *
     * @param int $seconds Lifetime in seconds (0 = until browser closes)
     * @return bool True if the lifetime was applied, false if it was too late
     */
    public static function setLifetime(int $seconds): bool
    {
        if (self::isStarted()) {
            trigger_error(
                'Session::setLifetime() must be called before the session starts; ignoring.',
                E_USER_WARNING
            );
            return false;
        }

        $params = session_get_cookie_params();
        $params['lifetime'] = $seconds;
        session_set_cookie_params($params);

        ini_set('session.gc_maxlifetime', (string) $seconds);

        return true;
    }

    /**
     * Get the session cookie lifetime
     * 
     * @return int Lifetime in seconds
     */
    public static function getLifetime(): int
    {
        return (int)ini_get('session.cookie_lifetime');
    }

    /**
     * Increment a session value
     * 
     * @param string $key The session key
     * @param int $amount Amount to increment by (default: 1)
     * @return int The new value
     */
    public static function increment(string $key, int $amount = 1): int
    {
        self::start();
        
        $value = self::get($key, 0);
        $value += $amount;
        self::set($key, $value);
        
        return $value;
    }

    /**
     * Decrement a session value
     * 
     * @param string $key The session key
     * @param int $amount Amount to decrement by (default: 1)
     * @return int The new value
     */
    public static function decrement(string $key, int $amount = 1): int
    {
        return self::increment($key, -$amount);
    }

    /**
     * Get session cookie parameters
     * 
     * @return array Cookie parameters
     */
    public static function getCookieParams(): array
    {
        return session_get_cookie_params();
    }

    /**
     * Set session cookie parameters with secure defaults.
     *
     * Must be called before the session is started to take effect. By default
     * the cookie is HttpOnly, uses SameSite=Lax, and is marked Secure whenever
     * the current request is over HTTPS.
     *
     * @param int $lifetime Cookie lifetime in seconds (0 = until browser closes)
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool|null $secure Secure flag; null auto-detects HTTPS
     * @param bool $httponly HTTP only flag (no JavaScript access)
     * @param string $samesite SameSite policy: 'Lax', 'Strict', or 'None'
     * @return bool True on success
     */
    public static function setCookieParams(
        int $lifetime = 0,
        string $path = '/',
        string $domain = '',
        ?bool $secure = null,
        bool $httponly = true,
        string $samesite = 'Lax'
    ): bool {
        if ($secure === null) {
            $secure = self::isHttps();
        }

        return session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    }

    /**
     * Determine whether the current request is served over HTTPS.
     *
     * @return bool True if the connection is secure
     */
    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
            return true;
        }

        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }

        return false;
    }
}