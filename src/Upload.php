<?php
namespace webrium\core;

use webrium\core\Directory;

class Upload
{

  public $multipleFiles = false,$inputName,$files;

  function __construct($name=false)
  {
    if ($name) {
      $this->inputName = $name;

      if ($this->exists() && is_array($this->input()['name'])) {
        $this->multipleFiles = true;
        $this->generateFilesArray();
      }
    }
  }

  public function initSingleFile($inputName,$files)
  {
    $this->inputName = $inputName;
    $this->files = $files;
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

  public function each($func=false)
  {
    $array = [];

    foreach ($this->getArray() as $key => $file) {

      $one = new Upload;
      $one->initSingleFile($this->inputName,[$file]);

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

  public function has($name)
  {
    return isset($_FILES[$name]);
  }

  public function exists()
  {
    return self::has($this->inputName);
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
}
