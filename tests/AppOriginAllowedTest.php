<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\App;

/**
 * Regression coverage for App::isOriginAllowed() origin matching.
 *
 * Mirrors Header::cors(): allowed_origins may contain wildcard patterns such
 * as "https://*.example.com", and every real HTTP(S) origin contains "://".
 */
class AppOriginAllowedTest extends TestCase
{
    protected function setUp(): void
    {
        App::setCorsOrigins([]);
    }

    public function testExactOriginMatchIsAllowed(): void
    {
        App::setCorsOrigins(['https://story.niix.ir']);

        $this->assertTrue(App::isOriginAllowed('https://story.niix.ir'));
    }

    public function testOriginNotInAllowListIsRejected(): void
    {
        App::setCorsOrigins(['https://story.niix.ir']);

        $this->assertFalse(App::isOriginAllowed('https://evil.example'));
    }

    public function testSubdomainWildcardOriginIsAllowed(): void
    {
        App::setCorsOrigins(['https://*.niix.ir']);

        $this->assertTrue(App::isOriginAllowed('https://game.niix.ir'));
    }

    public function testPortWildcardOriginIsAllowed(): void
    {
        App::setCorsOrigins(['http://localhost:*']);

        $this->assertTrue(App::isOriginAllowed('http://localhost:5173'));
    }

    public function testOriginSharingOnlyASuffixWithWildcardHostIsRejected(): void
    {
        App::setCorsOrigins(['https://*.niix.ir']);

        $this->assertFalse(App::isOriginAllowed('https://evilniix.ir'));
    }

    public function testLiteralStarEntryAllowsAnyOrigin(): void
    {
        App::setCorsOrigins(['*']);

        $this->assertTrue(App::isOriginAllowed('https://anything.example'));
    }
}
