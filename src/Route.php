<?php

declare(strict_types=1);

namespace Webrium;

use Webrium\Url;
use Webrium\Kernel;
use Webrium\Header;
use Webrium\Debug;

class Route
{
    private static $routes = [];
    private static $routeNames = [];
    private static $prefix = '';
    private static $notFoundHandler = null;
    private static $middlewares = [];
    private static $middlewareIndex = -1;

    /**
     * Load route files into the router.
     *
     * When called with just an array of filenames the files are resolved
     * from the registered 'routes' directory (backward-compatible default).
     * Pass a directory alias or an absolute path as the first argument to
     * load route files from any location.
     *
     * Examples:
     *   Route::source(['web.php', 'api.php']);
     *   Route::source(['shop.php'], 'modules/shop/routes');
     *   Route::source(['admin.php'], '/var/www/admin/routes');
     *
     * @param  string[]    $fileNames  Filenames to load.
     * @param  string|null $directory  Directory alias or absolute path (default: 'routes').
     * @return void
     */
    public static function source(array $fileNames, ?string $directory = null): void
    {
        $path = $directory !== null
            ? Directory::path($directory)
            : Directory::path('routes');

        foreach ($fileNames as $fileName) {
            $result = Kernel::runOnce("$path/$fileName");

            if ($result === false) {
                Debug::triggerError("Route file '$fileName' not found in '$path'.");
            }
        }
    }

    /**
     * Add a new route to the routes collection.
     *
     * @param string          $method  HTTP method (GET, POST, PUT, DELETE, ANY)
     * @param string          $url     URL pattern
     * @param callable|string $handler Route handler (closure or "Controller@method")
     * @param string          $name    Optional route name
     * @return Route
     */
    private static function add(string $method, string $url, $handler, string $name = ''): self
    {
        $url = trim($url, '/');

        if (!empty(self::$prefix)) {
            $url = self::$prefix . '/' . $url;
        }

        $routeIndex = count(self::$routes);
        self::$routes[] = [
            'method'     => $method,
            'url'        => '/' . $url,
            'handler'    => $handler,
            'middleware' => self::$middlewareIndex,
            'params'     => [],
        ];

        if (!empty($name)) {
            if (isset(self::$routeNames[$name])) {
                Debug::triggerError("Route name '$name' is already registered.");
            }
            self::$routeNames[$name] = $routeIndex;
        }

        return new self();
    }

    /** Register a GET route. */
    public static function get(string $url, $handler, string $name = ''): self
    {
        return self::add('GET', $url, $handler, $name);
    }

    /** Register a POST route. */
    public static function post(string $url, $handler, string $name = ''): self
    {
        return self::add('POST', $url, $handler, $name);
    }

    /** Register a PUT route. */
    public static function put(string $url, $handler, string $name = ''): self
    {
        return self::add('PUT', $url, $handler, $name);
    }

    /** Register a PATCH route. */
    public static function patch(string $url, $handler, string $name = ''): self
    {
        return self::add('PATCH', $url, $handler, $name);
    }

    /** Register a DELETE route. */
    public static function delete(string $url, $handler, string $name = ''): self
    {
        return self::add('DELETE', $url, $handler, $name);
    }

    /** Register a route that matches any HTTP method. */
    public static function any(string $url, $handler, string $name = ''): self
    {
        return self::add('ANY', $url, $handler, $name);
    }

    /**
     * Assign a name to the most recently registered route.
     *
     * @param string $name Route name
     * @return void
     */
    public function name(string $name): void
    {
        if (empty(self::$routes)) {
            Debug::triggerError("No route registered to assign name '$name'.");
            return;
        }

        if (isset(self::$routeNames[$name])) {
            Debug::triggerError("Route name '$name' is already registered.");
            return;
        }

        $lastIndex = count(self::$routes) - 1;
        self::$routeNames[$name] = $lastIndex;
    }

