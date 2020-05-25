<?php
namespace webrium\core;

class App
{
  private static $index_dir=false;

  public static function index($dir)
  {
    self::$index_dir=str_replace('\\','/',realpath($dir).'/');

    self::init_spl_autoload_register();
  }

  public static function init_spl_autoload_register()
  {
    spl_autoload_register(function($name){
      $name=str_replace('\\','/',$name).".php";
      if (File::exists($name)) {
        File::run($name);
      }
    });
  }

  public static function index_path()
  {
    return self::$index_dir;
  }

}
