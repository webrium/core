<?php

namespace Webrium;

use Webrium\Url;
use Webrium\Debug;
use Webrium\File;
use Webrium\Directory;

class App
{
  private static $rootPath = false, $local = 'en';
  private static $env = [];

  public static function root($dir)
  {
    self::rootPath($dir);

    self::init_spl_autoload_register();

    File::runOnce(__DIR__ . '/lib/Helper.php');

    Url::ConfirmUrl();
  }

  public static function init_spl_autoload_register()
  {
    spl_autoload_register(function ($class) {

      if (substr($class, 0, 4) == 'App\\') {
        $class[0] = 'a';
      }

      $class = App::rootPath() . "/$class";
      $name = str_replace('\\', '/', $class) . ".php";

      if (File::exists($name)) {
        File::runOnce($name);
      } else {
        Debug::createError("Class '" . basename($class) . "' not found", false, false, 500);
      }
    });
  }

  public static function rootPath($dir = false)
  {
    if ($dir) {
      self::$rootPath = str_replace('\\', '/', realpath($dir) . '/');
    }

    return Url::without_trailing_slash(self::$rootPath);
  }

  public static function input($name = false, $default = null)
  {
    $method = Url::method();
    $params = [];
    $json_content_status = ($_SERVER["CONTENT_TYPE"] ?? '') == 'application/json';

    if ($json_content_status == false && ($method == 'GET' || $method == 'PUT' || $method == 'DELETE')) {
      $params = $_GET;
    } else if ($method == 'POST' || $method == 'PUT' || $method == 'DELETE') {
      if ($json_content_status) {
        $params = json_decode(file_get_contents('php://input'), true);
      } else {
        $params = $_POST;
      }

    }

    if ($name != false) {
      return $params[$name] ?? $default;
    }

    return $params;
  }

  public static function ReturnData($data)
  {
    if (is_array($data) || is_object($data)) {
      header('Content-Type: application/json ; charset=utf-8 ');
      $data = json_encode($data);
    }

    echo $data;
  }


  /**
   * Gets the value of an environment variable.
   *
   * @param  string  $key
   * @param  mixed   $default
   * @return mixed
   */
  public static function env($name, $default = false)
  {
    if (self::$env == false) {
      if (File::exists(root_path('.env')) == false) {
        Debug::createError('Dotenv: Environment file .env not found. Create file with your environment settings at project root files');
      }
      $ENV_CONTENT = File::getContent(root_path('.env'));
      $lines = explode("\n", $ENV_CONTENT);

      foreach ($lines as $line) {
        $arr = explode("=", $line);
        $key = trim($arr[0] ?? '');
        $value = trim($arr[1] ?? '');

        self::$env[$key] = $value;
      }
    }

    if (isset(self::$env[$name])) {
      return self::$env[$name];
    } else {
      return $default;
    }
  }


  public static function setLocale($local)
  {
    self::$local = $local;
  }

  public static function isLocale($local)
  {
    return ($local == self::$local) ? true : false;
  }

  public static function getLocale()
  {
    return self::$local;
  }

  public static function disableCache()
  {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
  }

  private static $lang_store = [];
  public static function lang($name)
  {

    $arr = explode('.', $name);
    $file = $arr[0];
    $variable = $arr[1];
    $locale = App::getLocale();
    $index_name = "$locale.$file";
    if (!isset(self::$lang_store[$index_name])) {
      $path = Directory::path('langs');
      $content = include_once("$path/$locale/$file.php");
      self::$lang_store[$index_name] = $content;
    }

    return self::$lang_store[$index_name][$variable] ?? false;
  }
}
