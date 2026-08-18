# 12 — Core Services Index and Remaining APIs

Detailed docs by area:

| Area | Doc |
|------|-----|
| Error handling / return values | [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) |
| Database, QueryBuilder, ORM | [06](06-DATABASE.md) |
| Schema, migrations, installer | [07](07-SCHEMA-AND-INSTALL.md) |
| Validator, Input, Request, Response, HttpHelper, Limiter | [19](19-VALIDATION-AND-INPUT.md) |
| Cache, Logger, DSM, Config | [20](20-CACHE-LOGGER-SESSION.md) |
| Email, SMS, QR | [21](21-EMAIL-SMS-QR.md) |
| AI, FastSearch, MCP | [22](22-AI-SEARCH-MCP.md) |
| Renderer, Translator, assets | [05](05-VIEWS-TEMPLATES-ASSETS.md) |
| Bridge, Reactive, dotapp.js | [09](09-DOTAPP-JS-AND-BRIDGE.md) |
| Auth, Crypto, TOTP | [11](11-AUTH-AND-CRYPTO.md) |
| Tester | [13](13-TESTING.md) |

This file covers what is left: DotApp helpers, Events, DI, Module, Middleware, Pagination, Collection utilities.

---

## 1. DotApp helpers

```php
use Dotsystems\App\DotApp;
$dotApp = DotApp::dotApp();     // throws if not booted
```

| Method | Returns |
|--------|---------|
| `DotApp::dotApp()` / `DotApp()` | `DotApp` (throws when unset) |
| `call($callable, ...$args)` | result of the resolved callable |
| `stringToCallable($cb, ...$args)` | `\Closure` |
| `ajaxReply($data, $status = 0)` | **base64-encoded JSON string**; sets HTTP code when `$status > 0` |
| `encrypt/decrypt/encrypta/decrypta` | see [11](11-AUTH-AND-CRYPTO.md) |
| `protect(&$in)` / `unprotect(&$in)` | `$this` |
| `escape($value)` | `string` |
| `generatePasswordHash($pass)` / `verifyPassword($pass,$hash)` | `string` / `bool` |
| `crc_check($key,$crc,$data)` | `1\|0` |
| `bind($key,$resolver)` / `singleton($key,$resolver)` | void |
| `resolve($key)` | object — **throws** if unbound |
| `module($name)` | module DI wrapper — throws if unregistered |
| `modules()` | `int` |
| `moduleExist($name)` | module\|`null` |
| `random_string($len = 16)` | `string` |
| `generateStrongPassword()` | `string` |
| `formatBytes($bytes, $precision = 2)` | `string` |
| `normalize_string`, `create_alias`, `repair_url`, `is_json` | utilities |
| `post($name)` / `get($name)` | **unprotected** raw superglobal values |
| `consumption()` | memory/time stats array |
| `isDebugMode()` | `bool` |
| `trustProxy(array $proxies)` | void |

There is **no `DotApp::log()`** — use `Logger::use()`.

Maintenance mode is the `__MAINTENANCE__` constant in the bootstrap, not a runtime helper.

---

## 2. Events

```php
$sub = $dotApp->on('shop.item.saved', function ($result, ...$data) { /* ... */ });
$sub->off();

$dotApp->trigger('shop.item.saved', $payload, $itemId);
$dotApp->hasListener('shop.item.saved');   // bool
$dotApp->offevent('shop.item.saved');      // $this
```

Arities:

```php
Events::on($event, $callback);                       // always registers
Events::on($routePattern, $event, $callback);        // returns FALSE if route doesn't match
Events::on($method, $routePattern, $event, $callback); // returns FALSE on mismatch
```

**`trigger()` returns `$result` unchanged — listener return values are ignored.** Listener exceptions **propagate** and abort remaining listeners, so wrap risky bodies in `try/catch`. Event names are lowercased; `dotapp.middleware` is an alias of `dotapp.router.resolve`.

---

## 3. DI, facades, wrappers

| Tool | Use |
|------|-----|
| `$dotApp->bind($k,$fn)` / `singleton($k,$fn)` | register services |
| `$dotApp->resolve($k)` | fetch — throws when unbound |
| Type-hinted controller params | auto-resolved **only when the route has no trailing `!`** |
| `new NoDI(function ($request) { ... })` | bypass DI entirely |
| `Facade` subclass | set `$component` + `$allowedMethods` (`['*']` allowed) |

Calling a method not in `$allowedMethods` → `\BadMethodCallException`.

**Do not use `Injector::bind()` / `Injector::singleton()`** — they contain a typo (`dotAapp()`) and fail. Use `$dotApp->bind()` / `singleton()`.

Custom facade:

```php
namespace Dotsystems\App\Modules\Shop\Parts;

class ShopFacade extends \Dotsystems\App\Parts\Facade
{
    protected static $component = 'shopService';       // property on DotApp
    protected static $allowedMethods = ['find', 'save'];
}
```

---

## 4. Module class API

| Method | Returns |
|--------|---------|
| `initialize($dotApp)` | abstract — register routes here |
| `initializeRoutes()` | `array` of URL patterns (default `['*']`) |
| `initializeCondition($routeMatch)` | `bool`/mixed |
| `Module::optimize()` | `true` or the caught `\Exception` — writes `modulesAutoLoader.php` |
| `settings($input = null, $value = null, $mode = 0)` | see below |
| `moduleName($name = null)` | `string` / `bool` |
| `call($method, ...$args)` | mixed |
| `loadLibrary($file)` | void — `require_once Libraries/{file}.php` |
| `setData($k,$v)` / `getData($k)` / `isSetData($k)` | `$this` / mixed\|`false` / `bool` |
| `installation()` | void — runs `install.php` once (bare modules). DACore-bound modules use `dainstall.php` instead ([35](35-DACORE-INSTALL.md) §4) |

