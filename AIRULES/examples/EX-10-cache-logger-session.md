# EX-10 — Cache, Logger, Session

Rules: [20-CACHE-LOGGER-SESSION.md](../20-CACHE-LOGGER-SESSION.md).

## Cache — `load()` returns null on miss

```php
use Dotsystems\App\Parts\Cache;
use Dotsystems\App\Parts\DB;

function topItems(int $limit = 10): array
{
    $cache = Cache::use('Shop');
    $key = 'items.top.' . $limit;

    $rows = $cache->load($key);          // NULL on miss (never false)
    if ($rows !== null) {
        return $rows;
    }

    $rows = DB::module('RAW')->q(function ($qb) use ($limit) {
        $qb->select('*')->from('shop_items')->where('active', '=', 1)
           ->orderBy('sold', 'DESC')->limit($limit);
    })->all();

    $cache->save($key, $rows, 600);      // chainable
    return $rows;
}
```

Per-user cache via `$context`:

```php
$cache->save('menu', $items, 600, ['user' => Auth::userId()]);
$items = $cache->load('menu', ['user' => Auth::userId()]);
```

Invalidate:

```php
Cache::use('Shop')->delete('items.top.10');
Cache::use('Shop')->clear();     // Memcached driver flushes the WHOLE server
```

**Do not** enable `Renderer::useCache(true)` (broken) or `Config::db('cache', true)` with ORM `Entity::save()` (requires an unimplemented `deleteKeys()`).

---

## Logger

```php
use Dotsystems\App\Parts\Logger;

Logger::use()->error('Order failed', ['order' => $id, 'errno' => $err['errno'] ?? null]);
Logger::use('shop')->withContext(['module' => 'Shop'])->warning('Slow query', ['ms' => $ms]);
```

Two gotchas:

1. Default enabled levels are `emergency, alert, critical, error, warning` — `info`/`debug` are dropped.
2. `core_log_enabled` defaults to **false**, so drivers write nothing.

Enable file logs in `app/config.php`:

```php
Config::loggerDriver('file', LoggerDriverFile::driver());
Config::logger('driver', 'file');
Config::logger('core_log_enabled', true);
Config::logger('log_levels', ['emergency','alert','critical','error','warning','info']);
Config::logger('folder', 'shop');
```

Ship logs elsewhere without touching drivers:

```php
$dotApp->on('dotapp.log', function ($level, $message, $context, $loggerName, $driver) {
    // forward to an external collector
});
```

---

## Session (DSM)

```php
use Dotsystems\App\Parts\DSM;

$sess = DSM::use('Shop');
$sess->set('cart', $items);
$cart = $sess->get('cart') ?? [];      // null when missing
$sess->delete('cart');
$sess->save();
```

Never use raw `$_SESSION`. Avoid the reserved names `_enc_key`, `_bridge.*`, `_router.*`, `_request.auth`, `_formCSRF`, `_default_limiter`.

`status()` returns `$this`, not the PHP session status — do not test it. `session_id($new)` **throws** if that ID already exists.

Session hardening in `app/config.php`:

```php
Config::session('lifetime', 3600);
Config::session('secure', true);      // HTTPS only
Config::session('httponly', true);
Config::session('samesite', 'Strict');
```
