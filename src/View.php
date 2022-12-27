<?php
namespace Webrium;

use Webrium\Debug;
use Webrium\File;
use Webrium\Directory;
use Webrium\Event;

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

    foreach ($params as $key => $value) {
      $GLOBALS[$key] = $value;
    }

    if (! File::exists($render_file_path)) {

      self::autoClearCashes();

      $str ='<?php foreach ($GLOBALS as $key => $value) {${$key}=$value;}; $_all = $GLOBALS; ?>';

      $code = $str. File::getContent($file_path);

      self::CreateBaseCode('@foreach',$code,'<?php foreach',': ?>');
      self::CreateBaseCode('@for',$code,'<?php for',': ?>');
      self::CreateBaseCode('@if',$code,'<?php if',': ?>');
      self::CreateBaseCode('@elseif',$code,'<?php elseif',': ?>');
      self::CreateBaseCode('@while',$code,'<?php while',': ?>');

      self::CreateBaseCode('@view',$code,'<?php echo view','; ?>');
      self::CreateBaseCode('@lang',$code,'<?php echo lang','; ?>');
      self::CreateBaseCode('@load',$code,'<?php echo load','; ?>');
      self::CreateBaseCode('@url',$code,'<?php echo url','; ?>');
      self::CreateBaseCode('@old',$code,'<?php echo old',';?>');
      self::CreateBaseCode('@message',$code,'<?php echo message','; ?>');


      $code = str_replace('@endforeach','<?php endforeach; ?>',$code);
      $code = str_replace('@endfor','<?php endfor; ?>',$code);
      $code = str_replace('@else','<?php else: ?>',$code);
      $code = str_replace('@endif','<?php endif; ?>',$code);
      $code = str_replace('@endwhile','<?php endwhile; ?>',$code);

      $code = str_replace('@end','?>',$code);
      $code = str_replace('@php','<?php',$code);

      self::ReplaceSpecialSymbol('{{','}}',$code,'<?php echo htmlspecialchars(','); ?>');
      self::ReplaceSpecialSymbol('{!!','!!}',$code,'<?php echo ','; ?>');
      File::putContent($render_file_path,$code);
    }

    return self::loadPath($render_file_path);
  }


  public static function CreateBaseCode($find,&$code,$prefix,$suffix)
  {

    $error = false;

    $arr = \substr($code,\strpos($code,$find));
    $explode = \explode($find,$arr);

    foreach($explode  as $line){
      if ($line) {

        $s = 0;
        $e = 0;

        $finish = false;

        foreach (str_split($line) as $key => $str) {
          if ($str=='(') {
            $s++;
          }
          elseif ($str==')') {
            $e++;
          }

          if ($s>0 && $e==$s) {
            $finish = $key;
            break;
          }
        }

        if ($finish) {
          $block = \substr($line,0,$finish+1 );
          $code = \str_replace("$find$block","$prefix$block$suffix",$code);
        }
        else {
          $error = $find ;
        }
      }
    }

    if ($error) {
      throw new \Exception("Syntax error in '$error' in ".end(self::$views));
    }
  }

  public static function ReplaceSpecialSymbol($start,$end,&$code,$prefix,$suffix)
  {
    $code = \str_replace($start,$prefix,$code);
    $code = \str_replace($end  ,$suffix,$code);
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
