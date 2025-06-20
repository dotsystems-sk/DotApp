# dotApp PHP Framework 🚀

Full documentation is available at:  
[https://dotapp.dev/](https://dotapp.dev/)

dotApp is a lightweight, powerful, and scalable PHP framework for modern web applications. It provides fast solutions for routing, templating, and a PHP-JavaScript bridge.  
**Proudly made in Slovakia** 🇸🇰

🔹 **Minimal and efficient**  
🔹 **PSR-4 autoloading support**  
🔹 **Modular architecture**  
🔹 **Flexible templating system**

# Currently Working On 🛠️

We’re actively developing **Connector**, a powerful JS and PHP library integrated into the DotApp framework! Watch our testing demo to see how nodes are beautifully connected with mouse-driven logic, showcasing a stunning backend *and* frontend experience. The JS part is ready, and the PHP part is in progress. Check it out! 🚀

[![Connector Testing Demo](https://img.youtube.com/vi/nmEU7y1HS2Y/maxresdefault.jpg)](https://www.youtube.com/watch?v=nmEU7y1HS2Y)

**Support DotApp’s development!** Be among the first 100 sponsors to earn permanent recognition on [dotapp.dev](https://dotapp.dev). [Sponsor Now](https://github.com/sponsors/dotsystems-sk)

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

- **NEW: `FastSearch` Library**  ( 2025-06-13 )
  A unified search interface supporting Elasticsearch, OpenSearch, Meilisearch, Algolia, and Typesense. Features a consistent API for indexing, searching, and managing documents, with portability across engines. Example:
  ```php
  $search = FastSearch::use('product_search');
  $search->index('products', '123', ['name' => 'Smartphone', 'price' => 599.99]);
  $results = $search->search('products', 'smartphone', ['category' => 'electronics']);
  ```

- **NEW: `Cache` Library** ( 2025-06-13 )
  A driver-agnostic caching system supporting file-based and Redis drivers. Provides methods for storing, retrieving, and managing cached data with contextual support. Example:
  ```php
  $cache = Cache::use('myCache');
  $cache->save('key', ['data' => 'value'], 3600, ['user' => 3]);
  if ($data = $cache->exists('key', ['user' => 3], true)) {
      echo print_r($data, true);
  }
  ```

- **NEW: Centralized Configuration with `Config` Facade**  ( 2025-04-11 )
  Configuration has been centralized using the new `Config` facade, replacing the previous approach of configuring databases and encryption keys directly through the `$dotApp` object. This provides a cleaner, more unified way to manage settings. For example:
  ```php
  // Set encryption key
  Config::app("c_enc_key", md5('SECURE_KEY'));
  alias for:
  Config::set("app", "c_enc_key", md5('SECURE_KEY'));

  // Configure databases
  Config::addDatabase("main", "127.0.0.1", "dotsystems", "dotsystems", "dotsystems", "UTF8", "MYSQL", 'pdo');
  ```

- **NEW: Session Drivers**  ( 2025-04-11 )
  Version 1.7 introduces five built-in session drivers for flexible session management:
  - **`SessionDriverDefault`**: Wraps PHP's `$_SESSION`, configurable with Redis for distributed systems.
  - **`SessionDriverFile`**: File-based storage in `/app/runtime/SessionDriverFile`.
  - **`SessionDriverFile2`**: Stores each session variable as a separate file in `/app/runtime/SessionDriverFile2`.
  - **`SessionDriverDB`**: Database-driven sessions for load-balanced environments.
  - **`SessionDriverRedis`**: High-performance Redis-based sessions.  
  **Important**: Set session configurations (e.g., `lifetime`, `redis_host`) using `Config::session()` *before* defining the driver with `Config::sessionDriver()`. Example:
  ```php
  Config::session("lifetime", 30 * 24 * 3600);
  Config::session("redis_host", "127.0.0.1");
  Config::session("redis_port", 6379);
  Config::session("redis_prefix", "session:");
  Config::sessionDriver("redis", SessionDriverRedis::driver());
  ```

- **NEW: `Router` Facade**  ( 2025-04-11 )
  Alias for `$dotApp->router`. Define routes cleanly with `Router::`:
  ```php
  Router::get('/', fn() => 'Hello World');
  Router::post('/submit', fn() => 'Submitted');
  ```

- **NEW: `DB` Facade**  ( 2025-04-11 )
  Alias for `$dotApp->db`. Perform database operations elegantly with `DB::`:
  ```php
  DB::q(function ($qb) {
      $qb->select('*')->from('users')->where('id', '=', 1);
  })->first();
  ```

- **NEW: `Request` Facade**  ( 2025-04-11 )
  Alias for `$dotApp->request`. Access request data simply with `Request::`:
  ```php
  Request::getPath();
  Request::data();
  ```
## 👥 Installation

There are two ways to install dotApp:

1. **Using DotApper CLI** (Recommended):  
   Download the `dotapper.php` file from the [dotApp repository](https://github.com/dotsystems-sk/dotapp), place it in your project directory, and run:
   ```bash
   php dotapper.php --install
   ```

2. **Using Git Clone**:  
   Clone the repository to your project directory:
   ```bash
   git clone https://github.com/dotsystems-sk/dotapp.git ./
   ```

🚫 **Do not use** `composer require` for installing dotApp, as it uses its own structure and autoloading.  
✅ However, after installation, you can freely use `composer require` to install additional libraries.

## 🚀 Usage

Simple "Hello World" example using dotApp:

```php
// index.php
define('__ROOTDIR__', "/path/to/your/dotapp");
require_once __ROOTDIR__ . '/app/config.php';

$dotApp->router->get('/', function($request) {
    return 'Hello World';
});

$dotApp->run();
```

## ⚙️ Configuration

Main settings are located in `app/config.php`. Example:

```php
use \Dotsystems\App\DotApp;
use \Dotsystems\App\SessionDriverRedis;
use \Dotsystems\App\Config;

$dotApp = new \Dotsystems\App\DotApp();

// Set encryption key
Config::set("app", "c_enc_key", md5('SECURE_KEY'));

// Configure databases
Config::addDatabase("main", "127.0.0.1", "dotsystems", "dotsystems", "dotsystems", "UTF8", "MYSQL", "mysqli");

// Configure session driver
Config::session("lifetime", 30 * 24 * 3600);
Config::session("redis_host", "127.0.0.1");
Config::session("redis_port", 6379);
Config::session("redis_prefix", "session:");
Config::sessionDriver("redis", SessionDriverRedis::driver());

$dotApp->load_modules();
```

## 🛠️ DotApper CLI Tool

DotApper is a command-line utility for managing your dotApp application. Basic usage:

```bash
# Install dotApp
php dotapper.php --install

# Update dotApp core
php dotapper.php --update

# Create a new module
php dotapper.php --create-module=Blog

# List all routes
php dotapper.php --list-routes
```

### All Available Options

```
Usage: php dotapper.php [options]
Options:
  --install                         Install a fresh copy of the dotApp PHP framework
  --update                          Update dotApp core to the latest version
  --create-module=<name>            Create a new module
  --modules                         List all modules
  --module=<module_name> --create-controller=<ControllerName>  Create a new controller
  --module=<module_name> --create-middleware=<MiddlewareName>  Create a new middleware
  --module=<module_name> --create-model=<ModelName>            Create a new model
  --list-routes                     List all defined routes
  --create-htaccess                 Create or recreate a new .htaccess file
  --optimize-modules                Optimize module loading
```

## 🧪 Version Note

This is the **version 1.7 release** of dotApp.  
Older versions may have duplicate function names (lowercase and PascalCase) due to the transition to **PascalCase** for naming, maintaining **backward compatibility**. This has minimal impact on performance.

## 📚 Documentation

Full documentation is available at:  
[https://dotapp.dev/](https://dotapp.dev/)

## 💎 Contact

📧 **Email**: [dotapp@dotapp.dev](mailto:dotapp@dotapp.dev)  
🌐 **Web**: [https://dotapp.dev](https://dotapp.dev)  
🌐 **Company Web**: [https://dotsystems.sk](https://dotsystems.sk)

## 📝 License

dotApp is licensed under the **MIT License**. You must **retain the author's name** in all library headers.  
Additional Permission: The Software may be used for training AI models, provided the copyright notice is retained.