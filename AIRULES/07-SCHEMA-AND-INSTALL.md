# 07 — Schema and Module Installation

## Mechanisms

| Mechanism | Role |
|-----------|------|
| `Installation.php` extending `Installer` | Versioned module migrations — **preferred** |
| `install.php` → renamed `installed_<hash>_install.php` | One-shot bootstrap. After a **new** version, rename back to `install.php` so the next load runs it |
| `SchemaBuilder` via `createTable` / `alterTable` | Programmatic DDL |
| `--prepare-database` / `initializedb.php` | Core users/auth SQL bootstrap |
| `.sql` files | Documentation / DBA — **not** auto-executed |

`DB::migrate()` is declared but **not implemented in any driver** — never call it.

**MUST:** `$qb->raw()` treats **every** `?` as a placeholder, including SQL comments and `COMMENT 'SMS?'`. **MUST NOT** put `?` in CREATE/ALTER strings unless it is a real binding. Canonical: [06-DATABASE.md](06-DATABASE.md) “Raw SQL”.

**MUST:** `installer()` / `uninstaller()` keys run in **written PHP array order**. **MUST NOT** `ksort`, `uksort`, `krsort`, or `usort` those maps (`1.0.10` sorts before `1.0.9`). Canonical: [00](00-AGENT-CONTRACT.md) §5.

**MUST (older MySQL):** installer DDL is **probe-then-CREATE/ALTER**. **MUST NOT** emit `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS`, or `CREATE INDEX IF NOT EXISTS`. Older MySQL rejects those `IF NOT EXISTS` forms; MariaDB-only column syntax must not ship. Canonical: [this file §0](#0-mysql-safe-installer-ddl-must--law), [00](00-AGENT-CONTRACT.md) §5 item 24.

---

## 0. MySQL-safe installer DDL (MUST — law)

Idempotency is a **PHP probe**, not an `IF NOT EXISTS` clause. `SHOW TABLES LIKE` / `information_schema` first, then `CREATE TABLE` / `ALTER TABLE` only when the object is missing. Copy that **shape** into **your** `Installation.php`.

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

**MUST:** helpers live in **your** module. Table/column/index names are whitelist-only (`/^[A-Za-z0-9_]+$/`); refuse anything else. Catch-bus every probe/`execute` failure ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9).

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
            Logger::use()->error('SHOW TABLES', $error);
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
            Logger::use()->error('CREATE shop_items', $error);
            $ok = false;
        }
    );
    if ($ok !== true) {
        throw new \RuntimeException('Shop installation failed.');
    }
}
```

Sample: [EX-13](examples/EX-13-schema-migrations.md).

---

## 1. Versioned `Installation.php` (DACore-free)

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
                        function () use (&$ok) { $ok = true; },
                        function ($error) use (&$ok) {
                            Logger::use()->error('Shop 1.0.0 failed', $error);
                            $ok = false;
                        }
                    );
                    if ($ok !== true) {
                        return;
                    }
                }
                self::markDone('1.0.0');
            },

            '1.0.1' => function () {
                if (self::alreadyDone('1.0.1')) { return; }
                // Why: ADD COLUMN IF NOT EXISTS is MariaDB-only — probe information_schema first.
                if (self::mysqlColumnExists('shop_items', 'price') !== true) {
                    $ok = false;
                    DB::module('RAW')->q(function ($qb) {
                        $qb->raw("ALTER TABLE `shop_items` ADD `price` DECIMAL(10,2) NOT NULL DEFAULT 0", []);
                    })->execute(
                        function () use (&$ok) { $ok = true; },
                        function ($error) use (&$ok) {
                            Logger::use()->error('Shop 1.0.1 failed', $error);
                            $ok = false;
                        }
                    );
                    if ($ok !== true) {
                        return;
                    }
                }
                self::markDone('1.0.1');
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
    // Copy mysqlTableExists / mysqlColumnExists from §0 into this class.

    private static function ensureTable(): void
    {
        // Why: same probe-then-CREATE as product tables — no CREATE TABLE IF NOT EXISTS.
        if (self::mysqlTableExists('shop_installations') === true) {
            return;
        }
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
Installation::module('Shop')->install();          // all keys, written order
Installation::module('Shop')->install('1.0.1');   // version_compare filter, still written order
Installation::module('Shop')->uninstall();        // reverse of uninstaller() written order
```

