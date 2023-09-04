<?php
namespace Webrium;

use Webrium\App;
use Webrium\Debug;
use Webrium\Directory;


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

  public static function executeControllerMethod($dir_name,$class_name,$method_name, $params=[])
  {


    $dir = Directory::get($dir_name);

    $class = "$dir\\$class_name";

    $class=str_replace('/','\\',$class);

    $controller =new $class;


    if (method_exists($controller,'__init')) {
      $controller->__init();
    }


    if (method_exists($controller,$method_name)) {
      App::ReturnData($controller->{$method_name}(...$params));
    }
    else {
      Debug::createError("Method $method_name not found in $class","$class.php");
    }


    if (method_exists($controller,'__end')) {
      $controller->__end();
    }
  }


  
  /**
   * Downloads a file from the server to the client with appropriate headers and content type.
   *
   * @param string $file_path The path of the file to download.
   * @param string|null $download_name The filename that will be shown in the download prompt. Defaults to the basename of the file_path if null.
   * @return void
   */
  public static function download($file_path, $download_name = null)
  {
    // Load FileInfo module to determine the MIME type of the file
    $file_info = finfo_open(FILEINFO_MIME_TYPE);

    if (is_file($file_path)) {
      $file_size = filesize($file_path);
      $download_name = $download_name ?? basename($file_path);

      // Determine the content type based on the MIME type of the file
      $content_type = finfo_file($file_info, $file_path);

      // Set headers for streaming
      header('Content-Type: ' . $content_type);
      header('Content-Transfer-Encoding: Binary');
      header('Content-Length: ' . $file_size);
      header('Content-disposition: attachment; filename="' . $download_name . '"');

      // Open the file and stream it to the output buffer in small chunks
      $file = fopen($file_path, 'rb');
      while (!feof($file)) {
        print(fread($file, 1024 * 8));
        ob_flush();
        flush();
      }
      fclose($file);
      exit;
    } else {
      echo "Error: File not found.";
    }

    finfo_close($file_info); // Close the FileInfo module
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


    if (File::exists($path)) {
      header('Content-type: ' . $ctype);
      return file_get_contents($path);
    }
    else {
      http_response_code(404);
      return '404 file not found';
    }
  }

  public static function delete($path)
  {
    if(self::exists($path)){

      return \unlink($path);
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

  /**
 * Get the MD5 hash of the file at the given path.
 *
 * @param  string  $path
 * @return string
 */
 public static function hash($path)
 {
   if (self::exists($path)) {
     return md5_file($path);
   }
   return false;
 }

}