    /**
     * Group routes under a shared prefix and/or middleware.
     *
     * @param string|array $options  Prefix string, or array with 'prefix' and/or 'middleware'
     * @param callable     $callback Closure that registers routes in the group
     * @return void
     */
    public static function group($options, callable $callback): void
    {
        $oldPrefix          = self::$prefix;
        $oldMiddlewareIndex = self::$middlewareIndex;

        if (is_string($options)) {
            $prefix = $options;
        } else {
            $prefix = $options['prefix'] ?? '';

            if (isset($options['middleware'])) {
                self::$middlewares[]   = $options['middleware'];
                self::$middlewareIndex = count(self::$middlewares) - 1;
            }
        }

        if ($prefix !== '') {
            $prefix       = trim($prefix, '/');
            self::$prefix = self::$prefix === '' ? $prefix : self::$prefix . '/' . $prefix;
        }

        call_user_func($callback);

        self::$prefix          = $oldPrefix;
        self::$middlewareIndex = $oldMiddlewareIndex;
    }

    /**
     * Generate the URL for a named route, substituting any parameters.
     *
     * @param  string $name   Route name
     * @param  array  $params Parameter values keyed by placeholder name
     * @return string Generated URL, or empty string on error
     */
    public static function route(string $name, array $params = []): string
    {
        if (!isset(self::$routeNames[$name])) {
            Debug::triggerError("Route with name '$name' not found.");
            return '';
        }

        $url = self::$routes[self::$routeNames[$name]]['url'];

        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $url, $matches);

