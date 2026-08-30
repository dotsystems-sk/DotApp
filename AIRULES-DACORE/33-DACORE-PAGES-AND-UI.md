# 33 — DACore Admin Pages and UI Contract

Your module renders **only its content**. DACore supplies the shell (head, sidebar, navbar, footer, AI chat).

**MUST:** pass `$title`, `$body`, `$header`, `$css`, `$js` (and `$menuId`) to `DACore:Page@withMenu!`. On edit/detail URLs that are **not** under the registered list path, **MUST** also pass `$currentFile` (7th) so the sidebar stays on that section — [31](31-DACORE-MENU.md) Active sidebar. The Page controller generates the page. Do **not** `setView` a full `<html>` document. Do **not** put `{{ content }}` in your admin fragment and expect DACore to fill it — that slot is the **framework Renderer** on pages **you** own ([05](05-VIEWS-TEMPLATES-ASSETS.md) §1b), not the admin shell.

---

## 0. DACore is not Bootstrap (MUST — law)

The admin shell is **DotApp + DACore** (`$dotapp`, `Page@withMenu!`, `core.css`, `dotapp.modals.js`, Notiflix). It is **not** a Bootstrap app.

`core.css` reuses some familiar class names (`btn`, `card`, `form-control`, `nav-link`) so the page looks like the DACore theme. That is **theme CSS**, not a license to use Bootstrap as a framework.

**MUST NOT:**

- Load `bootstrap.bundle.js`, `bootstrap.min.js`, or a second Bootstrap CSS
- Assume Bootstrap’s JS Data API is present (`Tab`, `Collapse`, `Tooltip` as Bootstrap plugins)
- Switch “tabs” with `data-bs-toggle="tab"` / `data-bs-target` / `.tab-pane.fade` — the shell does **not** run Bootstrap Tab, so those clicks do nothing
- Use Bootstrap grid (`row` / `col-*`) for new admin layout — **MUST** `<dot-grid>` / `<dot-col>` (this file §7)
- Invent Bootstrap Icons / Bootstrap JS widgets

**MUST:**

- Chrome that looks like tabs = **real GET subpages** + `<a href>` styled in **this module’s** CSS.
- Modals that the shell already wires: `$dotapp().modal()` / documented `data-bs-dismiss` on **DACore modal markup** — that is `dotapp.modals.js`, not `bootstrap.bundle.js`
- New look in `{lowercase_modulename}_*` CSS under **your** module

Every module that uses the DACore admin shell **MUST** follow this law. Canonical compact rule: `AIRULES/cursor/rules/22-dacore-not-bootstrap.mdc`.

---

## 1. The rendering pattern

Two steps: render your content with the framework `Renderer`, then hand the HTML to DACore.

