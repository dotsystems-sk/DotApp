# EX-06 — DotApp.js boot, loaders, form hooks

## Mandatory script

```html
<script src="/assets/dotapp/dotapp.js"></script>
```

This URL is served by the framework with **injected random keys**. Do not replace it with a raw file under `app/parts/js/` on production pages.

## Boot

```javascript
(function () {
  var runMe = function ($dotapp) {
    // page logic
  };
  if (window.$dotapp) runMe(window.$dotapp);
  else window.addEventListener("dotapp", function () {
    runMe(window.$dotapp);
  }, { once: true });
})();
```

Plugins (`$dotapp().fn(...)`) register on **`dotapp-register`**, not `dotapp`. Full library recipe (new + jQuery port): [EX-15-dotapp-js-library.md](EX-15-dotapp-js-library.md) and [09](../09-DOTAPP-JS-AND-BRIDGE.md) §4 / §4.C. Never edit `app/parts/js/`.

## Submit button loader (common UX)

```javascript
$dotapp("#saveBtn").attr("loading", "true").attr("loader", "dots");
// on done:
$dotapp("#saveBtn").removeAttr("loading").removeAttr("loader");
```

## Double-submit guard

```javascript
.before(function (data, form) {
  if ($dotapp(form).attr("blocked") == 1) return $dotapp().halt();
  $dotapp(form).attr("blocked", "1");
})
.after(function (data, response, form) {
  $dotapp(form).attr("blocked", "0");
});
```

## After success: live DOM (**MUST**)

`<fo-rm>` never reloads the page. Empty `.after()` = DB changed, UI stale. Rules: [09](../09-DOTAPP-JS-AND-BRIDGE.md) §3.

Stay on the list / settings page:

```javascript
.after(function (data, response, form) {
  var reply = $dotapp().parseReply(response);
  if (reply && reply.status == 1) {
    if (reply.html) $dotapp("#listInner").html(reply.html);
    if (reply.message) $dotapp("#status").attr("hide", "false").html(reply.message);
  } else if (reply && reply.message) {
    $dotapp("#error-message").attr("hide", "false").html(reply.message);
  }
  $dotapp(form).attr("blocked", "0");
  $dotapp("#saveBtn").removeAttr("loading").removeAttr("loader");
  $dotapp("#listWrap").removeClass("shop_busy");
});
```

Toggle / delete / reorder (no form) — **MUST** cover the list with **your module overlay** until done ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §3). Patch a **child** (`TBODY` / `#listInner`), not the wrapper you overlay. CSS: `#listWrap { position: relative; }` + `.shop_busy::after { … }` (visible on desktop **and** mobile, intercepts pointer and touch):

```javascript
var listBusy = false;
function listDone() {
  listBusy = false;
  $dotapp("#listWrap").removeClass("shop_busy");
}
$dotapp().live("click", ".js-toggle", function (e) {
  if (listBusy) return;
  listBusy = true;
  $dotapp("#listWrap").addClass("shop_busy");
  $dotapp().load("/shop/rules/toggle", "POST", { id: $dotapp(e.currentTarget).attr("data-id") },
    function (raw) {
      var reply = $dotapp().parseReply(raw);
      if (reply && reply.status == 1 && reply.html) $dotapp("#listInner").html(reply.html);
      if (reply && reply.message) $dotapp("#status").attr("hide", "false").html(reply.message);
      listDone();
    },
    function () { listDone(); }
  );
});
```

PHP body: `['status' => 1, 'message' => 'Enabled', 'html' => $tableHtml]`. Encrypt `data-id` (`{{ enc(Shop.rule.id): $id }}`). Re-bind via `.live()` after HTML replace.

Row markup — **no** `<fo-rm>` per button:

```html
<div id="listWrap" class="shop_listwrap">
  <table>
    <tbody id="listInner">
      <tr data-rule="{{ enc(Shop.rule.id): $rule.id }}">
        <td>{{ var: $rule.title }}</td>
        <td>
          <button type="button" class="js-toggle btn btn-sm">Toggle</button>
          <button type="button" class="js-delete btn btn-sm">Delete</button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

Drag-and-drop: same — `data-rule="{{ enc(Shop.rule.id): $id }}"` on the token; on drop cover `#listWrap`, then `$dotapp().load(..., { f: "move", id: ..., from: ..., to: ... })`. Never one `<fo-rm>` per arrow. Never start a second drag until the overlay is gone.

