<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Header;

/**
 * Unit tests for Header::matchOrigin() - the single origin-matching
 * implementation shared by Header::cors() and App::isOriginAllowed().
 *
 * A pure function with no header()/exit() side effects, so it is tested
 * directly and in-process (no subprocess needed, unlike HeaderCorsTest).
 */
class HeaderMatchOriginTest extends TestCase
{
    public function testReturnsNullForEmptyAllowList(): void
    {
        $this->assertNull(Header::matchOrigin('https://story.niix.ir', []));
    }

    public function testMatchesExactOrigin(): void
    {
        $this->assertSame(
            'https://story.niix.ir',
            Header::matchOrigin('https://story.niix.ir', ['https://story.niix.ir'])
        );
    }

    public function testRejectsOriginNotInList(): void
    {
        $this->assertNull(Header::matchOrigin('https://evil.example', ['https://story.niix.ir']));
    }

    public function testMatchesSubdomainWildcard(): void
    {
        $this->assertSame(
            'https://game.niix.ir',
            Header::matchOrigin('https://game.niix.ir', ['https://*.niix.ir'])
        );
    }

    public function testMatchesPortWildcard(): void
    {
        $this->assertSame(
            'http://localhost:5173',
            Header::matchOrigin('http://localhost:5173', ['http://localhost:*'])
        );
    }

    public function testRejectsOriginSharingOnlyASuffixWithWildcardHost(): void
    {
        $this->assertNull(Header::matchOrigin('https://evilniix.ir', ['https://*.niix.ir']));
    }

    public function testLiteralStarEntryMatchesAnyOrigin(): void
    {
        $this->assertSame('*', Header::matchOrigin('https://anything.example', ['*']));
    }

    public function testLiteralStarEntryShortCircuitsBeforeOtherEntries(): void
    {
        // A bare "*" makes the rest of the list irrelevant - it should never
        // need to reach (and evaluate) other entries.
        $this->assertSame('*', Header::matchOrigin('https://anything.example', ['https://story.niix.ir', '*']));
    }

    public function testIgnoresTrailingSlashOnRequestOrigin(): void
    {
        $this->assertSame(
            'https://story.niix.ir',
            Header::matchOrigin('https://story.niix.ir/', ['https://story.niix.ir'])
        );
    }

    public function testIgnoresTrailingSlashOnAllowedEntry(): void
    {
        $this->assertSame(
            'https://story.niix.ir',
            Header::matchOrigin('https://story.niix.ir', ['https://story.niix.ir/'])
        );
    }

    public function testFirstMatchingEntryWinsAmongMultipleWildcardOrigins(): void
    {
        $allowed = [
            'https://story.niix.ir',
            'https://*.niix.ir',
            'http://localhost:*',
            'http://127.0.0.1:*',
        ];

        $this->assertSame('http://127.0.0.1:4174', Header::matchOrigin('http://127.0.0.1:4174', $allowed));
        $this->assertSame('http://localhost:5174', Header::matchOrigin('http://localhost:5174', $allowed));
        $this->assertSame('https://game.niix.ir', Header::matchOrigin('https://game.niix.ir', $allowed));
        $this->assertNull(Header::matchOrigin('https://other.example', $allowed));
    }
}
