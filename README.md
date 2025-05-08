# dotApp PHP Framework 🚀

Full documentation is available at:  
[https://dotapp.dev/](https://dotapp.dev/)

dotApp is a lightweight, powerful, and scalable PHP framework for modern web applications. It provides fast solutions for routing, templating, and a PHP-JavaScript bridge.  
**Proudly made in Slovakia** 🇸🇰

🔹 **Minimal and efficient**  
🔹 **PSR-4 autoloading support**  
🔹 **Modular architecture**  
🔹 **Flexible templating system**

## Getting Started

The `dotApp` instance is globally accessible, allowing you to interact with the framework's core components easily. To access the `dotApp` object, use the `DotApp` class as follows:

```php
use \Dotsystems\App\DotApp;

// Get the dotApp instance
$dotApp = DotApp::DotApp();

// Use the instance to call framework methods
$dotApp->router->get('/', fn($request) => 'Hello World');
```

This approach provides a convenient way to work with `dotApp` throughout your application, enabling you to call methods like `$dotApp->router->get()`, `$dotApp->db->q()`, and more.

## What's New ✨

Facades (`Router`, `DB`, `Request`) have been introduced to provide a cleaner and more elegant syntax for interacting with core components. Instead of using `$dotApp->component->method`, you can now use `Component::method` for improved readability. The original syntax via the `$dotApp` object remains fully functional, ensuring complete backward compatibility.

- **NEW**: Added `Router` facade, an alias for the `$dotApp->router` object. Use `Router::` for cleaner route definitions without needing the `$dotApp` object!  
  ```php
  Router::get('/', fn() => 'Hello World');
  Router::post('/submit', fn() => 'Submitted');
  Router::get('/', 'page@main'); // Call a controller method
  ```
- **NEW**: Added `DB` facade, an alias for `$dotApp->db`. Use `DB::` instead of `$dotApp->db->` for a cleaner and more elegant syntax when performing database operations like queries, schema management, or transactions.  
  ```php
  DB::driver('pdo', $dotApp);
  DB::q(function ($qb) {
      $qb->select('*')->from('users')->where('id', '=', 1);
  })->first();
  DB::schema(function ($schema) {
      $schema->createTable('posts', function ($table) {
          $table->id();
          $table->string('title');
      });
  });
  ```
- **NEW**: Added `Request` facade, an alias for `$dotApp->request`. Use `Request::` instead of `$dotApp->request->` for a simpler syntax when accessing request data, such as paths, methods, or form submissions.  
  ```php
  Request::getPath(); // Get the current request path
  Request::getMethod(); // Get the HTTP method (e.g., GET, POST)
  Request::data(); // Access request data (e.g., POST or JSON payload)
  Request::form('myForm', fn($request, $name) => 'Form submitted!', fn($request, $name) => 'Invalid form');
  ```

## 👥 Installation

dotApp requires a specific directory structure, so it must be installed via `git clone`:

```bash
git clone https://github.com/dotsystems-sk/dotapp.git ./
```

🚫 **Do not use** `composer require` for installing dotApp, as it uses its own structure and autoloading.  
✅ However, after installation, you can freely use `composer require` to install any additional libraries.

## 🚀 Usage

Simple "Hello World" example using dotApp:

```php
// index.php
define('__ROOTDIR__',"/path/to/your/dotapp"); // __ROOTDIR__ must be defined.
require_once __ROOTDIR__ . '/app/config.php';

// $dotApp is alias for $dotapp in camelCase, you can use either
$dotApp->router->get('/', function($request) {
    return 'Hello World';
});

$dotApp->run();
```

## ⚙️ Configuration

Main settings are located in `app/config.php`.

Example configuration:

```php
// app/config.php

$dotApp = new \Dotsystems\App\DotApp(md5("SECURE_KEY")); // Set a secure key, md5 is used to prolong key if key is short

// Configure databases if you want to use them
// PDO Driver
$dotApp->DB->driver("pdo");
$dotApp->DB->add("main","127.0.0.1","dotsystems","dotsystems","dotsystems","UTF8","MYSQL")->select_db("main");

// Or use MYSQLI Driver:
$dotApp->db->driver("mysqli");
$dotApp->db->add("main","127.0.0.1","dotsystems","dotsystems","dotsystems","UTF8","MYSQL")->select_db("main");

$dotApp->load_modules(); // Enable module system
```

## 🛠️ DotApper CLI Tool

DotApper is a command-line utility included with dotApp that helps you manage your application, including installation, updates, modules, controllers, middleware, and models.

### Basic Usage

```bash
# Install a fresh copy of dotApp
php dotapper.php --install

# Update dotApp core to the latest version (preserves configuration and modules)
php dotapper.php --update

# Create a new module
php dotapper.php --create-module=Blog

# List all modules
php dotapper.php --modules

# Create a controller in a module
php dotapper.php --module=Blog --create-controller=ArticleController

# Create a middleware in a module
php dotapper.php --module=Blog --create-middleware=AuthMiddleware

# Create a model in a module
php dotapper.php --module=Blog --create-model=PostModel

# List all routes
php dotapper.php --list-routes

# Create a new .htaccess file
php dotapper.php --create-htaccess
```

### All Available Options

```
Usage: php dotapper.php [options]
Options:
  --install                         Install a fresh copy of the dotApp PHP framework
  --update                          Update dotApp core to the latest version without overwriting configuration or modules
  --create-module=<name>            Create a new module (e.g., --create-module=Blog)
  --modules                         List all modules
  --module=<module_name> --create-controller=<ControllerName>  Create a new controller in the specified module
  --module=<module_name> --create-middleware=<MiddlewareName>  Create a new middleware in the specified module
  --module=<module_name> --create-model=<ModelName>            Create a new model in the specified module
  --list-routes                     List all defined routes
  --create-htaccess                 Create or recreate a new .htaccess file
  --optimize-modules                Optimize module loading for projects with many modules
```

## 🧪 Version Note

This is the **first public release** of dotApp.  
You might still encounter remnants of older versions in the codebase, such as duplicate function names with both lowercase and PascalCase styles. This is due to the recent transition to **PascalCase** for naming, while maintaining **backward compatibility**.

This does **not impact performance**, as these are just internal references (pointers) to functions and objects, having minimal effect on CPU or memory usage.

## 📚 Documentation

Full documentation is available at:  
[https://dotapp.dev/](https://dotapp.dev/)

## 💎 Contact

📧 **Email**: [dotapp@dotapp.dev](mailto:dotapp@dotapp.dev)  
🌐 **Web**: [https://dotapp.dev](https://dotapp.dev)  
🌐 **Company Web**: [https://dotsystems.sk](https://dotsystems.sk)

## 📝 License

dotApp is licensed under the **MIT License** – you can use, modify, and distribute it freely.  
However, you must **retain the author's name** in all library headers included with dotApp.