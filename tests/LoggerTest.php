<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Webrium\Logger;

/**
 * Unit Tests for Webrium\Logger
 *
 * Coverage:
 *  - log() writes a per-level, per-day file under the configured log path
 *  - entry format: timestamp, uppercased level, message
 *  - context is appended as JSON when given, omitted when empty
 *  - convenience methods (error/warning/notice/info/debug/critical/alert/emergency)
 *    route to their matching level file
 *  - repeated calls append rather than overwrite
 *  - setLogPath() creates the directory if missing and is honoured by getLogPath()
 */
class LoggerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/webrium_logger_test_' . uniqid();

        // Logger caches the resolved log path in a private static; reset it
        // before each test so tests don't leak state into one another.
        $prop = new ReflectionProperty(Logger::class, 'logPath');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->removeDir($this->root);
        }
    }

    private function removeDir(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }

    private function todayFile(string $level): string
    {
        return $this->root . '/' . $level . '_' . date('Y_m_d') . '.txt';
    }

    // =========================================================================
    // 1. Basic writing
    // =========================================================================

    public function testLogCreatesLogPathDirectory(): void
    {
        Logger::setLogPath($this->root);
        Logger::info('hello');

        $this->assertDirectoryExists($this->root);
    }

    public function testLogWritesToLevelAndDateNamedFile(): void
    {
        Logger::setLogPath($this->root);
        Logger::error('something broke');

        $this->assertFileExists($this->todayFile('error'));
    }

    public function testEntryContainsTimestampLevelAndMessage(): void
    {
        Logger::setLogPath($this->root);
        Logger::warning('disk almost full');

        $content = file_get_contents($this->todayFile('warning'));

        $this->assertStringContainsString(date('Y_m_d'), $content);
        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString('disk almost full', $content);
    }

    public function testContextIsAppendedAsJsonWhenProvided(): void
    {
        Logger::setLogPath($this->root);
        Logger::info('user exported report', ['user_id' => 5, 'rows' => 120]);

        $content = file_get_contents($this->todayFile('info'));

        $this->assertStringContainsString('Context:', $content);
        $this->assertStringContainsString('"user_id":5', $content);
        $this->assertStringContainsString('"rows":120', $content);
    }

    public function testContextLineOmittedWhenEmpty(): void
    {
        Logger::setLogPath($this->root);
        Logger::info('no extra data');

        $content = file_get_contents($this->todayFile('info'));

        $this->assertStringNotContainsString('Context:', $content);
    }

    // =========================================================================
    // 2. Level routing
    // =========================================================================

    public function levelMethodProvider(): array
    {
        return [
            'emergency' => ['emergency'],
            'alert'     => ['alert'],
            'critical'  => ['critical'],
            'error'     => ['error'],
            'warning'   => ['warning'],
            'notice'    => ['notice'],
            'info'      => ['info'],
            'debug'     => ['debug'],
        ];
    }

    /**
     * @dataProvider levelMethodProvider
     */
    public function testConvenienceMethodWritesToItsOwnLevelFile(string $level): void
    {
        Logger::setLogPath($this->root);
        Logger::$level("a {$level} message");

        $file = $this->todayFile($level);
        $this->assertFileExists($file);
        $this->assertStringContainsString(
            '[' . strtoupper($level) . ']',
            file_get_contents($file)
        );
    }

    public function testDifferentLevelsWriteToDifferentFiles(): void
    {
        Logger::setLogPath($this->root);
        Logger::error('an error');
        Logger::info('just fyi');

        $this->assertFileExists($this->todayFile('error'));
        $this->assertFileExists($this->todayFile('info'));

        $errorContent = file_get_contents($this->todayFile('error'));
        $this->assertStringNotContainsString('just fyi', $errorContent);
    }

    // =========================================================================
    // 3. Appending
    // =========================================================================

    public function testRepeatedCallsAppendRatherThanOverwrite(): void
    {
        Logger::setLogPath($this->root);
        Logger::info('first message');
        Logger::info('second message');

        $content = file_get_contents($this->todayFile('info'));

        $this->assertStringContainsString('first message', $content);
        $this->assertStringContainsString('second message', $content);
    }

    // =========================================================================
    // 4. Path configuration
    // =========================================================================

    public function testGetLogPathReturnsConfiguredPath(): void
    {
        Logger::setLogPath($this->root);
        $this->assertSame($this->root, Logger::getLogPath());
    }

    public function testSetLogPathCreatesDirectoryEvenWithoutLogging(): void
    {
        $this->assertDirectoryDoesNotExist($this->root);
        Logger::setLogPath($this->root);
        $this->assertDirectoryExists($this->root);
    }
}
