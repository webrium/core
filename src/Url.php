<?php
namespace webrium\core;

class Url
{

  public static function  doc_root()
  {
    return $_SERVER['DOCUMENT_ROOT'].'/';
  }

  public static function scheme($full=false)
  {
    return $_SERVER['REQUEST_SCHEME'].($full?'://':'');
  }

  public static function domain()
  {
    return $_SERVER['HTTP_HOST'];
  }

  public static function method()
  {
    return $_SERVER['REQUEST_METHOD'];
  }

  public static function home()
  {
    return self::scheme(true).self::domain();
  }

  public static function main()
  {
    $doc = self::doc_root();
    $index = App::index_path();

    $len=strlen($doc);

    return self::home().'/'.substr($index,$len);
  }

  public static function get($path='')
  {
    return self::main().$path;
  }

  public static function uri($resParams=false)
  {
    $uri= $_SERVER['REQUEST_URI'];

    if (substr($uri, -1)!='/') {
      $uri.="/";
    }

    if (! $resParams && strpos($uri,'?')>-1) {
      $uri=substr($uri,0,strpos($uri,'?'));
    }

    return $uri;
  }

  public static function server_addr()
  {
    return $_SERVER["SERVER_ADDR"];
  }

  public static function remote_addr()
  {
    return $_SERVER["REMOTE_ADDR"];
  }

  public static function query()
  {
    return $_SERVER["QUERY_STRING"];
  }

  public static function current($resParams=false)
  {
    return self::home().self::uri($resParams);
  }

  public static function full()
  {
    return self::current(true);
  }

  public static function current_array()
  {
    return explode('/',self::current());
  }

  public static function server()
  {
    return $_SERVER;
  }


  public static function is($text)
  {
    $current = self::current();
    $url = self::get($text);

    $star = false;
    if (strpos($url,'/*')>-1) {
      $url = str_replace('*','',$url);

      if (strpos($current,$url)>-1 && (strlen($current) > strlen($url))) {
        $star = true;
      }
      else {
        return false;
      }
    }

    if (substr($url,-1)!='/') {
      $url.='/';
    }

    if ($url == $current || $star) {
      return true;
    }
    else {
      return false;
    }
  }



}
