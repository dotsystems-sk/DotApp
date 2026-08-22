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
| **Extender** (judge: replaceable output, not every method) | this file §10 — [EX-17](examples/EX-17-extender.md) |

This file covers what is left: DotApp helpers, Events, DI, Module, Middleware, Pagination, Collection utilities, Extender.

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
| `post($name)` / `get($name)` | **unprotected** raw superglobal values (same idea as `$request->data(true)` — not the protected copy) |
| `consumption()` | memory/time stats array |
| `isDebugMode()` | `bool` |
| `trustProxy(array $proxies)` | void |

There is **no `DotApp::log()`** — use `Logger::use()`.

Maintenance mode is the `__MAINTENANCE__` constant in the bootstrap, not a runtime helper.

---

## 2. Events

```php
$sub = $dotApp->on('module.shop.sms_sent.hook', function ($result, ...$data) { /* ... */ });
$sub->off();

$dotApp->trigger('module.shop.sms_sent.hook', $payload);
$veto = $dotApp->triggerWithVeto('module.shop.item_delete.veto', $payload); // Veto|null
$dotApp->hasListener('module.shop.sms_sent.hook');   // bool
$dotApp->offevent('module.shop.sms_sent.hook');      // $this
```

Arities:

```php
Events::on($event, $callback);                       // always registers
Events::on($routePattern, $event, $callback);        // returns FALSE if route doesn't match
Events::on($method, $routePattern, $event, $callback); // returns FALSE on mismatch
Events::triggerWithVeto($event, $result, ...$data);   // first Veto or null
```

**`trigger()` returns `$result` unchanged — listener return values are ignored.** Listener exceptions **propagate** and abort remaining listeners, so wrap risky bodies in `try/catch`. Event names are lowercased; `dotapp.middleware` is an alias of `dotapp.router.resolve`.

**`triggerWithVeto()` is opt-in and returns the first `Dotsystems\App\Parts\Veto`, or `null`.** It stops listeners immediately only when a callback returns a `Veto` object. `false`, `null`, strings, arrays, and every other legacy return remain ignored. A returned `Veto` contains a stable lowercase `code`, an internal `message`, and safe `details`; the core never serializes it to a client. Exceptions still propagate. Ordinary `trigger()` ignores even a `Veto`, preserving old module behavior.

