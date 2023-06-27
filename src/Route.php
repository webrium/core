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
  private static function add(string $method, string $url, string $handler, string $route_name = '')
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
  public static function get(string $url, string $handler, string $route_name = '')
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
  public static function post(string $url, string $handler, string $route_name = '')
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
  public static function put(string $url, string $handler, string $route_name = '')
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
  public static function delete(string $url, string $handler, string $route_name = '')
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




  public static function run()
  {
    die(json_encode(self::$routes));
  }
}
