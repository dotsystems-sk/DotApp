# EX-D08 — DACore list pager (copy-paste)

Open with [40-DACORE-LIST-PAGER.md](../40-DACORE-LIST-PAGER.md). This is the **legal** admin list: HTML classes, encrypted page buttons, `$dotapp().live("click", …, function (el, e)`, COUNT + LIMIT, POST `/api/v1/auth/{Module}/…`.

Replace `Shop` / `shop` / `shop_items` / `Shop.items.page` with **your** module.

---

## 1. Routes (`module.init.php`)

```php
$admin = rtrim((string) Config::module('DACore', 'prefixUrl'), '/') . '/Shop';
$authApi = '/api/v1/auth/Shop';
Router::before([$admin, $admin . '/*'], '#Shop:Gate@login!');
Router::before(['POST'], [$authApi, $authApi . '/*'], '#DACore:AuthTest@LoginAndCRC!');
if (Auth::isLogged() === true) {
    Router::get($admin . '/items', 'Shop:Admin@items!')
        ->before(function ($request) {
            return DotApp::call('#Shop:Rights@check!', $request, ['dotapp.root', 'Shop.items.view']);
        });
    Router::post($authApi . '/items/list', 'Shop:Admin@itemsList!')
        ->before(function ($request) {
            return DotApp::call('#Shop:Rights@check!', $request, ['dotapp.root', 'Shop.items.view']);
        });
}
```

`itemsList` **MUST NOT** call `crcCheck()`.

---

## 2. COUNT + page (library or controller)

```php
$countRows = DB::module('RAW')->q(function ($qb) use ($bind) {
    $qb->raw('SELECT COUNT(*) AS `total` FROM `shop_items`' . $whereSql, $bind);
})->all();
$total = (int) ($countRows[0]['total'] ?? 0);
$last = $total > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
if ($page > $last) {
    $page = $last;
}
```

Then `LIMIT`/`OFFSET` for `$rows`. **MUST NOT** use `QueryObject::paginate()['total']` for `last_page`. **MUST NOT** set `total` from `count($rows)`.

Decrypt pager clicks:

```php
$raw = $body['page'] ?? 1;
if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
    $page = max(1, (int) $raw); // search reset only
} else {
    $plain = Crypto::decrypt((string) $raw, 'Shop.items.page');
    if ($plain === false || !ctype_digit((string) $plain)) {
        return ['status' => 0, 'message' => Translator::trans('Bad request')];
    }
    $page = max(1, (int) $plain);
}
```

---

## 3. Pager HTML

On `Page@withMenu!` `$css` include `/assets/modules/DACore/css/pages/dotapp-ui/users.css`.

```php
$links = (string) DotApp::call(
    'DACore:Page@paginate!',
    $current,
    $last,
    null,
    function ($type, $pageNo, $label, $state, $href) {
        unset($href);
        if ($type === 'ellipsis') {
            return '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $off = ($state === 'active' || $state === 'disabled') ? ' disabled' : '';
        $token = Crypto::encrypt((string) (int) $pageNo, 'Shop.items.page');
        if (!is_string($token) || $token === '') {
            return '<li class="page-item disabled"><span class="page-link">'
                . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</span></li>';
        }
        return '<li class="page-item ' . $state . '"><button type="button" class="page-link shop-page" data-page="'
            . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $off . '>'
            . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</button></li>';
    }
);
```

Footer (no `--split`):

```html
<div class="card-footer dacore-list-pager">
  <div class="dacore-list-pager-summary text-body-secondary small">{{ var: $pagerSummary }}</div>
  <nav class="dacore-list-pager-nav" aria-label="{{_ "Pagination" }}">
    <ul class="pagination mb-0">{{ var: $links }}</ul>
  </nav>
</div>
```

Outer card `#shopListWrap`, inner `#shopListInner` contains **table + footer**. Overlay the wrap. Patch the inner.

---

## 4. JS (`function (el, e)` — not `e.currentTarget`)

```javascript
$dotapp().live("click", ".shop-page", function (el, e) {
  if (e && typeof e.preventDefault === "function") {
    e.preventDefault();
  }
  if (!el || el.disabled) {
    return;
  }
  var item = el.closest(".page-item");
  if (item && (item.classList.contains("disabled") || item.classList.contains("active"))) {
    return;
  }
  var page = el.getAttribute("data-page") || "";
  if (!page) {
    return;
  }
  Notiflix.Block.standard("#shopListWrap", "Loading…");
  $dotapp().load("/api/v1/auth/Shop/items/list", "POST", { page: page, q: currentQuery },
    function (raw) {
      var reply = $dotapp().parseReply(raw);
      if (reply && reply.status == 1 && reply.html) {
        $dotapp("#shopListInner").html(reply.html);
      }
      Notiflix.Block.remove("#shopListWrap");
    },
    function () { Notiflix.Block.remove("#shopListWrap"); }
  );
});
```

**MUST NOT** `history.replaceState` / `?page=` — that burns CRC on the next POST.
