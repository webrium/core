<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Webrium\App;
use Webrium\Debug;
use Webrium\Route;

Debug::enableErrorDisplay(true);
Debug::enableErrorLogging(false);
Debug::initialize();

// Set application root path
App::initialize(__DIR__);

Route::get('/', function(){
    return 'Hello';
});

App::run();