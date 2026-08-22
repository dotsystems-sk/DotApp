# 18 — Error Handling and Return Values (CENTRAL LAW)

**Read this before writing any DotApp code.** DotApp uses **four different failure styles** depending on the library. Guessing one style for another is the #1 source of broken generated code.

---

## 1. The four failure styles

| Style | Libraries | How to handle |
|-------|-----------|---------------|
| **A. Callback pair** `($ok, $err)` | Database `execute()`, `Entity::save()/delete()`, `DB::schema()` | **Always pass BOTH callbacks** |
| **B. Boolean / null / false** | `Crypto::decrypt`, `Cache::load`, `Validator`, `Limiter`, `Config` getters, `Renderer` missing files | Check `=== false` / `=== null` explicitly |
| **C. Result envelope array** | `HttpHelper::request`, `FastSearch::*` | Check `['success']` before using `['data']` |
| **D. Exceptions** | `AI` (`AIException`), `QueryBuilder` build errors, `SchemaBuilder`, `Auth::createUser` (invalid email), `DI::resolve` | Wrap in `try/catch` |

Plus a fifth trap: some methods return **`void`** and only report through callbacks (`Entity::save()`).

---

## 2. Database — the most dangerous contract

### `execute($success, $onError)`

```php
DB::module('RAW')->q(function ($qb) use ($data) {
    $qb->insert('shop_items', $data);
})->execute(
    function ($result, $db, $execution_data) {
        $newId = $execution_data['insert_id'];   // int|string
        $rows  = $execution_data['affected_rows'];
    },
    function ($error, $db, $execution_data) {
        // $error['error'] => string message
        // $error['errno'] => int|string code
        Logger::use()->error('Insert failed', $error);
    }
);
```

**CRITICAL:** if you omit the error callback and the query fails, `execute()` **throws `\Exception`**. If you provide it, `execute()` returns `false`.

| Situation | `execute()` returns |
|-----------|--------------------|
| Success | result (rows array / Collection / driver result) |
| Error **with** `$onError` | `false` |
| Error **without** `$onError` | **throws `\Exception`** |
| No DB connection | **throws `\Exception`** |

### `$execution_data` keys

| Key | Type |
|-----|------|
| `affected_rows` | int |
| `insert_id` | int\|string |
| `num_rows` | int |
| `result` | driver statement/result |
| `query` | string |
| `bindings` | array |

**Warning:** on a **cache hit**, `$execution_data` is an **empty array** — always use `?? null`.

### `first()` / `all()` — zero rows

| Mode | `all()` empty | `first()` empty |
|------|---------------|-----------------|
| RAW | `[]` (safe) | **undefined index warning / unusable value** |
| ORM | empty `Collection` | **fatal error** (`null->getItem(0)`) |

**Never call `first()` unguarded.** Use one of:

```php
// Preferred: all() + array check
$rows = DB::module('RAW')->q(fn($qb) => $qb->select('*')->from('t')->where('id','=',$id)->limit(1))->all();
$row  = $rows[0] ?? null;
if ($row === null) { /* not found */ }

// Or exists() first
$has = DB::module('RAW')->q(fn($qb) => $qb->select('*')->from('t')->where('id','=',$id))->exists();
```

### Other DB returns

| Call | Returns |
|------|---------|
| `paginate($perPage,$page)` | array: `data, current_page, per_page, total, last_page, from, to, has_more_pages, prev_page, next_page` |
| `exists()` / `doesntExist()` | `bool` |
| `inserted_id()` | `int\|string\|null` |
| `affected_rows()` | `int\|null` |
| `transaction()/commit()/rollback()` | `$this` (chainable) |
| `Entity::save()` / `delete()` | **`void`** — use callbacks |

Full detail: [06-DATABASE.md](06-DATABASE.md).

---

## 3. Boolean / null returns you must check

