<?php

namespace Webrium;

/**
 * Hash Manager Class
 * 
 * A comprehensive hashing utility that provides secure password hashing,
 * verification, and various hashing algorithms for different use cases.
 * 
 * Features:
 * - Secure password hashing (bcrypt, argon2i, argon2id)
 * - Password verification
 * - Hash information retrieval
 * - Rehashing for security updates
 * - General-purpose hashing (MD5, SHA-256, etc.)
 * - HMAC generation and verification
 * - Hash comparison (timing-safe)
 * 
 * @package Webrium
 * @version 2.0.0
 */
class Hash
{
    /**
     * Default cost for bcrypt algorithm
     *
     * @var int
     */
    private const DEFAULT_BCRYPT_COST = 10;

    /**
     * Default memory cost for Argon2 (in KiB)
     *
     * @var int
     */
    private const DEFAULT_ARGON2_MEMORY = 65536;

    /**
     * Default time cost for Argon2
     *
     * @var int
     */
    private const DEFAULT_ARGON2_TIME = 4;

    /**
     * Default threads for Argon2
     *
     * @var int
     */
    private const DEFAULT_ARGON2_THREADS = 1;

    /**
     * Hash a password using secure algorithm
     *
     * @param string $password The password to hash
     * @param int|string|null $algorithm The hashing algorithm (PASSWORD_DEFAULT, PASSWORD_BCRYPT, PASSWORD_ARGON2I, PASSWORD_ARGON2ID)
     * @param array $options Optional algorithm-specific options
     * @return string The hashed password
     */
    public static function make(string $password, $algorithm = PASSWORD_DEFAULT, array $options = []): string
    {
        // Set default options based on algorithm
        if (empty($options)) {
            $options = self::getDefaultOptions($algorithm);
        }

        return password_hash($password, $algorithm, $options);
    }

    /**
     * Get default options for a hashing algorithm
     *
     * @param int|string $algorithm The hashing algorithm
     * @return array Default options
     */
    private static function getDefaultOptions($algorithm): array
    {
        switch ($algorithm) {
            case PASSWORD_BCRYPT:
                return ['cost' => self::DEFAULT_BCRYPT_COST];
            
            case PASSWORD_ARGON2I:
            case PASSWORD_ARGON2ID:
                return [
                    'memory_cost' => self::DEFAULT_ARGON2_MEMORY,
                    'time_cost' => self::DEFAULT_ARGON2_TIME,
                    'threads' => self::DEFAULT_ARGON2_THREADS,
                ];
            
            default:
                return [];
        }
    }

