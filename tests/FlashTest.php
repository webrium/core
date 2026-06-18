<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Flash;
use Webrium\Session;

/**
 * Unit Tests for Webrium\Flash
 *
 * Flash is a thin, session-backed convenience layer with three concerns:
 * validation errors, one-off messages (with optional HTML/JS rendering), and
 * "old input" repopulation. It keeps two private static caches ($errors,
 * $old) that memoise the first session read, and it consumes values from the
 * session on read (flash semantics) via Session::once().
 *
 * Because Flash reads and writes a real PHP session and also caches in static
 * properties, every test runs in an isolated process (matching SessionTest)
 * so each gets a clean session AND fresh static caches. Output is buffered so
 * session_start() is not blocked by emitted headers. We exercise the genuine
 * Session + input() collaborators rather than stubbing them, so a regression
 * in either the cache logic or the flash-consumption semantics will surface
 * here.
 *
 * Coverage:
 *  - withError(): string -> 'default' key; array -> field map
 *  - errors() / hasErrors() / error() / hasError()
 *  - errors() caching + one-time consumption from the session
 *  - clearErrors(): wipes session + cache
 *  - success/errorMessage/message + getMessage() rendering and raw mode
 *  - getMessage() XSS escaping and one-time consumption
 *  - hasMessage() / clearMessage()
 *  - withInput() + old() / oldAll() repopulation and defaults
 *  - fluent return values (static instance)
 */
class FlashTest extends TestCase
{
    /**
     * The project's composer autoload only registers the Webrium\ PSR-4
     * namespace; the global helper functions in src/Helpers/helpers.php are
     * NOT auto-loaded. Flash::withInput() calls the global input() helper, so
     * we load the helpers file once for this test class — mirroring how
     * HelpersTest bootstraps the same file.
     */
    public static function setUpBeforeClass(): void
    {
        // Flash::withInput() relies on the global input() helper, which lives in
        // src/Helpers/helpers.php. That file is loaded by Composer's "files"
        // autoload (or by App::initialize() at runtime), but we must not depend
        // on load order or on a particular bootstrap here: guarantee the helper
        // exists so this test class is self-contained and honest in any setup.
        if (!function_exists('input')) {
            require_once __DIR__ . '/../src/Helpers/helpers.php';
        }
    }

    protected function setUp(): void
    {
        // Buffer output so session_start() inside the isolated process is not
        // blocked by previously emitted headers.
        ob_start();
        $_SESSION = [];
        $this->resetFlashCaches();
    }