**`dotapp.catchall` (core, DotApp 2.0):** every `trigger($name, $result, …$data)` except `dotapp.catchall` itself first fires `dotapp.catchall` with `($result, $name, …$data)`. That is the **one** place a debugger / event tracer **MUST** subscribe to see every event. Do **not** trigger `dotapp.catchall` yourself. A throw in that listener aborts the original event. Distinct from `dotapp.catch` (module-fired failures — [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9) and from **`module.{mod}.{name}.hook`** (business steps — [41](41-MODULE-HOOKS.md)). Canonical: [01](01-ARCHITECTURE.md) Built-in events, [23](23-DEBUG-PLAYBOOK.md) §1c.

**Your `module.{mod}.{name}.hook` names (MUST judge):** fire **only** when another module could log, show history, or sync (SMS/mail sent, payment, lockout). Name: `module.{lowercase_modulename}.{hook_name}.hook`. **MUST** document it in `app/modules/<YourModule>/.hooks` and put `Hook:` / `Why:` / `About:` / `Params:` / `Use:` above `trigger()`. **MUST NOT** fire on every save. **MUST NOT** skip a **decided** hook because `hasListener` is false. Listener returns on `trigger()` are **ignored** (not a veto). No secrets on the bus. Canonical: [41](41-MODULE-HOOKS.md). Sample: [EX-16](examples/EX-16-module-hooks.md).

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
| `initializeRoutes()` | `array` of URL patterns (default `['*']`) — **MUST** list this module’s prefixes, not `['*']` unless the user asked |
| `Listeners::initializeRoutes()` | `array\|null` — `null` / omit inherits `Module::initializeRoutes()`; may be a **narrower** list so the listener wakes without full `initialize()` |
| `initializeCondition($routeMatch)` | `bool`/mixed |
| `Module::optimize()` | `true` or the caught `\Exception` — writes `modulesAutoLoader.php` |
| `settings($input = null, $value = null, $mode = 0)` | see below |
| `moduleName($name = null)` | `string` / `bool` |
| `call($method, ...$args)` | mixed |
| `loadLibrary($file)` | void — `require_once Libraries/{file}.php` |
| `setData($k,$v)` / `getData($k)` / `isSetData($k)` | `$this` / mixed\|`false` / `bool` |
| `installation()` | void — runs `install.php` once. After a new version, rename `installed_*` back to `install.php` ([07](07-SCHEMA-AND-INSTALL.md)) |

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

In-app lists **MUST** paginate with **buttons** + `$dotapp().load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3). **MUST NOT** pass a `?page=` `$href` that reloads the site.

---

## 8. Collection utilities

Available when using `DB::module('ORM')`. Returns a **new Collection**: `filter, map, pluck, sortBy, sortByDesc, sort, sortDesc, unique, take, skip, search, chunk, diff, intersect, merge, concat, zip, reject, when, unless, tap, nth, push`. Returns scalars/arrays: `all, first, count, toArray, groupBy, partition, reduce, avg, sum, min, max, contains, containsStrict, some, every, find, paginate, pipe`.

With RAW mode you get plain arrays — use PHP array functions instead.

---

## 9. Connector

`Connector.php` is a visual workflow/graph engine (`load, save, addNode, removeNode, connect, disconnect, setRules, route, evaluateInput, evaluateNode, getNodes, getConnections, exportSidebar`). `addNode`/`connect` throw on invalid input; `route()` returns a runner object or `null`. Niche — read the source before use.

---

## 10. Extender

`Dotsystems\App\Parts\Extender` is an **opt-in, request-local** replacement registry. It is **not** Events, hooks, middleware, or `triggerWithVeto()`. Sample: [EX-17](examples/EX-17-extender.md).

**Judge first.** **MUST NOT** opt in on every method. Offer Extender when another module would reasonably **replace this output** — especially **page/block HTML**, a **cart** drawn differently, an **export** / invoice built differently. Skip ordinary persist, CRC, decrypt, pager internals. Canonical writing law: [00](00-AGENT-CONTRACT.md) §2h.

A method is extendable only when its owner checks `exists()` and **immediately** `return Extender::call(...)`. Registering a handler for a method that never calls `Extender` does nothing. There is **no original / next** — the replacement fully owns the result.

| Method | Returns / throws |
|--------|------------------|
| `Extender::extend($className, $methodName, $handler)` | `void`. `\InvalidArgumentException` when the target or handler is invalid. `\LogicException` when that target already has a replacement. |
| `Extender::exists($className, $methodName)` | `bool` — **canonical** probe. `\InvalidArgumentException` when the identifier is invalid. |
| `Extender::exist($className, $methodName)` | alias of `exists()` — same return and throws. Prefer `exists()` in new code. |
| `Extender::call($className, $methodName, ...$arguments)` | replacement return value, unchanged. `\LogicException` when none is registered or the same target re-enters `call()`. Handler `\Throwable` **propagates**. `\InvalidArgumentException` when the identifier is invalid. |

`$className` is a fully qualified PHP class name (leading `\` stripped; the class is **not** autoloaded at register time). `$methodName` is a PHP method name. Keys are **case-insensitive**.

`$handler` is either:

- a DotApp controller string `Module:Controller@method!` — validated with `stringToCallable()`, invoked with `DotApp::call()` (trailing `!` = no DI, same grammar as routes — [04](04-CONTROLLERS-AND-RESPONSES.md));
- a native PHP callable — invoked with `call_user_func_array`, **no** DotApp DI.

**One replacement only.** A second `extend()` for the same class+method throws. Do not catch-and-ignore that.

**Boot:** `Extender::extend()` is request-local and **MUST** run before the owner can call `exists()` / `call()`. The canonical place is the extending module’s **`Listeners::register()`** (`module.listeners.php`): all matching listeners register before any matching Module initializes.

- `Listeners::initializeRoutes()` **MUST** include every URL surface that can reach the target method. `Module::initializeRoutes()` stays on the extending module’s own URLs, or returns `[]` for a listener-only extender. If Module routes are `[]`, listener routes **MUST NOT** omit/return `null` (that inherits `[]`). Then `php dotapper.php --optimize-modules`.
- **MUST NOT** use `['*']` merely to attach. Use it only for a genuinely global/dynamic dependency after warning that this listener file registers on every request.
- Prefer a controller string such as `Loyalty:Pricing@quote!`; it is validated when registered and invoked lazily through `DotApp::call()`. Native callables are legal but skip DotApp DI.
- **MUST NOT** call `$dotapp->module('Loyalty')` (self) or load the target module merely to attach. The string handler does not require full module initialization and is autoloaded when invoked.
- Direct `extend()` in `register()` is safest. If lifecycle timing is genuinely required, subscribe there to `dotapp.module.{Target}.init.start` or `.loading`. `{Target}.loaded`, `.init.end`, and `dotapp.modules.loaded` happen after target `initialize()` and are too late for an extension point used there.
- **MUST NOT** also register the same target from Module `initialize()`; the duplicate throws.

**Safe context only.** The owner passes explicit arguments into `call()`. **MUST NOT** forward `$request`, superglobals, locals, secrets, tokens, CRC, rights blobs, or request bodies automatically.

**Target shape:**

```php
if (Extender::exists(self::class, 'quote')) {
    return Extender::call(self::class, 'quote', $cartId, $subtotal);
}
```

**MUST NOT:** spray `exists()`/`call()` on every persist/helper; fire `Events::trigger` / `triggerWithVeto` instead of Extender; wrap `call()` and still run the original; patch another module you do not own to add an extension point; from the replacement, call the same extendable method (recursion throws). Canonical writing law: [00](00-AGENT-CONTRACT.md) §2h.
