# 46 — `menu-public` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a **public website** menu pack. A host (CMS, Shop) and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** DACore admin sidebar (`dacore_menu`, `DACore:Menu@register`). **MUST NOT** read or write `dacore_menu`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `menu-public` |
| `extra2` | `v1` |
| `extra3` | `tree` \| `flat` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'menu-public',
'extra2' => 'v1',
'extra3' => 'tree',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'menu-public', 'v1');
$tree = DotApp::call('DACore:Plugins@listByContract!', 'menu-public', 'v1', 'tree');
```

| extra3 | Meaning |
|--------|---------|
| `tree` | Nested items. Each node **MAY** have `children` |
| `flat` | One level. `children` is always `[]`. A `parent_id` in pack admin is ignored at `tree()` |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** put a menu name or locale here (locale is a `locale` pack) |

A pack that implements both sets `extra3` to the **primary** mode in `about.php` and lists both in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:PublicMenuContract@…!`

The **host** **MUST NOT** set `extra1=menu-public` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('menu-public','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PublicMenuContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PublicMenuContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SiteMenu',          // exact module name
    'modes' => ['tree'],             // extra3 this pack actually implements
    'max_depth' => 3,                // tree only; 1 in flat
    'max_items' => 80,               // total nodes one tree() returns
]
```

**MUST NOT** return admin `menuid` values, `dacore_menu` rows, or rights names.

**Failure:** `['ok' => false, 'message' => 'The site menu is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Menu item tables that grow: `COUNT` + `LIMIT` on admin lists — **MUST NOT** `all()`.

### `tree()`

**About:** Return the public nav items. The host (or pack public layout) walks `items` and prints links.

**Call:** `DotApp::call('{Module}:PublicMenuContract@tree!')`

**Input:** none. v1 has **no** `$menuId` argument. One public tree per pack. A later contract version may add named menus.

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'label' => 'About',
            'href' => '/about',
            'children' => [
                [
                    'label' => 'Team',
                    'href' => '/about/team',
                    'children' => [],
                ],
            ],
        ],
    ],
]
```

| Field | Type | Meaning |
|-------|------|---------|
| `label` | string | Visible text. Pack **MUST** `htmlspecialchars` before the host prints with `{{ var: }}` **or** the host escapes — one layer, not double-encode by accident. Treat as untrusted product copy from the operator |
| `href` | string | **Pack-owned path only** (see §5) |
| `children` | array | Same shape. `flat` → always `[]`. Depth ≤ `max_depth` |

**MUST NOT** add `target`, `onclick`, `javascript:`, or HTML in `label`. **MUST NOT** include `id` as a plaintext integer for HTML; if the host needs an id for an admin preview, it is ciphertext in a separate pack admin API, not this public `tree()`.

Cap total nodes at `max_items`. Deeper / extra rows are omitted (not silently fetched with `all()`).

**Failure:** pack not configured → `['ok' => false, 'message' => 'The site menu is not ready.']`.

`tree` **MUST NOT** throw.

---

## 5. `href` — pack-owned paths only (**MUST**)

Each `href` **MUST** be one of:

- a host-relative path starting with `/`, charset `[A-Za-z0-9._:/-]`, no `..`, no `?` from the request, no `#` javascript; or
- a path the **pack** registered in `initialize()`; or
- an `https://` URL whose host is on the operator allowlist in **pack** settings (external “Shop” / docs).

**MUST NOT:**

- Build `href` from `$request->query()` / `$request->data()`
- Put `href` into `header()`, `Location`, or `HttpHelper` from request input
- Use `dacore_menu` URLs (`{prefixUrl}/DACore/…` admin) as public items
- Use `javascript:`, `data:`, or scheme-relative `//` unless the operator allowlisted that exact host as `https://`

The host **MAY** wrap `tree()` in its public nav drawer ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §3): overlay L/R, lock page scroll, drawer scrolls.

---

## 6. Not `dacore_menu` (**MUST**)

`DACore:Menu@register` / `dacore_menu` is the **admin sidebar**. This contract is the **public site**.

**MUST NOT** `SELECT` / `INSERT` / `UPDATE` / `DELETE` `dacore_menu`. **MUST NOT** reuse admin `menuid` strings as public `href`. Uninstall of this pack deletes only **its** `{lowercase_modulename}_*` rows.

---

## 7. Host render

The host owns the `<nav>`. It calls `tree()` after pick and renders with a **host** (or pack) `.view.php` — **MUST NOT** concatenate a `<ul>` tree in a host controller factory.

Public mobile: overlay drawer; contacts + compact search in the drawer unless search is its own section.

After pack admin reorder: patch `reply.html` + toast — **MUST NOT** `location.reload()`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `tree`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.public_menu_updated.hook` | Public menu items saved / reordered | `item_count` |

**MUST NOT** put labels, hrefs, or secrets in the payload. Document in the pack `.hooks` only if this event actually fires. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`nav`, `menu`, `sitemap` as a synonym — `sitemap` is XML/RSS)
- `glob('app/modules')` or `include` the pack to discover it
- Read or write `dacore_menu`
- Build `href` from the request or send it in `header()`
- Put HTML or `javascript:` in `label` / `href`
- Leak `getMessage()` or request bodies
- `all()` on a growing item table without a cap
- Fire a hook on `tree`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `tree()` is `{ok, items[]}` with `label`, `href`, `children` only
- Every `href` is a pack-owned / allowlisted path
- No `dacore_menu` I/O
- Hooks named in `.hooks` only if `public_menu_updated` fires
- No `crcCheck()` on `capabilities` / `tree`
