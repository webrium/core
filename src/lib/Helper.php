<?php
use webrium\core\App;
use webrium\core\Url;
use webrium\core\View;
use webrium\core\Session;
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
   return new \webrium\core\RequestBack;
}

function back(){
  Session::set('_old',input());
  header('Location: ' . $_SERVER['HTTP_REFERER']);
  return new \webrium\core\RequestBack;
}

function error($name=false)
{
  return \webrium\core\RequestBack::getError($name);
}

function old($name,$default=''){
  $old = \webrium\core\RequestBack::getOldParamsValues();
  if (isset($old[$name])) {
    return $old[$name];
  }
  return $default;
}

function message($justGetText=false)
{
  return \webrium\core\RequestBack::getMessage($justGetText);
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
