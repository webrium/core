<?php

namespace Webrium;

use Webrium\Url;
use Webrium\File;
use Webrium\Debug;

class Route
{

  private static $routes;
  private static $route_names = [];
  private static $prefix = '';
  private static $notFoundHandler = false;


  /**
   * Loads route files given an array of file names.
   *
   * @param array $route_file_names An array of strings representing the names of route files to be loaded.
   *                               These files must be stored in the 'Routes' directory.
   */
  public static function source(array $route_file_names)
  {

    // Set path to the directory containing route files
    $path = Directory::path('routes');

    // Loop through each route file name and attempt to load it
    foreach ($route_file_names as $file_name) {

      $result = File::runOnce("$path/$file_name");

      // If the file is not found or is unable to run, create an error message using the Debug class
      if ($result == false) {
        Debug::createError("Route file '$file_name' not found.");
      }
    }
  }





  /**
   * Add a new route to the list of routes.
   *
   * @param string $method The HTTP method for this route.
   * @param string $url The URL pattern for this route.
   * @param callable $handler The function that will handle the request for this route.
   * @param string $route_name (Optional) The name of this route. Defaults to empty string.
   */
  private static function add(string $method, string $url, string|callable $handler, string $route_name = '')
  {

    $url = trim($url, '/');

    if (!empty(self::$prefix)) {
      $url = self::$prefix . "/$url";
    }

    self::$routes[] = [$method, "/$url", $handler];

    if (empty($route_name) == false) {
      self::$route_names[$route_name] = count(self::$routes) - 1;
    }
  }


  /**
   * Register a new route with the GET method
   *
   * @param string $url Route URL.
   * @param string $handler Controller and method name for handling this route in format "ControllerName->MethodName".
   * @param string $route_name Name of the route (optional).
   * 
   * @return void
   */
  public static function get(string $url, string|callable $handler, string $route_name = '')
  {
    self::add('GET', $url, $handler, $route_name);
  }

  /**
   * Register a new route with the POST method
   *
   * @param string $url Route URL.
   * @param string $handler Controller and method name for handling this route in format "ControllerName->MethodName".
   * @param string $route_name Name of the route (optional).
   * 
   * @return void
   */
  public static function post(string $url, string|callable $handler, string $route_name = '')
  {
    self::add('POST', $url, $handler, $route_name);
  }

  /**
   * Register a new route with the PUT method
   *
   * @param string $url Route URL.
   * @param string $handler Controller and method name for handling this route in format "ControllerName->MethodName".
   * @param string $route_name Name of the route (optional).
   * 
   * @return void
   */
  public static function put(string $url, string|callable $handler, string $route_name = '')
  {
    self::add('PUT', $url, $handler, $route_name);
  }

  /**
   * Register a new route with the DELETE method
   *
   * @param string $url Route URL.
   * @param string $handler Controller and method name for handling this route in format "ControllerName->MethodName".
   * @param string $route_name Name of the route (optional).
   * 
   * @return void
   */
  public static function delete(string $url, string|callable $handler, string $route_name = '')
  {
    self::add('DELETE', $url, $handler, $route_name);
  }





  /**
   * Group a series of routes under a common prefix.
   *
   * @param string $prefix Prefix to group the routes under.
   * @param callable $callback Function to execute the grouped routes.
   * 
   * @return void
   */
  public static function group(string $prefix, callable $callback)
  {
    $prefix = trim($prefix, '/');

    self::$prefix = $prefix;

    call_user_func($callback);

    self::$prefix = '';
  }


  /**
   * Get the URL of a named route.
   *
   * @param string $route_name Name of the route to get the URL for.
   * 
   * @return string URL of the named route.
   * @throws Exception If the named route does not exist.
   */
  public static function getRouteByName(string $route_name): string
  {
    if (isset(self::$route_names[$route_name])) {
      $route = self::$routes[self::$route_names[$route_name]];
      return $route[1];
    } else {
      Debug::createError("Route with name '{$route_name}' not found.");
    }
  }




  /**
   * Compares a route and a URI to check if they match.
   * If the route contains parameters, it extracts them from the URI
   * and returns them as an array of key-value pairs.
   *
   * @param string $route The route to compare with the URI.
   * @param string $uri The URI to compare with the route.
   * @return array An associative array containing a boolean 'match' value,
   *               indicating whether the route and URI matched, and an array of
   *               route parameters with their corresponding values (if any).
   */
  private static function matchRoute($route, $uri)
  {

    $match = false;
    $params = [];

    $uri = trim($uri, '/');
    $route = trim($route, '/');

    if ($route === $uri) {
      $match = true;
    } else {
      $parameter_sign = strpos($route, '{');

      if ($parameter_sign !== false) {

        $route_array = explode('/', $route);
        $uri_array = explode('/', $uri);

        if (count($uri_array) == count($route_array)) {

          $match = true;

          foreach ($route_array as $key => $route_str) {


            $sign = strpos($route_str, '{');

            if ($sign !== false) {
              $param_name =  trim(str_replace(['{', '}'], ['', ''], $route_str));
              $param_value = $uri_array[$key];
              $params[$param_name] = $param_value;
            } else if ($route_str != $uri_array[$key]) {
              $match = false;
              break;
            }
          }
        }
      }
    }

    return ['match' => $match, 'params' => $match ? $params : []];
  }


  public static function setNotFoundRoutePage(callable|string $handler)
  {
    self::$notFoundHandler = $handler;
  }


  public static function pageNotFound()
  {
    Debug::error404();

    if (self::$notFoundHandler == false) {
      echo 'Page not found';
    } else if (is_string(self::$notFoundHandler)) {
      return self::runController(self::$notFoundHandler);
    } else if (is_callable(self::$notFoundHandler)) {
      return App::ReturnData(call_user_func(self::$notFoundHandler));
    }
  }

  public static function runController(string $handler_string, $params = [])
  {
    $class_func = explode('->', $handler_string);
    return File::executeControllerMethod('controllers', $class_func[0], $class_func[1], $params);
  }


  public static function run()
  {

    $uri = Url::uri();

    $find_match_route = [];
    $find = false;
    $method = Url::method();

    foreach (self::$routes as $route) {

      if ($method == $route[0] || $route[0] == 'ANY') {

        $result = self::matchRoute($route[1], $uri);

        if ($result['match']) {
          $find = true;
          $find_match_route = $route;
          $find_match_route['params'] = $result['params'];
          break;
        }
      }
    }

    if ($find) {

      if (is_string($find_match_route[2])) {
        self::runController($find_match_route[2], $find_match_route['params']);
      } else if (is_callable($find_match_route[2])) {
        App::ReturnData($find_match_route[2](...$find_match_route['params']));
      }
    } else {
      self::pageNotFound();
    }
  }
}
