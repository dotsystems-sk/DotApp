# 19 — Validation, Input, Request, Response, HTTP

Complete API with return shapes. See [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md) for the general contract.

---

## 1. Validator

```php
use Dotsystems\App\Parts\Validator;

// Bulk
$result = Validator::validate(
    ['email' => $email, 'age' => $age],
    ['email' => 'required|email', 'age' => 'required|integer|between:18,120']
);

if ($result === true) {
    // valid
} else {
    // $result is array<string, string[]>  e.g. ['email' => ['The email must be a valid email address.']]
}

// Single value (synthetic field name is "data")
$r = Validator::validate($email, 'required|email');
// failure shape: ['data' => ['...message...']]
```

**Check with `=== true`.** A failure array is truthy.

Throws `\InvalidArgumentException` for: parameter type mismatch, unknown rule, malformed rule parameters.

### Complete rule list

| Rule | Alias | Parameters |
|------|-------|------------|
| `required` | | — |
| `present` | `set` | — |
| `email` | | — |
| `numeric` | `number` | — |
| `integer` | | — |
| `positive_number` | | — |
| `between:min,max` | `in_range:min,max` | 2 numeric, inclusive |
| `min:n` | `min_length:n` | **string length**, not numeric minimum |
| `max:n` | `max_length:n` | **string length** |
| `url` | | — |
| `alpha` | | — |
| `alpha_num` | `alphanumeric` | — |
| `strong_password` | | optional bool → require special chars |
| `phone` | | — |
| `date` | | — |
| `in:a,b,c` | `one_of:a,b,c` | strict comparison |
| `json` | | — |
| `username` | | optional `min,max,dash,dot` (default `3,20,false,false`) |
| `boolean` | | **PHP `is_bool` only** — `"1"` fails |
| `credit_card` | | Luhn |
| `hex_color` | | — |
| `ip` | | — |
| `uuid` | | v4 |
| `not_empty_array` | | — |
| `valid_file_name` | | — |
| `regex:pattern` | | one full pattern |
| `unique` | | optional array-column key |

**Traps:** `min`/`max` are lengths; `boolean` rejects `"true"`/`1`; every rule runs even on empty values (no auto-skip).

### Static helpers (all return `bool`)

`isEmail`, `isRequired`, `isNumber`, `isInteger`, `isInRange($v,$min,$max)`, `isMinLength($v,$n)`, `isMaxLength($v,$n)`, `isUrl`, `isAlpha`, `isAlphanumeric`, `isStrongPassword($v, $special=false)`, `isPhoneNumber`, `isDate`, `isOneOf($v, array)`, `isJson`, `isUsername($v,$min=3,$max=20,$dash=false,$dot=false)`, `isBoolean`, `isCreditCard`, `isHexColor`, `isIpAddress`, `isUuid`, `isNotEmptyArray`, `isValidFileName`, `isPositiveNumber`, `isMatchingRegex($v,$regex)`, `isUniqueInArray($arr,$key=null)`, `isSet`

Messages are **hardcoded English** and not translatable in this class.

---

## 2. Input (secure form builder + group validation)

```php
use Dotsystems\App\Parts\Input;

$form = Input::group('register_form');
$form->text('username', ['class' => 'form-control'], 'required|alpha_num|min:3')
     ->email('email', [], 'required|email')
     ->password('pass', [], 'required|strong_password')
     ->hidden('ref', $ref);
```

Field builders: `text`, `password`, `email`, `number`, `file`, `hidden($name,$value,$attrs=[])`, `textarea`, `select($name, array $options, $attrs=[], $rules='')`, `checkbox($name,$value=1,...)`, `radio($name,$value,...)`

Other methods: `setValues(array)`, `getValue($name)`, `render($fieldKey)` (`""` if unknown), `export()`, `handleRequest($data)`, `validate()`, `getErrors()`, `getGroupName()`, `Input::loadFromRequest($data)`, `Input::addGlobalFilter($name, callable)`, `Input::group($name)`

### Returns

| Call | Success | Failure |
|------|---------|---------|
| `validate()` | `true` | `false` (read `getErrors()`) |
| `getErrors()` | `[]` | `['field' => 'single message string']` — **flat**, unlike Validator |
| `loadFromRequest($d)` | `Input` | **`null`** (missing key, decrypt fail, group mismatch) |
| `render($key)` | HTML string | `""` |