**Pager MUST exist and MUST be AJAX** (users, logs, items — any accumulating list, first ship). SQL `paginate()`. Buttons, not `<a href="?page=">` / `location.reload()`:

```javascript
$dotapp().live("click", ".js-shop-page", function (e) {
  var page = parseInt($dotapp(e.currentTarget).attr("data-page"), 10) || 1;
  if (listBusy) return;
  listBusy = true;
  $dotapp("#listWrap").addClass("shop_busy");
  $dotapp().load("/api/v1/auth/Shop/items/list", "POST", { page: page, q: currentQuery },
    function (raw) {
      var reply = $dotapp().parseReply(raw);
      if (reply && reply.status == 1 && reply.html) $dotapp("#listInner").html(reply.html);
      listDone();
    },
    function () { listDone(); }
  );
});
```

PHP: clamp `page` to `1 … last_page`, return `{ status: 1, html: $rowsAndPager }`. Patch rows **and** the pager. First paint may be page 1 server-rendered. Keep `q` in every list POST.

**Search — ASK in the plan.** Lookup lists (articles, products, catalog) **MUST** ship interactive AJAX search unless declined. Debounce ~300 ms, fire from **3 characters**, SQL `LIKE` + `paginate()`, overlay, patch `#listInner`. **MUST NOT** `<fo-rm>` on each keystroke. Empty / under 3 chars = unfiltered page 1.

```javascript
var currentQuery = "";
var searchTimer = null;
$dotapp().live("input", "#shopSearch", function () {
  var q = ($dotapp("#shopSearch").val() || "").trim();
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(function () {
    currentQuery = q.length >= 3 ? q : "";
    if (listBusy) return;
    listBusy = true;
    $dotapp("#listWrap").addClass("shop_busy");
    $dotapp().load("/api/v1/auth/Shop/items/list", "POST", { page: 1, q: currentQuery },
      function (raw) {
        var reply = $dotapp().parseReply(raw);
        if (reply && reply.status == 1 && reply.html) $dotapp("#listInner").html(reply.html);
        listDone();
      },
      function () { listDone(); }
    );
  }, 300);
});
```

**Delete MUST confirm first** (graphical dialog — never `alert()` / `window.confirm()`):

```javascript
$dotapp().live("click", ".js-delete", function (e) {
  var id = $dotapp(e.currentTarget).attr("data-id");
  $dotapp("#shopConfirmTitle").text("Delete this item?");
  $dotapp("#shopConfirmText").text("This cannot be undone.");
  $dotapp("#shopConfirm").removeAttr("hidden");
  $dotapp("#shopConfirm .js-confirm-ok").off("click").on("click", function () {
    $dotapp("#shopConfirm").attr("hidden", "hidden");
    if (listBusy) return;
    listBusy = true;
    $dotapp("#listWrap").addClass("shop_busy");
    $dotapp().load("/shop/rules/delete", "POST", { id: id },
      function (raw) {
        var reply = $dotapp().parseReply(raw);
        if (reply && reply.status == 1 && reply.html) $dotapp("#listInner").html(reply.html);
        listDone();
      },
      function () { listDone(); }
    );
  });
  $dotapp("#shopConfirm .js-confirm-cancel").off("click").on("click", function () {
    $dotapp("#shopConfirm").attr("hidden", "hidden");
  });
});
```

`window.location = reply.redirectTo` only when **leaving** the page (login, wizard). Never `location.reload()`.

## Never

- `$('#x')`, `jQuery`, `$.ajax`
- Assume `$dotapp('#x').val()` returns a chainable object (often returns the value)
- Call secure endpoints with raw `fetch` without CRC/CSRF
- `location.reload()` / empty success handler after `fo-rm` or `load` while staying on the page
- `alert()` / `window.confirm()` / `prompt()` — use a graphical dialog; deletes **MUST** confirm first
- One `<fo-rm>` per table-row action (up/down/toggle/delete) or drag-and-drop via forms
- Leave the list/form clickable during `load()`; skip overlay cleanup on the error path; skip module preloaders
- Growing list with no pager, or pager that reloads via `<a href="?page=">`
- Lookup list with no search, or search that reloads / filters `->all()` in JS
