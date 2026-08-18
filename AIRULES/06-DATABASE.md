# 06 — Database (complete API + return values)

**Not Eloquent.** Default for new module code: **RAW** via `DB::module("RAW")`.

Read [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md) first — the DB layer has the strictest error contract in the framework.

Architecture: `DB` (facade) → `Databaser` → driver (`pdo` / `mysqli`) → `q()` returns a `QueryObject` wrapping a `QueryBuilder`.

---

## 1. Entry points (`DB` facade)

| Call | Returns | Notes |
|------|---------|-------|
| `DB::module()` | `Databaser` | driver + `Config::db('maindb')` selected |
| `DB::module("RAW")` | `Databaser` | rows as arrays — **recommended** |
| `DB::module("ORM")` | `Databaser` | rows as `Entity` in a `Collection` |
| `DB::module("BAD")` | — | **throws `\Exception`** |
| `DB::default($t)` | alias of `module()` | |
| `DB::isConnected()` | `bool` | `false` on any connection exception |
| `DB::schemaBuilder()` | `SchemaBuilder` | introspection / DDL |

Delegated facade methods: `driver, add, select_db, selectDb, q, qb, schema, migrate, execute, raw, first, all, return, newEntity, newCollection, fetchArray, fetchFirst, cache, inserted_id, affected_rows, transaction, transact, commit, rollback`.

---

## 2. Canonical query pattern

```php
use Dotsystems\App\Parts\DB;

// SELECT many
$rows = DB::module('RAW')
    ->q(function ($qb) use ($limit) {
        $qb->select(['id', 'title'])
           ->from('shop_items')
           ->where('active', '=', 1)
           ->orderBy('id', 'DESC')
           ->limit($limit);
    })
    ->all();                      // array (empty [] when nothing found)

// SELECT one — NEVER use first() unguarded
$rows = DB::module('RAW')
    ->q(fn($qb) => $qb->select('*')->from('shop_items')->where('id','=',$id)->limit(1))
    ->all();
$row = $rows[0] ?? null;
```

`q()` and `qb()` are aliases. Both return the query object; the **terminal** call decides the result.

---

## 3. Terminal methods and their exact returns

| Method | Success return | Empty / failure |
|--------|----------------|-----------------|
| `all()` | RAW: `array` of assoc rows · ORM: `Collection` | `[]` / empty Collection |
| `first()` | RAW: row array · ORM: `Entity` | **UNSAFE** — RAW warns on undefined index, ORM **fatal** |
| `execute($ok, $err)` | driver result | `false` with `$err`, **throws without `$err`** |
| `paginate($perPage = 15, $page = 1, $err = null)` | array (see below) | `data => []` |
| `exists()` | `bool` | `bool` |
| `doesntExist()` | `bool` | `bool` |
| `raw()` | same as `execute()` | same |

### `paginate()` return keys

`data`, `current_page`, `per_page`, `total`, `last_page`, `from`, `to`, `has_more_pages`, `prev_page`, `next_page`

```php
$page = DB::module('RAW')
    ->q(fn($qb) => $qb->select('*')->from('shop_items')->orderBy('id','DESC'))
    ->paginate(20, $currentPage);

foreach ($page['data'] as $row) { /* ... */ }
$totalPages = $page['last_page'];
```

**MUST (accumulating lists):** if the screen lists records that **can grow over time** — users, logs, items, orders, messages, files, events — **MUST** use `paginate()` on the **first** ship (typical `per_page` 20, or a module setting with a fallback). **“There are only three users now” is not an exception.** **MUST NOT** `->all()` the whole table into a view.

Skip a pager **only** when the set is **closed by product design** and will never grow (e.g. four fixed status cards). If you are unsure, paginate.