| Call | Failure value | Consequence if unchecked |
|------|---------------|--------------------------|
| `Crypto::decrypt($x, $key2)` | **`false`** | You treat `false` as a valid string |
| `Crypto::decrypta(...)` | `false` (or `null` from json_decode) | Array access on false |
| `Cache::load($key)` | **`null`** on miss | Null deref |
| `Config::module('X','key')` | `null` if unset | Use `?? default` (see [10](10-CONFIG-AND-SECRETS.md)) |
| `Config::get($section,$key)` | `null` if unset | same |
| `Validator::validate(...)` | `true` on success, **array** on failure | `if ($r === true)` — do **not** use truthiness |
| `Input::validate()` | `bool` | Read `getErrors()` on false |
| `$request->crcCheck()` | `bool` | Never skip; **once** per request (second call burns → `false`) |
| `$request->form(...)` | callback return, or `false`, or **`null`**, or throws | Guard all three |
| `Limiter::isAllowed($route)` | `bool` | — |
| `Renderer::loadView()` / missing layout | **`""`** + warning log (no exception) | Blank page with no error |
| `setLayoutVar` / `setViewVar` with `is_callable` string (`time`, `copy`, …) | **var never extracted** (siblings still work) | Heading + empty `foreach` — [05](05-VIEWS-TEMPLATES-ASSETS.md) §5 |
| `Renderer::getViewVar($k)` | `""` if missing | Silent empty output |
| `Auth::login($data)` | array, or **`false`** on malformed input | `$r['error']` on false → error. Password **MUST** come from `$request->data(true)` ([19](19-VALIDATION-AND-INPUT.md)) |
| `MCP::addTool(...)` | `bool` | Silent registration failure |

### Correct decrypt pattern

```php
$plain = Crypto::decrypt($cipher, 'Shop.item.id');
if ($plain === false) {
    return Response::json(['status' => 0, 'message' => 'Invalid token'], 400);
}
```

---

## 4. Result envelope arrays

### `HttpHelper::request()`

Returns **always** 4 keys; **never throws** on network failure:

```php
$res = HttpHelper::request('POST', $url, $payload, ['timeout' => 10], $headers);
if (!$res['success']) {
    // $res['http_code'] int, $res['error'] string, $res['response'] may be null
    return null;
}
$data = $res['response']; // decoded array (or raw string if binary)
```

### `FastSearch` (every method)

```php
$r = FastSearch::use('catalog', 'meili')->search('products', $q);
if (!$r['success']) {
    $code = $r['error']->getErrorCode();   // FastSearchError
    return [];
}
$hits = $r['data'];
```

---

## 5. Exceptions you must catch

| Library | Exception | Typical trigger |
|---------|-----------|-----------------|
| AI | `AIException` | missing `api_key`/`model`, HTTP failure, unparseable reply |
| QueryBuilder | `\Exception`, `\InvalidArgumentException` | mixing `?` and `:named`, binding count mismatch (**every** `?` counts, including comments / `COMMENT 'SMS?'`), unsupported feature per DB engine |
| SchemaBuilder | `\InvalidArgumentException` | invalid name, unsupported type for DB engine, `unsigned()` on non-MySQL |
| Database `execute()` | `\Exception` | error without `$onError`, or no connection |
| Entity | `\Exception` | validation failure, delete without primary key, cache driver missing `deleteKeys` |
| `Auth::createUser` | `\Exception` | invalid email format |
| `Input::formFunction` | `\Exception` | invalid form method |
| `$request->form()` | `\Exception` | bad signature and no error callback |
| `DotApp::resolve()` | `\Exception` | unbound DI key |
| `Facade` | `\BadMethodCallException` | method not in `$allowedMethods` |
| `Config::*Driver()` | `\Exception` | driver not defined / incompatible |
| `Middleware::get()` | `\Exception` | undefined middleware name |
| `TOTP::*` | `\InvalidArgumentException`, `\RuntimeException` | bad secret/length |
| `Sms::*` | `\InvalidArgumentException`, `\RuntimeException` | invalid provider |
| `QR::generate` | `\InvalidArgumentException` | empty text |

### Standard controller guard

