<?php
namespace Webrium;

use Webrium\File;
use Webrium\Directory;
use Webrium\App;

class FormValidation
{

  private array $form_data_params;
  private array $validation_messages;

  private array $validation_data;

  private string $current_field;

  private array $error_list = [];


  function __construct($form_data_params = null)
  {
    if ($form_data_params === null) {
      $this->form_data_params = input();
    } else {
      $this->form_data_params = $form_data_params;

    }

    $this->loadValidationMessages();
  }




  private function loadValidationMessages()
  {

    $validation_message_path = Directory::path('langs') . '/' . App::getLocale() . '/validation.php';

    if (File::exists($validation_message_path)) {
      $this->validation_messages = include_once $validation_message_path;
    } else {
      throw new \Exception("File not found in path '$validation_message_path'", 1);
    }
  }

  public function field($name, $translation = null)
  {

    $this->current_field = $name;

    $this->validation_data[$name] = [
      'name' => $name,
      't_name' => $translation,
      'rules' => [],
    ];

    return $this;
  }

  private function addNewRule($type, $message, $value1 = null, $value2 = null)
  {
    $this->validation_data[$this->current_field]['rules'][] = ['type' => $type, 'custom_message' => $message, 'value1' => $value1, 'value2' => $value2];
    return $this;
  }

  public function numeric($custom_message = null)
  {
    return $this->addNewRule('numeric', $custom_message);
  }

  public function integer($custom_message = null)
  {
    return $this->addNewRule('integer', $custom_message);
  }

  public function string($custom_message = null)
  {
    return $this->addNewRule('string', $custom_message);
  }

  public function min($min_value, $custom_message = null)
  {
    return $this->addNewRule('min', $custom_message, $min_value);
  }

  public function max($max_value, $custom_message = null)
  {
    return $this->addNewRule('max', $custom_message, $max_value);
  }

  public function required($custom_message)
  {
    return $this->addNewRule('required', $custom_message);
  }


  public function email($custom_message = false)
  {
    return $this->addNewRule('max', $custom_message);

  }

  public function url($custom_message = false)
  {
    return $this->addNewRule('max', $custom_message);

  }

  public function mac($custom_message = false)
  {
    return $this->addNewRule('max', $custom_message);
  }

  public function boolean($custom_message = false)
  {
    return $this->addNewRule('boolean', $custom_message);
  }

  public function array($custom_message = false)
  {
    return $this->addNewRule('array', $custom_message);
  }

  public function between($min, $max, $custom_message = null)
  {
    return $this->addNewRule('between', $custom_message, $min, $max);
  }

  public function file($custom_message = null)
  {
    return $this->addNewRule('file', $custom_message);
  }

  public function size(int $size_number, $custom_message)
  {
    return $this->addNewRule('size', $custom_message, $size_number);
  }

  private function process()
  {

    foreach ($this->validation_data as $data) {

      $name = $data['name'];
      $t_name = $data['t_name'];
      $rules = $data['rules'];

      // var_dump($data);
      echo "Run on : $name roules count:" . count($rules) . "\n";


      foreach ($rules as $rule) {
        $method_exec_name = '_check_' . $rule['type'];
        $result = $this->$method_exec_name($rule, $name);

        echo "name:$name type:" . $rule['type'] . " value1:".$rule['value1']." result:".json_encode($result)." \n---------\n";
      }

    }

  }


  private function getParam($name)
  {
    return $this->form_data_params[$name] ?? null;
  }

  private function _check_string($rule, $name)
  {
    $value = $this->getParam($name);
    return (is_string($value) && (gettype($value) == 'string'));
  }

  private function _check_numeric($rule, $name)
  {
    return is_numeric($this->getParam($name));
  }

  private function _check_integer($rule, $name)
  {
    return gettype($this->getParam($name))=='integer';
  }

  private function _check_min($rule, $name)
  {
    $value = $this->getParam($name);
    $min = $rule['value1'];
    $status = true;
    $type  = gettype($value);
    echo "\ntype value:" .gettype($value)." min: $min  value: $value\n";

    if($type  == 'string' && \mb_strlen($value) < $min){
      $status = false;
    }
    else if($type  == 'integer' && $value < $min){
      $status = false;
    }
    else if(is_array($value) && count($value) < $min){
      $status = false;
    }

    return $status;
  }


  private function _check_max($rule, $name)
  {
    $value = $this->getParam($name);
    $max = $rule['value1'];
    $status = true;
    $type  = gettype($value);

    echo "\n type value:" .gettype($value)." min: $max  value: $value\n";

    if($type == 'string' && \mb_strlen($value) > $max){
      $status = false;
    }
    else if($type == 'integer' && $value > $max){
      $status = false;
    }
    else if(is_array($value) && count($value) > $max){
      $status = false;
    }

    return $status;
  }

  private function filter($value, $FILTER)
  {
    if (!filter_var($value, $FILTER)) {

    }

    return $this;
  }


  public function isValid()
  {
    return $this->process();
  }

  public function test()
  {
    return $this->validation_data;
  }

}