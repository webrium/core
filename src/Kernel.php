<?php

declare(strict_types=1);

namespace Webrium;

use Webrium\Header;
use Webrium\Debug;
use Webrium\Directory;

class Kernel
{
    /**
     * Include a PHP file.
     *
     * @param string $path Absolute path to the file.
     * @return bool True if included, false if not found.
     */
    public static function run(string $path): bool
    {
        if (file_exists($path)) {
            include $path;
            return true;
        }
        return false;
    }

    /**
     * Include a PHP file once.
     *
     * @param string $path Absolute path to the file.
     * @return bool True if included, false if not found.
     */
    public static function runOnce(string $path): bool
    {
        if (file_exists($path)) {
            include_once $path;
            return true;
        }
        return false;
    }

    /**
     * Require a PHP file.
     *
     * @param string $path Absolute path to the file.
     * @return bool True if required, false if not found.
     */
    public static function requireFile(string $path): bool
    {
        if (file_exists($path)) {
            require $path;
            return true;
        }
        return false;
    }

    /**
     * Require a PHP file once.
     *
     * @param string $path Absolute path to the file.
     * @return bool True if required, false if not found.
     */
    public static function requireOnce(string $path): bool
    {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
        return false;
    }

    /**
     * Include multiple PHP files from a registered directory.
     *
     * @param string   $pathName Directory alias registered in Directory.
     * @param string[] $files    Filenames to include.
     * @return int Number of files successfully included.
     */
    public static function source(string $pathName, array $files): int
    {
        $path     = Directory::path($pathName);
        $included = 0;

        foreach ($files as $file) {
            if (self::runOnce("$path/$file")) {
                $included++;
            }
        }

        return $included;
    }

    /**
     * Instantiate a controller and invoke a method.
     *
     * Hooks:
     *   boot()     — called before the action method.
     *   teardown() — called after the response is sent.
     *
     * @param string $className  Fully-qualified class name (e.g. App\Controllers\UserController).
     * @param string $methodName Method to invoke.
     * @param array  $params     Route parameters.
     * @return void
     */
    public static function executeControllerMethod(
        string $className,
        string $methodName,
        array $params = []
    ): void {

        $className = str_replace('/', '\\', $className);

        if (!class_exists($className)) {
            Debug::triggerError("Class $className not found");
            return;
        }

        $controller = new $className();

        if (method_exists($controller, 'boot')) {
            $controller->boot();
        }

        if (method_exists($controller, $methodName)) {
            $arguments = self::resolveMethodArguments($controller, $methodName, $params);
            Header::respond($controller->{$methodName}(...$arguments));
        } else {
            Debug::triggerError("Method $methodName not found in $className");
        }

        if (method_exists($controller, 'teardown')) {
            $controller->teardown();
        }
    }

    /**
     * Coerce raw route parameters (always strings from the URL) into the scalar
     * types declared by the controller method, so a method like
     * `getPost(int $id)` works even under `declare(strict_types=1)`.
     *
     * Resolution is positional, matching the order route params are supplied.
     * Only scalar type hints (int, float, bool, string) are coerced; values
     * for untyped, union, or class-typed parameters are passed through
     * unchanged. Non-numeric values bound for int/float are also passed
     * through untouched, letting PHP raise its normal TypeError rather than
     * silently coercing them to 0.
     *
     * @param  object $controller
     * @param  string $methodName
     * @param  array  $params Raw (string) route parameters, in order.
     * @return array  Arguments with scalar values cast to the declared types.
     */
    private static function resolveMethodArguments(
        object $controller,
        string $methodName,
        array $params
    ): array {
        $values = array_values($params);

        try {
            $reflection = new \ReflectionMethod($controller, $methodName);
        } catch (\ReflectionException $e) {
            return $values;
        }

        $parameters = $reflection->getParameters();

        foreach ($values as $index => $value) {
            if (!isset($parameters[$index]) || !is_string($value)) {
                continue;
            }

            $type = $parameters[$index]->getType();

            if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin()) {
                continue;
            }

            $values[$index] = self::castScalar($value, $type->getName());
        }

        return $values;
    }

    /**
     * Cast a string route value to a declared scalar type. Values that cannot
     * be meaningfully cast are returned unchanged so PHP can surface a proper
     * TypeError instead of a misleading silent conversion.
     *
     * @param  string $value
     * @param  string $type  One of int, float, bool, string.
     * @return mixed
     */
    private static function castScalar(string $value, string $type): mixed
    {
        return match ($type) {
            'int'    => is_numeric($value) ? (int) $value : $value,
            'float'  => is_numeric($value) ? (float) $value : $value,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            'string' => $value,
            default  => $value,
        };
    }
}