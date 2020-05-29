<?php
namespace webrium\core;

use webrium\core\App;

class Directory
{
  private static $params=[];

  public static function set($name,$value)
  {
    self::$params[$name]=$value;
  }

  public static function path($name)
  {
    return App::rootPath().self::$params[$name];
  }

  public static function get($name)
  {
    return self::$params[$name];
  }

  public static function all()
  {
    return self::$params;
  }

  public static function makes()
  {
    foreach (self::all() as $key => $dirs) {

      $path = self::path($key);

      self::make($path);

    }
  }

  public static function make($path)
  {
    if (! is_dir($path) ) {
      \mkdir($path, 0755, true);
    }
  }

  public static function validate()
  {
    $valid = true;
    foreach (self::all() as $key => $dirs) {

      $path = self::path($key);

      if (! is_dir($path) ) {
        $valid=false;
      }

    }
    
    return $valid;
  }



  public static function initDefaultStructure()
  {
    Directory::set('app','app');
    Directory::set('controllers','app/controllers');
    Directory::set('models','app/models');
    Directory::set('views','app/views');
    Directory::set('routes','app/routes');
    Directory::set('config','app/config');

    Directory::set('storage','app/storage');
    Directory::set('storage_app','app/storage/app');

    Directory::set('sessions','app/storage/framework/sessions');
    Directory::set('render_views','app/storage/framework/views');
    Directory::set('logs','app/storage/logs');

    Directory::set('public','public');
  }
}
