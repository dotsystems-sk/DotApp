# 46 — `warehouse` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a location pack must be able to interoperate from this page alone. Machine catalog: `DACore\Libraries\ExtraContracts` role `warehouse`, controller `WarehouseContract`.

This role is **locations**. On-hand sellable qty and checkout holds are `inventory`. Do not merge the two packs into one `extra1`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `warehouse` |
| `extra2` | `v1` |
| `extra3` | `bins` \| `lots` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'warehouse',
'extra2' => 'v1',
'extra3' => 'bins',
'extra4' => 'erp',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'warehouse', 'v1');
$lotBins = DotApp::call('DACore:Plugins@listByContract!', 'warehouse', 'v1', 'lots');
```

| extra3 | Meaning |
|--------|---------|
| `bins` | Named bins / slots. `locate` returns locations; `move` is bin → bin |
| `lots` | Same plus lot identity on each bin row and on `move` |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** invent `aisle` / `serial` here |

**Kind:** peer. **Controller:** `{Module}:WarehouseContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=warehouse` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('warehouse','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':WarehouseContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:WarehouseContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'BinKit',            // exact module name
    'modes' => ['bins'],             // extra3 this pack actually implements
    'qty_kind' => 'int',             // int | decimal — never float
    'qty_scale' => 0,
    'supports_lots' => false,        // true when extra3=lots
]
```

**Failure:** `['ok' => false, 'message' => 'Warehouse is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Location ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing location / balance tables: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

Quantity follows the same int / decimal-string rule as `inventory`. **MUST NOT** `float`.

SKU charset `[A-Za-z0-9._-]`, length ≤ 64, bound in SQL.

### `locate($sku)`

**Call:** `DotApp::call('{Module}:WarehouseContract@locate!', $sku)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$sku` | string | Catalog SKU. Empty / illegal charset → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'bins' => [
        [
            'id' => '…ciphertext…',  // location id — HTML: {{ enc(BinKit.loc.id): $id }}
            'code' => 'A-12',        // generic bin code, not an address
            'qty' => 8,              // int or decimal string at this location
            'lot' => '',             // lots mode: encrypted lot id or pack token; else ''
        ],
    ],
]
```

Unknown SKU with no rows: `ok:true` and `bins` `[]` when the SKU is valid but nowhere on hand. Illegal SKU → `ok:false`.

`locate` is a **read**. **MUST NOT** `all()` then filter; use indexed SKU lookup. If a SKU can occupy more bins than a page, return the first page of locations plus:

```php
'page' => 1,
'last_page' => 1,
'total' => 2,
```

Default page size ≤ 50. Optional second argument `$page` (int, 1-based; invalid → 1) when the pack pages bins.

**Failure:** bad SKU, pack not ready → `ok:false`.

### `move($from, $to, $qty, $sku)`

**Call:** `DotApp::call('{Module}:WarehouseContract@move!', $from, $to, $qty, $sku)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$from` | string | Encrypted source location id |
| `$to` | string | Encrypted destination location id |
| `$qty` | int \| string | Same qty rules as `inventory` (`int` or decimal string) |
| `$sku` | string | SKU that must exist on `$from` |

Optional fifth argument (lots mode): `$lot` encrypted lot id or pack token. Required when `extra3=lots` and the source row is lot-scoped.

**Success:**

```php
[
    'ok' => true,
]
```

The pack **MUST** decrement `$from` and increment `$to` in one persist unit (transaction or equivalent). `$from === $to` after decrypt → `ok:false`. Insufficient qty on source → `ok:false`.

**Failure:** decrypt `=== false` on `$from` / `$to` / `$lot`, unknown location, bad qty, SKU mismatch, rights → `ok:false`. **MUST NOT** leave a partial move.

---

## 5. Host warehouse UI (not HTTP on the contract)

`locate` / `move` are in-process. Admin POST for a move lives on the **host or pack** `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. That action **MUST NOT** `crcCheck()` again. Contract methods themselves have **no CRC**.

**MUST NOT** expose `move` on `/api/v1/noauth/…`.

---

## 6. Admin / UI

Bounded pack choice = native `<select>` or `dotSelect2` from `listByContract!`. Location pickers: `<select>` / `dotSelect2` of encrypted ids, or a paged locate result — **MUST NOT** a typed absolute path or a remembered plaintext location id.

Move confirm is graphical (`Notiflix.Confirm` on admin) — never `alert()`. Overlay the form until the request ends; toast the outcome.

No media picker. This role has no `picker_js`.

---

## 7. Location identity v1 (**MUST**)

- Location ids in HTML are encrypted. Decrypt `false` → reject. Still check rights in PHP.
- `code` is a generic bin label (`A-12`). **MUST NOT** return street address, GPS, or staff names.
- Qty is int or decimal string — never float, never money.
- Tables `{lowercase_modulename}_*` ; index SKU, location, lot (comment names the `locate` / `move` query).
- `bins` mode: omit or empty `lot`. `lots` mode: each balance row is (location, sku, lot).

---

## 8. Hooks

Fire only after a useful persist — **not** on `locate`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.warehouse_moved.hook` | Bin qty moved | `from_id`, `to_id`, `sku`, `qty`, `lot` (empty when `bins`) |

**MUST NOT** put addresses, staff PII, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`wms`, `bins`, `locations`)
- `glob('app/modules')` or `include` the pack to discover it
- Use `float` for qty
- Leak `getMessage()`, plaintext location ids in HTML, or map coordinates
- `all()` on a growing bin-balance table
- Fire a hook on `locate`
- Treat this role as `inventory` (no `reserve` / `commit` here)
- Set `extra1=warehouse` on `<Host>`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML location / lot id is encrypted
- `locate` returns `bins[]`; `move` is transactional
- Qty is int or decimal string — never float
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `locate` / `move`
