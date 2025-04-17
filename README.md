# dotApp PHP Framework 🚀

dotApp is a lightweight, powerful, and scalable PHP framework for modern web applications. It provides fast solutions for routing, templating, and a PHP-JavaScript bridge.

🔹 **Minimal and efficient**  
🔹 **PSR-4 autoloading support**  
🔹 **Modular architecture**  
🔹 **Flexible templating system**

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
$dotApp->get('/', function($request) {
    return 'Hello World';
});

$dotApp->run();
```

## ⚙️ Configuration

Main settings are located in `app/config.php`.

Example configuration:

```php
// app/config.php

$dotApp->enc_key(md5("SECURE_KEY")); // Set a secure key

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

DotApper is a command-line utility included with dotApp that helps you manage modules and controllers.

### Basic Usage

```bash
# Create a new module
php dotapper.php --create-module=Blog

# List all modules
php dotapper.php --modules

# Create a controller in a module
php dotapper.php --module=Blog --create-controller=ArticleController

# List all routes
php dotapper.php --list-routes
```

## 🧪 Version Note

This is the **first public release** of dotApp.  
You might still encounter remnants of older versions in the codebase, such as duplicate function names with both lowercase and PascalCase styles. This is due to the recent transition to **PascalCase** for naming, while maintaining **backward compatibility**.

This does **not impact performance**, as these are just internal references (pointers) to functions and objects, having minimal effect on CPU or memory usage.

## 📚 Documentation

Full documentation is available at:  
[https://dotapp.dotsystems.sk/documentation/intro](https://dotapp.dotsystems.sk/documentation/intro/eng)

## 💎 Contact

📧 **Email**: [dotapp@dotsystems.sk](mailto:dotapp@dotsystems.sk)  
🌐 **Web**: [https://dotsystems.sk](https://dotsystems.sk)

## 📝 License

dotApp is licensed under the **MIT License** – you can use, modify, and distribute it freely.  
However, you must **retain the author's name** in all library headers included with dotApp.

---

# dotApp PHP Framework 🚀

dotApp je ľahký, výkonný a škálovateľný PHP framework pre moderné webové aplikácie. Poskytuje rýchle riešenia pre routing, templating a PHP-JavaScript bridge.

🔹 **Minimalistický a efektívny**  
🔹 **Podpora PSR-4 autoloadingu**  
🔹 **Modulárna architektúra**  
🔹 **Flexibilné templating riešenie**

## 👥 Inštalácia

dotApp vyžaduje špecifickú adresárovú štruktúru, preto ho musíš nainštalovať cez `git clone`:

```bash
git clone https://github.com/dotsystems-sk/dotapp.git ./
```

🚫 **Nepoužívaj** `composer require` na inštaláciu dotApp, pretože má vlastnú adresárovú štruktúru a autoloading.  
✅ Po nainštalovaní dotApp však môžeš používať `composer require` na pridávanie ďalších knižníc a závislostí.

## 🚀 Použitie

Príklad jednoduchého "Hello World" s dotApp:

```php
// index.php
define('__ROOTDIR__',"/path/to/your/dotapp"); // __ROOTDIR__ musí byť definovaný
require_once __ROOTDIR__ . '/app/config.php';

// $dotApp je alias pre $dotapp v camelCase, môžeš použiť ktorýkoľvek
$dotApp->get('/', function($request) {
    return 'Hello World';
});

$dotApp->run();
```

## ⚙️ Konfigurácia

Hlavné nastavenia nájdeš v súbore `app/config.php`.

Príklad konfigurácie:

```php
// app/config.php

$dotApp->enc_key(md5("SECURE_KEY")); // Nastav bezpečný kľúč

// Nastav si databázy ak ich chceš využívať
// Driver PDO
$dotApp->DB->driver("pdo");
$dotApp->DB->add("main","127.0.0.1","dotsystems","dotsystems","dotsystems","UTF8","MYSQL")->select_db("main");

// Alebo MYSQLI driver:
$dotApp->db->driver("mysqli");
$dotApp->db->add("main","127.0.0.1","dotsystems","dotsystems","dotsystems","UTF8","MYSQL")->select_db("main");

$dotApp->load_modules(); // Povolenie modulového systému
```

## 🛠️ DotApper CLI nástroj

DotApper je konzolový nástroj dodávaný s dotApp, ktorý pomáha pri správe modulov a kontrolérov.

### Základné použitie

```bash
# Vytvorenie nového modulu
php dotapper.php --create-module=Blog

# Zobrazenie všetkých modulov
php dotapper.php --modules

# Vytvorenie kontroléra v module
php dotapper.php --module=Blog --create-controller=ArticleController

# Zobrazenie všetkých routov
php dotapper.php --list-routes
```

## 🧪 Poznámka k verzii

Toto je **prvá verejne dostupná verzia** dotApp.  
V kóde sa preto môžu ešte objaviť zvyšky zo starších verzií, ako napríklad duplicitné názvy funkcií s malými aj veľkými písmenami. Je to z dôvodu nedávneho prechodu na **PascalCase**, pričom bola zachovaná **spätná kompatibilita**.

Nemá to **žiadny dopad na výkon**, pretože ide len o interné smerníky na funkcie a objekty, čo má minimálny vplyv na CPU alebo pamäť.

## 📝 Dokumentácia

Podrobné informácie o použití dotApp nájdeš v [oficiálnej dokumentácii](https://dotapp.dotsystems.sk/documentation/intro).

## 💎 Kontakt

📧 **Email**: [dotapp@dotsystems.sk](mailto:dotapp@dotsystems.sk)  
🌐 **Web**: [https://dotsystems.sk](https://dotsystems.sk)

## 📝 Licencia

dotApp je distribuovaný pod **MIT licenciou** – môžeš ho používať, upravovať a distribuovať bez obmedzení,  
musíš však **ponechať meno autora** v hlavičkách všetkých knižníc, ktoré sú súčasťou dotApp.
