<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Webrium\Jwt;

/**
 * Unit Tests for Webrium\Jwt
 *
 * Coverage:
 *  - Construction validation (algorithm, key length, leeway)
 *  - Token generation (structure, automatic iat, ttl/exp)
 *  - Round-trip verification across HS256/HS384/HS512
 *  - Signature integrity (tampering, wrong secret)
 *  - Algorithm-substitution attacks (alg:none, header is ignored)
 *  - Time-based claim validation (exp, nbf, iat) and leeway
 *  - Malformed input handling
 *  - getUnverifiedPayload behaviour
 */
class JwtTest extends TestCase
{
    /**
     * A 32-byte key, the minimum length accepted by the class.
     */
    private const SECRET = '0123456789abcdef0123456789abcdef';

    private Jwt $jwt;

    protected function setUp(): void
    {
        $this->jwt = new Jwt(self::SECRET);
    }

    /**
     * Helper to assemble a token from raw claims using a given secret,
     * so tests can craft tokens with arbitrary claim values.
     */
    private function craftToken(array $payload, string $secret = self::SECRET, string $algo = 'HS256'): string
    {
        $map = ['HS256' => 'sha256', 'HS384' => 'sha384', 'HS512' => 'sha512'];

        $encode = static fn (string $data): string =>
            rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => $algo]));
        $body   = $encode(json_encode($payload));
        $sig    = $encode(hash_hmac($map[$algo], "$header.$body", $secret, true));

        return "$header.$body.$sig";
    }

    // =========================================================================
    // 1. Construction
    // =========================================================================

    public function testConstructorAcceptsValidArguments(): void
    {
        $this->assertInstanceOf(Jwt::class, new Jwt(self::SECRET, 'HS512', 30));
    }

    public function testConstructorRejectsUnsupportedAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Jwt(self::SECRET, 'RS256');
    }

    public function testConstructorRejectsShortKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Jwt('too-short-key');
    }

    public function testConstructorRejectsNegativeLeeway(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Jwt(self::SECRET, 'HS256', -1);
    }

    // =========================================================================
    // 2. Generation
    // =========================================================================

    public function testGeneratedTokenHasThreeParts(): void
    {
        $token = $this->jwt->generateToken(['sub' => 1]);
        $this->assertCount(3, explode('.', $token));
    }

    public function testGeneratedTokenAddsIssuedAtClaim(): void
    {
        $token   = $this->jwt->generateToken(['sub' => 1]);
        $payload = $this->jwt->verifyToken($token);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('iat', $payload);
    }

    public function testGeneratedTokenWithoutTtlHasNoExpiration(): void
    {
        $token   = $this->jwt->generateToken(['sub' => 1]);
        $payload = $this->jwt->verifyToken($token);

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('exp', $payload);
    }

    public function testGeneratedTokenWithTtlSetsExpiration(): void
    {
        $token   = $this->jwt->generateToken(['sub' => 1], 3600);
        $payload = $this->jwt->verifyToken($token);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertGreaterThan(time(), $payload['exp']);
    }

    public function testGenerateTokenRejectsZeroTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->jwt->generateToken(['sub' => 1], 0);
    }

    public function testGenerateTokenRejectsNegativeTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->jwt->generateToken(['sub' => 1], -10);
    }

    // =========================================================================
    // 3. Round-trip verification
    // =========================================================================

    public function testValidTokenVerifiesAndReturnsPayload(): void
    {
        $token   = $this->jwt->generateToken(['sub' => 42, 'role' => 'admin'], 3600);
        $payload = $this->jwt->verifyToken($token);

        $this->assertIsArray($payload);
        $this->assertSame(42, $payload['sub']);
        $this->assertSame('admin', $payload['role']);
    }

    /**
     * @dataProvider algorithmProvider
     */
    public function testRoundTripForEachAlgorithm(string $algo): void
    {
        $jwt     = new Jwt(self::SECRET, $algo);
        $token   = $jwt->generateToken(['sub' => 7], 60);
        $payload = $jwt->verifyToken($token);

        $this->assertIsArray($payload);
        $this->assertSame(7, $payload['sub']);
    }

    public static function algorithmProvider(): array
    {
        return [
            'HS256' => ['HS256'],
            'HS384' => ['HS384'],
            'HS512' => ['HS512'],
        ];
    }

    // =========================================================================
    // 4. Signature integrity
    // =========================================================================

    public function testTamperedPayloadIsRejected(): void
    {
        $token = $this->jwt->generateToken(['sub' => 1, 'role' => 'user'], 3600);
        [$header, , $sig] = explode('.', $token);

        $forgedBody = rtrim(strtr(base64_encode(
            json_encode(['sub' => 1, 'role' => 'admin'])
        ), '+/', '-_'), '=');

        $this->assertNull($this->jwt->verifyToken("$header.$forgedBody.$sig"));
    }

    public function testTokenSignedWithDifferentSecretIsRejected(): void
    {
        $token = $this->jwt->generateToken(['sub' => 1], 3600);
        $other = new Jwt('ffffffffffffffffffffffffffffffff');

        $this->assertNull($other->verifyToken($token));
    }

    public function testTokenSignedWithDifferentAlgorithmIsRejected(): void
    {
        // Token crafted with HS512 must not verify under an HS256 instance.
        $token = $this->craftToken(['sub' => 1], self::SECRET, 'HS512');

        $this->assertNull($this->jwt->verifyToken($token));
    }

    public function testTokenSignedWithLowerAlgorithmIsRejected(): void
    {
        // The reverse direction: an HS256 token must not verify under HS384,
        // even though both share the same secret.
        $token = $this->craftToken(['sub' => 1], self::SECRET, 'HS256');
        $jwt   = new Jwt(self::SECRET, 'HS384');

        $this->assertNull($jwt->verifyToken($token));
    }

    public function testTruncatedSignatureIsRejected(): void
    {
        $token = $this->jwt->generateToken(['sub' => 1], 3600);
        [$header, $body, $sig] = explode('.', $token);

        $this->assertNull($this->jwt->verifyToken("$header.$body." . substr($sig, 0, -4)));
    }

    public function testZeroedSignatureOfCorrectLengthIsRejected(): void
    {
        // A signature that is well-formed base64url and the right byte length,
        // but all zero bytes, must still fail the constant-time comparison.
        $token = $this->jwt->generateToken(['sub' => 1], 3600);
        [$header, $body] = explode('.', $token);

        $zeroed = rtrim(strtr(base64_encode(str_repeat("\x00", 32)), '+/', '-_'), '=');

        $this->assertNull($this->jwt->verifyToken("$header.$body.$zeroed"));
    }

    // =========================================================================
    // 5. Algorithm-substitution attacks
    // =========================================================================

    public function testAlgNoneAttackIsRejected(): void
    {
        $encode = static fn (string $d): string =>
            rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $body   = $encode(json_encode(['sub' => 1, 'role' => 'admin']));

        // Unsigned token with empty signature.
        $this->assertNull($this->jwt->verifyToken("$header.$body."));
    }

    public function testAlgNoneAttackWithForgedSignatureIsRejected(): void
    {
        // A more deliberate variant: the attacker sets alg to "none" but also
        // supplies a non-empty signature. The header is ignored, so the forged
        // signature is checked against the real algorithm and fails.
        $encode = static fn (string $d): string =>
            rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $body   = $encode(json_encode(['sub' => 1, 'role' => 'admin']));

        $this->assertNull($this->jwt->verifyToken("$header.$body.deadbeef"));
    }

    public function testHeaderAlgorithmIsIgnoredDuringVerification(): void
    {
        // Header claims HS512 but the body is signed with HS256 (the configured algo).
        // Verification must succeed because the header is untrusted and ignored.
        $encode = static fn (string $d): string =>
            rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'HS512']));
        $body   = $encode(json_encode(['sub' => 99]));
        $sig    = $encode(hash_hmac('sha256', "$header.$body", self::SECRET, true));

        $payload = $this->jwt->verifyToken("$header.$body.$sig");

        $this->assertIsArray($payload);
        $this->assertSame(99, $payload['sub']);
    }

    // =========================================================================
    // 6. Time-based claims
    // =========================================================================

    public function testExpiredTokenIsRejected(): void
    {
        $token = $this->craftToken(['sub' => 1, 'exp' => time() - 100]);
        $this->assertNull($this->jwt->verifyToken($token));
    }

    public function testNotYetValidTokenIsRejected(): void
    {
        $token = $this->craftToken(['sub' => 1, 'nbf' => time() + 1000]);
        $this->assertNull($this->jwt->verifyToken($token));
    }

    public function testTokenIssuedInFutureIsRejected(): void
    {
        $token = $this->craftToken(['sub' => 1, 'iat' => time() + 1000]);
        $this->assertNull($this->jwt->verifyToken($token));
    }

    public function testLeewayAllowsRecentlyExpiredToken(): void
    {
        $token = $this->craftToken(['sub' => 1, 'exp' => time() - 30]);

        $strict  = new Jwt(self::SECRET, 'HS256', 0);
        $lenient = new Jwt(self::SECRET, 'HS256', 120);

        $this->assertNull($strict->verifyToken($token));
        $this->assertIsArray($lenient->verifyToken($token));
    }

    public function testNonNumericExpirationIsRejected(): void
    {
        $token = $this->craftToken(['sub' => 1, 'exp' => 'not-a-number']);
        $this->assertNull($this->jwt->verifyToken($token));
    }

    // =========================================================================
    // 7. Malformed input
    // =========================================================================

    public function testTokenWithTwoPartsIsRejected(): void
    {
        $this->assertNull($this->jwt->verifyToken('header.payload'));
    }

    public function testGarbageStringIsRejected(): void
    {
        $this->assertNull($this->jwt->verifyToken('not-a-jwt-at-all'));
    }

    public function testEmptySegmentsAreRejected(): void
    {
        $this->assertNull($this->jwt->verifyToken('..'));
    }

    public function testNonJsonPayloadIsRejected(): void
    {
        $encode = static fn (string $d): string =>
            rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body   = $encode('this is not json');
        $sig    = $encode(hash_hmac('sha256', "$header.$body", self::SECRET, true));

        $this->assertNull($this->jwt->verifyToken("$header.$body.$sig"));
    }

    /**
     * A payload that is valid JSON but not a JSON object (e.g. a bare number,
     * string, or null) must be rejected, since claims must be a structured set.
     *
     * @dataProvider nonObjectPayloadProvider
     */
    public function testNonObjectPayloadIsRejected(string $rawJson): void
    {
        $encode = static fn (string $d): string =>
            rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        $header = $encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body   = $encode($rawJson);
        $sig    = $encode(hash_hmac('sha256', "$header.$body", self::SECRET, true));

        $this->assertNull($this->jwt->verifyToken("$header.$body.$sig"));
    }

    public static function nonObjectPayloadProvider(): array
    {
        return [
            'number' => ['123'],
            'string' => ['"hello"'],
            'null'   => ['null'],
            'bool'   => ['true'],
        ];
    }

    public function testTokenWithExtraSegmentsIsRejected(): void
    {
        $this->assertNull($this->jwt->verifyToken('a.b.c.d'));
    }

    // =========================================================================
    // 8. getUnverifiedPayload
    // =========================================================================

    public function testGetUnverifiedPayloadReturnsClaimsWithoutValidation(): void
    {
        // An expired token: verifyToken rejects it, but the unverified reader still returns it.
        $token = $this->craftToken(['sub' => 5, 'exp' => time() - 100]);

        $this->assertNull($this->jwt->verifyToken($token));

        $unverified = Jwt::getUnverifiedPayload($token);
        $this->assertIsArray($unverified);
        $this->assertSame(5, $unverified['sub']);
    }

    public function testGetUnverifiedPayloadReturnsNullForMalformedToken(): void
    {
        $this->assertNull(Jwt::getUnverifiedPayload('only.two'));
    }
}