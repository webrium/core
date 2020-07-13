<?php
namespace webrium\core;

class Url
{

  public static function  doc_root()
  {
    return self::checkSlash(self::server('DOCUMENT_ROOT'));
  }

  public static function scheme($full=false)
  {

    if (isset($_SERVER['REQUEST_SCHEME'])) {
      return $_SERVER['REQUEST_SCHEME'].($full?'://':'');
    }
    elseif (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on') {
      return "https".($full?'://':'');
    }
    else {
      return "http".($full?'://':'');
    }

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
    $index = App::rootPath();

    $pos = \strpos($index,$doc);

    if ( $pos > 0 ) {
      $index = \substr($index,$pos);
    }

    $len=strlen($doc);

    return self::home().'/'.substr($index,$len);
  }

  public static function get($path='')
  {
    return self::main().$path;
  }

  public static function uri($resParams=false)
  {
    $uri= self::server('REQUEST_URI');

    if (! $resParams && strpos($uri,'?')>-1) {
      $uri=substr($uri,0,strpos($uri,'?'));
    }

    $uri = self::checkSlash($uri);

    return $uri;
  }

  public static function server_addr()
  {
    return self::server('SERVER_ADDR');
  }

  public static function remote_addr()
  {
    return self::server('REMOTE_ADDR');
  }

  public static function query()
  {
    return self::server('QUERY_STRING');
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

  public static function server($key=false)
  {
    if($key){
      return $_SERVER[$key] ?? false;
    }
    else if ($key==false) {
      return $_SERVER;
    }
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

    $url = self::checkSlash($url);

    if ($url == $current || $star) {
      return true;
    }
    else {
      return false;
    }
  }

  public static function checkSlash($value)
  {
    if (substr($value,-1)!='/') {
      $value.='/';
    }
    return $value;
  }

}
