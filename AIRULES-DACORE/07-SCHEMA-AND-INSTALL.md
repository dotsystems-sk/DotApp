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

**MUST:** `$qb->raw()` treats **every** `?` as a placeholder, including SQL comments and `COMMENT 'SMS?'`. **MUST NOT** put `?` in CREATE/ALTER strings unless it is a real binding. Canonical: [06-DATABASE.md](06-DATABASE.md) “Raw SQL”.

**MUST:** `installer()` / `uninstaller()` keys run in **written PHP array order**. **MUST NOT** `ksort` / `uksort` / `krsort` / `usort` those maps (`1.0.10` sorts before `1.0.9`). Canonical: [00](00-AGENT-CONTRACT.md) §5 item 26.

**MUST (DACore zip):** every `installer()` / `uninstaller()` key **MUST** be quoted text in the source (`'1.0.0' =>` / `"1.0.0" =>`). **MUST NOT** `self::…`, `static::…`, class constants, variables, or any other expression as the **key** — DACore greps `Installation.php` as text and rejects a zip with no quoted keys (`Installation.php has no version keys`, package version `0.0.0`). Constants belong **inside** the callback. Canonical: [00](00-AGENT-CONTRACT.md) §5 item 27, [35](35-DACORE-INSTALL.md) §2.

**MUST:** callback return values do not stop `Installer::install()` / `uninstall()`. A critical failure reports to the module catch bus and throws a generic `RuntimeException`; `return false` or a bare `return` after failure is a silent partial install/uninstall. DACore modules additionally follow [35](35-DACORE-INSTALL.md) “Installer/uninstaller failure propagation”.

