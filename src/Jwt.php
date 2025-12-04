<?php

declare(strict_types=1);

namespace webrium;

use InvalidArgumentException;

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
     * Constructor
     *
     * @param string $secretKey The secret key used to sign the token.
     * @param string $algo      The JWT algorithm (e.g., HS256, HS512). Default: HS256.
     * @throws InvalidArgumentException If the algorithm is not supported.
     */
    public function __construct(
        private string $secretKey,
        private string $algo = 'HS256'
    ) {
        if (!array_key_exists($this->algo, self::ALGORITHMS)) {
            throw new InvalidArgumentException("Algorithm '{$this->algo}' is not supported.");
        }
    }

    /**
     * Generate a JWT token.
     *
     * @param array $payload The payload data to include in the token.
     * @return string The signed JWT token.
     */
    public function generateToken(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algo
        ];

        // Encode Header and Payload
        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        // Create Signature
        $signature = $this->sign($base64UrlHeader . "." . $base64UrlPayload);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Verify a JWT token and retrieve the payload.
     *
     * @param string $jwt The JWT token string.
     * @return array|null The decoded payload if valid, null otherwise.
     */
    public function verifyToken(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $providedSignature] = $parts;

        // Re-calculate signature based on header and payload
        $calculatedSignature = $this->sign($header . '.' . $payload);
        $base64UrlCalculatedSignature = self::base64UrlEncode($calculatedSignature);

        // Verify signature using timing-attack safe comparison
        if (!hash_equals($base64UrlCalculatedSignature, $providedSignature)) {
            return null;
        }

        // Decode payload
        return self::decodePayload($payload);
    }

    /**
     * Get the payload from a JWT token without verification (Use with caution).
     *
     * @param string $jwt The JWT token.
     * @return array|null The decoded payload or null if format is invalid.
     */
    public static function getPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        return self::decodePayload($parts[1]);
    }

    /**
     * Generate the HMAC signature.
     *
     * @param string $data The data to sign (header.payload).
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
     * Decode the Base64Url encoded payload.
     *
     * @param string $base64UrlPayload
     * @return array|null
     */
    private static function decodePayload(string $base64UrlPayload): ?array
    {
        $json = self::base64UrlDecode($base64UrlPayload);
        $data = json_decode($json, true); // true for associative array

        return is_array($data) ? $data : null;
    }

    /**
     * Encode data to Base64Url format.
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode data from Base64Url format.
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode(string $data): string
    {
        $base64 = strtr($data, '-_', '+/');
        
        // Fix padding if necessary
        $remainder = strlen($base64) % 4;
        if ($remainder) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode($base64);
    }
}