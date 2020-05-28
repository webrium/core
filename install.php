<?php

require_once __DIR__ . '/vendor/autoload.php';

use webrium\core\App;
use webrium\core\Directory;
use webrium\core\File;
use webrium\core\Debug;

Debug::displayErrors(true);

App::index(__DIR__);

Directory::initDefaultStructure();
Directory::makes();

File::delete(__DIR__,'install.php');

echo "ok";
