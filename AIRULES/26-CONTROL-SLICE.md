# 26 — Control slice: one button is six parts (LAW, write-time)

Read this **before** you write a button, a link that acts, a form, a toggle, a row action or a pager. It is a recipe for writing, not a checklist for afterwards. `/finalcontrol` exists to confirm this file was followed.

Every code block here is verified against `app/parts/js/dotapp.js` and `app/parts/RequestObj.php`. This handbook has **no** DACore shell and **no** Notiflix. The overlay and the notice are built in the module. Copy the blocks. Do not write a variation of the boot block or of the four request functions.

## 1. The law

A control is **one slice of six parts**. Write all six in the **same chunk**, in this order, before you start the next control. A control with five parts is a dead or silent button.

| # | Part | Where | Done when |
|---|------|-------|-----------|
| 1 | Markup in an action row | `views/…` | The button sits in `{module}_actions` / a flex row with a gap, never bare (§2) |
| 2 | Listener | module `assets/js/…` | `shopForm()` or `$dotapp().live(..., function (el, e) {})` whose selector is spelled exactly as in part 1 (§3–§5) |
| 3 | Route | `module.init.php` | The exact URL and HTTP method the listener calls, with the rights `before` (§6) |
| 4 | Action | `Controllers/…` | The method named by the route exists and ends every branch in `Reply::ok()` / `Reply::fail()` (§7) |
| 5 | Reply | part 4 | HTTP **200**, `status` 0\|1, a `message` that names what happened (§7) |
| 6 | Visible outcome | part 2 | Notice on **success**, on **product failure** (`status` 0) **and** on **transport failure** (HTTP 4xx/5xx, network); overlay removed on all three (§5) |

After the slice, add one row to your reply — every cell filled with `file:line`:

```
| Control | View | Listener | URL + method | Route | Controller@method | Success notice | Failure notice |
```

An empty cell means the control is not written yet. Do not write "done".

Then run the source check and fix what it prints:

```
php AIRULES/tools/control-check.php <Module>
```

## 2. Markup: where a button lives

The button row is **this module’s CSS**. A class with no rule adds no space.

```css
.shop_actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: .75rem;
  padding: 1rem 1.25rem 1.5rem;
}
```

### Form card (copy)

```html
<div class="card shop_card" id="shopItemCard">
  <h5>{{_ "Item" }}</h5>

  <fo-rm method="post" action="/api/v1/auth/Shop/items/save" id="shopItemForm">
    <div class="shop_card_body">
      <input type="hidden" name="item" value="{{ enc(Shop.item.id): $itemId }}" />
      {{ formName(saveItem) }}
    </div>

    <div class="shop_actions">
      <a href="/Shop/items" class="btn">{{_ "Cancel" }}</a>
      <button type="submit" class="btn">{{_ "Save" }}</button>
    </div>
  </fo-rm>
</div>
```

- The buttons are in their **own row** (`.shop_actions`), a sibling **after** the fields. Never the last line inside the field block. Never straight after an input.
- `<fo-rm>` wraps the fields **and** the row, so Save is inside the form.
- Two buttons never touch: the row has `gap`. Primary action is last.
- The row has padding on all four sides, including below.

### Row actions (copy)

```html
<div class="card" id="shopItemsWrap">
  <div id="shopItemsInner">
    <table>
      <tbody>
        <tr>
          <td>{{ var: $item.title }}</td>
          <td>
            <div class="shop_actions">
              <button type="button" class="btn shop_item_toggle" data-item="{{ enc(Shop.item.id): $item.id }}">{{_ "Disable" }}</button>
              <button type="button" class="btn shop_item_delete" data-item="{{ enc(Shop.item.id): $item.id }}">{{_ "Delete" }}</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

`#shopItemsWrap` is the region you overlay. `#shopItemsInner` is the part you replace. They are never the same node.

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

Both events are dispatched on **`window`** (`dotapp.js`, last lines: `dotapp-register` first, then `dotapp`). `document.addEventListener("dotapp", …)` never fires. A script that runs after `dotapp.js` never receives the event — that is why the `if (window.$dotapp)` branch is mandatory.

`dotapp.js` takes over `<fo-rm>` **once**, when it executes. A `<fo-rm>` inserted later through `reply.html` is **not** taken over. Keep the form outside the fragment you replace, or use `shopSend()` for that action.

Inside `runMe`: module helpers first (§5), then one `// Section:` per screen, then one `// About:` + one handler per control. No handler outside `runMe`.