    /**
     * Verify a password against a hash
     *
     * @param string $password The plain-text password
     * @param string $hash The hashed password
     * @return bool True if the password matches, false otherwise
     */
    public static function check(string $password, string $hash): bool
    {
        if (strlen($hash) === 0) {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Verify a password and rehash if needed (for security updates)
     *
     * @param string $password The plain-text password
     * @param string $hash The current hashed password
     * @param int|string|null $algorithm The hashing algorithm
     * @param array $options Algorithm-specific options
     * @return array ['verified' => bool, 'hash' => string|null] Returns verification status and new hash if needed
     */
    public static function checkAndRehash(
        string $password,
        string $hash,
        $algorithm = PASSWORD_DEFAULT,
        array $options = []
    ): array {
        $verified = self::check($password, $hash);

        if (!$verified) {
            return ['verified' => false, 'hash' => null];
        }

        // Check if rehashing is needed
        if (self::needsRehash($hash, $algorithm, $options)) {
            $newHash = self::make($password, $algorithm, $options);
            return ['verified' => true, 'hash' => $newHash];
        }

        return ['verified' => true, 'hash' => null];
    }

    /**
     * Check if a hash needs to be rehashed
     *
     * @param string $hash The hash to check
     * @param int|string|null $algorithm The target algorithm
     * @param array $options Algorithm-specific options
     * @return bool True if rehashing is needed
     */
    public static function needsRehash(string $hash, $algorithm = PASSWORD_DEFAULT, array $options = []): bool
    {
        if (empty($options)) {
            $options = self::getDefaultOptions($algorithm);
        }

        return password_needs_rehash($hash, $algorithm, $options);
    }

    /**
     * Get information about a password hash
     *
     * @param string $hash The hash to analyze
     * @return array Information about the hash (algo, algoName, options)
     */
    public static function info(string $hash): array
    {
        return password_get_info($hash);
    }

    /**
     * Get the algorithm name from a hash
     *
     * @param string $hash The hash to analyze
     * @return string|null The algorithm name (bcrypt, argon2i, argon2id, unknown)
     */
    public static function getAlgorithm(string $hash): ?string
    {
        $info = self::info($hash);
        return $info['algoName'] ?? null;
    }

    /**
     * Create a hash using a specific algorithm (non-password)
     *
     * @param string $data The data to hash
     * @param string $algorithm The algorithm (md5, sha1, sha256, sha512, etc.)
     * @param bool $binary Return binary output instead of hex
     * @return string The hash
     */
    public static function digest(string $data, string $algorithm = 'sha256', bool $binary = false): string
    {
        return hash($algorithm, $data, $binary);
    }

    /**
     * Create an MD5 hash
     *
     * @param string $data The data to hash
     * @param bool $binary Return binary output
     * @return string The MD5 hash
     */
    public static function md5(string $data, bool $binary = false): string
    {
        return hash('md5', $data, $binary);
    }

    /**
     * Create a SHA-1 hash
     *
     * @param string $data The data to hash
     * @param bool $binary Return binary output
     * @return string The SHA-1 hash
     */
    public static function sha1(string $data, bool $binary = false): string
    {
        return hash('sha1', $data, $binary);
    }

    /**
     * Create a SHA-256 hash
     *
     * @param string $data The data to hash
     * @param bool $binary Return binary output
     * @return string The SHA-256 hash
     */
    public static function sha256(string $data, bool $binary = false): string
    {
        return hash('sha256', $data, $binary);
    }

    /**
     * Create a SHA-512 hash
     *
     * @param string $data The data to hash
     * @param bool $binary Return binary output
     * @return string The SHA-512 hash
     */
    public static function sha512(string $data, bool $binary = false): string
    {
        return hash('sha512', $data, $binary);
    }

    /**
     * Generate a keyed hash (HMAC)
     *
     * @param string $data The data to hash
     * @param string $key The secret key
     * @param string $algorithm The hash algorithm
     * @param bool $binary Return binary output
     * @return string The HMAC
     */
    public static function hmac(string $data, string $key, string $algorithm = 'sha256', bool $binary = false): string
    {
        return hash_hmac($algorithm, $data, $key, $binary);
    }

    /**
     * Verify an HMAC
     *
     * @param string $data The original data
     * @param string $hmac The HMAC to verify
     * @param string $key The secret key
     * @param string $algorithm The hash algorithm
     * @return bool True if HMAC is valid
     */
    public static function verifyHmac(string $data, string $hmac, string $key, string $algorithm = 'sha256'): bool
    {
        $calculated = self::hmac($data, $key, $algorithm);
        return self::equals($calculated, $hmac);
    }

    /**
     * Timing-safe string comparison
     *
     * @param string $known The known string
     * @param string $user The user-provided string
     * @return bool True if strings match
     */
    public static function equals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Generate a random hash (useful for tokens)
     *
     * @param int $length The desired length of the hash
     * @param string $algorithm The hash algorithm to use
     * @return string A random hash
     */
    public static function random(int $length = 32, string $algorithm = 'sha256'): string
    {
        $bytes = random_bytes(max(16, ceil($length / 2)));
        $hash = hash($algorithm, $bytes);
        return substr($hash, 0, $length);
    }

    /**
     * Generate a unique hash based on current data and time
     *
     * @param string $prefix Optional prefix for the hash
     * @param string $algorithm The hash algorithm
     * @return string A unique hash
     */
    public static function unique(string $prefix = '', string $algorithm = 'sha256'): string
    {
        $data = $prefix . microtime(true) . random_bytes(16);
        return hash($algorithm, $data);
    }

    /**
     * Create a hash from a file
     *
     * @param string $filepath The path to the file
     * @param string $algorithm The hash algorithm
     * @param bool $binary Return binary output
     * @return string|false The file hash, or false on failure
     */
    public static function file(string $filepath, string $algorithm = 'sha256', bool $binary = false)
    {
        if (!file_exists($filepath)) {
            return false;
        }
        return hash_file($algorithm, $filepath, $binary);
    }

    /**
     * Get a list of all available hash algorithms
     *
     * @return array List of supported algorithms
     */
    public static function algorithms(): array
    {
        return hash_algos();
    }

    /**
     * Check if an algorithm is supported
     *
     * @param string $algorithm The algorithm name
     * @return bool True if supported
     */
    public static function isAlgorithmSupported(string $algorithm): bool
    {
        return in_array(strtolower($algorithm), self::algorithms(), true);
    }

    /**
     * Create a bcrypt hash with custom cost
     *
     * @param string $password The password to hash
     * @param int $cost The cost parameter (4-31)
     * @return string The hashed password
     */
    public static function bcrypt(string $password, int $cost = self::DEFAULT_BCRYPT_COST): string
    {
        return self::make($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Create an Argon2i hash with custom parameters
     *
     * @param string $password The password to hash
     * @param int $memoryCost Memory cost in KiB
     * @param int $timeCost Number of iterations
     * @param int $threads Number of parallel threads
     * @return string The hashed password
     */
    public static function argon2i(
        string $password,
        int $memoryCost = self::DEFAULT_ARGON2_MEMORY,
        int $timeCost = self::DEFAULT_ARGON2_TIME,
        int $threads = self::DEFAULT_ARGON2_THREADS
    ): string {
        return self::make($password, PASSWORD_ARGON2I, [
            'memory_cost' => $memoryCost,
            'time_cost' => $timeCost,
            'threads' => $threads,
        ]);
    }

    /**
     * Create an Argon2id hash with custom parameters
     *
     * @param string $password The password to hash
     * @param int $memoryCost Memory cost in KiB
     * @param int $timeCost Number of iterations
     * @param int $threads Number of parallel threads
     * @return string The hashed password
     */
    public static function argon2id(
        string $password,
        int $memoryCost = self::DEFAULT_ARGON2_MEMORY,
        int $timeCost = self::DEFAULT_ARGON2_TIME,
        int $threads = self::DEFAULT_ARGON2_THREADS
    ): string {
        return self::make($password, PASSWORD_ARGON2ID, [
            'memory_cost' => $memoryCost,
            'time_cost' => $timeCost,
            'threads' => $threads,
        ]);
    }

    /**
     * Generate a secure token (URL-safe)
     *
     * @param int $length The desired length of the token
     * @return string A secure random token
     */
    public static function token(int $length = 32): string
    {
        $bytes = random_bytes(max(16, $length));
        return substr(bin2hex($bytes), 0, $length);
    }

    /**
     * Generate a UUID v4
     *
     * @return string A UUID v4 string
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        
        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Create a hash with salt
     *
     * @param string $data The data to hash
     * @param string $salt The salt to add
     * @param string $algorithm The hash algorithm
     * @return string The salted hash
     */
    public static function salted(string $data, string $salt, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $data . $salt);
    }

    /**
     * Create a pepper hash (hash with application-wide secret)
     *
     * @param string $data The data to hash
     * @param string $pepper The application secret
     * @param string $algorithm The hash algorithm
     * @return string The peppered hash
     */
    public static function peppered(string $data, string $pepper, string $algorithm = 'sha256'): string
    {
        return self::hmac($data, $pepper, $algorithm);
    }

    /**
     * Generate a checksum for data integrity verification
     *
     * @param string $data The data to checksum
     * @param string $algorithm The hash algorithm (default: sha256)
     * @return string The checksum
     */
    public static function checksum(string $data, string $algorithm = 'sha256'): string
    {
        return self::digest($data, $algorithm);
    }

    /**
     * Verify data against a checksum
     *
     * @param string $data The data to verify
     * @param string $checksum The expected checksum
     * @param string $algorithm The hash algorithm
     * @return bool True if checksum matches
     */
    public static function verifyChecksum(string $data, string $checksum, string $algorithm = 'sha256'): bool
    {
        $calculated = self::checksum($data, $algorithm);
        return self::equals($calculated, $checksum);
    }
}