<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use webrium\core\App;
use webrium\core\Directory;
use webrium\core\File;


App::index(__DIR__);

Directory::initDefaultStructure();
Directory::makes();

File::delete(__DIR__,'install.php');

echo "ok";
