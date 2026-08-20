# 04 — Controllers and Responses

## Controller rules

1. Namespace: `Dotsystems\App\Modules\{Module}\Controllers`
2. Extend `\Dotsystems\App\Parts\Controller`
3. Methods are **`public static function`** — there is no `$this`
4. First argument is usually `$request` (RequestObj)
5. Route string: `"Module:Controller@method!"`
6. Comments: English, short **why** at traps — not every line ([03](03-MODULES-AND-ROUTING.md))

### With DI (no trailing `!`)

```php
public static function index($request, \Dotsystems\App\Parts\Renderer $renderer)
{
    return $renderer->module(static::modulename())
        ->setView('home')
        ->setViewVar('title', 'Hello')
        ->renderView();
}
```

Route: `"Shop:Home@index"` (no `!`).

### Without DI (trailing `!`) — preferred for hot paths

```php
public static function index($request)
{
    $renderer = \Dotsystems\App\Parts\Renderer::new();
    return $renderer->module('Shop')
        ->setView('home')
        ->setViewVar('title', 'Hello')
        ->renderView();
}
```

Route: `"Shop:Home@index!"`.

**Wrong with `!`:**

```php
// Will break — DI params are not injected when ! is used
public static function index($request, Renderer $renderer) { ... }
```

### Calling other controllers

```php
DotApp::call('Shop:Home@helper!', $arg1);
static::call('otherMethod', $request);
static::call('OtherModule:Page@withShell!', $title, $html);
```

Prefixes: `#` Middleware, `*` Models.

---

## Reading the request

```php
$data = $request->data();           // protected/escaped copy (by reference) — default
$raw  = $request->data(true);       // original bytes — MUST for passwords, HTML, hashes
$get  = $request->query();          // protected GET
$getRaw = $request->query(true);    // original GET
$id   = $request->matchData()['id'] ?? null;
$method = $request->getMethod();
```

**MUST:** `data()` / `query()` run `protect()` (old injection/XSS guard). **MUST** pass `true` for passwords, HTML, anything stored or compared as-is. `data()` on a password with `)`, `=`, `%` hashes the **wrong** string. Canonical: [19](19-VALIDATION-AND-INPUT.md). There is **no `headers()` method**.

Uploads: `$request->upload(function ($files) { ... });` — each entry has `field, name, type, size, tmp_name, error, extension`. Always check `$f['error'] !== UPLOAD_ERR_OK`.

Validation: `Validator::validate(...)` (returns `true` or an error array), `Input::group(...)` + `$request->validateInputs('group')`.

---

## Return values

| Return | Effect |
|--------|--------|
| HTML string | Becomes `$request->response->body` |
| `new Response($status, $body)` | Short-circuits pipeline |
| `Response::json($array, $code = 200)` | JSON body + content-type |
| `Response::redirect($url, $code = 302)` | Redirect |
| `DotApp::DotApp()->ajaxReply($body, $code)` | **Base64 JSON** — client must use `parseReply` |
| `null` | Keep existing body |

**Important:** static `Response::*` calls mutate a **shared** response object bound to the request and return a `Response` for chaining. There is no `send()` / static `status()`. Use `Response::make($code, $body)` or `new Response(...)` when you want a distinct object to return.

### View rendering

```php
return Renderer::new()
    ->module('Shop')
    ->setView('page')
    ->setLayout('home/content')   // optional
    ->setViewVar('title', $title)
    ->setViewVar('items', $items)
    ->renderView();
```

`renderLayout()` uses **layout vars only** (`setLayoutVar`).  
`renderView()` eval uses **view vars only** (`setViewVar`).  
Do not assume layout vars bleed into view vars. **VIEW = outer file:** `setLayout` + `renderView()` inserts the layout at `{{ content }}` in the view — or `renderLayout()` / inject a string ([05](05-VIEWS-TEMPLATES-ASSETS.md) §1b).

**Sandbox:** do not pass PHP function names as var names or nested string values (`time`, `copy`, `count`, `header`, …) — the isolator **drops the whole var**. Details: [05](05-VIEWS-TEMPLATES-ASSETS.md) §5.

### JSON API

```php
return Response::json(['status' => 1, 'items' => $rows]);
```

### Redirect styles

Preferred:

```php
return Response::redirect('/shop/', 302);
```

Legacy (still seen in examples): `header('Location: ...'); exit();` — works but bypasses Response object.

---

## API dispatch pattern

```php
// Route via apiPoint or any('/api/v1/shop/{resource}(?:/{id})?', 'Shop:Api@apiDispatch!')
public static function getItems($request) { ... }
public static function postItems($request) { ... }
```

Method name = lowercase HTTP verb + resource name (`get` + `Items` → `getItems`).

---

## Static module name helpers

```php
static::modulename(); // derived from FQCN
Controller::moduleName();
```

---

## Minimal complete controller (with correct error handling)

```php
<?php
namespace Dotsystems\App\Modules\Shop\Controllers;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Logger;
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\Response;

class Home extends \Dotsystems\App\Parts\Controller
{
    public static function index($request)
    {
        $rows = DB::module('RAW')->q(function ($qb) {
            $qb->select('*')->from('shop_items')->orderBy('id', 'DESC')->limit(50);
        })->all();                                  // [] when empty — safe

        $html = Renderer::new()
            ->module('Shop')
            ->setView('home', 'fallback/empty')     // fallback: missing view returns ""
            ->setViewVar('title', 'Shop')
            ->setViewVar('items', $rows)
            ->renderView();

        if ($html === '') {
            Logger::use()->error('Shop home view rendered empty');
            return new Response(500, 'Template error');
        }
        return $html;
    }

    public static function save($request)
    {
        try {
            // Isolated POST: crcCheck here. Under /api/v1/… CRC prefix: skip — only form().
            if (!$request->crcCheck()) {
                return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Bad request'], 400);
            }

            $answer = $request->form(
                ['POST'],
                'saveItem',
                function ($request) {
                    $payload = $request->data(true)['data'] ?? [];
                    $title = trim((string)($payload['title'] ?? ''));
                    if ($title === '') {
                        return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Title required']];
                    }

                    $newId = null;
                    DB::module('RAW')->q(function ($qb) use ($title) {
                        $qb->insert('shop_items', [
                            'title' => $title,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    })->execute(
                        function ($result, $db, $exec) use (&$newId) {
                            $newId = $exec['insert_id'] ?? $db->inserted_id();
                        },
                        function ($error) {
                            Logger::use()->error('insert failed', $error);
                        }
                    );

                    if ($newId === null) {
                        return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Save failed']];
                    }
                    return ['code' => 200, 'body' => ['status' => 1, 'id' => $newId]];
                },
                function ($request, $name) {                 // error callback is REQUIRED
                    return ['code' => 403, 'body' => ['status' => 0, 'message' => 'Invalid signature']];
                }
            );

            if (!is_array($answer) || !isset($answer['body'])) {   // handles null / false
                return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Rejected'], 400);
            }

            return DotApp::DotApp()->ajaxReply($answer['body'], $answer['code']);
        } catch (\Throwable $e) {
            Logger::use()->error('Shop save failed', ['msg' => $e->getMessage()]);
            return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Server error'], 500);
        }
    }
}
```

Why each guard exists: `form()` **throws** without an error callback, returns `false` on method mismatch and `null` on handler mismatch; `execute()` **throws** without an error callback; a missing view yields `""`. See [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md).