**MUST — installer key order:** `install()` uses `foreach` on `installer()` exactly as the keys were written. Append each new version after the previous key. **MUST NOT** sort that map (or a copy) with `ksort`, `uksort`, `krsort`, or `usort`: string sorting places `1.0.10` before `1.0.9` and can run dependent schema steps in the wrong order. `uninstall()` reverses written order with `array_reverse` while preserving keys; it **MUST NOT** `krsort`. A requested target version may skip keys via `version_compare`; it **MUST NOT** `break` as if the map were sorted.

### One-shot `install.php`

```php
<?php
use Dotsystems\App\Modules\Shop\Installation;
Installation::module('Shop')->install();
```

The framework runs it once (event `dotapp.module.Shop.install`) then renames it to `installed_<md5>_install.php`. **Real idempotency still comes from your installations table** — the rename only prevents repeat execution.

**MUST (new version / migration):** after you add or change a version in `Installation.php`, **rename** `installed_*_install.php` back to `install.php` so the next web load runs it. The agent does this — **MUST NOT** leave it for the user. Already-applied versions stay skipped by `exist()` / your installations table. If `install.php` is already in the root, leave it.

```powershell
# module root, e.g. app/modules/Shop/
Rename-Item -Path .\installed_*_install.php -NewName install.php
```

**Copying to another project (no DACore zip).** There is **no** `dainstall.php` pack for a bare-framework module. The user copies `app/modules/Shop/` themselves. Before copy, the trigger file **MUST** be named `install.php` (rename `installed_*_install.php` if needed). The other project’s next page load runs it. Do **not** invent a DACore installer zip.

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

**MUST:** design the indexes for the queries you wrote — every FK plus every `WHERE` / `JOIN` / `ORDER BY` column on a table that grows; composite order **equality → range → sort**; one comment line above each index naming the query it serves. `index()` / `unique()` accept an **array** for composite keys. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §3–§4.

`primaryKey($columns, $name = null)`, `dropPrimaryKey()`, `foreign($column, $name = null)` → `->references($col)->on($table)->onDelete($action)->onUpdate($action)`, `dropForeign($name)`, `index($columns, $name = null)`, `unique($columns, $name = null)`, `fullTextIndex($columns, $name = null)` (mysql/pgsql), `dropIndex($name)`, `addConstraint`, `dropConstraint`

### Alter helpers

`addColumn($name, $type, $length = null, $nullable = false, $default = null, $comment = null)`, `dropColumn($name)`, `modifyColumn(...)`

### Table options / introspection

`engine($engine)` (MySQL), `charset($c)`, `collation($c)`, `getDbType()`

```php
$sb = DB::schemaBuilder();
// Why: raw installer SQL still MUST probe with SHOW TABLES LIKE ([§0](#0-mysql-safe-installer-ddl-must--law)).
if (!$sb->tableExists('shop_items')) { /* create — CREATE TABLE, not CREATE TABLE IF NOT EXISTS */ }
$sb->columnExists('shop_items', 'price');
$sb->indexExists('shop_items', 'active_idx');
$sb->foreignKeyExists('shop_items', 'fk_shop_cat');
```

### SchemaBuilder throws

`\InvalidArgumentException` on: invalid identifier, unsupported type for the engine, `unsigned()` on non-MySQL, `set()` on non-MySQL, `json()` on unsupported engine, SQLite `dropColumn`/`dropIndex`/`dropForeign`/`modifyColumn`, missing FK target. **Always wrap DDL in `try/catch`.**

---

## 3. Table naming (**MUST**)

**MUST:** Every table owned by a module is named `{lowercase_modulename}_*` (module `Shop` → `shop_items`, `shop_installations`). Never create unprefixed tables (`items`) or put module data under `dotapp_*`. Core auth tables use `Config::db('prefix')` only.

| Prefix | Owner |
|--------|-------|
| `Config::db('prefix')` (default `dotapp_`) | Core users/auth tables only |
| `{lowercase_modulename}_` | **All** tables your module creates |

| Wrong | Right (module `Shop`) |
|-------|------------------------|
| `items`, `orders` | `shop_items`, `shop_orders` |
| `Shop_items` | `shop_items` |
| `dotapp_items` | `shop_items` |

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

## 6. DACore note (Part 2)

If DACore is installed, modules may track installs via `DotApp::call('DACore:Installations@exist!', ...)` / `@insert!`. **Part 1 code must not depend on it** — use the module-owned table above.
