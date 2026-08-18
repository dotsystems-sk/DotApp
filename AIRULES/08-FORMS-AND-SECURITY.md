# 08 — Forms and Security (PREFERRED: fo-rm + formName)

## Policy for agents (non-negotiable)

When building a **real HTML form** (user fills fields and submits — CMS name/surname, login, settings, create/edit):

1. **Prefer** `<fo-rm>` + `{{ formName(handler) }}` + `/assets/dotapp/dotapp.js` + `$request->crcCheck()` + `$request->form(...)`.
2. **MUST:** `{{ formName(handler) }}` is a child **between** `<fo-rm …>` and `</fo-rm>`. Never before `<fo-rm>`, never after `</fo-rm>`. Outside that pair the renderer leaves the tag unchanged (silent failure).
3. Treat this as the **default** security path for those forms — not optional polish.
4. Plain CSRF (`{{ CSRF }}` alone), Laravel-style `_token`, or raw `fetch`/`$_POST` are **inferior** and must not replace formName for app forms.
5. Always load **`/assets/dotapp/dotapp.js`** before module scripts that use forms/bridge/load. That URL injects **per-session random encryption keys**. Without it, secure forms and `load()` do not work.

`<fo-rm>` is **not** required for every request. Clicks, row delete, toggles, reorder, drag-and-drop, JSON fetch: use `$dotapp().load()` (same CRC/CSRF). **MUST NOT** wrap those in `<fo-rm>`.

Full copy-paste sample: [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md)  
Edit/API sample: [examples/EX-02-secure-form-edit-api.md](examples/EX-02-secure-form-edit-api.md)

---

## When `<fo-rm>` vs `$dotapp().load()`

| Use | When |
|-----|------|
| `<fo-rm>` + `{{ formName }}` | Real form: several fields + submit (profile, CMS article, login, “save employee”). |
| `$dotapp().load(url, method, data, ok, err)` | One-shot action: click, toggle, delete, paginate, filter, **reorder / drag-and-drop**. |

`load()` **automatically** adds CSRF, CRC, the `dotapp: load` header and posts `{ data, crc }`. PHP **MUST** still `crcCheck()`. A `<fo-rm>` submit uses this same pipeline. **`fo-rm` does not make a click “more secure” than `load()`.**

### One-shot actions are not forms (**MUST**)

`<fo-rm>` **MUST** be used only when the user **fills several fields and submits**.

**MUST NOT** put `<fo-rm>` around row actions, including:

- move up / down, reorder, **drag-and-drop**
- toggle, delete, paginate, filter
- any single click that is not “submit this form”

Those **MUST** use `$dotapp().load()`:

1. Markup: `type="button"` (never `type="submit"`). Encrypted ids on the node: `data-rule="{{ enc(Shop.rule.id): $id }}"` — **unique `$key2` per field**.
2. JS: `$dotapp().live("click", ".js-toggle", …)` or a drag-drop callback. Read ids from `data-*`.  
   `$dotapp().load(url, "POST", { id: $dotapp(el).attr("data-rule"), f: "toggle" }, ok, err)`
3. PHP: `crcCheck()`, decrypt with the **same** `$key2`, **MUST** `Auth::can()` / ownership, return `{ status, message, html }`.
4. JS: patch the list (`reply.html`) + short toast. Bind with `.live()` so new HTML still works. **Delete MUST** open a graphical confirm first — never `alert()` / `window.confirm()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3 “Confirm before delete”).

**Drag-and-drop / reorder:** encrypted id on each item; on drop send the new order (array of ciphertexts) or `{ f: "move", id, from, to }` via `load()`. Never one `<fo-rm>` per arrow. **MUST** cover the whole list until `load()` finishes so a second drag cannot start ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3 “Block while in flight”).

**Wrong:** five `<fo-rm>` in one table row (up, down, toggle action, toggle active, delete).  
**Right:** optional **one** add/edit `<fo-rm>` above the table; the table is buttons + `data-*` + `load()`.

Copy-paste: [examples/EX-06-dotapp-js-boot.md](examples/EX-06-dotapp-js-boot.md).

Two layers (both **MUST**):

| Layer | What it protects | What it does **not** |
|-------|------------------|----------------------|
| `fo-rm` / `load()` + `crcCheck` | Transport (tamper, CSRF, wrong handler) | Meaning of `id=7` |
| Unique `$key2` encrypt on every FE identifier | Cross-field replay (product ciphertext cannot become userid) | Rights / ownership — **MUST** still `Auth::can()` |

---

## Identifiers on the frontend (**MUST**)

Never send a raw primary key the browser can copy onto another field or endpoint.

**Forbidden:** `value="7"`, `data-id="7"`, `data-user="6"`, JSON `{ "userid": 6, "productid": 8 }`.

**MUST:** encrypt with `Crypto::encrypt` / `{{ enc(Context): $id }}` and a **different `$key2` for every field** (`Shop.user.id` vs `Shop.product.id`). Same extra key on two fields → attacker copies `productid` ciphertext into `userid` and the other endpoint decrypts it.

```html
<select name="userid">
  <option value="{{ enc(Shop.user.id): $u.id }}">{{ var: $u.name }}</option>
