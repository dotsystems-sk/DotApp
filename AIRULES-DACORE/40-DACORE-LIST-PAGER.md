# 40 — List pager (MUST — law)

Canonical admin pager for DACore-bound modules. Copy this contract. Do **not** invent a second pager, a DataTable, or `?page=` links.

**Open this file** when the screen lists records that can grow. Other docs point here: [00](00-AGENT-CONTRACT.md) §2 item 7, [09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [33](33-DACORE-PAGES-AND-UI.md) §3. Copy-paste: [EX-D08](examples/EX-D08-list-pager.md).

Reference implementation (read-only pattern): `DACoreDebugger` history — `Libraries/HistoryStore.php` `pagerHtml()`, `assets/js/history.js`. DACore chrome: `dacore-list-pager*` in `app/modules/DACore/assets/css/pages/dotapp-ui/users.css` (consume the CSS; **MUST NOT** edit DACore).

---

## 1. What a legal pager is

| MUST | MUST NOT |
|------|----------|
| Stay on the same URL | `<a href="?page=2">`, `?logpage=`, `location.reload()`, `history.replaceState` of query params |
| SQL pages (`COUNT(*)` + `LIMIT`/`OFFSET`) | `->all()` then slice in PHP/JS |
| Interactive AJAX (`$dotapp().load()`) | A pager that reloads the admin shell |
| Overlay the **card** while in flight | Leave the list clickable |
| Patch **rows and pager** from JSON | Patch only `TBODY` and leave stale page buttons |
| Encrypted page token in `data-page` | Plain `data-page="2"` |
| `$dotapp().live("click", …, function (el, e)` | `function (e)` + `e.currentTarget` |

Skip a pager **only** when the set is closed by product design (four fixed cards). “Few rows now” is not a skip.

---

## 2. HTML and CSS classes (MUST)

DACore already styles these. **MUST** pass this file on `Page@withMenu!` `$css` unless the page already loads it:

`/assets/modules/DACore/css/pages/dotapp-ui/users.css`

**MUST** use these class names (do not rename, do not invent `shop-pager`):

| Class | Where |
|-------|--------|
| `card` | Outer list card (stable overlay target) |
| `card-header` | Title + search |
| `input-group dacore-list-search` | Search field chrome |
| `table-responsive` + `table table-hover mb-0` | Rows |
| `card-footer dacore-list-pager` | Pager bar — **never** `dacore-list-pager--split` |
| `dacore-list-pager-summary text-body-secondary small` | “Showing 1–10 of 53” (centered) |
| `dacore-list-pager-nav` | Wraps `<ul class="pagination">` |
| `pagination mb-0` | Bootstrap list of page controls |
| `page-item` + `page-link` | Each control |
| `{lowercase}_page` e.g. `shop-page` | Click target for **your** `live()` |

**MUST NOT** use `data-dacore-page` in your module — that attribute belongs to DACore’s own admin scripts. Your buttons use encrypted `data-page` plus **your** class.

**MUST NOT** add `dacore-list-pager--split` — not on a module list **and not in DACore**. Split (summary left, links right) is forbidden. Every list matches DACoreDebugger history: summary centered above the numbers.

DACore’s own lists (users, logs, email, SMS, inbox, plugin installer) **MUST** use `ListPager` + `views/layouts/users/_pager.layout.php` + `DACore:Page@paginate!` (encrypted `data-dacore-page`). Module lists **MUST NOT** use `data-dacore-page`.

Skeleton:

```html
<div class="card" id="shopListWrap" data-list-url="{{ var: $listUrl }}" data-busy="{{_ "Loading…" }}" data-fail="{{_ "Request failed" }}">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <h5 class="mb-0">{{_ "Items" }}</h5>
    <div class="input-group dacore-list-search">
      <span class="input-group-text"><i class="icon-base ri ri-search-line"></i></span>
      <input type="search" class="form-control" id="shopListSearch" maxlength="80" autocomplete="off" placeholder="{{_ "Search…" }}" />
    </div>
  </div>
  <div id="shopListInner">
    <!-- table-responsive + table, then pager -->
    <div class="card-footer dacore-list-pager">
      <div class="dacore-list-pager-summary text-body-secondary small">{{ var: $pagerSummary }}</div>
      {{ if ($showLinks == 1) }}
      <nav class="dacore-list-pager-nav" aria-label="{{_ "Pagination" }}">
        <ul class="pagination mb-0">{{ var: $links }}</ul>
      </nav>
      {{ /if }}
    </div>
  </div>
</div>
```

Omit the whole `card-footer` when `total < 1` (empty state lives in the table area). Hide the `<nav>` when `last_page === 1` (summary only: “Showing 1–3 of 3”).

---

## 3. Page controls (MUST)

`DACore:Page@paginate!` returns **`<li>` items only**. Wrap them in `ul.pagination`. Pass **`$callable`**. Pass `$href = null`. **MUST NOT** pass a `?page=` href.

The 5th callable argument is the **href helper**, not a per-item URL. Ignore it.

```php
$links = (string) DotApp::call(
    'DACore:Page@paginate!',
    $current,   // current_page
    $last,      // last_page (from COUNT, not from count($rows))
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

| Control | Markup |
|---------|--------|
| Ellipsis | `<li class="page-item disabled"><span class="page-link">…</span></li>` |
| Current / disabled | `<button … disabled>` **or** `<span class="page-link">` — click must no-op |
| Other pages | `<button type="button" class="page-link shop-page" data-page="{ciphertext}">` |

**MUST NOT** `<a class="page-link" href="?page=">`.

`$key2` for the page token is unique per list (`Shop.items.page`, `Shop.orders.page`). Decrypt with the **same** key. `false` / non-string / page `< 1` → reject (`Bad request`) or, for search reset with no token, default to page **1**.

---

## 4. SQL total (MUST)

`QueryObject::paginate()` runs COUNT through `execute()`. That return is **not** a numeric total, so `total` becomes `0` and the UI shows **“1–10 of 10”** with no page buttons.

**MUST** count and list as two cheap queries (same pattern as PluginLogs / debugger history):

```php
$countRows = DB::module('RAW')->q(function ($qb) use ($bind) {
    $qb->raw('SELECT COUNT(*) AS `total` FROM `shop_items`' . $whereSql, $bind);
})->all();
$total = (int) ($countRows[0]['total'] ?? 0);
$last = $total > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
if ($page > $last) {
    $page = $last;
}
$rows = [];
if ($total > 0) {
    $offset = (int) (($page - 1) * $perPage);
    $rows = DB::module('RAW')->q(function ($qb) use ($listSql, $bind, $perPage, $offset) {
        $qb->raw($listSql . ' ORDER BY `id` DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset, $bind);
    })->all();
}
```

**MUST NOT** invent `total` from `count($rows)` on the current page. Comment **why** if you do not use `QueryObject::paginate()`.

`LIMIT`/`OFFSET` are integers concatenated (PDO string-binds `LIMIT`). Search `LIKE` values stay bound. Cap `per_page`.

---

## 5. JS (MUST)

`$dotapp().live(event, selector, handler)` calls **`handler(el, e)`**. The **first** argument is the matched element. Treating the first argument as a DOM event makes `e.currentTarget` `undefined` and the click **silently does nothing**.

```javascript
$dotapp().live("click", ".shop-page", function (el, e) {
  if (e && typeof e.preventDefault === "function") {
    e.preventDefault();
  }
  if (!el || !wrap.contains(el) || el.disabled) {
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
  loadPage(page);
});
```

`loadPage`:

1. Overlay `#shopListWrap` (`Notiflix.Block` on admin).
2. `$dotapp().load(listUrl, "POST", { page: page, q: currentQuery }, ok, err)`.
3. `parseReply`. On `status == 1` set `#shopListInner` HTML (rows **and** pager).
4. Remove overlay on success **and** error. Toast on failure.

POST URL: `/api/v1/auth/{Module}/…/list`. Prefix `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again. Decrypt `page` from `$request->data(true)['data']`.

**MUST NOT** `history.replaceState` / write `?page=` into the address bar. `crcCheck()` binds **HTTP Referer** to the URL at page load. Changing the query string burns the **next** POST (`CRC failed`).

Search: debounce ~300 ms, from 3 characters, reset to page 1 (omit token or empty `page` — PHP defaults to 1). Keep `q` on pager POSTs.

---

## 6. Finish-gate greps (pager chunks)

| Fail | Fix |
|------|-----|
| `href="?page` / `logpage=` / `replaceState` | Buttons + `load()`, same URL |
| `live("click"` handler with one `e` argument and `e.currentTarget` | `function (el, e)` + `el.getAttribute("data-page")` |
| `data-page="2"` / `data-dacore-page` in **your** module | Encrypted `data-page` + your class |
| `dacore-list-pager--split` | Centered column (debugger history) |
| `->paginate(` then `total` used for `last_page` without a real `COUNT(*)` | Two queries as in §4 |
| `count($rows)` as table total | Use `COUNT(*)` |
| Pager POST `crcCheck()` after `LoginAndCRC!` | Prefix only |
| Overlay missing / not removed on error | `Notiflix.Block` on the card |

---

## 7. Antipatterns

| Wrong | Right |
|-------|-------|
| `function (e) { e.currentTarget … }` | `function (el, e) { el.getAttribute("data-page") }` |
| `QueryObject::paginate()` total → “1–10 of 10” | `COUNT(*)` + `LIMIT`/`OFFSET` |
| `?page=` / `?logpage=` / `replaceState` | Stay on the path; CRC Referer stays valid |
| `<a class="page-link">` | `<button type="button" class="page-link">` |
| Plain page number in HTML | `Crypto::encrypt((string) $n, 'Shop.items.page')` |
| `data-dacore-page` in Shop | `shop-page` + `data-page` |
| `--split` anywhere (including DACore users list) | Centered `dacore-list-pager` (debugger history) |
| Invent pager CSS | Load DACore `users.css` |
| 2FA unlock required to **read** a log page | Unlock only mutations |
