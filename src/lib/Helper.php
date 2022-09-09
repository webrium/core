<?php
use Webrium\App;
use Webrium\Url;
use Webrium\View;
use Webrium\Directory;

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
   return new \Webrium\RequestBack;
}

function back(){
  header('Location: ' . $_SERVER['HTTP_REFERER']);
  return new \Webrium\RequestBack;
}

function errors($name=false)
{
  return \Webrium\RequestBack::getError($name);
}

function old($name,$default=''){
  $old = \Webrium\RequestBack::getOldParamsValues();
  if (isset($old[$name])) {
    return $old[$name];
  }
  return $default;
}

function message($justGetText=false)
{
  return \Webrium\RequestBack::getMessage($justGetText);
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
  return App::rootPath()."/$path";
}
