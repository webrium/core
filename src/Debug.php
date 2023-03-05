<?php
namespace Webrium;

use Webrium\View;
use Webrium\Event;

class Debug
{


  public static $ErrorView   = false;

  private static
  $WritErrorsStatus = true ,
  $ShowErrorStatus  = true ,
  $LogPath          = false,
  $Error            = false,
  $HTML             = '',
  $ErrorLine        = 0,
  $ErrorFile        = '',
  $ErrorStr         = '';

  public static function status(){
    return self::$Error;
  }

  public static function getHTML(){
    return self::$HTML;
  }

  public static function displayErrors($status)
  {
    
    ini_set('display_errors', $status);
    ini_set('display_startup_errors', $status);
    
    if ($status) {
      error_reporting(E_ALL);
    }

    self::error_handler();
  }

  public static function error_handler()
  {

    set_error_handler(function ($errno, $errstr, $errfile, $errline)
    {
     self::$ErrorFile = $errfile= View::getOrginalNameByHash($errfile);
     self::createError( "$errstr", false, $errline);
    },E_ALL);
  }

  public static function createError($str,$file=false,$line=false,$response_code=500)
  {
    $show = $file;

    self::getFileBackTrace($file);
    self::getFileBackTraceForShow($show);

    if (! self::$Error) {

      self::$Error     = true;
      self::$ErrorStr  = $str;
      self::$ErrorLine = $line;

      Event::emit('error',['message'=>self::$ErrorStr,'line'=>self::$ErrorLine,'file'=>self::getErrorFile()]);

      if (self::$WritErrorsStatus) {
        self::saveError($str,$file,$line,$response_code);
      }

      self::showError($str,$show,$line,$response_code);

    }

  }

  public static function getErrorStr(){
    return self::$ErrorStr;
  }

  public static function getErrorLine(){
    return self::$ErrorLine;
  }

  public static function getErrorFile(){
    return self::$ErrorFile;
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


  public static function saveError($str,$file=false,$line=false,$response_code=500)
  {

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

    if (! self::$LogPath) {
      self::$LogPath = Directory::path('logs');
    }

    self::wrFile(self::$LogPath."/$name",$msg);
  }

  public static function showError($str,$file=false,$line=false,$response_code=500)
  {
    $msg = "Error : $str ";

    if ($line) {
      $msg.=" (file: <b>".self::getErrorFile()."</b> line:$line)";
    }

    if ($file) {
      $msg.="<br> Stack trace : <br>$file ";
    }

    if ($response_code!=false) {
      self::errorCode($response_code);
    }

    self::$HTML = $msg;

    if (! Debug::$ErrorView) {
      die(self::$HTML);
    }
  }

  public static function error404()
  {
    self::errorCode(404);
    echo "404";
  }

  public static function writErrorsStatus($status)
  {
    self::$WritErrorsStatus = $status;
  }

  public static function showErrorsStatus($status)
  {
    self::$ShowErrorStatus = $status;
  }

  public static function getShowErrorsStatus()
  {
    return self::$ShowErrorStatus;
  }



  public static function setLogPath($path)
  {
    self::$LogPath=$path;
  }

  public static function errorCode($code)
  {
    http_response_code($code);
  }

  private static function wrFile($name,$text)
  {
    $mode = (!file_exists($name)) ? 'w':'a';
    $logfile = fopen($name, $mode);
    fwrite($logfile, "\r\n".$text);
    fclose($logfile);
  }


}
