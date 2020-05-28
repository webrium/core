<?php
namespace webrium\core;

use webrium\core\Url;
use webrium\core\File;

class App
{
  private static $index_dir=false;

  public static function index($dir)
  {
    self::$index_dir=str_replace('\\','/',realpath($dir).'/');

    self::init_spl_autoload_register();

    File::runOnce(__DIR__.'/lib/Helper.php');
  }

  public static function init_spl_autoload_register()
  {
    spl_autoload_register(function($name){

      $name = App::index_path().$name;
      $name=str_replace('\\','/',$name).".php";

      if (File::exists($name)) {
        File::runOnce($name);
      }
      
    });
  }

  public static function index_path()
  {
    return self::$index_dir;
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
