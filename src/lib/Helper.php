<?php
use Webrium\App;
use Webrium\Url;
use Webrium\View\Engine;
use Webrium\Directory;
use Webrium\Route;
use Webrium\Vite;


function url($str = '')
{
  return Url::get($str);
}


function current_url()
{
  return Url::current();
}


function view($name, $params = [])
{
  return Engine::render($name, $params);
}

function layout($layoutView, $view, $data = [])
{
  return Engine::renderLayout($layoutView, $view, $data);
}

// function loadview($name)
// {
//   return View::loadview($name);
// }


function redirect($url, $statusCode = 303)
{
  header('Location: ' . $url, true, $statusCode);
  return new \Webrium\RequestBack;
}


function back()
{
  header('Location: ' . $_SERVER['HTTP_REFERER']);
  return new \Webrium\RequestBack;
}


function errors($name = false)
{
  return \Webrium\RequestBack::getError($name);
}


function old($name, $default = '')
{
  $old = \Webrium\RequestBack::getOldParamsValues();
  if (isset($old[$name])) {
    return $old[$name];
  }
  return $default;
}

function message($justGetText = false)
{
  return \Webrium\RequestBack::getMessage($justGetText);
}


function input($name = null, $default = null)
{
  return App::input($name, $default);
}

function public_path($path = '')
{
  return Directory::path('public') . "/$path";
}


function app_path($path = '')
{
  return Directory::path('app') . "/$path";
}


function storage_path($path = '')
{
  return Directory::path('storage_app') . "/$path";
}


function root_path($path = '')
{
  return App::getRootPath() . "/$path";
}


function lang($key, $replacements = [])
{
  return App::trans($key, $replacements);
}


function env($name, $default = false)
{
  return App::env($name, $default);
}


function route($name, $params)
{
  return Route::route($name, $params);
}

/**
 * Simplifies the main call from the view.
 * * @param string $entryPoint The main entry point file.
 * @return string The generated HTML tags.
 */
function vite_assets(): string
{
  return Vite::getInstance()->assets();
}
