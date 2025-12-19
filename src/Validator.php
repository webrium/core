<?php
namespace Webrium;

use Webrium\File;
use Webrium\Directory;
use Webrium\App;

/**
 * Validator class for form data validation.
 * 
 * Provides a fluent interface for validating form inputs with various rules.
 * Supports custom error messages and localization.
 * 
 * @package Webrium
 */
class Validator
{
    /**
     * Form data to be validated
     */
    private array $data;

    /**
     * Loaded validation error messages from locale file
     */
    private static array $messages;

    /**
     * Validation configuration for each field
     */
    private array $fields = [];

    /**
     * Currently selected field for chaining rules
     */
    private string $currentField;

    /**
     * List of validation errors
     */
    private array $errors = [];

    /**
     * Flag to track if validation has been executed
     */
    private bool $validated = false;

    /**
     * Maximum regex execution time in seconds
     */
    private const MAX_REGEX_EXECUTION_TIME = 2;


    /**
     * Create a new Validator instance.
     *
     * @param array|null $data Form data to validate. If null, uses global input()
     * @throws \Exception If validation messages file is not found
     */
    public function __construct(?array $data = null)
    {
        $this->data = $data ?? input();
        $this->loadMessages();
    }


    /**
     * Load validation error messages from locale file.
     * Messages are cached statically to avoid multiple file reads.
     *
     * @return void
     * @throws \Exception If validation messages file doesn't exist
     */
    private function loadMessages(): void
    {
        if (isset(self::$messages)) {
            return;
        }

        $messagesPath = Directory::path('langs') . '/' . App::getLocale() . '/validation.php';
        
        if (!File::exists($messagesPath)) {
            throw new \Exception("Validation messages file not found: '$messagesPath'");
        }

        self::$messages = include $messagesPath;
    }


    /**
     * Select a field for validation and define optional display name.
     *
     * @param string $name Field name in the form data
     * @param string|null $label Human-readable label for error messages
     * @return self
     */
    public function field(string $name, ?string $label = null): self
    {
        $this->currentField = $name;

        $this->fields[$name] = [
            'name' => $name,
            'label' => $label ?? $name,
            'hasValue' => isset($this->data[$name]) && $this->data[$name] !== '' && $this->data[$name] !== null,
            'rules' => [],
            'nullable' => false,
            'sometimes' => false,
        ];

        return $this;
    }


    /**
     * Require field to be present and not empty.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function required(?string $message = null): self
    {
        return $this->addRule('required', $message);
    }


    /**
     * Allow field to be null.
     * When this rule is applied, validation will pass if the field is null or empty.
     *
     * @return self
     */
    public function nullable(): self
    {
        $this->fields[$this->currentField]['nullable'] = true;
        return $this;
    }


    /**
     * Apply validation only when field is present.
     * If field is not present in request data, all rules are skipped.
     *
     * @return self
     */
    public function sometimes(): self
    {
        $this->fields[$this->currentField]['sometimes'] = true;
        return $this;
    }


    /**
     * Validate field as numeric (integer or float).
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function numeric(?string $message = null): self
    {
        return $this->addRule('numeric', $message);
    }


    /**
     * Validate field as integer only.
     * Accepts both positive and negative integers.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function integer(?string $message = null): self
    {
        return $this->addRule('integer', $message);
    }


    /**
     * Validate field as string.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function string(?string $message = null): self
    {
        return $this->addRule('string', $message);
    }


    /**
     * Validate field contains only alphabetic characters.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function alpha(?string $message = null): self
    {
        return $this->addRule('alpha', $message);
    }


    /**
     * Validate field contains only alphanumeric characters.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function alphaNum(?string $message = null): self
    {
        return $this->addRule('alpha_num', $message);
    }


    /**
     * Validate field has exact number of digits.
     *
     * @param int $length Expected number of digits
     * @param string|null $message Custom error message
     * @return self
     */
    public function digits(int $length, ?string $message = null): self
    {
        return $this->addRule('digits', $message, $length);
    }


