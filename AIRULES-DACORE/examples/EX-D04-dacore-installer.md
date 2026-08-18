# EX-D04 — DACore-aware installer (copy-paste)

Rules: [35](../35-DACORE-INSTALL.md), [32](../32-DACORE-RIGHTS.md), [31](../31-DACORE-MENU.md).

Order inside a version: **tables → rights → menu → AI tools → record result**.

## `Installation.php`

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Installer;
use Dotsystems\App\Parts\Logger;

class Installation extends Installer
{
    private const MODULE = 'Shop';

    public static function installer()
    {
        return [
            '1.0.0' => function () {
                $version = '1.0.0';
                if (DotApp::call("DACore:Installations@exist!", self::MODULE, $version) === true) {
                    return;
                }

                $ok = true;
                $notes = [];

                // ---------- 1. tables ----------
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
                            UNIQUE KEY `sku_unique` (`sku`),
                            KEY `active_idx` (`active`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        []
                    );
                })->execute(
                    function () use (&$notes) { $notes[] = 'table shop_items ok'; },
                    function ($error) use (&$ok, &$notes) {
                        $ok = false;
                        $notes[] = 'table shop_items failed: ' . ($error['error'] ?? '');
                        Logger::use()->error('Shop 1.0.0 table', $error);
                    }
                );

                // ---------- 2. rights ----------
                $groupId = DotApp::call("DACore:Rights@createGroup!", 'Shop', self::MODULE);
                if ($groupId === null) {
                    $ok = false;
                    $notes[] = 'rights group failed';
                } else {
                    // [label, description, rightname]
                    $rights = [
                        ['Shop administrator', 'Full access to the Shop module', 'administrator'],
                        ['View items',         'Read the item catalog',           'items.view'],
                        ['Edit items',         'Create and edit items',           'items.edit'],
                        ['Export items',       'Export the catalog to CSV',       'items.export'],
                    ];
                    foreach ($rights as $i => $r) {
                        $rightId = DotApp::call(
                            "DACore:Rights@createRight!",
                            $groupId,      // int   group id
                            $r[0],         // name (label)
                            $r[1],         // description
                            self::MODULE,  // module    -> "Shop.<rightname>"
                            $r[2],         // rightname
                            self::MODULE,  // creator   (uninstall key)
                            1,             // active
                            $i             // ordering
                        );
                        if ($rightId === null) {
                            $ok = false;
                            $notes[] = 'right failed: ' . $r[2];
                        }
                    }
                }

                // ---------- 3. menu ----------
                $sectionRights = json_encode(['dotapp.root', 'Shop.*']);
                $itemRights = json_encode(['dotapp.root', 'Shop.administrator', 'Shop.items.view']);

                $menu = [
                    // menuid                    name        parent              icon                    url                  type ord
                    ['Shop.main',                'Shop',     '',                 '',                     '',                  0,   500, $sectionRights],
                    ['Shop.main.catalog',        'Catalog',  'Shop.main',        'ri ri-store-2-line',   '',                  2,   1,   $itemRights],
                    ['Shop.main.catalog.items',  'Items',    'Shop.main.catalog', '',                    '/shop-admin/items', 1,   1,   $itemRights],
                ];

                foreach ($menu as $m) {
                    $registered = DotApp::call("DACore:Menu@register", $m[0], [
                        'name' => $m[1],
                        'parent' => $m[2],
                        'icon' => $m[3],
                        'url' => $m[4],
                        'urlprefix' => 1,          // prepend DACore prefixUrl
                        'type' => $m[5],
                        'ordering' => $m[6],
                        'rights' => $m[7],
                    ]);
                    if ($registered !== true) {
                        $ok = false;
                        $notes[] = 'menu failed: ' . $m[0];
                    }
                }

                // ---------- 4. AI tools (optional, non-fatal) ----------
                if (DB::schemaBuilder()->tableExists('dacore_ai_tools')) {
                    foreach (self::aiTools() as $toolid => $tool) {
                        if (DotApp::call("DACore:AITools@register", $toolid, $tool) !== true) {
                            $notes[] = 'ai tool failed: ' . $toolid;
                        }
                    }
                } else {
                    $notes[] = 'dacore_ai_tools missing - AI tools skipped';
                }

                // ---------- 5. record ----------
                DotApp::call(
                    "DACore:Installations@insert!",
                    self::MODULE,
                    $version,
                    $ok ? 1 : 0,
                    ['outcome' => $ok ? 'ok' : 'partial', 'notes' => $notes]
                );
            },

            '1.0.1' => function () {
                $version = '1.0.1';
                if (DotApp::call("DACore:Installations@exist!", self::MODULE, $version) === true) {
                    return;
                }

                $ok = true;
                if (!DB::schemaBuilder()->columnExists('shop_items', 'stock')) {
                    DB::module('RAW')->q(function ($qb) {
                        $qb->raw("ALTER TABLE `shop_items` ADD `stock` INT NOT NULL DEFAULT 0", []);
                    })->execute(null, function ($error) use (&$ok) {
                        $ok = false;
                        Logger::use()->error('Shop 1.0.1 alter', $error);
                    });
                }

                DotApp::call(
                    "DACore:Installations@insert!",
                    self::MODULE,
                    $version,
                    $ok ? 1 : 0,
                    ['outcome' => $ok ? 'ok' : 'failed']
                );
            },
        ];
    }

    public static function uninstaller()
    {
        return [
            '1.0.0' => function () {
                // AI tools
                foreach (array_keys(self::aiTools()) as $toolid) {
                    DotApp::call("DACore:AITools@delete", $toolid);
                }

                // rights + group + user assignments
                DotApp::call("DACore:Rights@deleteGroup!", self::MODULE);

                // menu - no unregister API exists, delete our own prefixed rows
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw("DELETE FROM `dacore_menu` WHERE `menuid` LIKE 'Shop.%'", []);
                })->execute(null, function ($error) {
                    Logger::use()->error('Shop menu cleanup', $error);
                });

                // our tables last
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
                'creator' => self::MODULE,
                'description' => 'Search shop items by title or SKU. Read-only.',
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

## `dainstall.php` (DACore installer trigger)

**MUST:** **Your** modules that work **under** DACore use this name. The framework **never** runs it. **MUST NOT** also ship `install.php`. **MUST NOT** apply this to `app/modules/DACore/` itself. See [35](../35-DACORE-INSTALL.md) §4–§6 (`init/` copies; export blanks the root only when the user asks).

```php
<?php
use Dotsystems\App\Modules\Shop\Installation;

Installation::module('Shop')->install();
```

Idempotency comes from the `Installations@exist!` guards, not from a framework rename.

## Re-running after adding a version

```php
Installation::module('Shop')->install();          // guarded, safe
Installation::module('Shop')->install('1.0.1');   // up to 1.0.1
Installation::module('Shop')->uninstall();
```

## Why each guard is here

| Guard | Reason |
|-------|--------|
| `Installations@exist!` first | Prevents re-running a completed version |
| `!== true` on `Menu@register` | It returns `bool` and never throws or logs |
| `=== null` on rights helpers | They return `int\|null` |
| Both `execute()` callbacks | Missing error callback makes it **throw** ([18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md)) |
| `tableExists('dacore_ai_tools')` | DACore 1.0.0 does not create that table |
| `columnExists` before `ALTER` | `ALTER ... ADD` is not idempotent |
| `insert!` last with a status | A partial install is recorded as `status = 0` |
