<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\HttpClient;
use Webrium\HttpResponse;

/**
 * Unit / integration tests for Webrium\HttpClient.
 *
 * The suite boots a small PHP built-in HTTP server (see
 * tests/Fixtures/http_server.php) so every test runs a real HTTP round-trip
 * against cURL, while still being self-contained and deterministic.
 *
 * Coverage:
 *  - HTTP verbs (GET, POST, PUT, PATCH, DELETE)
 *  - Base URL + relative path composition
 *  - Headers, bearer/basic auth, custom User-Agent
 *  - Query parameters
 *  - JSON, form, multipart bodies (including real file upload)
 *  - Response helpers (status family, json(), headers(), header(), info(), throw())
 *  - onSuccess / onError callbacks
 *  - Status-code branching (2xx / 3xx / 4xx / 5xx)
 *  - Set-Cookie & duplicate headers
 *  - Case-insensitive header lookup
 *  - State isolation between calls on the same client
 *  - reset()
 *  - Security: header CRLF injection rejection
 *  - JSON encoding failure surfaced loudly (not silently sent as empty body)
 *
 * Tests of correct behavior fail today are explicitly labelled
 * "BUG #N — expected behaviour, currently failing".
 */
class HttpClientTest extends TestCase
{
    private static string $baseUrl;
    /** @var resource|null */
    private static $serverProcess = null;
    private static array $pipes = [];

    // =========================================================================
    // Lifecycle: spin up / tear down a real HTTP server
    // =========================================================================

    public static function setUpBeforeClass(): void
    {
        $port      = self::pickFreePort();
        $router    = __DIR__ . '/Fixtures/http_server.php';
        $command   = sprintf('exec php -S 127.0.0.1:%d %s', $port, escapeshellarg($router));
        self::$baseUrl = "http://127.0.0.1:{$port}";

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$serverProcess = proc_open($command, $descriptors, self::$pipes);

        if (!is_resource(self::$serverProcess)) {
            self::fail('Could not start PHP built-in HTTP server.');
        }

        // Wait until the server is accepting connections (max ~5s).
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($sock) {
                fclose($sock);
                return;
            }
            usleep(50_000);
        }

        self::tearDownAfterClass();
        self::fail("PHP test server did not become ready on port {$port}.");
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            $status = proc_get_status(self::$serverProcess);
            if ($status['running'] ?? false) {
                // SIGTERM the actual php process.
                @posix_kill($status['pid'], 15);
            }
            foreach (self::$pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    private static function pickFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            throw new \RuntimeException("Could not bind to an ephemeral port: $errstr");
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        return (int) substr($name, strrpos($name, ':') + 1);
    }

    // =========================================================================
    // 1. HTTP verbs — basic round-trip
    // =========================================================================

    public function testGetReturnsHttpResponse(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/echo');

        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertTrue($response->ok());
        $this->assertTrue($response->successful());
        $this->assertSame('GET', $response->json()['method']);
    }

    public function testPostSendsBody(): void
    {
        $response = HttpClient::make()
            ->withBody('hello=world')
            ->post(self::$baseUrl . '/echo');

        $payload = $response->json();
        $this->assertSame('POST', $payload['method']);
        $this->assertSame('hello=world', $payload['body']);
    }

    public function testPutSendsBody(): void
    {
        $response = HttpClient::make()->put(self::$baseUrl . '/echo', 'put-body');

        $payload = $response->json();
        $this->assertSame('PUT', $payload['method']);
        $this->assertSame('put-body', $payload['body']);
    }

    public function testPatchSendsBody(): void
    {
        $response = HttpClient::make()->patch(self::$baseUrl . '/echo', 'patch-body');

        $payload = $response->json();
        $this->assertSame('PATCH', $payload['method']);
        $this->assertSame('patch-body', $payload['body']);
    }

    public function testDeleteSendsRequest(): void
    {
        $response = HttpClient::make()->delete(self::$baseUrl . '/echo');

        $this->assertSame('DELETE', $response->json()['method']);
    }

    // =========================================================================
    // 2. Base URL composition — BUG #1
    // =========================================================================

