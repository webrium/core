<?php

namespace Webrium;

use Error;
use Webrium\Url;
use Webrium\File;
use Webrium\Debug;

class Route
{

  private static $routes;


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
}
