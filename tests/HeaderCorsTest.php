<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for Header::cors() origin matching.
 *
 * Wildcard entries in allowed_origins (e.g. "https://*.example.com",
 * "http://localhost:*") are a documented feature. Every real HTTP(S) origin
 * contains a "://" separator, so the pattern built from a wildcard entry must
 * not rely on a regex delimiter that collides with a literal "/" in that
 * origin string.
 *
 * cors() calls Header::set(), which calls the real header() function. That
 * collides with PHPUnit's own output buffer ("headers already sent") when run
 * in-process, so - as with HeaderRespondTest - each scenario runs in a fresh
 * PHP subprocess and is asserted on its actual stdout/stderr.
 */
class HeaderCorsTest extends TestCase
{
    /**
     * Run Header::cors() with a given request origin and config in a fresh
     * PHP process and return [output, exitCode]. Warnings are captured in the
     * output via 2>&1 so a regression shows up as readable failure text
     * instead of a silently wrong boolean.
     *
     * @return array{0: string, 1: int}
     */
    private function runCors(?string $origin, array $config): array
    {
        $autoload = escapeshellarg(__DIR__ . '/../vendor/autoload.php');

        $script = sprintf(
            'require %s; use Webrium\\Header; $origin = %s; if ($origin !== null) { $_SERVER["HTTP_ORIGIN"] = $origin; } var_dump(Header::cors(%s));',
            $autoload,
            var_export($origin, true),
            var_export($config, true)
        );

        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1';

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        return [implode("\n", $output), $exitCode];
    }

    public function testReturnsTrueWhenNoOriginHeaderIsPresent(): void
    {
        [$output] = $this->runCors(null, ['allowed_origins' => ['https://story.niix.ir']]);

        $this->assertSame('bool(true)', $output);
    }

    public function testAllowsExactOriginMatch(): void
    {
        [$output] = $this->runCors('https://story.niix.ir', ['allowed_origins' => ['https://story.niix.ir']]);

        $this->assertSame('bool(true)', $output);
    }

    public function testRejectsOriginNotInAllowList(): void
    {
        [$output] = $this->runCors('https://evil.example', ['allowed_origins' => ['https://story.niix.ir']]);

        $this->assertSame('bool(false)', $output);
    }

    public function testAllowsSubdomainWildcardOrigin(): void
    {
        [$output] = $this->runCors('https://game.niix.ir', ['allowed_origins' => ['https://*.niix.ir']]);

        $this->assertSame('bool(true)', $output);
    }

    public function testAllowsPortWildcardOrigin(): void
    {
        [$output] = $this->runCors('http://localhost:5173', ['allowed_origins' => ['http://localhost:*']]);

        $this->assertSame('bool(true)', $output);
    }

    public function testRejectsOriginThatOnlySharesASuffixWithWildcardHost(): void
    {
        [$output] = $this->runCors('https://evilniix.ir', ['allowed_origins' => ['https://*.niix.ir']]);

        $this->assertSame('bool(false)', $output);
    }

    public function testAllowsMatchingEntryAmongMultipleWildcardOrigins(): void
    {
        [$output] = $this->runCors('http://127.0.0.1:4174', [
            'allowed_origins' => [
                'https://story.niix.ir',
                'https://*.niix.ir',
                'http://localhost:*',
                'http://127.0.0.1:*',
            ],
        ]);

        $this->assertSame('bool(true)', $output);
    }

    public function testAllowsLiteralStarOriginWithoutCredentials(): void
    {
        [$output] = $this->runCors('https://anything.example', [
            'allowed_origins' => ['*'],
            'allow_credentials' => false,
        ]);

        $this->assertSame('bool(true)', $output);
    }

    public function testRejectsLiteralStarOriginWhenCredentialsAreAllowed(): void
    {
        [$output] = $this->runCors('https://anything.example', [
            'allowed_origins' => ['*'],
            'allow_credentials' => true,
        ]);

        $this->assertSame('bool(false)', $output);
    }
}
