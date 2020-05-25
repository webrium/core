<?php
require_once __DIR__ . '/../vendor/autoload.php';

use webrium\core\App;
use webrium\core\Directory;
use webrium\core\File;

App::index(__DIR__);

//============ (name , path)
Directory::set('app','App');
Directory::set('controllers','App/Controllers');
