# 26 — Control slice: one button is six parts (LAW, write-time)

Read this **before** you write a button, a link that acts, a form, a toggle, a row action or a pager. It is a recipe for writing, not a checklist for afterwards. `/finalcontrol` exists to confirm this file was followed; needing it to repair buttons means this file was skipped.

Every code block here is verified against `app/parts/js/dotapp.js`, `app/parts/RequestObj.php` and the DACore shell. Copy the blocks. Do not write a variation of them.

## 1. The law

A control is **one slice of six parts**. Write all six in the **same chunk**, in this order, before you start the next control. A control with five parts is not "almost done" — it is a dead or silent button.

| # | Part | Where | Done when |
|---|------|-------|-----------|
| 1 | Markup in an action row | `views/…` | The button sits in `.dacore-card-actions` / a `d-flex gap-2` row, never bare (§2) |
| 2 | Listener | module `assets/js/…` | `shopForm()` or `$dotapp().live(..., function (el, e) {})` whose selector is spelled exactly as in part 1 (§3–§5) |
| 3 | Route | `module.init.php` | The exact URL and HTTP method the listener calls, with the rights `before` (§6) |
| 4 | Action | `Controllers/…` | The method named by the route exists and ends every branch in `Reply::ok()` / `Reply::fail()` (§7) |
| 5 | Reply | part 4 | HTTP **200**, `status` 0\|1, a `message` that names what happened (§7) |
| 6 | Visible outcome | part 2 | Toast on **success**, on **product failure** (`status` 0) **and** on **transport failure** (HTTP 4xx/5xx, network); overlay removed on all three (§5) |

After the slice, add one row to your reply — every cell filled with `file:line`:

```
| Control | View | Listener | URL + method | Route | Controller@method | Success toast | Failure toast |
```

An empty cell means the control is not written yet. Do not write "done".

Then run the source check and fix what it prints:

```
php AIRULES/tools/control-check.php <Module>
```

## 2. Markup: where a button lives

**DACore is not Bootstrap.** Its `core.css` has only some utility classes. A class with no CSS rule adds no space, so the button stays glued to the edge.

| Exists in the DACore shell — use | Does **not** exist — never write |
|----------------------------------|----------------------------------|
| `.dacore-card-actions` (button row: flex, wrap, gap, padding on all four sides) | `card-footer` |
| `.dacore-toolbar` (row above a list), `.dacore-empty` (empty state) | `pb-3` `pb-4` `pt-0` `pt-3` `px-4` `py-3` |
| `d-flex` `flex-wrap` `align-items-center` `justify-content-between` / `-end` / `-center` | `border-0` `bg-transparent` |
| `gap-1` … `gap-6` | any other `p*-N` / `m*-N` not listed on the left |
| `mb-0` … `mb-6`, `mt-1` … `mt-6`, `ms-1` … `ms-3`, `me-2` … `me-5`, `p-0` `p-2` `p-4` `p-6`, `pb-0` `pb-2`, `pt-2` `pt-4` | |

One exception: the list pager bar keeps its audited pair `card-footer dacore-list-pager` ([40](40-DACORE-LIST-PAGER.md)) — its padding comes from `.dacore-list-pager`, not from `card-footer`.

Not sure about a class? `grep -c "\.the-class" app/modules/DACore/assets/css/core.css`. Zero hits = it does nothing. Write the rule in **your** module CSS (`{lowercase_modulename}_*`) instead.

### Form card (copy)

```html
<div class="card mb-6" id="shopItemCard">
  <h5 class="card-header">{{_ "Item" }}</h5>

  <fo-rm method="post" action="/api/v1/auth/Shop/items/save" id="shopItemForm">
    <div class="card-body">
      <input type="hidden" name="item" value="{{ enc(Shop.item.id): $itemId }}" />
      <!-- fields -->
      {{ formName(saveItem) }}
    </div>

    <div class="dacore-card-actions">
      <a href="/dacore/Shop/items" class="btn btn-outline-secondary">{{_ "Cancel" }}</a>
      <button type="submit" class="btn btn-primary">{{_ "Save" }}</button>
    </div>
  </fo-rm>
</div>
```

Rules this block follows — keep them:

- The buttons are in their **own row** (`.dacore-card-actions`), a sibling **after** `.card-body`. Never the last line inside `.card-body`. Never straight after an input.
- `<fo-rm>` wraps `.card-body` **and** the row, so Save is inside the form.
- Two buttons never touch: the row has `gap`. Primary action is last (right).
- Consecutive cards are separated by `mb-6` on the card.

### Row actions and list toolbar (copy)

