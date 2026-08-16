# EX-03 — Module scaffold + routes + fallbacks

```powershell
Set-Location "path\to\project-root"
php .\dotapper.php --create-module=Shop
php .\dotapper.php --module=Shop --create-controller=Home
php .\dotapper.php --module=Shop --create-middleware=AuthGate
```

## module.init.php (trimmed)

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\Parts\Router;
use Dotsystems\App\Parts\Config;

class Module extends \Dotsystems\App\Parts\Module
{
    public function initialize($dotApp)
    {
        Config::module('Shop', 'prefix') ?? Config::module('Shop', 'prefix', '/shop');
        Config::module('Shop', 'enckey') ?? Config::module('Shop', 'enckey', bin2hex(random_bytes(16)));

        $p = Config::module('Shop', 'prefix');

        Router::get($p . '/', 'Shop:Home@index!', Router::STATIC_ROUTE);
        Router::get($p . '/item/{id:i}', 'Shop:Home@item!');
        Router::post($p . '/contact', 'Shop:Contact@save!', Router::STATIC_ROUTE);
    }

    public function initializeRoutes()
    {
        return ['/shop', '/shop/*'];
    }

    public function initializeCondition($routeMatch)
    {
        return $routeMatch;
    }
}

new Module($dotApp);
```

User overrides in `app/config.php`:

```php
Config::module('Shop', 'prefix', '/store');
Config::module('Shop', 'enckey', 'PRODUCTION_HEX_SECRET');
```
