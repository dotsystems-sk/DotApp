# EX-D01 — Complete DACore admin module skeleton

End-to-end: scaffold, routes, rights middleware, installer wiring. Rules: [30](../30-DACORE-OVERVIEW.md), [32](../32-DACORE-RIGHTS.md), [35](../35-DACORE-INSTALL.md).

**Never touch `app/modules/DACore/`.** Scaffold **your** module (`Shop` below). DACore updates overwrite that folder; extras and patches disappear. Use `DotApp::call("DACore:…")` only.

## 1. Scaffold

```powershell
Set-Location "path\to\project-root"
php dotapper.php --create-module=Shop
php dotapper.php --module=Shop --create-controller=Admin
php dotapper.php --module=Shop --create-controller=AITools
php dotapper.php --module=Shop --create-middleware=Rights
```

## 2. `module.init.php`

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\Response;
use Dotsystems\App\Parts\Router;
use Dotsystems\App\Parts\Translator;

class Module extends \Dotsystems\App\Parts\Module
{
    public function initialize($dotApp)
    {
        // --- config defaults with fallbacks (never assume config.php was filled) ---
        Config::module('Shop', 'currency') ?? Config::module('Shop', 'currency', 'EUR');
        Config::module('Shop', 'itemsPerPage') ?? Config::module('Shop', 'itemsPerPage', 20);

        Translator::loadLocaleFile('Shop:sk_sk.json', 'sk_sk');

        $admin = Config::module('DACore', 'prefixUrl') . '/shop-admin';

        // Admin routes only make sense for a logged-in user.
        if (Auth::isLogged() === true) {
            $viewRights = ['dotapp.root', 'Shop.administrator', 'Shop.items.view'];
            $editRights = ['dotapp.root', 'Shop.administrator', 'Shop.items.edit'];

            Router::get($admin . '/items', 'Shop:Admin@items!')
                ->before(function ($request) use ($viewRights) {
                    return DotApp::call('#Shop:Rights@check!', $request, $viewRights);
                });

            Router::get($admin . '/items/{id:i}', 'Shop:Admin@itemEdit!')
                ->before(function ($request) use ($editRights) {
                    return DotApp::call('#Shop:Rights@check!', $request, $editRights);
                });

            Router::post($admin . '/items/save', 'Shop:Admin@itemSave!', Router::STATIC_ROUTE)
                ->before(function ($request) use ($editRights) {
                    if ($request->crcCheck() === false) {
                        return new Response(403, DotApp::call(Config::module('DACore', 'error403Page')));
                    }
                    return DotApp::call('#Shop:Rights@check!', $request, $editRights);
                });

            Router::post($admin . '/items/list', 'Shop:Admin@itemsList!', Router::STATIC_ROUTE)
                ->before(function ($request) use ($viewRights) {
                    if ($request->crcCheck() === false) {
                        return new Response(403, DotApp::call(Config::module('DACore', 'error403Page')));
                    }
                    return DotApp::call('#Shop:Rights@check!', $request, $viewRights);
                });
        }
    }

    public function initializeRoutes()
    {
        // Lazy-load this module only for its own admin URLs.
        return [Config::module('DACore', 'prefixUrl') . '/shop-admin', Config::module('DACore', 'prefixUrl') . '/shop-admin/*'];
    }

    public function initializeCondition($routeMatch)
    {
        return $routeMatch;
    }
}

new Module($dotApp);
```

## 3. `module.listeners.php`

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\DotApp;

class Listeners extends \Dotsystems\App\Parts\Listeners
{
    public function register($dotApp)
    {
        // Feed module context into the DACore AI chat.
        $dotApp->on('DACore.ai.chat.active', 'Shop:AITools@addSystemContext');
        $dotApp->on('DACore.permissions.refresh', 'Shop:AITools@addSystemContext');
    }
}

new Listeners($dotApp);
```

**MUST** also keep identical copies at `init/module.init.php` and `init/module.listeners.php` ([35](../35-DACORE-INSTALL.md) §5). Update those copies whenever you change the live files.

## 4. `Middleware/Rights.php`

Copy the class verbatim from [32-DACORE-RIGHTS.md](../32-DACORE-RIGHTS.md) section 3 — it adds the wildcard support that `Auth::can()` lacks and returns the DACore 403 page.

## 5. `Controllers/Admin.php` (page + save)

