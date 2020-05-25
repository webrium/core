<?php

require_once __DIR__ . '/../vendor/autoload.php';

use webrium\core\Url;
use webrium\core\App;

App::index(__DIR__);

// echo App::index_path();

echo (Url::is('test/'));
