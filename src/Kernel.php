<?php

declare(strict_types=1);

namespace Webrium;

use Webrium\Header;
use Webrium\Debug;
use Webrium\Directory;

/**
 * Kernel — Application Execution Core
 *
 * Responsible for bootstrapping the framework's runtime layer:
 * loading PHP files, sourcing route/config bundles, and dispatching
 * requests to controller methods.
 *
 * This class intentionally has no knowledge of file I/O utilities
 * (reading, writing, streaming). Those belong in File. Kernel's sole
 * concern is *execution*: include, require, and controller lifecycle.
 *
 * @package Webrium
 * @version 1.0.0
 */
class Kernel
{
    // -------------------------------------------------------------------------
    // PHP File Execution
    // -------------------------------------------------------------------------

    /**
     * Include and execute a PHP file.
     *
     * @param string $path Absolute path to the file
     * @return bool True if the file was included, false if it does not exist
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
     * Include and execute a PHP file only once (idempotent).
     *
     * @param string $path Absolute path to the file
     * @return bool True if the file was included, false if it does not exist
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
     * Require a PHP file (fatal error if not found).
     *
     * @param string $path Absolute path to the file
     * @return bool True if the file was required, false if it does not exist
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
     * Require a PHP file only once (fatal error if not found, idempotent).
     *
     * @param string $path Absolute path to the file
     * @return bool True if the file was required, false if it does not exist
     */
    public static function requireOnce(string $path): bool
    {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Source Bundle Loading
    // -------------------------------------------------------------------------

    /**
     * Load multiple PHP files from a named directory.
     *
     * Resolves the directory via Directory::path() and include_once's each
     * file in the provided list. Silently skips files that do not exist
     * (the missing count is reflected in the return value).
     *
     * Typical use: loading route files, config files, or service providers
     * that live under a registered directory alias.
     *
     * @param string   $pathName Registered directory alias (e.g. 'routes')
     * @param string[] $files    Filenames to include (e.g. ['web.php', 'api.php'])
     * @return int Number of files successfully included
     */
    public static function source(string $pathName, array $files): int
    {
        $path = Directory::path($pathName);
        $included = 0;

        foreach ($files as $file) {
            if (self::runOnce("$path/$file")) {
                $included++;
            }
        }

        return $included;
    }

    // -------------------------------------------------------------------------
    // Controller Dispatcher
    // -------------------------------------------------------------------------

    /**
     * Resolve, instantiate, and invoke a controller method.
     *
     * Lifecycle:
     *   1. Resolve the fully-qualified class name from the directory alias
     *      and class name (e.g. 'controllers' + 'UserController').
     *   2. Instantiate the controller.
     *   3. Call boot() if it exists (pre-action hook).
     *   4. Invoke the target method with any route parameters.
     *   5. Pass the return value to Header::respond() for output.
     *   6. Call teardown() if it exists (post-action hook).
     *
     * Controller hook methods:
     *   boot()      — runs before the action method; use for auth checks, setup, etc.
     *   teardown()  — runs after the response is sent; use for cleanup, logging, etc.
     *
     * @param string   $dirName    Registered directory alias (e.g. 'controllers')
     * @param string   $className  Controller class name (e.g. 'UserController')
     * @param string   $methodName Method to invoke (e.g. 'index')
     * @param array    $params     Route parameters passed as positional arguments
     * @return void
     */
    public static function executeControllerMethod(
        string $dirName,
        string $className,
        string $methodName,
        array $params = []
    ): void {
        $dir = Directory::get($dirName);
        $class = "$dir\\$className";
        $class = str_replace('/', '\\', $class);

        if (!class_exists($class)) {
            Debug::triggerError("Class $class not found", "$className.php");
            return;
        }

        $controller = new $class();

        // Pre-action hook
        if (method_exists($controller, 'boot')) {
            $controller->boot();
        }

        // Dispatch
        if (method_exists($controller, $methodName)) {
            Header::respond($controller->{$methodName}(...$params));
        } else {
            Debug::triggerError("Method $methodName not found in $class", "$className.php");
        }

        // Post-action hook
        if (method_exists($controller, 'teardown')) {
            $controller->teardown();
        }
    }
}
