# 46 — `pricing` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a price-list pack must be able to interoperate from this page alone. Machine catalog: `DACore\Libraries\ExtraContracts` role `pricing`, controller `PricingContract`.

This role is **list / promo quotes**. Tax is `tax`. FX is `currency`. Capture is `payment`. Do not invent a second money float.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `pricing` |
| `extra2` | `v1` |
| `extra3` | `list` \| `promo` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'pricing',
'extra2' => 'v1',
'extra3' => 'list',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'pricing', 'v1');
$promo = DotApp::call('DACore:Plugins@listByContract!', 'pricing', 'v1', 'promo');
```

| extra3 | Meaning |
|--------|---------|
| `list` | Catalog / list price only. `quote` ignores promo codes in `$context` |
| `promo` | List price plus promotions. `$context['promo']` may apply; still no coupon **redeem** (that is `coupon`) |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** invent `b2b` / `tier` here (use `$context`) |

**Kind:** peer. **Controller:** `{Module}:PricingContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=pricing` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('pricing','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PricingContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PricingContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PriceKit',          // exact module name
    'modes' => ['list'],             // extra3 this pack actually implements
    'currencies' => ['EUR'],         // ISO 4217 uppercase; bounded list
    'scale' => 2,                    // decimal places for unit_price / total
    'supports_promo' => false,       // true when extra3=promo
]
```

**Failure:** `['ok' => false, 'message' => 'Pricing is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Amounts that leave PHP are **decimal strings** (`'19.90'`), never `float` / `double`. Qty follows `inventory`: `int` or decimal string, never float.

SKU charset `[A-Za-z0-9._-]`, length ≤ 64, bound in SQL.

### `quote($sku, $qty, $context)`

**Call:** `DotApp::call('{Module}:PricingContract@quote!', $sku, $qty, $context)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$sku` | string | Catalog SKU. Empty / illegal charset → `ok:false` |
| `$qty` | int \| string | Units to price. `0`, negative, or float → `ok:false` |
| `$context` | array | See table. Missing keys use pack defaults |

**`$context` keys** (all optional; whitelist only — **MUST NOT** spread `$request->data()`):

| Key | Type | Meaning |
|-----|------|---------|
| `currency` | string | ISO 4217. Unknown → `ok:false` or pack default if `capabilities` has one currency |
| `channel` | string | `web` \| `pos` \| `b2b` — whitelist |
| `group` | string | Price-group token (not a user email) |
| `country` | string | ISO 3166-1 alpha-2 |
| `promo` | string | Promo token when `extra3=promo`. **MUST NOT** a secret coupon code meant for `coupon` |

**Success:**

```php
[
    'ok' => true,
    'unit_price' => '19.90',         // decimal string, scale from capabilities
    'total' => '39.80',              // unit_price * qty as a decimal string
    'currency' => 'EUR',
    'promo_applied' => false,        // true only in promo mode when a promo changed the price
]
```

`total` **MUST** be the string the host can persist — not a float multiply in the host. Pack does the scale math (integer cents internally is fine; the reply is still a string).

Unknown SKU → `ok:false`. SKU exists but not priced in that currency/channel → `ok:false` with product copy (`'No price for this item.'`).

**Failure:** bad SKU, bad qty, unknown currency, pack not ready → `ok:false`.

---

## 5. Host cart (not HTTP on the contract)

`quote` is in-process and **MUST NOT** persist. The host stores the returned strings on the cart / order line.

Cart POST stays on the **host** `/api/v1/auth/{Host}/…` or public add-to-cart with `throttle()`. Contract methods have **no CRC**.

**MUST NOT** expose a public unthrottled “price probe” that dumps every SKU.

Tax is a later `tax` `quote`. **MUST NOT** bake VAT into `unit_price` unless `capabilities` documents that the list is gross (optional key `tax_included` => bool).

---

## 6. Admin / UI

Bounded pack choice = native `<select>` or `dotSelect2`. Currency and channel are bounded — **MUST NOT** a bare text box for a known ISO currency list.

No media picker. This role has no `picker_js`.

---

## 7. Money strings v1 (**MUST**)

- `unit_price` and `total` are strings matching `^[0-9]+(\.[0-9]{1,n})?$` with `n = scale`.
- **MUST NOT** `float`, `number_format` into a locale comma inside the contract reply (host UI may format for display).
- **MUST NOT** return cents as an undocumented int unless you also return the decimal strings above.
- Context **MUST NOT** include PAN, passwords, emails, or rights blobs.
- Tables `{lowercase_modulename}_*` ; index SKU + currency + channel (comment names the `quote` query).

---

## 8. Hooks

`quote` is a read. **MUST NOT** fire a hook on `quote`.

Fire only if the pack **persists** a list or promo change in its own admin (not on every host cart tick):

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.pricing_list_updated.hook` | Operator saved a list/promo row | `sku`, `currency` — **not** the price string if you treat it as sensitive; counts are enough |

Most packs fire **nothing** for v1 host `quote`. Document any event in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`prices`, `pricelist`, `promo-engine`)
- `glob('app/modules')` or `include` the pack to discover it
- Use `float` for money or qty
- Leak `getMessage()`, cost markup, or competitor lists
- `all()` on a growing price table then filter in PHP
- Redeem a coupon code here (`coupon` role)
- Set `extra1=pricing` on `<Host>`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `quote` returns `unit_price` and `total` as decimal strings
- `$context` is a whitelist, not request spread
- No hook on a bare `quote` unless a persist happened
- No `crcCheck()` on `capabilities` / `quote`
