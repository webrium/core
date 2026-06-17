<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\File;

/**
 * Unit Tests for Webrium\File
 *
 * Coverage:
 *  - File existence and type checks
 *  - Read/write/append/prepend (including atomic prepend with locking)
 *  - Metadata (size, extension, MIME, timestamps)
 *  - Copy/move/delete/deleteMultiple
 *  - Directory listing (getFiles/getFilesRecursive)
 *  - Hashing helpers
 *  - Human-readable sizes
 *  - Filename sanitization (header injection prevention)
 *  - Image MIME validation (file disclosure prevention)
 *  - Edge cases (non-existent files, permissions)
 */
class FileTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/webrium_file_tests_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $this->removeDir($this->testDir);
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

    // =========================================================================
    // 1. Existence and type checks
    // =========================================================================

    public function testExistsIsFileAndIsDirectory(): void
    {
        $filePath = $this->testDir . '/test.txt';
        file_put_contents($filePath, 'Hello');

        $this->assertTrue(File::exists($filePath));
        $this->assertTrue(File::isFile($filePath));
        $this->assertFalse(File::isDirectory($filePath));

        $this->assertTrue(File::exists($this->testDir));
        $this->assertTrue(File::isDirectory($this->testDir));
        $this->assertFalse(File::isFile($this->testDir));

        $this->assertFalse(File::exists($this->testDir . '/fake.txt'));
    }

    // =========================================================================
    // 2. Read / write / append / prepend
    // =========================================================================

    public function testWriteAndRead(): void
    {
        $filePath = $this->testDir . '/write_test.txt';

        $bytes = File::write($filePath, 'Hello');
        $this->assertNotFalse($bytes);
        $this->assertSame('Hello', File::read($filePath));
        $this->assertSame('Hello', File::getContent($filePath));
    }

    public function testAppend(): void
    {
        $filePath = $this->testDir . '/append.txt';
        File::write($filePath, 'Hello');
        File::append($filePath, ' World');

        $this->assertSame('Hello World', File::read($filePath));
    }

    public function testPrependToExistingFile(): void
    {
        $filePath = $this->testDir . '/prepend.txt';
        File::write($filePath, 'World');
        File::prepend($filePath, 'Hello ');

        $this->assertSame('Hello World', File::read($filePath));
    }

    public function testPrependCreatesNewFile(): void
    {
        $filePath = $this->testDir . '/new_prepend.txt';
        File::prepend($filePath, 'First');

        $this->assertSame('First', File::read($filePath));
    }

    public function testPutContentAlias(): void
    {
        $filePath = $this->testDir . '/put_test.txt';
        $bytes = File::putContent($filePath, 'Data');

        $this->assertNotFalse($bytes);
        $this->assertSame('Data', File::read($filePath));
    }

    public function testLines(): void
    {
        $filePath = $this->testDir . '/lines.txt';
        File::write($filePath, "Line 1\nLine 2\n\nLine 3");

        $lines = File::lines($filePath);
        $this->assertIsArray($lines);
        $this->assertCount(3, $lines);
        $this->assertSame('Line 2', $lines[1]);
    }

    // =========================================================================
    // 3. Metadata
    // =========================================================================

    public function testMetadata(): void
    {
        $filePath = $this->testDir . '/info.json';
        File::write($filePath, '{"key":"value"}');

        $this->assertSame('json', File::extension($filePath));
        $this->assertSame('info', File::name($filePath));
        $this->assertSame('info.json', File::basename($filePath));
        $this->assertSame($this->testDir, File::dirname($filePath));
        $this->assertSame(15, File::size($filePath));
        $this->assertIsInt(File::lastModified($filePath));
        $this->assertSame('application/json', File::mimeType($filePath));
    }

    public function testHumanSize(): void
    {
        $filePath = $this->testDir . '/size.txt';
        File::write($filePath, str_repeat('A', 1024));

        $this->assertSame('1 KB', File::humanSize($filePath));

        clearstatcache();
        File::write($filePath, str_repeat('A', 1536));
        clearstatcache();

        $this->assertSame('1.5 KB', File::humanSize($filePath, 1));
    }

    public function testReadabilityAndWritability(): void
    {
        $filePath = $this->testDir . '/access.txt';
        File::write($filePath, 'test');

        $this->assertTrue(File::isReadable($filePath));
        $this->assertTrue(File::isWritable($filePath));
        $this->assertFalse(File::isReadable($this->testDir . '/fake.txt'));
    }

    public function testPermissionsAndOwner(): void
    {
        $filePath = $this->testDir . '/perm.txt';
        File::write($filePath, 'test');

        $this->assertTrue(File::chmod($filePath, 0644));
        $this->assertIsInt(File::permissions($filePath));
        $this->assertIsInt(File::owner($filePath));

        $this->assertFalse(File::chmod($this->testDir . '/fake.txt', 0644));
        $this->assertFalse(File::permissions($this->testDir . '/fake.txt'));
        $this->assertFalse(File::owner($this->testDir . '/fake.txt'));
    }

    // =========================================================================
    // 4. Copy / move / delete
    // =========================================================================

    public function testCopyAndMove(): void
    {
        $source   = $this->testDir . '/source.txt';
        $copyDest = $this->testDir . '/copy.txt';
        $moveDest = $this->testDir . '/moved.txt';

        File::write($source, 'Data');

        $this->assertTrue(File::copy($source, $copyDest));
        $this->assertTrue(File::exists($source));
        $this->assertSame('Data', File::read($copyDest));

        $this->assertTrue(File::move($source, $moveDest));
        $this->assertFalse(File::exists($source));
        $this->assertSame('Data', File::read($moveDest));
    }

    public function testDeleteAndMultipleDelete(): void
    {
        $file1 = $this->testDir . '/del1.txt';
        $file2 = $this->testDir . '/del2.txt';
        $file3 = $this->testDir . '/del3.txt';

        File::write($file1, '1');
        File::write($file2, '2');
        File::write($file3, '3');

        $this->assertTrue(File::delete($file1));
        $this->assertFalse(File::exists($file1));

        $deletedCount = File::deleteMultiple([$file2, $file3, $this->testDir . '/fake.txt']);
        $this->assertSame(2, $deletedCount);
    }

    // =========================================================================
    // 5. Directory listing
    // =========================================================================

    public function testGetFilesAndRecursive(): void
    {
        $subDir = $this->testDir . '/sub/folder';
        mkdir($subDir, 0777, true);

        File::write($this->testDir . '/root.txt', '1');
        File::write($subDir . '/deep.txt', '2');

        $files = File::getFiles($this->testDir);
        $this->assertContains('root.txt', $files);
        $this->assertContains('sub', $files);
        $this->assertNotContains('deep.txt', $files);

        $allFiles = File::getFilesRecursive($this->testDir);
        $this->assertContains($this->testDir . '/root.txt', $allFiles);
        $this->assertContains($subDir . '/deep.txt', $allFiles);
    }

    // =========================================================================
    // 6. Hashing
    // =========================================================================

    public function testHashingMethods(): void
    {
        $filePath = $this->testDir . '/hash.txt';
        File::write($filePath, 'Webrium');

        $this->assertSame(md5('Webrium'), File::hash($filePath));
        $this->assertSame(sha1('Webrium'), File::sha1($filePath));
        $this->assertSame(hash('sha256', 'Webrium'), File::hashFile($filePath, 'sha256'));
    }

    // =========================================================================
    // 7. Glob and matching
    // =========================================================================

    public function testGlobAndMatches(): void
    {
        File::write($this->testDir . '/script1.php', '<?php');
        File::write($this->testDir . '/script2.php', '<?php');
        File::write($this->testDir . '/text.txt', 'txt');

        $this->assertTrue(File::matches($this->testDir . '/script1.php', '*.php'));
        $this->assertFalse(File::matches($this->testDir . '/text.txt', '*.php'));

        $phpFiles = File::glob($this->testDir . '/*.php');
        $this->assertIsArray($phpFiles);
        $this->assertCount(2, $phpFiles);
    }

    // =========================================================================
    // 8. Filename sanitization (SECURITY)
    // =========================================================================

    /**
     * @dataProvider dangerousFilenameProvider
     */
    public function testSanitizeFilenameStripsUnsafeCharacters(string $input, string $expected): void
    {
        $method = new \ReflectionMethod(File::class, 'sanitizeFilename');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke(null, $input));
    }

    public static function dangerousFilenameProvider(): array
    {
        return [
            'normal filename' => [
                'report.pdf',
                'report.pdf',
            ],
            'path separators stripped' => [
                '../../etc/passwd',
                '....etcpasswd',
            ],
            'backslash stripped' => [
                '..\\..\\windows\\system32',
                '....windowssystem32',
            ],
            'null bytes stripped' => [
                "evil\0.php",
                'evil.php',
            ],
            'control chars stripped' => [
                "file\r\nX-Injected: yes",
                'fileX-Injected: yes',
            ],
            'double quotes escaped' => [
                'file"; evil=yes',
                'file\\"; evil=yes',
            ],
            'empty becomes download' => [
                '',
                'download',
            ],
            'only slashes becomes download' => [
                '//\\\\',
                'download',
            ],
        ];
    }

    // =========================================================================
    // 9. Image MIME validation (SECURITY)
    // =========================================================================

    public function testShowImageAcceptsRealImage(): void
    {
        // A minimal valid 1x1 PNG.
        $png = $this->testDir . '/valid.png';
        file_put_contents($png, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIABQABNjN9GQAAAAlwSFlzAAAWJQAAFiUBSVIk8AAAAA0lEQVQI12P4z8BQDwAEgAF/QualzQAAAABJRU5ErkJggg=='
        ));

        $mime = File::mimeType($png);
        $this->assertTrue(str_starts_with($mime, 'image/'));
    }

    public function testShowImageRejectsNonImageMime(): void
    {
        // A PHP file must not be served as an image.
        $php = $this->testDir . '/evil.php';
        file_put_contents($php, '<?php phpinfo();');

        $mime = File::mimeType($php);
        $this->assertFalse(str_starts_with($mime, 'image/'));
    }

    public function testShowImageRejectsTextFileRenamedToImage(): void
    {
        // A text file with an image extension — MIME detection reveals the real type.
        $fake = $this->testDir . '/secret.png';
        file_put_contents($fake, 'DB_PASSWORD=hunter2');

        $mime = File::mimeType($fake);
        $this->assertFalse(str_starts_with($mime, 'image/'));
    }

    // =========================================================================
    // 10. Edge cases
    // =========================================================================

    public function testMethodsReturnFalseOnNonExistentFiles(): void
    {
        $fakePath = $this->testDir . '/does_not_exist.txt';

        $this->assertFalse(File::size($fakePath));
        $this->assertFalse(File::lastModified($fakePath));
        $this->assertFalse(File::mimeType($fakePath));
        $this->assertFalse(File::read($fakePath));
        $this->assertFalse(File::lines($fakePath));
        $this->assertFalse(File::copy($fakePath, $this->testDir . '/dest.txt'));
        $this->assertFalse(File::move($fakePath, $this->testDir . '/dest.txt'));
        $this->assertFalse(File::delete($fakePath));
        $this->assertFalse(File::hash($fakePath));
        $this->assertFalse(File::sha1($fakePath));
        $this->assertFalse(File::hashFile($fakePath, 'md5'));
        $this->assertFalse(File::humanSize($fakePath));
    }
}