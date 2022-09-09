<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use webrium\App;
use Webrium\Debug;
use Webrium\Route;
use Webrium\Session;
use Webrium\Directory;

Debug::showErrorsStatus(true);
Debug::writErrorsStatus(false);

Directory::set('views','');

// init index path
App::root(__DIR__);

Session::set('error','this error message');

echo Session::get('error','is emty')."<br>";
echo Session::once('error','is emty')."<br>";
echo Session::get('error','is emty')."<br>";

echo "id   : ". Session::id()."<br>";
echo "name : ". Session::name();
