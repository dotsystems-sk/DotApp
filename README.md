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

The dotApp instance is globally accessible, and you can work with it through facades for brevity. A few quick routing examples (single path, array of paths, controller strings, static routes):

```php
// Single route
Router::get('/', fn($request) => 'Hello World');

// Multiple paths share the same handler
Router::get(['/', '/home'], fn($request) => 'Welcome!');

// Controller syntax (module:Controller@method)
Router::get('/users/{id}', 'Users:Profile@show');

// Static route (no pattern matching) example
Router::post('/login', 'Users:Login@loginPost', Router::STATIC_ROUTE);
```

This keeps routes concise while still letting you access other services via facades or `DotApp::DotApp()` when needed.

## What's New ✨

### Version 1.7.2 Released (NEW – 2025-12-25)

- **Dependency Injection helpers**: `DI` wrapper resolves method arguments via the DotApp resolver, and `Injector` facade adds easy `singleton()` / `bind()` registration.
- **Rate Limiter**: Multi-window request limiting with pluggable storage (session by default, custom getter/setter supported) for routes and Bridge endpoints.
- **OTP & QR utilities**: `TOTP` generates Base32 secrets, TOTP codes, and otpauth URIs; `QR` builds PNG/base64 QR codes with styling options.
- **Localization**: Global `translator()` helper with JSON/locale files, runtime locale switching, and placeholder replacement.
- **Email stack**: `Emailer` (SMTP + IMAP/POP3) with attachments, saving to folders, protocol switching, and `Email` facade shortcuts.
- **SMS providers**: `Sms` facade and `SmsProvider` interface to validate numbers, send/receive messages, check status, and set provider-specific config.

### Highlights from 1.7

- Testing with Tester Class: Lightweight unit and integration testing for modules and core.
- FastSearch Library: Unified search interface for Elasticsearch, OpenSearch, Meilisearch, Algolia, and Typesense.
- Cache Library: Driver-agnostic caching with file-based and Redis support.
- Centralized Configuration with Config Facade: Unified configuration management.
- Session Drivers: Five built-in drivers (Default, File, File2, DB, Redis) for flexible session management.
- Router Facade: Alias for $dotApp->router.
- DB Facade: Alias for $dotApp->db.
- Request Facade: Alias for $dotApp->request.

## 👥 Installation

There are three ways to install dotApp:

1. **Using Composer** (New!):  

   Install dotApp directly into your current directory using Composer:

   ```bash
   composer create-project dotsystems/dotapp ./
   ```

   This will download the latest version of dotApp and set up the project structure in your current directory.

2. **Using DotApper CLI** (Recommended):  

   Obtain the `dotapper.php` file and run it to install dotApp. You can either:

   - **Download it manually**: Visit [https://install.dotapp.dev/dotapper.php](https://install.dotapp.dev/dotapper.php), download the file, and place it in your project directory.
   - **Use `wget`**: Run the following command to download `dotapper.php` directly:

     ```bash
     wget https://install.dotapp.dev/dotapper.php
     ```

   Then, execute the installer:

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

Router::get('/', fn($request) => 'Hello World');

DotApp::DotApp()->run();
```

> Route callbacks receive a **locked Request object**; you can return a string (it becomes the response body) or work with `$request->response` if you need full control.

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

This is the **version 1.7.2 release** of dotApp.  

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