<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use webrium\core\App;
use webrium\core\Directory;
use webrium\core\Session;
use webrium\core\Route;


use webrium\mysql\DB;


$config=[];

$config['main']=[
  'driver'=>'mysql' ,
  'db_host'=>'localhost' ,
  'db_host_port'=>3306 ,
  'db_name'=>'test' ,
  'username'=>'root' ,
  'password'=>'1234' ,
  'charset'=>'utf8mb4' ,
  'result_stdClass'=>true
];

DB::setConfig($config);

App::index(__DIR__);

//============ (name , path)
Directory::initDefaultStructure();



// Session::set(['name'=>'tam']);

// echo "<br><br>";
// echo "get name : ".Session::get('name');

// Route::get('','controllers@msg->show');
// Route::get('test','controllers@msg->test');
//
// Route::any('test/*','controllers@msg->error');
// Route::any('test*','controllers@msg->error');
