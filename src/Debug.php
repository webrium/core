<?php
namespace webrium\core;

use webrium\core\File;

class Debug
{
  private static
  $showError = true ,
  $writeError = true ,
  $ErrorMessage = false ,
  $errorCallback = false ,
  $writeErrorPath = false;

  public static function displayErrors($status)
  {
    if ($status) {
      ini_set('display_errors', $status);
      ini_set('display_startup_errors', $status);
      error_reporting(E_ALL);
    }

    self::error_handler();
  }

  public static function writeError($status)
  {
    self::$writeError=$status;
  }

  public static function saveErrorPath($path)
  {
    self::$writeErrorPath=$path;
  }

  public static function errorCode($code)
  {
    http_response_code($code);
  }

  public static function error404()
  {
    self::errorCode(404);
    echo "404";
  }

  public static function error_handler()
  {
    set_exception_handler(function ($callback)
    {
      self::createError($callback->getMessage()." ".$callback->getFile(), false, $callback->getLine());
    });

    set_error_handler(function ($errno, $errstr, $errfile, $errline)
    {
     self::createError( "$errstr $errfile", false, $errline);
    },E_ALL);
  }

  public static function createError($str,$file=false,$line=false,$response_code=500)
  {

    $show = $file;

    self::getFileBackTrace($file);
    self::getFileBackTraceForShow($show);

    self::saveError($str,$file,$line,$response_code);
    self::$ErrorMessage = self::showError($str,$show,$line,$response_code);
  }

  public static function saveError($str,$file=false,$line=false,$response_code=500)
  {
    if (! self::$writeError) {
      return;
    }

    $date = date('Y_m_d');
    $time = date('H_i_s');

    $name = "error_$date.txt";


    $msg = "## $date $time Error : $str ";

    if ($file) {
      $msg.="Stack trace : $file ";
    }

    if ($line) {
      $msg.="Line : $line";
    }

    if (! self::$writeErrorPath) {
      self::$writeErrorPath = Directory::path('logs');
    }

    self::wrFile(self::$writeErrorPath."/$name",$msg);
  }

  public static function showError($str,$file=false,$line=false,$response_code=500)
  {
    if (! self::$showError) {
      return false;
    }

    $msg = "Error : $str ";

    if ($line) {
      $msg.=":($line)";
    }

    if ($file) {
      $msg.="<br> Stack trace : <br>$file ";
    }


    if ($response_code!=false) {
      self::errorCode($response_code);
    }

    if (self::$errorCallback) {
      \webrium\core\Event::emit('_error',$msg);
    }

    echo $msg;
    return $msg;
  }

  public static function callback($func)
  {
    self::$errorCallback = true;
    \webrium\core\Event::on('_error',$func);
  }

  private static function getFileBackTraceForShow(&$file){
    $msg='';

    if ($file != false) {
      $msg .="<span> <b> <span style=\"color:red\" >#</span> $file</b> </span> <br>";
    }

    foreach (debug_backtrace() as $key => $value) {

      if (! isset($value['file']) || strpos($value['file'],'webrium/core/src')==true) {
        continue;
      }

      $file = $value['file'];
      $line = $value['line'];

      if ( strpos($value['file'],'vendor')==false) {
        $msg.="<span ><b># $file:( $line ) </b></span>";
      }
      else {
        $msg.="<span style=\"color: #3e3e3e;\" ># $file:( $line )</span>";
      }
      $msg.="<br>";

      $file=$msg;
    }
  }

  private static function getFileBackTrace(&$file){
    $msg='';

    if ($file != false) {
      $msg .=" #$file";
    }

    foreach (debug_backtrace() as $key => $value) {

      if (! isset($value['file']) || strpos($value['file'],'webrium/core/src')==true) {
        continue;
      }

      $file = $value['file'];
      $line = $value['line'];

      $msg.=" #$file:( $line )";

      $file=$msg;
    }
  }


  private static function wrFile($name,$text)
  {
    $mode = (!file_exists($name)) ? 'w':'a';
    $logfile = fopen($name, $mode);
    fwrite($logfile, "\r\n".$text);
    fclose($logfile);
  }

  public static function findError()
  {
    return self::$ErrorMessage;
  }


}