</select>
<select name="productid">
  <option value="{{ enc(Shop.product.id): $p.id }}">{{ var: $p.title }}</option>
</select>
<div data-item="{{ enc(Shop.item.id): $item.id }}"></div>
```

```php
$uid = Crypto::decrypt($data['userid'] ?? '', 'Shop.user.id');
$pid = Crypto::decrypt($data['productid'] ?? '', 'Shop.product.id');
if ($uid === false || $pid === false) {
    return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Bad id']];
}
if (!Auth::can('Shop.users.edit')) { /* MUST still check rights */ }
```

JSON from `ajaxReply` / `load()`: encrypt IDs in PHP **before** sending (`Crypto::encrypt((string)$row['id'], 'Shop.item.id')`). Decrypt on the way back with the **same** `$key2`.

**MUST still authorize.** Unique `$key2` stops **type confusion** (product ≠ user). If one select lists many users, all under `Shop.user.id`, swapping one user token for another in **that same field** still works — `Auth::can()` and an ownership query are **required**. Do not skip rights because values are encrypted.

Full crypto contract: [11-AUTH-AND-CRYPTO.md](11-AUTH-AND-CRYPTO.md) §8. Renderer: [05-VIEWS-TEMPLATES-ASSETS.md](05-VIEWS-TEMPLATES-ASSETS.md).

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
    if (reply && reply.status == 1) {
        if (reply.html) $dotapp('#listWrap').html(reply.html);
        // toast — do not location.reload(); redirectTo only if leaving this page
    }
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
        return ['code' => 200, 'body' => ['status' => 1, 'message' => 'Saved', 'html' => $tableHtml]];
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

1. `<fo-rm>` + `{{ formName(...) }}` **only** for real HTML forms (several fields + submit). **MUST** place `formName` **between** `<fo-rm>` and `</fo-rm>`. One-shot row actions (toggle, delete, reorder, drag-and-drop, paginate): `$dotapp().load()` — **MUST NOT** wrap them in `<fo-rm>`.
2. `/assets/dotapp/dotapp.js` on every page that uses secure forms/bridge/load.
3. `crcCheck()` before trusting `form()` or `load()` bodies.
4. Encrypt every FE identifier with a **unique `$key2` per field**; decrypt with the same key; reject `false`. **MUST still** `Auth::can()` / ownership.
5. `ajaxReply` + client `parseReply`. On success **MUST** patch the DOM (e.g. `reply.html`) and a short toast — `<fo-rm>` does **not** reload the page. `redirectTo` only when leaving the page. See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
6. **MUST** block while in flight (desktop **and** mobile): form `blocked` + halt; button `loading`/`loader`; **your module preloaders** covering the list/form until `load()` ends (success **and** error). Notiflix is DACore-only — not available here. See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
7. **MUST** confirm deletes in a graphical dialog (module modal) — never `alert()` / `window.confirm()`.
8. **MUST** paginate accumulating lists on **first ship** (`paginate()` + interactive AJAX buttons + `$dotapp().load()`). **MUST NOT** dump `->all()`, skip because “few rows now”, or reload with `<a href="?page=">` / `location.reload()`. See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.

## Must-nots

1. Invent `f-form`.
2. Skip CRC “temporarily”.
3. Use jQuery `$` / `$.ajax`.
4. Rely on `{{ CSRF }}` alone as the full security story.
5. Put `{{ formName(...) }}` after `</fo-rm>` or before `<fo-rm>`.
6. Send raw IDs to the browser (`value="7"`, `data-id="7"`) or reuse one Crypto `$key2` on two fields.
7. Skip `Auth::can()` / ownership because IDs are encrypted.
8. `location.reload()` / empty `.after()` after a successful `fo-rm` or `load` while staying on the page.
9. One `<fo-rm>` per table-row button (up/down/toggle/delete) or drag-and-drop via forms.
10. Leave a list/form clickable (or start a second `load()`) while the first request is still in flight; forget to remove the overlay on the error path; skip module preloaders.
11. Delete with `alert()` / `window.confirm()` or with no confirm dialog.
12. Dump logs/users/items with `->all()` and no pager, or paginate by reloading `<a href="?page=">`.
13. Use `data-dotapp-nojs` unless rebuilding the whole chain.
14. Load raw `app/parts/js/dotapp.js` instead of `/assets/dotapp/dotapp.js` on pages.
