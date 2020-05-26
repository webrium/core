<?php
namespace webrium\core;

use webrium\core\Url;
use webrium\core\File;

class Route
{
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
      self::call($file);
    }
  }

  public static function call($file)
  {
    $arr = explode('@',$file);
    $class_func=explode('->',$arr[1]);
    File::runControllerFunction($arr[0],$class_func[0],$class_func[1]);
  }
}
