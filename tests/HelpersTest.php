<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Webrium\App;
use Webrium\Directory;

/**
 * Unit Tests for global helper functions (src/Helpers/helpers.php)
 *
 * Coverage:
 *  - Path helpers point to the correct registered directories
 *  - storage_path() regression: must point to "storage", not "storage_app"
 *  - Path helpers apply sanitization (no traversal via helpers)
 *  - redirect() rejects header-injection URLs
 *  - env() default value
 *
 * Functions that call exit (redirect, back, respond) are tested only for
 * their validation logic; the actual redirect/exit behaviour is outside
 * the scope of unit tests.
 */
class HelpersTest extends TestCase
{
    private string $root;

    public static function setUpBeforeClass(): void
    {
        // Load helper functions once for the entire suite.
        $file = __DIR__ . '/../src/Helpers/helpers.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/webrium_helper_test_' . uniqid();
        mkdir($this->root, 0755, true);
        App::setRootPath($this->root);
        Directory::clear();
        Directory::initDefaultStructure();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->root, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->root);
        }
    }

    // =========================================================================
    // 1. Path helpers — correct targets
    // =========================================================================

    public function testRootPathReturnsRoot(): void
    {
        $this->assertSame($this->root, root_path());
    }

    public function testRootPathAppendsSegment(): void
    {
        $this->assertSame($this->root . '/composer.json', root_path('composer.json'));
    }

    public function testPublicPathPointsToPublic(): void
    {
        $this->assertSame($this->root . '/public', public_path());
    }

    public function testPublicPathAppendsSegment(): void
    {
        $this->assertSame($this->root . '/public/assets/app.js', public_path('assets/app.js'));
    }

    public function testAppPathPointsToApp(): void
    {
        $this->assertSame($this->root . '/app', app_path());
    }

    public function testConfigPathPointsToConfig(): void
    {
        $this->assertSame($this->root . '/app/Config', config_path());
    }

    public function testResourcePathPointsToViews(): void
    {
        $this->assertSame($this->root . '/app/Views', resource_path());
    }

    // =========================================================================
    // 2. storage_path regression — must be "storage", not "storage_app"
    // =========================================================================

    public function testStoragePathPointsToStorageRoot(): void
    {
        // This is the critical regression test: storage_path() used to point
        // to "storage/App" (the storage_app key). It must point to "storage".
        $this->assertSame($this->root . '/storage', storage_path());
    }

    public function testStoragePathAppendsSubpath(): void
    {
        $this->assertSame($this->root . '/storage/app/uploads', storage_path('app/uploads'));
    }

    public function testStoragePathAppSubdirectory(): void
    {
        // storage_path('app') replaces the old storage_app_path() need.
        $this->assertSame($this->root . '/storage/app', storage_path('app'));
    }

    // =========================================================================
    // 3. Path helpers inherit traversal protection (SECURITY)
    // =========================================================================

    public function testPublicPathBlocksTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        public_path('../../etc/passwd');
    }

    public function testStoragePathBlocksTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        storage_path('../../../etc/shadow');
    }

    public function testAppPathBlocksTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app_path('../../.env');
    }

    public function testConfigPathBlocksTraversal(): void
    {
        // config = app/Config (2 levels); need 3+ levels to escape.
        $this->expectException(InvalidArgumentException::class);
        config_path('../../../.env');
    }

    // =========================================================================
    // 4. redirect() header-injection prevention (SECURITY)
    // =========================================================================

    public function testRedirectRejectsCarriageReturn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        redirect("https://evil.com\r\nSet-Cookie: stolen=yes");
    }

    public function testRedirectRejectsNewline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        redirect("https://evil.com\nX-Injected: true");
    }

    public function testRedirectRejectsEmbeddedCRLF(): void
    {
        $this->expectException(InvalidArgumentException::class);
        redirect("/path\r\n\r\n<script>alert(1)</script>");
    }

    // =========================================================================
    // 5. env() default value
    // =========================================================================

    public function testEnvReturnsNullByDefault(): void
    {
        // A non-existent variable with no explicit default should be null,
        // not false. This is a regression test for the old default.
        $result = env('WEBRIUM_NONEXISTENT_VAR_' . uniqid());
        $this->assertNull($result);
    }
}