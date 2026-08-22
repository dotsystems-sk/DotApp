# EX-03 — Module scaffold + routes + fallbacks

**MUST:** tables this module owns are `{lowercase_modulename}_*` (here `shop_*`). Never `items` or `dotapp_*`. See [07-SCHEMA-AND-INSTALL.md](../07-SCHEMA-AND-INSTALL.md) §3.

```powershell
Set-Location "path\to\project-root"
php .\dotapper.php --create-module=Shop
php .\dotapper.php --module=Shop --create-controller=Home
php .\dotapper.php --module=Shop --create-middleware=Gate
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
        Config::module('Shop', 'prefix') ?? Config::module('Shop', 'prefix', '/Shop');
        Config::module('Shop', 'enckey') ?? Config::module('Shop', 'enckey', bin2hex(random_bytes(16)));

        $p = Config::module('Shop', 'prefix');
        $member = $p . '/account';

        $authApi = '/api/v1/auth/Shop';
        $openApi = '/api/v1/noauth/Shop';
        Router::before(['POST'], [$authApi, $authApi . '/*'], '#Shop:Gate@loginAndCrc!');
        Router::before(['POST'], [$openApi, $openApi . '/*'], '#Shop:Gate@crc!');
        Router::before([$member, $member . '/*'], '#Shop:Gate@login!');

        Router::get($p . '/', 'Shop:Home@index!', Router::STATIC_ROUTE);
        Router::get($p . '/item/{id:i}', 'Shop:Home@item!');
        Router::post($openApi . '/contact', 'Shop:Contact@save!', Router::STATIC_ROUTE);

        // Member URLs: MUST register only when logged in — MUST NEVER show this page to anonymous users
        if (\Dotsystems\App\Parts\Auth::isLogged() === true) {
            Router::get($member, 'Shop:Account@index!', Router::STATIC_ROUTE);
        }
    }

    public function initializeRoutes()
    {
        return ['/Shop', '/Shop/*', '/api/v1/auth/Shop', '/api/v1/auth/Shop/*', '/api/v1/noauth/Shop', '/api/v1/noauth/Shop/*'];
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

After a **useful** side-effect (SMS/mail sent, payment, lockout — **not** every save), fire `module.{lowercase_modulename}.{hook_name}.hook` and create **`app/modules/<Module>/.hooks`**. Sample: [EX-16](EX-16-module-hooks.md). Law: [41](../41-MODULE-HOOKS.md).
