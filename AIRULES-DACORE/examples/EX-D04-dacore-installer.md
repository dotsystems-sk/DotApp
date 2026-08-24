# EX-D04 — DACore-aware installer (copy-paste)

Rules: [35](../35-DACORE-INSTALL.md), [32](../32-DACORE-RIGHTS.md), [31](../31-DACORE-MENU.md).

Order inside a version: **tables → rights → menu → AI tools → record result**.

Across versions: `installer()` keys run in **written order** (`foreach`). **MUST NOT** `ksort` / `uksort` / `krsort` / `usort` that map — `1.0.10` sorts before `1.0.9`.

## `Installation.php`

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\DotApp;
use Dotsystems\App\Modules\Shop\Libraries\CatchBus;
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

                // ---------- 3. immutable installer-managed user group ----------
                $managerRoleId = DotApp::call(
                    "DACore:Roles@createGroup!",
                    'Shop managers',
                    'Manage the product catalog.',
                    self::MODULE,
                    'managers',
                    [
                        ['module' => self::MODULE, 'rightname' => 'items.view'],
                        ['module' => self::MODULE, 'rightname' => 'items.edit'],
                    ]
                );
                if ($managerRoleId === null) {
                    $ok = false;
                    $notes[] = 'installer user group failed';
                }

                // ---------- 4. menu (ASK: shared vs module-own — [31]) ----------
                // This sample is the **shared** tree: header + type 2 group + leaf.
                // Module-own: header + one entry here; inner pages pass withMenu $menuId.
                $sectionRights = json_encode(['dotapp.root', 'Shop.*']);
                $itemRights = json_encode(['dotapp.root', 'Shop.administrator', 'Shop.items.view']);

                $menu = [
                    // menuid                    name        parent              icon                    url                  type ord
                    ['Shop.main',                'Shop',     '',                 '',                     '',                  0,   500, $sectionRights],
                    ['Shop.main.catalog',        'Catalog',  'Shop.main',        'ri ri-store-2-line',   '',                  2,   1,   $itemRights],
                    ['Shop.main.catalog.items',  'Items',    'Shop.main.catalog', '',                    '/Shop/items', 1,   1,   $itemRights],
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

                // ---------- 5. AI tools (optional, non-fatal) ----------
                if (DB::schemaBuilder()->tableExists('dacore_ai_tools')) {
                    foreach (self::aiTools() as $toolid => $tool) {
                        if (DotApp::call("DACore:AITools@register", $toolid, $tool) !== true) {
                            $notes[] = 'ai tool failed: ' . $toolid;
                        }
                    }
                } else {
                    $notes[] = 'dacore_ai_tools missing - AI tools skipped';
                }

                // ---------- 6. record ----------
                $recorded = DotApp::call(
                    "DACore:Installations@insert!",
                    self::MODULE,
                    $version,
                    $ok ? 1 : 0,
                    ['outcome' => $ok ? 'ok' : 'partial', 'notes' => $notes]
                );
                if ($recorded !== true || $ok !== true) {
                    CatchBus::reportCatch(null, 'error', ['version' => $version], 'Shop installation failed');
                    throw new \RuntimeException('Shop installation failed.');
                }
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

                $recorded = DotApp::call(
                    "DACore:Installations@insert!",
                    self::MODULE,
                    $version,
                    $ok ? 1 : 0,
                    ['outcome' => $ok ? 'ok' : 'failed']
                );
                if ($recorded !== true || $ok !== true) {
                    CatchBus::reportCatch(null, 'error', ['version' => $version], 'Shop update failed');
                    throw new \RuntimeException('Shop installation failed.');
                }
            },
        ];
    }

    public static function uninstaller()
    {
        return [
            '1.0.0' => function () {
                // AI tools
                foreach (array_keys(self::aiTools()) as $toolid) {
                    if (DotApp::call("DACore:AITools@delete", $toolid) !== true) {
                        CatchBus::reportCatch(null, 'error', ['tool_id' => $toolid], 'Shop AI tool cleanup failed');
                        throw new \RuntimeException('Shop uninstall cleanup failed.');
                    }
                }

                // Stable installer-managed user group, then rights-catalog cleanup
                if (DotApp::call("DACore:Roles@deleteGroup!", self::MODULE, 'managers') !== true) {
                    CatchBus::reportCatch(null, 'error', [], 'Shop role cleanup failed');
                    throw new \RuntimeException('Shop uninstall cleanup failed.');
                }
                if (DotApp::call("DACore:Rights@deleteGroup!", self::MODULE) !== true) {
                    CatchBus::reportCatch(null, 'error', [], 'Shop rights cleanup failed');
                    throw new \RuntimeException('Shop uninstall cleanup failed.');
                }

                // menu - no unregister API exists, delete our own prefixed rows
                $menuOk = false;
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw(
                        'DELETE FROM `dacore_menu` WHERE `menuid` LIKE :prefix',
                        ['prefix' => 'Shop.%']
                    );
                })->execute(
                    function () use (&$menuOk) {
                        $menuOk = true;
                    },
                    function ($error) use (&$menuOk) {
                        CatchBus::reportDb($error);
                        $menuOk = false;
                    }
                );
                if ($menuOk !== true) {
                    throw new \RuntimeException('Shop uninstall cleanup failed.');
                }

                // our tables last
                $tableOk = false;
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw("DROP TABLE IF EXISTS `shop_items`", []);
                })->execute(
                    function () use (&$tableOk) {
                        $tableOk = true;
                    },
                    function ($error) use (&$tableOk) {
                        CatchBus::reportDb($error);
                        $tableOk = false;
                    }
                );
                if ($tableOk !== true) {
                    throw new \RuntimeException('Shop uninstall cleanup failed.');
                }
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

