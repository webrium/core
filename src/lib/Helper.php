<?php
use webrium\core\App;
use webrium\core\Url;
use webrium\core\View;
use webrium\core\Directory;

function url($str='')
{
  return Url::get($str);
}

function current_url()
{
  return Url::current();
}

function view($name,$params=[])
{
  return View::render($name,$params);
}

function load($name,$params=[])
{
  return View::load($name,$params);
}

function redirect($url, $statusCode = 303)
{
   header('Location: ' . $url, true, $statusCode);
   die();
}

function input($name=false,$default=null)
{
  return App::input($name,$default);
}

function public_path($path='')
{
  return Directory::path('public')."/$path";
}

function app_path($path='')
{
  return Directory::path('app')."/$path";
}

function storage_path($path='')
{
  return Directory::path('storage_app')."/$path";
}

function root_path($path='')
{
  return App::rootPath().$path;
}
