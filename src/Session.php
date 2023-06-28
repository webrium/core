<?php

namespace Webrium;

class Session
{

  private static $session_start_app_status = false;
  private static $save_path = false;

  /**
   * Set the path for storing session files.
   * 
   * @param string $path The path to store session files.
   * @return void
   */
  public static function set_path($path)
  {
    self::$save_path = $path;
  }

  /**
   * Start a new session or resume an existing one.
   * If the session has already started, this method does nothing.
   * 
   * @return void
   */
  public static function start()
  {
    if (Session::$session_start_app_status == false) {

      if (self::$save_path != false) {
        \session_save_path(self::$save_path);
      }

      \session_start();

      Session::$session_start_app_status = true;
    }
  }

  /**
   * Get or set the session ID.
   * 
   * @param boolean|string $id The session ID to set, or false to get the current session ID.
   * @return string|void The current session ID, or void if setting the session ID.
   */
  public static function id($id = false)
  {
    if (!$id) {
      return \session_id();
    } else {
      return \session_id($id);
    }
  }

  /**
   * Get or set the name of the current session.
   * 
   * @param boolean|string $name The session name to set, or false to get the current session name.
   * @return string|void The current session name, or void if setting the session name.
   */
  public static function name($name = false)
  {
    if (!$name) {
      return \session_name();
    } else {
      return \session_name($name);
    }
  }

  /**
   * Set session variables.
   * 
   * @param array|string $param The name of the session variable to set, or an associative array of session variables.
   * @param mixed $value The value to set for the session variable (required if $param is a string).
   * @return void
   */
  public static function set($param, $value = false)
  {
    Session::start();
    if (is_array($param)) {
      foreach ($param as $key => $value) {
        $_SESSION[$key] = $value;
      }
    } else {
      $_SESSION[$param] = $value;
    }
  }

  /**
   * Get the value of a session variable.
   * 
   * @param string $name The name of the session variable.
   * @param mixed $default The default value to return if the session variable does not exist.
   * @return mixed The value of the session variable, or the default value if it does not exist.
   */
  public static function get($name, $default = false)
  {
    Session::start();

    if (isset($_SESSION[$name]) == false || $_SESSION[$name] == null) {
      return $default;
    }
    return $_SESSION[$name];
  }

  /**
   * Get the value of a session variable once and remove it from the session.
   * 
   * @param string|array $name The name of the session variable, or an array of session variables.
   * @param mixed $default The default value to return if the session variable does not exist.
   * @return mixed The value of the session variable, or the default value if it does not exist.
   */
  public static function once($name = false, $default = false)
  {
    $res = self::get($name, $default);
    self::remove($name);
    return $res;
  }

  /**
   * Get all session variables.
   * 
   * @return array An associative array of all session variables.
   */
  public static function all()
  {
    Session::start();
    return $_SESSION;
  }

  /**
   * Remove a session variable.
   * 
   * @param string $name The name of the session variable to remove.
   * @return boolean True if the session variable was removed, false otherwise.
   */
  public static function remove($name)
  {
    Session::start();
    if (isset($_SESSION[$name]) == false || $_SESSION[$name] == null) {
      return false;
    }
    unset($_SESSION[$name]);
    return true;
  }

  /**
   * Clear all session variables and destroy the session.
   * 
   * @return void
   */
  public static function clear()
  {
    Session::start();
    \session_unset();
    \session_destroy();
  }

  /**
   * Set the session cookie lifetime in seconds.
   *
   * @param int $sec The number of seconds to set as the session cookie lifetime.
   * @return void
   */
  public static function lifetime($sec)
  {
    ini_set('session.cookie_lifetime', $sec);
    ini_set('session.gc_maxlifetime', $sec);
  }
}