```php
public static function save($request)
{
    try {
        if (!$request->crcCheck()) {
            return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Bad request'], 400);
        }

        $answer = $request->form(['POST'], 'saveItem', function ($request) {
            // ... work ...
            return ['code' => 200, 'body' => ['status' => 1]];
        }, function ($request, $name) {
            return ['code' => 403, 'body' => ['status' => 0, 'message' => 'Invalid signature']];
        });

        if (!is_array($answer) || !isset($answer['body'])) {
            return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Rejected'], 400);
        }

        return DotApp::DotApp()->ajaxReply($answer['body'], $answer['code']);
    } catch (\Throwable $e) {
        Logger::use()->error('save failed', ['msg' => $e->getMessage()]);
        return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Server error'], 500);
    }
}
```

Note the **error callback on `form()`** — without it a bad signature **throws**.

---

## 6. Void-returning APIs (report only via callbacks)

```php
$entity->save(
    function ($result, $db, $execution_data) { /* ok */ },
    function ($error, $db, $execution_data)  { /* fail */ }
);
// $entity->save() itself returns null/void — do NOT test its return value
```

Same for `Entity::delete()` and `Entity::find($db, $id, $ok, $err)`.

---

## 7. Events do not propagate return values

```php
$result = $dotApp->trigger('module.shop.sms_sent.hook', $initial);
// $result === $initial ALWAYS — listener return values are IGNORED
```

Listener exceptions **propagate** and abort remaining listeners. Wrap risky listener bodies in `try/catch` yourself.

A pre-action stop is a **different** API: `triggerWithVeto()` returns the first `Dotsystems\App\Parts\Veto` or `null`. Ordinary `false`/scalar returns stay ignored. Canonical: [12](12-SERVICES.md) §2, [41](41-MODULE-HOOKS.md).

Also: `Events::on($route, $event, $cb)` and `on($method, $route, $event, $cb)` return **`false` and do not register** when the current request does not match the route.

---

## 8. Mandatory rules for agents

