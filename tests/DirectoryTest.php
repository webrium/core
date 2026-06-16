<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Webrium\App;
use Webrium\Directory;

/**
 * Unit Tests for Webrium\Directory
 *
 * Coverage:
 *  - Directory registration and retrieval
 *  - Path resolution and append
 *  - Path traversal prevention (sanitization)
 *  - Safe recursive deletion (root boundary + symlink safety)
 *  - Safe directory emptying
 *  - Directory creation
 *  - Default structure initialization
 */
class DirectoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/webrium_dir_test_' . uniqid();
        mkdir($this->root, 0755, true);
        App::setRootPath($this->root);
        Directory::clear();
    }

    protected function tearDown(): void
    {
        // Clean up: remove test root if it still exists.
        if (is_dir($this->root)) {
            $this->removeDir($this->root);
        }
    }

    /**
     * Recursively remove a directory (test utility, not the method under test).
     */
    private function removeDir(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }

    /**
     * Create a directory tree inside the test root.
     */
    private function createTree(array $dirs): void
    {
        foreach ($dirs as $dir) {
            mkdir($this->root . '/' . $dir, 0755, true);
        }
    }

    /**
     * Create a file inside the test root.
     */
    private function createFile(string $relativePath, string $content = ''): void
    {
        $path = $this->root . '/' . $relativePath;
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    // =========================================================================
    // 1. Registration and retrieval
    // =========================================================================

    public function testSetAndGet(): void
    {
        Directory::set('cache', 'storage/cache');
        $this->assertSame('storage/cache', Directory::get('cache'));
    }

    public function testHasReturnsTrueForRegistered(): void
    {
        Directory::set('logs', 'storage/logs');
        $this->assertTrue(Directory::has('logs'));
        $this->assertFalse(Directory::has('nonexistent'));
    }

    public function testPathReturnsAbsolute(): void
    {
        Directory::set('public', 'public');
        $this->assertSame($this->root . '/public', Directory::path('public'));
    }

    public function testPathReturnsNullForUnregistered(): void
    {
        $this->assertNull(Directory::path('nonexistent'));
    }

    public function testPathAppendsSubPath(): void
    {
        Directory::set('public', 'public');
        $this->assertSame($this->root . '/public/css/app.css', Directory::path('public', 'css/app.css'));
    }

    public function testForgetRemovesDirectory(): void
    {
        Directory::set('temp', 'tmp');
        $this->assertTrue(Directory::forget('temp'));
        $this->assertFalse(Directory::has('temp'));
    }

    public function testClearRemovesAll(): void
    {
        Directory::set('a', 'a');
        Directory::set('b', 'b');
        Directory::clear();
        $this->assertSame([], Directory::all());
    }

    // =========================================================================
    // 2. Path traversal prevention (SECURITY)
    // =========================================================================

    public function testPathBlocksTraversalAboveRoot(): void
    {
        Directory::set('uploads', 'public/uploads');

        // Three levels of ".." from public/uploads → escapes root.
        $this->expectException(InvalidArgumentException::class);
        Directory::path('uploads', '../../../etc/passwd');
    }

    public function testPathBlocksMultipleLevelTraversal(): void
    {
        Directory::set('uploads', 'public/uploads');

        $this->expectException(InvalidArgumentException::class);
        Directory::path('uploads', '../../../../../../../etc/shadow');
    }

    public function testPathBlocksTraversalViaSubdirectory(): void
    {
        Directory::set('uploads', 'public/uploads');

        // Descend then ascend past root: subdir/../../../../ → escapes.
        $this->expectException(InvalidArgumentException::class);
        Directory::path('uploads', 'subdir/../../../../etc/passwd');
    }

    public function testPathAllowsTraversalWithinRoot(): void
    {
        Directory::set('uploads', 'public/uploads');

        // Going up two levels from public/uploads lands at root — that's still
        // inside the application, so it should be allowed.
        $result = Directory::path('uploads', '../../composer.json');
        $this->assertSame($this->root . '/composer.json', $result);
    }

    public function testPathAllowsSafeDotSegments(): void
    {
        Directory::set('uploads', 'public/uploads');

        // "./file.txt" is safe — the dot segment resolves to the same directory.
        $result = Directory::path('uploads', './images/photo.jpg');
        $this->assertSame($this->root . '/public/uploads/images/photo.jpg', $result);
    }

    public function testPathAllowsNormalNestedPaths(): void
    {
        Directory::set('storage', 'storage');

        $result = Directory::path('storage', 'app/user/42/avatar.png');
        $this->assertSame($this->root . '/storage/app/user/42/avatar.png', $result);
    }

    public function testPathWithoutAppendIsNotSanitized(): void
    {
        // path() without $append should work without sanitization — it's a
        // registered name, not user input.
        Directory::set('storage', 'storage');
        $this->assertSame($this->root . '/storage', Directory::path('storage'));
    }

    // =========================================================================
    // 3. delete() — root boundary and symlink safety (SECURITY)
    // =========================================================================

    public function testDeleteRemovesDirectoryAndContents(): void
    {
        $this->createTree(['target/sub']);
        $this->createFile('target/a.txt', 'hello');
        $this->createFile('target/sub/b.txt', 'world');

        Directory::set('target', 'target');

        $this->assertTrue(Directory::delete('target'));
        $this->assertFalse(is_dir($this->root . '/target'));
    }

    public function testDeleteReturnsFalseForNonExistent(): void
    {
        Directory::set('ghost', 'nonexistent');
        $this->assertFalse(Directory::delete('ghost'));
    }

    public function testDeleteRejectsPathOutsideRoot(): void
    {
        // Create a directory outside the test root.
        $outside = sys_get_temp_dir() . '/webrium_outside_' . uniqid();
        mkdir($outside, 0755, true);
        file_put_contents($outside . '/precious.txt', 'do not delete');

        // Attempt to delete it by passing its absolute path.
        $result = Directory::delete($outside);

        $this->assertFalse($result);
        $this->assertTrue(is_dir($outside));

        // Cleanup.
        unlink($outside . '/precious.txt');
        rmdir($outside);
    }

    public function testDeleteDoesNotFollowSymlinks(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Symlinks not reliably available on Windows.');
        }

        // Create a directory outside the root that should survive.
        $outsideDir = sys_get_temp_dir() . '/webrium_symtarget_' . uniqid();
        mkdir($outsideDir, 0755, true);
        file_put_contents($outsideDir . '/secret.txt', 'must survive');

        // Create a directory inside the root with a symlink pointing outside.
        $this->createTree(['danger']);
        symlink($outsideDir, $this->root . '/danger/link_to_outside');

        Directory::set('danger', 'danger');
        Directory::delete('danger');

        // The symlink target must be intact.
        $this->assertTrue(is_file($outsideDir . '/secret.txt'));
        $this->assertSame('must survive', file_get_contents($outsideDir . '/secret.txt'));

        // Cleanup.
        unlink($outsideDir . '/secret.txt');
        rmdir($outsideDir);
    }

    public function testDeleteHandlesNestedSymlinkToFile(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Symlinks not reliably available on Windows.');
        }

        // A file outside the root.
        $outsideFile = sys_get_temp_dir() . '/webrium_linked_file_' . uniqid() . '.txt';
        file_put_contents($outsideFile, 'protected');

        // A symlink to it inside a deletable directory.
        $this->createTree(['linked']);
        symlink($outsideFile, $this->root . '/linked/shortcut.txt');

        Directory::set('linked', 'linked');
        Directory::delete('linked');

        // The original file must survive.
        $this->assertSame('protected', file_get_contents($outsideFile));

        unlink($outsideFile);
    }

    // =========================================================================
    // 4. empty() — safety checks (SECURITY)
    // =========================================================================

    public function testEmptyRemovesContentsButKeepsDirectory(): void
    {
        $this->createTree(['basket/sub']);
        $this->createFile('basket/file.txt', 'data');
        $this->createFile('basket/sub/nested.txt', 'more');

        Directory::set('basket', 'basket');
        $this->assertTrue(Directory::empty('basket'));

        // The directory itself must still exist, but be empty.
        $this->assertTrue(is_dir($this->root . '/basket'));
        $this->assertSame(['.', '..'], scandir($this->root . '/basket'));
    }

    public function testEmptyReturnsFalseForNonExistent(): void
    {
        Directory::set('ghost', 'nonexistent');
        $this->assertFalse(Directory::empty('ghost'));
    }

    // =========================================================================
    // 5. make()
    // =========================================================================

    public function testMakeCreatesDirectory(): void
    {
        Directory::set('cache', 'storage/cache');
        $this->assertTrue(Directory::make('cache'));
        $this->assertTrue(is_dir($this->root . '/storage/cache'));
    }

    public function testMakeReturnsTrueIfAlreadyExists(): void
    {
        $this->createTree(['existing']);
        Directory::set('existing', 'existing');
        $this->assertTrue(Directory::make('existing'));
    }

    public function testMakeCreatesNestedDirectories(): void
    {
        Directory::set('deep', 'a/b/c/d/e');
        $this->assertTrue(Directory::make('deep'));
        $this->assertTrue(is_dir($this->root . '/a/b/c/d/e'));
    }

    // =========================================================================
    // 6. exists() and filesystem checks
    // =========================================================================

    public function testExistsReturnsTrueForRealDirectory(): void
    {
        $this->createTree(['real']);
        Directory::set('real', 'real');
        $this->assertTrue(Directory::exists('real'));
    }

    public function testExistsReturnsFalseForMissing(): void
    {
        Directory::set('missing', 'nowhere');
        $this->assertFalse(Directory::exists('missing'));
    }

    // =========================================================================
    // 7. initDefaultStructure
    // =========================================================================

    public function testInitDefaultStructureRegistersExpectedKeys(): void
    {
        Directory::initDefaultStructure();

        $expected = ['app', 'controllers', 'models', 'views', 'routes', 'config',
                     'middleware', 'helpers', 'services', 'storage', 'storage_app',
                     'sessions', 'cache', 'render_views', 'static_views', 'logs',
                     'langs', 'public', 'assets', 'uploads'];

        foreach ($expected as $key) {
            $this->assertTrue(Directory::has($key), "Expected key '{$key}' to be registered.");
        }
    }
}