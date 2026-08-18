# 35 — DACore-aware Installer

With DACore present, migrations use **DACore's** install tracking instead of the module-owned table from [07](07-SCHEMA-AND-INSTALL.md).

**MUST:** tables **your** module creates are still `{lowercase_modulename}_*` (Shop → `shop_items`). Never name them `dacore_*` (DACore-owned) or `dotapp_*` (core).

---

## 1. Tracking API

```php
DotApp::call("DACore:Installations@exist!", string $module, string $installationID): bool
DotApp::call("DACore:Installations@insert!", string $module, string $installationID, int $status, array|string $statusTxt): bool
```

| Aspect | Detail |
|--------|--------|
| `exist` returns | `true` only when a row exists with `status = 1`; `false` on miss **or DB error** |
| `insert` returns | `bool`; it deletes any previous row for the same `(module, installationID)` first |
| `$status` | `1` = OK, `0` = failed |
| `$statusTxt` | array or string, JSON-encoded into `status_txt` |
| `installation_user` | filled automatically from `Auth::userId() ?? 0` |
| Note | These are **instance** methods — the `!` suffix is required |

`$installationID` is your version string, e.g. `"1.0.0"`.

---

## 2. Migration order that works

Inside one version callback, do it in this order:

1. Create/alter **your own** tables
2. `Rights@createGroup!` → `Rights@createRight!`
3. `Menu@register` (needs the permission strings from step 2 in its `rights`)
4. `AITools@register`
5. `Installations@insert!` — only after everything above succeeded

---

## 3. Complete `Installation.php`

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Installer;
use Dotsystems\App\Parts\Logger;