    /**
     * BUG #1 — expected behaviour, currently failing.
     *
     * Calling get('/users') on a client with a base URL should issue a
     * request to <baseUrl>/users. Today HttpClient::url() replaces the
     * URL entirely instead of joining, so the base URL is lost.
     */
    public function testBaseUrlIsPreservedWhenRelativePathProvided(): void
    {
        $response = HttpClient::make(self::$baseUrl)->get('/echo');

        $this->assertTrue(
            $response->successful(),
            'A relative path should be appended to the configured base URL.'
        );
        $this->assertSame('/echo', $response->json()['path']);
    }

    public function testAbsoluteUrlIgnoresBaseUrl(): void
    {
        // If the caller supplies a full URL, it must win over the base URL.
        $response = HttpClient::make('http://wrong.invalid')
            ->get(self::$baseUrl . '/echo');

        $this->assertTrue($response->successful());
        $this->assertSame('/echo', $response->json()['path']);
    }

    // =========================================================================
    // 3. Query parameters
    // =========================================================================

    public function testWithQueryAppendsParameters(): void
    {
        $response = HttpClient::make()
            ->withQuery(['page' => 2, 'limit' => 50])
            ->get(self::$baseUrl . '/echo');

        $query = $response->json()['query'];
        parse_str($query, $parsed);
        $this->assertSame('2', $parsed['page']);
        $this->assertSame('50', $parsed['limit']);
    }

    public function testGetSecondArgumentMergesQuery(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/echo', ['q' => 'webrium']);

        parse_str($response->json()['query'], $parsed);
        $this->assertSame('webrium', $parsed['q']);
    }

    // =========================================================================
    // 4. Headers, auth, user-agent
    // =========================================================================

    public function testWithHeaderIsSent(): void
    {
        $response = HttpClient::make()
            ->withHeader('X-Custom', 'value')
            ->get(self::$baseUrl . '/echo');

        $headers = $response->json()['headers'];
        $this->assertSame('value', $headers['X-Custom'] ?? null);
    }

    public function testWithTokenSendsBearer(): void
    {
        $response = HttpClient::make()
            ->withToken('abc.def.ghi')
            ->get(self::$baseUrl . '/echo');

        $this->assertSame(
            'Bearer abc.def.ghi',
            $response->json()['headers']['Authorization'] ?? null
        );
    }

    public function testWithBasicAuthEncodesCredentials(): void
    {
        $response = HttpClient::make()
            ->withBasicAuth('alice', 's3cr3t')
            ->get(self::$baseUrl . '/echo');

        $expected = 'Basic ' . base64_encode('alice:s3cr3t');
        $this->assertSame(
            $expected,
            $response->json()['headers']['Authorization'] ?? null
        );
    }

    public function testWithUserAgentOverridesDefault(): void
    {
        $response = HttpClient::make()
            ->withUserAgent('MyApp/2.0')
            ->get(self::$baseUrl . '/echo');

        $this->assertSame(
            'MyApp/2.0',
            $response->json()['headers']['User-Agent'] ?? null
        );
    }

