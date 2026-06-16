<?php

declare(strict_types=1);

namespace Webrium;

use InvalidArgumentException;
use JsonException;

/**
 * A minimal, dependency-free JWT implementation supporting HMAC-SHA signatures.
 *
 * Security properties:
 *  - The signing algorithm is fixed at construction time and is never read from
 *    the untrusted token header, which prevents "alg: none" and algorithm
 *    confusion attacks.
 *  - Signatures are compared using a constant-time function to avoid timing attacks.
 *  - Standard time-based claims ("exp", "nbf", "iat") are validated on verification.
 */
class Jwt
{
    /**
     * Map JWT standard algorithm names to PHP hash algorithms.
     */
    private const ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    /**
     * Minimum recommended secret key length in bytes.
     *
     * HMAC-SHA256 has a 256-bit (32-byte) output, so a key shorter than this
     * provides no additional security and is more vulnerable to brute force.
     */
    private const MIN_KEY_LENGTH = 32;

    /**
     * Leeway in seconds to account for minor clock skew between systems
     * when validating time-based claims.
     */
    private int $leeway = 0;

    /**
     * @param string $secretKey The secret key used to sign and verify tokens.
     * @param string $algo      The JWT algorithm (HS256, HS384, HS512). Default: HS256.
     * @param int    $leeway    Allowed clock skew in seconds for time-based claims. Default: 0.
     *
     * @throws InvalidArgumentException If the algorithm is unsupported, the key is
     *                                  too short, or the leeway is negative.
     */
    public function __construct(
        private string $secretKey,
        private string $algo = 'HS256',
        int $leeway = 0
    ) {
        if (!array_key_exists($this->algo, self::ALGORITHMS)) {
            throw new InvalidArgumentException("Algorithm '{$this->algo}' is not supported.");
        }

        if (strlen($this->secretKey) < self::MIN_KEY_LENGTH) {
            throw new InvalidArgumentException(
                'Secret key must be at least ' . self::MIN_KEY_LENGTH . ' bytes long.'
            );
        }

        if ($leeway < 0) {
            throw new InvalidArgumentException('Leeway cannot be negative.');
        }

        $this->leeway = $leeway;
    }

    /**
     * Generate a signed JWT token.
     *
     * The "iat" (issued at) claim is added automatically. If $ttl is provided,
     * an "exp" (expiration) claim is set to the current time plus $ttl seconds.
     * Any "iat"/"exp" already present in $payload will be overwritten.
     *
     * @param array    $payload The custom claims to include in the token.
     * @param int|null $ttl      Token lifetime in seconds. Null means no expiration claim.
     *
     * @return string The signed JWT token.
     *
     * @throws InvalidArgumentException If $ttl is not positive, or encoding fails.
     */
    public function generateToken(array $payload, ?int $ttl = null): string
    {
        $now = time();
        $payload['iat'] = $now;

        if ($ttl !== null) {
            if ($ttl <= 0) {
                throw new InvalidArgumentException('TTL must be a positive number of seconds.');
            }
            $payload['exp'] = $now + $ttl;
        }

        $header = [
            'typ' => 'JWT',
            'alg' => $this->algo,
        ];

        $base64UrlHeader = self::base64UrlEncode(self::jsonEncode($header));
        $base64UrlPayload = self::base64UrlEncode(self::jsonEncode($payload));

        $signature = $this->sign($base64UrlHeader . '.' . $base64UrlPayload);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    /**
     * Verify a JWT token and return its payload.
     *
     * Verification checks, in order: structural format, signature validity,
     * and the time-based claims "nbf" (not before), "iat" (issued at) and
     * "exp" (expiration). Returns null if any check fails.
     *
     * @param string $jwt The JWT token string.
     *
     * @return array|null The decoded payload if the token is valid, null otherwise.
     */
    public function verifyToken(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $providedSignature] = $parts;

        if ($header === '' || $payload === '' || $providedSignature === '') {
            return null;
        }

        // Re-calculate the signature over the received header and payload.
        $calculatedSignature = $this->sign($header . '.' . $payload);
        $base64UrlCalculatedSignature = self::base64UrlEncode($calculatedSignature);

        // Constant-time comparison to avoid timing attacks.
        if (!hash_equals($base64UrlCalculatedSignature, $providedSignature)) {
            return null;
        }

        $data = self::decodePayload($payload);

        if ($data === null) {
            return null;
        }

        if (!$this->validateClaims($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Get the payload from a token WITHOUT verifying its signature or claims.
     *
     * The returned data is untrusted: it may be forged or expired. Never use it
     * for authentication or authorization decisions. Use verifyToken() for that.
     *
     * @param string $jwt The JWT token.
     *
     * @return array|null The decoded payload, or null if the format is invalid.
     */
    public static function getUnverifiedPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        return self::decodePayload($parts[1]);
    }

    /**
     * Validate the standard time-based claims on a decoded payload.
     *
     * @param array $payload The decoded payload.
     *
     * @return bool True if the claims are valid (or absent), false otherwise.
     */
    private function validateClaims(array $payload): bool
    {
        $now = time();

        // "nbf" (not before): token is not yet valid.
        if (isset($payload['nbf'])) {
            if (!is_numeric($payload['nbf']) || $now + $this->leeway < (int) $payload['nbf']) {
                return false;
            }
        }

        // "iat" (issued at): reject tokens issued in the future.
        if (isset($payload['iat'])) {
            if (!is_numeric($payload['iat']) || $now + $this->leeway < (int) $payload['iat']) {
                return false;
            }
        }

        // "exp" (expiration): token has expired.
        if (isset($payload['exp'])) {
            if (!is_numeric($payload['exp']) || $now - $this->leeway >= (int) $payload['exp']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate the HMAC signature over the given data.
     *
     * @param string $data The data to sign ("header.payload").
     *
     * @return string The raw binary signature.
     */
    private function sign(string $data): string
    {
        return hash_hmac(
            self::ALGORITHMS[$this->algo],
            $data,
            $this->secretKey,
            true
        );
    }

    /**
     * Decode a Base64Url-encoded payload into an associative array.
     *
     * @param string $base64UrlPayload The encoded payload segment.
     *
     * @return array|null The decoded payload, or null if decoding fails.
     */
    private static function decodePayload(string $base64UrlPayload): ?array
    {
        $json = self::base64UrlDecode($base64UrlPayload);

        if ($json === null) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Encode a value as JSON, throwing on failure.
     *
     * @param mixed $value The value to encode.
     *
     * @return string The JSON string.
     *
     * @throws InvalidArgumentException If the value cannot be encoded.
     */
    private static function jsonEncode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to encode JWT segment: ' . $e->getMessage());
        }
    }

    /**
     * Encode data to Base64Url format (RFC 4648 section 5, without padding).
     *
     * @param string $data Raw input data.
     *
     * @return string The Base64Url-encoded string.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode data from Base64Url format using strict validation.
     *
     * @param string $data The Base64Url-encoded string.
     *
     * @return string|null The decoded data, or null if the input is malformed.
     */
    private static function base64UrlDecode(string $data): ?string
    {
        $base64 = strtr($data, '-_', '+/');

        $remainder = strlen($base64) % 4;
        if ($remainder) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($base64, true);

        return $decoded === false ? null : $decoded;
    }
}