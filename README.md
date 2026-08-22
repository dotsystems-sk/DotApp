# DotApp PHP Framework 2.0

Full documentation is available at:  

[https://dotapp.dev/](https://dotapp.dev/)

**DotApp PHP Framework 2.0 is released — and it is fully AI READY.** After a long stretch of demanding real-world use, the framework is ready for production: internals are tighter, bugs found under heavy load are gone, and the AI rulebook (`AIRULES/` + `AIRULES-DACORE/`) was stress-tested in **Cursor** with **Grok 4.5** and **Grok 4.6**. The apps those sessions produced are secure, snappy, and ready to ship — even though these models stay cheap to run. Documentation is being prepared on [dotapp.dev](https://dotapp.dev/) — that remains the official site.

DotApp is an ultra-fast, powerful, and scalable PHP framework for modern web applications. It stays lean yet handles very large apps, with clean structure that's easy for humans, vibe coders, and AI assistants to read, learn, and build on. It ships with a built-in Bridge for seamless PHP↔JS calls and an ultra-light reactive frontend library, alongside fast routing and templating.

**DACore**, the official administration module, is available **free** — login, 2FA, rights, menus, and an admin shell that your own modules plug into. Details stay on [dotapp.dev](https://dotapp.dev/).

**Proudly made in Slovakia** 🇸🇰

🔹 **Minimal and efficient**

🔹 **PSR-4 autoloading support**

🔹 **Modular architecture**

🔹 **Flexible templating system**

🔹 **Fully AI READY** — `AIRULES/` battle-tested in Cursor with Grok 4.5 and 4.6; generated apps stay secure and fast

🔹 **Callable strings** — trailing `!` skips DI; `#` = middleware; `*` = model:

```php
Router::get('/', 'Shop:Home@index!');                    // controller, no DI
Router::post('/save', 'Shop:Home@save!')
    ->before('#Shop:AuthGate@check!');                   // # middleware
$item = DotApp::call('*Shop:Item@find!', $id);          // * model
```

🔹 **DACore admin module** — official admin UI, free

**Support DotApp’s development!** Be among the first 100 sponsors to earn permanent recognition on [dotapp.dev](https://dotapp.dev). [Sponsor Now](https://github.com/sponsors/dotsystems-sk)

## Getting Started

The DotApp instance is globally accessible. Work through facades. Controllers are `public static` methods. A trailing **`!`** on a callable **turns the DI container off** for that call — the preferred path for hot routes. Prefix **`#`** targets **middleware**, **`*`** targets a **model**.

```php
// Static route (exact URL, no pattern matching) + controller, no DI
Router::get('/', 'Shop:Home@index!', Router::STATIC_ROUTE);

// Dynamic param + integer constraint
Router::get('/item/{id:i}', 'Shop:Home@item!');

// POST + middleware gate (# = Middleware class, ! = skip DI)
Router::post('/save', 'Shop:Home@save!')
    ->before('#Shop:AuthGate@check!');

// Closures still work when you want them
Router::get(['/', '/home'], fn($request) => 'Welcome!');

// Call a model (*) or another controller from PHP
$item = DotApp::call('*Shop:Item@find!', $id);
$html = DotApp::call('Shop:Home@helper!', $request);
```

Hot-path controller (no injected services — you construct what you need):

```php
public static function index($request)
{
    $renderer = \Dotsystems\App\Parts\Renderer::new();
    return $renderer->module('Shop')
        ->setView('home')
        ->setViewVar('title', 'Hello')
        ->renderView();
}
```

Route: `'Shop:Home@index!'`. Omit the `!` only when the method should receive DI parameters.

## What's New ✨

### Extender — opt-in method replacement (NEW – 2026-08-22)

New core class: **`Dotsystems\App\Parts\Extender`**. A module can **replace** another module’s method for the current request — one handler either owns the result or explicitly defers to the owner’s original logic. This is **not** Events, `module.{mod}.{name}.hook`, or `triggerWithVeto()`.

- **Judge first:** offer Extender on highly replaceable **outputs** (page/block HTML, cart, export) — **not** on every method.
- When the owner opts in: `Extender::exists()` then `Extender::call(...)`. Return an ordinary result, or continue only when `isOriginal()` recognizes the unique `original()` marker. There is no `next()` chain.
- The extending module registers `Extender::extend()` in **`Listeners::register()`** (`module.listeners.php`) before any matching Module initializes.
- Target URLs belong in `Listeners::initializeRoutes()`; the extending Module keeps only its own routes or `[]`. Prefer a `Module:Controller@method!` handler.
- Direct listener registration is canonical. `.loaded` is too late when the target can call the extension point during `initialize()`.
- One replacement per class+method (a duplicate throws). Recursion into the same target throws. Handler exceptions propagate.
- Pass only explicit safe arguments (ids, flags, scalars) — never `$request`, tokens, CRC, or request bodies.

Rules: `AIRULES/12-SERVICES.md` §10. Sample: `AIRULES/examples/EX-17-extender.md`.

### Module loader v2, listener routes, and `Veto` (NEW – 2026-08-22)

The kernel is **done**. Agents and contributors **MUST NOT** edit `app/DotApp.php`, `app/parts/`, `dotapper.php`, or `index.php` — implement features in **your module**.

- **Faster module boot, old maps still work:** `php dotapper.php --optimize-modules` writes `app/modules/modulesAutoLoader.php` **v2** (`$modulesAutoLoaderVersion = 2`) with two independent lists: `$modules` (full `module.init.php` / `initialize()`) and `$listeners` (only `module.listeners.php`). An older file that has only `$modules` still loads.
- **Listeners can have their own URLs:** `Listeners::initializeRoutes()` may return a different prefix list than `Module::initializeRoutes()`. Return `null` (or omit the method) to inherit the module map — that is the compatible default.
- **Listeners register first:** matching `module.listeners.php` files run **before** matching modules initialize, so a subscriber can hear an event without booting its whole admin UI.
- **`triggerWithVeto()` + `Dotsystems\App\Parts\Veto`:** ordinary `Events::trigger()` still **ignores** listener returns (not a veto). The opt-in API stops only when a listener returns `new Veto($code, $message, $details)` and yields `Veto|null`. Name: `module.{mod}.{action}.veto`. Never send `message` / `details` to the browser.

AI rules: `AIRULES/03-MODULES-AND-ROUTING.md` (sleep + listener routes), `AIRULES/12-SERVICES.md` §2, `AIRULES/41-MODULE-HOOKS.md`, sample `AIRULES/examples/EX-16-module-hooks.md`.

### Version 2.0 Released (NEW – 2026-08-18)

This is not a coat of paint. 2.0 is the result of **heavy production use** plus a hard rewrite of how agents are allowed to touch the stack. The rulebook was driven in **Cursor** with **Grok 4.5** and **Grok 4.6**. Apps that came out of those sessions are **secure, snappy, and shippable** — on models that stay cheap to run.

- **Fully AI READY**: First-class support for AI agents. `AIRULES/` (framework) and `AIRULES-DACORE/` (with DACore) tell Cursor / Grok exactly how to write DotApp.
- **`!` skips DI**: `'Shop:Home@index!'` turns the container off for that controller method. Fast, explicit, no mystery injection on hot paths.
- **Callable prefixes**: `#Shop:Gate@check!` = middleware, `*Shop:Item@find!` = model, `Shop:Home@index!` = controller. Same string form on routes, `->before()`, and `DotApp::call()`.
- **Secure forms by default**: `<fo-rm>` + `{{ formName(handler) }}` + CRC — stronger than a plain CSRF token. Row actions (toggle, delete, paginate) use `$dotapp().load()`, not a `<fo-rm>` per button.
- **Encrypted IDs in the browser**: no raw `data-id="7"`. `{{ enc(Shop.item.id): $id }}` on the way out, decrypt + rights check on the way in.
- **Lists that can grow must paginate**: `paginate()` + **AJAX** pager on first ship. No `->all()` dumps, no `?page=` full reload of the admin shell.
- **DSM, not `$_SESSION`**: `DSM::use('Shop')->set/get/delete` for app session state.
- **`$dotapp()` frontend**: live DOM patch + overlay + toast after save. No `location.reload()`. File uploads go through `$dotapp().uploadFile`.
- **DB the DotApp way**: `DB::module('RAW')->q(function ($qb) { ... })->all()|first()|execute()`. Module tables are `{lowercase_modulename}_*` (`Shop` → `shop_items`).
- **DACore admin — free**: login, 2FA, rights, DB-driven menu, page shell, optional AI chat with tools. Your module plugs in via `DotApp::call('DACore:…')`. See [dotapp.dev](https://dotapp.dev/).
- **Production hardening**: bugs found under real load are gone; ORM, QueryBuilder, routing, and the JS stack were cleaned up because they had to survive daily use — not a demo.

### AI READY (2.0)

- **Shipped rulebook**: drop `AIRULES/` next to `index.php` (start at `AIRULES/00-AGENT-CONTRACT.md`). If DACore is installed, copy `AIRULES-DACORE/` as `AIRULES/`.
- **Cursor + Grok 4.5 / 4.6**: stress-tested until generated admin and public UI stayed secure and fast.
- **Cheap models, serious apps**: you do not need the most expensive model in the catalog. Affordable agents follow the rules and still emit real DotApp code.
- **Hard boundaries for agents**: they may edit `app/config.php` and `app/modules/<YourModule>/` — not the kernel, not DACore. That is why the output stays maintainable.

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

# Install a module from Git or registry
php dotapper.php --install-module=https://github.com/vendor/module.git

# Create a new module
php dotapper.php --create-module=Blog

# List all routes
php dotapper.php --list-routes

# Regenerate .htaccess
php dotapper.php --create-htaccess

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
  --install-module=<url|name[:ver]> Install a module (Git URL or name, optional version)
  --prepare-database[=<prefix>]     Prepare database structure (optional table prefix)
  --module=<module_name> --create-controller=<ControllerName>  Create a new controller
  --module=<module_name> --create-middleware=<MiddlewareName>  Create a new middleware
  --module=<module_name> --create-model=<ModelName>            Create a new model
  --list-routes                     List all defined routes
  --list-route=<route>              List callbacks for a specific route (e.g., /)
  --create-htaccess                 Create or recreate a new .htaccess file
  --optimize-modules                Optimize module loading
  --test                            Run all core tests
  --test-modules                    Run all module tests (no core tests)
  --module=<module_name> --test     Run tests for a specific module
```

## 🧪 Version Note

This is **DotApp PHP Framework 2.0**: production-ready, **fully AI READY**, with a real agent rulebook, `!` to skip DI, `#` / `*` callables, hardened forms and lists, and the **DACore** admin module available free. The AIRULES pack was stress-tested in Cursor with Grok 4.5 and Grok 4.6 — generated apps stay secure and fast, even on inexpensive models. Full documentation lives at [https://dotapp.dev/](https://dotapp.dev/).

Older versions may have duplicate function names (lowercase and PascalCase) due to the transition to **PascalCase** for naming, maintaining **backward compatibility**. This has minimal impact on performance.

## 📚 Documentation

Full documentation is available at:  

[https://dotapp.dev/](https://dotapp.dev/)

## 💎 Contact

📧 **Email**: [dotapp@dotapp.dev](mailto:dotapp@dotapp.dev)  

🌐 **Web**: [https://dotapp.dev](https://dotapp.dev)  

🌐 **Company Web**: [https://dotsystems.sk](https://dotsystems.sk)

## 📝 License

DotApp PHP Framework 2.0 is licensed under the **MIT License**. You must **retain the author's name** in all library headers.  

Additional Permission: The Software may be used for training AI models, provided the copyright notice is retained.