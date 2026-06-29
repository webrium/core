<div align="center">
  <img src="https://repository-images.githubusercontent.com/265541994/1532aafa-589a-40fc-914b-ee9b8c9ab13f" alt="Webrium Core Cover" />

  # Webrium Core
  ### The Lightweight Engine for Modern PHP Applications

  [![Latest Stable Version](http://poser.pugx.org/webrium/core/v?style=for-the-badge)](https://packagist.org/packages/webrium/core)
  [![Total Downloads](http://poser.pugx.org/webrium/core/downloads?style=for-the-badge)](https://packagist.org/packages/webrium/core)
  [![License](http://poser.pugx.org/webrium/core/license?style=for-the-badge)](https://packagist.org/packages/webrium/core)

  **Fast · Modular · Elegant**

  [**webrium.dev**](https://webrium.dev) · [Documentation](https://webrium.dev/docs/v5/core/introduction) · [GitHub](https://github.com/webrium)
</div>

---

## 🌟 Overview

**Webrium Core** is a high-performance PHP component library designed to simplify web development. While it serves as the backbone of the [Webrium Framework](https://github.com/webrium/webrium), it is built to be completely **standalone** — drop it into any PHP project and use only what you need.

It provides routing, controllers, requests/responses, sessions, validation, file uploads, an HTTP client, JWT, hashing, events, filesystem helpers, localization, and a unified error handler — with no required dependencies beyond PHP itself.

## 🚀 Quick Start

### 1. Installation

```bash
composer require webrium/core
```

### 2. Basic Setup (`index.php`)

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Webrium\App;
use Webrium\Route;

App::initialize(__DIR__);

Route::get('/', fn() => "Welcome to Webrium Core! 🚀");

App::run();
```

Then run:

```bash
php -S 127.0.0.1:8000 index.php
```

---

## 📚 Documentation

The complete documentation for Webrium Core lives at **[webrium.dev/docs/v5/core](https://webrium.dev/docs/v5/core/introduction)**. It covers every component the package ships with:

- **Routing** — defining routes, groups, middleware, named routes, typed parameters
- **Controllers** — class structure, `boot()` / `teardown()` lifecycle hooks
- **Requests & Responses** — reading input, sending responses, headers, CORS, redirects
- **Sessions** — sessions, flash messages, validation errors, old input
- **Validation** — fluent input validation
- **File Uploads** — secure uploads with safe defaults
- **HTTP Client** — calling external APIs
- **JWT** — issuing and verifying signed tokens
- **Hashing** — passwords, HMACs, tokens, UUIDs
- **Events** — publish/subscribe system
- **Filesystem** — `File` and `Directory` utilities
- **Localization** — file-based translations
- **Error Handling** — unified handling of errors, exceptions, and fatal shutdowns
- **Helper Functions** — the complete reference of global helpers

The same documentation is also available as plain Markdown in the **[webrium/docs](https://github.com/webrium/docs)** repository.

---

## Part of the Webrium Ecosystem

Webrium Core is one of four packages that make up the full [Webrium Framework](https://github.com/webrium/webrium):

- **[`webrium/core`](https://github.com/webrium/core)** — this package
- **[`webrium/foxdb`](https://github.com/webrium/foxdb)** — query builder, ORM, migrations, seeders
- **[`webrium/view`](https://github.com/webrium/view)** — Blade-compatible templating engine with hybrid static caching
- **[`webrium/console`](https://github.com/webrium/console)** — the `webrium` CLI toolkit

Each is independently usable, except `webrium/console` which is framework-coupled.

---

## License

Webrium Core is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