    protected function tearDown(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Reset Flash's private static caches so a memoised read from one test
     * cannot bleed into another within the same process.
     */
    private function resetFlashCaches(): void
    {
        $ref = new \ReflectionClass(Flash::class);
        foreach (['errors', 'old'] as $name) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    // =========================================================================
    // 1. Errors
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWithErrorStringStoresUnderDefaultKey(): void
    {
        Flash::withError('Something went wrong.');

        // Read straight from the session to confirm the storage shape.
        $this->assertSame(
            ['default' => 'Something went wrong.'],
            Session::get('_flash_errors')
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWithErrorArrayStoresFieldMap(): void
    {
        Flash::withError(['email' => 'Invalid email.', 'name' => 'Required.']);

        $this->assertSame(
            ['email' => 'Invalid email.', 'name' => 'Required.'],
            Session::get('_flash_errors')
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWithErrorReturnsFlashInstanceForFluency(): void
    {
        $this->assertInstanceOf(Flash::class, Flash::withError('x'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHasErrorsTrueWhenErrorsPresent(): void
    {
        Flash::withError(['email' => 'Invalid.']);
        $this->resetFlashCaches(); // simulate a fresh request reading the flash

        $this->assertTrue(Flash::hasErrors());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHasErrorsFalseWhenNoneStored(): void
    {
        $this->assertFalse(Flash::hasErrors());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testErrorReturnsMessageForField(): void
    {
        Flash::withError(['email' => 'Invalid email.']);
        $this->resetFlashCaches();

        $this->assertSame('Invalid email.', Flash::error('email'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testErrorReturnsNullForUnknownField(): void
    {
        Flash::withError(['email' => 'Invalid email.']);
        $this->resetFlashCaches();

        $this->assertNull(Flash::error('name'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHasErrorReflectsFieldPresence(): void
    {
        Flash::withError(['email' => 'Invalid email.']);
        $this->resetFlashCaches();

        $this->assertTrue(Flash::hasError('email'));
        $this->assertFalse(Flash::hasError('name'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testErrorsAreConsumedFromSessionOnRead(): void
    {
        Flash::withError(['email' => 'Invalid.']);
        $this->resetFlashCaches();

        // First read returns the data (and consumes it from the session).
        $this->assertSame(['email' => 'Invalid.'], Flash::errors());
        $this->assertFalse(
            Session::has('_flash_errors'),
            'errors() must consume the flashed value from the session (flash semantics).'
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testErrorsAreCachedAcrossRepeatedCalls(): void
    {
        Flash::withError(['email' => 'Invalid.']);
        $this->resetFlashCaches();

        $first = Flash::errors();
        // Even though the session value is consumed, the in-request cache keeps it.
        $second = Flash::errors();

        $this->assertSame($first, $second);
        $this->assertSame(['email' => 'Invalid.'], $second);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testClearErrorsRemovesFromSessionAndCache(): void
    {
        Flash::withError(['email' => 'Invalid.']);
        $this->resetFlashCaches();

        Flash::clearErrors();

        $this->assertFalse(Flash::hasErrors());
        $this->assertSame([], Flash::errors());
        $this->assertFalse(Session::has('_flash_errors'));
    }

    // =========================================================================
    // 2. Messages
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMessageStoresTextAndType(): void
    {
        Flash::success('Saved.');

        $this->assertSame('Saved.', Session::get('_flash_message'));
        $this->assertSame('success', Session::get('_flash_message_type'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMessageHelpersSetExpectedTypes(): void
    {
        Flash::errorMessage('Oops.');
        $this->assertSame('error', Session::get('_flash_message_type'));

        Flash::message('Neutral.');
        $this->assertSame('normal', Session::get('_flash_message_type'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMessageHelpersReturnFlashInstance(): void
    {
        $this->assertInstanceOf(Flash::class, Flash::success('a'));
        $this->assertInstanceOf(Flash::class, Flash::errorMessage('b'));
        $this->assertInstanceOf(Flash::class, Flash::message('c'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHasMessageReflectsPresence(): void
    {
        $this->assertFalse(Flash::hasMessage());

        Flash::message('Hi');
        $this->assertTrue(Flash::hasMessage());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetMessageRawReturnsPlainText(): void
    {
        Flash::message('Plain text');
        $this->assertSame('Plain text', Flash::getMessage(raw: true));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetMessageReturnsFalseWhenNoMessage(): void
    {
        $this->assertFalse(Flash::getMessage());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetMessageIsConsumedAfterRead(): void
    {
        Flash::message('Once only');

        $this->assertSame('Once only', Flash::getMessage(raw: true));
        // Second read should find nothing left (flash semantics).
        $this->assertFalse(Flash::getMessage(raw: true));
        $this->assertFalse(Flash::hasMessage());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetMessageFallsBackToAlertTemplate(): void
    {
        // With no MESSAGE_SCRIPT* constants defined, rendering falls back to a
        // JS alert() wrapper around the escaped text.
        Flash::message('Hello');

        $rendered = Flash::getMessage();
        $this->assertIsString($rendered);
        $this->assertStringContainsString("alert('Hello')", $rendered);
        $this->assertStringContainsString('<script>', $rendered);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetMessageEscapesHtmlToPreventXss(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        Flash::message($payload);

        $rendered = Flash::getMessage();

        // The raw tag must not survive into the rendered output.
        $this->assertStringNotContainsString('<img', $rendered);
        // It must appear in escaped form instead.
        $this->assertStringContainsString('&lt;img', $rendered);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testClearMessageRemovesMessageAndType(): void
    {
        Flash::success('Saved.');
        Flash::clearMessage();

        $this->assertFalse(Flash::hasMessage());
        $this->assertFalse(Session::has('_flash_message_type'));
    }

    // =========================================================================
    // 3. Old input
    // =========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWithInputStoresCurrentRequestInput(): void
    {
        // Drive the genuine input() helper via a GET request.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['email' => 'a@b.com', 'name' => 'Ada'];

        Flash::withInput();

        $this->assertSame(
            ['email' => 'a@b.com', 'name' => 'Ada'],
            Session::get('_flash_old')
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWithInputReturnsFlashInstance(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $this->assertInstanceOf(Flash::class, Flash::withInput());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOldReturnsStoredValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['email' => 'a@b.com'];
        Flash::withInput();
        $this->resetFlashCaches(); // next-request read

        $this->assertSame('a@b.com', Flash::old('email'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOldReturnsDefaultForMissingField(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['email' => 'a@b.com'];
        Flash::withInput();
        $this->resetFlashCaches();

        $this->assertSame('fallback', Flash::old('phone', 'fallback'));
        $this->assertNull(Flash::old('phone'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOldAllReturnsEverything(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['email' => 'a@b.com', 'name' => 'Ada'];
        Flash::withInput();
        $this->resetFlashCaches();

        $this->assertSame(['email' => 'a@b.com', 'name' => 'Ada'], Flash::oldAll());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOldAllReturnsEmptyArrayWhenNothingFlashed(): void
    {
        $this->assertSame([], Flash::oldAll());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOldIsConsumedFromSessionOnFirstRead(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['email' => 'a@b.com'];
        Flash::withInput();
        $this->resetFlashCaches();

        // First access loads and consumes from the session.
        Flash::old('email');
        $this->assertFalse(
            Session::has('_flash_old'),
            'old input must be consumed from the session on first read.'
        );
    }
}