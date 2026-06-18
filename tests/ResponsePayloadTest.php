<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\ResponsePayload;

/**
 * Unit Tests for Webrium\ResponsePayload and the html()/json()/text() helpers.
 *
 * ResponsePayload is a pure value object: body, status code and a header map,
 * with chainable mutators and plain getters. It performs no I/O, so it is
 * fully and deterministically unit-testable. The three helpers are thin
 * factories that pre-attach the correct Content-Type (and, for json(), perform
 * the encoding), so they are tested by asserting on the payload they produce —
 * not by emitting real HTTP headers, which is not observable from CLI.
 *
 * The actual header emission and `exit` live in Header::respond(); that
 * side-effecting boundary is exercised separately (see HeaderRespondTest),
 * here we verify everything that decides WHAT will be sent.
 */
class ResponsePayloadTest extends TestCase
{
    // =========================================================================
    // 1. ResponsePayload value object
    // =========================================================================

    public function testDefaultsAreEmptyBody200AndNoHeaders(): void
    {
        $payload = new ResponsePayload();

        $this->assertSame('', $payload->getBody());
        $this->assertSame(200, $payload->getStatusCode());
        $this->assertSame([], $payload->getHeaders());
    }

    public function testConstructorStoresBodyStatusAndHeaders(): void
    {
        $payload = new ResponsePayload('hi', 201, ['X-Test' => '1']);

        $this->assertSame('hi', $payload->getBody());
        $this->assertSame(201, $payload->getStatusCode());
        $this->assertSame(['X-Test' => '1'], $payload->getHeaders());
    }

    public function testWithHeaderAddsHeaderAndReturnsSelf(): void
    {
        $payload = new ResponsePayload('body');
        $returned = $payload->withHeader('Content-Type', 'text/plain');

        $this->assertSame($payload, $returned, 'withHeader must be chainable (return $this)');
        $this->assertSame(['Content-Type' => 'text/plain'], $payload->getHeaders());
    }

    public function testWithHeaderReplacesExistingHeaderOfSameName(): void
    {
        $payload = (new ResponsePayload())
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Type', 'text/html');

        $this->assertSame(['Content-Type' => 'text/html'], $payload->getHeaders());
    }

    public function testWithHeaderKeepsMultipleDistinctHeaders(): void
    {
        $payload = (new ResponsePayload())
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('X-Frame-Options', 'DENY');

        $this->assertSame(
            ['Content-Type' => 'text/html', 'X-Frame-Options' => 'DENY'],
            $payload->getHeaders()
        );
    }

    public function testWithStatusChangesStatusAndReturnsSelf(): void
    {
        $payload  = new ResponsePayload('x');
        $returned = $payload->withStatus(404);

        $this->assertSame($payload, $returned);
        $this->assertSame(404, $payload->getStatusCode());
    }

    public function testRawPayloadHasNoHeaders(): void
    {
        // The documented "raw" behaviour: a payload with no header attached
        // reports an empty header map, so respond() sends none.
        $payload = new ResponsePayload('binary-or-raw', 200);
        $this->assertSame([], $payload->getHeaders());
    }

    // =========================================================================
    // 2. html() helper
    // =========================================================================

    public function testHtmlHelperSetsHtmlContentTypeAndBody(): void
    {
        $payload = html('<h1>Hi</h1>');

        $this->assertInstanceOf(ResponsePayload::class, $payload);
        $this->assertSame('<h1>Hi</h1>', $payload->getBody());
        $this->assertSame(200, $payload->getStatusCode());
        $this->assertSame(
            ['Content-Type' => 'text/html; charset=utf-8'],
            $payload->getHeaders()
        );
    }

    public function testHtmlHelperHonoursCustomStatus(): void
    {
        $payload = html('<p>created</p>', 201);
        $this->assertSame(201, $payload->getStatusCode());
    }

    // =========================================================================
    // 3. json() helper
    // =========================================================================

    public function testJsonHelperEncodesBodyAndSetsJsonContentType(): void
    {
        $payload = json(['ok' => true, 'n' => 3]);

        $this->assertSame('{"ok":true,"n":3}', $payload->getBody());
        $this->assertSame(200, $payload->getStatusCode());
        $this->assertSame(
            ['Content-Type' => 'application/json; charset=utf-8'],
            $payload->getHeaders()
        );
    }

    public function testJsonHelperHonoursCustomStatus(): void
    {
        $payload = json(['created' => true], 201);
        $this->assertSame(201, $payload->getStatusCode());
    }

    public function testJsonHelperEncodesScalarsAndNull(): void
    {
        $this->assertSame('42', json(42)->getBody());
        $this->assertSame('"hello"', json('hello')->getBody());
        $this->assertSame('null', json(null)->getBody());
    }

    public function testJsonHelperProducesErrorPayloadAndStatusOnEncodeFailure(): void
    {
        // A malformed UTF-8 byte sequence cannot be JSON-encoded; the helper
        // must fail safe with a 500 error body rather than emitting an empty
        // or broken response.
        $payload = json(["bad" => "\xB1\x31"]);

        $this->assertSame(500, $payload->getStatusCode());
        $this->assertSame('{"error":"Internal server error"}', $payload->getBody());
    }

    // =========================================================================
    // 4. text() helper
    // =========================================================================

    public function testTextHelperSetsPlainContentTypeAndBody(): void
    {
        $payload = text('Hello');

        $this->assertSame('Hello', $payload->getBody());
        $this->assertSame(200, $payload->getStatusCode());
        $this->assertSame(
            ['Content-Type' => 'text/plain; charset=utf-8'],
            $payload->getHeaders()
        );
    }

    public function testTextHelperHonoursCustomStatus(): void
    {
        $payload = text('gone', 410);
        $this->assertSame(410, $payload->getStatusCode());
    }
}
