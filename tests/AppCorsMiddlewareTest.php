<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for App::corsMiddleware() after it was changed to make
 * its accept/reject decision from a single Header::cors() call instead of
 * calling isOriginAllowed() and then Header::cors() separately on the same
 * origin. The public contract (status codes, JSON error shape, preflight
 * handling) must stay identical.
 *
 * corsMiddleware() calls header()/http_response_code()/exit(), so - as with
 * HeaderRespondTest and HeaderCorsTest - each scenario runs in a fresh PHP
 * subprocess.
 */
class AppCorsMiddlewareTest extends TestCase
{
    /**
     * @return array{0: string, 1: int}
     */
    private function runMiddleware(?string $origin, string $method, array $allowedOrigins): array
    {
        $autoload = escapeshellarg(__DIR__ . '/../vendor/autoload.php');

        $script = sprintf(
            'require %s; use Webrium\\App; $origin = %s; if ($origin !== null) { $_SERVER["HTTP_ORIGIN"] = $origin; } $_SERVER["REQUEST_METHOD"] = %s; App::corsMiddleware(%s); echo "REACHED";',
            $autoload,
            var_export($origin, true),
            var_export($method, true),
            var_export($allowedOrigins, true)
        );

        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1';

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        return [implode("\n", $output), $exitCode];
    }

    public function testAllowedOriginContinuesPastMiddleware(): void
    {
        [$output, $exitCode] = $this->runMiddleware('https://game.niix.ir', 'GET', ['https://*.niix.ir']);

        $this->assertSame('REACHED', $output);
        $this->assertSame(0, $exitCode);
    }

    public function testDisallowedOriginGetsASingleWellFormedErrorResponse(): void
    {
        [$output, $exitCode] = $this->runMiddleware('https://evil.example', 'GET', ['https://story.niix.ir']);

        $this->assertSame(0, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'Expected a single JSON error body, got: ' . $output);
        $this->assertSame('CORS policy: Origin not allowed', $decoded['error']);
        $this->assertSame('https://evil.example', $decoded['origin']);
        $this->assertSame(['https://story.niix.ir'], $decoded['allowed_origins']);
    }

    public function testAllowedWildcardOriginPreflightExitsCleanlyWithNoBody(): void
    {
        [$output, $exitCode] = $this->runMiddleware('http://localhost:5173', 'OPTIONS', ['http://localhost:*']);

        $this->assertSame('', $output);
        $this->assertSame(0, $exitCode);
    }

    public function testRequestWithoutOriginHeaderContinuesPastMiddleware(): void
    {
        [$output, $exitCode] = $this->runMiddleware(null, 'GET', ['https://story.niix.ir']);

        $this->assertSame('REACHED', $output);
        $this->assertSame(0, $exitCode);
    }
}
