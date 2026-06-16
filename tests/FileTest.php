<?php

declare(strict_types=1);

namespace Tests;

use Directory;
use PHPUnit\Framework\TestCase;
use Webrium\File;

class FileTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        // Create a dedicated temporary directory for testing
        $this->testDir = sys_get_temp_dir() . '/webrium_file_tests_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }


    public function testExistsIsFileAndIsDirectory()
    {
        $filePath = $this->testDir . '/test.txt';
        file_put_contents($filePath, 'Hello');

        // Check file existence
        $this->assertTrue(File::exists($filePath));
        $this->assertTrue(File::isFile($filePath));
        $this->assertFalse(File::isDirectory($filePath));

        // Check directory existence
        $this->assertTrue(File::exists($this->testDir));
        $this->assertTrue(File::isDirectory($this->testDir));
        $this->assertFalse(File::isFile($this->testDir));

        // Check invalid path
        $this->assertFalse(File::exists($this->testDir . '/fake.txt'));
    }

    public function testWriteAndReadMethods()
    {
        $filePath = $this->testDir . '/write_test.txt';

        // Test writing
        $bytes = File::write($filePath, 'Hello');
        $this->assertNotFalse($bytes);
        $this->assertTrue(File::exists($filePath));

        // Test reading
        $this->assertEquals('Hello', File::read($filePath));
        $this->assertEquals('Hello', File::getContent($filePath));

        // Test append
        File::append($filePath, ' World');
        $this->assertEquals('Hello World', File::read($filePath));

        // Test prepend
        File::prepend($filePath, 'First ');
        $this->assertEquals('First Hello World', File::read($filePath));
    }

    public function testMetaDataMethods()
    {
        $filePath = $this->testDir . '/info.json';
        File::write($filePath, '{"key":"value"}');

        $this->assertEquals('json', File::extension($filePath));
        $this->assertEquals('info', File::name($filePath));
        $this->assertEquals('info.json', File::basename($filePath));
        $this->assertEquals($this->testDir, File::dirname($filePath));
        
        $size = File::size($filePath);
        $this->assertEquals(15, $size); // {"key":"value"} is 15 bytes

        $this->assertIsInt(File::lastModified($filePath));
        $this->assertEquals('application/json', File::mimeType($filePath));
    }

    public function testCopyAndMoveFiles()
    {
        $source = $this->testDir . '/source.txt';
        $copyDest = $this->testDir . '/copy.txt';
        $moveDest = $this->testDir . '/moved.txt';

        File::write($source, 'Data');

        // Test copy
        $this->assertTrue(File::copy($source, $copyDest));
        $this->assertTrue(File::exists($source)); // Source file should remain
        $this->assertTrue(File::exists($copyDest));
        $this->assertEquals('Data', File::read($copyDest));

        // Test move
        $this->assertTrue(File::move($source, $moveDest));
        $this->assertFalse(File::exists($source)); // Source file should be deleted
        $this->assertTrue(File::exists($moveDest));
        $this->assertEquals('Data', File::read($moveDest));
    }

    public function testDeleteAndMultipleDelete()
    {
        $file1 = $this->testDir . '/del1.txt';
        $file2 = $this->testDir . '/del2.txt';
        $file3 = $this->testDir . '/del3.txt';

        File::write($file1, '1');
        File::write($file2, '2');
        File::write($file3, '3');

        // Test single deletion
        $this->assertTrue(File::delete($file1));
        $this->assertFalse(File::exists($file1));

        // Test multiple deletion
        $deletedCount = File::deleteMultiple([$file2, $file3, $this->testDir . '/fake.txt']);
        $this->assertEquals(2, $deletedCount);
        $this->assertFalse(File::exists($file2));
        $this->assertFalse(File::exists($file3));
    }

    public function testMakeDirectoryAndGetFiles()
    {
        $subDir = $this->testDir . '/sub/folder';
        
        // Create nested directories
        mkdir($subDir, 0775, true);
        $this->assertTrue(File::isDirectory($subDir));

        // Create files inside directories
        File::write($this->testDir . '/root.txt', '1');
        File::write($subDir . '/deep.txt', '2');

        // Test getFiles (current directory only)
        $files = File::getFiles($this->testDir);
        $this->assertContains('root.txt', $files);
        $this->assertContains('sub', $files);
        $this->assertNotContains('deep.txt', $files); // Should not see the file in the inner directory

        // Test getFilesRecursive (full traversal)
        $allFiles = File::getFilesRecursive($this->testDir);
        $this->assertContains($this->testDir . '/root.txt', $allFiles);
        $this->assertContains($subDir . '/deep.txt', $allFiles);
    }


    public function testHashingMethods()
    {
        $filePath = $this->testDir . '/hash.txt';
        File::write($filePath, 'Webrium');

        // md5('Webrium') = a6d2269a2ab53f7f2b1d5c7f8a7e373a
        $this->assertEquals(md5('Webrium'), File::hash($filePath));
        
        // sha1('Webrium') = 0122e2365287e07ff819cc7e47dfa9f7338baef6
        $this->assertEquals(sha1('Webrium'), File::sha1($filePath));
        
        // hash_file with custom algorithm
        $this->assertEquals(hash('sha256', 'Webrium'), File::hashFile($filePath, 'sha256'));
    }

    public function testHumanSize()
    {
        $filePath = $this->testDir . '/size.txt';
        
        // 1024 Bytes = 1 KB
        $content = str_repeat('A', 1024);
        File::write($filePath, $content);

        $this->assertEquals('1 KB', File::humanSize($filePath));

        // Clear PHP's internal file status cache
        clearstatcache();

        // Test with decimal precision (1536 Bytes = 1.5 KB)
        $content = str_repeat('A', 1536);
        File::write($filePath, $content);
        
        // Clear cache again after writing the new size
        clearstatcache();

        $this->assertEquals('1.5 KB', File::humanSize($filePath, 1));
    }

    public function testLines()
    {
        $filePath = $this->testDir . '/lines.txt';
        File::write($filePath, "Line 1\nLine 2\n\nLine 3"); // Contains empty line

        $lines = File::lines($filePath);
        $this->assertIsArray($lines);
        $this->assertCount(3, $lines); // Empty line should be ignored according to the flag
        $this->assertEquals('Line 2', $lines[1]);
    }

    public function testReadabilityAndWritability()
    {
        $filePath = $this->testDir . '/access.txt';
        File::write($filePath, 'test');

        $this->assertTrue(File::isReadable($filePath));
        $this->assertTrue(File::isWritable($filePath));

        // Test non-existent file
        $this->assertFalse(File::isReadable($this->testDir . '/fake.txt'));
        $this->assertFalse(File::isWritable($this->testDir . '/fake.txt'));
    }

    public function testPutContentAlias()
    {
        $filePath = $this->testDir . '/put_test.txt';
        $bytes = File::putContent($filePath, 'Data');
        
        $this->assertNotFalse($bytes);
        $this->assertEquals('Data', File::read($filePath));
    }

    public function testPermissionsAndOwner()
    {
        $filePath = $this->testDir . '/perm.txt';
        File::write($filePath, 'test');

        $this->assertTrue(File::chmod($filePath, 0644));
        
        $permissions = File::permissions($filePath);
        $this->assertIsInt($permissions);
        
        $owner = File::owner($filePath);
        $this->assertIsInt($owner);

        // Test failure on non-existent files
        $this->assertFalse(File::chmod($this->testDir . '/fake.txt', 0644));
        $this->assertFalse(File::permissions($this->testDir . '/fake.txt'));
        $this->assertFalse(File::owner($this->testDir . '/fake.txt'));
    }

    public function testGlobAndMatchesMethods()
    {
        File::write($this->testDir . '/script1.php', '<?php');
        File::write($this->testDir . '/script2.php', '<?php');
        File::write($this->testDir . '/text.txt', 'txt');

        // Test matches
        $this->assertTrue(File::matches($this->testDir . '/script1.php', '*.php'));
        $this->assertFalse(File::matches($this->testDir . '/text.txt', '*.php'));

        // Test glob
        $phpFiles = File::glob($this->testDir . '/*.php');
        $this->assertIsArray($phpFiles);
        $this->assertCount(2, $phpFiles);
    }

    public function testMethodsReturnFalseOnNonExistentFiles()
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