<?php
namespace webrium\core;

class Debug
{
  public static function displayErrors($status)
  {
    if ($status) {
      ini_set('display_errors', $status);
      ini_set('display_startup_errors', $status);
      error_reporting(E_ALL);
    }
  }

  public static function code($code)
  {
    http_response_code($code);
  }

  public static function error404()
  {
    self::code(404);
    echo "404";
  }
}
