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

  <fo-rm method="post" id="contactForm" action="/shop/contact">
    <input type="text" name="email" autocomplete="username" required />
    <input type="text" name="message" required />

    <!-- Handler name MUST match PHP form(..., "saveContact", ...) -->
    {{ formName(saveContact) }}

    <div class="shop_actions">
      <button type="submit" id="contactBtn">{{_ "Send" }}</button>
    </div>
  </fo-rm>

  <script src="/assets/dotapp/dotapp.js"></script>
  <script src="/assets/modules/Shop/js/contact.js"></script>
</body>
</html>
```

Notes:

- The button sits in an action row. On a public page there is no DACore CSS, so the module defines it: `.shop_actions { display:flex; flex-wrap:wrap; gap:.75rem; padding:1rem 0 1.5rem; }` ([26](../26-CONTROL-SLICE.md) §2).
- Prefer **`<fo-rm>`** over `<form>` (bots/scanners target `<form>`; DotApp converts at runtime).
- **MUST:** `{{ formName(saveContact) }}` sits **between** `<fo-rm …>` and `</fo-rm>` — never after `</fo-rm>`, never before `<fo-rm>`. `method` and `action` on the opening tag are **literal**. **MUST NOT** put `{{ var: $postAction }}` (or any `{{ }}`) inside `<fo-rm …>`. That compiles to `<?php … ?>`; the `>` hides `method` and the page shows `{{ formName }}` as text. `method` before the variable still posts to the current page. Outside the pair, or with `method` hidden, the renderer leaves `{{ formName }}` unchanged (silent failure).
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
        // Why: a body that is not base64 JSON comes back from parseReply() as a string.
        var text = (reply && typeof reply === "object" && reply.message)
          ? reply.message
          : "The server sent an answer this page cannot read.";
        $dotapp("#error-message").attr("hide", "false").html(text);
        $dotapp(form).attr("blocked", "0");
        $dotapp("#contactBtn").removeAttr("loading").removeAttr("loader");
      })
      .onError(function (data, status, body, form) {
        // Why: .after() does not run on HTTP 4xx/5xx or a network fault. Without this hook
        // the form stays blocked, the button keeps spinning and the visitor sees nothing.
        var reply = $dotapp().parseReply(body);
        var text = (reply && typeof reply === "object" && reply.message)
          ? reply.message
          : "The message was not sent (HTTP " + status + "). Try again.";
        $dotapp("#error-message").attr("hide", "false").html(text);
        $dotapp(form).attr("blocked", "0");
        $dotapp("#contactBtn").removeAttr("loading").removeAttr("loader");
      });
  };

  if (window.$dotapp) {
    runMe(window.$dotapp);
  } else {
    window.addEventListener(
      "dotapp",
      function () {
        runMe(window.$dotapp);
      },
      { once: true }
    );
  }
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

`crcCheck()` **burns** the CSRF token. **MUST NOT** call it in middleware **and** here. If `initialize()` already attached `#DACore:AuthTest@CRC!` / `LoginAndCRC!` on this POST prefix, **skip** `crcCheck()` below — only `form()`. This sample is the isolated-POST path.

```php
<?php
namespace Dotsystems\App\Modules\Shop\Controllers;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\Validator;

class Contact extends \Dotsystems\App\Parts\Controller
{
    /**
     * CRCchecking — none (GET HTML). CRC is forbidden on GET.
     *
     * Renders the public contact page with the secure form.
     *
     * @param  mixed $request Current request.
     * @return mixed Rendered HTML.
     */
    public static function page($request)
    {
        return Renderer::new()->module('Shop')
            ->setView('contact')
            ->setViewVar('title', 'Contact')
            ->renderView();
    }

    /**
     * CRCchecking — this action (`$request->crcCheck()` once). No CRC prefix on this route.
     *
     * Saves the public contact form after CRC and formName bind.
     *
     * @param  mixed $request POST from `<fo-rm>`.
     * @return mixed JSON reply for `$dotapp().form`.
     */
    public static function save($request)
    {
        // Why: HTTP 200 on every branch — HTTP 400 would skip .after() and hide this sentence.
        $answer = ['code' => 200, 'body' => ['status' => 0, 'message' => 'This page is out of date. Reload it and send the form again.']];

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

<!-- WRONG: {{ }} inside the opening tag — method after the var is invisible; method first still cuts action -->
<fo-rm action="{{ var: $apiAuth }}/contact" method="post" id="contactForm">
<fo-rm method="post" id="contactForm" action="{{ var: $postAction }}">
```
