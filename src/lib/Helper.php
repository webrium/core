<?php
use Webrium\App;
use Webrium\Url;
use Webrium\Directory;
use Webrium\Route;
use Webrium\Vite;


/**
 * Generate a full URL for the given relative path.
 *
 * @param  string $str  Relative path (e.g. 'products/list').
 * @return string       Absolute URL.
 */
function url($str = '')
{
    return Url::get($str);
}


/**
 * Get the current request URL.
 *
 * @return string The full URL of the current request.
 */
function current_url()
{
    return Url::current();
}


/**
 * Redirect the user to the given URL.
 *
 * Sends a Location header and returns a RequestBack instance
 * so the call can be returned from a controller action.
 *
 * @param  string $url         Target URL.
 * @param  int    $statusCode  HTTP redirect status code (default: 303 See Other).
 * @return \Webrium\RequestBack
 */
function redirect($url, $statusCode = 303)
{
    header('Location: ' . $url, true, $statusCode);
    return new \Webrium\RequestBack;
}


/**
 * Redirect the user back to the previous page.
 *
 * Uses the HTTP_REFERER header to determine the previous URL.
 *
 * @return \Webrium\RequestBack
 */
function back()
{
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    return new \Webrium\RequestBack;
}


/**
 * Retrieve validation error messages from the previous request.
 *
 * @param  string|false $name  Field name to retrieve a specific error, or false for all errors.
 * @return mixed               Error message(s) from the previous request.
 */
function errors($name = false)
{
    return \Webrium\RequestBack::getError($name);
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
function old($name, $default = '')
{
    $old = \Webrium\RequestBack::getOldParamsValues();
    if (isset($old[$name])) {
        return $old[$name];
    }
    return $default;
}


/**
 * Retrieve the flash message from the previous request.
 *
 * @param  bool $justGetText  When true, returns only the message text without metadata.
 * @return mixed              Flash message data.
 */
function message($justGetText = false)
{
    return \Webrium\RequestBack::getMessage($justGetText);
}


/**
 * Retrieve an input value from the current request.
 *
 * @param  string|null $name     Input field name. Pass null to get all inputs.
 * @param  mixed       $default  Default value if the field is not present.
 * @return mixed                 Input value or default.
 */
function input($name = null, $default = null)
{
    return App::input($name, $default);
}


/**
 * Get the absolute path to a file within the public directory.
 *
 * @param  string $path  Relative path inside the public directory.
 * @return string        Absolute filesystem path.
 */
function public_path($path = '')
{
    return Directory::path('public') . "/$path";
}


/**
 * Get the absolute path to a file within the app directory.
 *
 * @param  string $path  Relative path inside the app directory.
 * @return string        Absolute filesystem path.
 */
function app_path($path = '')
{
    return Directory::path('app') . "/$path";
}


/**
 * Get the absolute path to a file within the application storage directory.
 *
 * @param  string $path  Relative path inside the storage directory.
 * @return string        Absolute filesystem path.
 */
function storage_path($path = '')
{
    return Directory::path('storage_app') . "/$path";
}


/**
 * Get the absolute path to a file relative to the application root.
 *
 * @param  string $path  Relative path from the root.
 * @return string        Absolute filesystem path.
 */
function root_path($path = '')
{
    return App::getRootPath() . "/$path";
}


/**
 * Translate a language key into the current locale's string.
 *
 * @param  string $key           Translation key (e.g. 'auth.login_failed').
 * @param  array  $replacements  Key-value pairs to substitute into the translation string.
 * @return string                Translated string.
 */
function lang($key, $replacements = [])
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
function env($name, $default = false)
{
    return App::env($name, $default);
}


/**
 * Generate a URL for a named route.
 *
 * @param  string $name    Route name.
 * @param  mixed  $params  Route parameters.
 * @return string          Generated URL.
 */
function route($name, $params)
{
    return Route::route($name, $params);
}


/**
 * Generate Vite asset HTML tags (script and/or link) for the current entry point.
 *
 * In development mode, returns the Vite dev server script tag.
 * In production mode, returns the hashed asset tags from the manifest.
 *
 * @return string HTML tags for the Vite assets.
 */
function vite_assets(): string
{
    return Vite::getInstance()->assets();
}
