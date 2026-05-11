<div align="center">
  <img src="YOUR_LOGO_URL_HERE" alt="Webrium Core Logo" width="120" />

  # Webrium Core
  ### The Lightweight Engine for Modern PHP Applications

  [![Latest Stable Version](http://poser.pugx.org/webrium/core/v?style=for-the-badge)](https://packagist.org/packages/webrium/core)
  [![Total Downloads](http://poser.pugx.org/webrium/core/downloads?style=for-the-badge)](https://packagist.org/packages/webrium/core)
  [![License](http://poser.pugx.org/webrium/core/license?style=for-the-badge)](https://packagist.org/packages/webrium/core)

  **Fast. Modular. Elegant.**
</div>

---

## 🌟 Overview

**Webrium Core** is a high-performance PHP component library designed to simplify web development. While it serves as the backbone of the Webrium Framework, it is built to be completely **standalone**. Whether you're building a microservice or a full-scale web app, Webrium Core provides the essential tools without the bloat.

### Key Features:
* ⚡ **Powerful Routing:** Simple yet flexible route management.
* 🛡️ **Security First:** Built-in JWT and Hash utilities.
* 🌐 **HTTP Mastery:** Advanced Header, URL, and Client management.
* 📁 **File Handling:** Effortless upload and download controllers.
* ✅ **Robust Validation:** Clean and extensible data validation.

---

## 🚀 Quick Start

### 1. Installation
Get started in seconds via Composer:
```bash
composer require webrium/core

```

### 2. Basic Setup (index.php)

Create your entry point and define your first route:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Webrium\App;
use Webrium\Debug;
use Webrium\Route;

// Enable error handling for development
Debug::enableErrorDisplay(true);
Debug::initialize();

// Initialize App path
App::initialize(__DIR__);

// Define a simple route
Route::get('/', function() {
    return "Welcome to Webrium Core! 🚀";
});

App::run();

```

### 3. Server Configuration (.htaccess)

Ensure all requests are handled by your `index.php`:

```apache
AddDefaultCharset UTF-8

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php?_url=/$1 [QSA,L]
</IfModule>

```
