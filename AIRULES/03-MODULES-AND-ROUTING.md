# 03 — Modules and Routing

## module.init.php

Every module ends with `new Module($dotApp);` and defines a class extending `\Dotsystems\App\Parts\Module`:

```php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\Parts\Router;
use Dotsystems\App\Parts\Config;

class Module extends \Dotsystems\App\Parts\Module
{
    public function initialize($dotApp)
    {
        // Config fallbacks (see 10-CONFIG-AND-SECRETS.md)
        Config::module("Shop", "prefix") ?? Config::module("Shop", "prefix", "/Shop");

        $prefix = Config::module("Shop", "prefix");
        $member = $prefix . "/account";

        Router::before([$member, $member . "/*"], "#Shop:Gate@login!");

        Router::get($prefix . "/", "Shop:Home@index!", Router::STATIC_ROUTE);
        Router::get($prefix . "/item/{id:i}", "Shop:Home@item!");

        if (\Dotsystems\App\Parts\Auth::isLogged() === true) {
            Router::get($member, "Shop:Account@index!", Router::STATIC_ROUTE);
        }
    }

    public function initializeRoutes()
    {
        // Patterns for modulesAutoLoader lazy loading
        return ['/Shop', '/Shop/*'];
        // return ['*']; // load on every request (less efficient)
    }

    public function initializeCondition($routeMatch)
    {
        return $routeMatch;
    }
}

new Module($dotApp);
```

Register **routes and config defaults** in `initialize()`.

### Login-required routes (**MUST**)

A page meant only for a logged-in user **MUST NEVER** render for an anonymous visitor.

**MUST** do all three:

1. Put those URLs under **one prefix** (module name).
2. Cover that prefix with **one login `before`** that returns `new Response(403, …)` and stops the pipeline.
3. Register the handlers only inside `if (Auth::isLogged() === true)` so the route is **not** in the table when anonymous.

`initialize()` runs per request, after Auth is available. Use `Auth::isLogged()` — **`Auth::logged()` does not exist**. Rights still go on per-route `->before` inside the `if` (logged in ≠ allowed). Public catalog/login stay **outside** the login prefix **and** outside the `if`.

**MUST NOT** register login-only routes for everyone and hope middleware is enough. **MUST NOT** hang a public page (including the login form) under the login `before` pattern — it would 403.

### URL prefix (**MUST**)

Path segment = **module name** (`Shop`), not a kebab slug.

| Project | Login-only URLs |
|---------|-----------------|
| Bare module | `/{ModuleName}/…` — `Config::module('Shop','prefix')` default `'/Shop'`. If the module also has public pages, hang the login gate on a **subtree** (`/Shop/account`), not on every `/Shop/…` URL. |
| DACore admin | `{DACore prefixUrl}/{ModuleName}/…` e.g. `/dacore/Shop/items`. The whole tree is login-only (DACore login lives at `loginUrl`, not under this prefix). |

### Login `before` on the prefix (**MUST**)

This is **not** Laravel `Route::prefix()->middleware()`. `Router::before($pattern, $fn)` **binds only if the current request already matches** `$pattern` (`match_url` during `initialize()`). At resolve, the hook runs **before** the controller. A `Response` return **stops** the rest (controller never runs).

**Fast prefix:** a pattern that **ends in `*`** and has no `{` `?` `}` is starts-with. `/Shop/account/*` matches URLs that **start with** `/Shop/account/` (the `*` is stripped). Exact `/Shop/account` does **not** match `/Shop/account/*`. Pass **both** as an array — `hooksFn` recurses:

```php
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\Router;

$prefix = Config::module('Shop', 'prefix'); // '/Shop'
$member = $prefix . '/account';

// Binds on this request only if the path already matches (not a Laravel group)
Router::before([$member, $member . '/*'], '#Shop:Gate@login!');

Router::get($prefix . '/', 'Shop:Home@index!', Router::STATIC_ROUTE);
Router::post($prefix . '/login', 'Shop:Auth@loginPost!', Router::STATIC_ROUTE);

if (Auth::isLogged() === true) {
    Router::get($member, 'Shop:Account@index!', Router::STATIC_ROUTE);
    // Extra permission checks stay per-route (Auth::can / #Shop:Rights@check!)
}
```

`Gate@login` **MUST** only test login. **MUST NOT** `crcCheck()` on that **HTML** hook (GET has no CRC).

```php
public static function login($request)
{
    if (!Auth::isLogged()) {
        return new Response(403, 'Forbidden');
    }
}

/** POST API only. Burns the one-time token — the action MUST NOT crcCheck() again. */
public static function crc($request)
{
    if ($request->crcCheck() === false) {
        return new Response(403, 'Forbidden');
    }
}

public static function loginAndCrc($request)
{
    if ($request->crcCheck() === false || !Auth::isLogged()) {
        return new Response(403, 'Forbidden');
    }
}
```

