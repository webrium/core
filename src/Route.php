<?php
namespace Webrium;

use Webrium\Url;
use Webrium\File;

class Route
{

  private static $found = false;

  public static function isFound()
  {
    return self::$found;
  }

  public static function get($addr,$file)
  {
    self::check("GET",$addr,$file);
  }

  public static function post($addr,$file)
  {
    self::check("POST",$addr,$file);
  }

  public static function put($addr,$file)
  {
    self::check("PUT",$addr,$file);
  }

  public static function delete($addr,$file)
  {
    self::check("DELETE",$addr,$file);
  }

  public static function any($addr,$file)
  {
    self::check("ALL",$addr,$file);
  }

  public static function check($method,$addr,$file)
  {
    if ((Url::method()==$method || $method=='ALL')&& Url::is($addr)) {

      if (is_string($file)) {

        // run controller function
        $res= self::call($file);

        // route found
        self::$found=true;

        // function not found
        if ($res['func']==false) {
          Debug::createError($res['error_message'],$res['class_path'],false,500);
        }

      }
      else {
        App::ReturnData($file());
      }
    }

    if(self::isFound()){
      die;
    }
  }

  public static function call($file)
  {
    $arr = explode('@',$file);

    $dir = 'controllers';

    if (count($arr)==2) {
      $dir    = $arr[0];
      $arr[0] = $arr[1];
    }

    $class_func=explode('->',$arr[0]);
    return File::runControllerFunction($dir,$class_func[0],$class_func[1]);
  }

  public static function notFound($file=false)
  {
    if ( ! self::isFound()) {

      if ( $file == false ) {
        Debug::error404();
      }
      else if ( $file != false ) {
        self::call($file);
      }

      die;

    }
  }

}
