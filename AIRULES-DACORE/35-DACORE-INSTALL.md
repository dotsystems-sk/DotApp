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
2. If the module creates accounts: `UserPolicy@registerOrigin` and require `{ok:true, origin_id>0}`
3. `Rights@createGroup!` → `Rights@createRight!`
4. `Roles@createGroup!` for every installer-managed user group (needs the rights from step 3)
5. `Menu@register` (needs the permission strings from step 3 in its `rights`)
6. `AITools@register`
7. `Installations@insert!` — only after everything above succeeded

`Rights@createGroup!` is the rights-catalog heading. `Roles@createGroup!` is the actual bundle assigned to users. Installer-created user groups **MUST** use stable `(creator, groupid)` identity and are automatically immutable (`editable = 0`). See [32](32-DACORE-RIGHTS.md) §1.

If this module creates accounts (shop checkout, public register, import), **MUST** register in the installer with the same creator used on stamp/uninstall. **MUST** check `ok === true` and positive `origin_id`; a fire-and-forget call is a bug. Create flow is register → `Auth::createUser` → bound exact id lookup → `stampOrigin` → `read` exact token/id verification. Uninstall checks `removeOrigin` and stops/surfaces a refusal while profiles still use it. Canonical: [42](42-DACORE-USER-ORIGIN.md).

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

                // ---- 2. account origin (required because this Shop creates customers) ----
                $origin = DotApp::call(
                    'DACore:UserPolicy@registerOrigin',
                    'shop.checkout',
                    'Shop'
                );
                if (
                    !is_array($origin)
                    || ($origin['ok'] ?? false) !== true
                    || (int) ($origin['origin_id'] ?? 0) < 1
                ) {
                    $ok = false;
                    $notes[] = 'shop.checkout origin failed';
                    DotApp::call(
                        "DACore:Installations@insert!",
                        $module,
                        $version,
                        0,
                        ['outcome' => 'failed', 'notes' => $notes]
                    );
                    return;
                }

                // ---- 3. rights ----
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

                // ---- 4. immutable installer-managed user group ----
                $managerRoleId = DotApp::call(
                    "DACore:Roles@createGroup!",
                    "Shop managers",
                    "Manage the product catalog.",
                    "Shop",              // creator
                    "managers",          // stable internal groupid, never a numeric id
                    [
                        ['module' => 'Shop', 'rightname' => 'items.view'],
                        ['module' => 'Shop', 'rightname' => 'items.edit'],
                    ]
                );
                if ($managerRoleId === null) {
                    $ok = false;
                    $notes[] = 'installer user group failed';
                }

                // ---- 5. menu (ASK shared vs module-own — [31]) ----
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

                // ---- 6. AI tools (optional) ----
                foreach (self::aiTools() as $toolid => $tool) {
                    if (DotApp::call("DACore:AITools@register", $toolid, $tool) !== true) {
                        $notes[] = 'ai tool failed: ' . $toolid;   // non-fatal
                    }
                }

                // ---- 7. record the result ----
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

                $originRemoval = DotApp::call(
                    'DACore:UserPolicy@removeOrigin',
                    'shop.checkout',
                    'Shop'
                );
                if (!is_array($originRemoval) || ($originRemoval['ok'] ?? false) !== true) {
                    // Why: customer profiles still using this token must not be silently orphaned/remapped.
                    Logger::use()->error('Shop origin cleanup refused', [
                        'origin' => 'shop.checkout',
                    ]);
                    throw new \RuntimeException(
                        'Shop cannot be removed while customer accounts still use its origin.'
                    );
                }

                DotApp::call("DACore:Roles@deleteGroup!", "Shop", "managers");
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

## 3b. `about.php` — installer preview (**MUST**)

Every DACore-bound module **MUST** keep `about.php` in the **module root** (working tree **and** packed zip). DACore’s plugin installer **rejects** a zip without it. The file is **data**, not an installer: DACore **parses** it and **MUST NOT** `include` / `require` / `eval` it.

