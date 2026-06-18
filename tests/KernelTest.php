<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Kernel;

/**
 * Unit Tests for Webrium\Kernel controller parameter handling.
 *
 * The focus is the bug fix that lets a controller like `getPost(int $id)` work
 * even though route parameters always arrive as strings from the URL. The
 * coercion lives in two private helpers — resolveMethodArguments() (reads the
 * method signature via reflection and casts positional args) and castScalar()
 * (the per-type cast) — exercised here against the real code via reflection.
 *
 * The casting rules under test:
 *   - int/float: numeric strings are cast; non-numeric strings are passed
 *     through unchanged so PHP raises its own TypeError instead of silently
 *     producing 0/0.0.
 *   - bool: parsed with FILTER_VALIDATE_BOOLEAN; unrecognised values pass through.
 *   - string: untouched.
 *   - untyped / union / class-typed params: passed through untouched.
 *
 * executeControllerMethod() itself calls Header::respond(), which exits, so its
 * end-to-end behaviour is verified in a real subprocess (see the bottom of the
 * file) rather than in-process.
 */
class KernelTest extends TestCase
{
    /**
     * Invoke a private static Kernel method via reflection.
     */
    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod(Kernel::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }

    // =========================================================================
    // 1. castScalar()
    // =========================================================================

    public function testCastScalarIntFromNumericString(): void
    {
        $result = $this->callPrivate('castScalar', ['42', 'int']);
        $this->assertSame(42, $result);
    }

    public function testCastScalarFloatFromNumericString(): void
    {
        $result = $this->callPrivate('castScalar', ['9.5', 'float']);
        $this->assertSame(9.5, $result);
    }

    public function testCastScalarStringIsUnchanged(): void
    {
        $this->assertSame('hello', $this->callPrivate('castScalar', ['hello', 'string']));
    }

    /**
     * @dataProvider boolProvider
     */
    public function testCastScalarBool(string $input, bool $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('castScalar', [$input, 'bool']));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function boolProvider(): array
    {
        return [
            'true'  => ['true', true],
            '1'     => ['1', true],
            'false' => ['false', false],
            '0'     => ['0', false],
        ];
    }

    public function testCastScalarNonNumericIntIsKeptAsStringForTypeError(): void
    {
        // Crucial: 'abc' for an int must NOT become 0 — it is passed through so
        // PHP can raise a proper TypeError downstream.
        $result = $this->callPrivate('castScalar', ['abc', 'int']);
        $this->assertSame('abc', $result);
    }

    public function testCastScalarNonNumericFloatIsKeptAsString(): void
    {
        $this->assertSame('x', $this->callPrivate('castScalar', ['x', 'float']));
    }

    public function testCastScalarUnrecognisedBoolIsKeptAsString(): void
    {
        // filter_var returns null for unrecognised tokens; the value passes through.
        $this->assertSame('maybe', $this->callPrivate('castScalar', ['maybe', 'bool']));
    }

    // =========================================================================
    // 2. resolveMethodArguments() — signature-driven coercion
    // =========================================================================

    public function testResolveCastsAllScalarTypesByPosition(): void
    {
        $controller = new KernelTestTypedController();
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'allScalars', ['42', '9.5', 'true', 'slug']]
        );

        $this->assertSame([42, 9.5, true, 'slug'], $args);
    }

    public function testResolveEnablesStrictTypedControllerToRun(): void
    {
        // The original bug: a strict_types controller crashed on string args.
        // After coercion the call must succeed and observe real scalar types.
        $controller = new KernelTestTypedController();
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'getPost', ['42']]
        );

        $this->assertSame([42], $args);
        $this->assertSame('integer', $controller->getPost(...$args));
    }

    public function testResolveLeavesUntypedParameterUntouched(): void
    {
        $controller = new KernelTestTypedController();
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'untyped', ['42']]
        );

        $this->assertSame(['42'], $args, 'untyped params must remain strings');
    }

    public function testResolveLeavesClassTypedParameterUntouched(): void
    {
        $controller = new KernelTestTypedController();
        // A class-typed parameter is not a builtin scalar; value passes through.
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'classTyped', ['something']]
        );

        $this->assertSame(['something'], $args);
    }

    public function testResolveLeavesUnionTypedParameterUntouched(): void
    {
        $controller = new KernelTestTypedController();
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'unionTyped', ['42']]
        );

        // Union types are not a single ReflectionNamedType, so no cast occurs.
        $this->assertSame(['42'], $args);
    }

    public function testResolveHandlesFewerParamsThanSignature(): void
    {
        $controller = new KernelTestTypedController();
        // Only the first arg is supplied; it is cast, nothing else is invented.
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'allScalars', ['7']]
        );

        $this->assertSame([7], $args);
    }

    public function testResolveHandlesNoParams(): void
    {
        $controller = new KernelTestTypedController();
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'getPost', []]
        );

        $this->assertSame([], $args);
    }

    public function testResolveReindexesAssociativeParams(): void
    {
        // Route params may arrive keyed by name; coercion is positional, so the
        // values are reindexed against the signature order.
        $controller = new KernelTestTypedController();
        $args = $this->callPrivate(
            'resolveMethodArguments',
            [$controller, 'getPost', ['id' => '99']]
        );

        $this->assertSame([99], $args);
    }

    // =========================================================================
    // 3. executeControllerMethod() end-to-end (subprocess; respond() exits)
    // =========================================================================

    public function testExecuteControllerMethodCoercesAndRespondsEndToEnd(): void
    {
        [$body, $code] = $this->runKernel(<<<'PHP'
            class E2EController {
                public function show(int $id) {
                    return ['id' => $id, 'type' => gettype($id)];
                }
            }
            \Webrium\Kernel::executeControllerMethod('E2EController', 'show', ['42']);
        PHP);

        $this->assertSame('{"id":42,"type":"integer"}', $body);
        $this->assertSame(0, $code);
    }

    public function testExecuteControllerMethodWorksUnderStrictTypes(): void
    {
        // Reproduces the exact reported scenario: a strict_types controller with
        // `int $id`. Before the fix this raised a TypeError; now it succeeds.
        [$body, $code] = $this->runKernel(<<<'PHP'
            class StrictE2EController {
                public function getPost(int $id) {
                    return ['ok' => true, 'id' => $id];
                }
            }
            \Webrium\Kernel::executeControllerMethod('StrictE2EController', 'getPost', ['7']);
        PHP, strict: true);

        $this->assertSame('{"ok":true,"id":7}', $body);
        $this->assertSame(0, $code);
    }

    /**
     * Run a Kernel snippet in a fresh PHP process; returns [stdout, exitCode].
     *
     * @return array{0: string, 1: int}
     */
    private function runKernel(string $body, bool $strict = false): array
    {
        $autoload = var_export(realpath(__DIR__ . '/../vendor/autoload.php'), true);
        $prelude  = $strict ? 'declare(strict_types=1);' : '';

        $script = $prelude . ' require ' . $autoload . '; ' . $body;

        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null';

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        return [implode("\n", $output), $exitCode];
    }
}

/**
 * Controller fixture exercising every parameter shape resolveMethodArguments()
 * must handle.
 */
class KernelTestTypedController
{
    public function getPost(int $id): string
    {
        return gettype($id);
    }

    public function allScalars(int $a, float $b, bool $c, string $d): array
    {
        return [$a, $b, $c, $d];
    }

    public function untyped($id): mixed
    {
        return $id;
    }

    public function classTyped(?\stdClass $obj): mixed
    {
        return $obj;
    }

    public function unionTyped(int|string $id): mixed
    {
        return $id;
    }
}