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

## Catch bus — the report helper every module needs

Law: every `catch` and every `execute()` `$err` reports it. Canonical contract: [18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.

```php
namespace Dotsystems\App\Modules\Shop\Libraries;

use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Events;
use Dotsystems\App\Parts\Logger;

/**
 * Single reporting point for caught failures in the Shop module.
 *
 * Why a class: the payload keys are a contract for the debugger, and the
 * trigger() calls must be wrapped once — listener exceptions propagate.
 */
class Diag
{
    /**
     * @param  string          $source    'Shop:Items@save'
     * @param  string          $operation Stable slug, e.g. 'shop.item.update'
     * @param  \Throwable|null $e         Caught throwable, or null for a failure branch
     * @param  string          $severity  'error' (aborted) or 'info' (recovered)
     * @param  array           $context   Ids and counts only — never secrets or PII
     * @param  string          $message   Used when there is no throwable (DB error text)
     * @return void
     */
    public static function reportCatch($source, $operation, $e = null, $severity = 'error', $context = [], $message = '')
    {
        $payload = [
            'severity'  => $severity,
            'module'    => 'Shop',
            'source'    => $source,
            'operation' => $operation,
            'message'   => $e instanceof \Throwable ? $e->getMessage() : (string) $message,
            'exception' => $e instanceof \Throwable ? get_class($e) : null,
            'code'      => $e instanceof \Throwable ? $e->getCode() : 0,
            'file'      => $e instanceof \Throwable ? $e->getFile() : __FILE__,
            'line'      => $e instanceof \Throwable ? $e->getLine() : __LINE__,
            'time'      => microtime(true),
            'context'   => $context,
            'user_id'   => Auth::isLogged() ? Auth::userId() : null,
        ];

        try {
            Events::trigger('dotapp.catch', $payload);              // funnel for a future debugger
            Events::trigger('dotapp.catch.' . $severity, $payload); // severity channel
        } catch (\Throwable $busError) {
            // A broken listener must not replace the real error.
            Logger::use('shop')->error('catch bus listener failed', ['msg' => $busError->getMessage()]);
        }

        // 'info' is logged as warning because info/debug levels are dropped by default.
        Logger::use('shop')->{$severity === 'info' ? 'warning' : 'error'}($operation, $payload);
    }
}
```

Usage in a controller — report, then tell the user:

```php
} catch (\Throwable $e) {
    Diag::reportCatch('Shop:Items@save', 'shop.item.update', $e, 'error', ['item_id' => $id]);
    return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Could not save the item.'], 500);
}
```

Read the trail while debugging (temporary, in your `module.listeners.php`):

```php
Events::on('dotapp.catch', function ($payload) {
    Logger::use('debug')->error($payload['operation'] ?? 'unknown', $payload);
});
```

See **every** event (not only failures) — core `dotapp.catchall`. A debug tool **MUST** subscribe here. Listener arity is `($result, $eventname, ...$data)`. Gate it; own `try/catch`; do not trigger `dotapp.catchall` yourself. Canonical: [01](../01-ARCHITECTURE.md), [23](../23-DEBUG-PLAYBOOK.md) §1c.

```php
Events::on('dotapp.catchall', function ($result, $eventname, ...$data) {
    try {
        Logger::use('debug')->warning($eventname, ['argc' => count($data)]);
    } catch (\Throwable $ignored) {
    }
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

Never use raw `$_SESSION` / `session_start()`. **MUST** `DSM::use('Shop')`. Avoid the reserved names `_enc_key`, `_bridge.*`, `_router.*`, `_request.auth`, `_formCSRF`, `_default_limiter`.

`status()` returns `$this`, not the PHP session status — do not test it. `session_id($new)` **throws** if that ID already exists.

Session hardening in `app/config.php`:

```php
Config::session('lifetime', 3600);
Config::session('secure', true);      // HTTPS only
Config::session('httponly', true);
Config::session('samesite', 'Strict');
```
