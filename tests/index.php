<?php
require_once __DIR__ . '/../vendor/autoload.php';

use webrium\core\App;
use webrium\core\Directory;
use webrium\core\Route;

App::index(__DIR__);

//============ (name , path)
Directory::set('app','App');
Directory::set('controllers','App/Controllers');


Route::get('show','controllers@msg->show');
Route::get('test','controllers@msg->test');

Route::any('test/*','controllers@msg->error');
// Route::any('test*','controllers@msg->error');
