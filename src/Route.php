<?php

namespace Webrium;

use Error;
use Webrium\Url;
use Webrium\File;
use Webrium\Debug;

class Route
{

  private static $routes;

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


  private static function add($method, $url, $handler)
  {

    $url = trim($url, '/');

    if(!empty(self::$prefix)){
      $url = self::$prefix."/$url";
    }

    self::$routes[] = [$method, $url, $handler];
  }


  public static function get($url, $handler)
  {
    self::add('GET', $url, $handler);
    return static::class;
  }

  public static function post($url, $handler)
  {
    self::add('POST', $url, $handler);
  }

  public static function put($url, $handler)
  {
    self::add('PUT', $url, $handler);
  }

  public static function delete($url, $handler)
  {
    self::add('DELETE', $url, $handler);
  }


  public static function group($prefix, callable $callback)
  {
      $prefix = trim($prefix, '/');

      self::$prefix = $prefix;

      call_user_func($callback);

      self::$prefix = '';
  }


  public static function run(){
    die(json_encode(self::$routes));
  }

}