**HTML via Renderer (MUST — law):** the fragment **MUST** be a `.layout.php` via `setLayout` + `setLayoutVar` + `renderLayout()`. **MUST NOT** concatenate tables, grids, empty states, pager chrome, trees, or crumbs in PHP. A PHP HTML string is **only** for a named one-piece exception. Canonical: [00](00-AGENT-CONTRACT.md) §2j, [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c.

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
        ->setLayoutVar('baseUrl', rtrim((string) Config::module('DACore', 'prefixUrl'), '/') . '/Shop')
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
        '',                                             // $menuId: '' = shared nested (default); 'Shop.nav' = module-own only if asked ([31])
        ''                                              // $currentFile: omit / '' on the list URL; registered list URL on edit/detail when the path is not under that leaf ([31])
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
    ?string $menuId = null,
    ?string $currentFile = null,
    ?string $assetModule = null
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
| `$currentFile` | Empty / omitted = real `REQUEST_URI`. Non-empty = highlight **as if** this URL were open (`Menu@generate` `current_file`). **MUST** on edit/detail pages whose path is not a longer path under the registered leaf (e.g. `/dacore/users/4` vs `/dacore/users-list`). Canonical: [31](31-DACORE-MENU.md) Active sidebar |
| `$assetModule` | Optional 8th. Installed module whose `assets/` **mirrors DACore filenames and CSS class names**. DACore rewrites `/assets/modules/DACore/…` chrome URLs (and `$css`/`$js` entries that still point at DACore) to that module. Empty = selected `dacore.admin-skin` with `extra3=assets`, otherwise DACore. `/assets/dotapp/dotapp.js` never moves. Existing callers that omit this argument keep the built-in look. |

**MUST ASK** when starting a **new** DACore module: shared nested sidebar (`0` → `2` → `1`, `$menuId` `''`) vs module-own. **No answer → shared nested.** Module-own only if the user explicitly chose it. Canonical: [31](31-DACORE-MENU.md).

In the same planning round, **MUST ASK one grouped module-identity question**: public name/purpose; installer preview as text-only, compact logo or wide banner; existing local asset + alt text; optional landing/header placement and colours. Do not confuse the sidebar Remix icon with the module logo. The installer mechanism is `about.php` + local `about-assets/`, not a DACore page/core edit ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8b, [35](35-DACORE-INSTALL.md) §3b).

**MUST plan the UI, not only routes.** A new admin module (or first major workspace / rewrite) **MUST** write desktop and mobile regions, hierarchy, empty/loading/error states, toolbar, padding vs the parent, **and** every page / tab / control **in the plan** before scaffolding. Canonical: [45](45-MODULE-PLANNING.md), [00](00-AGENT-CONTRACT.md) §2k.

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

`trigger()` ignores listener return values, so you cannot rewrite the output this way — build the HTML you want before calling `withMenu`, **or** register an Extender on the chrome APIs below.

### Extender (skins that need different HTML)

Events cannot replace chrome. These methods **opt in** to `Extender` so a pack can return completely different HTML, or `Extender::original()` to keep DACore’s renderer. Register in `Listeners::register()` ([12](12-SERVICES.md) §10, [EX-17](examples/EX-17-extender.md)). **MUST NOT** pass `$request`, tokens, or bodies.

| Target | Arguments (safe) |
|--------|------------------|
| `DACore\Controllers\Page::withMenu` | `$title, $body, $header, $css, $js, $menuId, $currentFile, $assetModule` |
| `DACore\Controllers\Page::navbar` | `$vars` (already-escaped navbar scalars) |
| `DACore\Controllers\Page::paginate` | `$actual_page, $number_of_pages, $href, $callable` |
| `DACore\Controllers\Menu::generate` | `$items, $options` |
| `DACore\Controllers\Login::renderLoginForm` | *(none)* |
| `DACore\Controllers\Setup::pageHtml` | *(none)* |
| `DACore\Controllers\ErrorPages::error403` / `error404` / `error500` | *(none)* |

When `AI.enabled` is true, DACore injects the chat CSS/JS itself; do not add them.

---

## 3. Pagination

**Law:** [40-DACORE-LIST-PAGER.md](40-DACORE-LIST-PAGER.md). Copy-paste: [EX-D08](examples/EX-D08-list-pager.md).

Growing lists **MUST** use SQL paging (`COUNT(*)` + `LIMIT`/`OFFSET`) **and** the [40](40-DACORE-LIST-PAGER.md) AJAX pager in the **first** version. **MUST NOT** dump `->all()`. **MUST NOT** `<a href="?page=2">` / `location.reload()` / `history.replaceState` of query params.

**MUST** classes: `card-footer dacore-list-pager` (**no** `--split`), `dacore-list-pager-summary`, `dacore-list-pager-nav`, `ul.pagination.mb-0`, `button.page-link.{module}-page` with encrypted `data-page`. Load DACore `users.css` on `withMenu` `$css`. **MUST NOT** `data-dacore-page` in your module.

