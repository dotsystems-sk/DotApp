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
                // $qb->raw() counts every ? as a placeholder — including COMMENT 'SMS?'. Never put ? in comments.
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

                // ---- 3. menu (ASK shared vs module-own — [31]) ----
                // Shared sample: header + type 2 + leaf. Many items: header + one entry, inner withMenu $menuId.
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
                        'url' => '/Shop/items', 'urlprefix' => 1, 'rights' => $menuRights,
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

Deleting `dacore_menu` rows directly is acceptable **only in an uninstaller**, because DACore offers no unregister API. Never do it during normal operation. Delete **only your** `menuid` prefix (`Shop.%`). An extension that added items under another module **MUST NOT** delete that host’s prefix. Own sidebar: register a `type => 0` header. **ASK** shared vs module-own before a new module; group with `type => 2` or use header + one entry ([31](31-DACORE-MENU.md)).

---

## 4. Development vs DACore zip (**MUST**)

**This is not about the DACore module.** Do **not** rename, move, blank, or wrap `app/modules/DACore/` with `dainstall.php` / `init/`. The zip rules below apply **only** to **your** modules that **plug into DACore** (`app/modules/Shop/` with admin pages, menu, rights). A module that is **not** for DACore is never packed this way.

### While you are programming (default)

Work like a normal DotApp module:

| In the module root | Do |
|--------------------|----|
| `module.init.php` / `module.listeners.php` | **Live** files — routes, listeners, config. **MUST NOT** blank them |
| `install.php` | **MUST** — the framework runs it on the next page load, then renames it to `installed_<md5>_install.php` |
| `dainstall.php` / `init/` | **MUST NOT** create these while coding |

`install.php` still calls the same `Installation` class:

```php
<?php
use Dotsystems\App\Modules\Shop\Installation;
Installation::module('Shop')->install();
```

Idempotency is `Installations@exist!` / `insert!`, not the filename.

**MUST (new version):** after you add or change a version in `Installation.php`, rename `installed_*_install.php` back to `install.php` so the next web load runs the new migration. The agent does this — **MUST NOT** leave it for the user. See [07](07-SCHEMA-AND-INSTALL.md).

### What DACore does with a **packed** zip

1. Unpacks into `app/modules/<Module>/`.
2. Runs **`dainstall.php`** (tables, rights, menu, AI tools).
3. **On success**, copies `init/module.init.php` and `init/module.listeners.php` over the module root. Routes become live.
4. If `dainstall.php` fails, those copies **do not** run. The root files stay inert.

The framework **never** executes `dainstall.php`. That is why the zip **MUST NOT** contain `install.php`: DACore **rejects** that package, and if it slipped through, the framework would auto-run `install.php` on unpack **before** the plugin installer finished.

DACore’s own plugin installer (ZIP upload, installer logs, `dacore_modules` / …) is **DACore-owned**. **MUST NOT** write those tables or reimplement that UI.

---

## 5. Pack an installable zip (**DACore modules only**, and only when the user asks)

**LAW ([00](00-AGENT-CONTRACT.md) §2e):** `install.php` is for the **framework**. `dainstall.php` is for the **DACore installer**. A zip that still contains `install.php` is **rejected** (DACore will not treat it as a plugin). A zip that has no `dainstall.php` **never runs** `Installation` (tables, rights, menu stay missing). Live `module.init.php` / `module.listeners.php` **MUST** sit in **`init/`** — DACore copies them to the root only **after** `dainstall.php` succeeds.

This zip exists **only** so DACore’s plugin installer can install **your** admin module. **MUST NOT** pack if the module is not for DACore. For a bare-framework module the user copies the folder themselves after renaming `installed_*_install.php` → `install.php` ([07](07-SCHEMA-AND-INSTALL.md)).

Do **not** pack, blank root init files, or create `dainstall.php` on your own. Only when the user **explicitly** asks to export / zip **your DACore-bound** module. **MUST NOT** pack `app/modules/DACore/` this way.

**Working tree stays a normal module** (`install.php` + live init). Transform a **copy** (or transform → zip → **restore**). **MUST NOT** leave the working module packed.

On the copy / before zip:

1. Copy live `module.init.php` → `init/module.init.php` and live `module.listeners.php` → `init/module.listeners.php` (create `init/` if needed).
2. Replace the **root** `module.init.php` and `module.listeners.php` with **inert stubs** (below).
3. Rename `install.php` **or** `installed_*_install.php` to **`dainstall.php`**. Same PHP body (`Installation::module('Shop')->install();`).
4. Zip so unpack lands in `app/modules/Shop/` with `dainstall.php`, `Installation.php`, `init/`, inert root init files, and the rest of the module. **MUST NOT** include `install.php` or `installed_*_install.php`.
5. Restore the working tree: live init files from `init/`, `dainstall.php` → `install.php`.

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

## 6. Checklist

- [ ] These pack rules were applied to **your** module — **not** to `app/modules/DACore/` itself
- [ ] **While coding:** live root `module.init.php` / `module.listeners.php`, trigger is **`install.php`**, no `dainstall.php` / no inert stubs
- [ ] After a new `Installation.php` version: `installed_*_install.php` renamed back to `install.php` (agent did it)
- [ ] Zip **only** for a DACore-bound module and only when the user asked: `install.php` **renamed** to `dainstall.php`, `init/` copies, inert root, **no** `install.php` in the zip (DACore rejects it / will not run Installation); working tree restored ([00](00-AGENT-CONTRACT.md) §2e)
- [ ] Every version callback starts with `Installations@exist!` and ends with `Installations@insert!`
- [ ] `exist!` / `insert!` use the `!` suffix (instance methods)
- [ ] Rights created before the menu that references them
- [ ] `Menu@register` return value checked (`!== true`)
- [ ] `Rights@createGroup!` / `createRight!` checked for `null`
- [ ] Menu rights JSON contains `dotapp.root`
- [ ] AI tool `rights` non-empty and wildcard-free
- [ ] `dacore_ai_tools` verified to exist before registering tools
- [ ] Uninstaller removes tools, rights, menu rows and your tables
- [ ] Menu uninstall deletes only **your** `menuid` prefix (not a host module’s `LIKE 'Other.%'`) ([31](31-DACORE-MENU.md))
- [ ] New module: user was **asked** shared vs module-own; many items are grouped (`type => 2`) or module-own ([31](31-DACORE-MENU.md))
- [ ] Every `execute()` has both callbacks ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md))
