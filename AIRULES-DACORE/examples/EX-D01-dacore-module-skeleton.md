# EX-D01 — Complete DACore admin module skeleton

End-to-end: scaffold, routes, rights middleware, installer wiring. Rules: [30](../30-DACORE-OVERVIEW.md), [31](../31-DACORE-MENU.md), [32](../32-DACORE-RIGHTS.md), [35](../35-DACORE-INSTALL.md). **List pager:** [40](../40-DACORE-LIST-PAGER.md), [EX-D08](EX-D08-list-pager.md).

**ASK in chat before scaffolding the menu:** shared nested sidebar (`0` → `2` → `1`, `Page@withMenu` `$menuId` `''`) vs **module-own**. **No answer → shared nested** (this sample). Module-own **only** if the user explicitly chose it. Do not register “Return back”. See [31](../31-DACORE-MENU.md).

**Never touch `app/modules/DACore/` by default** (files or assets). Scaffold **your** module (`Shop` below). **MUST NOT propose** a DACore edit. DACore updates overwrite that folder. Use `DotApp::call("DACore:…")`. Informed exception: [00](../00-AGENT-CONTRACT.md) §1.

## 1. Scaffold

```powershell
Set-Location "path\to\project-root"
php dotapper.php --create-module=Shop
php dotapper.php --module=Shop --create-controller=Admin
php dotapper.php --module=Shop --create-controller=AITools
php dotapper.php --module=Shop --create-middleware=Gate
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

        $admin = rtrim((string) Config::module('DACore', 'prefixUrl'), '/') . '/Shop';
        $authApi = '/api/v1/auth/Shop';
        $openApi = '/api/v1/noauth/Shop';

        Router::before([$admin, $admin . '/*'], '#Shop:Gate@login!');
        Router::before(['POST'], [$authApi, $authApi . '/*'], '#DACore:AuthTest@LoginAndCRC!');
        Router::before(['POST'], [$openApi, $openApi . '/*'], '#DACore:AuthTest@CRC!');

        // Admin URLs MUST NOT stay registered for anonymous users — the page must never render
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

            Router::post($authApi . '/items/save', 'Shop:Admin@itemSave!', Router::STATIC_ROUTE)
                ->before(function ($request) use ($editRights) {
                    return DotApp::call('#Shop:Rights@check!', $request, $editRights);
                });

            Router::post($authApi . '/items/list', 'Shop:Admin@itemsList!', Router::STATIC_ROUTE)
                ->before(function ($request) use ($viewRights) {
                    return DotApp::call('#Shop:Rights@check!', $request, $viewRights);
                });
        }
    }

    public function initializeRoutes()
    {
        // Lazy-load this module only for its own admin URLs.
        $admin = rtrim((string) Config::module('DACore', 'prefixUrl'), '/') . '/Shop';
        return [
            $admin, $admin . '/*',
            '/api/v1/auth/Shop', '/api/v1/auth/Shop/*',
            '/api/v1/noauth/Shop', '/api/v1/noauth/Shop/*',
        ];
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

## 4. `Middleware/Gate.php` + `Middleware/Rights.php`

`Gate@login` is **login only** (403 `Response`). Copy it from [32](../32-DACORE-RIGHTS.md) §3. **MUST NOT** `crcCheck()` there.

`Rights@check` is permissions. Copy that class from the same section — it adds wildcard support that `Auth::can()` lacks and returns the DACore 403 page.

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

        $baseUrl = rtrim((string) Config::module('DACore', 'prefixUrl'), '/') . '/Shop';

        // Pager HTML/classes/COUNT: AIRULES/40 + examples/EX-D08 — do not use QueryObject::paginate()['total']
        $links = DotApp::call(
            'DACore:Page@paginate!',
            $result['current_page'],
            $result['last_page'],
            null,
            function ($type, $pageNo, $label, $state, $href) {
                unset($href);
                if ($type === 'ellipsis') {
                    return '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                $off = ($state === 'active' || $state === 'disabled') ? ' disabled' : '';
                $token = Crypto::encrypt((string) (int) $pageNo, 'Shop.items.page');
                if (!is_string($token) || $token === '') {
                    return '<li class="page-item disabled"><span class="page-link">'
                        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</span></li>';
                }
                return '<li class="page-item ' . $state . '"><button type="button" class="page-link shop-page" data-page="'
                    . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $off . '>'
                    . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</button></li>';
            }
        );

        $html = Renderer::new()
            ->module('Shop')
            ->setLayout('admin/items', 'admin/empty')
            ->setLayoutVar('items', $result['data'])
            ->setLayoutVar('links', $links)
            ->setLayoutVar('total', $result['total'])
            ->setLayoutVar('baseUrl', $baseUrl)
            ->setLayoutVar('apiAuth', '/api/v1/auth/Shop')
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
            ''   // shared nested menu (default); module-own = 'Shop.nav' only if the user chose it — [31]
        );
    }

    public static function itemsList($request)
    {
        try {
            $perPage = (int) (Config::module('Shop', 'itemsPerPage') ?? 20);
            $body = $request->data(true)['data'] ?? [];
            $raw = $body['page'] ?? 1;
            if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
                $page = max(1, (int) $raw);
            } else {
                $plain = Crypto::decrypt((string) $raw, 'Shop.items.page');
                if ($plain === false || !ctype_digit((string) $plain)) {
                    // Why: ajaxReply (base64) + HTTP 200 — Response::json() or HTTP 400 never reaches the toast.
                    return Reply::fail(Translator::trans('This page link has expired. Reload the list and try again.'));
                }
                $page = max(1, (int) $plain);
            }
            // List query: COUNT(*) + LIMIT — AIRULES/40 §4 / EX-D08. Do not trust paginate()['total'].

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
                    unset($href);
                    if ($type === 'ellipsis') {
                        return '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    $off = ($state === 'active' || $state === 'disabled') ? ' disabled' : '';
                    $token = Crypto::encrypt((string) (int) $pageNo, 'Shop.items.page');
                    if (!is_string($token) || $token === '') {
                        return '<li class="page-item disabled"><span class="page-link">'
                            . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                            . '</span></li>';
                    }
                    return '<li class="page-item ' . $state . '"><button type="button" class="page-link shop-page" data-page="'
                        . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $off . '>'
                        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</button></li>';
                }
            );

            $html = Renderer::new()
                ->module('Shop')
                ->setLayout('admin/items-inner', 'admin/empty')
                ->setLayoutVar('items', $result['data'])
                ->setLayoutVar('links', $links)
                ->renderLayout();

            if ($html === '') {
                self::reportCatch('Shop:Admin@itemsList', 'shop.items.render', null, 'error', [], 'admin/items-inner rendered empty');
                return Reply::fail('The list could not be drawn: its template is missing (shop.items.render). The fault was logged.');
            }

            // Why: a pager patch has nothing to announce — an empty message shows no toast.
            return Reply::ok('', ['html' => $html]);
        } catch (\Throwable $e) {
            self::reportCatch('Shop:Admin@itemsList', 'shop.items.list', $e, 'error');
            return Reply::fail('The list could not be loaded because of a server fault (shop.items.list). The fault was logged.');
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
                        return Reply::fail('Enter the item title.', ['title' => 'Enter the item title.']);
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
                        function ($error) {
                            self::reportCatch('Shop:Admin@itemSave', 'shop.item.create', null, 'error',
                                ['errno' => $error['errno'] ?? 0], $error['error'] ?? 'db error');
                        }
                    );

                    if ($newId === null) {
                        return Reply::fail('The item was not saved: the database refused the change (shop.item.create). The fault was logged.');
                    }
                    return Reply::ok('Item saved.', ['redirectTo' => self::listUrl()]);
                },
                function ($request, $name) {
                    // Why: without this callback a bad form signature throws.
                    return Reply::fail('This form has expired. Reload the page and try again.');
                }
            );

            if ($answer === false || $answer === null) {
                // Why: form() gives false on a wrong HTTP method and null when formName / action do not match.
                return Reply::fail('This form does not match its handler (saveItem). Reload the page and try again.');
            }
            return $answer;
        } catch (\Throwable $e) {
            self::reportCatch('Shop:Admin@itemSave', 'shop.item.create', $e, 'error');
            return Reply::fail('The item was not saved because of a server fault (shop.item.create). The fault was logged.');
        }
    }
}
```

