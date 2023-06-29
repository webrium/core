
<div align="center">

[![Latest Stable Version](http://poser.pugx.org/webrium/core/v?style=for-the-badge)](https://packagist.org/packages/webrium/core) [![Total Downloads](http://poser.pugx.org/webrium/core/downloads?style=for-the-badge)](https://packagist.org/packages/webrium/core) [![Latest Unstable Version](http://poser.pugx.org/webrium/core/v/unstable?style=for-the-badge)](https://packagist.org/packages/webrium/core) [![License](http://poser.pugx.org/webrium/core/license?style=for-the-badge)](https://packagist.org/packages/webrium/core)

</div>

## Webrium Core

Webrium Core has a set of features that make site development simpler and faster. Core is used to develop the Webrium framework, but if needed, all or part of its features can be used in other projects.
Webrium Core includes facilities such as routes, file upload and download, session management, etc


### Documentation :

 - [Route Class Documentation](https://github.com/webrium/core/wiki/Route-Class-Documentation)
 - [Session Class Documentation](https://github.com/webrium/core/wiki/Session-Class-Documentation)
 - [JWT (JSON Web Token) Documentation](https://github.com/webrium/core/wiki/JWT-Documentation)

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

use Webrium\App;
use Webrium\Debug;
use Webrium\Route;

Debug::showErrorsStatus(true);
Debug::writErrorsStatus(false);

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