Input-only rule: **`match:otherField`**. Unknown rules **pass silently** unless registered via `addGlobalFilter($name, function ($value, $paramString, $allData) { return bool; })`.

Empty optional fields are skipped unless the rule is `required` / `present` / `set`.

### Template side

```html
<form method="POST">
  {{ InputKeys('register_form') }}
  {{ input:text name="username" rules="required|alpha_num" group="register_form" }}
  <button type="submit">Register</button>
</form>
```

Emits 2 hidden fields: `DotAppInputGroupKey`, `DotAppInputGroupData`.

> `{{ InputKeys(...) }}` and `{{ input: ... }}` are implemented in `Input.php` as registered custom renderers — not in `Renderer.php`.

### Server validation

```php
$result = $request->validateInputs('register_form');
if ($result !== true) {
    return Response::json($result, $result['error_code'] ?? 422);
}
```

Failure shapes:

| Cause | Shape |
|-------|-------|
| Invalid/missing security fields | `['status'=>0,'error_code'=>403,'status_txt'=>'Security Error: Invalid form state.','errors'=>['_security'=>'Invalid form signature.']]` |
| Group mismatch | same with `'Group mismatch.'` |
| Rule failures | `['status'=>0,'error_code'=>422,'status_txt'=>'Validation Failed','errors'=>[field=>message]]` |

`Request::validateInputs()` is **not** on the facade — call it on the `$request` object.

### formName (preferred for whole-form security)

`{{ formName(handler) }}` emits **4** encrypted hidden fields: `dotapp-secure-auto-fnname`, `...-action`, `...-method`, `...-public`. **MUST** place it **between** `<fo-rm>` and `</fo-rm>` — never after `</fo-rm>`. See [08-FORMS-AND-SECURITY.md](08-FORMS-AND-SECURITY.md).

---

## 3. RequestObj

**There is no `headers()` method.**

### Data access

| Call | Returns |
|------|---------|
| `data()` | **by reference** protected array (escaped) |
| `data(true)` | unprotected/original array |
| `query()` / `query(true)` | GET array |
| `matchData()` | array of route params |
| `matchData($arr)` | setter — **throws `\InvalidArgumentException`** if request is locked |
| `getMethod()` | lowercase method; disallowed method → **405 + exit** |
| `getPath()` | string |
| `getVars()` | query string or `null` |
| `getFullUrl()` / `getHost()` / `getPort()` / `isSecure()` | url parts |
| `body()` / `body($s)` | get/set `response->body` |
| `route()` / `hookData()` | get/set |
| `lock()` | `$this` |
| `requireAuth($returnData=false)` | `bool`, or auth array / `null` |
| `getDsm()` | `DSM` by reference |
| `firewall($rules,$ip,$default=true)` | `bool` |

Body parsing: `GET`→`$_GET`; `POST`→`$_POST`, falling back to JSON/`parse_str` on `php://input`; `PUT`/`PATCH`/`DELETE`→ body parsed as JSON or query string; `HEAD`/`OPTIONS`→`[]`. Result is cached after first call.

### Uploads

```php
$request->upload(function ($files) {
    foreach ($files as $f) {
        // $f: field, name, type, size, tmp_name, error, extension
        if ($f['error'] !== UPLOAD_ERR_OK) { continue; }
    }
});
```

Returns `$this`. Non-callable argument → `\InvalidArgumentException`; exception inside your callback is rethrown as `\RuntimeException`.

### Security methods

| Call | Returns | Notes |
|------|---------|-------|
| `crcCheck()` | `bool` | fails on: missing `data`/`crc`, non-array payload, used CSRF token, referer mismatch, CRC mismatch |
| `formSignatureCheck()` | `bool` | |
| `isValidCSRF($token)` | `bool` | **`true` = token NOT yet used** (i.e. valid) |
| `invalidateCSRF($token)` | void | |
| `form(...)` | see below | |
| `validateInputs($group)` | `true` or array | |

### `form()` overloads and returns

```php
$request->form($name, $success);
$request->form($name, $success, $error);
$request->form($method, $name, $success);
$request->form($method, $name, $success, $error, $rewriteAction = null);
```

Callback signature: `function ($request, $name)`. Your callback's return value is passed straight through.

