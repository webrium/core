<?php
namespace Webrium;

use Webrium\Directory;

class Session
{

  public static $session_startAppStatus=false;
  private static $save_path=false;

  public static function set_path($path)
  {
    self::$save_path=$path;
  }

  public static function start()
  {
    if (Session::$session_startAppStatus == false) {

      if (self::$save_path!=false) {
        \session_save_path(self::$save_path);
      }

      \session_start();

      Session::$session_startAppStatus=true;
    }
  }

  public static function id($id=false){
    if (!$id) {
      return \session_id();
    }
    else {
      return \session_id($id);
    }
  }

  public static function name($name=false){
    if (!$name) {
      return \session_name();
    }
    else {
      return \session_name($name);
    }
  }

  public static function set($param,$value=false)
  {
    Session::start();
    if (is_array($param)) {
      foreach ($param as $key => $value) {
        $_SESSION[$key]=$value;
      }
    }
    else {
      $_SESSION[$param]=$value;
    }
  }

  public static function get($name,$default=false)
  {
    Session::start();

    if (isset($_SESSION[$name]) == false || $_SESSION[$name] == null) {
      return $default;
    }
    return $_SESSION[$name];
  }

  /**
  * It can be called only once and deletes it after reading
  * @param $name [string or Array of objects]
  * @param $default
  * @return session value
  */
  public static function once($name=false,$default=false){
    $res = self::get($name,$default);
    self::remove($name);
    return $res;
  }

  public static function all()
  {
    Session::start();
    return $_SESSION;
  }

  public static function remove($name)
  {
    Session::start();
    if (isset($_SESSION[$name]) ==false || $_SESSION[$name]==null) {
      return false;
    }
    unset($_SESSION[$name]);
    return true;
  }

  public static function clear()
  {
    Session::start();
    \session_unset();
    \session_destroy();
  }

  public static function lifetime($sec){
    ini_set('session.cookie_lifetime', $sec);
    ini_set('session.gc_maxlifetime' , $sec);
  }

}
