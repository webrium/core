
<div align="center">
  <img src="https://repository-images.githubusercontent.com/265541994/1532aafa-589a-40fc-914b-ee9b8c9ab13f" alt="Webrium Core Cover" />

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

---

## 📚 Comprehensive Documentation (Wiki)

Explore the full power of Webrium Core through our detailed guides:

#### 🚀 Core Essentials
*   **[App Initialization](https://github.com/webrium/core/wiki/App)**: Learn about the application structure and boot process.
*   **[Routing System](https://github.com/webrium/core/wiki/route)**: Define and manage clean, RESTful routes easily.

#### 🌐 Request & Response
*   **[URL Helpers](https://github.com/webrium/core/wiki/Url)**: Manage and generate dynamic URLs effortlessly.
*   **[Header Management](https://github.com/webrium/core/wiki/Header)**: Control HTTP headers and responses.
*   **[HTTP Client](https://github.com/webrium/core/wiki/http-client)**: Perform outgoing requests with a simple interface.

#### 🛡️ Security & Validation
*   **[Data Validator](https://github.com/webrium/core/wiki/From-Validator)**: Robust tools for validating user input and forms.
*   **[JWT Integration](https://github.com/webrium/core/wiki/JWT-Documentation)**: Secure your APIs with JSON Web Tokens.
*   **[Hash Utilities](https://github.com/webrium/core/wiki/Hash-Class-Documentation)**: Secure hashing for sensitive information.

#### 🛠️ Files & Utilities
*   **[File Upload](https://github.com/webrium/core/wiki/Upload)**: A streamlined way to handle file uploads safely.

---

## 🚀 Quick Start

### 1. Installation
Get started via Composer:
```bash
composer require webrium/core

```

### 2. Basic Setup (index.php)

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Webrium\App;
use Webrium\Debug;
use Webrium\Route;

Debug::enableErrorDisplay(true);
Debug::initialize();

App::initialize(__DIR__);

Route::get('/', function() {
    return "Welcome to Webrium Core! 🚀";
});

App::run();

```
