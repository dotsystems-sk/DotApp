# 08 — Forms and Security (PREFERRED: fo-rm + formName)

## Policy for agents (non-negotiable)

When building **any interactive browser form** in DotApp:

1. **Prefer** `<fo-rm>` + `{{ formName(handler) }}` + `/assets/dotapp/dotapp.js` + `$request->crcCheck()` + `$request->form(...)`.
2. Treat this as the **default and recommended** security path — not optional polish.
3. Plain CSRF (`{{ CSRF }}` alone), Laravel-style `_token`, or raw `fetch`/`$_POST` are **inferior** and must not replace formName for app forms.
4. Always load **`/assets/dotapp/dotapp.js`** before module scripts that use forms/bridge/load. That URL injects **per-session random encryption keys**. Without it, secure forms do not work.

Full copy-paste sample: [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md)  
Edit/API sample: [examples/EX-02-secure-form-edit-api.md](examples/EX-02-secure-form-edit-api.md)

---

## Why formName ≫ plain CSRF

| Mechanism | Plain CSRF hidden field | DotApp `formName` + transport |
|-----------|-------------------------|-------------------------------|
| Proves request came from a page that saw a token | Yes (weakly) | Yes |
| Binds to **specific PHP handler name** | No | **Yes** (encrypted) |
| Binds to **action URL + HTTP method** | No | **Yes** |
| Per-form encryption key | No | **Yes** |
| Payload CRC integrity | No | **Yes** (`crcCheck`) |
| One-time random CSRF in JS transport | Rarely | **Yes** |
| Referer/tab binding via generated `dotapp.js` keys | No | **Yes** |
| Obfuscated markup (`<fo-rm>`) | No | **Yes** |

Agents must **not** invent `f-form`. The tag is **`<fo-rm>`**.

---

## Why `/assets/dotapp/dotapp.js` is mandatory

- Served by framework Bridge route (not a dumb static file for production pages).
- Injects random key material used to seal CSRF/CRC for that browser session.
- Converts `<fo-rm>` → `<form>`, hijacks submit, adds CRC + CSRF, posts with `dotapp: load`.
- Powers `$dotapp().form()`, `load()`, bridge, loaders (`loading` / `loader` attributes).

```html
<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/modules/Shop/js/page.js"></script>
```

If you omit this script, do not claim the form is DotApp-secure.

---

## Minimal correct chain

### Template

```html
<fo-rm method="POST" id="saveForm" action="{{ var: $postAction }}">
  <input type="text" name="title" />
  {{ formName(saveItem) }}
  <button type="submit" id="saveBtn">Save</button>
</fo-rm>
<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/modules/Shop/js/save.js"></script>
```

### JS (loader + halt + parseReply)

```javascript
$dotapp().form("#saveForm")
  .before(function (data, form) {
    if ($dotapp(form).attr("blocked") == 1) return $dotapp().halt();
    $dotapp(form).attr("blocked", "1");
    $dotapp("#saveBtn").attr("loading", "true").attr("loader", "dots");
  })
  .after(function (data, response, form) {
    var reply = $dotapp().parseReply(response);
    // handle reply.status ...
    $dotapp(form).attr("blocked", "0");
    $dotapp("#saveBtn").removeAttr("loading").removeAttr("loader");
  });
```

Boot with the `dotapp` event — see [examples/EX-06-dotapp-js-boot.md](examples/EX-06-dotapp-js-boot.md).

### PHP

```php
if ($request->crcCheck()) {
    $answer = $request->form(['POST'], 'saveItem', function ($request) {
        $data = $request->data(true)['data'] ?? [];
        // validate + persist
        return ['code' => 200, 'body' => ['status' => 1, 'redirectTo' => '/']];
    });
}
return \Dotsystems\App\DotApp::DotApp()->ajaxReply($answer['body'], $answer['code']);
```

Handler string in `formName(saveItem)` **must equal** `form(..., 'saveItem', ...)`.

---

## Input::group (secondary)

Field-schema validation with encrypted group keys — use when you need declarative rules per field. Still typically rides the DotApp transport. See `Input.php` and `$request->validateInputs('group')`.

For standard UX forms, **formName remains preferred**.

---

## Request data

| Call | Use |
|------|-----|
| `$request->data(true)['data']` | Secure form field values after DotApp unwrap |
| `$request->data()` | Protected/escaped copy — not for crypto/password compare |

---

## Must-dos

1. `<fo-rm>` + `{{ formName(...) }}` for user forms.
2. `/assets/dotapp/dotapp.js` on every page that uses secure forms/bridge/load.
3. `crcCheck()` before trusting `form()`.
4. `ajaxReply` + client `parseReply`.
5. Button loaders via `loading`/`loader` attributes where UX needs a spinner.

## Must-nots

1. Invent `f-form`.
2. Skip CRC “temporarily”.
3. Use jQuery `$` / `$.ajax`.
4. Rely on `{{ CSRF }}` alone as the full security story.
5. Use `data-dotapp-nojs` unless rebuilding the whole chain.
6. Load raw `app/parts/js/dotapp.js` instead of `/assets/dotapp/dotapp.js` on pages.