### Module-local persisted settings

```php
$all  = $this->settings();                                   // array
$val  = $this->settings('apiUrl');                           // value|null
$this->settings('apiUrl', 'https://x');                      // true|false (writes settings.php)
$this->settings('apiUrl', 'default', Module::IF_NOT_EXIST);   // existing value if present
$this->settings('apiUrl', null, Module::DELETE);              // true
```

Stored in `app/modules/{Module}/settings.php`. Use `Config::module()` for user-facing configuration and `settings()` for values the module itself persists.

### Lifecycle events

`dotapp.module.{name}.init.start`, `.init.condition`, `.init.end`, `.loading`, `.loaded`, `.install` — payload is the module instance.

---

## 5. Middleware

Two distinct callback shapes — do not mix them:

```php
// A) Route hook (before/after) — receives the locked request
Router::get('/x', 'Shop:Home@index!')->before(function ($request) {
    if (!Auth::isLogged()) { return new Response(403, 'Forbidden'); }
});

// B) Named pipeline middleware — receives ($request, $next) and MUST call $next
Middleware::register('is_admin', function ($request, $next) {
    if (!Auth::can('Shop.admin')) { return new Response(403, 'Forbidden'); }
    return $next($request);
});

Middleware::use('is_admin')->group(function () {
    Router::get('/admin/items', 'Shop:Admin@items!');
});
```

| Call | Returns |
|------|---------|
| `Middleware::register/define/set($name,$cb,...$args)` | chain object |
| `Middleware::use($name)` / `get($name)` | `Middleware` — **throws** if undefined |
| `->group($cb)` | `$this`; if a middleware returns a `Response` it is sent and the script **exits** |
| `->callAllMiddlewares()` | last return value, or the `Response` |
| `->when($cb)` / `->true($cb)` / `->false($cb)` | `$this` |

Returning a `Response` from a `before` hook short-circuits the route.

Module middleware classes extend `ModuleMiddleware` and are referenced as `#Module:Class@method!`.

---

## 6. Router extras

| Call | Returns / behaviour |
|------|--------------------|
| route chain `->before/after/middleware($fn)` | chain |
| `->throttle(array $limits)` | chain; sets a `Limiter` |
| `->limitExceeded(callable $fn)` | chain; without it a 429 JSON is sent and the script exits |
| `Router::errorHandle($code, $view)` | void — registers an error view name |
| `Router::onPath($pattern, $callback)` | callback result or `$this` |
| `Router::apiPoint($ver, $modul, $controller, $custom = null)` | chain |
| `Router::hasRoute(...)` | **inverted** — `false` means the route *would* match |
| `Router::matched()` | `bool` |
| `Router::reset()` | void |
| `Router::match_url($route, $url = false, $static = false)` | params `array` or `false` |

404 handling: the `dotapp.router.resolve.404` listener wins; otherwise `errorHandle(404, $view)` renders `error_{$view}`; otherwise an empty 404 and `die()`. **HTTP 405 is not implemented.**

Response sending order in `runRequest()`: redirect → status → headers → cookies → body.

---

## 7. Pagination (UI)

```php
use Dotsystems\App\Parts\Pagination;

$html = Pagination::paginate($page['current_page'], $page['last_page'])
    ->window(2)->arrows(true)->ellipsis(true)->edge(true)
    ->render(function ($type, $pageNo, $label, $state, $href) {
        // $type: first|prev|page|ellipsis|next|last
        // $state: active|disabled|normal
        if ($type === 'ellipsis') { return '<li class="disabled"><span>…</span></li>'; }
        $off = ($state === 'active' || $state === 'disabled') ? ' disabled' : '';
        return '<li class="' . $state . '"><button type="button" class="js-shop-page" data-page="'
            . (int) $pageNo . '"' . $off . '>' . $label . '</button></li>';
    });
```

`render()` returns an HTML `string` (empty string when total ≤ 0). This is unrelated to SQL `paginate()`.

In-app lists **MUST** paginate with **buttons** + `$dotapp().load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3). **MUST NOT** pass a `?page=` `$href` that reloads the site. Admin markup: [33](33-DACORE-PAGES-AND-UI.md) §3 (`DACore:Page@paginate!` + `$callable`).

---

## 8. Collection utilities

Available when using `DB::module('ORM')`. Returns a **new Collection**: `filter, map, pluck, sortBy, sortByDesc, sort, sortDesc, unique, take, skip, search, chunk, diff, intersect, merge, concat, zip, reject, when, unless, tap, nth, push`. Returns scalars/arrays: `all, first, count, toArray, groupBy, partition, reduce, avg, sum, min, max, contains, containsStrict, some, every, find, paginate, pipe`.

With RAW mode you get plain arrays — use PHP array functions instead.

---

## 9. Connector

`Connector.php` is a visual workflow/graph engine (`load, save, addNode, removeNode, connect, disconnect, setRules, route, evaluateInput, evaluateNode, getNodes, getConnections, exportSidebar`). `addNode`/`connect` throw on invalid input; `route()` returns a runner object or `null`. Niche — read the source before use.
