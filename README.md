# Create Micro Web App By Webrium-core

1) install core
```
composer require webrium/core
```
2) create app Directory

3) create index.php in to the app

index.php
```PHP
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use webrium\core\App;
use webrium\core\Debug;
use webrium\core\Route;

Debug::displayErrors(true);

// init index path
App::root(__DIR__);

Route::get('', function ()
{
  return ['message'=>'successfully'];
});

```

4) create .htaccess file in to the app

.htaccess
```
AddDefaultCharset UTF-8

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php?_url=/$1 [QSA,L]
</IfModule>
```


