<?php

$time_start = microtime(true);


require_once __DIR__ . '/../vendor/autoload.php';
//
use webrium\core\Url;
use webrium\core\App;
use webrium\core\Directory;
use webrium\core\File;

App::index(__DIR__);
//
Directory::set('app','App');
Directory::set('controllers','App/Controllers');
//
if (Url::is('show/')) {
  $s=File::runControllerFunction("controllers",'msg','show');
}
else {
  $s=File::runControllerFunction("controllers",'msg','show222');

}
echo "<br>";
// echo $s['end_exec_time']."<br>";
// echo $s['start_exec_time']."<br><br>";
//
$time = $s['end_exec_time']-$s['start_exec_time'];
echo "time:$time<br>";
//
echo json_encode($s);
// File::runControllerFunction("App\Controllers\msg",'show');
// echo json_encode($exsist);


$time_end = microtime(true);
$time = $time_end - $time_start;

echo "<br><br>Did nothing in $time seconds\n";


$time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];

echo "<br><br>Did nothing in $time seconds\n";
