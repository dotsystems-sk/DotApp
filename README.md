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

The dotApp instance is globally accessible, allowing you to interact with the framework's core components easily. To access the dotApp object, use the DotApp class as follows:

```php
use \Dotsystems\App\DotApp;

// Get the dotApp instance
$dotApp = DotApp::DotApp();

// Use the instance to call framework methods
$dotApp->router->get('/', fn($request) => 'Hello World');
```

This approach provides a convenient way to work with dotApp throughout your application, enabling you to call methods like $dotApp->router->get(), $dotApp->db->q(), and more.

## What's New ✨

### Version 1.7 Released

- **NEW: Testing with Tester Class** (2025-06-22): Lightweight unit and integration testing for modules and core.
- **NEW: FastSearch Library** (2025-06-13): Unified search interface for Elasticsearch, OpenSearch, Meilisearch, Algolia, and Typesense.
- **NEW: Cache Library** (2025-06-13): Driver-agnostic caching with file-based and Redis support.
- **NEW: Centralized Configuration with Config Facade** (2025-04-11): Unified configuration management.
- **NEW: Session Drivers** (2025-04-11): Five built-in drivers (Default, File, File2, DB, Redis) for flexible session management.
- **NEW: Router Facade** (2025-04-11): Alias for $dotApp->router.
- **NEW: DB Facade** (2025-04-11): Alias for $dotApp->db.
- **NEW: Request Facade** (2025-04-11): Alias for $dotApp->request.

## 👥 Installation

There are three ways to install dotApp:

1. **Using Composer** (New!):  

   Install dotApp directly into your current directory using Composer:

   ```bash
   composer create-project dotsystems/dotapp ./
   ```

   This will download the latest version of dotApp and set up the project structure in your current directory.

2. **Using DotApper CLI** (Recommended):  

   Download the dotapper.php file from the [dotApp repository](https://github.com/dotsystems-sk/dotapp), place it in your project directory, and run:

   ```bash
   php dotapper.php --install
   ```

3. **Using Git Clone**:  

   Clone the repository to your project directory:

   ```bash
   git clone https://github.com/dotsystems-sk/dotapp.git ./
   ```

✅ After installation, you can freely use `composer require` to install additional libraries as needed.

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

Main settings are located in app/config.php. Example:

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

# Run tests
php dotapper.php --test # All tests (core)
php dotapper.php --test-modules # Module tests only
php dotapper.php --module=Blog --test # Tests for Blog module
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
  --test                            Run all core tests
  --test-modules                    Run all module tests (no core tests)
  --module=<module_name> --test     Run tests for a specific module
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