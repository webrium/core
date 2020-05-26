<?php
namespace webrium\core;

use webrium\core\App;

class Directory
{
  private static $params=[];

  public static function set($name,$value)
  {
    self::$params[$name]=$value;
  }

  public static function path($name)
  {
    return App::index_path().self::$params[$name];
  }

  public static function get($name)
  {
    return self::$params[$name];
  }
}
