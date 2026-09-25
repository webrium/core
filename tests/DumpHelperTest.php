<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Unit / integration tests for the global dump() helper (src/Helpers/helpers.php).
 *
 * dump() branches on PHP_SAPI, a constant that is genuinely "cli" while
 * PHPUnit itself is running — so the CLI branch is tested directly,
 * in-process. The non-CLI (HTML-wrapped) branch can only be observed under
 * a real non-CLI SAPI, so those tests boot the PHP built-in server (SAPI
 * "cli-server") against tests/Fixtures/dump_fixture.php and inspect a real
 * HTTP response, the same approach HttpClientTest uses.
 *
 * Coverage:
 *  - CLI: output is plain var_dump(), no HTML wrapper
 *  - CLI: multiple arguments each get their own var_dump() block
 *  - Web: output is wrapped in a styled <pre> block
 *  - Web: dumped content is HTML-escaped (XSS safety)
 *  - Web: multiple arguments produce multiple <pre> blocks
 */
class DumpHelperTest extends TestCase
{
    private static string $baseUrl;
    /** @var resource|null */
    private static $serverProcess = null;
    private static array $pipes = [];

    public static function setUpBeforeClass(): void
    {
        $file = __DIR__ . '/../src/Helpers/helpers.php';
        if (is_file($file)) {
            require_once $file;
        }

        $port    = self::pickFreePort();
        $router  = __DIR__ . '/Fixtures/dump_fixture.php';
        $command = sprintf('exec php -S 127.0.0.1:%d %s', $port, escapeshellarg($router));
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
    // 1. CLI branch (in-process — PHPUnit genuinely runs under SAPI "cli")
    // =========================================================================

    public function testCliOutputIsPlainVarDump(): void
    {
        $this->assertSame('cli', PHP_SAPI);

        ob_start();
        dump('hello');
        $output = ob_get_clean();

        $this->assertStringContainsString('string(5) "hello"', $output);
        $this->assertStringNotContainsString('<pre', $output);
    }

    public function testCliOutputDumpsEachArgumentSeparately(): void
    {
        ob_start();
        dump('first', ['a' => 1]);
        $output = ob_get_clean();

        $this->assertStringContainsString('string(5) "first"', $output);
        $this->assertStringContainsString('array(1)', $output);
    }

    // =========================================================================
    // 2. Non-CLI branch (real HTTP round-trip, SAPI "cli-server")
    // =========================================================================

    public function testWebOutputIsWrappedInStyledPre(): void
    {
        $body = file_get_contents(self::$baseUrl . '/?case=simple');

        $this->assertStringContainsString('<pre style=', $body);
        $this->assertStringContainsString('string(5) &quot;hello&quot;', $body);
    }

    public function testWebOutputEscapesHtmlInDumpedValues(): void
    {
        $body = file_get_contents(self::$baseUrl . '/?case=xss');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testWebOutputProducesOnePreBlockPerArgument(): void
    {
        $body = file_get_contents(self::$baseUrl . '/?case=multi');

        $this->assertSame(2, substr_count($body, '<pre style='));
    }
}
