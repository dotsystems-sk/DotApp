# 46 — `shipping` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (Shop, ERP) and a shipping pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `shipping` |
| `extra2` | `v1` |
| `extra3` | `courier` \| `pickup` \| `rate` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit unused) |

```php
'extra1' => 'shipping',
'extra2' => 'v1',
'extra3' => 'courier',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'shipping', 'v1');
$courier = DotApp::call('DACore:Plugins@listByContract!', 'shipping', 'v1', 'courier');
```

| extra3 | Meaning |
|--------|---------|
| `courier` | Rates + create shipment + label + track |
| `pickup` | Store / locker pickup. `rates` may return a single free or fixed row. `label` **MAY** return `ok:false` |
| `rate` | Quote only. `createShipment` / `label` / `track` **MUST** return `ok:false` with product copy |

**Kind:** peer. **Controller:** `{Module}:ShippingContract@…!`

The **host** (Shop, ERP) **MUST NOT** set `extra1=shipping` on itself. A separate `label` pack (`extra1=label`) prints shelf / shipping labels after the host picks it — **MUST NOT** invent `extra1`.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('shipping','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ShippingContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ShippingContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'ShipFast',           // exact module name
    'modes' => ['courier'],           // extra3 this pack actually implements
    'currencies' => ['EUR'],          // ISO-4217 for rate prices
    'label' => true,                  // false when extra3=rate or pickup without labels
    'track' => true,
    'create_shipment' => true,        // false when extra3=rate
]
```

**Failure:** `['ok' => false, 'message' => 'Shipping is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Money and weights in replies are **DECIMAL STRINGS**. **MUST NOT** `float`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Pack.shipment.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

Growing event / shipment lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `rates($dest, $parcels)`

**Call:** `DotApp::call('{Module}:ShippingContract@rates!', $dest, $parcels)`

**Input** `$dest` array (destination — **no** full address dump in later hooks):

| Key | Type | Meaning |
|-----|------|---------|
| `country` | string | ISO-3166-1 alpha-2 (`SK`, `DE`). Whitelist / length 2 |
| `postal` | string | Postal code. Charset `[A-Za-z0-9 -]`, length ≤ 16 |
| `region` | string | Optional region / state code. Length ≤ 16 |
| `city` | string | Optional city for zone rules. Length ≤ 80. **MUST NOT** required when postal + country suffice |

**Input** `$parcels` array of parcel rows (1..20). Empty → `ok:false`.

| Key | Type | Meaning |
|-----|------|---------|
| `weight` | string | Kilograms, decimal string (`'1.250'`) |
| `length` | string | Optional cm, decimal string |
| `width` | string | Optional cm, decimal string |
| `height` | string | Optional cm, decimal string |
| `qty` | int | Piece count ≥ 1. Invalid → 1 |

**Success:**

```php
[
    'ok' => true,
    'rates' => [
        [
            'rate_id' => '…pack token…', // encrypt before HTML if shown
            'service' => 'standard',
            'label' => 'Standard 2–3 days',
            'amount' => '4.90',          // decimal string
            'currency' => 'EUR',
            'days_min' => 2,
            'days_max' => 3,
        ],
    ],
]
```

**Failure:** unknown country, illegal postal, empty parcels, `rate` mode with no table → `ok:false`. **MUST NOT** echo a street address in `message`.

### `createShipment($opts)`

**Call:** `DotApp::call('{Module}:ShippingContract@createShipment!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `rate_id` | string | Token from `rates` (or ciphertext). Decrypt `false` → `ok:false` |
| `order_ref` | string | Host order token. Charset `[A-Za-z0-9._-]`, length ≤ 64 |
| `dest` | array | Same keys as `rates` `$dest`. Pack stores it; hooks **MUST NOT** repeat it |
| `parcels` | array | Same shape as `rates` `$parcels` |
| `pickup_point` | string | Optional locker / store id when `extra3=pickup` |

**Success:**

```php
[
    'ok' => true,
    'shipment_id' => '…pack-stable token…', // encrypt before HTML
]
```

**Failure:** `extra3=rate`; unknown `rate_id`; dest escape / illegal country → `ok:false`.

### `label($shipmentId)`

**Call:** `DotApp::call('{Module}:ShippingContract@label!', $shipmentId)`

**Input:** `$shipmentId` string (token or ciphertext).

**Success** (one of `url` or `pdf_ref` **MUST** be non-empty):

```php
[
    'ok' => true,
    'shipment_id' => '…same token…',
    'url' => '/assets/modules/ShipFast/labels/…', // public assets URL, or ''
    'pdf_ref' => '…pack token…',                  // resolve inside the pack; encrypt in HTML
]
```

`url` **MUST** be under `/assets/modules/{Module}/…` or an https URL the pack owns. Runtime paths **MUST NOT** appear.

**Failure:** `extra3=rate`; pickup without labels; unknown / decrypt fail; label not ready → `ok:false` (no guessed URL).

### `track($shipmentId)`

**Call:** `DotApp::call('{Module}:ShippingContract@track!', $shipmentId)`

**Input:** `$shipmentId` string (token or ciphertext).

**Success:**

```php
[
    'ok' => true,
    'shipment_id' => '…same token…',
    'status' => 'in_transit',         // created | labeled | in_transit | delivered | exception | cancelled
    'events' => [
        [
            'at' => '2026-08-25T12:00:00+00:00', // ISO-8601
            'code' => 'accepted',
            'label' => 'Accepted at depot',
        ],
    ],
]
```

When the event list can grow, return the latest page only (cap, e.g. 20) — **MUST NOT** `all()` a growing track table.

**Failure:** `extra3=rate`; unknown / decrypt fail → `ok:false`.

---

## 5. Addresses and HTML ids

- Host **MAY** pass a street line **into** `createShipment` `$opts['dest']['street']` (optional, length ≤ 160) when the courier needs it. Hooks and `track` events **MUST NOT** dump the address — use `shipment_id`.
- `shipment_id` / `rate_id` / `pdf_ref` in HTML: `{{ enc(...) }}`. Decrypt `false` → reject.
- Label download HTTP on the pack uses `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` — **not** CRC on `ShippingContract@…!`.

---

## 6. Hooks

Fire only after a useful persist — **not** on `rates` or `track`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.shipment_created.hook` | Shipment row stored | `shipment_id`, `order_ref` |
| `module.{mod}.shipment_labeled.hook` | Label persisted | `shipment_id` |
| `module.{mod}.shipment_delivered.hook` | Terminal delivered stored | `shipment_id` |

**MUST NOT** put street, city, postal, phone, or recipient name in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`carrier`, `delivery`, `ship`)
- `glob('app/modules')` or `include` the pack to discover it
- Dump addresses in hooks or `message`
- Leak `getMessage()`, raw disk paths, or request bodies
- Return a public URL for `app/runtime`
- `all()` a growing shipments / events table
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=shipping` on the Shop **host**

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML shipment / rate id is encrypted
- Money and weights are decimal strings
- Hooks use `shipment_id` only — no address dump
- No `crcCheck()` on `capabilities` / `rates` / `createShipment` / `label` / `track`
