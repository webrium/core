<?php

use Webrium\Url;
use Webrium\Header;
use Webrium\Directory;
use Webrium\Route;
use Webrium\App;
use Webrium\Vite;
use Webrium\Flash;


/**
 * Generate a full URL for the given relative path.
 *
 * @param  string $str  Relative path (e.g. 'products/list').
 * @return string       Absolute URL.
 */
function url(string $str = ''): string
{
    return Url::to($str);
}


/**
 * Get the current request URL.
 *
 * @return string The full URL of the current request.
 */
function current_url(): string
{
    return Url::current();
}


/**
 * Redirect the user to the given URL and terminate the request.
 *
 * @param  string $url         Target URL.
 * @param  int    $statusCode  HTTP redirect status code (default: 303 See Other).
 * @return never
 */
function redirect(string $url, int $statusCode = 303): never
{
    // Reject URLs containing line breaks to prevent header injection.
    if (preg_match('/[\r\n]/', $url)) {
        throw new \InvalidArgumentException('Redirect URL must not contain line breaks.');
    }

    header('Location: ' . $url, true, $statusCode);
    exit;
}


/**
 * Redirect the user back to the previous page and terminate the request.
 *
 * Falls back to the application base URL when no referer header is present.
 *
 * @return never
 */
function back(): never
{
    $url = Url::previous();
    redirect($url, 303);
}


/**
 * Send an HTTP response and terminate the request.
 *
 * Arrays and objects are JSON-encoded automatically.
 * Any other value is cast to string and sent as-is.
 *
 * @param  mixed $data        Response payload.
 * @param  int   $statusCode  HTTP status code (default: 200).
 * @return never
 */
function respond(mixed $data, int $statusCode = 200): never
{
    Header::respond($data, $statusCode);
}


/**
 * Retrieve validation error messages from the previous request.
 *
 * @param  string|false $name  Field name for a specific error, or false for all errors.
 * @return mixed               Error message(s) from the previous request.
 */
function errors(string|false $name = false): mixed
{
    if ($name === false) {
        return Flash::errors();
    }

    return Flash::error($name);
}


/**
 * Retrieve the old input value for a given field from the previous request.
 *
 * Useful for repopulating form fields after a failed validation.
 *
 * @param  string $name     Input field name.
 * @param  mixed  $default  Default value if the field is not found.
 * @return mixed            The old input value or the default.
 */
function old(string $name, mixed $default = ''): mixed
{
    return Flash::old($name, $default);
}


/**
 * Retrieve the flash message from the previous request.
 *
 * @param  bool $justGetText  When true, returns only the message text without metadata.
 * @return mixed              Flash message data.
 */
function message(bool $justGetText = false): mixed
{
    return Flash::getMessage($justGetText);
}


/**
 * Retrieve an input value from the current request.
 *
 * Reads GET params or the request body (JSON or form-encoded).
 * Pass null to receive the entire input array.
 *
 * @param  string|null $name     Input field name; null returns all inputs.
 * @param  mixed       $default  Default value if the field is not present.
 * @return mixed                 Input value or default.
 */
function input(?string $name = null, mixed $default = null): mixed
{
    return Url::input($name, $default);
}


/**
 * Get the absolute path to a file within the public directory.
 *
 * @param  string $path  Relative path inside the public directory.
 * @return string        Absolute filesystem path.
 */
function public_path(string $path = ''): string
{
    return Directory::path('public', $path) ?? '';
}


/**
 * Get the absolute path to a file within the app directory.
 *
 * @param  string $path  Relative path inside the app directory.
 * @return string        Absolute filesystem path.
 */
function app_path(string $path = ''): string
{
    return Directory::path('app', $path) ?? '';
}


/**
 * Get the absolute path to a file within the storage directory.
 *
 * @param  string $path  Relative path inside the storage directory.
 * @return string        Absolute filesystem path.
 */
function storage_path(string $path = ''): string
{
    return Directory::path('storage', $path) ?? '';
}


/**
 * Get the absolute path to a file within the config directory.
 *
 * @param  string $path  Relative path inside the config directory.
 * @return string        Absolute filesystem path.
 */
function config_path(string $path = ''): string
{
    return Directory::path('config', $path) ?? '';
}


/**
 * Get the absolute path to a file within the views directory.
 *
 * @param  string $path  Relative path inside the views directory.
 * @return string        Absolute filesystem path.
 */
function resource_path(string $path = ''): string
{
    return Directory::path('views', $path) ?? '';
}


/**
 * Get the absolute path to a file relative to the application root.
 *
 * @param  string $path  Relative path from the root.
 * @return string        Absolute filesystem path.
 */
function root_path(string $path = ''): string
{
    return App::getRootPath() . ($path !== '' ? '/' . ltrim($path, '/\\') : '');
}


/**
 * Translate a language key into the current locale's string.
 *
 * @param  string  $key           Translation key (e.g. 'auth.login_failed').
 * @param  array   $replacements  Key-value pairs to substitute into the translation string.
 * @return string                 Translated string.
 */
function lang(string $key, array $replacements = []): string
{
    return App::trans($key, $replacements);
}


/**
 * Retrieve an environment variable value.
 *
 * @param  string $name     Variable name.
 * @param  mixed  $default  Default value if the variable is not set.
 * @return mixed            Environment variable value or default.
 */
function env(string $name, mixed $default = null): mixed
{
    return App::env($name, $default);
}


/**
 * Generate a URL for a named route.
 *
 * @param  string $name    Route name.
 * @param  array  $params  Route parameter values keyed by placeholder name.
 * @return string          Generated URL.
 */
function route(string $name, array $params = []): string
{
    return Route::route($name, $params);
}


/**
 * Generate Vite asset HTML tags (script and/or link) for the current entry point.
 *
 * In development mode returns the Vite dev-server script tag.
 * In production mode returns the hashed asset tags from the manifest.
 *
 * @return string HTML tags for the Vite assets.
 */
function vite_assets(?string $entryPoint = null): string
{
    return $entryPoint === null
        ? Vite::getInstance()->assets()
        : Vite::getInstance()->assets($entryPoint);
}