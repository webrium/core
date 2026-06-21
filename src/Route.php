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
     * Built-in shorthand patterns for typed route parameters.
     * Usage: {id:int}, {slug:slug}, {token:uuid}, ...
     *
     * @var array<string, string>
     */
    private static array $paramPatterns = [
        'int'   => '/^-?\d+$/',
        'alpha' => '/^[a-zA-Z]+$/',
        'alnum' => '/^[a-zA-Z0-9]+$/',
        'slug'  => '/^[a-zA-Z0-9_-]+$/',
        'uuid'  => '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
    ];

    /**
     * HTTP methods allowed to be spoofed via a `_method` form field, since
     * plain HTML forms only support GET/POST natively.
     *
     * @var string[]
     */
    private static array $spoofableMethods = ['PUT', 'PATCH', 'DELETE'];

    /**
     * Load route files into the router.
     *
     * When called with just an array of filenames, the files are resolved
     * from the directory registered under the alias 'routes' in
     * {@see Directory}.
     *
     * The optional second argument is the name of any other directory
     * alias registered in {@see Directory} — NOT a relative or absolute
     * filesystem path. To load route files from a custom location,
     * register the alias first and then refer to it by name.
     *
     * Examples:
     *   // Default: loads from the 'routes' alias.
     *   Route::source(['web.php', 'api.php']);
     *
     *   // Custom alias: register first, then load by alias name.
     *   Directory::set('shop_routes', 'modules/shop/routes');
     *   Route::source(['shop.php'], 'shop_routes');
     *
     * @param  string[]    $fileNames  Filenames to load.
     * @param  string|null $directory  Name of a directory alias registered in
     *                                 {@see Directory}. Defaults to 'routes'.
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
     * Middleware declared on nested groups is merged with (not replaced by)
     * the middleware inherited from any enclosing group, so a route always
     * runs the full chain of middleware from every group it is nested in.
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
                $newMiddlewares = is_array($options['middleware'])
                    ? $options['middleware']
                    : [$options['middleware']];

                $inheritedMiddlewares = $oldMiddlewareIndex !== -1
                    ? self::$middlewares[$oldMiddlewareIndex]
                    : [];

                if (!is_array($inheritedMiddlewares)) {
                    $inheritedMiddlewares = [$inheritedMiddlewares];
                }

                self::$middlewares[]   = array_merge($inheritedMiddlewares, $newMiddlewares);
                self::$middlewareIndex = count(self::$middlewares) - 1;
            }
        }

        if ($prefix !== '') {
            $prefix       = trim($prefix, '/');
            self::$prefix = self::$prefix === '' ? $prefix : self::$prefix . '/' . $prefix;
        }

        try {
            call_user_func($callback);
        } finally {
            // Always restore the previous routing state, even if the callback
            // throws. Otherwise an exception inside the group would leave the
            // accumulated prefix/middleware behind and leak it into every route
            // registered afterwards (state leak).
            self::$prefix          = $oldPrefix;
            self::$middlewareIndex = $oldMiddlewareIndex;
        }
    }

    /**
     * Generate the URL for a named route, substituting any parameters.
     *
     * Parameter values are percent-encoded so values containing slashes,
     * spaces or other reserved characters cannot corrupt the resulting URL.
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

        // Capture: 1 = name, 2 = optional '?', 3 = optional ':type'
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?(?::[a-zA-Z0-9]+)?\}/', $url, $matches);

        foreach ($matches[1] as $index => $param) {
            $placeholder = $matches[0][$index];
            $isOptional  = $matches[2][$index] === '?';

            if (!isset($params[$param])) {
                if ($isOptional) {
                    // Drop the placeholder and any leading slash it owns, so
                    // '/users/{id?}' becomes '/users' when 'id' is omitted.
                    $url = str_replace('/' . $placeholder, '', $url);
                    $url = str_replace($placeholder, '', $url);
                    continue;
                }

                Debug::triggerError("Missing required parameter '$param' for route '$name'.");
                return '';
            }

            $url = str_replace($placeholder, rawurlencode((string) $params[$param]), $url);
        }

        if (preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*(\?)?(?::[a-zA-Z0-9]+)?\}/', $url)) {
            Debug::triggerError("Some parameters were not replaced in route '$name'.");
            return '';
        }

        return $url === '' ? '/' : $url;
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
        $method       = self::resolveMethod();
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

        // Middleware guard — a failing middleware never falls through to the
        // handler. A middleware may return `false` for the default 403, or
        // an array (optionally containing a 'status' key) for a custom
        // response body/status code.
        $middlewareResult = self::processMiddleware($matchedRoute);

        if ($middlewareResult !== true) {
            self::respondMiddlewareFailure($middlewareResult);
            return;
        }

        self::dispatch($matchedRoute['handler'], $matchedRoute['params']);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the effective HTTP method for the current request.
     *
     * Plain HTML forms can only submit GET or POST, so a POST request
     * carrying a `_method` field (PUT, PATCH or DELETE) is treated as that
     * spoofed method. Any other value is ignored and the real method is used.
     *
     * @return string
     */
    private static function resolveMethod(): string
    {
        $method = Url::method();

        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper((string) $_POST['_method']);

            if (in_array($spoofed, self::$spoofableMethods, true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    /**
     * Send the response produced by a middleware that did not return `true`.
     *
     * Contract (mirrors Laravel/Symfony "return a Response to short-circuit"):
     *   - array  → JSON body; honours an optional 'status' key (default 403,
     *              since an array is typically a denial payload).
     *   - string → sent verbatim via Header::respond (e.g. a rendered view or
     *              redirect HTML); default status 200, because a view is a
     *              legitimate response, not necessarily an error.
     *   - object → JSON-encoded by Header::respond; default status 200.
     *   - anything else (false, null, ...) → default 403 Forbidden.
     *
     * @param  mixed $middlewareResult Any non-`true` middleware return value.
     * @return void
     */
    private static function respondMiddlewareFailure(mixed $middlewareResult): void
    {
        if (is_array($middlewareResult)) {
            $statusCode = $middlewareResult['status'] ?? 403;
            unset($middlewareResult['status']);
            Header::respond($middlewareResult, (int) $statusCode);
            return;
        }

        if (is_string($middlewareResult) || is_object($middlewareResult)) {
            Header::respond($middlewareResult, 200);
            return;
        }

        Header::respond(['error' => 'Forbidden'], 403);
    }

    /**
     * Match a route pattern against the current URI.
     *
     * Supports typed parameters using `{name:type}` (e.g. `{id:int}`). When
     * a type is given, the captured segment must satisfy the corresponding
     * built-in pattern or the route is rejected as a non-match.
     *
     * Supports optional parameters using a trailing `?` (e.g. `{id?}` or
     * `{id?:int}`). An optional parameter may be omitted from the URI; when
     * omitted it is simply absent from the returned params. Optional
     * parameters are only meaningful at the end of the pattern — once one
     * optional segment is omitted, no later segment may be present.
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
        $uriParts   = $uri === '' ? [] : explode('/', $uri);

        // The URI may legitimately be shorter than the pattern when trailing
        // parameters are optional, but it may never be longer.
        if (count($uriParts) > count($routeParts)) {
            return ['match' => false, 'params' => []];
        }

        $params = [];

        foreach ($routeParts as $index => $routePart) {
            $isParam    = str_starts_with($routePart, '{');
            $hasUriPart = array_key_exists($index, $uriParts);

            if (!$isParam) {
                // A static segment must be present and identical.
                if (!$hasUriPart || $routePart !== $uriParts[$index]) {
                    return ['match' => false, 'params' => []];
                }
                continue;
            }

            [$paramName, $paramType, $optional] = self::parseParamDefinition($routePart);

            if (!$hasUriPart) {
                // Missing segment is only acceptable for an optional parameter.
                if (!$optional) {
                    return ['match' => false, 'params' => []];
                }
                // Optional and omitted: skip it (and, by extension, everything
                // after it — guaranteed because uriParts is never longer).
                continue;
            }

            $value = $uriParts[$index];

            if ($paramType !== null && isset(self::$paramPatterns[$paramType])) {
                if (!preg_match(self::$paramPatterns[$paramType], $value)) {
                    return ['match' => false, 'params' => []];
                }
            }

            $params[$paramName] = $value;
        }

        return ['match' => true, 'params' => $params];
    }

    /**
     * Parse a `{name}`, `{name:type}`, `{name?}` or `{name?:type}` placeholder
     * into its name, optional type, and optional flag.
     *
     * @param  string $routePart The raw segment including braces.
     * @return array{0: string, 1: string|null, 2: bool}  [name, type|null, isOptional]
     */
    private static function parseParamDefinition(string $routePart): array
    {
        $paramDef  = trim($routePart, '{}');
        $paramType = null;

        if (str_contains($paramDef, ':')) {
            [$paramName, $paramType] = explode(':', $paramDef, 2);
        } else {
            $paramName = $paramDef;
        }

        $optional = false;
        if (str_ends_with($paramName, '?')) {
            $optional  = true;
            $paramName = substr($paramName, 0, -1);
        }

        return [$paramName, $paramType, $optional];
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
     * Run every middleware attached to a route and decide the outcome.
     *
     * A route proceeds to its handler ONLY if every middleware returns exactly
     * `true`. The first middleware that returns anything else short-circuits
     * the chain and its value becomes the response:
     *   - `false`              → default 403 (handled downstream)
     *   - array/string/object  → that value is sent as the response
     * This is fail-secure: any non-`true` value stops the route.
     *
     * @param  array $route
     * @return mixed `true` to proceed, or the short-circuiting response value.
     */
    private static function processMiddleware(array $route): mixed
    {
        if ($route['middleware'] === -1) {
            return true;
        }

        $middlewares = self::$middlewares[$route['middleware']];

        if (!is_array($middlewares)) {
            $middlewares = [$middlewares];
        }

        foreach ($middlewares as $middleware) {
            $result = self::executeMiddleware($middleware);

            // Only an exact `true` lets the chain continue; everything else
            // (false, array, string, object, null, ...) stops here.
            if ($result !== true) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Execute a single middleware and return its raw outcome.
     *
     * Supported forms:
     *   - Closure / any callable
     *   - 'function_name'  (global function)
     *   - 'Class@method'   (class-based middleware with a named method)
     *   - 'Class'          (class-based middleware; calls handle())
     *   - true / false     (static pass/fail, useful for testing)
     *
     * Return-value contract (interpreted by processMiddleware):
     *   - `true`               → pass; continue to the next middleware/handler
     *   - `false`              → deny with the default 403
     *   - array/string/object  → short-circuit; sent as the response
     * Unresolvable or invalid middleware fail secure (return `false`).
     *
     * @param  mixed $middleware
     * @return mixed
     */
    private static function executeMiddleware(mixed $middleware): mixed
    {
        if (is_bool($middleware)) {
            return $middleware;
        }

        if (is_callable($middleware)) {
            return self::normalizeMiddlewareResult($middleware());
        }

        if (is_string($middleware)) {
            // Class@method format
            if (str_contains($middleware, '@')) {
                [$class, $method] = explode('@', $middleware, 2);

                if (!class_exists($class)) {
                    Debug::triggerError("Middleware class '$class' not found.");
                    return false;
                }

                return self::normalizeMiddlewareResult((new $class())->{$method}());
            }

            // Plain class name — call handle()
            if (class_exists($middleware)) {
                return self::normalizeMiddlewareResult((new $middleware())->handle());
            }

            // Global function name
            if (function_exists($middleware)) {
                return self::normalizeMiddlewareResult(call_user_func($middleware));
            }

            Debug::triggerError("Middleware '$middleware' could not be resolved.");
            return false;
        }

        Debug::triggerError("Invalid middleware handler type.");
        return false;
    }

    /**
     * Normalize a raw middleware return value into the routing contract.
     *
     * Only a strict boolean `true` is treated as "pass"; a strict boolean
     * `false` is the default deny. Arrays, strings and objects are returned
     * untouched so they can be sent as a custom response (JSON payload, a
     * rendered view, a redirect body, etc.). Any other scalar that is *not*
     * exactly `true` (e.g. 1, "1", 0, null) is deliberately NOT treated as a
     * pass — it collapses to the default deny — keeping the contract
     * fail-secure: a route advances only on an explicit `true`.
     *
     * @param  mixed $result
     * @return mixed `true`, a response value (array/string/object), or `false`.
     */
    private static function normalizeMiddlewareResult(mixed $result): mixed
    {
        if ($result === true) {
            return true;
        }

        if (is_array($result) || is_string($result) || is_object($result)) {
            return $result;
        }

        // false, null, 0, 1, '' and any other non-true scalar → default deny.
        return false;
    }
}