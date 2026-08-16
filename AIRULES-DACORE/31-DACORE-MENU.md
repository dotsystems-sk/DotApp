# 31 — DACore Menu Registration

Menu items live in the `dacore_menu` table. Register them **from your installer**, never on every request.

---

## API

```php
DotApp::call("DACore:Menu@register", string $menuid, array $data): bool
```

| Aspect | Detail |
|--------|--------|
| Returns | `bool` — `true` on insert or update |
| `false` when | `$menuid` is empty, `$data` is not an array, `name` is empty, or the DB write fails |
| Throws | never |
| Logs | nothing — **check the return value yourself** |
| Idempotency | **upsert by `menuid`** (safe to call repeatedly) |
| Cache | clears `Cache::use('DAcore')` when `Config::module('DACore','useCache') === true` |
| Unregister | **no API exists** — see [36](36-DACORE-KNOWN-ISSUES.md) |

`$menuid` is truncated to 50 chars. Use a stable dotted id: `YourModule.section.item`.

---

## `$data` keys (complete)

| Key | Type | Required | Default | Notes |
|-----|------|----------|---------|-------|
| `name` | string | **yes** | — | Visible label; empty ⇒ `register` returns `false` |
| `parent` | string | no | `''` | Parent's **`menuid`** (not a numeric id). Max 50 chars. `''` = root |
| `icon` | string | no | `''` | Remix Icon classes, e.g. `ri ri-time-line`. Plain classes get wrapped into `<i class="menu-icon ...">` automatically; raw `<i ...>` HTML is kept as-is |
| `url` | string | no | `''` | Route path. Max 100 chars |
| `urlprefix` | int | no | `0` | `1` = prepend `Config::module('DACore','prefixUrl')` when rendering |
| `rights` | array or JSON string | no | `'{}'` | Visibility rule (see below). Arrays are `json_encode`d for you |
| `type` | int | no | `1` | `0` header, `1` leaf, `2` expandable parent |
| `ordering` | int | no | `0` | Ascending sort within the same parent |

### `type` semantics

| `type` | Renders as | Visibility rule |
|--------|-----------|-----------------|
| `0` | section header | shown **only if it has at least one visible child** |
| `1` | leaf link | shown when rights pass |
| `2` | expandable parent | **skipped when it has no visible children and an empty `url`** |

So a three-level menu is: header (`0`) → parent (`2`) → leaf (`1`), each child pointing at its parent's `menuid`.

### `rights` semantics (evaluated at render time, OR logic)

| Value | Meaning |
|-------|---------|
| `[]` / `'{}'` / empty after decode | **no restriction** — visible to every logged-in user |
| `["*"]` | any logged-in user |
| `["YourModule.*"]` | user has any permission starting with `YourModule.` |
| `["dotapp.root","YourModule.admin"]` | `Auth::can([...])` with OR semantics |

Wildcards work **here** (menu) but **not** for AI tool rights ([34](34-DACORE-AI-TOOLS.md)).

---

## Correct registration (from `Installation.php`)

```php
use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Logger;

$rights = ['dotapp.root', 'Shop.administrator', 'Shop.items.view'];

// 1) section header
$ok = DotApp::call("DACore:Menu@register", "Shop.main", [
    'name' => 'Shop',
    'parent' => '',
    'icon' => '',
    'url' => '',
    'urlprefix' => 1,
    'rights' => json_encode(['dotapp.root', 'Shop.*']),
    'type' => 0,
    'ordering' => 500,
]);
if ($ok !== true) {
    Logger::use()->error('Menu register failed', ['menuid' => 'Shop.main']);
}

// 2) expandable parent
DotApp::call("DACore:Menu@register", "Shop.main.catalog", [
    'name' => 'Catalog',
    'parent' => 'Shop.main',
    'icon' => 'ri ri-store-2-line',
    'url' => '',
    'urlprefix' => 1,
    'rights' => json_encode($rights),
    'type' => 2,
    'ordering' => 1,
]);

// 3) leaf pointing at your route
DotApp::call("DACore:Menu@register", "Shop.main.catalog.items", [
    'name' => 'Items',
    'parent' => 'Shop.main.catalog',
    'icon' => '',
    'url' => '/shop-admin/items',          // urlprefix=1 -> /dacore/shop-admin/items
    'urlprefix' => 1,
    'rights' => json_encode($rights),
    'type' => 1,
    'ordering' => 1,
]);
```

The `url` must match the route you registered in `module.init.php`:

```php
Router::get(Config::module("DACore", "prefixUrl") . "/shop-admin/items", "Shop:Admin@items!");
```

---

## Reading the menu (rarely needed)

```php
$nodes = DotApp::call('*DACore:Menu@getItems!', '');   // '' = full tree
```

Each node:

```php
[
    'type' => 'menu-header' | 'menu-item',
    'text' => string,
    'url'  => string,     // absent for headers
    'icon' => string,     // HTML
    'items' => array,     // optional children
]
```

Passing a non-empty `$menuId` returns that branch plus a synthetic **"Return back"** leaf pointing at `prefixUrl + defaultUrl`. Results are cached under key `DACore.menu` with context `{menuid, user}` for 600 s when `useCache` is on.

Render HTML with `DotApp::call('DACore:Menu@generate!', $nodes, $options)` — returns `<li>` fragments without a wrapping `<ul>`. `$options` accepts `current_file` and `base_href`. Normally `Page@withMenu` does this for you.

---

## Common mistakes

| Wrong | Right |
|-------|-------|
| Registering menu items in `module.init.php` | Register in `Installation.php` (once per version) |
| `'parent' => 12` (numeric id) | `'parent' => 'Shop.main'` (parent's `menuid`) |
| Forgetting `'urlprefix' => 1` | URL then misses `/dacore` and 404s |
| `'type' => 2` with no children and no `url` | Item silently disappears |
| Random `menuid` per install | Duplicate menu entries — keep ids stable |
| `INSERT INTO dacore_menu ...` | `DACore:Menu@register` |
| Ignoring the return value | Check `!== true` and log |
| Expecting an unregister call | None exists — plan your uninstall SQL |
