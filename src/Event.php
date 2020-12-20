<?php
namespace webrium\core;

class Event {
  private static $instance;
  private $hooks = array();

  public static function on($hook_name, $fn){
    $instance = self::get_instance();
    $instance->hooks[$hook_name][] = $fn;
  }

  public static function emit($hook_name, $params = null){
    $instance = self::get_instance();

    if (isset($instance->hooks[$hook_name])) {
      foreach ($instance->hooks[$hook_name] as $fn) {
        call_user_func_array($fn, array(&$params));
      }
    }

  }

  public static function remove($hook_name){
    $instance = self::get_instance();
    unset($instance->hooks[$hook_name]);
    var_dump($instance->hooks);
  }

  public static function get_instance(){
    if (empty(self::$instance)) {
      self::$instance = new Event();
    }
    return self::$instance;
  }
  
}
