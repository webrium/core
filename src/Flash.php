<?php
namespace Webrium;

/**
 * Flash - Session-based Flash Data Manager
 *
 * Stores temporary data (errors, messages, old input) in the session
 * that persists for exactly one subsequent request, then is discarded.
 *
 * Typical use case: store data before a redirect, retrieve it on the next page.
 *
 * @package Webrium
 */
class Flash
{
    /**
     * Cached error data retrieved from the session.
     * Null means not yet loaded; empty array means loaded but no errors.
     */
    private static ?array $errors = null;

    /**
     * Cached old input data retrieved from the session.
     * Null means not yet loaded.
     */
    private static ?array $old = null;

    // =========================================================================
    // Errors
    // =========================================================================

    /**
     * Flash one or more validation errors to the session.
     *
     * Accepts either a single string (stored under the 'default' key)
     * or an associative array of field => message pairs.
     *
     * @param  string|array  $errors  A single message or ['field' => 'message'] map.
     * @return static
     *
     * @example
     *   Flash::withError('Something went wrong.');
     *   Flash::withError(['email' => 'Invalid email.', 'name' => 'Name is required.']);
     */
    public static function withError(string|array $errors): static
    {
        $payload = is_string($errors) ? ['default' => $errors] : $errors;
        Session::set('_flash_errors', $payload);
        return new static;
    }

    /**
     * Check whether any flashed errors exist.
     *
     * @return bool  True if at least one error is present.
     *
     * @example
     *   if (Flash::hasErrors()) { ... }
     */
    public static function hasErrors(): bool
    {
        return !empty(self::errors());
    }

    /**
     * Check whether a flashed error exists for a specific field.
     *
     * @param  string  $field  The field name to check.
     * @return bool
     *
     * @example
     *   if (Flash::hasError('email')) { ... }
     */
    public static function hasError(string $field): bool
    {
        return self::error($field) !== null;
    }

    /**
     * Get the flashed error message for a specific field.
     *
     * Returns null if no error is stored for that field.
     *
     * @param  string  $field  The field name.
     * @return string|null
     *
     * @example
     *   echo Flash::error('email') ?? '';
     */
    public static function error(string $field): ?string
    {
        $all = self::errors();
        return $all[$field] ?? null;
    }

    /**
     * Get all flashed errors as an associative array.
     *
     * Results are cached after the first call so the session is only read once.
     *
     * @return array  ['field' => 'message', ...]  or an empty array if none.
     *
     * @example
     *   $errors = Flash::errors();
     *   foreach ($errors as $field => $message) { ... }
     */
    public static function errors(): array
    {
        if (self::$errors === null) {
            self::$errors = Session::once('_flash_errors', []);
        }
        return self::$errors ?? [];
    }

    /**
     * Remove all flashed errors from the session and reset the local cache.
     *
     * @return void
     *
     * @example
     *   Flash::clearErrors();
     */
    public static function clearErrors(): void
    {
        self::$errors = [];
        Session::forget('_flash_errors');
    }

    // =========================================================================
    // Messages
    // =========================================================================

    /**
     * Flash a success message to the session.
     *
     * @param  string  $text  The message text.
     * @return static
     *
     * @example
     *   Flash::success('Record saved successfully.');
     */
    public static function success(string $text): static
    {
        return self::setMessage($text, 'success');
    }

    /**
     * Flash an error message to the session.
     *
     * @param  string  $text  The message text.
     * @return static
     *
     * @example
     *   Flash::errorMessage('Failed to save. Please try again.');
     */
    public static function errorMessage(string $text): static
    {
        return self::setMessage($text, 'error');
    }

    /**
     * Flash a normal (neutral) message to the session.
     *
     * @param  string  $text  The message text.
     * @return static
     *
     * @example
     *   Flash::message('Your session will expire in 5 minutes.');
     */
    public static function message(string $text): static
    {
        return self::setMessage($text, 'normal');
    }

    /**
     * Check whether a flashed message is waiting in the session.
     *
     * @return bool
     *
     * @example
     *   if (Flash::hasMessage()) { echo Flash::getMessage(); }
     */
    public static function hasMessage(): bool
    {
        return Session::has('_flash_message');
    }

