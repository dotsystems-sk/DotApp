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

**MUST** show the outcome ([00](../00-AGENT-CONTRACT.md) §2d). Mark the **wrong field** (red input + the exact sentence under it). The first `input` / `change` on that control clears **only that** mark. Success uses **your** module toast/status — never `alert()`, never empty `.after()`, never only “could not save” when `errors` names a field.

```javascript
.after(function (data, response, form) {
  var reply = $dotapp().parseReply(response);
  if (!reply) { return; }
  $dotapp(form).find(".shop_invalid").removeClass("shop_invalid");
  $dotapp(form).find(".shop_field_err").text("");
  if (reply.status == 1) {
    if (reply.html) { $dotapp('#listWrap').html(reply.html); }
    if (reply.message) { $dotapp("#status").attr("hide", "false").text(reply.message); }
    if (reply.redirectTo) { window.location = reply.redirectTo; }
    return;
  }
  if (reply.errors) {
    Object.keys(reply.errors).forEach(function (field) {
      var msgs = reply.errors[field];
      var text = Array.isArray(msgs) ? msgs[0] : msgs;
      var $el = $dotapp('[name="' + field + '"]');
      $el.addClass("shop_invalid");
      var $hint = $el.parent().find(".shop_field_err");
      if ($hint.length) { $hint.text(text); }
    });
  }
  if (reply.message && !reply.errors) {
    $dotapp('#error-message').attr('hide', 'false').text(reply.message);
  }
});
```

Markup next to each input: `<span class="shop_field_err"></span>`. Class prefix is the module’s (`shop_` here). PHP still re-validates.

```css
.shop_invalid {
  border-color: #b42318;
  background: #fdecea;
}
```

Clear **that** field when the person edits it. Other invalid fields stay marked until they are edited or the next successful save:

```javascript
$dotapp().live("input", ".shop_invalid", function (el) {
  el.classList.remove("shop_invalid");
  var hint = el.parentNode ? el.parentNode.querySelector(".shop_field_err") : null;
  if (hint) { hint.textContent = ""; }
});
```
