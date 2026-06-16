<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webrium\Session;

/**
 * Unit Tests for Webrium\Session
 *
 * Coverage:
 *  - Lifecycle (start / isStarted)
 *  - Data storage and retrieval (set / get / has / exists / all)
 *  - Pull / forget / remove
 *  - Push / increment / decrement
 *  - Flash data lifecycle
 *  - Session ID validation (session-fixation hardening)
 *  - Cookie parameter secure defaults (Secure auto-detect / SameSite / HttpOnly)
 *  - Lifetime guard after start
 *  - Fingerprint binding and verification
 *
 * Methods that start a PHP session run in isolated processes so each test
 * gets a clean session and its own headers.
 */
class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        // Buffer output so session_start() is not blocked by emitted headers
        // inside the isolated test process.
        ob_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    // =========================================================================
    // 1. Storage and retrieval
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetAndGet(): void
    {
        Session::set('user_id', 42);
        $this->assertSame(42, Session::get('user_id'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('fallback', Session::get('missing', 'fallback'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetArrayOfValues(): void
    {
        Session::set(['a' => 1, 'b' => 2]);
        $this->assertSame(1, Session::get('a'));
        $this->assertSame(2, Session::get('b'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHasAndExists(): void
    {
        Session::set('present', 'x');
        Session::set('nullish', null);

        $this->assertTrue(Session::has('present'));
        $this->assertTrue(Session::has('nullish'));   // key exists
        $this->assertFalse(Session::exists('nullish')); // but value is null
        $this->assertFalse(Session::has('absent'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPullRemovesKey(): void
    {
        Session::set('token', 'abc');

        $this->assertSame('abc', Session::pull('token'));
        $this->assertFalse(Session::has('token'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testForgetRemovesKeys(): void
    {
        Session::set(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertTrue(Session::forget(['a', 'b']));
        $this->assertFalse(Session::has('a'));
        $this->assertTrue(Session::has('c'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPush(): void
    {
        Session::push('list', 'one');
        Session::push('list', 'two');

        $this->assertSame(['one', 'two'], Session::get('list'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIncrementAndDecrement(): void
    {
        $this->assertSame(1, Session::increment('counter'));
        $this->assertSame(4, Session::increment('counter', 3));
        $this->assertSame(3, Session::decrement('counter'));
    }

    // =========================================================================
    // 2. Flash data
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFlashIsReadableInSameRequest(): void
    {
        Session::flash('notice', 'Saved');
        $this->assertSame('Saved', Session::getFlash('notice'));
    }

    // =========================================================================
    // 3. Session ID validation (session-fixation hardening)
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIdRejectsInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Session::id('invalid id with spaces!');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIdRejectsOverlongValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Session::id(str_repeat('a', 200));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIdAcceptsWellFormedValue(): void
    {
        // Build an ID that matches this environment's session id configuration.
        $bits   = (int) (ini_get('session.sid_bits_per_character') ?: 4);
        $length = (int) (ini_get('session.sid_length') ?: 32);

        $alphabet = match ($bits) {
            6 => 'abcdefghijklmnopqrstuvwxyz0123456789',
            5 => 'abcdefghijklmnopqrstuv0123456789',
            default => 'abcdef0123456789',
        };

        $id = substr(str_repeat($alphabet, 10), 0, $length);

        $this->assertSame($id, Session::id($id));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIdCannotBeSetAfterStart(): void
    {
        Session::start();

        $this->expectException(RuntimeException::class);
        Session::id('abcdef0123456789');
    }

    // =========================================================================
    // 4. Cookie parameter secure defaults
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCookieDefaultsAreSecureFlags(): void
    {
        Session::setCookieParams();
        $params = session_get_cookie_params();

        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSecureFlagIsDisabledWithoutHttps(): void
    {
        unset($_SERVER['HTTPS']);
        Session::setCookieParams();

        $this->assertFalse(session_get_cookie_params()['secure']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSecureFlagIsEnabledUnderHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        Session::setCookieParams();

        $this->assertTrue(session_get_cookie_params()['secure']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCookieParamsCanBeOverridden(): void
    {
        Session::setCookieParams(0, '/', '', false, true, 'Strict');
        $params = session_get_cookie_params();

        $this->assertFalse($params['secure']);
        $this->assertSame('Strict', $params['samesite']);
    }

    // =========================================================================
    // 5. Lifetime guard
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetLifetimeBeforeStartSucceeds(): void
    {
        $this->assertTrue(Session::setLifetime(3600));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetLifetimeAfterStartIsRejected(): void
    {
        Session::start();

        // The guard emits an E_USER_WARNING; capture it and assert the result.
        $result = @Session::setLifetime(3600);
        $this->assertFalse($result);
    }

    // =========================================================================
    // 6. Fingerprint binding
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFingerprintVerifiesForSameClient(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
        Session::bind();

        $this->assertTrue(Session::verifyFingerprint());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFingerprintFailsForDifferentClient(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
        Session::bind();

        $_SERVER['HTTP_USER_AGENT'] = 'Attacker/9.9';
        $this->assertFalse(Session::verifyFingerprint());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testVerifyFingerprintTrueWhenUnbound(): void
    {
        // A session that was never bound should pass verification.
        $this->assertTrue(Session::verifyFingerprint());
    }
}