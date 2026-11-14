# 46 — `catalog` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a catalog **pack** must be able to interoperate from this page alone.

This role is **not** the <Host> **host**. A <Host> that owns products **omits** extras. A satellite catalog package sets `extra1=catalog`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `catalog` |
| `extra2` | `v1` |
| `extra3` | `products` \| `products-variants` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit unused) |

```php
'extra1' => 'catalog',
'extra2' => 'v1',
'extra3' => 'products',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'catalog', 'v1');
$variants = DotApp::call('DACore:Plugins@listByContract!', 'catalog', 'v1', 'products-variants');
```

| extra3 | Meaning |
|--------|---------|
| `products` | Flat products (SKU / name / price). `get` is a product |
| `products-variants` | Products plus variants. `get` accepts a product **or** variant id; `list` may include `variant_id` |

**Kind:** peer. **Controller:** `{Module}:CatalogContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=catalog` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('catalog','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CatalogContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CatalogContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PimPack',            // exact module name
    'modes' => ['products-variants'], // extra3 this pack actually implements
    'currencies' => ['EUR'],
    'page_size' => 20,                // default LIMIT (int 1..100)
    'variants' => true,               // false when extra3=products
]
```

**Failure:** `['ok' => false, 'message' => 'Catalog is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Prices are **DECIMAL STRINGS**. **MUST NOT** `float`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Pack.product.id): $id }}` (and a distinct `$key2` for variants, e.g. `Pack.variant.id`). Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

**Needed columns only.** `list` / `get` **MUST NOT** `select('*')`. Growing catalogs: `COUNT(*)` + `LIMIT` / `OFFSET` — **MUST NOT** `all()`.

### `list($opts)`

**Call:** `DotApp::call('{Module}:CatalogContract@list!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `page` | int | 1-based page. Invalid → 1 |
| `per_page` | int | Optional. Clamp to 1..`page_size` (max 100) |
| `q` | string | Optional name / SKU filter (bound `LIKE`, not raw SQL) |
| `currency` | string | Optional ISO-4217 on the pack whitelist. Default = first `currencies[]` |
| `active` | int | Optional `1` / `0`. Omit = pack default (usually active only) |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext or pack token…', // encrypt before HTML
            'sku' => 'SKU-104',
            'name' => 'Cotton shirt',
            'price' => '29.90',       // decimal string
            'currency' => 'EUR',
            'variant_id' => '',       // pack token when extra3=products-variants and this row is a variant; else ''
        ],
    ],
    'page' => 1,
    'last_page' => 4,
    'total' => 73,                    // COUNT(*) — not count($items)
]
```

Columns on each item **MUST** stay this set (plus `variant_id` when modes include variants). **MUST NOT** dump description HTML, stock lots, or cost.

**Failure:** decrypt fail on a filter token the pack does not use; unknown currency → `ok:false`.

### `get($id)`

**Call:** `DotApp::call('{Module}:CatalogContract@get!', $id)`

**Input:** `$id` string — product id, or variant id when `extra3=products-variants` (token or ciphertext).

**Success:**

```php
[
    'ok' => true,
    'id' => '…same product token…',
    'sku' => 'SKU-104',
    'name' => 'Cotton shirt',
    'price' => '29.90',
    'currency' => 'EUR',
    'variant_id' => '…variant token or empty…',
    'active' => 1,
]
```

**Failure:** unknown / decrypt fail / gone → `ok:false`.

---

## 5. Lists, HTML ids, indexes

- Admin / picker lists follow [40](../40-DACORE-LIST-PAGER.md) when the **host** renders a growing HTML list (`COUNT` + encrypted `data-page`).
- Product / variant ids in `value` / `data-*` / views: `{{ enc(...) }}`.
- Pack tables `{lowercase_modulename}_*`. Every `WHERE` / `ORDER BY` column used by `list` / `get` **MUST** be indexed (comment names the query).
- **MUST NOT** query inside `foreach` of `$items` — prefetch + keyed map.

---

## 6. Hooks

`list` / `get` **MUST NOT** fire a hook.

If the pack **persists** a product (own admin, not this contract), that save **MAY** fire `module.{mod}.catalog_product_saved.hook` with `id` only — **MUST NOT** prices as a side channel for every browse. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`pim`, `products`, `shop-catalog`)
- Set `extra1=catalog` on `<Host>`
- `glob('app/modules')` or `include` the pack to discover it
- `all()` or `select('*')` on a growing catalog
- Put a plain product id in HTML
- Use `float` for `price`
- Leak `getMessage()` or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML product / variant id is encrypted
- `list` uses `COUNT` + `LIMIT` and needed columns only
- Prices are decimal strings
- No `crcCheck()` on `capabilities` / `list` / `get`
