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

## Reply contract for `load()` / `form()` endpoints

One shape, one helper — [26](../26-CONTROL-SLICE.md) §7:

```php
return Reply::fail('Check the highlighted fields.', $fieldErrors);   // status 0 + errors { field: sentence }
return Reply::ok('Contact saved.', ['html' => $html]);                // status 1
```

Always HTTP 200. HTTP 4xx/5xx skips `.after()`, so a failure sent that way never reaches the page. Only the CRC prefix (400) and the rights middleware (403) answer non-200, and the `.onError()` hook shows those.

## Client side

**MUST** show the outcome on all three paths ([00](../00-AGENT-CONTRACT.md) §2d): `status` 1, `status` 0, and HTTP / network failure.

**DACore admin:** `shopForm()` from [26](../26-CONTROL-SLICE.md) §5 — toast. Field marks are an optional extra.

**Public page:** mark the **wrong field** (where + what) and show a status line. There is no Notiflix and no `dacoreRequestError` here, so the module helper does the same job with its own nodes:

```javascript
// About: public-page variant of the module request helper (same names as AIRULES/26 §5).
function shopReply(raw) {
  var reply = $dotapp().parseReply(raw);
  if (!reply || typeof reply !== "object") {
    return { status: 0, message: "The server sent an answer this page cannot read." };
  }
  return reply;
}

function shopShow(form, kind, text) {
  $dotapp(form).find(".shop_status").attr("data-kind", kind).text(text);
}

function shopMarkFields(form, errors) {
  $dotapp(form).find(".shop_invalid").removeClass("shop_invalid");
  $dotapp(form).find(".shop_field_err").text("");
  Object.keys(errors || {}).forEach(function (field) {
    var text = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
    var input = $dotapp(form).find('[name="' + field + '"]');
    input.addClass("shop_invalid");
    input.parent().find(".shop_field_err").text(text);
  });
}

function shopForm(selector, onOk) {
  $dotapp().form(selector)
    .before(function (data, form) {
      if ($dotapp(form).attr("blocked") == 1) {
        return $dotapp().halt();
      }
      $dotapp(form).attr("blocked", "1").addClass("shop_busy");
    })
    .after(function (data, raw, form) {
      var reply = shopReply(raw);
      $dotapp(form).attr("blocked", "0").removeClass("shop_busy");
      shopMarkFields(form, reply.errors);
      if (reply.status == 1) {
        shopShow(form, "ok", reply.message || "");
        if (typeof onOk === "function") {
          onOk(reply);
        }
        return;
      }
      shopShow(form, "error", reply.message || "The form was not sent and the server gave no reason.");
    })
    .onError(function (data, status, body, form) {
      // Why: .after() does not run on HTTP 4xx/5xx or a network fault.
      var reply = $dotapp().parseReply(body);
      var text = (reply && typeof reply === "object" && reply.message)
        ? reply.message
        : "The form was not sent (HTTP " + status + "). Try again.";
      $dotapp(form).attr("blocked", "0").removeClass("shop_busy");
      shopShow(form, "error", text);
    });
}

shopForm("#contactForm", function (reply) {
  if (reply.redirectTo) {
    window.location = reply.redirectTo;
  }
});
```

Texts are written with `.text()`, never `.html()` — they may contain what the visitor typed. `.shop_busy` is the module overlay class (covers the form, blocks pointer and touch).

Public markup: `<span class="shop_field_err"></span>` next to each input and one `<p class="shop_status"></p>` inside the form. PHP still re-validates.
