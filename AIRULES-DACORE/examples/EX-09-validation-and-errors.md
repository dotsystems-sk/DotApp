# EX-09 — Validation and structured error responses

Rules: [19-VALIDATION-AND-INPUT.md](../19-VALIDATION-AND-INPUT.md), [18-ERROR-HANDLING-AND-RETURN-VALUES.md](../18-ERROR-HANDLING-AND-RETURN-VALUES.md).

## Validator — check with `=== true`

```php
use Dotsystems\App\Parts\Validator;

$rules = [
    'email' => 'required|email',
    'username' => 'required|alpha_num|min:3|max:20',
    'age' => 'required|integer|between:18,120',
    'website' => 'url',
];

$result = Validator::validate($payload, $rules);

if ($result !== true) {
    // $result === ['email' => ['The email must be a valid email address.'], ...]
    return ['code' => 200, 'body' => [
        'status' => 0,
        'message' => 'Validation failed',
        'errors' => $result,
    ]];
}
```

Traps: `min`/`max` are **string lengths**, not numeric bounds (use `between:`). `boolean` accepts only real PHP booleans — `"1"` fails. Unknown rule names **throw** `\InvalidArgumentException`.

## Single value

```php
$r = Validator::validate($email, 'required|email');
if ($r !== true) {
    $messages = $r['data'];    // synthetic field name
}
```

## Static helpers (bool)

```php
if (!Validator::isEmail($email)) { /* ... */ }
if (!Validator::isStrongPassword($pass, true)) { /* require special chars */ }
if (!Validator::isUsername($u, 3, 20, false, false)) { /* ... */ }
```

---

## Input groups (schema-signed forms)

```php
use Dotsystems\App\Parts\Input;

// building (controller that renders the form)
$form = Input::group('register_form');
$form->text('username', ['class' => 'form-control'], 'required|alpha_num|min:3')
     ->email('email', [], 'required|email')
     ->password('pass', [], 'required|strong_password');
```

```html
<form method="POST" action="/shop/register">
  {{ InputKeys('register_form') }}
  {{ input:text name="username" rules="required|alpha_num|min:3" group="register_form" }}
  {{ input:email name="email" rules="required|email" group="register_form" }}
  {{ input:password name="pass" rules="required|strong_password" group="register_form" }}
  <button type="submit">Register</button>
</form>
```

```php
// handler
$result = $request->validateInputs('register_form');

if ($result !== true) {
    // 403 -> tampered/missing security fields; 422 -> rule failures
    return Response::json($result, $result['error_code'] ?? 422);
}
$data = $request->data(true);
```

Custom rule:

```php
Input::addGlobalFilter('even', function ($value, $paramString, $allData) {
    return is_numeric($value) && ((int) $value % 2 === 0);
});
```

Unknown rules **pass silently** in `Input` — register a filter or use `Validator` when strictness matters.

---

## Canonical error envelope for JSON endpoints

```php
return DotApp::DotApp()->ajaxReply([
    'status' => 0,          // 1 = success
    'errorNo' => 2,         // your own code, for the JS side
    'message' => 'Human readable message',
    'errors' => $fieldErrors ?? null,
], 200);
```

Keep HTTP 200 for business validation failures (the JS `parseReply` path reads `status`), and reserve 4xx/5xx for transport/authorisation problems.

## Client side

```javascript
.after(function (data, response, form) {
  var reply = $dotapp().parseReply(response);
  if (!reply) { return; }
  if (reply.status == 1) {
    if (reply.html) { $dotapp('#listWrap').html(reply.html); }
    else if (reply.redirectTo) { window.location = reply.redirectTo; }
    return;
  }
  if (reply.errors) {
    Object.keys(reply.errors).forEach(function (field) {
      $dotapp('[name="' + field + '"]').addClass('danger');
    });
  }
  $dotapp('#error-message').attr('hide', 'false').html(reply.message || 'Error');
});
```