    /**
     * Validate field has digits between min and max.
     *
     * @param int $min Minimum number of digits
     * @param int $max Maximum number of digits
     * @param string|null $message Custom error message
     * @return self
     */
    public function digitsBetween(int $min, int $max, ?string $message = null): self
    {
        return $this->addRule('digits_between', $message, $min, $max);
    }


    /**
     * Validate field is different from another field.
     *
     * @param string $otherField Name of the field to compare against
     * @param string|null $message Custom error message
     * @return self
     */
    public function different(string $otherField, ?string $message = null): self
    {
        return $this->addRule('different', $message, $otherField);
    }


    /**
     * Validate field matches another field (e.g., password confirmation).
     *
     * @param string $otherField Name of the field to match
     * @param string|null $message Custom error message
     * @return self
     */
    public function confirmed(string $otherField, ?string $message = null): self
    {
        return $this->addRule('confirmed', $message, $otherField);
    }


    /**
     * Validate field meets minimum value/length.
     * - For strings: minimum character length
     * - For numbers: minimum value
     * - For arrays: minimum item count
     *
     * @param int|float $min Minimum value
     * @param string|null $message Custom error message
     * @return self
     */
    public function min($min, ?string $message = null): self
    {
        return $this->addRule('min', $message, $min);
    }


    /**
     * Validate field doesn't exceed maximum value/length.
     * - For strings: maximum character length
     * - For numbers: maximum value
     * - For arrays: maximum item count
     *
     * @param int|float $max Maximum value
     * @param string|null $message Custom error message
     * @return self
     */
    public function max($max, ?string $message = null): self
    {
        return $this->addRule('max', $message, $max);
    }


    /**
     * Validate field is between min and max values.
     * - For strings: character length
     * - For numbers: numeric value
     * - For arrays: item count
     *
     * @param int|float $min Minimum value
     * @param int|float $max Maximum value
     * @param string|null $message Custom error message
     * @return self
     */
    public function between($min, $max, ?string $message = null): self
    {
        return $this->addRule('between', $message, $min, $max);
    }


    /**
     * Validate field as valid email address.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function email(?string $message = null): self
    {
        return $this->addRule('email', $message);
    }


    /**
     * Validate field as valid phone number.
     * Supports international formats with optional country code.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function phone(?string $message = null): self
    {
        return $this->addRule('phone', $message);
    }


    /**
     * Validate field as valid URL.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function url(?string $message = null): self
    {
        return $this->addRule('url', $message);
    }


    /**
     * Validate field as valid domain name.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function domain(?string $message = null): self
    {
        return $this->addRule('domain', $message);
    }


    /**
     * Validate field as valid MAC address.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function mac(?string $message = null): self
    {
        return $this->addRule('mac', $message);
    }


    /**
     * Validate field as valid IP address.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function ip(?string $message = null): self
    {
        return $this->addRule('ip', $message);
    }


    /**
     * Validate field as boolean value.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function boolean(?string $message = null): self
    {
        return $this->addRule('boolean', $message);
    }


    /**
     * Validate field is an array.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function array(?string $message = null): self
    {
        return $this->addRule('array', $message);
    }


    /**
     * Validate field is an object.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function object(?string $message = null): self
    {
        return $this->addRule('object', $message);
    }


    /**
     * Validate field matches a regular expression pattern.
     * Includes security measures against ReDoS attacks.
     *
     * @param string $pattern Regular expression pattern
     * @param string|null $message Custom error message
     * @return self
     * @throws \Exception If pattern is potentially dangerous
     */
    public function regex(string $pattern, ?string $message = null): self
    {
        // Security: Prevent ReDoS attacks
        $this->validateRegexSafety($pattern);
        
        return $this->addRule('regex', $message, $pattern);
    }


