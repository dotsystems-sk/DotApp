# 20 — Cache, Logger, Session (DSM)

Return values and driver matrices. See [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md).

---

## 1. Cache

**MUST:** cache only what is **expensive and shared across requests**, always with a TTL **and** invalidation on write. A `WHERE id = ?` lookup, a `COUNT(*)`, or anything an index already answers **MUST NOT** be cached — a stale row costs more than the query saved ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §2).

```php
use Dotsystems\App\Parts\Cache;

$cache = Cache::use('Shop');                 // singleton per name
$cache->save('items.top', $rows, 600);       // chainable
$rows = $cache->load('items.top');           // NULL on miss

if ($rows === null) {
    $rows = /* query */;
    $cache->save('items.top', $rows, 600);
}
```

| Method | Signature | Returns |
|--------|-----------|---------|
| `Cache::use` | `use($cacheName = null, $folder = null, $driver = null)` | `Cache` |
| `save` | `save($key, $data, $lifetime = null, $context = [])` | `$this` |
| `load` | `load($key, $context = [], $destroy = false)` | value or **`null`** |
| `exists` | `exists($key, $context = [], $load = false)` | `bool` (or value when `$load = true`) |
| `delete` | `delete($key, $context = [])` | `$this` |
| `clear` | `clear()` | `$this` |
| `gc` | `gc()` | `$this` |
| `folder()` / `name()` | | `string` |
| `Cache::normalizeData($data)` | static | normalized value |

`$context` is an extra array folded into the physical key — same logical key + different context = different entry (useful for per-user caches).

Defaults: `folder` ← `Config::cache('folder')`, `driver` ← `Config::cache('driver')`, lifetime ← `Config::cache('lifetime')` (36000) when `null`.

### Driver matrix

| Concern | File (default) | Redis | Memcached | Null |
|---------|----------------|-------|-----------|------|
| `load()` miss | `null` | `null` | `null` | `null` |
| `clear()` scope | `cache_*.php` in folder | **buggy key tracking** | **flushes the whole server** | no-op |
| `gc()` | includes/expires files | no-op (TTL) | no-op | no-op |
| Extra config | — | `cache.redis_*` | `cache.memcached_*` | — |
| `deleteKeys()` | ❌ | ❌ | ❌ | ❌ |

Registration in `app/config.php`:

```php
Config::cacheDriver('default', CacheDriverFile::driver());
Config::cache('lifetime', 36000);
```

Custom driver must supply callables: `save, load, exists, delete, clear, gc` (each receives the `Cache` instance as last arg). Missing/non-callable → `\Exception`.

### 1a. Cache the public surface — and let the operator switch it off (MUST — law)

Canonical: [00](00-AGENT-CONTRACT.md) §2t. Compact: `AIRULES/cursor/rules/29-cache-required.mdc`.

Any module that renders a **public, anonymous, read-mostly** surface — a catalogue, a price list, a public API answer, a landing page, a public detail page, a widget another page embeds — **MUST** ship caching in the same work as the surface, and **MUST** ship an operator switch for it.

**MUST**

1. **`Cache::use('<Module>')->save() / load() / exists() / delete() / gc()`.** One instance name per module. A project-level driver change then reaches this module for free.
2. **An on/off switch in this module’s settings**, normally also a TTL. Default the switch **off** when updating a live site. A new install may default it on only when the surface is genuinely static.
3. **Respect the switch on both paths.** Disabled means stores nothing **and** serves nothing.
4. **Invalidate on write.** Delete the affected keys or bump a generation that is part of the key. A TTL alone is not invalidation.
5. **Decide per surface.** Anonymous list/detail HTML, rendered menus, and expensive aggregate boxes: cache. A CSRF form, a logged-in page, a cart, a checkout step, a per-visitor block, or a search over unbounded text: do not cache the response. Cache the expensive fragments behind it, or cache nothing.
6. **Never** cache a value that a session or a right varies, unless that value is part of `$context`. Never store personal data, tokens, passwords, or one-time codes.
7. **Write the decision into `PLAN/`:** surfaces, TTL, invalidation, settings key.

**MAY** cache an authenticated aggregate, report, or external API answer under the same rules. Never a write path.

**MUST NOT**

- Hand-roll storage (`file_put_contents` folder, APCu, own `cache_*` table, static array as persistence, `$_SESSION` as cache)
- Use `clear()` as invalidation — folder-wide on file, **whole-server flush** on Memcached
- Cache a `WHERE id = ?`, a `COUNT(*)`, or anything an index already answers
- Enable `Renderer::useCache(true)` or `Config::db('cache')`
- Skip this layer because a CDN, a reverse proxy, or another page cache might sit in front. Those can be off or absent. On a miss, and on a partly dynamic page, this layer is what still pays off

### Two important warnings

1. **`Renderer::useCache(true)` is broken** — it calls `cachePageExists`/`cachePageSave`, which exist only on the legacy `Cache_OLD` class. Do not enable it.
2. **`Config::db('cache') = true` breaks `Entity::save()`** — Entity requires `deleteKeys()` on the cache driver, which no shipped driver implements (throws). Keep DB query cache off unless you provide a custom driver.

---

## 2. Logger

```php
use Dotsystems\App\Parts\Logger;

Logger::use()->error('Save failed', ['id' => $id, 'errno' => $e['errno']]);
Logger::use('shop', 'file')->withContext(['module' => 'Shop'])->warning('Slow query');
```