After a successful install or update, DACore stores the sanitised HTML in `dacore_modules` (`about_html`, `license_html`, `changelog_json`) **and** optional discovery flags (`extra1`…`extra5`, [§3c](#3c-discovery-flags-extra1extra5-must)). The installed-modules list and detail drawer **MUST** read that table. **MUST NOT** boot the installed module or read its files again just to show about / license / changelog / flags.

**ASK** the user for the three HTML fields if they were not provided. Do **not** invent license terms or a fake changelog. Version numbers stay **only** in `Installation.php` — `about.php` must list the **same** keys under `changelog`.

Allowed grammar: `<?php` + `return [` + nowdoc values (`<<<'HTML'` only — **MUST NOT** unquoted `<<<HTML`) for `about` / `license` / `changelog`. Keys **MUST:** `about`, `license`, `changelog`. Optional quoted-string keys: `extra1`…`extra5` ([§3c](#3c-discovery-flags-extra1extra5-must)). Images: optional raster files under `about-assets/` (`png` / `jpeg` / `gif` / `webp`), referenced as `about-assets/file.png`.

### Installer identity / branding (**ASK in the plan**)

For every new DACore-bound module, ask **one grouped identity question** before writing `about.php`:

1. What public module name and one-sentence description should operators see?
2. Should the installer preview use **no image**, a **compact logo/mark near the heading**, or a **wide banner above the summary**?
3. If an image is wanted, which existing local asset should be used? Ask for its alt text; ask whether a light/dark variant is needed.
4. Where else, if anywhere, should the same identity appear (module landing/header)? The DACore sidebar uses a separate Remix `icon` class — it is **not** this logo ([31](31-DACORE-MENU.md)).

If the user has no preference, default to a clean text-only heading and description; do **not** hold up the installer. If they want a generated image, **ASK before using a potentially paid image model** ([00](00-AGENT-CONTRACT.md) §2b).

**MUST** also open **`app/modules/DACore/.hooks`** (read-only) before scaffolding listeners for this module — catalog of events DACore already fires ([41](41-MODULE-HOOKS.md) §6).

There is **no separate installer `logo` field** in this package contract. Placement is expressed by the order of HTML inside `about`; the file itself lives under your module’s `about-assets/` and ships in the zip:

```php
'about' => <<<'HTML'
<img src="about-assets/shop-logo.png" width="160" height="160" alt="Shop">
<h2>Shop</h2>
<p>Catalog and item management for the admin area.</p>
HTML,
```

- Compact logo/mark → place it with or immediately before the heading.
- Wide banner → place it first, before the heading/summary.
- Decorative image → `alt=""`; meaningful logo → concise product-name alt text.
- **MUST** use a local relative `about-assets/...` path, a supported raster type, optimised file size, and real intrinsic `width` / `height`.
- **MUST NOT** use SVG/script/iframe, external URLs or tracking pixels; invent branding; duplicate one image under several names; or patch DACore CSS/views to position it.
- **MUST** preview the upload/install flow at desktop and narrow width. The content must remain readable if the image cannot load.

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
    // Optional — omit on a normal app module. Theme pack example: extra1 = template
    // 'extra1' => 'template',
];
```

Installer flow: upload ZIP → DACore shows changelog (for an update: only versions **newer than** the installed one, up to the package version) then about HTML then license → operator ticks **I agree and continue** → **Install** / **Update**. The server **MUST** refuse if `accepted` is missing. Same version or a downgrade is **rejected**.

**MUST (new version):** add the same semver key to `about.php` `changelog`. If the user did not write the notes, **ASK**.

---

## 3c. Discovery flags `extra1`…`extra5` (**MUST** understand at design time)

`dacore_modules.extra1` … `extra5` are **short text tokens** copied from `about.php` at ZIP install/update. They exist so one module can **find other installed packages by role** without waking them.

### Why this exists (CMS / templates)

A **CMS** (editorial system) must not bake a public theme into its own files. A CMS update would overwrite operator customisations. The durable design is:

1. **CMS module** — settings, articles, routing. It does **not** own the public HTML theme.
2. **Template pack module** — a separate DACore-installable package that only carries the theme (views/assets).
3. CMS settings: “Choose the template module.” The dropdown is **not** every folder under `app/modules/`. It is **only** installed rows in `dacore_modules` whose **`extra1` is `template`** (the vocabulary the CMS author published).

The template-pack author writes in `about.php`:

```php
'extra1' => 'template',
```

After install, CMS lists packs:

```php
$packs = DotApp::call('DACore:Plugins@listByExtra!', 1, 'template');
// $packs = list of [module, version, extra1…extra5] — status=1 only. Never boots those modules.
```

Shipped in **DACore 1.0.26** (`dacore_modules.extra1`…`extra5` + this helper). It is a **static in-process helper**, not an HTTP action — no CRC. Slot must be `1`–`5`. Empty token, invalid charset, unknown slot, or extra columns not yet migrated → `[]`. **MUST NOT** call it with `''` to mean “all modules” (blank extra columns are the default).

If that helper is unavailable on an older DACore, **READ** is still allowed:

```php
$rows = DB::module('RAW')->q(function ($qb) {
    $qb->raw(
        'SELECT `module`, `version`, `extra1`, `extra2`, `extra3`, `extra4`, `extra5` FROM `dacore_modules` WHERE `status` = 1 AND `extra1` = :flag ORDER BY `module` ASC',
        ['flag' => 'template']
    );
})->all();
```

**MUST NOT** `glob('app/modules/*')`, `include` the pack’s `about.php` / `settings.php` / `module.init.php`, or `DotApp::call('ThemePack:…')` just to discover it. Same sleep law: [03](03-MODULES-AND-ROUTING.md).

### What the five slots are for

DACore does **not** assign a global meaning to extra2–extra5. The **host** module (CMS, shop, mail) publishes a small vocabulary; **pack** modules fill the slots.

| Column | Typical use | Example |
|--------|-------------|---------|
| `extra1` | Primary kind / role | `template`, `gateway`, `locale` |
| `extra2` | Family / variant the host documented | `blog`, `shop` |
| `extra3`…`extra5` | Further host-defined tokens | only if the host’s README / AIRULES-style comment in **that** module says so |

**MUST:**

- Tokens: quoted strings in `about.php` (`'template'`), **not** HTML nowdoc
- Length ≤ 64; charset `[a-zA-Z0-9._-]`; no spaces, no sentences, no secrets, no rights names
- Omit unused keys (empty columns). A normal Shop/CMS host often has **no** extras
- **ASK** when planning a **pack**: “Which host should discover this, and which extra1–extra5 tokens does that host require?” Do not invent `template` unless the host is a CMS/theme picker
- **ASK** when planning a **host** that will pick among packs: document the required `extra1` (and others) in that host’s settings UI copy and in a short comment at the query. Packs **MUST** use that exact string
- Re-install / update the pack after changing extras — flags are stored at install, not read live from disk on every request

**MUST NOT:**

- `UPDATE dacore_modules` from your module to set extras (installer + `about.php` only)
- Put HTML, URLs, JSON blobs, or passwords in extra*
- Use extras instead of `DACore:Rights@*`
- Set `extra1 = template` on the CMS host itself (that would list the CMS as a theme)
- Assume extra1 is always `template` — that word is the **CMS author’s** contract, not a DACore enum

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
4. Zip so unpack lands in `app/modules/Shop/` with `dainstall.php`, `Installation.php`, **`about.php`**, **`.hooks`** (when the module fires `module.shop.*.hook` events — [41](41-MODULE-HOOKS.md)), `init/`, inert root init files, and the rest of the module. **MUST NOT** include `install.php` or `installed_*_install.php`. **MUST NOT** omit `about.php` (DACore rejects it). **MUST NOT** put `.hooks` under `assets/`.
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
- [ ] Zip **only** for a DACore-bound module and only when the user asked: `install.php` **renamed** to `dainstall.php`, `init/` copies, inert root, **`about.php` in the zip root**, **`.hooks` in the zip root** when the module fires `module.{this}.*` hooks ([41](41-MODULE-HOOKS.md)), **no** `install.php` in the zip (DACore rejects it / will not run Installation); working tree restored ([00](00-AGENT-CONTRACT.md) §2e)
- [ ] Every version callback starts with `Installations@exist!` and ends with `Installations@insert!`
- [ ] `about.php` exists in the module root; changelog keys **match** `Installation.php`; new versions were **asked** if the user did not supply notes ([35](35-DACORE-INSTALL.md) §3b)
- [ ] Pack/host discovery: extras **asked** when this module is a pack or a host that picks packs; tokens match the host contract ([35](35-DACORE-INSTALL.md) §3c)
- [ ] `exist!` / `insert!` use the `!` suffix (instance methods)
- [ ] Rights created before the menu that references them
- [ ] Installer-managed user groups use `Roles@createGroup!` after every referenced right exists
- [ ] Each installer group has a stable `(creator, groupid)` identity; no saved numeric role id
- [ ] Module that creates accounts: checked `registerOrigin` (`ok === true`, positive id) in install; create does exact bound id lookup + `stampOrigin` + `read` token/id verification; uninstall checks `removeOrigin` and surfaces/stops on refusal ([42](42-DACORE-USER-ORIGIN.md))
- [ ] `Roles@createGroup!` / `assignGroup!` / `removeGroup!` / `deleteGroup!` return values checked
- [ ] `Menu@register` return value checked (`!== true`)
- [ ] `Rights@createGroup!` / `createRight!` checked for `null`
- [ ] Menu rights JSON contains `dotapp.root`
- [ ] AI tool `rights` non-empty and wildcard-free
- [ ] `dacore_ai_tools` verified to exist before registering tools
- [ ] Uninstaller removes tools, rights, menu rows and your tables
- [ ] Menu uninstall deletes only **your** `menuid` prefix (not a host module’s `LIKE 'Other.%'`) ([31](31-DACORE-MENU.md))
- [ ] New module: user was **asked** shared vs module-own; many items are grouped (`type => 2`) or module-own ([31](31-DACORE-MENU.md))
- [ ] Every `execute()` has both callbacks ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md))