    /**
     * Validate regex pattern safety to prevent ReDoS attacks.
     *
     * @param string $pattern Regex pattern to validate
     * @return void
     * @throws \Exception If pattern is potentially dangerous
     */
    private function validateRegexSafety(string $pattern): void
    {
        // Check for complex patterns that can cause ReDoS
        $dangerousPatterns = [
            '/(\*|\+|\{)\{/',           // Nested quantifiers like {2,}{3,}
            '/\(\?[!<>=].*\(\?[!<>=]/', // Nested lookahead/lookbehind
            '/\(\?R\)/',                // Recursive patterns
            '/\(\?\d+\)/',              // Subroutine calls
        ];

        foreach ($dangerousPatterns as $dangerousPattern) {
            if (preg_match($dangerousPattern, $pattern)) {
                throw new \Exception('Complex or potentially dangerous regex patterns are not allowed for security reasons');
            }
        }

        // Limit pattern length
        if (strlen($pattern) > 500) {
            throw new \Exception('Regex pattern is too long (max 500 characters)');
        }
    }


    /**
     * Validate field is one of the given values.
     *
     * @param array $values Allowed values
     * @param string|null $message Custom error message
     * @return self
     */
    public function in(array $values, ?string $message = null): self
    {
        return $this->addRule('in', $message, $values);
    }


    /**
     * Validate field is not one of the given values.
     *
     * @param array $values Disallowed values
     * @param string|null $message Custom error message
     * @return self
     */
    public function notIn(array $values, ?string $message = null): self
    {
        return $this->addRule('not_in', $message, $values);
    }


    /**
     * Validate field as valid JSON string.
     *
     * @param string|null $message Custom error message
     * @return self
     */
    public function json(?string $message = null): self
    {
        return $this->addRule('json', $message);
    }


    /**
     * Validate field as valid date format.
     *
     * @param string $format Expected date format (default: Y-m-d)
     * @param string|null $message Custom error message
     * @return self
     */
    public function date(string $format = 'Y-m-d', ?string $message = null): self
    {
        return $this->addRule('date', $message, $format);
    }


    /**
     * Add a validation rule to the current field.
     *
     * @param string $type Rule type
     * @param string|null $message Custom error message
     * @param mixed $param1 First rule parameter
     * @param mixed $param2 Second rule parameter
     * @return self
     */
    private function addRule(string $type, ?string $message = null, $param1 = null, $param2 = null): self
    {
        $this->fields[$this->currentField]['rules'][] = [
            'type' => $type,
            'message' => $message,
            'param1' => $param1,
            'param2' => $param2,
        ];

        return $this;
    }


    /**
     * Execute validation on all defined fields.
     *
     * @return bool True if all validations pass, false otherwise
     */
    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = true;
        $isValid = true;

        foreach ($this->fields as $fieldName => $field) {
            // Skip validation if field has 'sometimes' and is not present
            if ($field['sometimes'] && !isset($this->data[$fieldName])) {
                continue;
            }

            // Skip validation if field is nullable and has no value
            if ($field['nullable'] && !$field['hasValue']) {
                continue;
            }

            foreach ($field['rules'] as $rule) {
                // Skip non-required rules if field has no value and is not nullable
                if (!$field['hasValue'] && $rule['type'] !== 'required' && !$field['nullable']) {
                    continue;
                }

                $validatorMethod = 'validate' . str_replace('_', '', ucwords($rule['type'], '_'));

                if (!method_exists($this, $validatorMethod)) {
                    throw new \Exception("Validation method '$validatorMethod' does not exist for rule '{$rule['type']}'");
                }

                $result = $this->$validatorMethod($rule, $fieldName);

                if (!$result['valid']) {
                    $this->addError($fieldName, $field, $rule, $result);
                    $isValid = false;
                    break; // Stop at first error for this field
                }
            }
        }

