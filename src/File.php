<?php
namespace webrium\core;

use webrium\core\App;
use webrium\core\Debug;
use webrium\core\Directory;


class File
{
  public static function exists($path)
  {
    return file_exists($path);
  }

  public static function run($path)
  {
    if (self::exists($path)) {
      include $path;
      return true;
    }
    return false;
  }

  public static function runOnce($path)
  {
    if (self::exists($path)) {
      include_once $path;
      return true;
    }

    return false;
  }

  public static function source($path_name,$arr)
  {
    $path = Directory::path($path_name);
    foreach ($arr as $key => $file) {
      File::runOnce("$path/$file");
    }
  }

  public static function runControllerFunction($dir_name,$class,$func)
  {
    $status =['start_exec_time'=>microtime(true)];

    $dir = Directory::get($dir_name);
    $dir=str_replace('/','\\',$dir);

    $n = "$dir\\$class";
    $controller =new $n;

    if (method_exists($controller,'init')) {
      $status['init']=true;
    }
    else {
      $status['init']=false;
    }

    if (method_exists($controller,$func)) {
      $status['func']=true;
    }
    else {
      $status['func']=false;
      $status['error_message']="function $func not found in $n";
      $status['class_path']="$n.php";
    }

    if (method_exists($controller,'end')) {
      $status['end']=true;
    }
    else {
      $status['end']=false;
    }


    if ($status['func']) {

      if ($status['init']) {
        $controller->init();
      }

      if ($status['func']) {
        App::ReturnData($controller->{$func}());
        $status['exec']=true;
      }

      if ($status['end']) {
        $controller->end();
      }

    }

    $status['end_exec_time']=microtime(true);

    return $status;
  }


  public static function download($path,$download_name=null)
  {
    if ($download_name==null) {
      $download_name=basename($path);
    }

    header("Expires: 0");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");  header("Content-type: application/file");
    header('Content-length: '.filesize($path));
    header('Content-disposition: attachment; filename='.$download_name);
    readfile($path);
  }

  public static function showImage($path)
  {
    $name=basename($path);
    $file_ext=(explode('.',strtolower($name)));
    $file_ext=$file_ext[count($file_ext)-1];

    switch( $file_ext ) {

      case "gif": $ctype="image/gif";
      break;

      case "png": $ctype="image/png";
      break;

      case "jpeg":
      case "jpg":
      $ctype="image/jpeg";
      break;

      case 'svg':
      $ctype="image/svg+xml";
      break;
      default:
    }

    header('Content-type: ' . $ctype);

    if (File::exist($path)) {
      return file_get_contents($path);
    }
    else {
      http_response_code(404);
      return '404 file not found';
    }
  }

  public static function delete($path,$name)
  {
    if(self::exists("$path/$name")){

      \unlink("$path/$name");

      return true;
    }

    return false;
  }


  public static function delete_dir($dir) {

    $files =self::getFiles($dir);

    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? self::delete_dir("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
  }

  public static function getFiles($path,$filter=['.','..','.gitignore'])
  {
    $files=[];
    $res= array_diff(scandir($path), $filter);
    foreach ($res as $key => $file) {
      $files[]=$file;
    }
    return $files;
  }

  public static function getContent($name)
  {
    $text='';
    $myfile = fopen($name, "r");;
    $text= fread($myfile,filesize($name));
    fclose($myfile);
    return $text;
  }

  public static function putContent($name,$content)
  {
    file_put_contents($name, $content);
  }

}
