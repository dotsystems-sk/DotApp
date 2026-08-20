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
$result = $dotApp->trigger('shop.item.saved', $initial, $itemId);
// $result === $initial ALWAYS — listener return values are IGNORED
```

Listener exceptions **propagate** and abort remaining listeners. Wrap risky listener bodies in `try/catch` yourself.

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

`execute($ok, $err)` is the DB error path — **MUST** pass both callbacks (item 2). That is **not** a substitute for the outer `try/catch` on the handler (unexpected throwables). Do **not** omit `$err` and “just catch” — omitting `$err` still throws inside `execute()`.

---

## 9. Quick reference table

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
