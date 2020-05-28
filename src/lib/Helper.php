<?php
use webrium\core\Url;
use webrium\core\View;

function url($str)
{
  return Url::get($str);
}

function view($name,$params=[])
{
  return View::render($name,$params);
}

function redirect($url, $statusCode = 303)
{
   header('Location: ' . $url, true, $statusCode);
   die();
}
