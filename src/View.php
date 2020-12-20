<?php
namespace webrium\core;

use webrium\core\Debug;
use webrium\core\File;
use webrium\core\Directory;
use webrium\core\Event;

class View
{

  private static $views;

  private static function loadPath($view)
  {
    Debug::$ErrorView=true;

    ob_start();
    File::run($view);
    $view = ob_get_clean();

    if (Debug::status()==false) {
      return $view;
    }
    else {
      Event::emit('error_view',['message'=>Debug::getErrorStr(),'line'=>Debug::getErrorLine(),'file'=>Debug::getErrorFile()]);

      if (Debug::getShowErrorsStatus()) {
        return Debug::getHTML();
      }

    }
  }

  public static function load($view)
  {
    return self::loadPath(Directory::path('views')."/$view.php");
  }

  public static function findOrginalNameByHash($hashName){

    $name = basename($hashName);

    if ( isset(self::$views[$name]) ) {
      return self::$views[$name];
    }
    else {
      return false;
    }
  }

  public static function getOrginalNameByHash($hashName)
  {
    $name = self::findOrginalNameByHash($hashName);
    if ($name) {
      return $name;
    }
    else {
      return $hashName;
    }
  }

  public static function render($view,$params=[])
  {
    $path = Directory::path('views');
    $file_path="$path/$view.php";

    $hash_file = self::hash($file_path);


    $render_path = Directory::path('render_views');
    $render_file_path="$render_path/$hash_file.php";

    self::$views["$hash_file.php"] = "$view.php";

    $GLOBALS = $params;

    if (! File::exists($render_file_path)) {

      self::autoClearCashes();

      $str ='<?php foreach ($GLOBALS as $key => $value) {${$key}=$value;}; $_all = $GLOBALS; ?>';

      $code = $str. File::getContent($file_path);

      self::str_replace_type_value('/\@foreach\((.+?\W+|.+)\)/','<?php foreach',": ?>",$code);
      self::str_replace_type_value('/\@foreach[[:blank:]]\((.+?\W+|.+)\)/','<?php foreach',": ?>",$code);

      self::str_replace_type_value('/\@for\((.+?\W+|.+)\)/','<?php for',": ?>",$code);
      self::str_replace_type_value('/\@for[[:blank:]]\((.+?\W+|.+)\)/','<?php for',": ?>",$code);

      self::str_replace_type_value('/\@if\((.+?\W+|.+)\)/','<?php if',": ?>",$code);
      self::str_replace_type_value('/\@if[[:blank:]]\((.+?\W+|.+)\)/','<?php if',": ?>",$code);

      self::str_replace_type_value('/\@elseif\((.+?\W+|.+)\)/','<?php elseif',": ?>",$code);
      self::str_replace_type_value('/\@else[[:blank:]]if\((.+?\W+|.+)\)/','<?php elseif',": ?>",$code);

      self::str_replace_type_value('/\@echo\((.+?\W+|.+)\)/','<?php echo',"; ?>",$code);
      self::str_replace_type_value('/\@view\((.+?\W+|.+)\)/','<?= view',"; ?>",$code);
      self::str_replace_type_value('/\@load\((.+?\W+|.+)\)/','<?= load',"; ?>",$code);
      self::str_replace_type_value('/\@url\((.+?\W+|.+)\)/','<?= url',"; ?>",$code);

      self::str_replace_type_value('/\{{(.+?)\}}/','<?php echo htmlspecialchars',"; ?>",$code);
      self::str_replace_type_value('/\{!!(.+?)\!!}/','<?php echo ',"; ?>",$code,false);

      $code = str_replace('@endforeach','<?php endforeach; ?>',$code);
      $code = str_replace('@endfor','<?php endfor; ?>',$code);
      $code = str_replace('@else','<?php else: ?>',$code);
      $code = str_replace('@endif','<?php endif; ?>',$code);

      $code = str_replace('@end','?>',$code);
      $code = str_replace('@php','<?php',$code);

      File::putContent($render_file_path,$code);
    }

    return self::loadPath($render_file_path);
  }


  public static function str_replace_type_value($preg,$to,$end,&$code,$parentheses=true)
  {
    preg_match_all($preg, $code, $output_array);

    $_str1=$output_array[0];
    $_str2=$output_array[1];

    foreach ($_str1 as $key => $value) {

      $value = self::checkSyntax($value);

      $_r = $_str2[$key];
      $_r = self::checkSyntax($_r);

      if ($parentheses) {
        $code = str_replace($value,"$to($_r)$end",$code);
      }
      else {
        $code = str_replace($value,"$to$_r$end",$code);
      }

    }
  }

  private static function checkSyntax($value)
  {
    $start = explode('(',$value);
    $end = explode(')',$value);

    $count_start = count($start);
    $count_end = count($end);

    if ($count_start != $count_end) {

      $int = $count_end - $count_start;

      for($i=0;$i<$int;$i++){
        unset($end[$count_end-1]);
      }

      $new = implode(')',$end);

      $count_start = count($start);
      $count_end = count($end);

      if (strrpos($new,')')>$count_end) {
        $new = substr($new,0,strrpos($new,')')+1);
      }

      $value = $new;

    }

    return $value;
  }


  /**
  * Get the MD5 hash of the file at the given path.
  *
  * @param  string  $path
  * @return string
  */
  private static function hash($path)
  {
    $hash = File::hash($path);

    if ($hash) {
      return $hash;
    }
    else {
      Debug::createError("View file '".basename($path)."' not found",false,false,500);
    }
  }

  public static function clearCaches()
  {
    $render_path = Directory::path('render_views');

    $list = File::getFiles($render_path);

    foreach ($list as $key => $file) {
      File::delete("$render_path/$file");
    }

    return count($list);
  }

  public static function autoClearCashes()
  {
    $render_path = Directory::path('render_views');

    $list = File::getFiles($render_path);

    $now = date("Y-m-d H:i");

    foreach ($list as $key => $file) {
      $created_at = date("Y-m-d H:i", filemtime("$render_path/$file"));
      if ($created_at!=$now) {
        File::delete("$render_path/$file");
      }
    }
  }

}
