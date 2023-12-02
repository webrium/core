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


  /**
   * Construct a new FormValidation object.
   *
   * @param array|null $form_data_params The form data parameters.
   */
  function __construct($form_data_params = null)
  {
    if ($form_data_params === null) {
      $this->form_data_params = input();
    } else {
      $this->form_data_params = $form_data_params;

    }

    $this->loadValidationMessages();
  }



  /**
   * Load the validation messages from a file.
   */
  private function loadValidationMessages(): void
  {

    $validation_message_path = Directory::path('langs') . '/' . App::getLocale() . '/validation.php';
    if (isset(FormValidation::$validation_messages) == false) {
      if (File::exists($validation_message_path) == true) {
        FormValidation::$validation_messages = include_once $validation_message_path;
      } else {
        throw new \Exception("File not found in path '$validation_message_path'", 1);
      }

    }
  }


  /**
   * Validate the value of a field.
   *
   * @param string $name The name of the field.
   * @param string|null $translation The translation of the field name.
   * @return FormValidation
   */
  public function field(string $name, $translation = null)
  {

    $this->current_field = $name;

    $this->validation_data[$name] = [
      'has_value' => false,
      'name' => $name,
      't_name' => $translation,
      'rules' => [],
    ];

    return $this;
  }



  /**
   * Adds a numeric validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function numeric($custom_message = null)
  {
    return $this->addNewRule('numeric', $custom_message);
  }

  /**
   * Adds an integer validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function integer($custom_message = null)
  {
    return $this->addNewRule('integer', $custom_message);
  }

  /**
   * Adds a string validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function string($custom_message = null)
  {
    return $this->addNewRule('string', $custom_message);
  }

  /**
   * Adds a digits validation rule to the current field.
   *
   * @param int $digits The number of digits.
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function digits(int $digits, $custom_message = null)
  {
    return $this->addNewRule('digits', $custom_message, $digits);
  }


  /**
   * Adds a digits_between validation rule to the current field.
   *
   * @param int $min The minimum number of digits.
   * @param int $max The maximum number of digits.
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function digitsBetween(int $min, int $max, $custom_message = false)
  {
    return $this->addNewRule('digits_between', $custom_message, $min, $max);
  }


  /**
   * Adds a different validation rule to the current field.
   *
   * @param mixed $other_value The value to compare the current value to.
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function different($other_value, $custom_message = false)
  {
    return $this->addNewRule('different', $custom_message, $other_value);
  }


  /**
   * Adds a confirmed validation rule to the current field.
   *
   * @param string $field The name of the field to compare the current value to.
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function confirmed($field, $custom_message = false)
  {
    return $this->addNewRule('confirmed', $custom_message, $field);
  }

  /**
   * Adds a min validation rule to the current field.
   *
   * @param int|float $min_value The minimum value.
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function min($min_value, $custom_message = null)
  {
    return $this->addNewRule('min', $custom_message, $min_value);
  }

  /**
   * Adds a max validation rule to the current field.
   *
   * @param int|float $max_value The maximum value.
   * @param string|null $custom_message The custom error
   * @return FormValidation The current FormValidation instance.
   */
  public function max($max_value, $custom_message = null)
  {
    return $this->addNewRule('max', $custom_message, $max_value);
  }

  /**
   * Adds a required validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function required($custom_message = false)
  {
    return $this->addNewRule('required', $custom_message);
  }


  /**
   * Adds an email validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function email($custom_message = false)
  {
    return $this->addNewRule('email', $custom_message);

  }


  /**
   * Adds a url validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function url($custom_message = false)
  {
    return $this->addNewRule('url', $custom_message);
  }


  /**
   * Adds a domain validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function domain($custom_message = false)
  {
    return $this->addNewRule('domain', $custom_message);
  }


  /**
   * Adds a mac validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function mac($custom_message = false)
  {
    return $this->addNewRule('mac', $custom_message);
  }


  /**
   * Adds an ip validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function ip($custom_message = false)
  {
    return $this->addNewRule('ip', $custom_message);
  }


  /**
   * Adds a boolean validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function boolean($custom_message = false)
  {
    return $this->addNewRule('boolean', $custom_message);
  }


  /**
   * Adds an array validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function array($custom_message = false)
  {
    return $this->addNewRule('array', $custom_message);
  }


  /**
   * Adds an object validation rule to the current field.
   *
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function object($custom_message = false)
  {
    return $this->addNewRule('object', $custom_message);
  }


  /**
   * Adds a between validation rule to the current field.
   *
   * @param int|float $min The minimum value.
   * @param int|float $max The maximum value.
   * @param string|null $custom_message The custom error message for the rule.
   * @return FormValidation The current FormValidation instance.
   */
  public function between($min, $max, $custom_message = null)
  {
    return $this->addNewRule('between', $custom_message, $min, $max);
  }

  // public function file($custom_message = null)
  // {
  //   return $this->addNewRule('file', $custom_message);
  // }

  // public function size(int $size_number, $custom_message)
  // {
  //   return $this->addNewRule('size', $custom_message, $size_number);
  // }



  /**
   * Add a new validation rule.
   *
   * @param string $type The type of validation rule.
   * @param string $message The custom message for the validation rule.
   * @param mixed|null $value1 The first value for the validation rule.
   * @param mixed|null $value2 The second value for the validation rule.
   * @return FormValidation
   */
  private function addNewRule(string $type, $message, $value1 = null, $value2 = null)
  {
    $this->validation_data[$this->current_field]['rules'][] = ['type' => $type, 'custom_message' => $message, 'value1' => $value1, 'value2' => $value2];
    return $this;
  }


  /**
   * Add an error to the error list.
   *
   * @param string $field_name The name of the field with the error.
   * @param array $validation_result The result of the validation check.
   * @param array $rule The validation rule that caused the error.
   */
  private function addError(string $field_name, array $validation_result, array $rule)
  {
    $message = $this->generateErrorMessage($field_name, $validation_result, $rule);
    $this->error_list[] = ['field' => $field_name, 'message' => $message];
  }


  /**
   * Get the first error in the error list.
   *
   * @return array|false The first error or false if there are no errors.
   */
  public function getFirstError()
  {
    return $this->error_list[0] ?? false;
  }


  /**
   * Get the first error message.
   *
   * @return array|false The first error message or false.
   */
  public function getFirstErrorMessage()
  {
    return $this->getFirstError()['message'] ?? false;
  }


  /**
   * Get all errors in the error list.
   *
   * @return array An array of errors.
   */
  public function getAllError()
  {
    return $this->error_list ?? [];
  }

  private function generateErrorMessage(string $field_name, $validation_result, $rule): string
  {
    if ($rule['custom_message'] == false) {
      $message = $this->getValidationErrorMessage($validation_result[1]);
    } else {
      $message = $rule['custom_message'];
    }

    $data = $this->validation_data[$field_name];
    $name = $data['name'];
    $t_name = $data['t_name'];

    $message = str_replace(':attribute', $t_name ?? $name, $message);

    foreach ($validation_result[2] ?? [] as $key => $value) {
      $message = str_replace(':' . $key, $value, $message);
    }

    return $message;
  }

  private function getValidationErrorMessage(array $array)
  {
    if (count($array) == 1) {
      return FormValidation::$validation_messages[$array[0]] ?? null;
    } else if (count($array) == 2) {
      return FormValidation::$validation_messages[$array[0]][$array[1]] ?? null;
    }
  }


  private function process()
  {

    $validation = true;
    foreach ($this->validation_data as $data) {

      $name = $data['name'];
      $rules = $data['rules'];

      if (isset($this->form_data_params[$name])) {
        $this->validation_data[$name]['has_value'] = true;
      }


      foreach ($rules as $rule) {

        if ($this->validation_data[$name]['has_value'] == false && $rule['type'] != 'required') {
          break;
        }

        $method_exec_name = '_check_' . $rule['type'];

        $result = $this->$method_exec_name($rule, $name);

        if ($result[0] == false) {
          $this->addError($name, $result, $rule);
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

  public function _check_required($rule, $name)
  {
    return [$this->validation_data[$name]['has_value'] ?? false, ['required']];
  }

  private function _check_string($rule, $name): array
  {
    $value = $this->getParam($name);
    return [(is_string($value) && (gettype($value) == 'string')), ['string']];
  }

  private function _check_numeric($rule, $name): array
  {
    return [is_numeric($this->getParam($name)), ['numeric']];
  }

  private function _check_integer($rule, $name): array
  {
    return [gettype($this->getParam($name)) == 'integer', ['integer']];
  }

  private function _check_boolean($rule, $name): array
  {
    $value = $this->getParam($name);
    return [is_bool($value), ['boolean']];
  }

  public function _check_digits($rule, $name): array
  {
    $status = true;
    if ($this->getParam($name) != null) {
      $digits = $rule['value1'] ?? null;
      $status = (strlen((string) $this->getParam($name)) == $digits);
    }
    return [$status, ['digits'], ['digits' => $digits ?? '']];
  }

  public function _check_digits_between($rule, $name): array
  {
    $min = $rule['value1'];
    $max = $rule['value2'];
    $digits = strlen((string) $this->getParam($name));

    $status = false;
    if ($digits <= $max && $digits >= $min) {
      $status = true;
    }

    return [$status, ['digits_between'], ['min' => $min, 'max' => $max]];
  }


  public function _check_different($rule, $name): array
  {
    $other = $this->getParam($rule['value1']);

    $status = true;
    if ($other == $this->getParam($name)) {
      $status = false;
    }

    return [$status, ['different'], ['other' => $other]];
  }


  public function _check_confirmed($rule, $name): array
  {
    $field = $this->getParam($rule['value1']);

    $status = false;
    if ($field == $this->getParam($name)) {
      $status = true;
    }

    return [$status, ['confirmed']];
  }

  private function _check_min($rule, $name): array
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

    return [$status, $error_message_array, ['min' => $min]];
  }

  private function _check_max($rule, $name): array
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

    return [$status, $error_message_array, ['max' => $max]];
  }

  private function _check_email($rule, $name): array
  {
    $status = $this->filter($this->getParam($name), FILTER_VALIDATE_EMAIL);
    return [$status, ['email'],];
  }



  private function _check_url($rule, $name): array
  {
    $status = (!$this->filter($this->getParam($name), FILTER_VALIDATE_URL) === false);
    return [$status, ['url'],];
  }

  private function _check_domain($rule, $name): array
  {

    $url = $this->getParam($name);
    $domain = str_replace('https://', '', $url);
    $domain = str_replace('http://', '', $domain);

    // Define a regular expression pattern for a valid domain name
    $pattern = '/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/';

    // Use preg_match to check if the domain matches the pattern
    if (preg_match($pattern, $domain)) {
      $status = true;
    } else {
      $status = false;
    }

    return [$status, ['url']];
  }

  private function _check_mac($rule, $name): array
  {
    $status = $this->filter($this->getParam($name), FILTER_VALIDATE_MAC);
    return [$status, ['mac']];
  }

  private function _check_array($rule, $name): array
  {
    $status = is_array($this->getParam($name));
    return [$status, ['array']];
  }

  private function _check_object($rule, $name): array
  {
    $status = is_object($this->getParam($name));
    return [$status, ['object']];
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

}