## 4. Inside `runMe`: `$dotapp`, not raw DOM

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
| Down into a region | `$dotapp(el).find(".shop_field_err")`, `.children()`, `.siblings()` | `el.querySelector`, `el.children` |
| First / last / n-th of a set | `.first()`, `.last()`, `.nth(i)` (each returns a `$dotapp` object); raw element: `.get(i)` | `[0]`, `querySelectorAll(...)[i]` |
| Any event, also on markup replaced later | `$dotapp().live("click", ".shop_x", function (el, e) {})` | `el.addEventListener`, `onclick=` |
| A request | `shopSend()` / `shopForm()` (§5) | `fetch`, `XMLHttpRequest`, `$.ajax` |
| Loop | `$dotapp(".shop_row").each(function (index, el) {})` — index first | `forEach` over `querySelectorAll` |
| Is anything matched? | `$dotapp(".shop_row").length` | `document.querySelectorAll(...).length` |

`$dotapp(x)` accepts a **selector string**, one **HTMLElement**, a `NodeList` / array of elements, or another `$dotapp` object.

Getters return the **value**, not the chain: `.attr(name)`, `.val()`, `.text()`, `.data(key)`, `.hasClass()`, `.is()`. Read into a `var`, then act. `.val()` returns `null` for an empty field — write `var q = $dotapp("#shopSearch").val() || "";`. `.html()` with no argument returns the chain — read markup with `$dotapp("#x").html().getLastResult()`. `.html(markup)` on a selector that matches nothing **throws**, so the id in the view and in the JS must be the same.

| Method | What it really does | Write instead |
|--------|--------------------|---------------|
| `.on(event, selector, fn)` | Not delegated; returns an unsubscribe function, not the chain | `$dotapp().live(event, selector, function (el, e) {})` |
| `.live("focus" / "blur", …)` | Never fires (these events do not bubble) | `"focusin"` / `"focusout"` |

`$dotapp().uploadFile(file, url, function (name, percent) {})` reports real upload progress. On HTTP failure the Promise rejects with an `Error` that carries `.status` and `.responseText`.

**Library date.** `dotapp.js` from **2026-11-14** fixes `.find()` / `.children()` / `.siblings()` / `.clone()` (they used to return an empty collection), `.last()` (it used to throw), `.nth()` (it used to return nothing), `.length` (it did not exist) and the upload progress callback (it never ran). If `$dotapp("body").length` is `undefined`, the framework copy is older than that fix — update the framework. Do not work around it with raw DOM.

Raw DOM is allowed only where `$dotapp` has no method: `e.preventDefault()`, `el.disabled`, `window.scrollY`, `window.location`, and the internals of a `$dotapp().fn` library ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4). Add `// Why:` on that line.

## 5. One request helper per module (the outcome funnel)

Write these four functions **once**, at the top of `runMe`, and make **every** request go through them. Rename the `shop` prefix to your module. `shopBusy` / `shopIdle` / `shopNotice` are the module overlay and notice — there is no shared toast library here.

```javascript
function shopReply(raw) {
  // Why: parseReply() gives back the raw string when the body is not base64 JSON.
  var reply = $dotapp().parseReply(raw);
  if (!reply || typeof reply !== "object") {
    return { status: 0, message: "The server sent an answer this page cannot read." };
  }
  return reply;
}

function shopDone(region, reply, onOk) {
  shopIdle(region);
  if (reply.status == 1) {
    if (reply.message) {
      shopNotice(reply.message, true);
    }
    if (typeof onOk === "function") {
      onOk(reply);
    }
    return;
  }
  shopNotice(reply.message || "The action failed and the server gave no reason.", false);
  shopMarkFields(reply.errors);
}

function shopFail(region, status, body) {
  shopIdle(region);
  var reply = shopReply(body);
  var message = (reply && reply.message) ? reply.message : ("The request failed (HTTP " + status + ")");
  shopNotice(message, false);
}

function shopSend(url, payload, region, onOk) {
  shopBusy(region);
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

function shopForm(selector, region, onOk) {
  $dotapp().form(selector)
    .before(function (data, form) {
      if ($dotapp(form).attr("blocked") == 1) {
        return $dotapp().halt();
      }
      $dotapp(form).attr("blocked", "1");
      shopBusy(region);
    })
    .after(function (data, raw, form) {
      $dotapp(form).attr("blocked", "0");
      shopDone(region, shopReply(raw), onOk);
    })
    .onError(function (data, status, body, form) {
      // Why: .after() does NOT run on HTTP 4xx/5xx. Without this the form stays blocked.
      $dotapp(form).attr("blocked", "0");
      shopFail(region, status, body);
    });
}
```

`shopMarkFields(errors)` paints each named input red and writes the sentence under it ([00](00-AGENT-CONTRACT.md) §2d). A missing `errors` object does nothing.

```javascript
shopForm("#shopItemForm", "#shopItemCard");

$dotapp().live("click", ".shop_item_delete", function (el, e) {
  var item = $dotapp(el).attr("data-item");
  shopConfirm("Delete this item?", function () {
    shopSend("/api/v1/auth/Shop/items/delete", { item: item }, "#shopItemsWrap", function (reply) {
      $dotapp("#shopItemsInner").html(reply.html);
    });
  });
});
```

