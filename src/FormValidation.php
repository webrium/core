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




  private function loadValidationMessages()
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
    $this->error_list[] = [$this->current_field => $message];
  }

  private function generateErrorMessage($validation_result, $rule): string
  {
    // die(json_encode( $this->getValidationErrorMessage($validation_result[1])));
    if ($rule['custom_message'] == false) {
      $message = $this->getValidationErrorMessage($validation_result[1]);
    } else {
      $message = $rule['custom_message'];
    }

    // die('me:'.$message);
    $data = $this->validation_data[$this->current_field];
    $name = $data['name'];
    $t_name = $data['t_name'];

    // die("data:".json_encode($data));
    $message = str_replace(':attribute', $t_name ?? $name, $message);

    return $message;
  }

  private function getValidationErrorMessage(array $array)
  {
    if (count($array) == 1) {
      return $this->validation_messages[$array[0]] ?? null;
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
    return (is_string($value) && (gettype($value) == 'string'));
  }

  private function _check_numeric($rule, $name)
  {
    return is_numeric($this->getParam($name));
  }

  private function _check_integer($rule, $name)
  {
    return gettype($this->getParam($name)) == 'integer';
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

    return [$status, $error_message_array];
  }

  private function _check_max($rule, $name)
  {
    $value = $this->getParam($name);
    $max = $rule['value1'];
    $status = true;
    $type = gettype($value);

    if ($type == 'string' && \mb_strlen($value) > $max) {
      $status = false;
    } else if ($type == 'integer' && $value > $max) {
      $status = false;
    } else if (is_array($value) && count($value) > $max) {
      $status = false;
    }

    return $status;
  }

  private function _check_email()
  {
    $status = $this->filter($this->getCurrentValue(), FILTER_VALIDATE_EMAIL);
    return [$status, ['email'],];
  }

  private function _check_url()
  {
    $status = $this->filter($this->getCurrentValue(), FILTER_VALIDATE_URL);
    return [$status, ['url'],];
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