class Installation extends Installer
{
    public static function installer()
    {
        return [
            '1.0.0' => function () {
                $module = 'Shop';
                $version = '1.0.0';

                if (DotApp::call("DACore:Installations@exist!", $module, $version) === true) {
                    return;
                }

                $ok = true;
                $notes = [];

                // ---- 1. tables ----
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw(
                        "CREATE TABLE IF NOT EXISTS `shop_items` (
                            `id` INT NOT NULL AUTO_INCREMENT,
                            `title` VARCHAR(200) NOT NULL,
                            `sku` VARCHAR(64) NOT NULL DEFAULT '',
                            `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
                            `active` TINYINT(1) NOT NULL DEFAULT 1,
                            `created_at` DATETIME NOT NULL,
                            PRIMARY KEY (`id`),
                            KEY `active_idx` (`active`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        []
                    );
                })->execute(
                    function () use (&$notes) { $notes[] = 'shop_items ok'; },
                    function ($error) use (&$ok, &$notes) {
                        $ok = false;
                        $notes[] = 'shop_items failed: ' . ($error['error'] ?? '');
                        Logger::use()->error('Shop 1.0.0 table', $error);
                    }
                );

                // ---- 2. rights ----
                $groupId = DotApp::call("DACore:Rights@createGroup!", "Shop", "Shop");
                if ($groupId === null) {
                    $ok = false;
                    $notes[] = 'rights group failed';
                } else {
                    $rights = [
                        ['Shop administrator', 'Full access to the Shop module', 'administrator'],
                        ['View items',         'Read the item catalog',          'items.view'],
                        ['Edit items',         'Create and edit items',          'items.edit'],
                    ];
                    foreach ($rights as $i => $r) {
                        $rightId = DotApp::call(
                            "DACore:Rights@createRight!",
                            $groupId,
                            $r[0],        // name
                            $r[1],        // description
                            "Shop",       // module    -> "Shop.<rightname>"
                            $r[2],        // rightname
                            "Shop",       // creator
                            1,            // active
                            $i            // ordering
                        );
                        if ($rightId === null) {
                            $ok = false;
                            $notes[] = 'right failed: ' . $r[2];
                        }
                    }
                }

                // ---- 3. menu ----
                $menuRights = json_encode(['dotapp.root', 'Shop.administrator', 'Shop.items.view']);

                $menu = [
                    ['Shop.main', [
                        'name' => 'Shop', 'parent' => '', 'icon' => '', 'url' => '',
                        'urlprefix' => 1, 'rights' => json_encode(['dotapp.root', 'Shop.*']),
                        'type' => 0, 'ordering' => 500,
                    ]],
                    ['Shop.main.catalog', [
                        'name' => 'Catalog', 'parent' => 'Shop.main', 'icon' => 'ri ri-store-2-line',
                        'url' => '', 'urlprefix' => 1, 'rights' => $menuRights,
                        'type' => 2, 'ordering' => 1,
                    ]],
                    ['Shop.main.catalog.items', [
                        'name' => 'Items', 'parent' => 'Shop.main.catalog', 'icon' => '',
                        'url' => '/shop-admin/items', 'urlprefix' => 1, 'rights' => $menuRights,
                        'type' => 1, 'ordering' => 1,
                    ]],
                ];

                foreach ($menu as [$menuid, $data]) {
                    if (DotApp::call("DACore:Menu@register", $menuid, $data) !== true) {
                        $ok = false;
                        $notes[] = 'menu failed: ' . $menuid;
                    }
                }

                // ---- 4. AI tools (optional) ----
                foreach (self::aiTools() as $toolid => $tool) {
                    if (DotApp::call("DACore:AITools@register", $toolid, $tool) !== true) {
                        $notes[] = 'ai tool failed: ' . $toolid;   // non-fatal
                    }
                }

                // ---- 5. record the result ----
                DotApp::call(
                    "DACore:Installations@insert!",
                    $module,
                    $version,
                    $ok ? 1 : 0,
                    ['outcome' => $ok ? 'ok' : 'partial', 'notes' => $notes]
                );
            },

            '1.0.1' => function () {
                if (DotApp::call("DACore:Installations@exist!", 'Shop', '1.0.1') === true) {
                    return;
                }
                $ok = true;
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw("ALTER TABLE `shop_items` ADD `stock` INT NOT NULL DEFAULT 0", []);
                })->execute(null, function ($error) use (&$ok) {
                    $ok = false;
                    Logger::use()->error('Shop 1.0.1', $error);
                });
                DotApp::call("DACore:Installations@insert!", 'Shop', '1.0.1', $ok ? 1 : 0, ['outcome' => $ok ? 'ok' : 'failed']);
            },
        ];
    }

    public static function uninstaller()
    {
        return [
            '1.0.0' => function () {
                foreach (array_keys(self::aiTools()) as $toolid) {
                    DotApp::call("DACore:AITools@delete", $toolid);
                }

                DotApp::call("DACore:Rights@deleteGroup!", "Shop");

                // No unregister API for menu items - remove them explicitly.
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw("DELETE FROM `dacore_menu` WHERE `menuid` LIKE 'Shop.%'", []);
                })->execute(null, function ($error) {
                    Logger::use()->error('Shop menu cleanup', $error);
                });

                DB::module('RAW')->q(function ($qb) {
                    $qb->raw("DROP TABLE IF EXISTS `shop_items`", []);
                })->execute(null, function ($error) {
                    Logger::use()->error('Shop drop table', $error);
                });
            },
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function aiTools(): array
    {
        return [
            'Shop.Items.Search' => [
                'creator' => 'Shop',
                'description' => 'Search shop items by title or SKU.',
                'controller' => 'Shop:AITools@itemsSearch!',
                'rights' => ['dotapp.root', 'Shop.administrator', 'Shop.items.view'],
                'tool_type' => 'lookup',
                'risk_level' => 0,
                'workflow' => 'Shop.Catalog',
                'intent_tags' => ['find item', 'search product'],
                'howtouse' => [
                    'parameters' => ['query' => 'string, required', 'limit' => 'int, optional, default 20'],
                    'returns' => 'JSON { result, message, items[] }',
                ],
            ],
        ];
    }
}
```

Deleting `dacore_menu` rows directly is acceptable **only in an uninstaller**, because DACore offers no unregister API. Never do it during normal operation.

---

## 4. `dainstall.php` — DACore runs it, the framework does not (**MUST**)

**This is not about the DACore module.** Do **not** rename, move, blank, or wrap `app/modules/DACore/` with `dainstall.php` / `init/`. DACore is the installer host. These rules apply **only** to **your** modules that **run under DACore** (`app/modules/<YourModule>/` — Shop, etc.). Never apply them to `app/modules/DACore/` itself.

A **bare** DotApp module uses `install.php` in the module root. The framework finds it (unless the autoloader is already optimized) and runs it once. That is correct for modules that do **not** depend on DACore.

A **your-module** written to work **under** DACore (admin pages, `DACore:Page@withMenu!`, menu, rights) **MUST** use **`dainstall.php`** instead. **MUST NOT** ship `install.php` in **that** module — the framework would auto-run it.

| File | Who runs it |
|------|-------------|
| `install.php` | The **framework**, on first load of a module (then it renames the file). **Not** for DACore-bound modules. |
| `dainstall.php` | **DACore’s installer only**, after a logged-in operator installs the module through DACore. The framework **never** executes this file. |

`dainstall.php` still calls the same `Installation` class:

```php
<?php
use Dotsystems\App\Modules\Shop\Installation;
Installation::module('Shop')->install();
```

**What DACore does** (operator is logged in → install this module):

1. Unpacks the module zip into `app/modules/<Module>/`.
2. Runs `dainstall.php` (tables, rights, menu, AI tools — this file’s `Installation.php`).
3. **On success**, copies `init/module.init.php` and `init/module.listeners.php` over the module root. Routes and listeners become live; the module starts working.
4. If `dainstall.php` fails, those copies **do not** run. The root files stay inert. Correct — a half-installed module must not register routes.

DACore’s own plugin installer (ZIP upload, installer logs, `dacore_modules` / `dacore_plugin_logs` / `dacore_settings`) is **DACore-owned**. **MUST NOT** write those tables or reimplement that UI in your module.

Idempotency stays in `Installations@exist!` / `insert!`, not in a framework rename of `dainstall.php`.

Manual re-run on a machine where the module is already live (e.g. you added version `1.0.1`):

```php
Installation::module('Shop')->install();          // all versions, guarded by exist!
Installation::module('Shop')->install('1.0.1');   // up to 1.0.1
Installation::module('Shop')->uninstall();
```

---

## 5. `init/` — live copies of init and listeners (**MUST**)

Still **your** module only (`app/modules/Shop/init/`, …). **MUST NOT** create `init/` under `app/modules/DACore/`.

Create `app/modules/<Module>/init/` and **keep it in sync** whenever you edit the live files:

| Live (you code here) | Copy (always identical while developing) |
|----------------------|------------------------------------------|
| `module.init.php` | `init/module.init.php` |
| `module.listeners.php` | `init/module.listeners.php` |

During local development the **root** files stay fully working (routes, listeners) so you can build and test. The copies in `init/` are what DACore will activate after a successful `dainstall.php`.

**MUST** copy into `init/` after every change to those two files. A stale `init/` copy means a production install boots old routes.

---

## 6. Export for the DACore installer (**MUST ask the user**)

Do **not** blank the root files on your own. Only when the user **explicitly** asks to export / pack **your** module for DACore install (production zip). **MUST NOT** export or blank `app/modules/DACore/` this way.

1. Confirm `init/module.init.php` and `init/module.listeners.php` are the current working copies.
2. Replace the **root** `module.init.php` and `module.listeners.php` with **inert stubs** — no routes, no listeners, they do nothing. The packed zip ships those stubs in the root.
3. The zip still contains `dainstall.php`, `Installation.php`, `init/`, and the rest of the module. **MUST NOT** include `install.php`.

Inert root `module.init.php` (Shop example):

```php
<?php
namespace Dotsystems\App\Modules\Shop;

class Module extends \Dotsystems\App\Parts\Module
{
    public function initialize($dotApp)
    {
    }

    public function initializeRoutes()
    {
        return [];
    }

    public function initializeCondition($routeMatch)
    {
        return $routeMatch;
    }
}

new Module($dotApp);
```

Inert root `module.listeners.php`:

```php
<?php
namespace Dotsystems\App\Modules\Shop;

class Listeners extends \Dotsystems\App\Parts\Listeners
{
    public function register($dotApp)
    {
    }
}

new Listeners($dotApp);
```

After DACore’s installer succeeds, it overwrites these stubs from `init/`. Until then the unpacked module must not expose routes.

---

## 7. Checklist

- [ ] These `dainstall.php` / `init/` rules were applied to **your** module under DACore — **not** to `app/modules/DACore/` itself
- [ ] DACore-bound module uses **`dainstall.php`**, not `install.php` (framework must not auto-run it)
- [ ] `init/module.init.php` and `init/module.listeners.php` exist and match the live root files while developing
- [ ] Export / production zip only when the user asked: root init files inert, real copies stay in `init/`
- [ ] Every version callback starts with `Installations@exist!` and ends with `Installations@insert!`
- [ ] `exist!` / `insert!` use the `!` suffix (instance methods)
- [ ] Rights created before the menu that references them
- [ ] `Menu@register` return value checked (`!== true`)
- [ ] `Rights@createGroup!` / `createRight!` checked for `null`
- [ ] Menu rights JSON contains `dotapp.root`
- [ ] AI tool `rights` non-empty and wildcard-free
- [ ] `dacore_ai_tools` verified to exist before registering tools
- [ ] Uninstaller removes tools, rights, menu rows and your tables
- [ ] Every `execute()` has both callbacks ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md))
