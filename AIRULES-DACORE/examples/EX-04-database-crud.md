# EX-04 — Database CRUD with return values and error handling

Every snippet shows **what comes back** and **how failures surface**. Rules: [06-DATABASE.md](../06-DATABASE.md), [18-ERROR-HANDLING-AND-RETURN-VALUES.md](../18-ERROR-HANDLING-AND-RETURN-VALUES.md).

```php
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Logger;
```

---

## SELECT many → array (`[]` when empty)

```php
$rows = DB::module('RAW')->q(function ($qb) use ($limit) {
    $qb->select(['id', 'title', 'price'])
       ->from('shop_items')
       ->where('active', '=', 1)
       ->orderBy('id', 'DESC')
       ->limit($limit);
})->all();

// $rows === [] when nothing matches — always safe to foreach
foreach ($rows as $row) {
    // $row['id'], $row['title']
}
```

---

## SELECT one → NEVER use first() unguarded

```php
// WRONG — RAW warns on undefined index, ORM crashes on empty result
// $row = DB::module('RAW')->q(...)->first();

// RIGHT
$rows = DB::module('RAW')->q(function ($qb) use ($id) {
    $qb->select('*')->from('shop_items')->where('id', '=', $id)->limit(1);
})->all();

$row = $rows[0] ?? null;
if ($row === null) {
    return Response::json(['status' => 0, 'message' => 'Not found'], 404);
}
```

## Existence check → bool

```php
$exists = DB::module('RAW')->q(function ($qb) use ($slug) {
    $qb->select('id')->from('shop_items')->where('slug', '=', $slug);
})->exists();     // true|false
```

---

## INSERT → id via callback (`execute` throws without the error callback!)

```php
$newId = null;
$failed = null;

DB::module('RAW')->q(function ($qb) use ($title) {
    $qb->insert('shop_items', [
        'title' => $title,
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
})->execute(
    function ($result, $db, $execution_data) use (&$newId) {
        // $execution_data: affected_rows, insert_id, num_rows, result, query, bindings
        // NOTE: empty array on a cache hit -> use ??
        $newId = $execution_data['insert_id'] ?? $db->inserted_id();
    },
    function ($error, $db, $execution_data) use (&$failed) {
        // $error['error'] string, $error['errno'] int
        $failed = $error;
        Logger::use()->error('shop_items insert failed', $error);
    }
);

if ($newId === null) {
    return Response::json(['status' => 0, 'message' => 'Save failed'], 500);
}
```

## UPDATE → affected rows

```php
$affected = 0;
DB::module('RAW')->q(function ($qb) use ($id, $title) {
    $qb->update('shop_items')->set(['title' => $title])->where('id', '=', $id);
})->execute(
    function ($result, $db, $exec) use (&$affected) { $affected = $exec['affected_rows'] ?? 0; },
    function ($error) { Logger::use()->error('update failed', $error); }
);

if ($affected === 0) { /* nothing changed or row missing */ }
```

## DELETE

```php
DB::module('RAW')->q(function ($qb) use ($id) {
    $qb->delete('shop_items')->where('id', '=', $id);
})->execute(
    function ($result, $db, $exec) { /* $exec['affected_rows'] */ },
    function ($error) { Logger::use()->error('delete failed', $error); }
);
```

---

## RAW SQL with bindings

```php
// Named bindings — do NOT mix with ?
$rows = DB::module('RAW')->q(function ($qb) use ($term, $perPage, $offset) {
    $qb->raw(
        'SELECT id, title FROM shop_items
          WHERE title LIKE :q AND active = :active
          ORDER BY id DESC LIMIT :lim OFFSET :off',
        ['q' => '%' . $term . '%', 'active' => 1, 'lim' => $perPage, 'off' => $offset]
    );
})->all();
```

`raw()` **throws** when: `?` and `:named` are mixed, a named binding is missing, or the `?` count does not match the bindings count. Wrap dynamic query building in `try/catch`.

---

## Pagination → 10 keys

```php
$page = DB::module('RAW')->q(function ($qb) {
    $qb->select('*')->from('shop_items')->orderBy('id', 'DESC');
})->paginate(20, $currentPage);

// data, current_page, per_page, total, last_page, from, to, has_more_pages, prev_page, next_page
foreach ($page['data'] as $row) { /* ... */ }
$last = $page['last_page'];
```

---

## Transaction with rollback

```php
DB::module('RAW')->transaction();
try {
    $ok = true;

    DB::module('RAW')->q(function ($qb) use ($orderId) {
        $qb->insert('shop_orders', ['id' => $orderId, 'created_at' => date('Y-m-d H:i:s')]);
    })->execute(null, function ($error) use (&$ok) {
        $ok = false;
        Logger::use()->error('order insert', $error);
    });

    if (!$ok) {
        throw new \RuntimeException('order insert failed');
    }

    DB::module('RAW')->q(function ($qb) use ($orderId, $itemId) {
        $qb->insert('shop_order_items', ['order_id' => $orderId, 'item_id' => $itemId]);
    })->execute(null, function ($error) use (&$ok) {
        $ok = false;
        Logger::use()->error('order item insert', $error);
    });

    if (!$ok) {
        throw new \RuntimeException('order item insert failed');
    }

    DB::module('RAW')->commit();
} catch (\Throwable $e) {
    DB::module('RAW')->rollback();
    Logger::use()->error('order transaction rolled back', ['msg' => $e->getMessage()]);
    return Response::json(['status' => 0, 'message' => 'Could not create order'], 500);
}
```

Note: passing `null` as the success callback is fine, but **never** pass `null` as the error callback — that makes `execute()` throw.

---

## Counting (there is no ->count())

```php
$rows = DB::module('RAW')->q(function ($qb) {
    $qb->select('COUNT(*) as total')->from('shop_items')->where('active', '=', 1);
})->all();
$total = (int) ($rows[0]['total'] ?? 0);
```

---

## JOIN (exact argument order)

```php
$rows = DB::module('RAW')->q(function ($qb) {
    $qb->select(['o.id', 'u.username'])
       ->from('shop_orders o')
       ->join('dotapp_users u', 'o.user_id', '=', 'u.id')
       ->where('o.active', '=', 1);
})->all();
```

---

## Forbidden

```php
// Legacy string-built SQL — never in new code
// DB::module()->query("SELECT * FROM t WHERE id = $id");
// DB::module()->insert('t', $data);
// DB::module()->updateManual('t', $data, "id = $id");
// DB::module()->insert_multi('t', $rows);

// Non-existent APIs
// DB::table('t')->get();  DB::getConnection();  ->selectRaw();  ->find(1);  ->count();
// DB::migrate();  // declared but not implemented by any driver
```
