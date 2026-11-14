# EX-08 — Config secrets + module fallbacks

## Generate keys (PowerShell / PHP CLI)

```powershell
php -r "foreach (['c_enc_key','rm_key','rmrcm_key'] as $k) echo $k.': '.bin2hex(random_bytes(32)).PHP_EOL;"
```

## app/config.php

```php
Config::set('app', 'name', 'MyUniqueApp');
Config::set('app', 'c_enc_key', 'PASTE_HEX');
Config::set('app', 'rm_key', 'PASTE_HEX');
Config::set('app', 'rmrcm_key', 'PASTE_HEX');
Config::session('secure', true); // HTTPS

Config::addDatabase('main', '127.0.0.1', 'user', 'pass', 'dbname', 'UTF8', 'MYSQL', 'pdo');

// Optional module overrides
Config::module('Shop', 'enckey', 'PRODUCTION_HEX');
```

`/* @AUTOCONFIG */` does **not** fill secrets automatically.

## Module fallbacks (always)

```php
Config::module('Shop', 'enckey') ?? Config::module('Shop', 'enckey', bin2hex(random_bytes(16)));
Config::module('Shop', 'itemsPerPage') ?? Config::module('Shop', 'itemsPerPage', 20);
```