    /**
     * BUG #3 — expected behaviour, currently failing.
     *
     * A header value containing CR/LF must be rejected so that
     * attacker-controlled input cannot inject additional headers
     * (HTTP header injection).
     */
    public function testWithHeaderRejectsCrlfInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HttpClient::make()->withHeader('X-Evil', "value\r\nInjected: yes");
    }

    // =========================================================================
    // 5. JSON / Form / Multipart bodies
    // =========================================================================

    public function testAsJsonSendsJsonBody(): void
    {
        $response = HttpClient::make()
            ->asJson('POST', self::$baseUrl . '/echo', ['name' => 'Alice', 'n' => 1]);

        $payload = $response->json();
        $this->assertSame('POST', $payload['method']);
        $this->assertStringContainsString('application/json', $payload['headers']['Content-Type'] ?? '');
        $this->assertSame(['name' => 'Alice', 'n' => 1], json_decode($payload['body'], true));
    }

    /**
     * BUG #7 — expected behaviour, currently failing.
     *
     * If json_encode() returns false (e.g. on invalid UTF-8 bytes),
     * the client should signal the failure, not silently send an empty body.
     */
    public function testAsJsonThrowsOnEncodingFailure(): void
    {
        $this->expectException(\RuntimeException::class);

        // Invalid UTF-8 byte sequence — json_encode() will return false.
        HttpClient::make()->asJson(
            'POST',
            self::$baseUrl . '/echo',
            ['bad' => "\xB1\x31"]
        );
    }

    public function testAsFormSendsUrlEncodedBody(): void
    {
        $response = HttpClient::make()
            ->asForm(self::$baseUrl . '/echo', ['user' => 'alice', 'pw' => 'secret']);

        $payload = $response->json();
        $this->assertStringContainsString(
            'application/x-www-form-urlencoded',
            $payload['headers']['Content-Type'] ?? ''
        );
        parse_str($payload['body'], $parsed);
        $this->assertSame('alice', $parsed['user']);
        $this->assertSame('secret', $parsed['pw']);
    }

    /**
     * BUG #2 — expected behaviour, currently failing.
     *
     * asMultipart() with a string starting with '@' followed by a real path
     * is the documented way to attach a file. The '@/path' syntax was
     * removed from PHP in 7.0; the client should use CURLFile so the server
     * actually receives a file rather than the literal string "@/path/...".
     */
    public function testAsMultipartUploadsRealFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wbr');
        file_put_contents($tmp, 'hello-file-contents');

        try {
            $response = HttpClient::make()->asMultipart(self::$baseUrl . '/multipart/inspect', [
                'description' => 'Q1 report',
                'file'        => '@' . $tmp,
            ]);

            $payload = $response->json();
            $this->assertSame('Q1 report', $payload['post']['description'] ?? null);
            $this->assertArrayHasKey('file', $payload['files'],
                "Server should have seen 'file' as an uploaded file, not as a POST string. " .
                "If this fails, the '@/path' upload syntax silently sends the literal string."
            );
            $this->assertSame(strlen('hello-file-contents'), $payload['files']['file']['size']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testAsMultipartAcceptsPlainFields(): void
    {
        // Plain (non-file) multipart fields should work today — this guards
        // the non-buggy half of asMultipart().
        $response = HttpClient::make()->asMultipart(self::$baseUrl . '/multipart/inspect', [
            'description' => 'plain text field',
        ]);

        $payload = $response->json();
        $this->assertSame('plain text field', $payload['post']['description'] ?? null);
    }

    // =========================================================================
    // 6. Response helpers
    // =========================================================================

    public function testStatusFamilyHelpers(): void
    {
        $r201 = HttpClient::make()->get(self::$baseUrl . '/status/201');
        $this->assertTrue($r201->successful());
        $this->assertFalse($r201->ok()); // ok() is strictly 200
        $this->assertFalse($r201->failed());

        $r404 = HttpClient::make()->get(self::$baseUrl . '/status/404');
        $this->assertTrue($r404->clientError());
        $this->assertTrue($r404->failed());
        $this->assertFalse($r404->successful());

        $r500 = HttpClient::make()->get(self::$baseUrl . '/status/500');
        $this->assertTrue($r500->serverError());
        $this->assertTrue($r500->failed());
    }

    public function testRedirectIsFollowedByDefault(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/redirect/once');
        $this->assertTrue($response->ok());
        $this->assertSame('/echo', $response->json()['path']);
    }

    public function testRedirectNotFollowedWhenDisabled(): void
    {
        $response = HttpClient::make()
            ->withRedirects(false)
            ->get(self::$baseUrl . '/redirect/once');

        $this->assertTrue($response->redirect());
        $this->assertSame(302, $response->status());
    }

    public function testJsonDecodesBody(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/status/201');
        $this->assertSame(['created' => true], $response->json());
    }

    public function testJsonDecodesAsObjectWhenAssocIsFalse(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/status/201');
        $obj = $response->json(false);
        $this->assertIsObject($obj);
        $this->assertTrue($obj->created);
    }

    public function testHeaderAndHeadersAccessors(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/echo');

        $this->assertIsArray($response->headers());
        $this->assertNotEmpty($response->header('Content-Type'));
        $this->assertSame('fallback', $response->header('X-Missing', 'fallback'));
    }

    /**
     * BUG #5 — expected behaviour, currently failing.
     *
     * HTTP header names are case-insensitive. header('content-type') and
     * header('Content-Type') must return the same value regardless of how
     * the server cased the header.
     */
    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/case-headers');

        $this->assertSame(
            $response->header('X-Mixed-Case'),
            $response->header('x-mixed-case'),
            'Header lookup must be case-insensitive per RFC 7230.'
        );
        $this->assertSame('hello', $response->header('x-mixed-case'));
    }

    /**
     * BUG #4 — expected behaviour, currently failing.
     *
     * Multiple Set-Cookie headers (or any duplicated header) must be
     * preserved. Today they are stored as a flat string keyed by name,
     * so only the last one survives.
     */
    public function testSetCookieDuplicatesArePreserved(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/cookies/many');

        $cookies = $response->header('Set-Cookie');
        $this->assertIsArray(
            $cookies,
            'Duplicated headers like Set-Cookie should be exposed as an array.'
        );
        $this->assertCount(3, $cookies);
    }

    public function testInfoAndStatus(): void
    {
        $response = HttpClient::make()->get(self::$baseUrl . '/echo');

        $this->assertSame(200, $response->status());
        $this->assertIsArray($response->info());
        $this->assertIsFloat($response->info('total_time'));
    }

    public function testThrowOnFailedResponseThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        HttpClient::make()->get(self::$baseUrl . '/status/500')->throw();
    }

    public function testThrowOnSuccessReturnsSelf(): void
    {
        $r = HttpClient::make()->get(self::$baseUrl . '/echo');
        $this->assertSame($r, $r->throw());
    }

    public function testOnSuccessCallbackFires(): void
    {
        $fired = false;
        HttpClient::make()->get(self::$baseUrl . '/echo')->onSuccess(function ($r) use (&$fired) {
            $fired = true;
        });
        $this->assertTrue($fired);
    }

    public function testOnErrorCallbackFires(): void
    {
        $fired = false;
        HttpClient::make()->get(self::$baseUrl . '/status/500')->onError(function ($r) use (&$fired) {
            $fired = true;
        });
        $this->assertTrue($fired);
    }

    public function testOnSuccessDoesNotFireOnFailure(): void
    {
        $fired = false;
        HttpClient::make()->get(self::$baseUrl . '/status/500')->onSuccess(function () use (&$fired) {
            $fired = true;
        });
        $this->assertFalse($fired);
    }

    public function testToStringYieldsBody(): void
    {
        $r = HttpClient::make()->get(self::$baseUrl . '/status/201');
        $this->assertSame('{"created":true}', (string) $r);
    }

    // =========================================================================
    // 7. State isolation between requests on the same client
    // =========================================================================

    /**
     * BUG #6 — expected behaviour, currently failing.
     *
     * Query parameters set on a client must not leak into a subsequent
     * unrelated request issued by the same client.
     */
    public function testQueryParametersDoNotLeakBetweenRequests(): void
    {
        $client = HttpClient::make();

        $first = $client->withQuery(['page' => 1])->get(self::$baseUrl . '/echo');
        parse_str($first->json()['query'], $firstQuery);
        $this->assertSame('1', $firstQuery['page']);

        $second = $client->get(self::$baseUrl . '/echo');
        $this->assertSame(
            '',
            $second->json()['query'],
            "A second request on the same client must not inherit the previous request's query string."
        );
    }

    /**
     * BUG #6 — expected behaviour, currently failing.
     *
     * Body set on a client must not be silently re-sent with the next request.
     */
    public function testBodyDoesNotLeakBetweenRequests(): void
    {
        $client = HttpClient::make();

        $client->withBody('first-body')->post(self::$baseUrl . '/echo');

        $second = $client->post(self::$baseUrl . '/echo');
        $this->assertSame(
            '',
            $second->json()['body'],
            'A second POST on the same client must not re-send the previous body.'
        );
    }

    /**
     * BUG #8 — expected behaviour, currently failing.
     *
     * reset() is documented as restoring the client to a fresh state.
     * Today it leaves url, options, timeout, SSL, redirect, and user-agent
     * settings untouched, which contradicts the name and is surprising.
     */
    public function testResetRestoresClientToInitialState(): void
    {
        $client = HttpClient::make(self::$baseUrl)
            ->withHeader('X-A', 'a')
            ->withQuery(['x' => 1])
            ->withBody('payload')
            ->withUserAgent('Foo/1.0')
            ->timeout(7);

        $client->reset();

        $response = $client->get(self::$baseUrl . '/echo');
        $payload  = $response->json();

        $this->assertArrayNotHasKey('X-A', $payload['headers'],
            'reset() must clear custom headers.');
        $this->assertSame('', $payload['query'],
            'reset() must clear query parameters.');
        $this->assertSame('', $payload['body'],
            'reset() must clear the body.');
        $this->assertNotSame('Foo/1.0', $payload['headers']['User-Agent'] ?? '',
            'reset() should restore the default user agent.');
    }
}