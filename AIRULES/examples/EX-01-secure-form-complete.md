# EX-01 — Complete secure form (preferred path)

**Use this for any login/contact/settings form.**  
Security level: **formName encrypted binding + CRC + one-time CSRF + referer tab** ≫ plain CSRF hidden field.

**MUST** read fields with `$request->data(true)` (original). `$request->data()` is `protect()`-escaped — passwords/HTML will not match. **MUST** show `reply.message` on every failure, including `crcCheck` / `form()` reject (“Bad request”). Canonical: [19](../19-VALIDATION-AND-INPUT.md).

## Why `dotapp.js` is mandatory

`/assets/dotapp/dotapp.js` is **not** a static library copy. The framework Bridge route generates it per client and injects random key material (CSRF tab token + key exchange). Without that script:

- CRC/CSRF fields are missing or wrong
- `fo-rm` is not converted / hijacked
- form posts are not DotApp-secure

Always include **before** your module script:

```html
<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/modules/Shop/js/contact.js"></script>
```

## 1) View — `<fo-rm>` + `formName`

```html
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="utf-8" />
  <title>{{ var: $title }}</title>
</head>
<body>
  <div id="error-message" hide="hide"></div>

  <fo-rm method="POST" id="contactForm" action="{{ var: $postAction }}">
    <input type="text" name="email" autocomplete="username" required />
    <input type="text" name="message" required />

    <!-- Handler name MUST match PHP form(..., "saveContact", ...) -->
    {{ formName(saveContact) }}

    <button type="submit" id="contactBtn">{{_ "Send" }}</button>
  </fo-rm>

  <script src="/assets/dotapp/dotapp.js"></script>
  <script src="/assets/modules/Shop/js/contact.js"></script>
</body>
</html>
```

Notes:

- Prefer **`<fo-rm>`** over `<form>` (bots/scanners target `<form>`; DotApp converts at runtime).
- **MUST:** `{{ formName(saveContact) }}` sits **between** `<fo-rm …>` and `</fo-rm>` — never after `</fo-rm>`, never before `<fo-rm>`. The tag must have `method`. Outside the pair the renderer leaves `{{ formName }}` unchanged (silent failure).
- Renderer emits encrypted hidden fields binding **handler name + action URL + HTTP method** to a per-form key. Forging or swapping handlers from HTML is not practical.

## 2) Module JS — hooks + loader + parseReply

```javascript
(function () {
  var runMe = function ($dotapp) {
    $dotapp()
      .form("#contactForm")
      .before(function (data, form) {
        if ($dotapp(form).attr("blocked") == 1) {
          return $dotapp().halt();
        }
        $dotapp(form).attr("blocked", "1");
        // Built-in button loading UX (DotApp attributes)
        $dotapp("#contactBtn").attr("loading", "true").attr("loader", "dots");
        $dotapp("#error-message").attr("hide", "hide");
      })
      .after(function (data, response, form) {
        var reply = $dotapp().parseReply(response);
        if (reply && reply.status == 1) {
          // This sample **leaves** the page. For lists / toggles / add-on-same-page
          // MUST patch reply.html + toast instead — see EX-06. Never location.reload().
          window.location = reply.redirectTo || "/shop/";
          return;
        }
        if (reply && reply.message) {
          $dotapp("#error-message").attr("hide", "false").html(reply.message);
        }
        $dotapp(form).attr("blocked", "0");
        $dotapp("#contactBtn").removeAttr("loading").removeAttr("loader");
      });
  };

  if (window.$dotapp) runMe(window.$dotapp);
  else window.addEventListener("dotapp", function () {
    runMe(window.$dotapp);
  }, { once: true });
})();
```

What DotApp does on submit (do not reimplement):

1. Converts `<fo-rm>` → `<form>`
2. `preventDefault`, serializes fields
3. Adds transport CSRF fields + **CRC** via `load()`
4. POSTs with header `dotapp: load`

## 3) Route

```php
Router::post('/shop/contact', 'Shop:Contact@save!', Router::STATIC_ROUTE);
Router::get('/shop/contact', 'Shop:Contact@page!', Router::STATIC_ROUTE);
```

## 4) Controller — crcCheck **once** → form → ajaxReply

`crcCheck()` **burns** the CSRF token. **MUST NOT** call it in middleware **and** here. If `initialize()` already attached `#Shop:Gate@crc!` on this POST prefix, **skip** `crcCheck()` below — only `form()`. This sample is the isolated-POST path.

```php
<?php
namespace Dotsystems\App\Modules\Shop\Controllers;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\Validator;

class Contact extends \Dotsystems\App\Parts\Controller
{
    public static function page($request)
    {
        return Renderer::new()->module('Shop')
            ->setView('contact')
            ->setViewVar('title', 'Contact')
            ->setViewVar('postAction', '/shop/contact')
            ->renderView();
    }

    public static function save($request)
    {
        $answer = ['code' => 400, 'body' => ['status' => 0, 'message' => 'Bad request']];

        if ($request->crcCheck()) {
            $answer = $request->form(['POST'], 'saveContact', function ($request) {
                $data = $request->data(true)['data'] ?? [];
                $email = $data['email'] ?? '';
                $message = $data['message'] ?? '';

                if (!Validator::isEmail($email)) {
                    return [
                        'code' => 200,
                        'body' => [
                            'status' => 0,
                            'errorNo' => 1,
                            'message' => 'Enter a valid email',
                        ],
                    ];
                }

                // persist with DB::module('RAW') ...

                return [
                    'code' => 200,
                    'body' => [
                        'status' => 1,
                        'message' => 'Saved',
                        'redirectTo' => '/shop/',
                    ],
                ];
            });
        }

        return DotApp::DotApp()->ajaxReply($answer['body'], $answer['code']);
    }
}
```

## Security model (why this beats plain CSRF)

| Layer | What it proves |
|-------|----------------|
| Encrypted `formName` fields | Exact PHP handler + method + action were issued by the server for this form |
| Per-form random key | Hidden fields cannot be reused across forms |
| CRC over POST `data` | Payload integrity |
| One-time CSRF random token | Replay resistance |
| Encrypted referer tab in `dotapp.js` | Script/context binding to the page that loaded keys |
| `<fo-rm>` | Reduces naive bot targeting of static `<form>` markup |

**Do not** skip `crcCheck()`. **Do not** accept the same endpoint via raw `fetch` without this pipeline. **Do not** use `data-dotapp-nojs` unless you rebuild the entire security chain.

## Anti-patterns

```html
<!-- WRONG -->
<form method="POST">...</form>
<input type="hidden" name="_token" value="...">  <!-- Laravel-style only -->

<!-- WRONG name -->
<div f-form>...</div>

<!-- WRONG: formName before fo-rm -->
{{ formName(saveContact) }}
<fo-rm>...</fo-rm>

<!-- WRONG: formName after </fo-rm> -->
<fo-rm>...</fo-rm>
{{ formName(saveContact) }}
```
