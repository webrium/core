<?php
namespace webrium\core;

use webrium\core\Directory;

class Upload
{

  protected $multipleFiles = false,$inputName,$files,$savePath,$saveName=false,$status;

  protected $errors=[] , $maxSize=false,$limitExtention=false,$limitType=false;

  function __construct($name=false)
  {
    if ($name) {
      $this->inputName = $name;

      if ($this->exists() && is_array($this->input()['name'])) {
        $this->multipleFiles = true;
        $this->generateFilesArray();
      }
      else if($this->exists() && is_string($this->input()['name'])){
        $this->files = [$this->input()];
      }
    }
  }

  public function initSingleFile($inputName,$files,$_class)
  {
    $this->inputName = $inputName;
    $this->files = $files;

    if ($_class->maxSize) {
      $this->maxSize($_class->maxSize['number'],$_class->maxSize['errorText']);
    }

    if ($_class->limitExtention) {
      $this->allowExtensions($_class->limitExtention['array'],$_class->limitExtention['errorText']);
    }

    if ($_class->limitType) {
      $this->allowTypes($_class->limitType['array'],$_class->limitType['errorText']);
    }
  }

  public function input()
  {
    return $_FILES[$this->inputName]??false;
  }

  public function first()
  {
    return $this->files[0]??false;
  }

  public function getArray()
  {
    return $this->files;
  }


  public function has($name)
  {
    return isset($_FILES[$name]);
  }

  public function exists()
  {
    return $this->has($this->inputName);
  }

  public function count()
  {
    if ($this->multipleFiles) {
      return count($this->input()['name']);
    }
    elseif ($this->exists() && is_string($this->input()['name'])) {
      return 1;
    }
    else {
      return 0;
    }
  }

  public function generateFilesArray()
  {
    $files = [];

    foreach ($this->input() as $key => $array) {

      foreach ($array as $index=> $string) {
        $files[$index][$key] = $string;
      }

    }
    return $this->files = $files;
  }

  public function each($func=false)
  {
    $array = [];


    foreach ($this->getArray() as $key => $file) {

      $one = new Upload;


      $one->initSingleFile($this->inputName,[$file],$this);

      if ($func) {
        $func($one);
      }
      else {
        $array[] = $one;
      }

    }

    return $array;
  }

  public function get()
  {
    return $this->each();
  }

  public function getClientOriginalName()
  {
    return $this->first()['name'];
  }

  public function extension()
  {
    $name = $this->getClientOriginalName();
    $arr = explode('.',$name);
    return end($arr);
  }

  public function name($name=false,$setCustomExtention=false)
  {
    if ($name) {
      $this->saveName = $name;

      if (!$setCustomExtention) {
        $this->saveName .='.'.$this->extension();
      }
      else {
        $this->saveName .= $setCustomExtention;
      }

      return $this;
    }
    else {
      return $this->saveName;
    }
  }

  public function tmpName()
  {
    return $this->first()['tmp_name'];
  }

  public function size()
  {
    return $this->first()['size'];
  }

  public function error()
  {
    return $this->first()['error'];
  }

  public function getErrors()
  {
    return $this->errors;
  }

  public function getFirstError()
  {
    if (isset($this->getErrors()[0])) {
      return $this->getErrors()[0];
    }
    else {
      return '';
    }
  }


  public function type()
  {
    return $this->first()['type'];
  }

  public function path($savePath=false)
  {
    if ($savePath) {
      $this->savePath = $savePath;
      return $this;
    }
    else {
      return $this->savePath;
    }
  }

  public function hashName()
  {
    $this->name( \md5_file($this->tmpName()) );
    return $this;
  }

  public function save()
  {
    if (! $this->validate()) {
      $this->status = false;
      return;
    }

    // make dir if not exsits
    if(! \is_dir($this->savePath)){
      mkdir($this->savePath,0777, true);
    }

    if (! $this->name()) {
      $this->name($this->getClientOriginalName());
    }

    $full_path = "$this->savePath/".$this->name();

    $this->status = \move_uploaded_file(
      $this->tmpName(),
      $full_path
    );

    return $this->status();
  }

  public function status()
  {
    return $this->status;
  }

  /**
   * Limits file size
   * @param  int    $number    Amount in kilobytes
   * @param  string $errorText
   */
  public function maxSize(int $number,$errorText='File size is more than allowed')
  {
    $this->maxSize = ['number'=>$number,'errorText'=>$errorText];

    if (($this->size()/1000) > $number) {
      $this->replaceStr($errorText);
      $this->errors[] = $errorText;
    }
    return $this;
  }

  /**
   * Limits file extension
   * @param  array $array  example ['png','jpg']
   * @param  string $errorText
   */
  public function allowExtensions($array,$errorText='File type is not allowed')
  {
    $this->limitExtention = ['array'=>$array,'errorText'=>$errorText];

    if (! in_array($this->extension(),$array)) {
      $this->replaceStr($errorText);
      $this->errors[] = $errorText;
    }

    return $this;
  }

  /**
   * Limits file type
   * @param  array $array  example ['image/jpeg']
   * @param  string $errorText
   */
  public function allowTypes($array,$errorText='File type is not allowed')
  {
    $this->limitType = ['array'=>$array,'errorText'=>$errorText];

    if (! in_array($this->type(),$array)) {
      $this->replaceStr($errorText);
      $this->errors[] = $errorText;
    }
    return $this;
  }

  public function checkWritable()
  {
    return \is_writable($this->path());
  }

  public function validate()
  {

    if (! $this->checkWritable()) {
      $this->errors[] = 'There is no permission to write the file';
    }

    if ($this->errors && count($this->errors)>0 ) {
      return false;
    }
    else {
      return true;
    }
  }

  public function replaceStr(&$text)
  {
    $text = str_replace('@name',$this->getClientOriginalName(),$text);
    $text = str_replace('@size',$this->size(),$text);
    $text = str_replace('@maxSize',$this->maxSize['number'],$text);
  }
}
