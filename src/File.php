<?php
namespace webrium\core;

use webrium\core\App;
use webrium\core\Directory;


class File
{
  public static function exists($path)
  {
    return file_exists($path);
  }

  public static function run($path)
  {
    include_once $path;
  }

  public static function runControllerFunction($dir_name,$class,$func)
  {
    $status =['start_exec_time'=>microtime(true)];

    $dir = Directory::get($dir_name);
    $dir=str_replace('/','\\',$dir);

    $n = "$dir\\$class";
    $controller =new $n;

    if (method_exists($controller,'init')) {
      $status['init']=true;
    }
    else {
      $status['init']=false;
    }

    if (method_exists($controller,$func)) {
      $status['func']=true;
    }
    else {
      $status['func']=false;
      $status['code']=404;
      $status['error_level']='function';
    }

    if (method_exists($controller,'end')) {
      $status['end']=true;
    }
    else {
      $status['end']=false;
    }


    if ($status['func']) {

      if ($status['init']) {
        $controller->init();
      }

      if ($status['func']) {
        App::ReturnData($controller->{$func}());
        $status['exec']=true;
      }

      if ($status['end']) {
        $controller->end();
      }

    }

    $status['end_exec_time']=microtime(true);

    return $status;
  }
}
