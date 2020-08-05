<?php
use webrium\core\App;
use webrium\core\Url;
use webrium\core\View;

function url($str='')
{
  return Url::get($str);
}

function curent_url()
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