```php
<?php
namespace Dotsystems\App\Modules\Shop\Controllers;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Logger;
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\Response;
use Dotsystems\App\Parts\Translator;

class Admin extends \Dotsystems\App\Parts\Controller
{
    public static function items($request)
    {
        $perPage = (int) (Config::module('Shop', 'itemsPerPage') ?? 20);
        $page = 1;

        $result = DB::module('RAW')->q(function ($qb) {
            $qb->select(['id', 'title', 'sku', 'price', 'active'])
               ->from('shop_items')
               ->orderBy('id', 'DESC');
        })->paginate($perPage, $page);

        $baseUrl = Config::module('DACore', 'prefixUrl') . '/shop-admin';

        $links = DotApp::call(
            'DACore:Page@paginate!',
            $result['current_page'],
            $result['last_page'],
            null,
            function ($type, $pageNo, $label, $state, $href) {
                if ($type === 'ellipsis') {
                    return '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                $off = ($state === 'active' || $state === 'disabled') ? ' disabled' : '';
                return '<li class="page-item ' . $state . '"><button type="button" class="page-link js-shop-page" data-page="'
                    . (int) $pageNo . '"' . $off . '>' . $label . '</button></li>';
            }
        );

        $html = Renderer::new()
            ->module('Shop')
            ->setLayout('admin/items', 'admin/empty')
            ->setLayoutVar('items', $result['data'])
            ->setLayoutVar('links', $links)
            ->setLayoutVar('total', $result['total'])
            ->setLayoutVar('baseUrl', $baseUrl)
            ->renderLayout();

        if ($html === '') {
            Logger::use()->error('Shop admin items layout empty');
            return new Response(500, 'Template error');
        }

        return static::call(
            'DACore:Page@withMenu!',
            Translator::trans('Items'),
            $html,
            [],
            ['/assets/modules/Shop/css/admin.css'],
            ['/assets/modules/Shop/js/admin-items.js'],
            ''
        );
    }

    public static function itemsList($request)
    {
        try {
            $perPage = (int) (Config::module('Shop', 'itemsPerPage') ?? 20);
            $body = $request->data(true)['data'] ?? [];
            $page = max(1, (int) ($body['page'] ?? 1));

            $result = DB::module('RAW')->q(function ($qb) {
                $qb->select(['id', 'title', 'sku', 'price', 'active'])
                   ->from('shop_items')
                   ->orderBy('id', 'DESC');
            })->paginate($perPage, $page);

            $links = DotApp::call(
                'DACore:Page@paginate!',
                $result['current_page'],
                $result['last_page'],
                null,
                function ($type, $pageNo, $label, $state, $href) {
                    if ($type === 'ellipsis') {
                        return '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    $off = ($state === 'active' || $state === 'disabled') ? ' disabled' : '';
                    return '<li class="page-item ' . $state . '"><button type="button" class="page-link js-shop-page" data-page="'
                        . (int) $pageNo . '"' . $off . '>' . $label . '</button></li>';
                }
            );

            $html = Renderer::new()
                ->module('Shop')
                ->setLayout('admin/items-inner', 'admin/empty')
                ->setLayoutVar('items', $result['data'])
                ->setLayoutVar('links', $links)
                ->renderLayout();

            if ($html === '') {
                Logger::use()->error('Shop admin items-inner layout empty');
                return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Template error'], 500);
            }

            return DotApp::DotApp()->ajaxReply(['status' => 1, 'html' => $html], 200);
        } catch (\Throwable $e) {
            Logger::use()->error('Shop itemsList failed', ['msg' => $e->getMessage()]);
            return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Server error'], 500);
        }
    }

    public static function itemSave($request)
    {
        try {
            $answer = $request->form(
                ['POST'],
                'saveItem',
                function ($request) {
                    $data = $request->data(true)['data'] ?? [];
                    $title = trim((string) ($data['title'] ?? ''));
                    if ($title === '') {
                        return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Title is required']];
                    }

                    $newId = null;
                    DB::module('RAW')->q(function ($qb) use ($title, $data) {
                        $qb->insert('shop_items', [
                            'title' => $title,
                            'sku' => (string) ($data['sku'] ?? ''),
                            'price' => (float) ($data['price'] ?? 0),
                            'active' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    })->execute(
                        function ($r, $db, $exec) use (&$newId) {
                            $newId = $exec['insert_id'] ?? $db->inserted_id();
                        },
                        function ($error) { Logger::use()->error('item insert', $error); }
                    );

                    if ($newId === null) {
                        return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Save failed']];
                    }
                    return ['code' => 200, 'body' => ['status' => 1, 'id' => $newId]];
                },
                function ($request, $name) {
                    return ['code' => 403, 'body' => ['status' => 0, 'message' => 'Invalid signature']];
                }
            );

            if (!is_array($answer) || !isset($answer['body'])) {
                return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Rejected'], 400);
            }
            return DotApp::DotApp()->ajaxReply($answer['body'], $answer['code']);
        } catch (\Throwable $e) {
            Logger::use()->error('Shop itemSave failed', ['msg' => $e->getMessage()]);
            return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Server error'], 500);
        }
    }
}
```

`crcCheck()` already ran in the route hook, so the handler starts with `form()`. Full error-handling rationale: [18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md).

## 6. Installer

Copy [EX-D04-dacore-installer.md](EX-D04-dacore-installer.md) / [35](../35-DACORE-INSTALL.md). Use **`dainstall.php`**, keep `init/` copies of `module.init.php` and `module.listeners.php`. Do not use `install.php`. **Do not do this inside `app/modules/DACore/`** — that is the host, not this plug-in module.

## 7. Resulting file layout

```
app/modules/Shop/
  module.init.php
  module.listeners.php
  Installation.php
  dainstall.php                    (DACore installer — NOT install.php)
  init/
    module.init.php                (copy of live init — keep in sync)
    module.listeners.php
  AI_RULES.md                      (generated by dotapper)
  Controllers/Admin.php
  Controllers/AITools.php
  Middleware/Rights.php
  views/layouts/admin/items.layout.php
  views/layouts/admin/items-inner.layout.php
  views/layouts/admin/empty.layout.php
  assets/css/admin.css
  assets/js/admin-items.js
  translations/sk_sk.json
  tests/AdminTest.php
```
