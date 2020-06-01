<?php
namespace webrium\core;

use webrium\core\Url;
use webrium\core\Debug;
use webrium\core\File;

class App
{
  private static $rootPath=false;

  public static function root($dir)
  {
    self::$rootPath=str_replace('\\','/',realpath($dir).'/');

    self::init_spl_autoload_register();

    File::runOnce(__DIR__.'/lib/Helper.php');
  }

  public static function init_spl_autoload_register()
  {
    spl_autoload_register(function($class){

      $class = App::rootPath().$class;
      $name=str_replace('\\','/',$class).".php";

      if (File::exists($name)) {
        File::runOnce($name);
      }
      else {
        Debug::createError("Class '".basename($class)."' not found",false,false,500);
      }

    });
  }

  public static function rootPath()
  {
    return self::$rootPath;
  }


  public static function input($name=false,$default=null) {
    $method = Url::method();
    $params=[];

    if ($method == "GET") {
      $params= $_GET;
    }
    else if ($method == "POST") {
      $params= $_POST;
    }
    else if ($method == "PUT" || $method == "DELETE") {
      parse_str(file_get_contents('php://input'),$params);
    }

    if ($name!=false) {
      return $params[$name]??$default;
    }

    return $params;
  }

  public static function ReturnData($data){
    if(is_array($data) || is_object($data))
    {
      header('Content-Type: application/json ; charset=utf-8 ');
      $data=json_encode($data);
    }

    echo $data;
  }

}
