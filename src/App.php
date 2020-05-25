<?php
namespace webrium\core;

class App
{
  private static $index_dir=false;

  public static function index($dir)
  {
    self::$index_dir=str_replace('\\','/',realpath($dir).'/');
  }

  public static function index_path()
  {
    return self::$index_dir;
  }

}
