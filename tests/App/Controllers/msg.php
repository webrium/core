<?php
namespace App\Controllers;

use webrium\mysql\DB;

class msg
{

  function show()
  {
    return DB::table('users')->get();
  }

  public function test()
  {
    echo "hello :)";
  }


}
