<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Webrium\App;
use Webrium\Debug;
use Webrium\Route;

Debug::showErrorsStatus(true);
Debug::writErrorsStatus(false);

// init index path
App::root(__DIR__);

Route::get('', function ()
{
  return ['message'=>'successful'];
});