<?php
namespace webrium\core;

use webrium\core\Debug;
use webrium\core\File;
use webrium\core\Directory;

class View
{

  private static function loadPath($view)
  {
    ob_start();
    File::run($view);
    return ob_get_clean();
  }

  public static function load($view)
  {
    return self::loadPath(Directory::path('views')."/$view.php");
  }

  public static function render($view,$params=[])
  {
    $path = Directory::path('views');
    $file_path="$path/$view.php";

    $hash_file = self::hash($file_path);

    $render_path = Directory::path('render_views');
    $render_file_path="$render_path/$hash_file.php";

    $GLOBALS = $params;

    if (! File::exists($render_file_path)) {

      $str ='<?php foreach ($GLOBALS as $key => $value) {${$key}=$value;};?>';

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
    if (File::exists($path)) {
      return md5_file($path);
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
      File::delete($render_path,$file);
    }

    return count($list);
  }

}