`Reply::ok()` / `Reply::fail()` = the module reply helper (`Libraries/Reply.php`, [26](../26-CONTROL-SLICE.md) §7): HTTP 200, `status`, `message`. `self::reportCatch()` = the module catch helper ([18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9). Every stop has its own sentence; a server fault carries its operation slug so the developer can grep it. `self::listUrl()` returns the registered list URL.

`LoginAndCRC` already burned the token on `/api/v1/auth/Shop/…` — the handler starts with `form()`. Full error-handling rationale: [18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md).

## 6. Installer

Copy [EX-D04-dacore-installer.md](EX-D04-dacore-installer.md) / [35](../35-DACORE-INSTALL.md). **While coding:** `install.php` + live `module.init.php` / `module.listeners.php`. After a new version, rename `installed_*_install.php` → `install.php`. **`dainstall.php` + `init/`** only when packing a zip the user asked for. **Do not do this inside `app/modules/DACore/`**.

**MUST** add `about.php` in the module root ([35](../35-DACORE-INSTALL.md) §3b). **ASK** the user for description, license, and 1.0.0 changelog HTML if they did not provide it. If this module is a **pack** or a **host that picks packs**, **ASK** which **reserved** `extra1` from [46](../46-DACORE-EXTRA-CONTRACTS.md) (`filemanager`, `template`, …) plus `extra2`=`v1` and that role’s extra3 ([35](../35-DACORE-INSTALL.md) §3c). In the same grouped question, ask for module identity: text-only / compact logo / wide banner in the installer, existing local asset + alt text, and optional landing/header placement. The sidebar Remix icon is separate. No preference → text-only; do not block the scaffold.

## 7. Resulting file layout

```
app/modules/Shop/
  module.init.php
  module.listeners.php
  Installation.php
  about.php                        (installer preview — nowdoc HTML)
  about-assets/                    (optional images)
  install.php                      (development trigger — NOT dainstall.php until pack)

  AI_RULES.md                      (generated by dotapper)
  Controllers/Admin.php
  Controllers/AITools.php
  Middleware/Gate.php
  Middleware/Rights.php
  views/layouts/admin/items.layout.php
  views/layouts/admin/items-inner.layout.php
  views/layouts/admin/empty.layout.php
  assets/css/admin.css
  assets/js/admin-items.js
  translations/sk_sk.json
  tests/AdminTest.php
```
