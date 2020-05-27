<?php
namespace webrium\core;

use webrium\core\Directory;

class Session
{

  public static $session_startAppStatus=false;
  private static $save_path;

  public static function save_dir_name($name)
  {
    self::$save_path=Directory::path($name);
  }

  public static function start()
  {
    if (Session::$session_startAppStatus == false) {

      \session_save_path (self::$save_path);
      \session_start();

      Session::$session_startAppStatus=true;
    }
  }

  public static function set($params)
  {
    Session::start();
    foreach ($params as $key => $value) {
      $_SESSION[$key]=$value;
    }
  }

  public static function get($name=false)
  {
    Session::start();

    if (isset($_SESSION[$name]) ==false || $_SESSION[$name]==null) {
      return false;
    }
    return $_SESSION[$name];
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

}