### Versioned POST API (**MUST** when the module has `fo-rm` / `load()` POSTs)

Decide this **first** in `initialize()` — then point every `<fo-rm action>` and `$dotapp().load()` at those URLs. Add `/api/v2/…` later; **keep** v1 so old clients still work.

| Kind | POST URL |
|------|----------|
| Logged-in JSON / save / pager | `/api/v1/auth/{Module}/…` e.g. `/api/v1/auth/Shop/users/add` |
| Public JSON (login, contact) | `/api/v1/noauth/{Module}/…` |

```php
$authApi = '/api/v1/auth/Shop';
$openApi = '/api/v1/noauth/Shop';
Router::before(['POST'], [$authApi, $authApi . '/*'], '#Shop:Gate@loginAndCrc!');
Router::before(['POST'], [$openApi, $openApi . '/*'], '#Shop:Gate@crc!');
```

**POST only.** After these hooks the token is **burned**: the action **MUST NOT** `crcCheck()`. With `formName`, only `$request->form(...)`. Isolated POSTs with **no** such prefix still `crcCheck()` in the action ([08](08-FORMS-AND-SECURITY.md)). **MUST NOT** hang `$dotapp().uploadFile` under a CRC prefix ([09](09-DOTAPP-JS-AND-BRIDGE.md)). Prefer these routes over `Router::apiPoint` (`/api/v{n}/{modul}/resource` has no `auth`/`noauth`).

### Comments in module code (**MUST**)

Write **English** comments in three layers — canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7.

1. A **docblock** on the file/class (what it owns) and on every public/static method (purpose, `@param`, `@return`, `@throws`).
2. A short **why** line above every **logical step** in the body: guards, decisions, formulas, named constants, the query shape, and the traps of this framework (logged-in route wrap, `crcCheck()` once, `$request->data(true)`, no `?` in `$qb->raw()` comments, unique `$key2`, owner predicate in SQL).
3. Nothing else.

**MUST NOT** restate the code (`// increment i`, `// return the response`), prompt-echo (“as requested…”), or leave dead code / commented-out blocks / a bare `TODO`. UI strings stay product copy ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8); comments are for the programmer.

---

## module.listeners.php

Loaded **before** `module.init.php`. Use for global early hooks:

```php
namespace Dotsystems\App\Modules\Shop;

use Dotsystems\App\Parts\Router;

class Listeners extends \Dotsystems\App\Parts\Listeners
{
    public function register($dotApp)
    {
        // Example: global before-hook
        // Router::before("any", "*", "Shop:AuthGate@before!");
    }
}

new Listeners($dotApp);
```

---

## Router API

Facades: `Router::` and `Route::` (alias of `Router`).

### Verbs

`any`, `get`, `post`, `put`, `delete`, `patch`, `options`, `head`, `trace`, `match`

```php
Router::get($path, $callback, $static = false);
Router::post($path, $callback, $static = false);
Router::match(['GET','POST'], $path, $callback, $static = false);
Router::any($path, $callback, $static = false);
```

Third argument:

- `Router::STATIC_ROUTE` (`true`) — exact URL match (fast)
- `Router::DYNAMIC_ROUTE` (`false`, default) — pattern matching

Returns a chain object: `->before()`, `->after()`, `->middleware()` (= alias of `before`), `->throttle()`, `->limitExceeded()`.

### Callback forms

```php
Router::get('/x', "Shop:Home@index!");           // controller string
Router::get('/x', function ($request) { ... }); // closure
Router::get('/x', "#Shop:Gate@check!");         // rarely as main handler
```

### Path parameters

| Pattern | Meaning |
|---------|---------|
| `{param}` | required segment |
| `{param?}` | optional |
| `{param:i}` | integer |
| `{param:s}` | string |
| `{param:l}` | letters |
| `{param*}` | greedy |
| `{*}` | anonymous greedy |
| `/prefix/*` | prefix wildcard |

Read matched params: `$request->matchData()['id']`.

### before / after hooks

```php
// Per route (extra Auth::can — not the login covering)
Router::get('/Shop/account', "Shop:Account@index!");

// Prefix covering (binds only if THIS request already matches — not Laravel)
Router::before($callback);                               // every request
Router::before($routePattern, $callback);
Router::before([$exact, $exact . '/*'], $callback);     // array of patterns
Router::before($method, $routePattern, $callback);
Router::before(['GET', 'POST'], $area . '/*', '#Shop:Gate@login!');
```

Returning a `Response` instance from a before-hook **short-circuits** the pipeline. Login covering: **MUST** return `new Response(403, …)` from `#Shop:Gate@login!` — see above. **MUST NOT** put `crcCheck()` on a catch-all login `before`.

