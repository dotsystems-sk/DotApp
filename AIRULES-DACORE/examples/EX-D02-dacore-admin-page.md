# EX-D02 — DACore admin page: table, dotgrid form, secure save

Rules: [33](../33-DACORE-PAGES-AND-UI.md), [08](../08-FORMS-AND-SECURITY.md).

Your layout renders **content only** — no `<html>`, no `<head>`, no CSS/JS tags that the shell already loads.

Pass extra files through `Page@withMenu!` `$css` / `$js` (charts, ported toolbars). Prefix classes `{lowercase_modulename}_*` and reuse DACore colors. See [33](../33-DACORE-PAGES-AND-UI.md) §5. Do not skip a widget because “DACore has no chart”.

## Layout: `views/layouts/admin/items.layout.php`

```html
<div class="card mb-6">
  <h5 class="card-header d-flex justify-content-between align-items-center">
    <span>{{_ "Items" }} <span class="badge bg-label-primary">{{ var: $total }}</span></span>
    <a href="{{ var: $baseUrl }}/items/0" class="btn btn-primary btn-sm">
      <i class="ri ri-add-line"></i> {{_ "New item" }}
    </a>
  </h5>

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
```

Notes: `{{ var: }}` output is **not** escaped — sanitise anything user-supplied in the controller. Conditions wrap the expression in parentheses as the framework parser expects a simple expression.

## Edit form with dotgrid + secure form

```html
<div class="card mb-6">
  <h5 class="card-header">{{_ "Edit item" }}</h5>
  <div class="card-body">

    <fo-rm action="{{ var: $baseUrl }}/items/save" method="post" id="itemForm">
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
            Notiflix.Notify.success("Saved");
          }
          window.location = $dotapp("#itemForm").attr("data-list-url") || document.referrer;
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
return static::call(
    'DACore:Page@withMenu!',
    Translator::trans('Edit item'),
    $html,
    [],                                              // extra <head> lines
    ['/assets/modules/Shop/css/admin.css'],
    ['/assets/modules/Shop/js/admin-items.js'],
    ''                                               // full menu
);
```

Pass a `menuid` as the last argument to show only that menu branch plus a "Return back" link.
