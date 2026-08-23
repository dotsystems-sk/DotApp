# EX-D02 — DACore admin page: table, dotgrid form, secure save

Rules: [33](../33-DACORE-PAGES-AND-UI.md), [08](../08-FORMS-AND-SECURITY.md).

Your layout renders **content only** — no `<html>`, no `<head>`, no CSS/JS tags that the shell already loads.

Pass extra files through `Page@withMenu!` `$css` / `$js` (charts, ported toolbars) **only after** grepping DACore (read-only) and this module — reuse an existing widget. Prefix classes `{lowercase_modulename}_*` and reuse DACore colors. See [33](../33-DACORE-PAGES-AND-UI.md) §4 “Search DACore first” and §5. Do not skip a widget because “DACore has no chart” **after** a real search found nothing.

Admin JS is `$dotapp`. jQuery may stay for UI widgets during a port; **requests** stay on `$dotapp().form` / `load` / bridge. Porting = rewrite as `$dotapp().fn` ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §4.C, [EX-15](EX-15-dotapp-js-library.md)) — **ask first**, do not wrap `$.fn`. If DACore already has the widget, use it.

## Layout: `views/layouts/admin/items.layout.php`

```html
<div class="card mb-6" id="listWrap" data-list-url="{{ var: $apiAuth }}/items/list">
  <h5 class="card-header d-flex justify-content-between align-items-center">
    <span>{{_ "Items" }} <span class="badge bg-label-primary">{{ var: $total }}</span></span>
    <a href="{{ var: $baseUrl }}/items/0" class="btn btn-primary btn-sm">
      <i class="ri ri-add-line"></i> {{_ "New item" }}
    </a>
  </h5>

  <div id="listInner">
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>{{_ "ID" }}</th>
          <th>{{_ "Title" }}</th>
          <th>{{_ "SKU" }}</th>
          <th>{{_ "Price" }}</th>
          <th>{{_ "State" }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {{ foreach $items as $item }}
        <tr>
          <td>{{ var: $item['id'] }}</td>
          <td><strong>{{ var: $item['title'] }}</strong></td>
          <td>{{ var: $item['sku'] }}</td>
          <td>{{ var: $item['price'] }}</td>
          <td>
            {{ if ($item['active'] == 1) }}
              <span class="badge rounded-pill bg-label-success">{{_ "Active" }}</span>
            {{ else }}
              <span class="badge rounded-pill bg-label-danger">{{_ "Inactive" }}</span>
            {{ /if }}
          </td>
          <td>
            <a class="btn btn-icon btn-sm" href="{{ var: $baseUrl }}/items/{{ var: $item['id'] }}">
              <i class="ri ri-edit-line"></i>
            </a>
          </td>
        </tr>
        {{ /foreach }}
      </tbody>
    </table>
  </div>

  <div class="card-footer">
    <ul class="pagination mb-0">{{ var: $links }}</ul>
  </div>
  </div>
</div>
```

Notes: `{{ var: }}` output is **not** escaped — sanitise anything user-supplied in the controller. `$baseUrl` is the HTML page prefix (`/dacore/Shop`). `$apiAuth` is `/api/v1/auth/Shop` — **MUST** pass it from the controller for `fo-rm` / list `load()`. Conditions wrap the expression in parentheses as the framework parser expects a simple expression. `$links` come from `DACore:Page@paginate!` with a **button** `$callable` — [40](../40-DACORE-LIST-PAGER.md), [EX-D08](EX-D08-list-pager.md). `#listInner` is the fragment AJAX replaces (rows **and** pager).

## Edit form with dotgrid + secure form

```html
<div class="card mb-6">
  <h5 class="card-header">{{_ "Edit item" }}</h5>
  <div class="card-body">

    <fo-rm action="{{ var: $apiAuth }}/items/save" method="post" id="itemForm">
      <input type="hidden" name="id" value="{{ enc(Shop.item.id): $itemId }}" />

      <dot-grid dg-marginb20="any" wrapped="any" stretch="any" any="12" class="mb-4">
        <dot-col any="12" md="6" ldesktop="6">
          <label class="form-label" for="title">{{_ "Title" }}</label>
          <input type="text" name="title" id="title" class="form-control"
                 value="{{ var: $title }}" required />
        </dot-col>

        <dot-col any="12" md="6" ldesktop="6">
          <label class="form-label" for="sku">{{_ "SKU" }}</label>
          <input type="text" name="sku" id="sku" class="form-control" value="{{ var: $sku }}" />
        </dot-col>

        <dot-col any="12" md="6" ldesktop="6">
          <label class="form-label" for="price">{{_ "Price" }}</label>
          <input type="text" name="price" id="price" class="form-control" value="{{ var: $price }}" />
        </dot-col>

        <dot-col any="12" md="6" ldesktop="6" overflowvisible="any">
          <label class="form-label" for="state">{{_ "State" }}</label>
          <select name="active" id="state" class="form-select">
            <option value="1">{{_ "Active" }}</option>
            <option value="0">{{_ "Inactive" }}</option>
          </select>
        </dot-col>
      </dot-grid>

      {{ formName(saveItem) }}

      <button type="submit" class="btn btn-primary" id="itemSaveBtn">{{_ "Save" }}</button>
      <a href="{{ var: $baseUrl }}/items" class="btn btn-outline-secondary">{{_ "Cancel" }}</a>
    </fo-rm>

  </div>
</div>
```

