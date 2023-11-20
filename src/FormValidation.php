<?php
namespace Webrium;

use Webrium\File;
use Webrium\Directory;
use Webrium\App;

class FormValidation
{

  private array $form_data_params;
  private static array $validation_messages;

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




  private function loadValidationMessages():void
  {

    $validation_message_path = Directory::path('langs') . '/' . App::getLocale() . '/validation.php';

    // die(json_encode([isset(FormValidation::$validation_messages),File::exists($validation_message_path)]));
    if (isset(FormValidation::$validation_messages) == false) {
      if (File::exists($validation_message_path) == true) {
        FormValidation::$validation_messages = include_once $validation_message_path;
      } else {
        throw new \Exception("File not found in path '$validation_message_path'", 1);
      }

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


  private function addError($validation_result, $rule)
  {
    $message = $this->generateErrorMessage($validation_result, $rule);
    // echo("\n\n error message : $message \n\n");
    $this->error_list[] = ['field'=> $this->current_field ,'message'=> $message];
  }


  public function getFirstError(){
    return $this->error_list[0]??false;
  }

  private function generateErrorMessage($validation_result, $rule): string
  {
    if ($rule['custom_message'] == false) {
      $message = $this->getValidationErrorMessage($validation_result[1]);
    } else {
      $message = $rule['custom_message'];
    }

    $data = $this->validation_data[$this->current_field];
    $name = $data['name'];
    $t_name = $data['t_name'];

    $message = str_replace(':attribute', $t_name ?? $name, $message);

    foreach($validation_result[2]??[] as $key=> $value){
      $message = str_replace(':'.$key, $value, $message);
    }

    return $message;
  }

  private function getValidationErrorMessage(array $array)
  {
    if (count($array) == 1) {
      return FormValidation::$validation_messages[$array[0]] ?? null;
    }
    else if(count($array)==2){
      return FormValidation::$validation_messages[$array[0]][$array[1]] ?? null;
    }
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

  public function digits(int $digits, $custom_message = null)
  {
    return $this->addNewRule('digits', $custom_message, $digits);
  }

  public function digitsBetween(int $min,int $max, $custom_message=false){
    return $this->addNewRule('digits_between', $custom_message, $min, $max);
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
    return $this->addNewRule('email', $custom_message);

  }

  public function url($custom_message = false)
  {
    return $this->addNewRule('url', $custom_message);
  }

  public function domain($custom_message = false)
  {
    return $this->addNewRule('domain', $custom_message);
  }

  public function mac($custom_message = false)
  {
    return $this->addNewRule('mac', $custom_message);
  }

  public function ip($custom_message = false)
  {
    return $this->addNewRule('ip', $custom_message);
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

    $validation = true;
    foreach ($this->validation_data as $data) {

      $name = $data['name'];
      $t_name = $data['t_name'];
      $rules = $data['rules'];



      foreach ($rules as $rule) {
        $method_exec_name = '_check_' . $rule['type'];

        $result = $this->$method_exec_name($rule, $name);

        if ($result[0] == false) {
          $this->addError($result, $rule);
          $validation = false;
        }

      }

    }

    return $validation;
  }


  private function getParam($name)
  {
    return $this->form_data_params[$name] ?? null;
  }

  private function getCurrentValue()
  {
    return $this->getParam($this->current_field);
  }

  private function _check_string($rule, $name)
  {
    $value = $this->getParam($name);
    return [(is_string($value) && (gettype($value) == 'string')),['string']];
  }

  private function _check_numeric($rule, $name)
  {
    return [is_numeric($this->getParam($name)),['numeric']];
  }

  private function _check_integer($rule, $name)
  {
    return [gettype($this->getParam($name)) == 'integer',['integer']];
  }

  public function _check_digits($rule, $name){
    $digits = $rule['value1'];
    $status = (strlen((string)$this->getParam($name)) == $digits);
    return[$status, ['digits'], ['digits'=>$digits]];
  }

  public function _check_digits_between($rule, $name){
    $min = $rule['value1'];
    $max = $rule['value2'];
    $digits = strlen((string)$this->getParam($name));

    $status = false;
    if($digits <= $max && $digits >= $min){
      $status = true;
    }

    return[$status, ['digits_between'], ['min'=>$min, 'max'=>$max]];
  }

  private function _check_min($rule, $name)
  {
    $value = $this->getParam($name);
    $min = $rule['value1'];
    $status = true;
    $error_message_array = [];
    $type = gettype($value);

    if ($type == 'string' && \mb_strlen($value) < $min) {
      $status = false;
      $error_message_array = ['min', 'string'];
    } else if ($type == 'integer' && $value < $min) {
      $status = false;
      $error_message_array = ['min', 'integer'];
    } else if (is_array($value) && count($value) < $min) {
      $status = false;
      $error_message_array = ['min', 'array'];
    }

    return [$status, $error_message_array,['min'=>$min]];
  }

  private function _check_max($rule, $name)
  {
    $value = $this->getParam($name);
    $max = $rule['value1'];
    $status = true;
    $type = gettype($value);
    $error_message_array = [];


    if ($type == 'string' && \mb_strlen($value) > $max) {
      $status = false;
      $error_message_array = ['max', 'string'];
    } else if ($type == 'integer' && $value > $max) {
      $status = false;
      $error_message_array = ['max', 'integer'];
    } else if (is_array($value) && count($value) > $max) {
      $status = false;
      $error_message_array = ['max', 'array'];
    }

    return [$status, $error_message_array, ['max'=>$max]];
  }

  private function _check_email($rule, $name)
  {
    $status = $this->filter($this->getParam($name), FILTER_VALIDATE_EMAIL);
    return [$status, ['email'],];
  }

  

  private function _check_url($rule, $name)
  {
    $status = (!$this->filter($this->getParam($name), FILTER_VALIDATE_URL)===false);
    return [$status, ['url'],];
  }

  private function _check_domain($rule, $name)
  {

    $url = $this->getParam($name);
    $domain = str_replace('https://', '', $url);
    $domain = str_replace('http://', '', $domain);

    // Define a regular expression pattern for a valid domain name
    $pattern = '/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/';
    
    // Use preg_match to check if the domain matches the pattern
    if (preg_match($pattern, $domain)) {
      $status= true;
    } else {
      $status= false;
    }
   
    return [$status, ['url']];
  }

  private function _check_mac($rule, $name)
  {
    $status = $this->filter($this->getParam($name), FILTER_VALIDATE_MAC);
    return [$status, ['mac']];
  }

  private function _check_ip($rule, $name)
  {
    $status = $this->filter($this->getParam($name), FILTER_VALIDATE_IP);
    return [$status, ['mac']];
  }


  private function filter($value, $FILTER)
  {
    return filter_var($value, $FILTER);
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