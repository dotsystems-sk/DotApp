# 33 — DACore Admin Pages and UI Contract

Your module renders **only its content**. DACore supplies the shell (head, sidebar, navbar, footer, AI chat).

---

## 1. The rendering pattern

Two steps: render your content with the framework `Renderer`, then hand the HTML to DACore.

```php
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\Translator;

public static function items($request)
{
    $rows = /* ... DB query, see 06 ... */;

    $html = Renderer::new()
        ->module('Shop')
        ->setLayout('admin/items', 'admin/empty')      // 2nd arg = fallback
        ->setLayoutVar('items', $rows)
        ->setLayoutVar('baseUrl', Config::module('DACore', 'prefixUrl') . '/shop-admin')
        ->renderLayout();                               // layout vars only!

    if ($html === '') {
        Logger::use()->error('Shop admin items layout rendered empty');
        return new Response(500, 'Template error');
    }

    return static::call(
        "DACore:Page@withMenu!",
        Translator::trans('Items'),                     // $title
        $html,                                          // $body
        [],                                             // $header
        ['/assets/modules/Shop/css/admin.css'],         // $css
        ['/assets/modules/Shop/js/admin-items.js'],     // $js
        ''                                              // $menuId: '' = shared; 'Shop.nav' = module-own — ASK ([31])
    );
}
```

`renderLayout()` sees **layout vars only**; `renderView()` sees **view vars only** ([05](05-VIEWS-TEMPLATES-ASSETS.md)). A missing template returns `""` silently — always use the fallback argument and check.

---

## 2. `Page@withMenu` signature

```php
DotApp::call(
    "DACore:Page@withMenu!",
    string $title = "",
    string $body = "",
    array|string $header = [],
    array|string $css = [],
    array|string $js = [],
    ?string $menuId = null
): string
```

| Param | Behaviour |
|-------|-----------|
| `$title` | Passed through `Translator::trans()` |
| `$body` | Your rendered HTML, injected into the content container |
| `$header` | Array of raw HTML lines for `<head>`, or a ready string |
| `$css` | Array of URLs → `<link rel="stylesheet" href="...">`; empty entries skipped |
| `$js` | Array of URLs → `<script src="..."></script>` before `</body>` |
| `$menuId` | `''`/`null` = full shared menu; a `menuid` = **direct children of that id** (one level) plus a synthetic **Return back** leaf at the bottom (do not register Return back) |

**MUST ASK** when starting a **new** DACore module: shared full sidebar vs module-own menu. Canonical layout, grouping (`type` 0 / 2 / 1), and wiring: [31](31-DACORE-MENU.md).

Returns the complete page HTML — return it directly from your controller.

### Shell placeholders it fills

| Placeholder in `page.view.php` | Filled from |
|-------------------------------|-------------|
| `<!--additionalheader-->` | `$header` |
| `<!--additionalcss-->` | `$css` |
| `<!--additionaljs-->` | `$js` |

### Variables the shell view uses

`$title`, `$body`, `$templatedata` (the `template` config array), `$defaultUrl`, `$DACoreMenuLeft` (rendered sidebar), `$navbar`, `$email`, `$aichatdiv`.

### Extension hooks

```php
DotApp::DotApp()->on('Dacore:Page@withMenu.rendering', function ($title, $body, $headerCode, $cssCode, $jsCode, $menuId) {
    // inspect before render
});

DotApp::DotApp()->on('Dacore:Page@withMenu.rendered', function ($viewcode, $title, $body, $headerCode, $cssCode, $jsCode, $menuId) {
    // inspect the finished HTML
});
```

`trigger()` ignores listener return values, so you cannot rewrite the output this way — build the HTML you want before calling `withMenu`.

When `AI.enabled` is true, DACore injects the chat CSS/JS itself; do not add them.

---

## 3. Pagination

Growing lists (logs, users, items, orders) **MUST** use SQL `paginate()` ([06](06-DATABASE.md)) **and** an **interactive AJAX** pager ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) in the **first** version. Empty table today is not a skip. **MUST NOT** dump `->all()` into the table. **MUST NOT** navigate with `<a href="?page=2">` / `location.reload()` — that reloads the admin shell and is not a pager.

**Search:** when **planning** the list, **ASK**. Lookup lists (articles, products, catalog) **MUST** ship interactive AJAX search unless declined (debounce, 3+ chars, SQL + `paginate()`, overlay). [09](09-DOTAPP-JS-AND-BRIDGE.md) §3. **Search DACore first** for search-field chrome.

