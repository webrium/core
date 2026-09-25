<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for the global dd() helper (src/Helpers/helpers.php).
 *
 * dd() dumps its arguments and then calls exit(1). exit() makes it
 * impossible to assert in-process (PHPUnit treats a child that calls exit
 * as "ended unexpectedly"). As with Header::respond() (see
 * HeaderRespondTest), the honest way to test an exiting function is to run
 * it in a genuine PHP subprocess and inspect its real stdout and exit code.
 */
class DdHelperTest extends TestCase
{
    /**
     * Run a snippet that calls dd() in a fresh PHP CLI process and return
     * [stdout, exitCode].
     *
     * @return array{0: string, 1: int}
     */
    private function runDd(string $phpArgsExpression): array
    {
        $autoload = escapeshellarg(__DIR__ . '/../vendor/autoload.php');

        $script = sprintf('require %s; dd(%s);', $autoload, $phpArgsExpression);

        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null';

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        return [implode("\n", $output), $exitCode];
    }

    public function testDdExitsWithStatusOne(): void
    {
        [, $code] = $this->runDd("'hello'");
        $this->assertSame(1, $code);
    }

    public function testDdOutputsVarDumpOfItsArgument(): void
    {
        [$output] = $this->runDd("'hello'");
        $this->assertStringContainsString('string(5) "hello"', $output);
    }

    public function testDdOutputsEachArgumentSeparately(): void
    {
        [$output] = $this->runDd("'first', ['a' => 1]");

        $this->assertStringContainsString('string(5) "first"', $output);
        $this->assertStringContainsString('array(1)', $output);
    }

    public function testDdStopsExecutionAfterDumping(): void
    {
        $autoload = escapeshellarg(__DIR__ . '/../vendor/autoload.php');
        $script   = sprintf(
            'require %s; dd("before"); echo "unreachable";',
            $autoload
        );
        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null';

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        $output = implode("\n", $output);

        $this->assertStringContainsString('string(6) "before"', $output);
        $this->assertStringNotContainsString('unreachable', $output);
    }
}
