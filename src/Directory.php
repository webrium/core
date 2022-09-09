<?php
namespace webrium;

use Webrium\App;

class Directory
{
  private static $params=[];

  public static function set($name,$value)
  {
    self::$params[$name]=$value;
  }

  public static function path($name)
  {
    return App::rootPath().'/'.self::$params[$name];
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
    Directory::set('controllers','app/Controllers');
    Directory::set('models','app/Models');
    Directory::set('views','app/Views');
    Directory::set('routes','app/Routes');
    Directory::set('config','app/Config');

    Directory::set('storage','storage');
    Directory::set('storage_app','storage/App');
    Directory::set('sessions','storage/Framework/Sessions');
    Directory::set('render_views','storage/Framework/Views');

    Directory::set('logs','storage/Logs');
    Directory::set('langs','storage/Langs');

    Directory::set('public','public');
  }
}
