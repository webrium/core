<?php
namespace webrium\core;

use webrium\core\Directory;

class Upload
{

    private $param_name='file';
    private $info=null;
    private $path='/';
    private $extensions=false;
    private $types=false;
    private $max_size=false;
    private $full_path;
    private $name;

    private $errors,$error=false;

    function __construct($name)
    {
      $this->getInfo();
      $this->param_name($name);
    }

    public function save()
    {
      $info=$this->getInfo();

      if (! $this->exsist()) {
        $this->addError('file','No file found');
      }

      //set default name
      if ($this->name==null) {
        $this->name=$info['file_name'];
      }

      // check max file size
      if ($this->checkMaxSize()==false) {
        $this->addError('size','File size');
      }

      if ($this->checkExt()==false) {
        $this->addError('extension','File extension is not valid');
      }

      if ($this->checkType()==false) {
        $this->addError('type','File type is not valid');
      }

      if ($this->error) {
        return $this;
      }

      // make dir if not exsits
      if(! \is_dir($this->path)){
        mkdir($this->path,0777, true);
      }

      $this->full_path=$this->path.'/'.$this->name;

      \move_uploaded_file(
        $info['tmp_name'],
        $this->full_path
      );

      return $this;
    }

    public function addError($type,$msg='')
    {
      $this->errors[]=[
        'type'=>$type,
        'msg'=>$msg
      ];
      $this->error=true;
    }

    public function getErrors()
    {
      return $this->errors;
    }
    public function error()
    {
      return $this->error;
    }

    public function status()
    {
      if ($this->error || File::exist($this->full_path)==false) {
        return false;
      }
      return true;
    }

    public function getFileName()
    {
      return $this->name;
    }

    public function rename($rename,$custom_ext=false)
    {
      if ($custom_ext==false) {
        $this->name=$this->set_ext_to_name($rename);
      }
      else {
        $this->name="$rename.$custom_ext";
      }
      return $this;
    }

    public function randomName()
    {
      $rand='f_'.rand(10000,99909).'_'.rand(10000,99999).'_'.time();
      $this->name=$this->set_ext_to_name($rand);
      return $this;
    }

    private function set_ext_to_name($name)
    {
      return "$name.".$this->getExt();
    }

    public function checkMaxSize(){
      if($this->max_size != false  && $this->max_size < ( $this->getSize() / 1024 )){
        return false;
      }
      else {
        return true;
      }
    }

    public function checkExt(){
      if($this->extensions != false && in_array($this->getExt(),$this->extensions)=== false){
        return false;
      }
      else {
        return true;
      }
    }

    public function checkType(){
      if($this->types != false && in_array($this->getType(),$this->types)=== false){
        return false;
      }
      else {
        return true;
      }
    }

    /**
     * get file size
     */
    public function getSize()
    {
      return $this->getProp('size');
    }

    /**
     * get extension name
     * @return [string]
     */
    public function getExt()
    {
      return $this->getProp('ext');
    }

    /**
     * get type name
     * @return [string]
     */
    public function getType()
    {
      return $this->getProp('type');
    }

    /**
     * get file property
     * @param  [string] $name
     * @return [array or false]
     */
    public function getProp($name)
    {
      if (isset($this->getInfo()[$name])) {
        return $this->getInfo()[$name];
      }
      else {
        return false;
      }
    }


    public function toStorage($path='')
    {
      return $this->path(Directory::path('storage')."/app/$path");
    }

    public function path($path='/')
    {
      $this->path=$path;
      return $this;
    }

    public function getPath()
    {
      return $this->path;
    }

    public function getFullPath()
    {
      return $this->full_path;
    }


    public function maxSize($size)
    {
      $this->max_size=$size;
      return $this;
    }

    public function limit_ext($arr)
    {
      $this->extensions=$arr;
      return $this;
    }

    public function limit_type($arr)
    {
      $this->types=$arr;
      return $this;
    }

    public function param_name($name)
    {
      $this->param_name=$name;
      return $this;
    }

    /**
     * check is exsist file for upload
     * @return [boolean]
     */
    public function exsist()
    {
      if(isset($_FILES[$this->param_name])){
        return true;
      }
      else{
         return false;
       }
    }
    /**
     * get file information
     * @param  boolean $force [description]
     * @return [array or false]
     */
    public function getInfo($force=false)
    {
      if ($this->info==null || $force==true) {
        $fileName=$this->param_name;
        $file=[];
        if ($this->exsist()) {
          $file['file_name']= $_FILES[$fileName]['name'];
          $file['size'] =$_FILES[$fileName]['size'];
          $file['tmp_name'] =$_FILES[$fileName]['tmp_name'];
          $file['type']=$_FILES[$fileName]['type'];
          $ext=(explode('.',strtolower($file['file_name'])));
          $file['ext']=$ext[count($ext)-1];
          $this->info=$file;
        }
        else {
          return false;
        }

      }
      return $this->info;
    }
}