The pager in the browser is **interactive AJAX** — [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 “Paginate accumulating lists”. **MUST NOT** reload the site with `<a href="?page=2">` / `location.reload()`. A reload pager counts as missing.

### Write with callbacks (mandatory pattern)

```php
DB::module('RAW')
    ->q(function ($qb) use ($data) {
        $qb->insert('shop_items', $data);
    })
    ->execute(
        function ($result, $db, $execution_data) {
            $newId = $execution_data['insert_id'] ?? $db->inserted_id();
        },
        function ($error, $db, $execution_data) {
            // $error['error'] (string), $error['errno']
        }
    );
```

`$execution_data`: `affected_rows`, `insert_id`, `num_rows`, `result`, `query`, `bindings` — **empty array on cache hit**.

---

## 4. QueryBuilder — complete method list

All methods are chainable (`$this`) unless noted.

### Select / source

| Method | Signature |
|--------|-----------|
| `select` | `select($columns = '*', $table = null, $distinct = false)` |
| `distinct` | `distinct()` |
| `from` | `from($table)` — alias via `from('posts p')` |
| `subQuery` | `subQuery()` → **new QueryBuilder** |
| `getQuery` | `getQuery()` → `['query','bindings','types','queryParts']` (terminal) |

There is **no `getBindings()`** — read `getQuery()['bindings']`.

### Where

`where($column, $operator = null, $value = null, $boolean = 'AND')`, `andWhere`, `orWhere`,
`whereIn($column, $values, $boolean = 'AND')`, `orWhereIn`,
`whereBetween($column, array $values, $boolean = 'AND')`, `orWhereBetween`,
`whereNull($column, $boolean = 'AND')`, `whereNotNull`, `orWhereNull`, `orWhereNotNull`

`whereIn` accepts an array, a `QueryBuilder`, or a `Closure` subquery.

### Join

```php
$qb->join('users u', 'p.user_id', '=', 'u.id');   // INNER
$qb->leftJoin('users u', 'p.user_id', '=', 'u.id');
$qb->rightJoin(...);
$qb->fullJoin(...);   // throws on mysql/sqlite
```

Signature: `join($table, $first, $operator = null, $second = null, $type = 'INNER')`.

### Grouping / ordering / paging

| Method | Note |
|--------|------|
| `groupBy($columns)` | |
| `having($column, $operator = null, $value = null, $aggregate = null)` | **only one HAVING stored** (later calls overwrite) |
| `orderBy($column, $direction = 'ASC')` | multiple allowed |
| `limit($limit)` / `offset($offset)` | bound as `?` |
| `resetLimitOffset()` | strips last two bindings (heuristic) |

### Writes

| Method | Signature |
|--------|-----------|
| `insert($table, array $data)` / `insertInto` | |
| `insertGetId($table, array $data, $idColumn = 'id')` | adds RETURNING on pgsql/sqlsrv |
| `onDuplicateKeyUpdate(array $data, $conflictTarget = null)` | upsert (mysql/pgsql/sqlite) |
| `update($table)` + `set(array $data)` + `where(...)` | |
| `delete($table = null)` / `deleteFrom` + `where(...)` | |
| `truncate($table)` | SQLite → `DELETE FROM` |

### Advanced SQL

| Method | Note |
|--------|------|
| `with($name, $queryCallback)` | CTE; **throws on SQLite** |
| `union(QueryBuilder $query, $all = false)` | needs a **QueryBuilder instance**, not a closure |
| `intersect(QueryBuilder $q)` / `except(QueryBuilder $q)` | **throw on mysql/sqlite** |

### DDL from QueryBuilder

`createTable($table, callable)`, `createTableIfNotExist($table, callable)`, `alterTable($table, callable)`, `dropTable($table)` — the callback receives a `SchemaBuilder`.

### Raw SQL

```php
$qb->raw(
    'SELECT * FROM shop_items WHERE title LIKE :q LIMIT :lim',
    ['q' => '%'.$term.'%', 'lim' => 20]
);
```

Rules enforced by code (**they throw**):

- Mixing `?` and `:named` in one statement → `\Exception`
- `?` count ≠ bindings count → `\Exception`
- Missing named binding → `\Exception`

### Not implemented (do not use)

`whereExists`, `whereColumn`, `selectRaw`, `count()` — and on `Databaser`: `whereHas`, `whereDoesntHave`, `withCount`, `with()` are **stubs that never reach SQL**.

For counting use `select('COUNT(*) as total')` + `all()`, or `paginate()['total']`, or `exists()`.

---

## 5. Transactions

```php
DB::module('RAW')->transaction();
try {
    DB::module('RAW')->q(fn($qb) => $qb->insert('a', $x))->execute(null, function ($e) {
        throw new \RuntimeException($e['error']);
    });
    DB::module('RAW')->commit();
} catch (\Throwable $e) {
    DB::module('RAW')->rollback();
    throw $e;
}
```

Callback form:

```php
DB::module('RAW')->transact(
    function ($db, $commitOnSuccess, $rollbackOnError) {
        $db->q(fn($qb) => $qb->insert('a', ['x' => 1]))
           ->execute($commitOnSuccess, $rollbackOnError);
    },
    function ($result, $db, $exec) { /* committed */ },
    function ($error, $db, $exec) { /* rolled back */ }
);
```

`transaction()`, `commit()`, `rollback()`, `transact()` all return `$this`.

---

## 6. ORM: Entity and Collection (optional)

Use only when you deliberately want objects: `DB::module('ORM')`.

### Entity essentials

| Method | Return | Notes |
|--------|--------|-------|
| `save($ok, $err)` | **void** | Callbacks are the only result channel; validation failure **throws** |
| `delete($ok, $err)` | **void** | **throws** without primary key |
| `fill(array $attrs)` | `$this` | |
| `toArray()` | `array` | |
| `get($k)` / `set($k,$v)` | mixed / void | `__get` returns `null` for unknown |
| `table($name)` | void | |
| `setPrimaryKey($k)` / `setRules(array)` | `$this` | |
| `isDirty($attr = null)` / `getDirty()` | `bool` / `array` | |
| `fresh()` | `Entity\|null` | `null` if no PK |
| `replicate(array $except = null)` | new `Entity` | |
| `touch($attr = 'updated_at')` | `$this` | |
| `observe($event, callable)` | `$this` | `creating, saving, updating, saved, created, updated, deleting, deleted` |
| soft deletes | `usesSoftDeletes()`, `setSoftDeletes()`, `trashed()`, `restore()`, `forceDelete()` | |
| `Entity::find($db, $id, $ok, $err)` | **void** (static) | callback style |
| `Entity::create($db, array $attrs, ...)` | `Entity` | |

`validate()` is **private** — it runs inside `save()`. Do not call it.

Relations are **method calls**, not magic properties:

```php
$posts   = $user->hasMany('posts', 'user_id');       // Collection
$profile = $user->hasOne('profiles', 'user_id');     // Entity|null
$author  = $post->belongsTo('users', 'user_id');     // Entity|null
```

Also available: `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`, `morphTo`.

**Cache caveat:** with `Config::db('cache') === true`, `Entity::save()` requires the cache driver to implement `deleteKeys()`. **No shipped driver does** — it will throw. Keep `db.cache` off unless you supply a custom driver.

### Collection (returned by ORM `all()`)

Returns a **new Collection**: `filter, map, pluck, sortBy, sortByDesc, sort, sortDesc, unique, take, skip, search, chunk, diff, intersect, merge, concat, zip, reject, when, unless, tap, nth, push`

Returns **scalar/array**: `all()` (array), `first()` (`Entity|null`), `count()` (int), `toArray()`, `groupBy()` (array of Collections), `partition()` (2 Collections), `reduce`, `avg`, `sum`, `min`, `max`, `contains`, `containsStrict`, `some`, `every`, `find` (`Entity|null`), `paginate()` (array), `pipe`

---

## 7. Multiple connections

```php
// app/config.php
Config::addDatabase('main', '127.0.0.1', 'user', 'pass', 'db', 'UTF8', 'MYSQL', 'pdo');
Config::addDatabase('reporting', '10.0.0.5', 'ro', 'pass', 'reports', 'UTF8', 'MYSQL', 'pdo');

// module code
DB::module('RAW')->selectDb('reporting')->q(fn($qb) => ...)->all();
```

`Config::db(...)` keys: `prefix` (`dotapp_`), `driver` (`pdo`), `maindb` (`main`), `cache` (`false`).

Custom driver: `Databaser::customDriver($name, $class)` where the class exposes `public static function create(Databaser $db)` and registers closures for `select_db, q, return, execute, first, all, raw, fetchArray, fetchFirst, newEntity, newCollection, inserted_id, affected_rows, schema, transaction, transact, commit, rollback`.

---

## 8. Query cache

Enabled by `Config::db('cache') === true`. Key: `"{table}:{returnType}:" . md5($query . serialize($bindings))`, TTL hardcoded **3600 s**. Override the store with `DB::module()->cache($driverObject)` (must expose `get($key)` and `set($key, $value, $lifetime)`).

Remember: cache hits deliver an **empty `$execution_data`**.

---

## 9. Forbidden legacy methods

Do **not** use in new module code — they build SQL by string concatenation:

`query()`, `query_first()`, `insert()` (on `Databaser`), `updateManual()`, `insert_multi()`, `prepare_query_data()`

Also avoid `DB::migrate()` — **no driver implements it**.

---

## 10. Eloquent traps

| Wrong | Right |
|-------|-------|
| `User::find(1)` | `->where('id','=',1)->all()` then `[0] ?? null` |
| `DB::table('users')->get()` | `DB::module('RAW')->q(...)->all()` |
| `->get()` | `->all()` |
| `->count()` | `select('COUNT(*) as total')` or `paginate()['total']` |
| `DB::getConnection()` | does not exist |
| `->selectRaw(...)` | `select('COUNT(*) as c')` or `raw()` |
| `->find(123)` on chain | does not exist |
| `$user->posts` | `$user->hasMany('posts','user_id')` |
| `$entity->validate()` | private; runs in `save()` |
| `if ($entity->save())` | `save()` is **void** — use callbacks |
| Laravel migrations | `Installation.php` — see [07](07-SCHEMA-AND-INSTALL.md) |
| `DB::transaction(fn)` | `transaction()/commit()/rollback()` or `transact()` |

**MUST:** Every table owned by a module is named `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never create unprefixed tables (`items`) or put module data under `dotapp_*`. Core auth tables use `Config::db('prefix')` only. See [07](07-SCHEMA-AND-INSTALL.md) §3.
