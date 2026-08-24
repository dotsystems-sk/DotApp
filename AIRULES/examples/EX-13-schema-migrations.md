# EX-13 — Schema and migrations (SchemaBuilder + raw DDL)

Rules: [07-SCHEMA-AND-INSTALL.md](../07-SCHEMA-AND-INSTALL.md).

**MUST:** module tables `{lowercase_modulename}_*` (Shop → `shop_items`). Never unprefixed names or `dotapp_*`.

**MUST:** raw installer SQL probes first (`SHOW TABLES LIKE` / `information_schema`), then `CREATE TABLE` / `ALTER TABLE` **without** `IF NOT EXISTS` ([07](../07-SCHEMA-AND-INSTALL.md) §0).

After you add a version in `Installation.php`, **rename** `installed_*_install.php` back to `install.php` so the next page load runs it. Do not leave that step for the user. To copy the module to another project (no DACore): keep `install.php` and copy the folder.

## SchemaBuilder — wrap DDL in try/catch (it throws)

```php
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Logger;

try {
    DB::module('RAW')->schema(
        function ($qb) {
            // Why: createTableIfNotExist probes tableExists() then emits CREATE TABLE without IF NOT EXISTS.
            $qb->createTableIfNotExist('shop_items', function ($t) {
                $t->id();                                       // BIGINT AUTO_INCREMENT PK
                $t->string('title', 200)->nullable(false);
                $t->string('slug', 220)->nullable(false);
                $t->decimal('price', 10, 2)->default(0);
                $t->integer('sold')->unsigned()->default(0);    // unsigned = MySQL only
                $t->boolean('active')->default(1);
                $t->text('description')->nullable();
                $t->json('meta')->nullable();                   // throws on unsupported engines
                $t->enum('state', ['draft', 'live', 'archived'])->default('draft');
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable()->onUpdateCurrentTimestamp();

                $t->unique('slug', 'shop_items_slug_unique');
                $t->index(['active', 'sold'], 'shop_items_active_sold');
                $t->engine('InnoDB');
                $t->charset('utf8mb4');
            });
        },
        function () { Logger::use()->info('shop_items ready'); },
        function ($error) { Logger::use()->error('schema failed', (array) $error); }
    );
} catch (\Throwable $e) {
    Diag::reportCatch('Shop:Installation@v3', 'shop.schema.migrate', $e, 'error', ['version' => 3]);
}
```

**`timestamps()` does not exist** — declare `created_at` / `updated_at` yourself.

## Foreign keys

```php
$qb->createTableIfNotExist('shop_order_items', function ($t) {
    $t->id();
    $t->bigInteger('order_id')->unsigned();
    $t->bigInteger('item_id')->unsigned();
    $t->integer('qty')->default(1);

    $t->foreign('order_id', 'fk_soi_order')->references('id')->on('shop_orders')
      ->onDelete('CASCADE')->onUpdate('RESTRICT');
    $t->index('item_id');
});
```

## Introspection before altering

```php
$sb = DB::schemaBuilder();

if ($sb->tableExists('shop_items') && !$sb->columnExists('shop_items', 'price')) {
    DB::module('RAW')->q(function ($qb) {
        $qb->raw('ALTER TABLE `shop_items` ADD `price` DECIMAL(10,2) NOT NULL DEFAULT 0', []);
    })->execute(null, function ($e) { Logger::use()->error('alter failed', $e); });
}

$sb->indexExists('shop_items', 'shop_items_slug_unique');
$sb->foreignKeyExists('shop_order_items', 'fk_soi_order');
```

## SchemaBuilder throws on

Invalid identifiers, unsupported types for the engine (`json`, `set`), `unsigned()` outside MySQL, SQLite `dropColumn` / `dropIndex` / `dropForeign` / `modifyColumn` / `dropPrimaryKey`, missing foreign-key target table.

## Raw DDL alternative (portable, explicit)

**MUST NOT** put `?` in `$qb->raw()` unless it is a real binding. Comments and `COMMENT 'SMS?'` count as placeholders — the CREATE never runs. Write “SMS optional”. Canonical: [06](../06-DATABASE.md).

```php
if (self::mysqlTableExists('shop_tags') !== true) {
    DB::module('RAW')->q(function ($qb) {
        $qb->raw(
            "CREATE TABLE `shop_tags` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name_unique` (`name`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );
    })->execute(
        function () { /* ok */ },
        function ($error) { Logger::use()->error('create table failed', $error); }
    );
}
```

## Never

```php
// DB::migrate();  // declared but NOT implemented by any driver
```

Use versioned `Installation.php` with your module-owned installations table — see [07-SCHEMA-AND-INSTALL.md](../07-SCHEMA-AND-INSTALL.md).
