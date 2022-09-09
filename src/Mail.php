<?php
namespace Webrium;

class Mail
{
  private static $config=[];

  public static function addConfig($name='main',$mail)
  {
    self::$config[$name]=$mail;
  }

  public static function get($name = 'main'){
    return self::$config[$name];
  }


}
