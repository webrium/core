<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Helpers\MimeMap;

/**
 * Unit Tests for Webrium\Helpers\MimeMap
 *
 * MimeMap is a pure, stateless lookup class: every method is static and
 * derives its result solely from its arguments and two private constant
 * tables (MAP and DANGEROUS). That makes it an ideal candidate for honest
 * behavioural tests — there is no I/O, no global state, and no time
 * dependence, so any failure here points at a genuine logic defect.
 *
 * Coverage:
 *  - mimeTypesFor(): known/unknown extensions, normalisation (case, dot, spaces)
 *  - isKnown(): membership in the MAP table
 *  - matches(): match / mismatch / unknown (null) tri-state and MIME normalisation
 *  - isDangerous(): blacklist membership and normalisation
 *  - extensionsForCategory(): each documented category + unknown category
 *  - Internal consistency between the category lists and the MAP table
 */
class MimeMapTest extends TestCase
{
    // =========================================================================
    // 1. mimeTypesFor()
    // =========================================================================

    public function testMimeTypesForKnownSingleType(): void
    {
        $this->assertSame(['image/png'], MimeMap::mimeTypesFor('png'));
    }

    public function testMimeTypesForKnownMultipleTypes(): void
    {
        // wav legitimately reports several MIME values depending on libmagic.
        $this->assertSame(
            ['audio/wav', 'audio/x-wav', 'audio/wave'],
            MimeMap::mimeTypesFor('wav')
        );
    }

    public function testMimeTypesForUnknownReturnsEmptyArray(): void
    {
        $this->assertSame([], MimeMap::mimeTypesFor('not-a-real-ext'));
    }

    public function testMimeTypesForIsCaseInsensitive(): void
    {
        $this->assertSame(['image/jpeg'], MimeMap::mimeTypesFor('JPG'));
        $this->assertSame(['image/jpeg'], MimeMap::mimeTypesFor('JpG'));
    }

    public function testMimeTypesForStripsLeadingDot(): void
    {
        $this->assertSame(['application/pdf', 'application/x-pdf'], MimeMap::mimeTypesFor('.pdf'));
    }

    public function testMimeTypesForTrimsSurroundingWhitespace(): void
    {
        $this->assertSame(['image/gif'], MimeMap::mimeTypesFor('  gif  '));
    }

    public function testMimeTypesForCombinedNormalisation(): void
    {
        // Upper-case + dot + whitespace should all be normalised away.
        $this->assertSame(['image/png'], MimeMap::mimeTypesFor('  .PNG '));
    }

    // =========================================================================
    // 2. isKnown()
    // =========================================================================

    public function testIsKnownTrueForMappedExtension(): void
    {
        $this->assertTrue(MimeMap::isKnown('mp4'));
    }

    public function testIsKnownFalseForUnmappedExtension(): void
    {
        $this->assertFalse(MimeMap::isKnown('xyz'));
    }

    public function testIsKnownNormalisesInput(): void
    {
        $this->assertTrue(MimeMap::isKnown('.MP3'));
        $this->assertTrue(MimeMap::isKnown(' zip '));
    }

    public function testIsKnownFalseForEmptyString(): void
    {
        $this->assertFalse(MimeMap::isKnown(''));
    }

    // =========================================================================
    // 3. matches() — tri-state: true / false / null
    // =========================================================================

    public function testMatchesReturnsTrueForCorrectMime(): void
    {
        $this->assertTrue(MimeMap::matches('png', 'image/png'));
    }

    public function testMatchesReturnsTrueForAnyAllowedAlias(): void
    {
        // bmp accepts two MIME values; both must match.
        $this->assertTrue(MimeMap::matches('bmp', 'image/bmp'));
        $this->assertTrue(MimeMap::matches('bmp', 'image/x-ms-bmp'));
    }

    public function testMatchesReturnsFalseForWrongMime(): void
    {
        $this->assertFalse(MimeMap::matches('png', 'image/jpeg'));
    }