**MUST (older MySQL):** installer DDL is **probe-then-CREATE/ALTER**. **MUST NOT** emit `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS`, or `CREATE INDEX IF NOT EXISTS`. Older MySQL rejects those `IF NOT EXISTS` forms; MariaDB-only column syntax must not ship. Canonical: [this file §0](#0-mysql-safe-installer-ddl-must--law), [00](00-AGENT-CONTRACT.md) §5 item 30.

---

## 0. MySQL-safe installer DDL (MUST — law)

Idempotency is a **PHP probe**, not an `IF NOT EXISTS` clause. DACore’s installer does the same: `SHOW TABLES LIKE` / `information_schema` first, then `CREATE TABLE` / `ALTER TABLE` only when the object is missing (`SetupGuard::mysqlTableExists` / `mysqlColumnExists`, `addColumnIfMissing`, `addIndexIfMissing`). Copy that **shape** into **your** `Installation.php`. **MUST NOT** call DACore `SetupGuard` / `SchemaCompat` from another module.

| Object | Probe (MUST) | Then |
|--------|----------------|------|
| Table | `SHOW TABLES LIKE 'shop_items'` after identifier whitelist `[A-Za-z0-9_]+` | `CREATE TABLE \`shop_items\` (...)` — **no** `IF NOT EXISTS` |
| Column | `information_schema.COLUMNS` scoped to `DATABASE()`, bound `TABLE_NAME` + `COLUMN_NAME` | `ALTER TABLE ... ADD COLUMN ...` — **no** `IF NOT EXISTS` (`ADD COLUMN IF NOT EXISTS` is MariaDB-only) |
| Index | `information_schema.STATISTICS` scoped to `DATABASE()`, bound names | `ALTER TABLE ... ADD KEY ...` — **no** `IF NOT EXISTS` (`ADD INDEX IF NOT EXISTS` is not portable) |

**MUST NOT** in installer / store `ensureTable` SQL:

- `CREATE TABLE IF NOT EXISTS`
- `ADD COLUMN IF NOT EXISTS`
- `ADD INDEX IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS`

**Allowed:** `DROP TABLE IF EXISTS` on uninstall (widely supported). SchemaBuilder `$qb->createTableIfNotExist()` is allowed — it already probes `tableExists()` and emits `CREATE TABLE` **without** `IF NOT EXISTS`.

**MUST:** helpers live in **your** module. Table/column/index names are whitelist-only (`/^[A-Za-z0-9_]+$/`); refuse anything else. Catch-bus every probe/`execute` failure.

```php
private static function mysqlTableExists(string $table): bool
{
    // About: SHOW TABLES LIKE — older MySQL has no reliable CREATE TABLE IF NOT EXISTS.
    // Why: whitelist so a crafted name cannot change the SQL shape.
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        return false;
    }
    $rows = [];
    $ok = false;
    DB::module('RAW')->q(function ($qb) use ($table) {
        $qb->raw('SHOW TABLES LIKE \'' . $table . '\'', []);
    })->execute(
        function ($result) use (&$ok, &$rows) {
            $ok = true;
            $rows = is_array($result) ? $result : [];
        },
        function ($error) use (&$ok) {
            CatchBus::reportDb($error);
            $ok = false;
        }
    );
    return $ok === true && $rows !== [];
}

private static function mysqlColumnExists(string $table, string $column): bool
{
    // About: information_schema probe with bindings — TABLE_SCHEMA = this database only.
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
        return false;
    }
    $rows = DB::module('RAW')->q(function ($qb) use ($table, $column) {
        $qb->raw(
            'SELECT 1 AS ok FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$table, $column]
        );
    })->all();
    return $rows !== [];
}
```

Create only when missing:

```php
if (self::mysqlTableExists('shop_items') !== true) {
    $ok = false;
    DB::module('RAW')->q(function ($qb) {
        $qb->raw(
            'CREATE TABLE `shop_items` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(200) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            []
        );
    })->execute(
        function () use (&$ok) { $ok = true; },
        function ($error) use (&$ok) {
            CatchBus::reportDb($error);
            $ok = false;
        }
    );
    if ($ok !== true) {
        throw new \RuntimeException('Shop installation failed.');
    }
}
```

Sample: [EX-13](examples/EX-13-schema-migrations.md), [EX-D04](examples/EX-D04-dacore-installer.md).

---

## 1. Versioned `Installation.php` (portable, no DACore dependency)

> DACore is installed in this project — for admin modules prefer the DACore tracking in [35](35-DACORE-INSTALL.md). Use the pattern below when the module must also run on a bare framework.

```php
<?php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\Modules\Shop\Libraries\CatchBus;
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Installer;

class Installation extends Installer
{
    public static function installer()
    {
        return [
            '1.0.0' => function () {
                if (self::alreadyDone('1.0.0')) { return; }

                // Why: probe first — older MySQL errors on CREATE TABLE IF NOT EXISTS ([§0](#0-mysql-safe-installer-ddl-must--law)).
                if (self::mysqlTableExists('shop_items') !== true) {
                    $ok = false;
                    DB::module('RAW')->q(function ($qb) {
                        $qb->raw(
                            "CREATE TABLE `shop_items` (
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
                        function () use (&$ok) {
                            $ok = true;
                        },
                        function ($error) use (&$ok) {
                            CatchBus::reportDb($error);
                            $ok = false;
                        }
                    );
                    if ($ok !== true) {
                        throw new \RuntimeException('Shop installation failed.');
                    }
                }
                if (self::markDone('1.0.0') !== true) {
                    throw new \RuntimeException('Shop installation failed.');
                }
            },

            '1.0.1' => function () {
                if (self::alreadyDone('1.0.1')) { return; }
                $ok = false;
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw("ALTER TABLE `shop_items` ADD `price` DECIMAL(10,2) NOT NULL DEFAULT 0", []);
                })->execute(
                    function () use (&$ok) {
                        $ok = true;
                    },
                    function ($error) use (&$ok) {
                        CatchBus::reportDb($error);
                        $ok = false;
                    }
                );
                if ($ok !== true || self::markDone('1.0.1') !== true) {
                    throw new \RuntimeException('Shop installation failed.');
                }
            },
        ];
    }

    public static function uninstaller()
    {
        return [
            '1.0.0' => function () {
                $ok = false;
                DB::module('RAW')->q(function ($qb) {
                    $qb->raw('DROP TABLE IF EXISTS `shop_items`', []);
                })->execute(
                    function () use (&$ok) {
                        $ok = true;
                    },
                    function ($error) use (&$ok) {
                        CatchBus::reportDb($error);
                        $ok = false;
                    }
                );
                if ($ok !== true) {
                    throw new \RuntimeException('Shop uninstall cleanup failed.');
                }
            },
        ];
    }

    // ---- module-owned idempotency (no DACore dependency) ----
    // Copy mysqlTableExists / mysqlColumnExists from §0 into this class.

    private static function ensureTable(): bool
    {
        // Why: same probe-then-CREATE as product tables — no CREATE TABLE IF NOT EXISTS.
        if (self::mysqlTableExists('shop_installations') === true) {
            return true;
        }
        $ok = false;
        DB::module('RAW')->q(function ($qb) {
            $qb->raw(
                "CREATE TABLE `shop_installations` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `installation_id` VARCHAR(100) NOT NULL,
                    `installed_at` DATETIME NOT NULL,
                    `status` TINYINT(1) NOT NULL DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `ver` (`installation_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                []
            );
        })->execute(
            function () use (&$ok) {
                $ok = true;
            },
            function ($error) use (&$ok) {
                CatchBus::reportDb($error);
                $ok = false;
            }
        );
        return $ok;
    }

    private static function alreadyDone(string $version): bool
    {
        if (self::ensureTable() !== true) {
            throw new \RuntimeException('Shop installation failed.');
        }
        $rows = DB::module('RAW')->q(function ($qb) use ($version) {
            $qb->raw(
                'SELECT 1 AS ok FROM `shop_installations` WHERE `installation_id` = :v AND `status` = 1 LIMIT 1',
                ['v' => $version]
            );
        })->all();
        return !empty($rows);
    }

    private static function markDone(string $version): bool
    {
        $ok = false;
        DB::module('RAW')->q(function ($qb) use ($version) {
            $qb->insert('shop_installations', [
                'installation_id' => $version,
                'installed_at' => date('Y-m-d H:i:s'),
                'status' => 1,
            ]);
        })->execute(
            function () use (&$ok) {
                $ok = true;
            },
            function ($error) use (&$ok) {
                CatchBus::reportDb($error);
                $ok = false;
            }
        );
        return $ok;
    }
}
```

### Running migrations

```php
Installation::module('Shop')->install();          // all keys, written order
Installation::module('Shop')->install('1.0.1');   // keys with version_compare <= 1.0.1, still written order
Installation::module('Shop')->uninstall();        // reverse of uninstaller() written order
```

**MUST — installer key order (law):** `install()` is `foreach` on `installer()` **as you wrote the keys**. **MUST NOT** `ksort`, `uksort`, `krsort`, or `usort` that map (or a copy of it). PHP string sort runs `1.0.10` **before** `1.0.9`, so an origin-catalog step can ALTER `AFTER origin` before the origin column exists. Append the next version **after** the last key. `uninstall()` reverses that written order (`array_reverse`, keep keys) — **MUST NOT** `krsort`. An optional `$version` argument may **skip** keys with `version_compare`; it **MUST NOT** `break` as if the map were sorted. Canonical: [00](00-AGENT-CONTRACT.md) §5 item 26.

**MUST — installer keys are quoted text (DACore zip):** every `installer()` / `uninstaller()` key is `'1.0.0' =>` / `"1.0.0" =>` in the file. **MUST NOT** `self::` / `static::` / constants / variables as a key. Canonical: [00](00-AGENT-CONTRACT.md) §5 item 27, [35](35-DACORE-INSTALL.md) §2.

### One-shot `install.php`

```php
<?php
use Dotsystems\App\Modules\Shop\Installation;
Installation::module('Shop')->install();
```

The framework runs `install.php` once (event `dotapp.module.Shop.install`) then renames it to `installed_<md5>_install.php`. **Real idempotency still comes from `Installations@exist!` / your version table** — the rename only prevents repeat execution.

**Develop with `install.php`.** Do **not** use `dainstall.php` while coding.

**DACore zip is only for modules built for DACore.** If this module is **not** for DACore: **MUST NOT** create `dainstall.php`, `init/`, or a pack zip. To take it to another project: rename `installed_*_install.php` → `install.php` and copy the module folder. The other project’s next page load runs it.

If the module **is** for DACore: pack **only** when the user asks for an installable zip, using the handbook packer [EX-D09](examples/EX-D09-dacore-pack-zip.md) (copy `.txt` → `dacore-pack-zip.php` → run → delete). **MUST NOT** invent a packer. **MUST** rename `install.php` → `dainstall.php` in that zip and put live init in `init/` — a zip that still has `install.php` is **rejected** and Installation **never runs**. **MUST NOT** apply that pack step to `app/modules/DACore/` itself. Canonical: [00](00-AGENT-CONTRACT.md) §2e, [35](35-DACORE-INSTALL.md) §4–§5.

**MUST (new version / migration):** after you add or change a version in `Installation.php`, **rename** `installed_*_install.php` back to `install.php`. The agent does this — **MUST NOT** leave it for the user. If `install.php` is already in the root, leave it.

```powershell
Rename-Item -Path .\installed_*_install.php -NewName install.php
```

---

## 2. SchemaBuilder (programmatic DDL)

```php
DB::module('RAW')->schema(
    function ($qb) {
        // Why: createTableIfNotExist probes tableExists() then emits CREATE TABLE without IF NOT EXISTS.
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

**MUST:** design the indexes for the queries you wrote — every FK plus every `WHERE` / `JOIN` / `ORDER BY` column on a table that grows; composite order **equality → range → sort**; one comment line above each index naming the query it serves. `index()` / `unique()` accept an **array** for composite keys. **Your** tables only — never `dacore_*`. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §3–§4.

`primaryKey($columns, $name = null)`, `dropPrimaryKey()`, `foreign($column, $name = null)` → `->references($col)->on($table)->onDelete($action)->onUpdate($action)`, `dropForeign($name)`, `index($columns, $name = null)`, `unique($columns, $name = null)`, `fullTextIndex($columns, $name = null)` (mysql/pgsql), `dropIndex($name)`, `addConstraint`, `dropConstraint`

### Alter helpers

`addColumn($name, $type, $length = null, $nullable = false, $default = null, $comment = null)`, `dropColumn($name)`, `modifyColumn(...)`

### Table options / introspection

`engine($engine)` (MySQL), `charset($c)`, `collation($c)`, `getDbType()`

```php
$sb = DB::schemaBuilder();
// Why: raw installer SQL still MUST probe with SHOW TABLES LIKE ([§0](#0-mysql-safe-installer-ddl-must--law)).
// SchemaBuilder tableExists() is SELECT 1 FROM table (error = missing). DACore uses SHOW TABLES LIKE.
if (!$sb->tableExists('shop_items')) { /* create — CREATE TABLE, not CREATE TABLE IF NOT EXISTS */ }
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

DACore-bound admin modules **MUST** also ship root `about.php` (installer preview). After install, DACore stores sanitised HTML **and** optional `extra1`…`extra5` flags in `dacore_modules` — do not `include` the live module to show it or to pick a template. Canonical: [35](35-DACORE-INSTALL.md) §3b–§3c.

Every module that fires `module.{thismodule}.*.hook` events **MUST** keep **`.hooks`** in the module root (Markdown body, not a public page). Canonical: [41](41-MODULE-HOOKS.md).