1. **Never call `first()`** without a `?? null` / `exists()` guard.
2. **Always pass the error callback** to `execute()` — otherwise failures throw.
3. **Always check `=== false`** after `Crypto::decrypt`.
4. **Always check `['success']`** for `HttpHelper` and `FastSearch`.
5. **Always `try/catch` (`\Throwable`)** around persist handlers, AI, SchemaBuilder, and QueryBuilder construction. Log in `catch`. Return a structured client error — **never** leak `$e->getMessage()`. **MUST NOT** empty `catch {}`.
6. **Never test the return value** of `Entity::save()`.
7. **Never rely on `trigger()`** to collect listener results.
8. **Log failures** with `Logger::use()->error(...)` (enable `Config::logger('core_log_enabled', true)` if the app wants files).
9. **Return a structured error to the client** — never leak exception messages to end users.
10. **Never silently swallow** an error with an empty `catch {}`.
11. **Always emit the catch event** — every `catch` and every `execute()` `$err` calls your module’s report helper: `dotapp.catch` + `dotapp.catch.error|info` with the fixed payload ([§9](#9-catch-telemetry--dotappcatch-must)).

`execute($ok, $err)` is the DB error path — **MUST** pass both callbacks (item 2). That is **not** a substitute for the outer `try/catch` on the handler (unexpected throwables). Do **not** omit `$err` and “just catch” — omitting `$err` still throws inside `execute()`.

---

## 9. Catch telemetry — `dotapp.catch` (**MUST**)

A failure nobody can see later is a failure you will debug twice. **Every** `catch` block and **every** `execute()` error callback **MUST** emit a catch event, so a debugger / audit page can be built later without touching any of this code — and **without** patching DACore.

### The three event names

| Event | When (**MUST**) |
|-------|-----------------|
| `dotapp.catch` | **Always** — the single funnel a debugger subscribes to. Emitted for every caught failure. |
| `dotapp.catch.error` | The operation **failed**: nothing was saved, the request is aborted, the user sees a failure. |
| `dotapp.catch.info` | The exception was **expected / recovered**: a fallback ran, an optional feature is missing, a retry succeeded, a duplicate was ignored. |

**Order:** emit `dotapp.catch` **first**, then the severity channel. Both carry the **same** payload. `severity` inside the payload matches the second event, so one listener on `dotapp.catch` is enough for a full log.

### Payload contract

One flat array. Keys are fixed — a later debugger relies on them.

| Key | **MUST** | Value |
|-----|----------|-------|
| `severity` | yes | `'error'` or `'info'` (matches the second event) |
| `module` | yes | owning module, e.g. `'Shop'` |
| `source` | yes | where it happened: `'Shop:Items@save'`, `'Shop:Gate@login'`, `'Shop:Installation@v3'` |
| `operation` | yes | stable slug of the attempt: `'shop.item.update'` — group by this in the debugger |
| `message` | yes | technical text (`$e->getMessage()` / the DB `error` string). **Never** shown to the user |
| `exception` | yes | `get_class($e)`, or `null` for a non-throwable failure branch |
| `code` | yes | `$e->getCode()` / driver errno / `0` |
| `file`, `line` | yes | `$e->getFile()`, `$e->getLine()` (own values for a non-throwable branch) |
| `time` | yes | `microtime(true)` |
| `context` | recommended | ids, counts, flags — `['item_id' => $id, 'rows' => count($rows)]` |
| `user_id` | recommended | `Auth::isLogged() ? Auth::userId() : null` |
| `route` | recommended | `$request->getPath()` when a request exists |
| `trace` | optional | `$e->getTraceAsString()` — big; only for `error`, and only if the project wants it |

**MUST NOT** put passwords, tokens, 2FA/reset codes, decrypted secrets, rights blobs, whole request bodies, card data, or personal data into the payload — it will end up in a log ([24](24-ATTACK-VECTORS.md) §8).

### The helper you write once per module

`trigger()` calls listeners **synchronously** and listener exceptions **propagate** ([§7](#7-events-do-not-propagate-return-values)) — a future debugger listener **MUST NOT** be able to break the user’s error path. So funnel everything through one helper (module `Libraries/`, or a `private static` in the controller) and call **that** from every `catch`.

```php
/**
 * Reports one caught failure to the catch bus and the log.
 *
 * @param  string     $source    'Shop:Items@save'
 * @param  string     $operation Stable slug, e.g. 'shop.item.update'
 * @param  \Throwable|null $e    The caught throwable, or null for a failure branch
 * @param  string     $severity  'error' (aborted) or 'info' (recovered/expected)
 * @param  array      $context   Ids and counts only — never secrets, rights or PII
 * @return void
 *
 * Why one helper: listener exceptions propagate, so a future debugger listener
 * must not be able to kill the reply the user is waiting for.
 */
private static function reportCatch($source, $operation, $e = null, $severity = 'error', $context = [], $message = '')
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

    // Generic funnel first, then the severity channel — same payload in both.
    try {
        Events::trigger('dotapp.catch', $payload);
        Events::trigger('dotapp.catch.' . $severity, $payload);
    } catch (\Throwable $busError) {
        // A broken listener must not replace the real error: log it and move on.
        Logger::use()->error('catch bus listener failed', ['msg' => $busError->getMessage()]);
    }

    Logger::use()->{$severity === 'info' ? 'warning' : 'error'}($operation, $payload);
}
```

`info` is logged as `warning` on purpose: `info` / `debug` levels are **dropped** by default ([20](20-CACHE-LOGGER-SESSION.md) §2).

### Using it

```php
} catch (\Throwable $e) {
    // Telemetry first, then the user-visible outcome (00 §2d — admin = toast).
    self::reportCatch('Shop:Items@save', 'shop.item.update', $e, 'error', ['item_id' => $id]);
    return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Could not save the item.'], 500);
}
```

```php
// The DB error path is not a throwable — it still MUST be reported.
->execute(function ($rows) { /* ok */ }, function ($error) use ($id) {
    self::reportCatch('Shop:Items@save', 'shop.item.update', null, 'error',
        ['item_id' => $id, 'errno' => $error['errno'] ?? 0], $error['error'] ?? 'db error');
});
```

**MUST:** every `catch` and every `execute()` `$err` calls the helper. **Recommended** for the other silent failure branches (`HttpHelper` `success => false`, `Email::send` error array, `Crypto::decrypt === false` on a path that should have worked, `Validator` rejecting server-generated data) — use `info` when the code recovers.

**MUST NOT:** an empty `catch`, a `catch` that only logs, a `catch` that only triggers (the user still needs the visible outcome), or a payload built ad-hoc with different key names in each file.

### Subscribing later (this is the point)

Do not mix the failure bus with business hooks:

| Event | Fired by | What a debugger sees |
|-------|----------|----------------------|
| `dotapp.catchall` | **Core**, every `trigger()` except itself | **All** events. Listener: `function ($result, $eventname, ...$data)`. [01](01-ARCHITECTURE.md), [23](23-DEBUG-PLAYBOOK.md) §1c |
| `dotapp.catch` | **Your** report helper | **Failures only**, fixed payload |
| `module.{mod}.{name}.hook` | **Owner** module after a useful side-effect | Business steps. Names + payload: that module’s `.hooks`. [41](41-MODULE-HOOKS.md) |

```php
// In YOUR debug/audit module's module.listeners.php — no change to any reporting code,
// and no file added under app/modules/DACore/.
Events::on('dotapp.catch', function ($payload) {
    // persist to your own {module}_ table, show it on your DACore page, ship it out
});

// Event tracer (opt-in). Core already fires this — do not trigger it yourself.
Events::on('dotapp.catchall', function ($result, $eventname, ...$data) {
    try {
        // cheap: name + maybe sizeof($data). Own try/catch — a throw aborts the original event.
    } catch (\Throwable $ignored) {
    }
});
```

Listener bodies **MUST** be defensive (own `try/catch`) and cheap — they run inside a failing request ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §2). **MUST NOT** push a DACore inbox notification from the bus by default — one broken cron would flood the inbox ([37](37-DACORE-NOTIFICATIONS.md)); notify on a real event, or on a rate-limited threshold. The same flood rule applies to `dotapp.catchall` (it fires far more often). The core `dotapp.log` hook stays available for log-level shipping ([20](20-CACHE-LOGGER-SESSION.md) §2).

**Frontend:** a JS `catch` (or a failed `load()` / `form()` reply) **MUST** show the outcome to the user ([00](00-AGENT-CONTRACT.md) §2d — admin = toast); `console.error` alone is not a report. If the project wants browser telemetry, POST the **same** payload shape to your own module endpoint — do not invent a second format.

---

## 10. Quick reference table

| API | Success | Failure |
|-----|---------|---------|
| `execute($ok,$err)` | result | `false` (or throws w/o `$err`) |
| `all()` | array | `[]` |
| `first()` | row | **unsafe — guard** |
| `exists()` | bool | bool |
| `paginate()` | array of 10 keys | `data => []` |
| `Entity::save()` | void + ok callback | void + err callback / throws |
| `Validator::validate` | `true` | `array<field, string[]>` |
| `Input::validate` | `true` | `false` + `getErrors()` |
| `$request->crcCheck` | `true` | `false` |
| `$request->form` | callback return | `false` / `null` / throws |
| `$request->validateInputs` | `true` | array w/ `error_code` 403\|422 |
| `Crypto::decrypt` | string | **`false`** |
| `Cache::load` | value | **`null`** |
| `HttpHelper::request` | `success=true` | `success=false`, `error` string |
| `FastSearch::*` | `success=true`, `data` | `success=false`, `error` object |
| `AI::...->call()` | `['all_messages','reply','raw']` | throws `AIException` |
| `Email::send` | `true` | **array of error strings** |
| `MCP::execute` | JSON-RPC `result` | JSON-RPC `error` object |
| `Renderer::renderView` | HTML string | `""` on missing files |
| `Auth::login` | array `logged/error/error_txt` | `false` on malformed input |
