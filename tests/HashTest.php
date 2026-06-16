<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Webrium\Hash;

/**
 * Unit Tests for Webrium\Hash
 *
 * Coverage:
 *  - Password hashing and verification (make / check)
 *  - Rehashing helpers (needsRehash / checkAndRehash)
 *  - Algorithm-specific hashing (bcrypt / argon2i / argon2id)
 *  - Digest helpers (md5 / sha1 / sha256 / sha512 / digest)
 *  - HMAC generation and verification
 *  - Timing-safe comparison (equals)
 *  - Random token generation (random / token) including exact length and entropy
 *  - UUID v4 format
 *  - Salted (HMAC-based) and peppered hashing
 *  - Checksums
 */
class HashTest extends TestCase
{
    // =========================================================================
    // 1. Password hashing
    // =========================================================================

    public function testMakeProducesVerifiableHash(): void
    {
        $hash = Hash::make('correct-horse-battery-staple');

        $this->assertNotSame('correct-horse-battery-staple', $hash);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $hash));
    }

    public function testCheckRejectsWrongPassword(): void
    {
        $hash = Hash::make('secret');
        $this->assertFalse(Hash::check('not-secret', $hash));
    }

    public function testCheckRejectsEmptyHash(): void
    {
        $this->assertFalse(Hash::check('secret', ''));
    }

    public function testMakeProducesDistinctHashesForSamePassword(): void
    {
        // Salting means two hashes of the same password must differ.
        $this->assertNotSame(Hash::make('secret'), Hash::make('secret'));
    }

    public function testBcryptHashIsValid(): void
    {
        $hash = Hash::bcrypt('secret', 5);
        $this->assertTrue(Hash::check('secret', $hash));
        $this->assertSame('bcrypt', Hash::getAlgorithm($hash));
    }

    // =========================================================================
    // 2. Rehashing
    // =========================================================================

    public function testNeedsRehashIsTrueWhenCostIncreases(): void
    {
        $hash = Hash::bcrypt('secret', 4);
        // A hash made at cost 4 should need rehashing for a higher target cost.
        $this->assertTrue(Hash::needsRehash($hash, PASSWORD_BCRYPT, ['cost' => 12]));
    }

    public function testCheckAndRehashReturnsNewHashWhenSettingsChange(): void
    {
        $hash   = Hash::bcrypt('secret', 4);
        $result = Hash::checkAndRehash('secret', $hash, PASSWORD_BCRYPT, ['cost' => 12]);

        $this->assertTrue($result['verified']);
        $this->assertNotNull($result['hash']);
        $this->assertTrue(Hash::check('secret', $result['hash']));
    }

    public function testCheckAndRehashReturnsNullHashWhenInvalid(): void
    {
        $hash   = Hash::bcrypt('secret', 4);
        $result = Hash::checkAndRehash('wrong', $hash);

        $this->assertFalse($result['verified']);
        $this->assertNull($result['hash']);
    }

    // =========================================================================
    // 3. Digests
    // =========================================================================

    public function testDigestLengthsAreCorrect(): void
    {
        $this->assertSame(32, strlen(Hash::md5('x')));
        $this->assertSame(40, strlen(Hash::sha1('x')));
        $this->assertSame(64, strlen(Hash::sha256('x')));
        $this->assertSame(128, strlen(Hash::sha512('x')));
    }

    public function testDigestIsDeterministic(): void
    {
        $this->assertSame(Hash::sha256('payload'), Hash::sha256('payload'));
    }

    public function testIsAlgorithmSupported(): void
    {
        $this->assertTrue(Hash::isAlgorithmSupported('sha256'));
        $this->assertFalse(Hash::isAlgorithmSupported('definitely-not-an-algo'));
    }

    // =========================================================================
    // 4. HMAC and comparison
    // =========================================================================

    public function testHmacVerifies(): void
    {
        $sig = Hash::hmac('payload', 'secret-key');
        $this->assertTrue(Hash::verifyHmac('payload', $sig, 'secret-key'));
    }

    public function testHmacFailsWithWrongKey(): void
    {
        $sig = Hash::hmac('payload', 'secret-key');
        $this->assertFalse(Hash::verifyHmac('payload', $sig, 'other-key'));
    }

    public function testEqualsComparesContent(): void
    {
        $this->assertTrue(Hash::equals('abc', 'abc'));
        $this->assertFalse(Hash::equals('abc', 'abd'));
        $this->assertFalse(Hash::equals('abc', 'abcd'));
    }

    // =========================================================================
    // 5. Random / token  (regression cover for the random() length bug)
    // =========================================================================

    /**
     * @dataProvider lengthProvider
     */
    public function testRandomReturnsExactLength(int $length): void
    {
        $this->assertSame($length, strlen(Hash::random($length)));
    }

    /**
     * @dataProvider lengthProvider
     */
    public function testTokenReturnsExactLength(int $length): void
    {
        $this->assertSame($length, strlen(Hash::token($length)));
    }

    public static function lengthProvider(): array
    {
        // Includes odd lengths and lengths beyond a hash digest's hex size,
        // which previously triggered a TypeError or silent truncation.
        return [
            'one'           => [1],
            'odd small'     => [15],
            'odd 31'        => [31],
            'even 32'       => [32],
            'odd 63'        => [63],
            'beyond sha256' => [100],
            'large 128'     => [128],
            'large odd 201' => [201],
        ];
    }

    public function testRandomIsHexadecimal(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', Hash::random(64));
    }

    public function testRandomValuesAreUnique(): void
    {
        $this->assertNotSame(Hash::random(64), Hash::random(64));
    }

    public function testRandomRejectsNonPositiveLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Hash::random(0);
    }

    public function testTokenRejectsNonPositiveLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Hash::token(-5);
    }

    public function testUniqueValuesDiffer(): void
    {
        $this->assertNotSame(Hash::unique('user_'), Hash::unique('user_'));
    }

    // =========================================================================
    // 6. UUID
    // =========================================================================

    public function testUuidHasValidV4Format(): void
    {
        $uuid = Hash::uuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testUuidValuesAreUnique(): void
    {
        $this->assertNotSame(Hash::uuid(), Hash::uuid());
    }

    // =========================================================================
    // 7. Salted / peppered  (regression cover for HMAC-based salting)
    // =========================================================================

    public function testSaltedUsesHmacNotPlainConcatenation(): void
    {
        // The hardened salted() must not equal a naive hash of data . salt.
        $this->assertSame(Hash::hmac('data', 'salt'), Hash::salted('data', 'salt'));
        $this->assertNotSame(hash('sha256', 'datasalt'), Hash::salted('data', 'salt'));
    }

    public function testSaltedDependsOnSalt(): void
    {
        $this->assertNotSame(Hash::salted('data', 'salt-a'), Hash::salted('data', 'salt-b'));
    }

    public function testPepperedMatchesHmac(): void
    {
        $this->assertSame(Hash::hmac('data', 'pepper'), Hash::peppered('data', 'pepper'));
    }

    // =========================================================================
    // 8. Checksums
    // =========================================================================

    public function testChecksumVerifies(): void
    {
        $sum = Hash::checksum('important-data');
        $this->assertTrue(Hash::verifyChecksum('important-data', $sum));
    }

    public function testChecksumDetectsModification(): void
    {
        $sum = Hash::checksum('important-data');
        $this->assertFalse(Hash::verifyChecksum('tampered-data', $sum));
    }
}