```html
<div class="dacore-toolbar">
  <a href="/dacore/Shop/items/new" class="btn btn-primary">{{_ "New item" }}</a>
</div>

<div class="card mb-6" id="shopItemsWrap">
  <div id="shopItemsInner">
    <table class="table">
      <tbody>
        <tr>
          <td>{{ var: $item.title }}</td>
          <td>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
              <button type="button" class="btn btn-sm btn-outline-secondary shop_item_toggle" data-item="{{ enc(Shop.item.id): $item.id }}">{{_ "Disable" }}</button>
              <button type="button" class="btn btn-sm btn-outline-danger shop_item_delete" data-item="{{ enc(Shop.item.id): $item.id }}">{{_ "Delete" }}</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

`#shopItemsWrap` is the region you overlay. `#shopItemsInner` is the part you replace. They are never the same node.

**Older DACore or a public page** (no `.dacore-card-actions` in the loaded CSS): put the same rule in your module CSS and use that class.

```css
.shop_actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .75rem; padding: 1rem 1.25rem 1.5rem; }
```

## 3. The JS file: one shape

Every page script has exactly this shape. The last block is copied **character for character** — no one-line `if … else`, no `DOMContentLoaded`, no `document.addEventListener`, no polling, no arrow functions.

```javascript
(function () {
  function runMe($dotapp) {
    // Section: Shop — items list

    // About: delete one item after a graphical confirm.
    $dotapp().live("click", ".shop_item_delete", function (el, e) {
      // handler body — §5
    });
  }

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

| File is | Event name in the block |
|---------|-------------------------|
| Page code (handlers, forms) | `"dotapp"` |
| A library that calls `$dotapp().fn(...)` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4) | `"dotapp-register"` |

Both events are dispatched on **`window`** (`dotapp.js`, last lines: `dotapp-register` first, then `dotapp`). `document.addEventListener("dotapp", …)` never fires.

Inside `runMe`: module helpers first (§5), then one `// Section:` per screen, then one `// About:` + one handler per control. No handler outside `runMe`.

## 4. Inside `runMe`: `$dotapp`, not raw DOM

You have a library. Use it. Raw DOM in page code is a finding of the source check.

| You want | Write | Not |
|----------|-------|-----|
| Find elements | `$dotapp("#shopItemsInner")`, `$dotapp(".shop_row")` | `document.getElementById`, `querySelector(All)` |
| The clicked element | `$dotapp(el)` — `el` is the **first** argument of the `live` handler | `e.currentTarget`, `this` |
| Read an attribute | `$dotapp(el).attr("data-item")` | `el.getAttribute(...)` |
| Set an attribute | `$dotapp(el).attr("aria-busy", "true")` | `el.setAttribute(...)` |
| Read / set a field | `$dotapp("#shopTitle").val()` / `.val("x")` | `.value` |
| Put server HTML | `$dotapp("#shopItemsInner").html(reply.html)` | `.innerHTML =` |
| Put plain text | `$dotapp("#shopCount").text(reply.total)` | `.textContent =` |
| Classes | `.addClass()` `.removeClass()` `.toggleClass()` `.hasClass()` | `classList` |
| Show / hide | `.show()` `.hide()` `.toggle()` | `style.display` |
| Up from an element | `$dotapp(el).closest("tr")`, `$dotapp(el).parent()` | `el.closest`, `el.parentElement` |
| Down into a region | `$dotapp("#shopItemCard .shop_field_err")` or `$dotapp(el).find(".shop_field_err")`, `.children()`, `.siblings()` | `el.querySelector`, `el.children` |
| First / last / n-th of a set | `.first()`, `.last()`, `.nth(i)` (each returns a `$dotapp` object); raw element: `.get(i)` | `[0]`, `querySelectorAll(...)[i]` |
| Any event on any element, also on markup replaced later | `$dotapp().live("click", ".shop_x", function (el, e) {})` | `el.addEventListener`, `onclick=` |
| A request | `shopSend()` / `shopForm()` (§5) → `$dotapp().load()` / `.form()` | `fetch`, `XMLHttpRequest`, `$.ajax` |
| Loop | `$dotapp(".shop_row").each(function (index, el) {})` — index first | `forEach` over `querySelectorAll` |
| Is anything matched? | `$dotapp(".shop_row").length` | `document.querySelectorAll(...).length` |

`$dotapp(x)` accepts a **selector string**, one **HTMLElement**, a `NodeList` / array of elements, or another `$dotapp` object.

