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
        if (!class_exists($className)) {
            Debug::triggerError("Class $className not found");
            return;
        }

        $controller = new $className();

        if (method_exists($controller, 'boot')) {
            $controller->boot();
        }

        if (method_exists($controller, $methodName)) {
            Header::respond($controller->{$methodName}(...$params));
        } else {
            Debug::triggerError("Method $methodName not found in $className");
        }

        if (method_exists($controller, 'teardown')) {
            $controller->teardown();
        }
    }
}