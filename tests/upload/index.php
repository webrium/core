<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use webrium\core\App;
use webrium\core\Debug;
use webrium\core\Route;
use webrium\core\View;
use webrium\core\Upload;
use webrium\core\Directory;

Debug::showErrorsStatus(true);
Debug::writErrorsStatus(false);

Directory::set('views','');

// init index path
App::root(__DIR__);

Route::get('', function ()
{
  return View::load('upload-html-two-files');
});

Route::get('array', function ()
{
  return View::load('upload-html-array-files');
});


Route::post('get/two-files', function ()
{
  $image = new Upload('image-1');



  echo "exists : ".( $image->exists()?'true':'false') ."<br>";
  echo "count  : ". $image->count() ."<br>";
  echo "size  : ". $image->size() ."<br>";
  echo "type  : ". $image->type() ."<br>";

  $image->path(__DIR__)
  ->maxSize(200)
  ->allowTypes(['image/svg+xml'])
  ->save();


  if (!$image->status()) {
    echo "error : " . $image->getFirstError();
  }

});

Route::any('get/array-files', function ()
{
  $images = new Upload('image');

  echo "exists : ".( $images->exists()?'true':'false') ."<br>";
  echo "count  : ". $images->count() ."<br>";

  $dir = __DIR__."/files";

  foreach ($images->get() as $key => $file) {
    $status = $file->path($dir)->save();

    if (! $status) {
      echo "error : ".$file->getClientOriginalName()." message : ".$file->getFirstError()."<br>";
    }
  }

  echo "<br>end<br>";
});