| Situation | Result |
|-----------|--------|
| Success | your callback's return |
| HTTP method mismatch | `false` |
| Bad signature **with** `$error` | `$error($request, $name)` |
| Bad signature **without** `$error` | **throws `\Exception("Signature is invalid !")`** |
| Handler name / action mismatch | **`null`** |
| `$success` not callable | throws |

**Always** provide `$error` and handle `null`/`false`.

---

## 4. Response

**Static calls mutate a shared singleton bound to `$dotApp->request->response`.** They return a `Response` for chaining. There is **no `send()`** and no static `status()`/`body()`.

| Method | Effect |
|--------|--------|
| `Response::json($data, $code = 200, $flags = JSON_UNESCAPED_UNICODE)` | status + `Content-Type: application/json; charset=utf-8` + encoded body |
| `Response::redirect($url, $code = 302)` | status + redirect + `Location` |
| `Response::code(int)` | status |
| `Response::header($k,$v)` / `headers(array)` | headers |
| `Response::body2(string)` | body |
| `Response::append(string)` | concatenate body |
| `Response::contentType(string)` | content type + header |
| `Response::cookie($name,$value,array $options=[])` / `removeCookie($name,$path='/')` | cookies |
| `Response::cache($seconds, $public = false)` / `noCache()` | Cache-Control |
| `Response::download($filename, $content, $mime = 'application/octet-stream')` | headers + body |
| `Response::cors($origin='*', array $methods=[...], array $headers=[...])` | CORS |
| `Response::data($key, $value = null)` | arbitrary response data |
| `Response::new()` | reset the static instance |
| `Response::make($code, $body='')` / `answer(...)` / `jsonResponse($data,$code=200)` | **new** `Response` object |
| `Response::getCode()` / `getBody()` / `getHeaders()` / `isSent()` | read-only accessors |

Returning a `Response` instance from a handler or a `before` hook **short-circuits** the pipeline.

```php
return Response::json(['status' => 1, 'items' => $rows]);
return Response::redirect('/shop/', 302);
return new Response(403, 'Forbidden');
```

---

## 5. HttpHelper

```php
use Dotsystems\App\Parts\HttpHelper;

$res = HttpHelper::request(
    'POST',
    'https://api.example.com/v1/items',
    ['name' => 'x'],                      // $data
    ['timeout' => 10, 'connect_timeout' => 3],  // $auth/options
    ['X-Api-Key: ' . $key]                // $headers
);

if (!$res['success']) {
    Logger::use()->error('API failed', ['code' => $res['http_code'], 'err' => $res['error']]);
    return null;
}
$payload = $res['response'];              // decoded array (null on invalid JSON)
```

Full signature:

```php
HttpHelper::request(string $method, string $url, array $data = [], array $auth = [],
    array $headers = [], array $queryParams = [], ?string $rawBody = null, bool $binary = false): array
```

Returns exactly `['success' => bool, 'http_code' => int, 'response' => array|string|null, 'error' => string|null]`. **Never throws on network failure.** No response headers are returned.

Defaults: `connect_timeout` 2 s, `timeout` = connect + 30 s (HEAD: 0.5 s / 1 s).

Retries: `HttpHelper::requestWithRetries($method, $url, ..., $maxAttempts = 3, $initialDelayMs = 500)` retries 429 / 502–504 / transient cURL. Check with `HttpHelper::isRetryableTransportOrOverload($result)`.

---

## 6. Limiter

```php
use Dotsystems\App\Parts\Limiter;

$limiter = new Limiter([60 => 10, 3600 => 100], $identifier ?? null);
if (!$limiter->isAllowed('shop.save')) {
    return Response::json(['status' => 0, 'message' => 'Too many requests'], 429);
}
$remaining = $limiter->getRemaining('shop.save', 60);
$resetIn   = $limiter->getResetTime('shop.save', 60);
$headers   = $limiter->getLimitHeaders('shop.save');
// [60 => ['X-Rate-Limit-Limit'=>int,'X-Rate-Limit-Remaining'=>int,'X-Rate-Limit-Reset'=>int], ...]
```

Default identifier is the client IP. Empty limits array → `isAllowed()` **throws**.

Prefer the route chain for HTTP endpoints:

```php
Router::post('/shop/save', 'Shop:Items@save!')
    ->throttle(['per_minute' => 10, 'per_hour' => 100])
    ->limitExceeded(function ($request) {
        return new Response(429, 'Slow down');
    });
```

Without `limitExceeded()`, exceeding the limit emits a **429 JSON response and exits**.
