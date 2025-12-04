<?php

namespace Webrium;

use Webrium\Url;
use Webrium\File;
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
     * Load route files from the routes directory
     *
     * @param array $fileNames Array of route file names to load
     * @return void
     */
    public static function source(array $fileNames)
    {
        $path = Directory::path('routes');

        foreach ($fileNames as $fileName) {
            $result = File::runOnce("$path/$fileName");

            if ($result === false) {
                Debug::triggerError("Route file '$fileName' not found.");
            }
        }
    }

    /**
     * Add a new route to the routes collection
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, ANY)
     * @param string $url URL pattern
     * @param callable|string $handler Route handler (callback or "Controller@method")
     * @param string $name Optional route name
     * @return Route Returns self for method chaining
     */
    private static function add(string $method, string $url, $handler, string $name = '')
    {
        $url = trim($url, '/');

        
        if (!empty(self::$prefix)) {
            $url = self::$prefix . '/' . $url;
        }


        $routeIndex = count(self::$routes);
        self::$routes[] = [
            'method' => $method,
            'url' => '/' . $url,
            'handler' => $handler,
            'middleware' => self::$middlewareIndex,
            'params' => []
        ];
        if (!empty($name)) {
            if (isset(self::$routeNames[$name])) {
                Debug::triggerError("Route name '$name' is already registered.");
            }
            self::$routeNames[$name] = $routeIndex;
        }

        return new self();
    }

    /**
     * Register GET route
     *
     * @param string $url URL pattern
     * @param callable|string $handler Route handler
     * @param string $name Optional route name
     * @return Route
     */
    public static function get(string $url, $handler, string $name = '')
    {
        return self::add('GET', $url, $handler, $name);
    }

    /**
     * Register POST route
     *
     * @param string $url URL pattern
     * @param callable|string $handler Route handler
     * @param string $name Optional route name
     * @return Route
     */
    public static function post(string $url, $handler, string $name = '')
    {
        return self::add('POST', $url, $handler, $name);
    }

    /**
     * Register PUT route
     *
     * @param string $url URL pattern
     * @param callable|string $handler Route handler
     * @param string $name Optional route name
     * @return Route
     */
    public static function put(string $url, $handler, string $name = '')
    {
        return self::add('PUT', $url, $handler, $name);
    }

    /**
     * Register DELETE route
     *
     * @param string $url URL pattern
     * @param callable|string $handler Route handler
     * @param string $name Optional route name
     * @return Route
     */
    public static function delete(string $url, $handler, string $name = '')
    {
        return self::add('DELETE', $url, $handler, $name);
    }

    /**
     * Register route for any HTTP method
     *
     * @param string $url URL pattern
     * @param callable|string $handler Route handler
     * @param string $name Optional route name
     * @return Route
     */
    public static function any(string $url, $handler, string $name = '')
    {
        return self::add('ANY', $url, $handler, $name);
    }

    /**
     * Set route name for the last registered route
     *
     * @param string $name Route name
     * @return void
     */
    public function name(string $name)
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
     * Group routes with common prefix and/or middleware
     *
     * @param string|array $options Prefix string or array with 'prefix' and/or 'middleware'
     * @param callable $callback Callback to define routes in the group
     * @return void
     */
    public static function group($options, callable $callback)
    {
        $prefix = '';
        $oldPrefix = self::$prefix;
        $oldMiddlewareIndex = self::$middlewareIndex;

        if (is_string($options)) {
            $prefix = $options;
        } elseif (is_array($options)) {
            if (isset($options['prefix'])) {
                $prefix = $options['prefix'];
            }

            if (isset($options['middleware'])) {
                self::$middlewares[] = $options['middleware'];
                self::$middlewareIndex = count(self::$middlewares) - 1;
            }
        }

        if (!empty($prefix)) {
            $prefix = trim($prefix, '/');
            self::$prefix = empty(self::$prefix) ? $prefix : self::$prefix . '/' . $prefix;
        }

        call_user_func($callback);

        self::$prefix = $oldPrefix;
        self::$middlewareIndex = $oldMiddlewareIndex;
    }

    /**
     * Generate URL for a named route with parameters
     *
     * @param string $name Route name
     * @param array $params Route parameters
     * @return string Generated URL
     * @throws \Exception
     */
    public static function route(string $name, array $params = [])
    {
        if (!isset(self::$routeNames[$name])) {
            Debug::triggerError("Route with name '$name' not found.");
            return '';
        }

        $route = self::$routes[self::$routeNames[$name]];
        $url = $route['url'];

        // Extract required parameters from route pattern
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $url, $matches);
        $requiredParams = $matches[1];

        // Check if all required parameters are provided
        foreach ($requiredParams as $param) {
            if (!isset($params[$param])) {
                Debug::triggerError("Missing required parameter '$param' for route '$name'.");
                return '';
            }
        }

        // Replace parameters in URL
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }

        // Check if all parameters were replaced
        if (preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $url)) {
            Debug::triggerError("Some parameters were not replaced in route '$name'.");
            return '';
        }

        return $url;
    }

    /**
     * Match route pattern against URI
     *
     * @param string $routeUrl Route pattern
     * @param string $uri Request URI
     * @return array Match result with params
     */
    private static function matchRoute(string $routeUrl, string $uri): array
    {
        $uri = trim($uri, '/');
        $routeUrl = trim($routeUrl, '/');

        // Exact match
        if ($routeUrl === $uri) {
            return ['match' => true, 'params' => []];
        }

        // Check for parameters
        if (strpos($routeUrl, '{') === false) {
            return ['match' => false, 'params' => []];
        }

        $routeParts = explode('/', $routeUrl);
        $uriParts = explode('/', $uri);

        // Different segment count
        if (count($routeParts) !== count($uriParts)) {
            return ['match' => false, 'params' => []];
        }

        $params = [];

        foreach ($routeParts as $index => $routePart) {
            if (strpos($routePart, '{') !== false) {
                // Extract parameter name
                $paramName = trim($routePart, '{}');
                $params[$paramName] = $uriParts[$index];
            } elseif ($routePart !== $uriParts[$index]) {
                // Segment mismatch
                return ['match' => false, 'params' => []];
            }
        }

        return ['match' => true, 'params' => $params];
    }

    /**
     * Set custom 404 handler
     *
     * @param callable|string $handler Handler for 404 pages
     * @return void
     */
    public static function setNotFoundHandler($handler)
    {
        self::$notFoundHandler = $handler;
    }

    /**
     * Handle 404 page not found
     *
     * @return void
     */
    private static function handleNotFound()
    {
        Debug::setStatusCode(404);

        if (self::$notFoundHandler === null) {
            echo 'Page not found';
        } elseif (is_string(self::$notFoundHandler)) {
            self::executeControllerMethod(self::$notFoundHandler);
        } elseif (is_callable(self::$notFoundHandler)) {
            App::ReturnData(call_user_func(self::$notFoundHandler));
        }
    }

    /**
     * Execute controller method
     *
     * @param string $handlerString Handler in format "Controller@method"
     * @param array $params Method parameters
     * @return mixed
     */
    public static function executeControllerMethod(string $handlerString, array $params = [])
    {
        if (strpos($handlerString, '@') === false) {
            Debug::triggerError("Invalid handler format: '$handlerString'. Expected 'Controller@method'.");
            return null;
        }

        list($controller, $method) = explode('@', $handlerString, 2);
        return File::executeControllerMethod('controllers', $controller, $method, $params);
    }

    /**
     * Run the router and match current request
     *
     * @param int $startIndex Starting index for route matching (used for fallback)
     * @return void
     */
    public static function run(int $startIndex = 0)
    {
        $uri = Url::uri();
        $method = Url::method();
        $matchedRoute = null;
        $currentIndex = $startIndex;

        foreach (array_slice(self::$routes, $startIndex, null, true) as $index => $route) {
            $currentIndex = $index;

            if ($method === $route['method'] || $route['method'] === 'ANY') {
                $matchResult = self::matchRoute($route['url'], $uri);

                if ($matchResult['match']) {
                    $matchedRoute = $route;
                    $matchedRoute['params'] = $matchResult['params'];
                    break;
                }
            }
        }

        if ($matchedRoute === null) {
            self::handleNotFound();
            return;
        }

        // Process middleware
        if (!self::processMiddleware($matchedRoute)) {
            self::run($currentIndex + 1);
            return;
        }

        // Execute handler
        if (is_string($matchedRoute['handler'])) {
            self::executeControllerMethod($matchedRoute['handler'], $matchedRoute['params']);
        } elseif (is_callable($matchedRoute['handler'])) {
            $result = call_user_func_array($matchedRoute['handler'], $matchedRoute['params']);
            App::ReturnData($result);
        } else {
            Debug::triggerError("Invalid route handler type.");
        }
    }

    /**
     * Process route middleware
     *
     * @param array $route Matched route
     * @return bool Whether access is granted
     */
    private static function processMiddleware(array $route): bool
    {
        if ($route['middleware'] === -1) {
            return true;
        }

        $middlewares = self::$middlewares[$route['middleware']];

        if (is_array($middlewares)) {
            foreach ($middlewares as $middleware) {
                if (!self::executeMiddleware($middleware)) {
                    return false;
                }
            }
            return true;
        }

        return self::executeMiddleware($middlewares);
    }

    /**
     * Execute a single middleware
     *
     * @param mixed $middleware Middleware to execute
     * @return bool Whether middleware passes
     */
    private static function executeMiddleware($middleware): bool
    {
        if (is_callable($middleware)) {
            return (bool) $middleware();
        }

        if (is_string($middleware)) {
            return (bool) call_user_func($middleware);
        }

        if (is_bool($middleware)) {
            return $middleware;
        }

        Debug::triggerError("Invalid middleware handler type.");
        return false;
    }
}