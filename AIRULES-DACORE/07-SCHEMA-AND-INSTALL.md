# 07 — Schema and Module Installation

## Mechanisms

| Mechanism | Role |
|-----------|------|
| `Installation.php` extending `Installer` | Versioned module migrations — **preferred** |
| `install.php` → renamed `installed_<hash>_install.php` | **Development** trigger. After a **new** version, rename back to `install.php`. DACore zip (`dainstall.php`) only if the module is **for DACore** and the user asks ([35](35-DACORE-INSTALL.md) §4–§5) |
| `dainstall.php` | **Packed DACore zip only** — and only if this module is **for DACore**. Not while coding. Not for a bare-framework module. **Not** for `app/modules/DACore/` itself |
| `SchemaBuilder` via `createTable` / `alterTable` | Programmatic DDL |
| `--prepare-database` / `initializedb.php` | Core users/auth SQL bootstrap |
| `.sql` files | Documentation / DBA — **not** auto-executed |

`DB::migrate()` is declared but **not implemented in any driver** — never call it.

---

## 1. Versioned `Installation.php` (portable, no DACore dependency)

> DACore is installed in this project — for admin modules prefer the DACore tracking in [35](35-DACORE-INSTALL.md). Use the pattern below when the module must also run on a bare framework.

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Installer;
use Dotsystems\App\Parts\Logger;

