# 46 — `inventory` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a stock pack must be able to interoperate from this page alone. Machine catalog: `DACore\Libraries\ExtraContracts` role `inventory`, controller `InventoryContract`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `inventory` |
| `extra2` | `v1` |
| `extra3` | `qty` \| `lots` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'inventory',
'extra2' => 'v1',
'extra3' => 'qty',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'inventory', 'v1');
$lots = DotApp::call('DACore:Plugins@listByContract!', 'inventory', 'v1', 'lots');
```

| extra3 | Meaning |
|--------|---------|
| `qty` | SKU-level on-hand only. `quote` / `reserve` / `commit` / `release` ignore lot tokens |
| `lots` | Same methods plus optional lot identity. A reserve without a lot may pick FIFO inside the pack |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** invent `serial` / `wms` here |

**Kind:** peer. **Controller:** `{Module}:InventoryContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=inventory` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('inventory','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':InventoryContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:InventoryContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'StockKit',          // exact module name
    'modes' => ['qty'],              // extra3 this pack actually implements
    'qty_kind' => 'int',             // int | decimal — never float
    'qty_scale' => 0,                // decimal places when qty_kind=decimal (e.g. 3)
    'supports_lots' => false,        // true when extra3=lots
    'reservation_ttl' => 900,        // seconds; 0 = until commit/release
]
```

**Failure:** `['ok' => false, 'message' => 'Inventory is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing stock / reservation tables: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

Quantity is **int** or a **decimal string** (`'1.250'`). **MUST NOT** PHP `float` / `double` for qty or money. Money does not belong on this contract (see `pricing`).

SKU is a host/catalog token: charset `[A-Za-z0-9._-]`, length ≤ 64, bound in SQL — **MUST NOT** concatenated into `WHERE`.

### `quote($sku, $qty)`

**Call:** `DotApp::call('{Module}:InventoryContract@quote!', $sku, $qty)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$sku` | string | Catalog SKU. Empty / illegal charset → `ok:false` |
| `$qty` | int \| string | Requested quantity. `int` when `qty_kind=int`. Decimal **string** when `qty_kind=decimal`. `0`, negative, or float → `ok:false` |

Optional third argument (lots mode only): `$lot` string pack token or encrypted lot id. Omit or `''` on `qty` mode.

**Success:**

```php
[
    'ok' => true,
    'available' => true,             // this qty can be reserved now
    'qty_available' => 12,           // int or decimal string; remaining sellable
]
```

`available` is `false` with `ok:true` when the SKU exists but on-hand cannot cover `$qty`. Unknown SKU → `ok:false`.

**Failure:** bad SKU, bad qty, decrypt fail (lot), pack not ready → `ok:false`.

### `reserve($sku, $qty, $ref)`

**Call:** `DotApp::call('{Module}:InventoryContract@reserve!', $sku, $qty, $ref)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$sku` | string | Same as `quote` |
| `$qty` | int \| string | Same qty rules as `quote` |
| `$ref` | string | Host order / cart / checkout token (not a raw HTML id). Length ≤ 64 |

Optional fourth argument (lots mode): `$lot` as on `quote`.

**Success:**

```php
[
    'ok' => true,
    'reservation_id' => '…ciphertext…', // HTML: {{ enc(StockKit.res.id): $id }}
]
```

The hold **MUST** reduce sellable qty so a second `quote` for the same units returns `available:false` (or a lower `qty_available`). Idempotent on the same `$ref` + `$sku` + `$qty`: return the existing reservation, do not double-hold.

**Failure:** insufficient qty, unknown SKU, expired pack, decrypt fail → `ok:false`. **MUST NOT** invent a reservation id on failure.

### `commit($reservationId)`

**Call:** `DotApp::call('{Module}:InventoryContract@commit!', $reservationId)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$reservationId` | string | Encrypted reservation id from `reserve` |

**Success:** `['ok' => true]`. On-hand decreases; the hold is closed. Idempotent if already committed.

**Failure:** decrypt `=== false`, unknown, already released, expired TTL → `ok:false`.

### `release($reservationId)`

**Call:** `DotApp::call('{Module}:InventoryContract@release!', $reservationId)`

`ExtraContracts` lists `commit` as the persist close. **v1 also requires** `release` so a cancelled checkout can return the hold.

| Arg | Type | Meaning |
|-----|------|---------|
| `$reservationId` | string | Same encrypted id as `commit` |

**Success:** `['ok' => true]`. Sellable qty is restored. Idempotent if already released.

**Failure:** decrypt fail, unknown, already committed → `ok:false`.

---

## 5. Host checkout (not HTTP on the contract)

Host **MUST** call these helpers in-process after the operator picked the pack. **MUST NOT** expose `reserve` / `commit` / `release` on `/api/v1/noauth/…`.

Checkout POST stays on the **host** `/api/v1/auth/{Host}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again. Contract methods themselves have **no CRC**.

Public “check stock” on the storefront **MAY** call `quote` only. If the host wraps `quote` in a public POST, **MUST** `throttle()`.

---

## 6. Admin / UI

Bounded pack choice = native `<select>` or existing `dotSelect2` from `listByContract!`. **MUST NOT** a typed module name.

Reservation ids in admin HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Lot pickers are a bounded or paged pack list — **MUST NOT** a bare text box that requires an exact remembered lot name when the pack can list lots.

No media picker. This role has no `picker_js`.

---

## 7. Quantity and identity v1 (**MUST**)

- `qty_kind=int`: PHP `int` ≥ 1 on write; replies use `int`.
- `qty_kind=decimal`: string matching `^[0-9]+(\.[0-9]{1,n})?$` with `n = qty_scale`. **MUST NOT** `float`.
- **MUST NOT** treat inventory qty as money. Amounts live on `pricing` / `payment`.
- Reservation ids that leave PHP are encrypted. Decrypt `false` → reject. Still check host rights / ownership in PHP.
- Tables `{lowercase_modulename}_*` with indexes on SKU, reservation token, and `$ref` (comment names the query).
- `lots` mode: lot tokens encrypted in HTML; FIFO when `$lot` is omitted.

---

## 8. Hooks

Fire only after a useful persist — **not** on `quote`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.inventory_reserved.hook` | Hold created | `reservation_id`, `sku`, `qty`, `ref` |
| `module.{mod}.inventory_committed.hook` | Hold committed | `reservation_id`, `sku`, `qty`, `ref` |
| `module.{mod}.inventory_released.hook` | Hold released or TTL expiry cleanup | `reservation_id`, `sku`, `qty`, `ref` |

**MUST NOT** put request bodies, customer PII, or payment tokens in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`stock`, `wms`, `inventory-pack`)
- `glob('app/modules')` or `include` the pack to discover it
- Use `float` for qty or money
- Leak `getMessage()`, raw table dumps, or reservation plaintext in HTML
- `all()` on a growing stock / reservation table
- Fire a hook on every `quote`
- Set `extra1=inventory` on `<Host>`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML reservation / lot id is encrypted
- Qty is int or decimal string — never float
- `commit` and `release` both exist; `quote` does not persist
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `quote` / `reserve` / `commit` / `release`
