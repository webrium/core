<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Webrium\App;
use Webrium\Route;

App::initialize(__DIR__);

Route::get('/', function () {
    return 'Hello';
});

App::run();