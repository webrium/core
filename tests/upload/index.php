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
  $images = new Upload('image-1');

  // die;

  echo "exists : ".( $images->exists()?'true':'false') ."<br>";
  echo "count  : ". $images->count() ."<br>";
  echo "<br> name:".$images->getClientOriginalName();

  // $images->each(function ($file)
  // {
  //   echo "string";
  //   echo $file->getClientOrginalName()."<br>";
  // });
  // foreach ($images->get() as $key => $file) {
  //   echo $file->getClientOrginalName()."<br>";
  // }

});

Route::any('get/array-files', function ()
{
  $images = new Upload('image');

  // die;

  echo "exists : ".( $images->exists()?'true':'false') ."<br>";
  echo "count  : ". $images->count() ."<br>";
  // echo "<br> json:". json_encode($images->get());

  // $images->each(function ($file)
  // {
  //   echo $file->getClientOrginalName()."<br>";
  // });
  foreach ($images->get() as $key => $file) {
    echo $file->getClientOriginalName()."<br>";
  }

   // return 'end';
});
