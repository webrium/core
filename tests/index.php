<?php
require_once __DIR__ . '/../vendor/autoload.php';

use webrium\core\App;
use webrium\core\Debug;
use webrium\core\Route;

Debug::displayErrors(true);
Debug::writeError(false);

// init index path
App::root(__DIR__);

Route::get('', function ()
{
  return ['message'=>'successful'];
});
