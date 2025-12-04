
<div align="center">

[![Latest Stable Version](http://poser.pugx.org/webrium/core/v?style=for-the-badge)](https://packagist.org/packages/webrium/core) [![Total Downloads](http://poser.pugx.org/webrium/core/downloads?style=for-the-badge)](https://packagist.org/packages/webrium/core) [![Latest Unstable Version](http://poser.pugx.org/webrium/core/v/unstable?style=for-the-badge)](https://packagist.org/packages/webrium/core) [![License](http://poser.pugx.org/webrium/core/license?style=for-the-badge)](https://packagist.org/packages/webrium/core)

</div>

## Webrium Core

Webrium Core has a set of features that make site development simpler and faster. Core is used to develop the Webrium framework, but if needed, all or part of its features can be used in other projects.
Webrium Core includes facilities such as routes, file upload and download, session management, etc



## 1) install Webrium Core
```
composer require webrium/core
```

## 2) Create the `index.php` file

index.php
```PHP
<?php

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Import required classes
use Webrium\App;
use Webrium\Debug;
use Webrium\Route;

// Enable error display for debugging
Debug::enableErrorDisplay(true);

// Disable error logging to file
Debug::enableErrorLogging(false);

// Set application root path
App::setRootPath(__DIR__);

// Define home route
Route::get('/', function () {
    return ['message' => 'Hello World'];
});

// Start routing
Route::run();
```

## 3) Create the `.htaccess` file in app

```
AddDefaultCharset UTF-8

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php?_url=/$1 [QSA,L]
</IfModule>
```

### Try it now 🎉