**MUST NOT** call `$dotapp().load()` or `$dotapp().form()` directly in a handler. **MUST NOT** write a second copy of the funnel. `.form()` without `.onError()` leaves the form blocked and shows nothing.

## 6. Route

The path and method are the ones in the JS string.

```php
// Section: Shop — items API
// Why: loginAndCrc burns the CRC once for every POST below; actions MUST NOT crcCheck() again.
Router::before(['POST'], [$authApi, $authApi . '/*'], '#Shop:Gate@loginAndCrc!');

if (Auth::isLogged() === true) {
    Router::post($authApi . '/items/save', 'Shop:Items@save!', Router::STATIC_ROUTE)
        ->before('#Shop:Gate@canEdit!');
    Router::post($authApi . '/items/delete', 'Shop:Items@delete!', Router::STATIC_ROUTE)
        ->before('#Shop:Gate@canDelete!');
}
```

`$authApi` is `/api/v1/auth/Shop`. The `before` names are this module’s own gate. Full wiring: [03](03-MODULES-AND-ROUTING.md).

## 7. Action and reply

| Key | Value | When |
|-----|-------|------|
| HTTP status | **200** | Always, for anything the action itself decides. 403 from the login gate and 400 from the CRC prefix are the only non-200 answers, and `shopFail` shows them. |
| `status` | `1` or `0` | Always |
| `message` | One product sentence | Always on `0`. On `1` unless the user is leaving the page. |
| `errors` | `{ field: "sentence" }` | On `0` when a field is wrong |
| `html` | Re-rendered fragment (Renderer) | On `1` when the page shows the changed data |
| `redirectTo` | Path | On `1` only when leaving the page |

This contract is for endpoints called by `$dotapp().load()` / `.form()`. An endpoint for an **external** client uses `Response::json()` with real HTTP codes instead ([16](16-RECIPES.md)).

Send it with `DotApp::DotApp()->ajaxReply($body, 200)`. **MUST NOT** answer a `load()` / `form()` caller with `Response::json()`: the body is not base64, `parseReply()` hands back a string, and the page shows nothing.

### One reply helper per module (`Libraries/Reply.php`)

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
     * @param  string $message What happened, as the person reads it.
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
     * @param  string $message What failed and why.
     * @param  array  $errors  Optional field => sentence map.
     * @return mixed  Base64 JSON, HTTP 200, status 0.
     */
    public static function fail($message, array $errors = [])
    {
        // Why: HTTP 200 on purpose. HTTP 4xx/5xx skips .after() and the load() success callback.
        return DotApp::DotApp()->ajaxReply([
            'status' => 0,
            'message' => (string) $message,
            'errors' => $errors,
        ], 200);
    }
}
```

### Action skeleton

```php
/**
 * CRCchecking — #Shop:Gate@loginAndCrc! (prefix /api/v1/auth/Shop/*). No crcCheck() here.
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
        $id = Crypto::decrypt((string) ($post['item'] ?? ''), 'Shop.item.id');
        if ($id === false) {
            return Reply::fail('This item link has expired. Reload the page and try again.');
        }
        if (!self::itemExists((int) $id)) {
            return Reply::fail('This item was already deleted.');
        }
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
        return Reply::ok('Item deleted.', ['html' => self::rowsHtml()]);
    } catch (\Throwable $e) {
        self::reportCatch('Shop:Items@delete', 'shop.item.delete', $e, 'error', ['item_id' => (int) $id]);
        return Reply::fail('The item was not deleted because of a server fault (shop.item.delete). The fault was logged.');
    }
}
```

A `<fo-rm>` action differs only at the top:

```php
$answer = $request->form(['POST'], 'saveItem', function ($request) {
    return self::saveItem($request->data(true)['data'] ?? []);
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
- A server fault carries its **operation slug** — the same slug passed to `reportCatch()`. **MUST NOT** put `$e->getMessage()`, SQL, a path or a token in `message`.
- `$request->crcCheck()` returns `1` / `0` / `false`, never `true`. Test it with `if (!$request->crcCheck())`. Under a CRC prefix do not call it at all.
- Where the real exception goes: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.

## 8. Before you say done

1. The slice table (§1) has a row for every control you touched, no empty cell.
2. `php AIRULES/tools/control-check.php <Module>` printed `OK — 0 findings`, or you list each remaining line with the reason it is correct.
3. Each selector in JS is spelled as in the view. Each URL in JS is spelled as in `module.init.php`.
4. Your reply ends with a click list: *open X → press Y → expect notice "Z"*, including one failure case per control.