        foreach ($matches[1] as $param) {
            if (!isset($params[$param])) {
                Debug::triggerError("Missing required parameter '$param' for route '$name'.");
                return '';
            }
        }

        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }

        if (preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $url)) {
            Debug::triggerError("Some parameters were not replaced in route '$name'.");
            return '';
        }

        return $url;
    }

    /**
     * Set a custom handler for unmatched routes (404).
     *
     * Accepts a closure or a "Controller@method" string.
     *
     * @param callable|string $handler
     * @return void
     */
    public static function setNotFoundHandler($handler): void
    {
        self::$notFoundHandler = $handler;
    }

    /**
     * Run the router against the current HTTP request.
     *
     * @return void
     */
    public static function run(): void
    {
        $uri          = Url::uri();
        $method       = Url::method();
        $matchedRoute = null;

        foreach (self::$routes as $route) {
            if ($method !== $route['method'] && $route['method'] !== 'ANY') {
                continue;
            }

            $matchResult = self::matchRoute($route['url'], $uri);

            if ($matchResult['match']) {
                $matchedRoute           = $route;
                $matchedRoute['params'] = $matchResult['params'];
                break;
            }
        }

        if ($matchedRoute === null) {
            self::handleNotFound();
            return;
        }

        // Middleware guard — a failing middleware returns a 403, never falls through
        if (!self::processMiddleware($matchedRoute)) {
            Header::respond(['error' => 'Forbidden'], 403);
        }

        self::dispatch($matchedRoute['handler'], $matchedRoute['params']);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Match a route pattern against the current URI.
     *
     * @param  string $routeUrl Route pattern (may contain {param} segments)
     * @param  string $uri      Current request URI
     * @return array{match: bool, params: array}
     */
    private static function matchRoute(string $routeUrl, string $uri): array
    {
        $uri      = trim($uri, '/');
        $routeUrl = trim($routeUrl, '/');

        if ($routeUrl === $uri) {
            return ['match' => true, 'params' => []];
        }

        if (!str_contains($routeUrl, '{')) {
            return ['match' => false, 'params' => []];
        }

        $routeParts = explode('/', $routeUrl);
        $uriParts   = explode('/', $uri);

        if (count($routeParts) !== count($uriParts)) {
            return ['match' => false, 'params' => []];
        }

        $params = [];

        foreach ($routeParts as $index => $routePart) {
            if (str_starts_with($routePart, '{')) {
                $params[trim($routePart, '{}')] = $uriParts[$index];
            } elseif ($routePart !== $uriParts[$index]) {
                return ['match' => false, 'params' => []];
            }
        }

        return ['match' => true, 'params' => $params];
    }

    /**
     * Dispatch a handler to the correct executor.
     *
     * Supported handler forms:
     *   - Closure / callable
     *   - 'Controller@method'          (string syntax)
     *   - [Controller::class, 'method'] (array syntax — IDE-friendly)
     *
     * @param  callable|string|array $handler
     * @param  array                 $params
     * @return void
     */
    private static function dispatch($handler, array $params): void
    {
        if (is_array($handler)) {
            self::dispatchControllerArray($handler, $params);
        } elseif (is_string($handler)) {
            self::dispatchControllerString($handler, $params);
        } elseif (is_callable($handler)) {
            Header::respond(call_user_func_array($handler, $params));
        } else {
            Debug::triggerError("Invalid route handler type.");
        }
    }

    /**
     * @param  array{0: class-string, 1: string} $handler
     * @param  array $params
     * @return void
     */
    private static function dispatchControllerArray(array $handler, array $params): void
    {
        if (count($handler) !== 2 || !is_string($handler[0]) || !is_string($handler[1])) {
            Debug::triggerError("Array handler must be [ClassName::class, 'method'].");
            return;
        }

        Kernel::executeControllerMethod($handler[0], $handler[1], $params);
    }

    /**
     * @param  string $handlerString  Format: 'ControllerName@method'
     * @param  array  $params
     * @return void
     */
    private static function dispatchControllerString(string $handlerString, array $params): void
    {
        if (!str_contains($handlerString, '@')) {
            Debug::triggerError("Invalid handler format: '$handlerString'. Expected 'Controller@method'.");
            return;
        }

        [$shortClass, $method] = explode('@', $handlerString, 2);
        $fqcn = 'App\\Controllers\\' . $shortClass;

        Kernel::executeControllerMethod($fqcn, $method, $params);
    }

    /**
     * Handle a 404 response.
     *
     * @return void
     */
    private static function handleNotFound(): void
    {
        http_response_code(404);

        if (self::$notFoundHandler === null) {
            echo 'Page not found';
            return;
        }

        self::dispatch(self::$notFoundHandler, []);
    }

    /**
     * Check whether all middleware attached to a route pass.
     *
     * @param  array $route
     * @return bool
     */
    private static function processMiddleware(array $route): bool
    {
        if ($route['middleware'] === -1) {
            return true;
        }

        $middlewares = self::$middlewares[$route['middleware']];

        if (!is_array($middlewares)) {
            $middlewares = [$middlewares];
        }

        foreach ($middlewares as $middleware) {
            if (!self::executeMiddleware($middleware)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute a single middleware and return whether it passes.
     *
     * Supported forms:
     *   - Closure / any callable
     *   - 'function_name'  (global function)
     *   - 'Class@method'   (class-based middleware with a named method)
     *   - 'Class'          (class-based middleware; calls handle())
     *   - true / false     (static pass/fail, useful for testing)
     *
     * @param  mixed $middleware
     * @return bool
     */
    private static function executeMiddleware(mixed $middleware): bool
    {
        if (is_bool($middleware)) {
            return $middleware;
        }

        if (is_callable($middleware)) {
            return (bool) $middleware();
        }

        if (is_string($middleware)) {
            // Class@method format
            if (str_contains($middleware, '@')) {
                [$class, $method] = explode('@', $middleware, 2);

                if (!class_exists($class)) {
                    Debug::triggerError("Middleware class '$class' not found.");
                    return false;
                }

                return (bool) (new $class())->{$method}();
            }

            // Plain class name — call handle()
            if (class_exists($middleware)) {
                return (bool) (new $middleware())->handle();
            }

            // Global function name
            if (function_exists($middleware)) {
                return (bool) call_user_func($middleware);
            }

            Debug::triggerError("Middleware '$middleware' could not be resolved.");
            return false;
        }

        Debug::triggerError("Invalid middleware handler type.");
        return false;
    }
}