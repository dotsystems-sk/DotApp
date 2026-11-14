# EX-D02 — DACore admin page: table, dotgrid form, secure save

Rules: [33](../33-DACORE-PAGES-AND-UI.md), [08](../08-FORMS-AND-SECURITY.md).

Your layout renders **content only** — no `<html>`, no `<head>`, no CSS/JS tags that the shell already loads.

Pass extra files through `Page@withMenu!` `$css` / `$js` (charts, ported toolbars) **only after** grepping DACore (read-only) and this module — reuse an existing widget. Prefix classes `{lowercase_modulename}_*` and reuse DACore colors. See [33](../33-DACORE-PAGES-AND-UI.md) §4 “Search DACore first” and §5. Do not skip a widget because “DACore has no chart” **after** a real search found nothing.

Admin JS is `$dotapp`. jQuery may stay for UI widgets during a port; **requests** stay on `$dotapp().form` / `load` / bridge. Porting = rewrite as `$dotapp().fn` ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §4.C, [EX-15](EX-15-dotapp-js-library.md)) — **ask first**, do not wrap `$.fn`. If DACore already has the widget, use it.

## Layout: `views/layouts/admin/items.layout.php`

```html
<div class="card mb-6" id="listWrap">
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
            <div class="d-flex flex-wrap gap-2 justify-content-end">
              <a class="btn btn-icon btn-sm" href="{{ var: $baseUrl }}/items/{{ var: $item['token'] }}">
                <i class="ri ri-edit-line"></i>
              </a>
              <button type="button" class="btn btn-icon btn-sm shop_item_delete" data-item="{{ enc(Shop.item.id): $item['id'] }}">
                <i class="ri ri-delete-bin-line"></i>
              </button>
            </div>
          </td>
        </tr>
        {{ /foreach }}
      </tbody>
    </table>
  </div>

  <div class="card-footer dacore-list-pager">
    <ul class="pagination mb-0">{{ var: $links }}</ul>
  </div>
  </div>
</div>
```

Notes: the raw database id is never sent to the browser — the edit link uses a URL-safe sealed token (`$item['token']`, [33](../33-DACORE-PAGES-AND-UI.md) §13) and the delete button an encrypted `data-item`. Row buttons sit in a `d-flex gap-2` row. `{{ var: }}` output is **not** escaped — sanitise anything user-supplied in the controller. `$baseUrl` is the HTML page prefix (`/dacore/Shop`). `$apiAuth` is `/api/v1/auth/Shop` — pass it from the controller for list `load()` URLs. The `<fo-rm action>` is a **literal** path; **MUST NOT** write `action="{{ var: $apiAuth }}/items/save"` on the opening tag ([08](../08-FORMS-AND-SECURITY.md)). Conditions wrap the expression in parentheses as the framework parser expects a simple expression. `$links` come from `DACore:Page@paginate!` with a **button** `$callable` — [40](../40-DACORE-LIST-PAGER.md), [EX-D08](EX-D08-list-pager.md). `#listInner` is the fragment AJAX replaces (rows **and** pager).

## Edit form with dotgrid + secure form

```html
<div class="card mb-6" id="itemCard">
  <h5 class="card-header">{{_ "Edit item" }}</h5>

  <fo-rm action="/api/v1/auth/Shop/items/save" method="post" id="itemForm">
    <div class="card-body">
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
    </div>

    <div class="dacore-card-actions">
      <a href="{{ var: $baseUrl }}/items" class="btn btn-outline-secondary">{{_ "Cancel" }}</a>
      <button type="submit" class="btn btn-primary">{{_ "Save" }}</button>
    </div>
  </fo-rm>
</div>
```

`md="6"` plus `ldesktop="6"` is required — `md` does not cascade to wider breakpoints.