First paint may be server-rendered page 1. Further pages: `$dotapp().load()` POST with `page` (clamp in PHP) plus current filters (`q`). Overlay the list while in flight.

```php
$page = DB::module('RAW')->q(/* ... */)->paginate(20, $currentPage);

$links = DotApp::call(
    "DACore:Page@paginate!",
    $page['current_page'],
    $page['last_page'],
    null,
    function ($type, $pageNo, $label, $state, $href) {
        if ($type === 'ellipsis') {
            return '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $off = ($state === 'active' || $state === 'disabled') ? ' disabled' : '';
        return '<li class="page-item ' . $state . '"><button type="button" class="page-link js-shop-page" data-page="'
            . (int) $pageNo . '"' . $off . '>' . $label . '</button></li>';
    }
);
```

Returns Bootstrap `<li class="page-item">` items — wrap them yourself:

```html
<ul class="pagination">{{ var: $links }}</ul>
```

Signature: `paginate($actual_page, $number_of_pages, $href = null, $callable = null)`. Pass **`$callable`** so each control is a **button**. Do **not** pass a `?page=` `$href` for an in-app list.

---

## 4. CSS and JS the shell already loads

Loaded by DACore in this order — **never duplicate them**:

1. `/assets/modules/DACore/css/fonts/inter-local.css`
2. `/assets/modules/DACore/css/iconify/iconify-icons.css`
3. `/assets/modules/DACore/dotgrid/dotgrid.css`
4. `/assets/modules/DACore/colors.css`
5. `/assets/modules/DACore/css/core.css`
6. *(your `$css` here)*

JS already present before your `$js`:

```html
<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/modules/DACore/js/dotapp.shell.js"></script>
<script src="/assets/modules/DACore/js/dotapp.modals.js"></script>
<script src="/assets/modules/DACore/vendor/notiflix/notiflix-aio.min.js"></script>
```

`/assets/dotapp/dotapp.js` is therefore **already loaded** on admin pages — your page scripts only need the `dotapp` event guard ([09](09-DOTAPP-JS-AND-BRIDGE.md)).

Your own assets go to `app/modules/Shop/assets/...` and are served from `/assets/modules/Shop/...`.

### Search DACore first (**MUST**)

DACore ships **many admin subpages and libraries** in the base. Agents **MUST NOT** start by writing a new JS/CSS library, `$dotapp().fn` widget, vendor bundle, or page chrome.

**Before any new library or control:**

1. Search `app/modules/DACore/` **read-only** — `assets/js`, `assets/css`, `vendor`, views, controllers — for the same job (select, table, modal, toast, date range, confirm, overlay, pager, icons, cards, …).
2. Search **your** module `app/modules/<YourModule>/assets/` — it may already exist from an earlier task.
3. Check what the shell already loads (this file §4) and named `$dotapp` widgets (including `dotSelect2`, `dotDataTable`, `modal`, `toast`, `daterangepicker`, `twoFactor`, Notiflix, `Page@paginate!`, dotgrid, Remix). The files on disk are the source of truth — that list is not exhaustive.

**If it exists: use it.** Call it from **your** page. Do not fork it. Do not copy DACore files into your module. Do not add a second select / DataTable / modal / toast / date / confirm library.