Getters return the **value**, not the chain: `.attr(name)`, `.val()`, `.text()`, `.data(key)`, `.hasClass()`, `.is()`. Read into a `var`, then act. `.val()` returns `null` for an empty field — write `var q = $dotapp("#shopSearch").val() || "";`. `.html()` with no argument returns the chain — read markup with `$dotapp("#x").html().getLastResult()`. `.html(markup)` on a selector that matches nothing **throws**, so the id in the view and in the JS must be the same.

Two traps that remain:

| Method | What it really does | Write instead |
|--------|--------------------|---------------|
| `.on(event, selector, fn)` | Not delegated; returns an unsubscribe function, not the chain | `$dotapp().live(event, selector, function (el, e) {})` |
| `.live("focus" / "blur", …)` | Never fires (these events do not bubble) | `"focusin"` / `"focusout"` |

`$dotapp().uploadFile(file, url, function (name, percent) {})` reports real upload progress; on failure the rejected `Error` carries `.status` and `.responseText`.

Old `dotapp.js` (before 2026-11-14): `.find()` / `.children()` / `.siblings()` / `.clone()` returned an empty collection, `.last()` threw, `.nth()` returned nothing, `.length` did not exist and the upload progress callback never ran. If `$dotapp("body").length` is `undefined`, the framework copy is old — update the framework; do not work around it with raw DOM.

A `<fo-rm>` that arrives later inside `reply.html` is **not** taken over by `dotapp.js` (forms are taken over once, at page load). Keep the form outside the fragment you replace, or use `shopSend()` for that action.

Raw DOM is allowed only where `$dotapp` has no method: `e.preventDefault()`, `el.disabled`, `window.scrollY`, `window.location`, and the internals of a `$dotapp().fn` library ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4). Add `// Why:` on that line.

## 5. One request helper per module (the outcome funnel)

Silent buttons come from hand-writing overlay + `parseReply` + toast + error path at every call and forgetting one. So write these four functions **once**, at the top of `runMe` in the module admin script (or a shared module file), and make **every** request go through them. Rename the `shop` prefix to your module.

```javascript
// About: turns any response body into a reply object. Never returns null.
// Why: parseReply() gives back the raw string when the body is not base64 JSON (PHP warning, HTML error page).
function shopReply(raw) {
  var reply = $dotapp().parseReply(raw);
  if (!reply || typeof reply !== "object") {
    return { status: 0, message: "The server sent an answer this page cannot read." };
  }
  return reply;
}

// About: the single place where a finished request becomes a toast.
function shopDone(region, reply, onOk) {
  Notiflix.Block.remove(region);
  if (reply.status == 1) {
    if (reply.message) {
      Notiflix.Notify.success(reply.message);
    }
    if (typeof onOk === "function") {
      onOk(reply);
    }
    return;
  }
  Notiflix.Notify.failure(reply.message || "The action failed and the server gave no reason.");
}

// About: HTTP 4xx/5xx and network faults. The success callback never runs for them.
// Why: dacoreRequestError (loaded by the DACore shell) reads reply.message out of an error body.
function shopFail(region, status, body) {
  Notiflix.Block.remove(region);
  Notiflix.Notify.failure(window.dacoreRequestError.message(status, body, "The request did not reach the server"));
}

// About: row action / toggle / delete / pager. region = stable parent that is NOT replaced.
function shopSend(url, payload, region, onOk) {
  Notiflix.Block.standard(region, "Working…");
  $dotapp().load(url, "POST", payload,
    function (raw) {
      shopDone(region, shopReply(raw), onOk);
    },
    function (status, body) {
      shopFail(region, status, body);
    }
  ).catch(function () {
    // Why: load() also rejects its Promise on failure; shopFail already told the user.
  });
}

// About: a real multi-field <fo-rm>. Same funnel, three hooks.
// Why: .after() does NOT run on HTTP 4xx/5xx — only .onError() does. Without it the form stays blocked forever.
function shopForm(selector, region, onOk) {
  $dotapp().form(selector)
    .before(function (data, form) {
      if ($dotapp(form).attr("blocked") == 1) {
        return $dotapp().halt();
      }
      $dotapp(form).attr("blocked", "1");
      Notiflix.Block.standard(region, "Saving…");
    })
    .after(function (data, raw, form) {
      $dotapp(form).attr("blocked", "0");
      shopDone(region, shopReply(raw), onOk);
    })
    .onError(function (data, status, body, form) {
      $dotapp(form).attr("blocked", "0");
      shopFail(region, status, body);
    });
}
```

A control is then a few lines, and it cannot be silent:

```javascript
// Section: Shop — item editor
// About: save the item form; stay on the page.
shopForm("#shopItemForm", "#shopItemCard");

// Section: Shop — items list
// About: delete one item after a graphical confirm, then redraw the rows.
$dotapp().live("click", ".shop_item_delete", function (el, e) {
  var item = $dotapp(el).attr("data-item");
  Notiflix.Confirm.show("Delete this item?", "This cannot be undone.", "Delete", "Cancel", function () {
    shopSend("/api/v1/auth/Shop/items/delete", { item: item }, "#shopItemsWrap", function (reply) {
      $dotapp("#shopItemsInner").html(reply.html);
    });
  });
});
```

**MUST NOT** call `$dotapp().load()` or `$dotapp().form()` directly in a handler. **MUST NOT** write a second copy of the funnel in another file.

**Public page** (no DACore shell, so no Notiflix and no `dacoreRequestError`): keep the same four function names and signatures. Replace `Notiflix.Block.*` with your module overlay, `Notiflix.Notify.*` with your module notice, and in `shopFail` use `shopReply(body).message` or `"The request failed (HTTP " + status + ")"`. Field errors: for each key in `reply.errors`, mark that input and print the text under it.

## 6. Route

Register the route in the **same chunk** as the listener. The path and method are the ones in the JS string — compare them character by character.

```php
// Section: Shop — items API
// Why: LoginAndCRC burns the CRC once for every POST below; actions MUST NOT crcCheck() again.
Router::before(['POST'], [$authApi, $authApi . '/*'], '#DACore:AuthTest@LoginAndCRC!');

if (Auth::isLogged() === true) {
    Router::post($authApi . '/items/save', 'Shop:Items@save!', Router::STATIC_ROUTE)
        ->before(function ($request) {
            return DotApp::call('#Shop:Rights@check!', $request, ['dotapp.root', 'Shop.items.edit']);
        });
    Router::post($authApi . '/items/delete', 'Shop:Items@delete!', Router::STATIC_ROUTE)
        ->before(function ($request) {
            return DotApp::call('#Shop:Rights@check!', $request, ['dotapp.root', 'Shop.items.delete']);
        });
}
```

`$authApi` is `/api/v1/auth/Shop`. Full wiring: [EX-D01](examples/EX-D01-dacore-module-skeleton.md), rights: [32](32-DACORE-RIGHTS.md).

## 7. Action and reply

### The reply contract (the only shape)

| Key | Value | When |
|-----|-------|------|
| HTTP status | **200** | Always, for anything the action itself decides. 403 from the rights middleware and 400 from the CRC prefix are the only non-200 answers, and `shopFail` shows them. |
| `status` | `1` or `0` | Always |
| `message` | One product sentence | Always on `0`. On `1` unless the user is leaving the page. |
| `errors` | `{ field: "sentence" }` | On `0` when a field is wrong |
| `html` | Re-rendered fragment (Renderer) | On `1` when the page shows the changed data |
| `redirectTo` | Path | On `1` only when leaving the page |

This contract is for endpoints called by `$dotapp().load()` / `.form()`. An endpoint for an **external** client (another server, a mobile app) uses `Response::json()` with real HTTP codes instead ([16](16-RECIPES.md) R5).

Send it with `DotApp::DotApp()->ajaxReply($body, 200)`. **MUST NOT** answer a `load()` / `form()` caller with `Response::json()`: the body is not base64, `parseReply()` hands back a string, and the page shows nothing.

### One reply helper per module (copy to `Libraries/Reply.php`)

```php
<?php
namespace Dotsystems\App\Modules\Shop\Libraries;

use Dotsystems\App\DotApp;

/**
 * About: the only place this module builds an AJAX answer, so every answer has the same keys.
 */
class Reply
{
    /**
     * Answers a finished action.
     *
     * @param  string $message What happened, as the operator reads it in the toast.
     * @param  array  $extra   Optional `html`, `redirectTo` or data keys.
     * @return mixed  Base64 JSON, HTTP 200, status 1.
     */
    public static function ok($message, array $extra = [])
    {
        $extra['status'] = 1;
        $extra['message'] = (string) $message;
        return DotApp::DotApp()->ajaxReply($extra, 200);
    }

    /**
     * Answers an action that did not happen.
     *
     * @param  string $message What failed and why, as the operator reads it in the toast.
     * @param  array  $errors  Optional field => sentence map.
     * @return mixed  Base64 JSON, HTTP 200, status 0.
     */
    public static function fail($message, array $errors = [])
    {
        // Why: HTTP 200 on purpose. HTTP 4xx/5xx skips .after() and the load() success callback.
        return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => (string) $message, 'errors' => $errors], 200);
    }
}
```