The buttons are in their own row, `.dacore-card-actions`, **after** `.card-body` and still inside `<fo-rm>`. That class has padding on all four sides and a gap. `card-footer` / `pb-4` have no CSS rule in the DACore shell ([26](../26-CONTROL-SLICE.md) §2). A real editor splits the fields into numbered sections with a lede ([33](../33-DACORE-PAGES-AND-UI.md) §6b); this sample shows one section.

## Page script: `assets/js/admin-items.js`

`dotapp.js`, Notiflix and `window.dacoreRequestError` are already loaded by the DACore shell. The script has one shape ([26](../26-CONTROL-SLICE.md) §3): helpers, then one handler per control, then the boot block.

```javascript
(function () {
  function runMe($dotapp) {
    // About: request helper — copy shopReply / shopDone / shopFail / shopSend / shopForm
    // from AIRULES/26 §5 (full file: EX-06) here, once per module.

    // Section: Shop — item editor
    // About: save the item. Why: the editor leaves for the list on success, so the reply carries redirectTo.
    shopForm("#itemForm", "#itemCard", function (reply) {
      if (reply.redirectTo) {
        window.location = reply.redirectTo;
      }
    });

    // Section: Shop — items list
    // About: delete one item after a graphical confirm, then redraw rows and pager.
    $dotapp().live("click", ".shop_item_delete", function (el, e) {
      var item = $dotapp(el).attr("data-item");
      Notiflix.Confirm.show("Delete this item?", "This cannot be undone.", "Delete", "Cancel", function () {
        shopSend("/api/v1/auth/Shop/items/delete", { item: item }, "#listWrap", function (reply) {
          $dotapp("#listInner").html(reply.html);
        });
      });
    });

    // About: pager. data-page is ciphertext; live() hands the element first.
    $dotapp().live("click", ".shop-page", function (el, e) {
      var page = $dotapp(el).attr("data-page");
      if (!page) {
        return;
      }
      shopSend("/api/v1/auth/Shop/items/list", { page: page }, "#listWrap", function (reply) {
        $dotapp("#listInner").html(reply.html);
      });
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

`shopForm()` binds `.before()`, `.after()` **and** `.onError()`; `shopSend()` passes both `load()` callbacks. Success, `status` 0 and HTTP failure each end in one toast, and the overlay always comes off. Every URL in this script has a route and an action — [26](../26-CONTROL-SLICE.md) §6–§7. Pager law: [40](../40-DACORE-LIST-PAGER.md), [EX-D08](EX-D08-list-pager.md).

Operators cannot turn 2FA off. A **second** 2FA prompt: **ASK** in the plan (default **no**). If the user named an action (wipe, grant `dotapp.root`, install a package), use the [EX-D10](EX-D10-stepup-2fa-modal.md) modal + `$dotapp().twoFactor` `{ autoSubmit: true }` and verify in your module. See [32](../32-DACORE-RIGHTS.md) §6.

Every **delete** **MUST** open a graphical confirm first (`Notiflix.Confirm` on admin — never `alert()` / `window.confirm()`). Then `load()`. See [09](../09-DOTAPP-JS-AND-BRIDGE.md) §3.

Labels, help, rights descriptions: **MUST** be product copy a software company would ship — never prompt-echo. See [05](../05-VIEWS-TEMPLATES-ASSETS.md) §8.

## Controller side

```php
$id = \Dotsystems\App\Parts\Crypto::decrypt($data['id'] ?? '', 'Shop.item.id');
if ($id === false) {                        // decrypt returns FALSE, not null
    return Reply::fail('This item link has expired. Reload the page and try again.');   // 26 §7
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

`$menuId` chooses the tree. `$currentFile` chooses the **active** leaf when this URL is not that leaf and not a longer path under it. Do not register a menu row per edit URL. Pass a `menuid` as `$menuId` to show only that branch’s **direct children** plus a generated **Return back** leaf. Do not register Return back. **ASK** shared nested vs module-own before a new module; **no answer → shared nested**.