**If it does not exist:** write it in **your** module and pass it as `$css` / `$js` on `withMenu` ([§5](#5-own-module-cssjs-when-the-shell-is-not-enough-must) below). Never drop the new file into `app/modules/DACore/`.

A new library without that search is a bug.

---

## 5. Own module CSS/JS when the shell is not enough (**MUST**)

Keep the DACore **shell** (`Page@withMenu!`, sidebar, `colors.css`). **Never** edit DACore to add widgets.

1. **MUST search first** ([this file §4](#search-dacore-first-must)): DACore already has many subpages and libraries. Grep DACore (read-only) and your module before writing a new widget.
2. **Prefer** existing DACore UI (cards, `btn btn-*`, dotgrid, Remix icons, Notiflix) when it fits.
2. **MUST** add CSS/JS in **your own module** when DACore has no equivalent, or when forcing the template would change the UX too much (charts, ported toolbars, custom controls people already know).
3. Files live in `app/modules/<YourModule>/assets/` and are passed as `$css` / `$js` to `Page@withMenu!` — never copied into `app/modules/DACore/`.
4. **CSS class prefix MUST** be `{lowercase_modulename}_*` (same idea as tables): `.shop_chart`, `.shop_btn-export`. Do not collide with DACore / Bootstrap class names.
5. **Colors MUST** follow the admin palette already loaded (`colors.css` / `core.css`, existing `btn-*` / `bg-label-*`) so the page still looks like DACore, not a second theme. Reuse shell classes and hues — do not patch DACore’s `colors.css`.
6. Do **not** duplicate shell files (`dotapp.js`, `dotgrid.css`, `core.css`, `colors.css`). Extra libraries (chart JS, etc.) belong in **your** assets.

### Frontend: `$dotapp` is the admin runtime (**MUST**)

DACore administration **runs on `$dotapp`** (`/assets/dotapp/dotapp.js` is already in the shell). New admin pages should use `$dotapp` for DOM, forms, and network.

**jQuery and `$dotapp` may coexist** on the same page (ported widgets, existing plugins). They are not mutually exclusive for UI. The hard split:

| Allowed with jQuery | **Forbidden** — always `$dotapp` |
|---------------------|-----------------------------------|
| DOM widgets, datepickers, charts that still need `$` | **All HTTP to the app:** forms, `load`, bridge, uploads |
| Temporary leftover UI during a port | `$.ajax` / `$.post` / `$.get` / raw `fetch` to DotApp endpoints |

Requests **MUST** go through `$dotapp().form` / `$dotapp().load` / `dotbridge` so CRC, CSRF, keys and signatures stay intact. Mixing jQuery UI with `$dotapp` transport is fine; mixing jQuery **AJAX** with DotApp endpoints is not.

**When porting** jQuery plugins: **ask first**, then **rewrite** them as a new `$dotapp` library (porting = writing a new lib — do not wrap `$.fn`). Playbook: [09](09-DOTAPP-JS-AND-BRIDGE.md) **§4.C** and [EX-15](examples/EX-15-dotapp-js-library.md). If DACore already ships the widget (`dotSelect2`, `dotDataTable`, `modal`, `toast`, `daterangepicker`), **use that** — do not copy DACore JS into your module. Do not silently keep `$.ajax`. If they keep jQuery for UI only, still send every request with `$dotapp`.

Plugin registration (own libraries): see [09](09-DOTAPP-JS-AND-BRIDGE.md) §4 and [EX-15](examples/EX-15-dotapp-js-library.md). Never edit `app/parts/js/` or `app/modules/DACore/`.

```javascript
(function () {
  var runMe = function ($dotapp) {
    $dotapp().fn("shopChart", function (opts) {
      // `this` is the DotApp instance — use this.load / this.form, never $.ajax
      return this;
    });
  };
  if (window.$dotapp) runMe(window.$dotapp);
  else window.addEventListener("dotapp-register", function () { runMe(window.$dotapp); }, { once: true });
})();
```

Duplicate `fn()` names **throw**. Pass the script via `Page@withMenu!` `$js`.

This is how ports work: keep familiar controls, restyle them to DACore colors, keep the DACore menu/shell. Do **not** smash a ported UI into only DACore cards because “the template has no chart”.

```php
return static::call(
    "DACore:Page@withMenu!",
    Translator::trans('Sales'),
    $html,
    [],
    ['/assets/modules/Shop/css/shop_charts.css'],
    ['/assets/modules/Shop/js/shop_charts.js'],
    ''
);
```

```css
/* app/modules/Shop/assets/css/shop_charts.css — served as /assets/modules/Shop/css/shop_charts.css */
.shop_chart { /* reuse DACore / btn-* hues already on the page */ }
```

---

## 6. Layout structure of your `$body`

Your content is placed inside the shell's content container. Start from a card, not from `<html>`:

```html
<div class="card mb-6">
  <h5 class="card-header">{{_ "Items" }}</h5>
  <div class="card-body">
    ...
  </div>
</div>
```

The surrounding shell (`layout-wrapper` → `layout-container` → `layout-menu` + `layout-page` → `content-wrapper` → `container-xxl container-p-y`) is DACore's responsibility.

---

## 7. dotgrid — the grid system

DACore ships its own grid. Use `<dot-grid>` / `<dot-col>`, not raw Bootstrap rows, for forms and card layouts.

```html
<dot-grid dg-marginb20="any" wrapped="any" stretch="any">
  <dot-col any="12" md="6" ldesktop="6">
    <label class="form-label" for="title">{{_ "Title" }}</label>
    <input type="text" name="title" id="title" class="form-control" value="{{ var: $title }}" required />
  </dot-col>
  <dot-col any="12" md="6" ldesktop="6">
    <label class="form-label" for="price">{{_ "Price" }}</label>
    <input type="text" name="price" id="price" class="form-control" value="{{ var: $price }}" />
  </dot-col>
</dot-grid>
```

| Attribute | Meaning |
|-----------|---------|
| `any="12"` | width on all breakpoints (12 = full) |
| `md="6"` | 768 px – 1199.98 px only |
| `ldesktop="6"` | large desktop |
| `wrapped="any"` | allow wrapping |
| `stretch="any"` | equal-height columns |
| `dg-marginb20="any"` | bottom margin |
| `overflowvisible="any"` | needed when a column hosts a dropdown/select2 |

**Important:** `md` does not cascade upwards. To reproduce Bootstrap's `col-md-6` behaviour on wide screens you must give both `md="6"` and `ldesktop="6"`.

---

## 8. Icons

Remix Icon via the iconify CSS already loaded:

```html
<i class="ri ri-time-line"></i>
<i class="icon-base ri ri-user-line icon-18px"></i>
```

In `Menu@register` pass just the classes (`ri ri-store-2-line`) — DACore wraps them.

---

## 9. Forms inside the admin

Use the framework secure-form stack — it is unchanged by DACore ([08](08-FORMS-AND-SECURITY.md), [examples/EX-01](examples/EX-01-secure-form-complete.md)):

```html
<fo-rm action="/dacore/shop-admin/items/save" method="post" id="itemForm">
  <dot-grid wrapped="any">
    <dot-col any="12" md="6" ldesktop="6">
      <input type="text" name="title" class="form-control" required />
    </dot-col>
  </dot-grid>

  {{ formName(saveItem) }}
  <button type="submit" class="btn btn-primary" id="itemSaveBtn">{{_ "Save" }}</button>
</fo-rm>
```

**MUST:** `{{ formName(saveItem) }}` sits **between** `<fo-rm>` and `</fo-rm>` — never after `</fo-rm>`.

PHP handler: `crcCheck()` → `form(['POST'], 'saveItem', $ok, $err)` → `ajaxReply()`. Because `dotapp.js` is already loaded by the shell, your page script only registers the hooks.

**MUST (live UX):** `<fo-rm>` does not reload. After save / toggle / add-on-the-same-page, return `html` (updated table) + `message` in JSON, patch the DOM, toast with **Notiflix** (`Notify.success` / `failure`). Never `location.reload()`. `redirectTo` only when leaving (e.g. edit screen → list). See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 and [EX-06](examples/EX-06-dotapp-js-boot.md).

**MUST NOT** wrap row actions in `<fo-rm>` (up/down, drag-and-drop, toggle, delete, paginate). Those are `type="button"` + encrypted `data-*` + `$dotapp().load()`. One optional add/edit `<fo-rm>` above the table is enough ([08](08-FORMS-AND-SECURITY.md)).

**MUST (paginate growing lists):** logs, users, items **MUST** have an AJAX pager. See this file §3. **MUST NOT** dump `->all()` or reload with `<a href="?page=">`. **Search / list UX:** [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 — **ASK** in the plan; empty state + sticky header **MUST**.

**MUST (block while in flight):** while save / toggle / reorder / paginate is running, cover the form or the **whole list** so the user cannot click or drag again. **Notiflix is DACore-admin only** (this shell). **Preferred on admin pages:** `Notiflix.Block`. **Alternative on admin:** equivalent overlay in **your** module. **Public / front-office pages** in the same project **MUST** use **module preloaders** — Notiflix is not loaded there. Skipping Notiflix does **not** skip preloaders. **MUST** remove the overlay on success **and** error. Overlay a stable parent; patch `TBODY` / inner wrap. UX **MUST** be excellent on desktop **and** mobile (visible spinner, intercepts touch, no hover-only). See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.

For toasts, Notiflix is available (loaded by the shell); modals come from `dotapp.modals.js`.

**MUST (delete confirm):** never `alert()` / `window.confirm()`. Ask in a graphical dialog first (`Notiflix.Confirm` preferred, or `$dotapp().modal`). Then `load()`. See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.

**MUST (operator 2FA / dangerous actions):** DACore operators **MUST** have at least one 2FA method and **MUST NOT** be able to turn it off. Before an action that can seriously damage the system (delete admin, wipe data, grant `dotapp.root`, …), re-prompt with `$dotapp().twoFactor` and **verify the code in PHP** in **your** module **before** persist. The overlay is UX only — removing it **MUST** still fail on the server. Do **not** call `Auth::confirmTwoFactor` (login stage 2 only). See [08](08-FORMS-AND-SECURITY.md), [32](32-DACORE-RIGHTS.md) §6.

**MUST (product copy):** labels, help under buttons, rights descriptions, menu names, toasts. A software company would ship the sentence — never prompt-echo (`This user can hide the AI icon themselves.`). See [05](05-VIEWS-TEMPLATES-ASSETS.md) §8.

**MUST (AI write on this page):** if this screen shows data that an AI tool can change, listen for `DACore.AI.UIEvent`, accept only that tool’s `detail.name`, AJAX-refresh. Ignore other tools. No `location.reload()`. See [34](34-DACORE-AI-TOOLS.md) §5.

---

## 10. Mistakes to avoid

| Wrong | Right |
|-------|-------|
| Rendering a full `<html>` document | Return only your content and let `Page@withMenu` wrap it |
| Re-adding `dotgrid.css` / `core.css` / `dotapp.js` | The shell loads them |
| `$.ajax` for admin saves / lists | `$dotapp().form` / `$dotapp().load` / `dotbridge` (jQuery UI OK) |
| `location.reload()` after toggle/save on the same page | JSON `html` + patch DOM + Notiflix toast ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Logs/users/items with no pager, or `<a href="?page=">` | `paginate()` + AJAX buttons + `$dotapp().load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, this file §3) |
| Catalog/articles list with no search | **ASK**; lookup lists **MUST** AJAX search ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| One `<fo-rm>` per row button / D&D via forms | `type="button"` + encrypted `data-*` + `$dotapp().load()` ([08](08-FORMS-AND-SECURITY.md)) |
| List still clickable during reorder / toggle | Overlay the wrapper (Notiflix preferred **or** module preloaders); remove on success **and** error; desktop **and** mobile |
| Dangerous admin action with no second 2FA prompt | Step-up `$dotapp().twoFactor` + **PHP** verifies the code before persist ([32](32-DACORE-RIGHTS.md) §6) |
| 2FA overlay only; Save still writes without a code | PHP refuses — FE is UX only ([08](08-FORMS-AND-SECURITY.md)) |
| `alert()` / `window.confirm()` on delete | `Notiflix.Confirm` or `$dotapp().modal`, then `load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Prompt-echo copy on a right / button / help | Product language ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| AI tool changed this list and the table stayed stale / full reload | `DACore.AI.UIEvent` + `$dotapp().load()` ([34](34-DACORE-AI-TOOLS.md) §5) |
| UI that disables an operator’s 2FA | Forbidden |
| Refusing custom CSS/JS and forcing every widget into DACore cards | Shell + **your** `$css`/`$js`; classes `{modulename}_*`; DACore colors |
| New select/table/modal/toast/date library without grepping DACore | Search `app/modules/DACore/` (read-only) + your module first (this file §4) |
| Patching DACore `colors.css` / adding files under `DACore/` | Assets in `app/modules/<YourModule>/assets/` — **MUST NOT propose** a DACore patch ([00](00-AGENT-CONTRACT.md) §1) |
| `setViewVar` with `renderLayout()` | Use `setLayoutVar` |
| Guess shared vs module-own menu / ten leaves under a header | **ASK**; grouping and `$menuId`: [31](31-DACORE-MENU.md) |
| Register “Return back” | DACore appends it on a non-empty `$menuId` |
| Bootstrap `col-md-6` alone for simple admin forms | `<dot-col any="12" md="6" ldesktop="6">` (prefer; custom layout OK when porting) |
| Font Awesome / Bootstrap Icons | Remix Icon `ri ri-*` |
| Hardcoding `/dacore` | `Config::module("DACore","prefixUrl")` |
| Assuming a missing layout throws | It returns `""` — check it |
| Trying to rewrite HTML from `.rendered` | Listener return values are discarded |