`md="6"` plus `ldesktop="6"` is required — `md` does not cascade to wider breakpoints.

## Page script: `assets/js/admin-items.js`

`dotapp.js` is already loaded by the DACore shell, so only guard for the event:

```javascript
(function () {
  var runMe = function ($dotapp) {
    $dotapp()
      .form("#itemForm")
      .before(function (data, form) {
        if ($dotapp(form).attr("blocked") == 1) {
          return $dotapp().halt();
        }
        $dotapp(form).attr("blocked", "1");
        $dotapp("#itemSaveBtn").attr("loading", "true").attr("loader", "dots");
      })
      .after(function (data, response, form) {
        var reply = $dotapp().parseReply(response);

        if (reply && reply.status == 1) {
          if (window.Notiflix && Notiflix.Notify) {
            Notiflix.Notify.success(reply.message || "Saved");
          }
          if (reply.html) {
            $dotapp("#listWrap").html(reply.html);
          } else if ($dotapp("#itemForm").attr("data-list-url")) {
            // leaving the edit screen → list is OK
            window.location = $dotapp("#itemForm").attr("data-list-url") || document.referrer;
          }
        } else if (reply && reply.message) {
          if (window.Notiflix && Notiflix.Notify) {
            Notiflix.Notify.failure(reply.message);
          }
        }

        $dotapp(form).attr("blocked", "0");
        $dotapp("#itemSaveBtn").removeAttr("loading").removeAttr("loader");
      });
  };

  if (window.$dotapp) runMe(window.$dotapp);
  else window.addEventListener("dotapp", function () { runMe(window.$dotapp); }, { once: true });
})();
```

On a **list** page (add rule, toggle, delete, reorder, **page**): overlay `#listWrap` **before** `load()`, patch `#listInner` (rows **and** pager), then remove the overlay on success **and** error. Pager law: [40](../40-DACORE-LIST-PAGER.md), [EX-D08](EX-D08-list-pager.md). `$dotapp().live` = `function (el, e)`.

```javascript
$dotapp().live("click", ".shop-page", function (el, e) {
  if (e && typeof e.preventDefault === "function") e.preventDefault();
  if (!el || el.disabled) return;
  var page = el.getAttribute("data-page") || "";
  if (!page) return;
  Notiflix.Block.standard("#listWrap", "Loading…");
  $dotapp().load($dotapp("#listWrap").attr("data-list-url"), "POST", { page: page },
    function (raw) {
      var reply = $dotapp().parseReply(raw);
      if (reply && reply.status == 1 && reply.html) $dotapp("#listInner").html(reply.html);
      Notiflix.Block.remove("#listWrap");
    },
    function () { Notiflix.Block.remove("#listWrap"); }
  );
});
```

If the action can **seriously damage** the system (delete an admin, wipe data, grant `dotapp.root`), **MUST** step-up 2FA first (`$dotapp().twoFactor` + verify in your module). Operators cannot turn 2FA off. See [32](../32-DACORE-RIGHTS.md) §6.

Every **delete** **MUST** open a graphical confirm first (`Notiflix.Confirm` on admin — never `alert()` / `window.confirm()`). Then `load()`. See [09](../09-DOTAPP-JS-AND-BRIDGE.md) §3.

Labels, help, rights descriptions: **MUST** be product copy a software company would ship — never prompt-echo. See [05](../05-VIEWS-TEMPLATES-ASSETS.md) §8.

## Controller side

```php
$id = \Dotsystems\App\Parts\Crypto::decrypt($data['id'] ?? '', 'Shop.item.id');
if ($id === false) {                        // decrypt returns FALSE, not null
    return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Invalid id']];
}
```

Encrypted ids in the markup are bound to the session — do not persist them.

## Wiring the page

```php
$prefix = rtrim((string) Config::module('DACore', 'prefixUrl'), '/');
$listUrl = $prefix . '/Shop/items';   // registered leaf; /Shop/items/4 already matches — 7th can stay ''
// If the list is /Shop/users-list and this page is /Shop/users/4, 7th MUST be $prefix . '/Shop/users-list'

return static::call(
    'DACore:Page@withMenu!',
    Translator::trans('Edit item'),
    $html,
    [],                                              // extra <head> lines
    ['/assets/modules/Shop/css/admin.css'],
    ['/assets/modules/Shop/js/admin-items.js'],
    '',                                              // $menuId — full shared, or a branch id ([31](../31-DACORE-MENU.md); ASK)
    $listUrl                                         // $currentFile — keep that leaf active ([31] Active sidebar)
);
```

`$menuId` chooses the tree. `$currentFile` chooses the **active** leaf when this URL is not that leaf and not a longer path under it. Do not register a menu row per edit URL. Pass a `menuid` as `$menuId` to show only that branch’s **direct children** plus a generated **Return back** leaf. Do not register Return back. **ASK** shared vs module-own before a new module.
