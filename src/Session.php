<?php

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
 * - Flash data support for temporary messages
 * - Array manipulation helpers
 * - Security features (session regeneration, lifetime control)
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
     * Flash data key prefix
     *
     * @var string
     */
    private const FLASH_PREFIX = '_flash_';

    /**
     * Flash data that should be kept for next request
     *
     * @var string
     */
    private const FLASH_NEW_KEY = '_flash_new';

    /**
     * Flash data from previous request
     *
     * @var string
     */
    private const FLASH_OLD_KEY = '_flash_old';

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

        // Manage flash data lifecycle
        self::ageFlashData();

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
     * Get or set the session ID
     * 
     * @param string|null $id Optional session ID to set
     * @return string The current session ID
     */
    public static function id(?string $id = null): string
    {
        if ($id !== null) {
            session_id($id);
        }
        return session_id();
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
     * Check if a session variable exists
     * 
     * @param string|array $key Single key or array of keys to check
     * @return bool True if key exists, false otherwise
     */
    public static function has($key): bool
    {
        self::start();

        if (is_array($key)) {
            foreach ($key as $k) {
                if (!isset($_SESSION[$k])) {
                    return false;
                }
            }
            return true;
        }

        return isset($_SESSION[$key]);
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
            if (isset($_SESSION[$key])) {
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
     * Flash data for the next request only
     * 
     * Useful for one-time messages like success/error notifications.
     * 
     * @param string|array $key The flash key or array of key-value pairs
     * @param mixed $value The value to flash (ignored if $key is an array)
     * @return void
     */
    public static function flash($key, $value = null): void
    {
        self::start();

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::flashSingle($k, $v);
            }
        } else {
            self::flashSingle($key, $value);
        }
    }

    /**
     * Flash a single key-value pair
     * 
     * @param string $key The flash key
     * @param mixed $value The value to flash
     * @return void
     */
    private static function flashSingle(string $key, $value): void
    {
        self::set($key, $value);

        if (!isset($_SESSION[self::FLASH_NEW_KEY])) {
            $_SESSION[self::FLASH_NEW_KEY] = [];
        }

        $_SESSION[self::FLASH_NEW_KEY][] = $key;
    }

    /**
     * Keep flash data for one more request
     * 
     * @param string|array|null $keys Specific keys to keep, or null for all
     * @return void
     */
    public static function reflash($keys = null): void
    {
        self::start();

        $oldFlash = $_SESSION[self::FLASH_OLD_KEY] ?? [];

        if ($keys === null) {
            $keysToReflash = $oldFlash;
        } else {
            $keys = is_array($keys) ? $keys : [$keys];
            $keysToReflash = array_intersect($oldFlash, $keys);
        }

        if (!isset($_SESSION[self::FLASH_NEW_KEY])) {
            $_SESSION[self::FLASH_NEW_KEY] = [];
        }

        $_SESSION[self::FLASH_NEW_KEY] = array_merge(
            $_SESSION[self::FLASH_NEW_KEY],
            $keysToReflash
        );
    }

    /**
     * Get flash data
     * 
     * @param string $key The flash key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The flash value or default value
     */
    public static function getFlash(string $key, $default = null)
    {
        return self::get($key, $default);
    }

    /**
     * Age flash data (move new to old, remove old)
     * 
     * @return void
     */
    private static function ageFlashData(): void
    {
        // Remove old flash data
        if (isset($_SESSION[self::FLASH_OLD_KEY])) {
            foreach ($_SESSION[self::FLASH_OLD_KEY] as $key) {
                unset($_SESSION[$key]);
            }
        }

        // Move new flash data to old
        $_SESSION[self::FLASH_OLD_KEY] = $_SESSION[self::FLASH_NEW_KEY] ?? [];
        $_SESSION[self::FLASH_NEW_KEY] = [];
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

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
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
     * Set the session cookie lifetime
     * 
     * @param int $seconds Lifetime in seconds (0 = until browser closes)
     * @return void
     */
    public static function setLifetime(int $seconds): void
    {
        ini_set('session.cookie_lifetime', (string)$seconds);
        ini_set('session.gc_maxlifetime', (string)$seconds);
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
     * Set session cookie parameters
     * 
     * @param int $lifetime Cookie lifetime in seconds
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure Secure flag (HTTPS only)
     * @param bool $httponly HTTP only flag (no JavaScript access)
     * @return void
     */
    public static function setCookieParams(
        int $lifetime = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = true
    ): void {
        session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
    }
}