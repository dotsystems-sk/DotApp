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
| `name` | string | **yes** | — | Visible label; empty ⇒ `register` returns `false`. **MUST** be product copy ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) — never prompt-echo |
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

## Own header, grouping, and module-own menu (**MUST**)

Not every module has a sidebar. If **your** module **does** register menu items:

**Ideal:** one section header of your own (`type => 0`, `parent => ''`, `menuid` like `Shop.main`). Extra headers are OK when the product has several top-level sections. Do **not** register only leaves at the root — the sidebar will not group.

**Group when there are many items.** A header with ten `type => 1` leaves wastes the **global** admin sidebar. From about **five** items, either nest them under expandable parents (`type => 2`) on the shared menu, or switch to a **module-own** menu. Shape for shared grouping: header `0` → group `2` → leaf `1`.

**Two layouts — ASK the user in chat before you scaffold a new DACore module** (do not guess, do not default to ten flat leaves):

| Layout | When | Main (global) sidebar | Inside the module |
|--------|------|----------------------|-------------------|
| **Shared** | Few items, or the user wants everything in one tree | Your header + groups (`2`) + leaves | `Page@withMenu!(…, '')` — full menu |
| **Module-own** | Many items (typical when the module is a real app) | Your header + **one** leaf that opens the module | `Page@withMenu!(…, 'Shop.nav')` — only that branch |

Module-own is the better UX for a large module: the global sidebar stays short; after the operator opens the module, the sidebar is **that module’s own list**. DACore always appends a **Return back** leaf at the **bottom** of a branch menu (`getItems($menuId)` when `$menuId !== ''`). **MUST NOT** register that item yourself.

A non-empty `$menuId` loads **one level only** (`menuid = X OR parent = X`). Nested `type => 2` groups under that branch will **not** show their children. Grouping with `type => 2` belongs on the **shared** menu (full table). On a module-own sidebar, register inner items as **direct children** of the branch id (`type => 1`). A long inner list is fine — it does not crowd the rest of the admin. See [36](36-DACORE-KNOWN-ISSUES.md).

Module-own wiring:

```php
// Global sidebar: header + one entry (does not list the module’s pages)
DotApp::call("DACore:Menu@register", "Shop.main", [ /* type 0, parent '' */ ]);
DotApp::call("DACore:Menu@register", "Shop.main.home", [
    'name' => 'Shop',
    'parent' => 'Shop.main',
    'url' => '/shop-admin/',
    'urlprefix' => 1,
    'type' => 1,
    // …
]);

// Inner items — parent is the branch id passed to withMenu, NOT Shop.main
DotApp::call("DACore:Menu@register", "Shop.nav.items", [
    'name' => 'Items',
    'parent' => 'Shop.nav',
    'url' => '/shop-admin/items',
    'urlprefix' => 1,
    'type' => 1,
    // …
]);
```

Every inner admin page (same `$menuId` on all of them):

```php
return static::call("DACore:Page@withMenu!", $title, $html, [], $css, $js, 'Shop.nav');
```

`''` / `null` = full shared menu. A `menuid` = that branch’s direct children + Return back. See [33](33-DACORE-PAGES-AND-UI.md).

Do **not** register `Shop.nav` itself as a root `type => 0` header — it would appear as a second section in the global sidebar. It is only the `parent` / `$menuId` of the inner items.

**`menuid` always starts with your module name** (`Shop.…`). That is what uninstall deletes. Register from `Installation.php` ([35](35-DACORE-INSTALL.md)).

**Allowed:** an **extension** module may hang items under another module’s header or parent (`'parent' => 'Shop.main.catalog'`) when it extends that product. The `menuid` still belongs to **you** (`Reports.shop.export`). Do **not** reuse `Shop.*` ids.

```php
// Reports extends Shop — under Shop's catalog, but the id is Reports.*
DotApp::call("DACore:Menu@register", "Reports.shop.export", [
    'name' => 'Export',
    'parent' => 'Shop.main.catalog',
    'icon' => 'ri ri-download-2-line',
    'url' => '/reports-admin/shop-export',
    'urlprefix' => 1,
    'rights' => json_encode(['dotapp.root', 'Shop.administrator', 'Reports.export']),
    'type' => 1,
    'ordering' => 50,
]);
```

**Uninstall MUST** remove **only your** rows (`WHERE menuid LIKE 'Reports.%'` or the explicit ids you registered). **MUST NOT** `DELETE … LIKE 'Shop.%'` from an extension — that wipes the host module’s menu. See [36](36-DACORE-KNOWN-ISSUES.md).

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

Passing a non-empty `$menuId` selects `menuid = X OR parent = X` (**one level**), then appends a synthetic **"Return back"** leaf pointing at `prefixUrl + defaultUrl` (last — do not register it). Results are cached under key `DACore.menu` with context `{menuid, user}` for 600 s when `useCache` is on.

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
| Own sidebar with no `type => 0` header | One header per module (`Shop.main`); more only if you need more sections |
| Ten `type => 1` leaves under a header (global sidebar) | **ASK** shared vs module-own; group with `type => 2`, or header + one entry + `withMenu` `$menuId` |
| Guess the layout / skip the chat question | Stop and ask: shared full menu or module-own menu |
| Nest `type => 2` groups under a branch `$menuId` | Branch is one level — inner items are direct `type => 1` children of that id |
| Invent a “Return back” menu row | DACore appends it when `$menuId !== ''` |
| Extension `menuid` `Shop.extra…` / uninstall `LIKE 'Shop.%'` | Your prefix (`Reports.…`); delete only `Reports.%` ([31](31-DACORE-MENU.md)) |
