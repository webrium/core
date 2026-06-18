<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for Webrium\Header::respond() branch selection.
 *
 * respond() echoes a body and then calls exit(). exit() makes it impossible to
 * assert in-process (PHPUnit treats a child that calls exit as "ended
 * unexpectedly", and ob_* buffers are abandoned). The honest, reliable way to
 * test an exiting function is to run it in a genuine PHP subprocess and capture
 * its real stdout and exit code - exactly how it behaves in production.
 *
 * Header emission itself is not asserted here: it is not observable from the
 * CLI SAPI (no headers over a real socket, no xdebug in this environment). The
 * header map each helper builds is fully verified at the value-object level in
 * ResponsePayloadTest. This class verifies the body that respond() actually
 * writes for each input kind, and that the process exits cleanly (code 0).
 */
class HeaderRespondTest extends TestCase
{
    /**
     * Run a snippet that calls Header::respond() in a fresh PHP process and
     * return [stdout, exitCode].
     *
     * @return array{0: string, 1: int}
     */
    private function runRespond(string $phpExpression): array
    {
        $autoload = escapeshellarg(__DIR__ . '/../vendor/autoload.php');

        $script = sprintf(
            'require %s; use Webrium\\Header; use Webrium\\ResponsePayload; Header::respond(%s);',
            $autoload,
            $phpExpression
        );

        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null';

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        return [implode("\n", $output), $exitCode];
    }

    public function testRespondWritesResponsePayloadBody(): void
    {
        [$body, $code] = $this->runRespond("html('<h1>From payload</h1>')");
        $this->assertSame('<h1>From payload</h1>', $body);
        $this->assertSame(0, $code, 'respond() should exit cleanly');
    }

    public function testRespondWritesJsonPayloadBody(): void
    {
        [$body] = $this->runRespond("json(['ok' => true])");
        $this->assertSame('{"ok":true}', $body);
    }

    public function testRespondWritesTextPayloadBody(): void
    {
        [$body] = $this->runRespond("text('plain message')");
        $this->assertSame('plain message', $body);
    }

    public function testRespondWritesRawStringVerbatim(): void
    {
        [$body] = $this->runRespond("'<p>raw html</p>'");
        $this->assertSame('<p>raw html</p>', $body);
    }

    public function testRespondEncodesRawArrayAsJson(): void
    {
        [$body] = $this->runRespond("['error' => 'Forbidden']");
        $this->assertSame('{"error":"Forbidden"}', $body);
    }

    public function testRespondEncodesRawObjectAsJson(): void
    {
        [$body] = $this->runRespond("(object) ['view' => 'home']");
        $this->assertSame('{"view":"home"}', $body);
    }

    public function testRespondWritesRawPayloadBodyWithoutHeaders(): void
    {
        [$body, $code] = $this->runRespond("new ResponsePayload('raw-body', 200)");
        $this->assertSame('raw-body', $body);
        $this->assertSame(0, $code);
    }
}
