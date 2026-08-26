# 46 — `coupon` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is **redeem a code** against an order. Amounts are decimal **strings**. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `coupon` |
| `extra2` | `v1` |
| `extra3` | `code` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'coupon',
'extra2' => 'v1',
'extra3' => 'code',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'coupon', 'v1');
```

| extra3 | Meaning |
|--------|---------|
| `code` | Single-code redeem |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:CouponContract@…!`

The **host** **MUST NOT** set `extra1=coupon` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('coupon','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CouponContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CouponContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PromoPack',
    'modes' => ['code'],
    'code_max' => 32,
]
```

**Failure:** `['ok' => false, 'message' => 'Coupons are not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Public redeem **MUST** `throttle()`. Compare stored secrets with `hash_equals`.

### `redeem($opts)`

**Call:** `DotApp::call('{Module}:CouponContract@redeem!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `code` | string | Posted code. Original (`data(true)`). Length ≤ `code_max`. Charset `[A-Za-z0-9._-]` after trim |
| `order_ref` | string | Encrypted host order / cart id |

**Success:**

```php
[
    'ok' => true,
    'discount' => '5.00',             // decimal string, not float
    'currency' => 'EUR',
    'redemption_id' => '…ciphertext…',
]
```

**Failure:**

```php
['ok' => false, 'message' => 'This code cannot be used.']
```

**Same** message for unknown, expired, or already used. **MUST NOT** echo a valid code, confirm it exists, or leak `getMessage()`.

PHP **MUST** re-check the code and order totals. Frontend “applied” is UX only.

Ids in HTML **MUST** be encrypted.

---

## 5. Hooks

Fire after a stored redemption — **not** on a failed guess.

| Event | When | Payload |
|-------|------|---------|
| `module.{mod}.coupon_redeemed.hook` | Code applied | `redemption_id`, `order_ref`, `discount`, `currency` |

**MUST NOT** put the code in the payload. Document in `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 6. MUST NOT

- Invent `extra1` (`promo`, `voucher`, `discount`)
- Float money
- Echo valid codes
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `hash_equals` + throttle
- Decimal strings
- Hooks named in `.hooks` if fired
- No `crcCheck()` on these helpers
