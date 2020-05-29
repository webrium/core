<?php
namespace webrium\core;

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

      $code = str_replace('@endview','); ?>',$code);
      $code = str_replace('@view','<?= view(',$code);

      $code = str_replace('@echo','<?=',$code);
      $code = str_replace('@end','?>',$code);
      $code = str_replace('@php','<?php',$code);

      File::putContent($render_file_path,$code);
    }

    return self::loadPath($render_file_path);
  }


  /**
   * Get the MD5 hash of the file at the given path.
   *
   * @param  string  $path
   * @return string
   */
  private static function hash($path)
  {
      return md5_file($path);
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
