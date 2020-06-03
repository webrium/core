
## Create Micro Web App By webrium-core

1) install core
```
composer require webrium/core
```
2) create the app Directory

3) create the index.php file in app

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
  return ['message'=>'successful'];
});

```

4) create the .htaccess file in app

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

Try it now

Output (http://localhost/app/)

``
{"message":"successful"}
``