        return $isValid;
    }


    /**
     * Alias for validate() method.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->validate();
    }


    /**
     * Check if validation has failed.
     *
     * @return bool
     */
    public function fails(): bool
    {
        if (!$this->validated) {
            $this->validate();
        }

        return !empty($this->errors);
    }


    /**
     * Check if validation has passed.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return !$this->fails();
    }


    /**
     * Get value from form data with sanitization.
     *
     * @param string $name Field name
     * @return mixed
     */
    private function getValue(string $name)
    {
        $value = $this->data[$name] ?? null;

        // Basic sanitization for strings
        if (is_string($value)) {
            // Trim whitespace
            $value = trim($value);
            
            // Remove null bytes (security)
            $value = str_replace("\0", '', $value);
        }

        return $value;
    }


    /**
     * Add validation error to the error list.
     *
     * @param string $fieldName Field name
     * @param array $field Field configuration
     * @param array $rule Rule configuration
     * @param array $result Validation result
     * @return void
     */
    private function addError(string $fieldName, array $field, array $rule, array $result): void
    {
        $message = $this->generateErrorMessage($field, $rule, $result);
        
        $this->errors[] = [
            'field' => $fieldName,
            'message' => $message,
        ];
    }


    /**
     * Generate error message for failed validation.
     *
     * @param array $field Field configuration
     * @param array $rule Rule configuration
     * @param array $result Validation result
     * @return string
     */
    private function generateErrorMessage(array $field, array $rule, array $result): string
    {
        // Use custom message if provided
        if ($rule['message']) {
            $message = $rule['message'];
        } else {
            $message = $this->getMessageTemplate($result['messageKey']);
        }

        if (!$message) {
            $message = "The :attribute field is invalid.";
        }

        // Replace placeholders
        $message = str_replace(':attribute', $field['label'], $message);

        if (isset($result['replacements'])) {
            foreach ($result['replacements'] as $key => $value) {
                $message = str_replace(':' . $key, (string)$value, $message);
            }
        }

        return $message;
    }


    /**
     * Get error message template from locale file.
     *
     * @param array|string $key Message key or nested keys
     * @return string|null
     */
    private function getMessageTemplate($key): ?string
    {
        if (is_string($key)) {
            return self::$messages[$key] ?? null;
        }

        if (is_array($key) && count($key) === 2) {
            return self::$messages[$key[0]][$key[1]] ?? null;
        }

        return null;
    }


    /**
     * Get the first validation error.
     *
     * @return array|null
     */
    public function getFirstError(): ?array
    {
        return $this->errors[0] ?? null;
    }


    /**
     * Get the first error message.
     *
     * @return string|null
     */
    public function getFirstErrorMessage(): ?string
    {
        $error = $this->getFirstError();
        return $error ? $error['message'] : null;
    }


    /**
     * Get all validation errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }


    /**
     * Get errors for a specific field.
     *
     * @param string $fieldName Field name
     * @return array
     */
    public function getFieldErrors(string $fieldName): array
    {
        return array_filter($this->errors, function($error) use ($fieldName) {
            return $error['field'] === $fieldName;
        });
    }


    /**
     * Check if a specific field has errors.
     *
     * @param string $fieldName Field name
     * @return bool
     */
    public function hasError(string $fieldName): bool
    {
        return !empty($this->getFieldErrors($fieldName));
    }


    // ==================== VALIDATION METHODS ====================

    /**
     * Validate required field.
     */
    private function validateRequired(array $rule, string $fieldName): array
    {
        $hasValue = $this->fields[$fieldName]['hasValue'];
        return [
            'valid' => $hasValue,
            'messageKey' => 'required',
        ];
    }


    /**
     * Validate string type.
     */
    private function validateString(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $isValid = is_string($value);
        
        return [
            'valid' => $isValid,
            'messageKey' => 'string',
        ];
    }


    /**
     * Validate alphabetic characters only.
     */
    private function validateAlpha(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $isValid = is_string($value) && preg_match('/^[a-zA-Z]+$/', $value) === 1;
        
        return [
            'valid' => $isValid,
            'messageKey' => 'alpha',
        ];
    }


    /**
     * Validate alphanumeric characters only.
     */
    private function validateAlphaNum(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $isValid = is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
        
        return [
            'valid' => $isValid,
            'messageKey' => 'alpha_num',
        ];
    }


    /**
     * Validate numeric type.
     */
    private function validateNumeric(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => is_numeric($value),
            'messageKey' => 'numeric',
        ];
    }


    /**
     * Validate integer type (supports negative integers).
     */
    private function validateInteger(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        
        // Fixed: Support negative integers
        if (is_int($value)) {
            return ['valid' => true, 'messageKey' => 'integer'];
        }
        
        if (is_string($value)) {
            // Check for negative integers
            $isValid = preg_match('/^-?\d+$/', $value) === 1;
            return ['valid' => $isValid, 'messageKey' => 'integer'];
        }
        
        return ['valid' => false, 'messageKey' => 'integer'];
    }


    /**
     * Validate boolean type.
     */
    private function validateBoolean(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true),
            'messageKey' => 'boolean',
        ];
    }


    /**
     * Validate exact digit count.
     */
    private function validateDigits(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $length = $rule['param1'];
        $isValid = is_numeric($value) && strlen((string)$value) === $length;

        return [
            'valid' => $isValid,
            'messageKey' => 'digits',
            'replacements' => ['digits' => $length],
        ];
    }


    /**
     * Validate digits between range.
     */
    private function validateDigitsBetween(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $min = $rule['param1'];
        $max = $rule['param2'];
        $length = strlen((string)$value);

        $isValid = is_numeric($value) && $length >= $min && $length <= $max;

        return [
            'valid' => $isValid,
            'messageKey' => 'digits_between',
            'replacements' => ['min' => $min, 'max' => $max],
        ];
    }


    /**
     * Validate field is different from another.
     */
    private function validateDifferent(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $otherField = $rule['param1'];
        $otherValue = $this->getValue($otherField);

        return [
            'valid' => $value !== $otherValue,
            'messageKey' => 'different',
            'replacements' => ['other' => $otherField],
        ];
    }


    /**
     * Validate field matches another.
     */
    private function validateConfirmed(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $otherField = $rule['param1'];
        $otherValue = $this->getValue($otherField);

        return [
            'valid' => $value === $otherValue,
            'messageKey' => 'confirmed',
        ];
    }


    /**
     * Validate minimum value/length.
     */
    private function validateMin(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $min = $rule['param1'];
        $type = gettype($value);
        $isValid = true;
        $messageKey = ['min', 'string'];

        if ($type === 'string') {
            $isValid = mb_strlen($value) >= $min;
            $messageKey = ['min', 'string'];
        } elseif (is_numeric($value)) {
            $isValid = $value >= $min;
            $messageKey = ['min', 'numeric'];
        } elseif (is_array($value)) {
            $isValid = count($value) >= $min;
            $messageKey = ['min', 'array'];
        }

        return [
            'valid' => $isValid,
            'messageKey' => $messageKey,
            'replacements' => ['min' => $min],
        ];
    }


    /**
     * Validate maximum value/length.
     */
    private function validateMax(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $max = $rule['param1'];
        $type = gettype($value);
        $isValid = true;
        $messageKey = ['max', 'string'];

        if ($type === 'string') {
            $isValid = mb_strlen($value) <= $max;
            $messageKey = ['max', 'string'];
        } elseif (is_numeric($value)) {
            $isValid = $value <= $max;
            $messageKey = ['max', 'numeric'];
        } elseif (is_array($value)) {
            $isValid = count($value) <= $max;
            $messageKey = ['max', 'array'];
        }

        return [
            'valid' => $isValid,
            'messageKey' => $messageKey,
            'replacements' => ['max' => $max],
        ];
    }


    /**
     * Validate value is between range.
     */
    private function validateBetween(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $min = $rule['param1'];
        $max = $rule['param2'];
        $type = gettype($value);
        $isValid = false;

        if ($type === 'string') {
            $length = mb_strlen($value);
            $isValid = $length >= $min && $length <= $max;
        } elseif (is_numeric($value)) {
            $isValid = $value >= $min && $value <= $max;
        } elseif (is_array($value)) {
            $count = count($value);
            $isValid = $count >= $min && $count <= $max;
        }

        return [
            'valid' => $isValid,
            'messageKey' => 'between',
            'replacements' => ['min' => $min, 'max' => $max],
        ];
    }


    /**
     * Validate email address.
     */
    private function validateEmail(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'messageKey' => 'email',
        ];
    }


    /**
     * Validate phone number.
     * Supports international formats with optional country code.
     */
    private function validatePhone(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        
        // Remove common separators
        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', $value);
        
        // Check for valid phone number format
        // Supports: +1234567890, 1234567890, (123) 456-7890, etc.
        $isValid = preg_match('/^\+?[1-9]\d{1,14}$/', $cleaned) === 1;

        return [
            'valid' => $isValid,
            'messageKey' => 'phone',
        ];
    }


    /**
     * Validate URL.
     */
    private function validateUrl(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'messageKey' => 'url',
        ];
    }


    /**
     * Validate domain name (improved and simplified).
     */
    private function validateDomain(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $value);
        $domain = strtok($domain, '/'); // Remove path
        
        // Validate using FILTER_VALIDATE_DOMAIN (PHP 7.0+)
        $isValid = filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        return [
            'valid' => $isValid,
            'messageKey' => 'domain',
        ];
    }


    /**
     * Validate MAC address.
     */
    private function validateMac(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => filter_var($value, FILTER_VALIDATE_MAC) !== false,
            'messageKey' => 'mac',
        ];
    }


    /**
     * Validate IP address.
     */
    private function validateIp(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => filter_var($value, FILTER_VALIDATE_IP) !== false,
            'messageKey' => 'ip',
        ];
    }


    /**
     * Validate array type.
     */
    private function validateArray(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => is_array($value),
            'messageKey' => 'array',
        ];
    }


    /**
     * Validate object type.
     */
    private function validateObject(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        return [
            'valid' => is_object($value),
            'messageKey' => 'object',
        ];
    }


    


    /**
     * Validate regex pattern with timeout protection.
     */
    private function validateRegex(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $pattern = $rule['param1'];

        // Set timeout for regex execution (prevent ReDoS)
        $startTime = microtime(true);
        
        try {
            // Use error suppression and check for timeout
            set_error_handler(function() {});
            $result = @preg_match($pattern, $value);
            restore_error_handler();
            
            $executionTime = microtime(true) - $startTime;
            
            // Check if execution took too long
            if ($executionTime > self::MAX_REGEX_EXECUTION_TIME) {
                throw new \Exception('Regex execution timeout');
            }
            
            $isValid = $result === 1;
        } catch (\Exception $e) {
            // Regex execution failed or timed out
            $isValid = false;
        }

        return [
            'valid' => $isValid,
            'messageKey' => 'regex',
        ];
    }


    /**
     * Validate value is in list.
     */
    private function validateIn(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $values = $rule['param1'];

        return [
            'valid' => in_array($value, $values, true),
            'messageKey' => 'in',
        ];
    }


    /**
     * Validate value is not in list.
     */
    private function validateNotIn(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $values = $rule['param1'];

        return [
            'valid' => !in_array($value, $values, true),
            'messageKey' => 'not_in',
        ];
    }


    /**
     * Validate JSON string.
     */
    private function validateJson(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        
        if (!is_string($value)) {
            return ['valid' => false, 'messageKey' => 'json'];
        }

        json_decode($value);
        return [
            'valid' => json_last_error() === JSON_ERROR_NONE,
            'messageKey' => 'json',
        ];
    }


    /**
     * Validate date format.
     */
    private function validateDate(array $rule, string $fieldName): array
    {
        $value = $this->getValue($fieldName);
        $format = $rule['param1'] ?? 'Y-m-d';

        $date = \DateTime::createFromFormat($format, $value);
        $isValid = $date && $date->format($format) === $value;

        return [
            'valid' => $isValid,
            'messageKey' => 'date',
            'replacements' => ['format' => $format],
        ];
    }
}