`DACore:Page@paginate!` `$callable` as **buttons**, `$href = null`. `$dotapp().live("click", …, function (el, e)` — first arg is the **element**.

**Search:** when planning a growing result list, **ASK**. Catalog/result lists use interactive AJAX search unless declined. A bounded one-value picker is not a result list: use native `<select>` or the existing `dotSelect2`; choices must be visible on open without remembering an exact name. Only genuinely large remote choices use AJAX `dotSelect2` with initial results and server paging/search. [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.

Do **not** copy the old `js-shop-page` + plain `data-page` + `e.currentTarget` snippet from earlier AIRULES — it is **wrong**.

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

1. Search `app/modules/DACore/` **read-only** — `assets/js`, `assets/css`, `vendor`, views, controllers — for the same job (select, table, modal, toast, **Notiflix.Notify / Confirm / Block**, date range, confirm, overlay, pager, icons, cards, …). **Toasts and alerts:** use the shell’s notify. Do **not** invent a second toast library.
2. Search **your** module `app/modules/<YourModule>/assets/` — it may already exist from an earlier task.
3. Check what the shell already loads (this file §4) and named `$dotapp` widgets (including `dotSelect2`, `dotDataTable`, `modal`, `toast`, `daterangepicker`, `twoFactor`, Notiflix, `Page@paginate!`, dotgrid, Remix). The files on disk are the source of truth — that list is not exhaustive.

**MUST NOT** search `app/modules/<Sibling>/` for cards, CSS, or “how they laid out a page.” That is a [00](00-AGENT-CONTRACT.md) §1b read-scope violation. A sibling is readable **only** when the user named it as the module this work extends / listens to. Examples of DotApp admin pages live in `AIRULES/examples/`, not in a live neighbour.

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

### Button padding vs the parent (MUST — law)

Whenever you **add or move a button** (Save, Back, Copy, empty-state action, modal footer, pager cluster), **MUST** check padding vs the parent on **all sides**: left, right, **top**, and **bottom**. A working POST with a flush control is **not** done.

The usual hole: a Save (or a row of actions) is the **last content** in a card, `card-body`, modal, drawer, or page block. The parent then has no leftover space **below**, so the control sits on the bottom edge. That is a **bug**. When buttons are the last piece of a block, **almost always** add bottom padding — `card-footer` with `pb-3`/`pb-4`, or CSS `padding-bottom` on that parent. `pt-0` on a footer **MUST** still keep that bottom padding.

**MUST NOT:** drop Save at the end of `card-body` with no footer / `pb-*`; claim done from the PHP handler without reading the HTML/CSS chrome.

Canonical: [00](00-AGENT-CONTRACT.md) §2f, [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c. Compact: `AIRULES/cursor/rules/ux-ui-layout.mdc`.

### 6b. Admin page composition (MUST — law)

A DACore admin **form / settings / editor** that is **one long card of inputs** is unfinished — even if every field posts. Operators need **readable density**: numbered Identity / Content / Advanced sections, a one-sentence lede, and a tinted “Why this matters” note. That is product law for every module.

**MUST** on every new or rewritten admin workspace:

| Piece | What the operator sees |
|-------|------------------------|
| Page header | Title + **one purpose sentence** (“Create and manage pages for this website.”) |
| Numbered section | Visible number when order matters, **heading**, **one-sentence lede** under it |
| Why-this-matters | Calm tinted panel (admin-palette green/teal) — short **product** copy, not prompt-echo |
| Related fields only | One job per card. Identity is not SEO. Assignments are not the body. |
| GET workspaces | More than ~two jobs on one object → real GET leaves + this module’s tab CSS ([§0](#0-dacore-is-not-bootstrap-must--law)) |
| Advanced | Stems, JSON, internal codes, rare dates — not in the first card |

**MUST** implement the chrome in **this** module: `{lowercase_modulename}_section_*` / `{lowercase_modulename}_guidance_*` CSS and Renderer layouts. Match the loaded DACore palette. Button padding still [§6](#6-layout-structure-of-your-body).

**MUST NOT:**

- Dump every field into one `card-body`
- Skip the lede / Why panel “to save space”
- Copy another module's admin chrome layouts or prefixed CSS into the target module ([00](00-AGENT-CONTRACT.md) §1b)
- Use `data-bs-toggle="tab"` for the workspace split ([§0](#0-dacore-is-not-bootstrap-must--law))
- Write Why copy that restates the field label (“This is the title field”)

**Plan first** on a new module / first surface: [45](45-MODULE-PLANNING.md) §4 must name every section, its lede, and whether it has a Why panel. Compact: `AIRULES/cursor/rules/23-admin-page-sections.mdc`.

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
<fo-rm action="/api/v1/auth/Shop/items/save" method="post" id="itemForm">
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

PHP handler: prefix `LoginAndCRC` already burned the token — only `form(['POST'], 'saveItem', $ok, $err)` → `ajaxReply()`. Because `dotapp.js` is already loaded by the shell, your page script only registers the hooks.

**MUST (live UX):** `<fo-rm>` does not reload. After save / toggle / add-on-the-same-page, return `html` (updated table) + `message` in JSON, patch the DOM, **toast** with the shell notify (**MUST** grep DACore first — `Notiflix.Notify.success` / `failure` or `$dotapp().toast()`). Never silent `.after()`. Never `location.reload()`. `redirectTo` only when leaving (e.g. edit screen → list). Law: [00](00-AGENT-CONTRACT.md) §2d. See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 and [EX-06](examples/EX-06-dotapp-js-boot.md).

**MUST NOT** wrap row actions in `<fo-rm>` (up/down, drag-and-drop, toggle, delete, paginate). Those are `type="button"` + encrypted `data-*` + `$dotapp().load()`. One optional add/edit `<fo-rm>` above the table is enough ([08](08-FORMS-AND-SECURITY.md)).

**MUST (admin persist = original request):** `$request->data(true)` (unwrap nested `data`). SQL protection is **named / `?` bindings** on insert/update — **MUST NOT** persist `$request->data()` (`protect()`). A maps URL `?q=Sabinov` stored from the protected copy becomes `?&#61;Sabinov`. Canonical: [19](19-VALIDATION-AND-INPUT.md), [06](06-DATABASE.md).

**MUST (admin `load` / `form` PHP):** unwrap the nested `data` bag. Product outcomes **MUST** be HTTP **200** + `status` 0|1 + `message`. HTTP 400/500 makes `fetch` onError and the toast is **Request failed**. When one action has this hole, grep **this module** and fix every sibling in the same chunk ([00](00-AGENT-CONTRACT.md) §2q, [09](09-DOTAPP-JS-AND-BRIDGE.md) §3).

**MUST (paginate growing lists):** logs, users, items **MUST** use the [40](40-DACORE-LIST-PAGER.md) pager. **MUST NOT** dump `->all()` or reload with `<a href="?page=">`. **Search / list UX:** [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 — **ASK** in the plan; empty state + sticky header **MUST**.

**MUST (block while in flight):** while save / toggle / reorder / paginate is running, cover the form or the **whole list** so the user cannot click or drag again. **Notiflix is DACore-admin only** (this shell). **Preferred on admin pages:** `Notiflix.Block`. **Alternative on admin:** equivalent overlay in **your** module. **Public / front-office pages** in the same project **MUST** use **module preloaders** — Notiflix is not loaded there. Skipping Notiflix does **not** skip preloaders. **MUST** remove the overlay on success **and** error. Overlay a stable parent; patch `TBODY` / inner wrap. UX **MUST** be excellent on desktop **and** mobile (visible spinner, intercepts touch, no hover-only). See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.

For toasts, Notiflix is available (loaded by the shell); modals come from `dotapp.modals.js`.

**MUST (delete confirm):** never `alert()` / `window.confirm()`. Ask in a graphical dialog first (`Notiflix.Confirm` preferred, or `$dotapp().modal`). Then `load()`. See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.

**MUST (operator 2FA):** DACore operators **MUST** have at least one 2FA method and **MUST NOT** be able to turn it off. A **second** 2FA prompt: **ASK** in the plan (default **no**). Ordinary settings Save does **not** get a modal. When the user named an action (install package, wipe, grant `dotapp.root`, …), use the DACore installer modal + `$dotapp().twoFactor` `{ autoSubmit: true }` (paste immediately POSTs) and **verify the code in PHP** in **your** module **before** persist. The overlay is UX only. Do **not** call `Auth::confirmTwoFactor` (login stage 2 only). See [08](08-FORMS-AND-SECURITY.md), [32](32-DACORE-RIGHTS.md) §6, [EX-D10](examples/EX-D10-stepup-2fa-modal.md).

**MUST (product copy):** labels, help under buttons, rights descriptions, menu names, toasts. A software company would ship the sentence — never prompt-echo (`This user can hide the AI icon themselves.`). See [05](05-VIEWS-TEMPLATES-ASSETS.md) §8.

**MUST (layout / buttons):** this file §6 **Button padding vs the parent**. Padding on all sides — especially **below** when the buttons are the last content in the block. Center or match sibling cards. A flush Save is a **bug**.

**MUST (AI write on this page):** if this screen shows data that an AI tool can change, listen for `DACore.AI.UIEvent`, accept only that tool’s `detail.name`, AJAX-refresh. Ignore other tools. No `location.reload()`. See [34](34-DACORE-AI-TOOLS.md) §5.

---

## 10. Mistakes to avoid

| Wrong | Right |
|-------|-------|
| Rendering a full `<html>` document | Return only your content and let `Page@withMenu` wrap it |
| Re-adding `dotgrid.css` / `core.css` / `dotapp.js` | The shell loads them |
| `$.ajax` for admin saves / lists | `$dotapp().form` / `$dotapp().load` / `dotbridge` (jQuery UI OK) |
| `location.reload()` after toggle/save on the same page | JSON `html` + patch DOM + Notiflix toast ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Logs/users/items with no pager, or `<a href="?page=">` | [40](40-DACORE-LIST-PAGER.md) pager |
| Catalog/articles list with no search | **ASK**; lookup lists **MUST** AJAX search ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Module/language/status/other bounded picker as exact-name text input or empty datalist | Native `<select>` or existing `dotSelect2`; opening shows choices. Large remote set: AJAX `dotSelect2` with initial results + server paging/search |
| One `<fo-rm>` per row button / D&D via forms | `type="button"` + encrypted `data-*` + `$dotapp().load()` ([08](08-FORMS-AND-SECURITY.md)) |
| `$request->data()['id']` after `load()`; product fail HTTP 400 | Unwrap nested `data`; HTTP **200** + `status` 0|1; hunt every sibling in this module ([00](00-AGENT-CONTRACT.md) §2q) |
| List still clickable during reorder / toggle | Overlay the wrapper (Notiflix preferred **or** module preloaders); remove on success **and** error; desktop **and** mobile |
| Step-up 2FA on every settings Save without asking | **ASK** in the plan; default **no** ([32](32-DACORE-RIGHTS.md) §6) |
| Step-up as a 6-digit field on the card / custom OTP / no paste auto-submit | DACore installer modal + `$dotapp().twoFactor` `{ autoSubmit: true }` ([EX-D10](examples/EX-D10-stepup-2fa-modal.md)) |
| Named step-up action with no modal / PHP skip | Step-up `$dotapp().twoFactor` + **PHP** verifies before persist ([32](32-DACORE-RIGHTS.md) §6) |
| 2FA overlay only; Save still writes without a code | PHP refuses — FE is UX only ([08](08-FORMS-AND-SECURITY.md)) |
| `alert()` / `window.confirm()` on delete | `Notiflix.Confirm` or `$dotapp().modal`, then `load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Prompt-echo copy on a right / button / help | Product language ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| Save / last-in-block buttons flush to the card bottom / `pt-0` with no `pb-*` | Padding on all sides; **bottom padding almost always** when buttons are the last piece of the block ([00](00-AGENT-CONTRACT.md) §2f, this file §6) |
| AI tool changed this list and the table stayed stale / full reload | `DACore.AI.UIEvent` + `$dotapp().load()` ([34](34-DACORE-AI-TOOLS.md) §5) |
| UI that disables an operator’s 2FA | Forbidden |
| Refusing custom CSS/JS and forcing every widget into DACore cards | Shell + **your** `$css`/`$js`; classes `{modulename}_*`; DACore colors |
| New select/table/modal/toast/date library without grepping DACore | Search `app/modules/DACore/` (read-only) + your module first (this file §4) |
| Grep `app/modules/Shop` / another sibling for cards or CSS | Forbidden — DACore + this module + `AIRULES/examples/` ([00](00-AGENT-CONTRACT.md) §1b) |
| Patching DACore `colors.css` / adding files under `DACore/` | Assets in `app/modules/<YourModule>/assets/` — **MUST NOT propose** a DACore patch ([00](00-AGENT-CONTRACT.md) §1) |
| `setViewVar` with `renderLayout()` | Use `setLayoutVar` |
| `setLayoutVar('rows', [['key' => 'time', …]])` then empty `foreach` | Sandbox dropped the array (`is_callable('time')`). Prefix keys or pass escaped HTML ([05](05-VIEWS-TEMPLATES-ASSETS.md) §5) |
| Guess shared vs module-own menu / ten leaves under a header | **ASK**; no answer → shared nested `0` → `2` → `1`; [31](31-DACORE-MENU.md) |
| Edit/detail URL leaves the sidebar with no active item | `$currentFile` = registered list URL ([31](31-DACORE-MENU.md) Active sidebar) |
| Register “Return back” | DACore appends it on a non-empty `$menuId` |
| `data-bs-toggle="tab"` / `.tab-pane.fade` as in-page tabs | Real GET subpages + `<a href>` + **your** CSS ([33](33-DACORE-PAGES-AND-UI.md) §0) |
| Live-click `/admin/…` to “verify” after every chunk | Finish-gate + source UX + click list — browser only if the user asked ([00](00-AGENT-CONTRACT.md) §2r, this file §12) |
| Raw `Crypto::encrypt` / `{{ enc }}` in a path `{token}` | Apache **404** on `%2F`. Seal to `[A-Za-z0-9_-]+` in **this** module ([11](11-AUTH-AND-CRYPTO.md) §8, this file §13) |
| Bootstrap `col-md-6` alone for simple admin forms | `<dot-col any="12" md="6" ldesktop="6">` (prefer; custom layout OK when porting) |
| Font Awesome / Bootstrap Icons | Remix Icon `ri ri-*` |
| Hardcoding `/dacore` | `Config::module("DACore","prefixUrl")` |
| Assuming a missing layout throws | It returns `""` — check it |
| Trying to rewrite HTML from `.rendered` | Listener return values are discarded |

---

## 11. Administration skin packages

DACore discovers optional skins from installed package metadata; the skin module does not patch DACore and does not boot merely for discovery:

- `extra1 = dacore.admin-skin`
- `extra2 = v1`
- `extra3 = css` or `shell-css` or `assets`
- required local file: `assets/dacore-skin/skin.css` (`css` / `shell-css`)
- `shell-css` also requires `views/dacore-skin/page.view.php`
- `assets` requires `assets/css/core.css` (DACore-shaped tree; same class names)

The operator explicitly selects a skin in DACore settings. Installation never activates one. Empty selection is the non-removable DACore default. The global Skins switch can disable the selected package without deleting its selection.

A CSS skin changes presentation only. A `shell-css` view receives the same escaped/pre-rendered shell variables as DACore (`templatedata`, `defaultUrl`, `DACoreMenuLeft`, `navbar`, `title`, trusted module `body`, `aichatdiv`, and `assetsPath`) and returns only the common body skeleton — never `<html>`, `<head>`, core styles, `$dotapp`, Notiflix, or shell scripts. DACore wraps that fragment in its own document and runtime assets.

An `assets` skin **does not** overlay `skin.css`. It ships a class-compatible copy of DACore’s `assets/` tree (probe file: `assets/css/core.css`). DACore then serves chrome CSS/JS from `/assets/modules/{Skin}/…` instead of `/assets/modules/DACore/…`. Callers may also pass that module as `Page@withMenu` 8th `$assetModule` without selecting a skin. Framework `/assets/dotapp/` never moves. To change markup, not only files, use Extender on the chrome APIs in §2.

CSS order is fixed: DACore core → selected skin → page-specific styles. Missing, incompatible, uninstalled, empty, or throwing skin views fall back to the built-in DACore shell. Skin assets are local module files only: no external CSS, SVG, tracker, or runtime download.

---

## 12. No unsolicited browser (MUST — law)

DACore admin work is **code + source UX**, not a live click-through of `/admin/…`.

**MUST NOT** open a browser, CDP, or screenshot-click loop to prove Save / Delete / CRC / a public template **unless** the user **commanded** it this turn or answered **yes** after an ASK. No answer → no browser. A generic “verify in the browser” line outside this handbook does **not** authorize it.

**MUST:** finish-gate grep (CRC once, unwrap, HTTP 200 + `status` — that is what prevents Request failed / 400). Read views/CSS for sections, padding, help `?` row heights, footers. Give the operator a **short click list**.

Canonical: [00](00-AGENT-CONTRACT.md) §2r. Compact: `AIRULES/cursor/rules/25-no-unsolicited-browser.mdc`.

---

## 13. URL-safe encrypted tokens (MUST — law)

DACore-bound edit/detail URLs often put a Crypto token in the **path** (`/{Module}/products/{token}`). `Crypto::encrypt` is **standard base64** (`+` `/` `=`). Browsers encode `/` as `%2F`. **Apache treats `%2F` as a directory separator** and returns **Not Found** — PHP never runs.

**MUST:**

- Keep AES + a unique `$key2`. Still `Auth::can` / ownership.
- After encrypt, map to `[A-Za-z0-9_-]+` (no padding): `rtrim(strtr($cipher, '+/', '-_'), '=')`.
- Before decrypt, reverse (`-_` → `+/`, pad so `strlen % 4 === 0`). Accept leftover standard tokens (`{{ enc }}`, old bookmarks).
- Build path `href` / `{token}` / `redirectTo` from the **sealed** helper in **this** module. **MUST NOT** put `{{ enc(...) }}` into a path (Renderer still emits `+/`).
- Implement sealing and opening in the current module (for example, a `Pager` or `Libraries/UrlToken.php` helper), and keep the exact `$key2` context.

**MUST NOT:**

- Put raw `Crypto::encrypt` into `/admin/…/{token}`
- Patch `app/modules/DACore/` or `app/parts/` for this (DACore updates wipe local DACore files)
- Drop encryption or send a plain id

Hidden fields and `data-*` may keep `{{ enc }}` when PHP opens both shapes. Prefer sealing every FE token so one helper serves path, query, and POST.

Canonical: [11](11-AUTH-AND-CRYPTO.md) §8, [00](00-AGENT-CONTRACT.md) §2c IDs, [08](08-FORMS-AND-SECURITY.md) Identifiers. Compact: `AIRULES/cursor/rules/26-url-safe-tokens.mdc`.