### Action skeleton (copy, then fill the middle)

Every numbered step is a place where the action can stop. Each stop has **its own sentence**. That sentence is how you later find which step failed.

```php
/**
 * CRCchecking — #DACore:AuthTest@LoginAndCRC! (prefix /api/v1/auth/Shop/*). No crcCheck() here.
 *
 * Deletes one catalogue item and returns the redrawn rows.
 *
 * @param  mixed $request POST from the Delete row button.
 * @return mixed Reply::ok() with `html`, or Reply::fail() naming the step that stopped.
 */
public static function delete($request)
{
    $id = 0;
    try {
        // Why: load() posts { data: {...}, crc } — the fields are one level down.
        $post = $request->data(true)['data'] ?? [];

        // 1. Input
        $id = Crypto::decrypt((string) ($post['item'] ?? ''), 'Shop.item.id');
        if ($id === false) {
            return Reply::fail('This item link has expired. Reload the page and try again.');
        }

        // 2. Record
        if (!self::itemExists((int) $id)) {
            return Reply::fail('This item was already deleted.');
        }

        // 3. Write
        $failed = null;
        DB::module('RAW')->q(function ($qb) use ($id) {
            $qb->delete('shop_items')->where('id', '=', (int) $id);
        })->execute(
            function ($result, $db, $exec) {
            },
            function ($error) use (&$failed, $id) {
                $failed = $error;
                self::reportCatch('Shop:Items@delete', 'shop.item.delete', null, 'error',
                    ['item_id' => (int) $id, 'errno' => $error['errno'] ?? 0], $error['error'] ?? 'db error');
            }
        );
        if ($failed !== null) {
            return Reply::fail('The item was not deleted: the database refused the change (shop.item.delete). The fault was logged.');
        }

        // 4. Answer
        return Reply::ok('Item deleted.', ['html' => self::rowsHtml()]);
    } catch (\Throwable $e) {
        self::reportCatch('Shop:Items@delete', 'shop.item.delete', $e, 'error', ['item_id' => (int) $id]);
        return Reply::fail('The item was not deleted because of a server fault (shop.item.delete). The fault was logged.');
    }
}
```

A `<fo-rm>` action differs only at the top: `form()` checks the signature and hands you the fields.

```php
$answer = $request->form(['POST'], 'saveItem', function ($request) {
    return self::saveItem($request->data(true)['data'] ?? []);   // returns Reply::ok() / Reply::fail()
}, function ($request, $name) {
    // Why: without this callback a bad signature throws.
    return Reply::fail('This form has expired. Reload the page and try again.');
});
if ($answer === false || $answer === null) {
    // Why: form() gives false on a wrong HTTP method and null when formName / action do not match.
    return Reply::fail('This form does not match its handler (saveItem). Reload the page and try again.');
}
return $answer;
```

### Messages

| Stop | Bad | Good |
|------|-----|------|
| Field | `Invalid input` | `Enter a price greater than 0.` + `errors: { price: … }` |
| Record | `Not found` | `This item was already deleted.` |
| Rule | `Error` | `The item cannot be deleted while 3 orders use it.` |
| Database `$err` | `Save failed` | `The item was not saved: the database refused the change (shop.item.update). The fault was logged.` |
| `catch` | `Server error` | `The item was not saved because of a server fault (shop.item.update). The fault was logged.` |

- **MUST** name the object and the step. **MUST NOT** write `Server error`, `Bad request`, `Rejected`, `Error`, `Failed`, `Something went wrong`.
- A server fault carries its **operation slug** — the same slug passed to `reportCatch()`. The developer greps the slug and lands on the catch report. **MUST NOT** put `$e->getMessage()`, SQL, a path or a token in `message`.
- `$request->crcCheck()` returns `1` / `0` / `false`, never `true`. Test it with `if (!$request->crcCheck())`. Under the `LoginAndCRC` prefix do not call it at all.
- Where the real exception goes: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9 (`reportCatch`) and [23](23-DEBUG-PLAYBOOK.md) §1b. `Logger` alone is not a trail — `core_log_enabled` is off by default.

## 8. Before you say done

1. The slice table (§1) has a row for every control you touched, no empty cell.
2. `php AIRULES/tools/control-check.php <Module>` printed `OK — 0 findings`, or you list each remaining line with the reason it is correct.
3. Each selector in JS is spelled as in the view. Each URL in JS is spelled as in `module.init.php`.
4. Your reply ends with a click list: *open X → press Y → expect toast "Z"*, including one failure case per control.