| Method | Returns |
|--------|---------|
| `Logger::use($name = null, $driver = null)` | `Logger` |
| `name()` | `string` |
| `withContext(array $context)` | `$this` |
| `log($level, $message, array $context = [])` | `$this` |
| `emergency` `alert` `critical` `error` `warning` `notice` `info` `debug` | `$this` |
| `rotate()` / `clean()` | `$this` |
| `Logger::formatLog($level,$msg,$ctx)` | `[string, array]` |

### Two gotchas

1. Enabled levels default to `['emergency','alert','critical','error','warning']` — `info`/`debug` are **dropped** unless you extend `Config::logger('log_levels', [...])`.
2. **`core_log_enabled` defaults to `false`** — drivers never write. The `dotapp.log` hook still fires. To get files:

```php
Config::logger('core_log_enabled', true);
Config::loggerDriver('file', LoggerDriverFile::driver());
Config::logger('driver', 'file');
```

**MUST** pair the log with the catch bus: every `catch` and every `execute()` `$err` also fires `dotapp.catch` + `dotapp.catch.error|info` ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9). `Logger` alone is not the report — `core_log_enabled` is `false` by default, so a log-only failure can vanish entirely. `info`-severity reports are logged as `warning` because `info`/`debug` levels are dropped by default (gotcha 1).

### `dotapp.log` hook

```php
$dotApp->on('dotapp.log', function ($level, $message, $context, $loggerName, $driver) {
    // ship to external system
});
```

### Drivers

| Driver | Destination | Rotation | Permissions |
|--------|-------------|----------|-------------|
| `LoggerDriverDefault` | PHP `error_log` | — | OS |
| `LoggerDriverFile` | `app/runtime/logs/{folder}/log_{date}_{hash}.log` | `max_size` (10 MB) then keep `max_files` (7); `clean()` drops > 30 days | dir `0700`, file `0600`, `.htaccess` deny |
| `LoggerDriverNoLog` | discard | — | — |

Custom driver callables: `log($level,$message,$context,$logger)`, `rotate($logger)`, `clean($logger)`.

---

## 3. Session (DSM)

```php
use Dotsystems\App\Parts\DSM;

DSM::use('shop')->set('cart', $items);
$cart = DSM::use('shop')->get('cart');        // null when missing
DSM::use('shop')->delete('cart');
DSM::use('shop')->save();
```

| Method | Returns |
|--------|---------|
| `DSM::use($sessname = null)` | `DSM` |
| `get($name)` | value or **`null`** |
| `set($name, $value)` | `$this` |
| `delete($name)` / `clear()` / `gc()` | driver return (usually void) |
| `load()` / `save()` / `start()` / `destroy()` | `$this` |
| `regenerate_id($deleteOld = false)` | `$this` |
| `session_id()` | `string` |
| `session_id($new)` | driver result — **throws** if the ID already exists |
| `status()` | **`$this`** (not the PHP status int — misleading name) |

**MUST:** application session state goes through **`DSM::use('Shop')`** (module namespace). **MUST NOT** `$_SESSION`, `$_SESSION['…']`, or `session_start()` in module code.

Reserved names used by the framework: `_enc_key`, `_bridge.*`, `_router.*`, `_request.auth`, `_formCSRF`, `_default_limiter`. Pick your own namespace, e.g. `DSM::use('Shop')` or keys prefixed `shop.`.

### Driver comparison

| | default | file | file2 | db | redis |
|---|---------|------|-------|----|-------|
| Storage | `$_SESSION[$sessname]` *(driver internals — not app code)* | one file per session | file per `{id}_{sessname}` | DB row | Redis key |
| Needs | — | writable `session.file_driver_dir` | `file_driver_dir2` | table `session.database_table` | Redis ext + `session.redis_*` |
| GC | PHP | cron include | cron | SQL delete | TTL scan |
| Notes | simplest | all names in one file | more files, smaller writes | multi-server | fastest; uses `KEYS` pattern |

Known issues: the DB driver has bugs in `regenerate_id`; the Redis driver throws on construction if any `redis_*` config is empty. Prefer `default` or `file` unless you need shared sessions.

Registration:

```php
Config::sessionDriver('default', SessionDriverDefault::driver());
Config::session('lifetime', 3600);
Config::session('secure', true);   // HTTPS
Config::session('httponly', true);
Config::session('samesite', 'Strict');
```

Custom driver: validated keys are `load, save, get, set, delete, clear`, but DSM also calls `start, destroy, status, regenerate_id, session_id, gc` — implement **all twelve**.

---

## 4. Config quick reference

| Method | Getter return | Setter return |
|--------|---------------|---------------|
| `Config::get($section, $key = null)` | section array / value / **`null`** | — |
| `Config::set($section, $key, $value = null)` | — | void |
| `Config::module($m, $key = null, $value = null, $onlyIfNotExist = false)` | value / array / **`null`** | the value (or existing value with `IF_NOT_EXIST`) |
| `Config::db/session/cache/logger/totp/bridge/router/app($key, $value = null)` | value / `null` | void |
| `Config::email($account, $key, $value = null)` | account array / value / `null` | void |
| `Config::searchEngines($key, $value = null)` | value / `null` | void |
| `Config::addDatabase(...)` | — | void |
| `Config::sessionDriver/cacheDriver/loggerDriver/searchDriver($name, $driver = null)` | driver array | void |
| `Config::fn($name, callable, $sectionKey = null)` | — | void (custom section) |

Full defaults tree: [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md).