## `install.php` (development) / `dainstall.php` (packed zip only)

**While coding:** name it **`install.php`**. The framework runs it, then renames it to `installed_*_install.php`. After a new version, rename that file back to `install.php`.

**Packed zip only** (user asked, **and** this module is for DACore) — **LAW** ([00](../00-AGENT-CONTRACT.md) §2e): **MUST** rename `install.php` → **`dainstall.php`**, copy live init files into **`init/`**, inert root stubs. **MUST** include root **`.hooks`** when the module fires `module.{this}.*` hooks ([41](../41-MODULE-HOOKS.md)). **MUST NOT** leave `install.php` in the zip — DACore **rejects** that package and **will not run** `Installation`. **MUST NOT** pack a module that is not for DACore.

```php
<?php
use Dotsystems\App\Modules\Shop\Installation;

Installation::module('Shop')->install();
```

Idempotency comes from the `Installations@exist!` guards, not from a framework rename.

## `about.php` (module root — working tree and zip)

**MUST.** DACore’s installer reads this **without executing it**. Changelog keys **MUST** match `Installation.php`. **ASK** the user for the HTML if they did not supply it.

During planning, also **ASK** for the installer identity: text-only, compact logo near the heading, or wide banner above the summary; existing asset + alt text; and whether it should also appear on the module landing/header. A menu Remix icon is separate. If no image is wanted, keep the text-only example below. If wanted, store an optimised raster in `about-assets/` and insert one of these at the start of `about`:

```html
<!-- Compact mark: real intrinsic dimensions, meaningful alt text. -->
<img src="about-assets/shop-logo.png" width="160" height="160" alt="Shop">

<!-- Or a wide banner; do not include both unless the user asked. -->
<img src="about-assets/shop-banner.webp" width="1200" height="360" alt="Shop catalog">
```

No external URL, SVG/script/iframe, tracking image, invented logo, or DACore patch. Preview desktop + narrow width. Canonical: [35](../35-DACORE-INSTALL.md) §3b.

```php
<?php
return [
    'about' => <<<'HTML'
<h2>Shop</h2>
<p>Catalog and item management for the admin area.</p>
HTML,
    'license' => <<<'HTML'
<p>Licensed for use with this DACore installation. Do not redistribute the package without permission.</p>
HTML,
    'changelog' => [
        '1.0.0' => <<<'HTML'
<ul>
<li>Initial catalog, rights, and menu.</li>
</ul>
HTML,
        '1.0.1' => <<<'HTML'
<ul>
<li>Stock column on shop items.</li>
</ul>
HTML,
    ],
];
```

After you add a version in `Installation.php`, add the same key here. If the notes are unknown, **ASK**.

Optional discovery flags ([35](../35-DACORE-INSTALL.md) §3c) — omit on a normal Shop. A **template pack** for a CMS host:

```php
    'extra1' => 'template',
```

The CMS settings dropdown **MUST** list `DotApp::call('DACore:Plugins@listByExtra!', 1, 'template')` (or `SELECT … extra1 = :flag`), not `glob(app/modules)`. **ASK** the host’s token vocabulary before inventing `template`.

## Re-running after adding a version

Rename `installed_*_install.php` back to `install.php` (agent does it). Next page load runs `Installation::module('Shop')->install();`. Already-applied versions stay skipped by `Installations@exist!`.

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