### No Laravel groups / named routes

There is **no** `Route::prefix()->name()->group()`. Cover a module area with `Router::before([$prefix, $prefix . '/*'], '#Shop:Gate@login!')` plus routes registered inside `Auth::isLogged()`.

Other grouping (not a login gate):

1. Manual prefix via `Config::module(...)` concatenation.
2. `Router::onPath('/Shop/account*', function () { ... });` — runs the callback **now** if the current URL matches (to register routes), not as a 403 gate.
3. `Middleware::use('name')->group(function () { ... });`

**Named routes do not exist.**

### API helper

```php
Router::apiPoint($version, $modul, $controller, $custom = null);
// Default pattern: /api/v{version}/{modul}/{resource}(?:/{id})?
```

Dispatch via `Controller::apiDispatch` → methods like `getUsers`, `postUsers`. **Prefer** explicit `/api/v{n}/auth|noauth/{Module}/…` POSTs (CRC covering) over this helper.

### Checking if a route is free

`Router::hasRoute()` return value is **inverted** relative to the English name. Prefer reading [15-KNOWN-ISSUES.md](15-KNOWN-ISSUES.md) before using it. Safer pattern: register carefully and use `--list-routes`.

### First match wins

Once a dynamic route matches for the current request during registration/resolution, later registrations may be dropped for that request. Keep route lists ordered intentionally.

---

## Middleware systems

### A) Route before/after (flat hooks)

Not an onion `$next` stack unless you use system B.

### B) Named middleware chains

```php
use Dotsystems\App\Parts\Middleware;

Middleware::register('is_admin', function ($request, $next) {
    // must call $next($request) to continue
    return $next($request);
});

Middleware::use('is_admin')->group(function () {
    Router::get('/admin/users', 'Shop:Admin@users!');
});
```

### Module middleware class

```php
namespace Dotsystems\App\Modules\Shop\Middleware;

use Dotsystems\App\Parts\Response;

class Gate extends \Dotsystems\App\Parts\ModuleMiddleware
{
    public static function login($request)
    {
        if (!\Dotsystems\App\Parts\Auth::isLogged()) {
            return new Response(403, 'Forbidden');
        }
    }
}
```

Attach on the HTML prefix: `Router::before([$member, $member . '/*'], '#Shop:Gate@login!')`. **MUST NOT** `crcCheck()` in `login()`. POST API CRC is `crc` / `loginAndCrc` on `/api/v1/auth|noauth/{Module}/*` only — then the action **MUST NOT** `crcCheck()` again. Canonical: [08](08-FORMS-AND-SECURITY.md).

---

## Events from modules

```php
$sub = $dotApp->on('shop.item.saved', function ($result, ...$data) { /* ... */ });
$sub->off();
$dotApp->trigger('shop.item.saved', $result, $itemId);
```

`trigger()` returns `$result` **unchanged** — listener return values are ignored. Listener exceptions propagate. Route-scoped forms (`on($route, $event, $cb)` / `on($method, $route, $event, $cb)`) return **`false` and do not register** when the current request does not match. Full detail: [12-SERVICES.md](12-SERVICES.md).

---

## Module-local persisted settings

```php
$this->settings('apiUrl');                                  // value|null
$this->settings('apiUrl', 'https://x');                     // true|false
$this->settings('apiUrl', 'default', Module::IF_NOT_EXIST);  // existing value if set
$this->settings('apiUrl', null, Module::DELETE);             // true
```

Written to `app/modules/{Module}/settings.php`. Use `Config::module()` for user configuration, `settings()` for values the module persists itself.

Lifecycle events: `dotapp.module.{name}.init.start`, `.init.condition`, `.init.end`, `.loading`, `.loaded`, `.install`.

---

## Router return values and gotchas

- Verb methods return a **route chain object** only when the route matches the current request; otherwise an inert chain (calls are no-ops).
- After the first dynamic match, `route_matched` is set and later registrations get the inert chain for that request.
- `Router::hasRoute(...)` is **inverted**: it returns `false` when the route *would* match. Prefer `php dotapper.php --list-routes` to inspect routes.
- 404: the `dotapp.router.resolve.404` listener wins, else `Router::errorHandle(404, $view)`, else an empty 404 and `die()`. **HTTP 405 is not implemented.**
- `->throttle([...])` without `->limitExceeded($fn)` sends a 429 JSON response and **exits**.
- Every registered URL **MUST** call an existing handler that returns a `Response`. Feature off → redirect or 404 — **MUST NOT** a missing method (500). Logout **MUST** use the signed logout URL (session token). Public `noauth` that bots can hammer: **MUST warn** the user in chat ([11](11-AUTH-AND-CRYPTO.md) §11). Captcha is **not** MUST.