    public function testMatchesReturnsNullForUnknownExtension(): void
    {
        // Unknown extension => "cannot decide", which is distinct from false.
        $this->assertNull(MimeMap::matches('unknownext', 'application/octet-stream'));
    }

    public function testMatchesNormalisesExtensionAndMime(): void
    {
        $this->assertTrue(MimeMap::matches('.PNG', '  IMAGE/PNG '));
    }

    public function testMatchesDistinguishesFalseFromNull(): void
    {
        // A known extension with a wrong MIME is strictly false (not null).
        $this->assertSame(false, MimeMap::matches('png', 'text/plain'));
        // An unknown extension is strictly null (not false).
        $this->assertSame(null, MimeMap::matches('zzz', 'text/plain'));
    }

    // =========================================================================
    // 4. isDangerous()
    // =========================================================================

    public function testIsDangerousTrueForPhp(): void
    {
        $this->assertTrue(MimeMap::isDangerous('php'));
    }

    public function testIsDangerousTrueForSvg(): void
    {
        // SVG is deliberately blacklisted (inline-JS / stored-XSS vector).
        $this->assertTrue(MimeMap::isDangerous('svg'));
    }

    public function testIsDangerousFalseForSafeExtension(): void
    {
        $this->assertFalse(MimeMap::isDangerous('png'));
    }

    public function testIsDangerousNormalisesInput(): void
    {
        $this->assertTrue(MimeMap::isDangerous('.PHP'));
        $this->assertTrue(MimeMap::isDangerous(' EXE '));
    }

    public function testIsDangerousFalseForEmptyString(): void
    {
        $this->assertFalse(MimeMap::isDangerous(''));
    }

    // =========================================================================
    // 5. extensionsForCategory()
    // =========================================================================

    public function testExtensionsForImageCategory(): void
    {
        $this->assertSame(
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'avif', 'heic', 'heif'],
            MimeMap::extensionsForCategory('image')
        );
    }

    public function testExtensionsForPdfCategory(): void
    {
        $this->assertSame(['pdf'], MimeMap::extensionsForCategory('pdf'));
    }

    public function testExtensionsForArchiveCategory(): void
    {
        $this->assertSame(['zip', 'rar', '7z', 'tar', 'gz'], MimeMap::extensionsForCategory('archive'));
    }

    public function testExtensionsForUnknownCategoryReturnsEmptyArray(): void
    {
        $this->assertSame([], MimeMap::extensionsForCategory('does-not-exist'));
    }

    /**
     * Category matching is exact (a match expression on the raw string), so
     * unlike the extension helpers it is NOT normalised. This test documents
     * that real behaviour rather than assuming leniency.
     */
    public function testExtensionsForCategoryIsCaseSensitive(): void
    {
        $this->assertSame([], MimeMap::extensionsForCategory('Image'));
    }

    /**
     * @dataProvider categoryProvider
     */
    public function testEveryCategoryExtensionIsKnownInTheMap(string $category): void
    {
        foreach (MimeMap::extensionsForCategory($category) as $ext) {
            $this->assertTrue(
                MimeMap::isKnown($ext),
                "Category '$category' lists extension '$ext' which is missing from the MIME MAP."
            );
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function categoryProvider(): array
    {
        return [
            'image'    => ['image'],
            'video'    => ['video'],
            'audio'    => ['audio'],
            'pdf'      => ['pdf'],
            'document' => ['document'],
            'archive'  => ['archive'],
        ];
    }

    /**
     * Safety invariant: no extension offered as an accepted category member
     * should also appear on the dangerous blacklist. If this ever fails, the
     * core is simultaneously allowing and forbidding the same extension.
     *
     * @dataProvider categoryProvider
     */
    public function testCategoryExtensionsAreNeverDangerous(string $category): void
    {
        foreach (MimeMap::extensionsForCategory($category) as $ext) {
            $this->assertFalse(
                MimeMap::isDangerous($ext),
                "Category '$category' lists extension '$ext' which is also on the dangerous blacklist."
            );
        }
    }
}