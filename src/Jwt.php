<?php
namespace webrium;

class Jwt
{
    private $secretKey; // The secret key used to sign the token
    private $algorithm; // The algorithm used to sign the token

    /**
     * Constructor
     *
     * @param string $secretKey The secret key used to sign the token
     * @param string $algorithm The algorithm used to sign the token (default: HS256)
     */
    public function __construct($secretKey, $algorithm = 'sha3-256')
    {
        $this->secretKey = $secretKey;
        $this->algorithm = $algorithm;
    }

 /**
     * Generate a JWT token
     *
     * @param array $payload The payload to include in the token
     * @return string The JWT token
     */
    public function generateToken($payload)
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];

        $header = json_encode($header);
        $payload = json_encode($payload);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac($this->algorithm, $base64UrlHeader . "." . $base64UrlPayload, $this->secretKey, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        return $jwt;
    }

 /**
     * Verify a JWT token
     *
     * @param string $jwt The JWT token to verify
     * @return mixed The decoded payload if the token is valid, false otherwise
     */
    public function verifyToken($jwt)
    {
        $jwtArr = explode('.', $jwt);

        if (count($jwtArr) !== 3) {
            return false;
        }

        $header = $jwtArr[0];
        $payload = $jwtArr[1];
        $signatureProvided = $jwtArr[2];

        $base64UrlHeader = $header;
        $base64UrlPayload = $payload;

        $signature = $this->base64UrlDecode($signatureProvided);

        $token = $base64UrlHeader . '.' . $base64UrlPayload;

        $data = hash_hmac($this->algorithm, $token, $this->secretKey, true);

        if (function_exists('hash_equals')) {
            $isValid = hash_equals($signatureProvided, $this->base64UrlEncode($data));
        } else {
            $isValid = $this->base64UrlEncode($data) === $signatureProvided;
        }

        return $isValid ? $this->decodeToken($payload) : false;
    }

     /**
     * Decode the payload of a JWT token
     *
     * @param string $payload The payload to decode
     * @return array The decoded payload
     */
    private function decodeToken($payload)
    {
        return json_decode(base64_decode($payload), true);
    }

    private function base64UrlEncode($text)
    {
        $base64 = base64_encode($text);
        $base64Url = strtr($base64, '+/', '-_');
        return rtrim($base64Url, '=');
    }

    private function base64UrlDecode($base64Url)
    {
        $base64 = strtr($base64Url, '-_', '+/');
        $text = base64_decode($base64);
        return $text;
    }
}