    /**
     * Retrieve and render the flashed message.
     *
     * By default, wraps the text in the configured template (MESSAGE_SCRIPT,
     * MESSAGE_SCRIPT_SUCCESS, or MESSAGE_SCRIPT_ERROR constants). If no
     * matching template constant is defined, falls back to a JS alert().
     *
     * Pass $raw = true to receive the plain text without any template.
     *
     * The message is consumed from the session on first read (flash behavior).
     *
     * @param  bool  $raw  When true, return plain text instead of rendered HTML.
     * @return string|false  Rendered output, plain text, or false if no message.
     *
     * @example
     *   // Rendered output (uses MESSAGE_SCRIPT constants):
     *   echo Flash::getMessage();
     *
     *   // Plain text only:
     *   $text = Flash::getMessage(raw: true);
     */
    public static function getMessage(bool $raw = false): string|false
    {
        $text = Session::once('_flash_message', false);
        $type = Session::once('_flash_message_type', 'normal');

        if (!$text) {
            return false;
        }

        if ($raw) {
            return $text;
        }

        return self::renderMessage($text, $type);
    }

    /**
     * Remove the flashed message from the session.
     *
     * @return void
     *
     * @example
     *   Flash::clearMessage();
     */
    public static function clearMessage(): void
    {
        Session::forget('_flash_message');
        Session::forget('_flash_message_type');
    }

    // =========================================================================
    // Old Input
    // =========================================================================

    /**
     * Flash the current request's input data to the session.
     *
     * Call this before redirecting so the previous values can be
     * repopulated into the form on the next request via old().
     *
     * Relies on the global input() helper to retrieve all request input.
     *
     * @return static
     *
     * @example
     *   Flash::withInput();
     */
    public static function withInput(): static
    {
        Session::set('_flash_old', input());
        return new static;
    }

    /**
     * Get the flashed value of a single input field.
     *
     * @param  string  $field    The input field name.
     * @param  mixed   $default  Value to return when the field is not found.
     * @return mixed
     *
     * @example
     *   <input type="text" name="email" value="<?= Flash::old('email') ?>">
     */
    public static function old(string $field, mixed $default = null): mixed
    {
        if (self::$old === null) {
            self::$old = Session::once('_flash_old', []);
        }
        return self::$old[$field] ?? $default;
    }

    /**
     * Get all flashed old input values as an associative array.
     *
     * @return array  ['field' => 'value', ...] or an empty array if none.
     *
     * @example
     *   $old = Flash::oldAll();
     */
    public static function oldAll(): array
    {
        if (self::$old === null) {
            self::$old = Session::once('_flash_old', []);
        }
        return self::$old ?? [];
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Store a message and its type in the session.
     *
     * @param  string  $text  The message text.
     * @param  string  $type  One of: 'normal', 'success', 'error'.
     * @return static
     */
    private static function setMessage(string $text, string $type): static
    {
        Session::set('_flash_message', $text);
        Session::set('_flash_message_type', $type);
        return new static;
    }

    /**
     * Render a message using the appropriate template constant.
     *
     * Templates are defined as PHP constants and must contain the @text
     * placeholder, which is replaced with the escaped message text.
     *
     * Supported constants:
     *   - MESSAGE_SCRIPT         (type: normal)
     *   - MESSAGE_SCRIPT_SUCCESS (type: success)
     *   - MESSAGE_SCRIPT_ERROR   (type: error)
     *
     * Falls back to a JavaScript alert() if no constant is defined.
     *
     * @param  string  $text  The message text.
     * @param  string  $type  The message type.
     * @return string  Rendered HTML/JS output.
     */
    private static function renderMessage(string $text, string $type): string
    {
        $templates = [
            'normal'  => defined('MESSAGE_SCRIPT')          ? MESSAGE_SCRIPT          : null,
            'success' => defined('MESSAGE_SCRIPT_SUCCESS')   ? MESSAGE_SCRIPT_SUCCESS   : null,
            'error'   => defined('MESSAGE_SCRIPT_ERROR')     ? MESSAGE_SCRIPT_ERROR     : null,
        ];

        $template = $templates[$type] ?? "<script>alert('@text');</script>";

        // Sanitize output to prevent XSS
        return str_replace('@text', htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), $template);
    }
}