<?php
namespace Webrium;

use Webrium\Session;

class RequestBack
{
  private static $error=null,$old=null;

  public static function getError($name=false){
    if (self::$error==null) {
      self::$error = Session::once('_error',false);
    }

    if ($name) {
      return self::$error[$name]??false;
    }
    else {
      return self::$error;
    }
  }

  public function withError($params)
  {
    Session::set('_error',$params);
    return $this;
  }

  public function withErrors(array $params)
  {
    Session::set('_error',$params);
    return $this;
  }

  public function errorMessage($text)
  {
    Session::set([
      '_message'=>$text,
      '_message_type'=>'error'
    ]);
    return $this;
  }

  public function successMessage($text)
  {
    Session::set([
      '_message'=>$text,
      '_message_type'=>'success'
    ]);
    return $this;
  }

  public function message($text)
  {
    Session::set([
      '_message'=>$text,
      '_message_type'=>'normal'
    ]);
    return $this;
  }

  public static function getMessage($justGetText=false)
  {
    $text = Session::once('_message',false);
    $type = Session::once('_message_type','normal');

    if (!$justGetText && $text) {
      $SCRIPT = self::findMessageByType($type);
      $SCRIPT = \str_replace('@text',(string)$text,$SCRIPT);
      return $SCRIPT;
    }
    else {
      return $text;
    }
  }

  private static function findMessageByType($type){
    if ($type =='normal' && defined('MESSAGE_SCRIPT')) {
      return MESSAGE_SCRIPT;
    }
    elseif ($type =='success' && defined('MESSAGE_SCRIPT_SUCCESS')) {
      return MESSAGE_SCRIPT_SUCCESS;
    }
    elseif ($type =='error' && defined('MESSAGE_SCRIPT_ERROR')) {
      return MESSAGE_SCRIPT_ERROR;
    }
    else {
      return "<script> alert('@text'); </script>";
    }
  }


  public static function getOldParamsValues()
  {
    if (self::$old==null) {
      self::$old = Session::once('_old',false);
    }
    return self::$old;
  }

  public function withInput()
  {
    Session::set('_old',input());
    return $this;
  }

  public function die()
  {
    die;
  }

}