class Installation extends Installer
{
    public static function installer()
    {
        return [
            '1.0.0' => function () {
                if (self::alreadyDone('1.0.0')) { return; }

                DB::module('RAW')->q(function ($qb) {
                    $qb->raw(
                        "CREATE TABLE IF NOT EXISTS `shop_items` (
                            `id` INT NOT NULL AUTO_INCREMENT,
                            `title` VARCHAR(200) NOT NULL,
                            `active` TINYINT(1) NOT NULL DEFAULT 1,
                            `created_at` DATETIME NOT NULL,
                            PRIMARY KEY (`id`),
                            KEY `active_idx` (`active`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        []
                    );
                })->execute(
                    function () { self::markDone('1.0.0'); },
                    function ($error) {
                        Logger::use()->error('Shop 1.0.0 failed', $error);
                    }
                );
            },

            '1.0.1' => function () {
                if (self::alreadyDone('1.0.1')) { return; }
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw("ALTER TABLE `shop_items` ADD `price` DECIMAL(10,2) NOT NULL DEFAULT 0", []);
                })->execute(
                    function () { self::markDone('1.0.1'); },
                    function ($error) { Logger::use()->error('Shop 1.0.1 failed', $error); }
                );
            },
        ];
    }

    public static function uninstaller()
    {
        return [
            '1.0.0' => function () {
                DB::module('RAW')->q(fn($qb) => $qb->raw('DROP TABLE IF EXISTS `shop_items`', []))
                    ->execute(null, function ($e) { Logger::use()->error('drop failed', $e); });
            },
        ];
    }

    // ---- module-owned idempotency (no DACore dependency) ----

    private static function ensureTable(): void
    {
        DB::module('RAW')->q(function ($qb) {
            $qb->raw(
                "CREATE TABLE IF NOT EXISTS `shop_installations` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `installation_id` VARCHAR(100) NOT NULL,
                    `installed_at` DATETIME NOT NULL,
                    `status` TINYINT(1) NOT NULL DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `ver` (`installation_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                []
            );
        })->execute(null, function ($e) { Logger::use()->error('installations table', $e); });
    }

    private static function alreadyDone(string $version): bool
    {
        self::ensureTable();
        $rows = DB::module('RAW')->q(function ($qb) use ($version) {
            $qb->raw(
                'SELECT 1 AS ok FROM `shop_installations` WHERE `installation_id` = :v AND `status` = 1 LIMIT 1',
                ['v' => $version]
            );
        })->all();
        return !empty($rows);
    }

    private static function markDone(string $version): void
    {
        DB::module('RAW')->q(function ($qb) use ($version) {
            $qb->insert('shop_installations', [
                'installation_id' => $version,
                'installed_at' => date('Y-m-d H:i:s'),
                'status' => 1,
            ]);
        })->execute(null, function ($e) { Logger::use()->error('markDone', $e); });
    }
}
```

### Running migrations

```php
Installation::module('Shop')->install();          // all versions, ascending
Installation::module('Shop')->install('1.0.1');   // up to and including 1.0.1
Installation::module('Shop')->uninstall();        // descending
```

Ordering: `install()` sorts keys ascending and stops when `version_compare($ver, $target, '<=')` fails. `uninstall()` runs descending with `>=`.

### One-shot `install.php`

```php
<?php
use Dotsystems\App\Modules\Shop\Installation;
Installation::module('Shop')->install();
```

The framework runs `install.php` once (event `dotapp.module.Shop.install`) then renames it to `installed_<md5>_install.php`. **Real idempotency still comes from `Installations@exist!` / your version table** — the rename only prevents repeat execution.

**Develop with `install.php`.** Do **not** use `dainstall.php` while coding.

**DACore zip is only for modules built for DACore.** If this module is **not** for DACore: **MUST NOT** create `dainstall.php`, `init/`, or a pack zip. To take it to another project: rename `installed_*_install.php` → `install.php` and copy the module folder. The other project’s next page load runs it.

If the module **is** for DACore: pack `dainstall.php` **only** when the user asks for an installable zip ([35](35-DACORE-INSTALL.md) §4–§5). **MUST NOT** apply that pack step to `app/modules/DACore/` itself.

**MUST (new version / migration):** after you add or change a version in `Installation.php`, **rename** `installed_*_install.php` back to `install.php`. The agent does this — **MUST NOT** leave it for the user. If `install.php` is already in the root, leave it.

```powershell
Rename-Item -Path .\installed_*_install.php -NewName install.php
```

---

## 2. SchemaBuilder (programmatic DDL)

```php
DB::module('RAW')->schema(
    function ($qb) {
        $qb->createTableIfNotExist('shop_tags', function ($t) {
            $t->id();                                  // BIGINT AUTO_INCREMENT PK
            $t->string('name', 100)->nullable(false);
            $t->integer('sort')->default(0)->unsigned();
            $t->text('description')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index('name');
            $t->unique('name', 'tag_name_unique');
            $t->engine('InnoDB');
        });
    },
    function () { /* ok */ },
    function ($error) { /* fail */ }
);
```

### Column helpers (these exist)

`id($name='id', $defaultType='BIGINT')`, `string($name, $length=255)`, `integer`, `tinyInteger`, `bigInteger`, `boolean`, `decimal($name, $precision=10, $scale=2)`, `float`, `text`, `json`, `enum($name, array $values)`, `set($name, array $values)` (MySQL only), `timestamp`, `datetime`, `date`

**`timestamps()` does NOT exist** — declare `created_at` / `updated_at` manually.

### Column modifiers (chained on the column)

`nullable($bool = true)`, `default($value)`, `comment($text)`, `autoIncrement()`, `unsigned()` (MySQL only), `length($n)`, `precision($n)`, `scale($n)`, `onUpdateCurrentTimestamp()`

### Keys / indexes / constraints

`primaryKey($columns, $name = null)`, `dropPrimaryKey()`, `foreign($column, $name = null)` → `->references($col)->on($table)->onDelete($action)->onUpdate($action)`, `dropForeign($name)`, `index($columns, $name = null)`, `unique($columns, $name = null)`, `fullTextIndex($columns, $name = null)` (mysql/pgsql), `dropIndex($name)`, `addConstraint`, `dropConstraint`

### Alter helpers

`addColumn($name, $type, $length = null, $nullable = false, $default = null, $comment = null)`, `dropColumn($name)`, `modifyColumn(...)`

### Table options / introspection

`engine($engine)` (MySQL), `charset($c)`, `collation($c)`, `getDbType()`

```php
$sb = DB::schemaBuilder();
if (!$sb->tableExists('shop_items')) { /* create */ }
$sb->columnExists('shop_items', 'price');
$sb->indexExists('shop_items', 'active_idx');
$sb->foreignKeyExists('shop_items', 'fk_shop_cat');
```

### SchemaBuilder throws

`\InvalidArgumentException` on: invalid identifier, unsupported type for the engine, `unsigned()` on non-MySQL, `set()` on non-MySQL, `json()` on unsupported engine, SQLite `dropColumn`/`dropIndex`/`dropForeign`/`modifyColumn`, missing FK target. **Always wrap DDL in `try/catch`.**

---

## 3. Table naming (**MUST**)

**MUST:** Every table owned by a module is named `{lowercase_modulename}_*` (module `Shop` → `shop_items`, `shop_installations`). Never create unprefixed tables (`items`) or put module data under `dotapp_*`. Core auth tables use `Config::db('prefix')` only. Never name your tables `dacore_*` — that prefix belongs to DACore.

| Prefix | Owner |
|--------|-------|
| `Config::db('prefix')` (default `dotapp_`) | Core users/auth tables only |
| `{lowercase_modulename}_` | **All** tables your module creates |
| `dacore_` | DACore only — do not create tables with this prefix |

| Wrong | Right (module `Shop`) |
|-------|------------------------|
| `items`, `orders` | `shop_items`, `shop_orders` |
| `Shop_items` | `shop_items` |
| `dotapp_items` | `shop_items` |
| `dacore_shop_items` | `shop_items` |

Never write to another module's tables without a documented public API.

---

## 4. Installing external modules

```powershell
php .\dotapper.php --install-module=https://github.com/org/repo:1.0.0 --force --github-token=ghp_xxx
```

Under the hood `Installer::installModule($value, $version, $options)` returns:

```php
['success' => bool, 'error_code' => int, 'error_message' => string, 'module_name' => string]
```

Notable error codes: `1-3` invalid URL/tag, `5` migration failure, `7-9` prerequisites (missing `index.php`, unwritable `app/modules`), `10-19` download/extract, `20` user declined, `21-24` global listener issues.

`$options`: `force` (bool), `github_token` (string).

---

## 5. Global listeners file

`Installer::registerGlobalListener($event, $listener)` edits `app/listeners.php` (lock-protected) into:

```php
<?php
return [
  'Shop' => [
    'dotapp.modules.loaded' => ['Shop:Boot@run'],
  ],
];
```

Valid listener formats: `module` or `module:Controller@function`. Prefer your own `module.listeners.php` instead — editing `app/listeners.php` is an **ask-first** action per [00-AGENT-CONTRACT.md](00-AGENT-CONTRACT.md).

---

## 6. DACore is installed — use its tracking instead

In this project DACore is present, so **prefer** its install log over the module-owned table above:

```php
if (DotApp::call("DACore:Installations@exist!", 'Shop', '1.0.0') === true) { return; }
// ... migrate ...
DotApp::call("DACore:Installations@insert!", 'Shop', '1.0.0', 1, ['outcome' => 'ok']);
```

Full pattern including rights, menu and AI tools: [35-DACORE-INSTALL.md](35-DACORE-INSTALL.md) and [examples/EX-D04](examples/EX-D04-dacore-installer.md).

The module-owned `*_installations` table shown earlier stays valid for modules that must also run on a bare framework.
