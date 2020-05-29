# mysql
MySql Library

<br>

### Install
```
composer require webrium/mysql
```

<br>

```PHP
<?php
require_once __DIR__ . '/../vendor/autoload.php';

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

$get = DB::table('users')->get();

echo json_encode($get);
```
