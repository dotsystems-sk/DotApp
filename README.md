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

### Version 1.7 Released
The latest release, **version 1.7**, introduces significant improvements to the configuration system and session management. Key changes include:

- **NEW: Centralized Configuration with `Config` Facade**  
  Configuration has been centralized using the new `Config` facade, replacing the previous approach of configuring databases and encryption keys directly through the `$dotApp` object. This provides a cleaner, more unified way to manage settings. For example:
  ```php
  // Set encryption key
  Config::set("app", "c_enc_key", md5('SECURE_KEY'));

  // Configure databases
  Config::addDatabase("<name for your database for selectDB>", "host", "username", "password", "database_name", "encoding", "type", "driver");
  Config::addDatabase("main", "127.0.0.1", "dotsystems", "dotsystems", "dotsystems", "UTF8", "MYSQL", 'pdo');
  ```

- **NEW: Session Drivers**  
  Version 1.7 introduces four built-in session drivers for flexible session management, allowing developers to choose the best option for their application's needs:
  - **`SessionDriverDefault::driver()`**: A wrapper around PHP's native `$_SESSION`. Can be configured with Redis as a session handler for distributed systems (e.g., load balancers).
  - **`SessionDriverFile`**: Stores sessions as files in `/app/runtime/SessionDriverFile`. Independent of PHP's session system, suitable for simple file-based storage.
  - **`SessionDriverFile2`**: Stores sessions in `/app/runtime/SessionDriverFile2`, with each session variable saved as a separate file. Optimized for scenarios with many or large session variables.
  - **`SessionDriverDB`**: Manages sessions via a database, ideal for load-balanced environments requiring a shared session store.  
  Example configuration for session drivers:
  ```php
  Config::sessionDriver("default", SessionDriverDefault::driver());
  Config::session("lifetime", 30 * 24 * 3600); // Set session lifetime to 30 days
  Config::session("rm_autologin", true);
  ```

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

There are two ways to install dotApp:

1. **Using DotApper CLI** (Recommended):  
   Download the `dotapper.php` file from the [dotApp repository](https://github.com/dotsystems-sk/dotapp), place it in your project directory, and run the following command to install dotApp:
   ```bash
   php dotapper.php --install
   ```

2. **Using Git Clone**:  
   Clone the entire repository to your project directory:
   ```bash
   git clone https://github.com/dotsystems-sk/dotapp.git ./
   ```

🚫 **Do not use** `composer require` for installing dotApp, as it uses its own structure and autoloading.  
✅ However, after installation, you can freely use `composer require` to install any additional libraries.

## 🚀 Usage

Simple "Hello World" example using dotApp:

```php
// index.php
define('__ROOTDIR__', "/path/to/your/dotapp"); // __ROOTDIR__ must be defined.
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
use \Dotsystems\App\DotApp;
use \Dotsystems\App\SessionDriverDefault;
use \Dotsystems\App\SessionDriverFile;
use \Dotsystems\App\SessionDriverFile2;
use \Dotsystems\App\SessionDriverDB;
use \Dotsystems\App\Config;

$dotApp = new \Dotsystems\App\DotApp();

// Set encryption key
Config::set("app", "c_enc_key", md5('SECURE_KEY'));

// Configure databases
Config::addDatabase("<name for your database for selectDB>", "host", "username", "password", "database_name", "encoding", "type", "driver");
// Example:
// Config::addDatabase("main", "127.0.0.1", "dotsystems", "dotsystems", "dotsystems", "UTF8", "MYSQL", "mysqli");

// Configure session driver
Config::sessionDriver("default", SessionDriverDefault::driver()); // Options: SessionDriverFile, SessionDriverFile2, SessionDriverDB
Config::session("lifetime", 30 * 24 * 3600); // Set session lifetime to 30 days
Config::session("rm_autologin", true);

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

This is the **version 1.7 release** of dotApp.  
You might still encounter remnants of older versions in the codebase, such as duplicate function names with both lowercase and PascalCase styles. This is due to the recent transition to **PascalCase** for naming, while maintaining **backward compatibility**. This does **not impact performance**, as these are just internal references (pointers) to functions and objects, having minimal effect on CPU or memory usage.

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