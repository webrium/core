<?php
namespace webrium\core;

use webrium\core\File;
use webrium\core\Directory;
use webrium\core\App;

class FormValidation
{
  private $currentName;
  private $fakeName=false;
  private $currentValue;
  private $errors=[];
  private $list=[];
  private $type;
  private $messages;

  public function field($name,$translation=false)
  {
    $this->currentName  = $name;
    $this->fakeName = $translation;

    $this->currentValue = input($name,null);

    $message_path = Directory::path('langs').'/'.App::getLocale().'/validation.php';
    if (File::exists($message_path)) {
      $this->messages = include $message_path;
    }
    else {
      $this->messages = false;
    }

    if ($this->fakeName==false) {
      $this->fakeName = $this->messages['attributes'][$name]??false;
    }

    $this->type = false;

    return $this;
  }

  public function numeric($message=false)
  {
    $this->type = 'numeric';

    if (! \is_numeric($this->currentValue)) {
      $message = $this->initMessage($this->messages['numeric'],$message);
      $this->addError($message);
    }
    return $this;
  }

  public function string($message=false)
  {
    $this->type = 'string';

    if (! \is_string($this->currentValue) || \is_numeric($this->currentValue)) {
      $message = $this->initMessage($this->messages['string'],$message);
      $this->addError($message);
    }
    return $this;
  }

  public function min($min,$message=false)
  {
    if (is_string($this->currentValue) && (! $this->type || $this->type =='string' )) {
      $message = $this->initMessage($this->messages['min']['string'],$message);
      $message = \str_replace(':min',$min,$message);

      if (\mb_strlen($this->currentValue) < $min ) {
        $this->addError($message);
      }
    }
    elseif (\is_numeric($this->currentValue) && (! $this->type || $this->type =='numeric' ) ) {
      $message = $this->initMessage($this->messages['min']['numeric'],$message);
      $message = \str_replace(':min',$min,$message);

      if ( $this->currentValue < $min ) {
        $this->addError($message);
      }
    }
    elseif (is_array($this->currentValue) && (! $this->type || $this->type =='array' )) {
      $message = $this->initMessage($this->messages['min']['array'],$message);
      $message = \str_replace(':min',$min,$message);

      if ( count($this->currentValue) < $min ) {
        $this->addError($message);
      }
    }

    return $this;
  }

  public function max($max,$message=false)
  {

    if (is_string($this->currentValue) && (! $this->type || $this->type =='string' ) ) {
      $message = $this->initMessage($this->messages['max']['string'],$message);
      $message = \str_replace(':max',$max,$message);

      if (\mb_strlen($this->currentValue) > $max ) {
        $this->addError($message);
      }
    }
    elseif (\is_numeric($this->currentValue) && (! $this->type || $this->type =='numeric' )) {
      $message = $this->initMessage($this->messages['max']['numeric'],$message);
      $message = \str_replace(':max',$max,$message);

      if ( $this->currentValue > $max ) {
        $this->addError($message);
      }
    }

    elseif (is_array($this->currentValue) && (! $this->type || $this->type =='array' )) {
      $message = $this->initMessage($this->messages['max']['array'],$message);
      $message = \str_replace(':max',$max,$message);

      if ( count($this->currentValue) > $max ) {
        $this->addError($message);
      }
    }

    return $this;
  }

  public function required($message=false)
  {
    $message = $this->initMessage($this->messages['required'],$message);

    if (empty($this->currentValue)) {
      $this->addError($message);
    }
    return $this;
  }

  public function accepted($message=false)
  {
    $message = $this->initMessage($this->messages['accepted'],$message);

    if (input($this->currentName,false)==false) {
      $this->addError($message);
    }
    return $this;
  }

  public function confirmed($name_2,$message=false)
  {
    $message = $this->initMessage($this->messages['confirmed'],$message);

    if ($this->currentValue != input($name_2)) {
      $this->addError($message);
    }
    return $this;
  }

  public function email($message=false)
  {
    $this->usePHPFilter($this->messages['email'],$message,FILTER_VALIDATE_EMAIL);
    return $this;
  }

  public function url($message=false)
  {
    $this->usePHPFilter($this->messages['url'],$message,FILTER_VALIDATE_URL);
    return $this;
  }

  public function mac($message=false)
  {
    $this->usePHPFilter($this->messages['mac'],$message,FILTER_VALIDATE_MAC);
    return $this;
  }


  public function usePHPFilter($cmessage,$message,$FILTER)
  {
    if(! filter_var($this->currentValue, $FILTER)) {
      $message = $this->initMessage($cmessage,$message);
      $this->addError($message);
    }

    return $this;
  }

  public function addError($error){
    $error  = \str_replace('@field',$this->currentName,$error);
    $this->errors[$this->currentName][] = $error;
    $this->list[] = $error;
    return $this;
  }

  public function getErrors(){
    return $this->errors;
  }

  public function error()
  {
    $error = new GetError;
    $error->set($this->errors,$this->list);
    return $error;
  }

  public function autoCheck()
  {

    if ($this->isValid()==false) {
      back()->withError($this->error()->all())->withInput()->die();
    }
  }

  public function isValid()
  {
    if (count($this->getErrors())==0) {
      return true;
    }
    return false;
  }

  private function initMessage($text,$customMessage=false)
  {
    if ($customMessage) {
      $text = $customMessage;
    }

    $text = \str_replace(':attribute',($this->fakeName?$this->fakeName:$this->currentName),$text);

    if ($this->fakeName) {
      $text = \str_replace($this->currentName,$this->fakeName,$text);
    }

    return $text;
  }

}

class GetError{

  private $array;
  private $list;

  public function set($array,$list)
  {
    $this->array = $array;
    $this->list  = $list;

    return $this;
  }

  public function all()
  {
    return $this->list;
  }

  public function withFields()
  {
    return $this->array;
  }

  public function first()
  {
    if (isset($this->list[0])) {
      return $this->list[0];
    }
    else {
      return false;
    }
  }

  public function asString($separator="\n")
  {
    return \implode($separator,$this->all());
  }
}
