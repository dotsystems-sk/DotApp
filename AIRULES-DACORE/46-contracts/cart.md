# 46 — `cart` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (Shop, POS) and a cart pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `cart` |
| `extra2` | `v1` |
| `extra3` | `session` \| `api` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit unused) |

```php
'extra1' => 'cart',
'extra2' => 'v1',
'extra3' => 'session',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'cart', 'v1');
$session = DotApp::call('DACore:Plugins@listByContract!', 'cart', 'v1', 'session');
```

| extra3 | Meaning |
|--------|---------|
| `session` | Cart lives in **DSM** under the **host** module name. **MUST** `DSM::use($hostModule)` — **MUST NOT** `$_SESSION` / `session_start()` |
| `api` | Headless / remote cart identified by `cart_ref`. Still no `$_SESSION` |

**Kind:** peer. **Controller:** `{Module}:CartContract@…!`

The **host** (Shop) **MUST NOT** set `extra1=cart` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('cart','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CartContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

The host passes its **module name** (e.g. `Shop`) into `capabilities` context via `get` / `add` `$opts['host']` so `session` mode can `DSM::use('Shop')`.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CartContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'CartEngine',         // exact module name
    'modes' => ['session'],           // extra3 this pack actually implements
    'dsm' => true,                    // true when extra3=session
    'currencies' => ['EUR'],
]
```

**Failure:** `['ok' => false, 'message' => 'Cart is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Line prices / qty are **DECIMAL STRINGS** where money appears. **MUST NOT** `float`.

Line ids that leave PHP toward HTML **MUST** be `{{ enc(Pack.cart.line): $lineId }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

**Session:** `DSM::use($hostModule)` where `$hostModule` is the host that picked this pack. **MUST NOT** `$_SESSION` or `session_start()`. [20](../20-CACHE-LOGGER-SESSION.md).

### `get($opts)`

**Call:** `DotApp::call('{Module}:CartContract@get!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `host` | string | Host module name (`Shop`). Required for `session`. Charset `[A-Za-z0-9_]`. **MUST NOT** a sibling tour — the host passes its own name |
| `cart_ref` | string | Required for `api`. Pack token or ciphertext. `session` **MAY** omit (DSM key is host + visitor) |
| `visitor` | string | Optional bound visitor / user id token the **host** already resolved. Length ≤ 64. **MUST NOT** a raw email |

**Success:**

```php
[
    'ok' => true,
    'cart_ref' => '…pack token…',     // encrypt before HTML if shown
    'currency' => 'EUR',
    'lines' => [
        [
            'line_id' => '…pack token…', // encrypt before HTML
            'item_ref' => 'SKU-104',
            'qty' => '2',
            'amount' => '59.80',      // line total, decimal string
        ],
    ],
    'subtotal' => '59.80',            // decimal string
]
```

**Failure:** unknown host; decrypt fail; empty DSM miss **MAY** still be `ok:true` with `lines => []` (empty cart) — pack **MUST** document that. Corrupt store → `ok:false`.

### `add($itemRef, $qty, $opts)`

**Call:** `DotApp::call('{Module}:CartContract@add!', $itemRef, $qty, $opts)`

**Input:**

| Arg / key | Type | Meaning |
|-----------|------|---------|
| `$itemRef` | string | Host / catalog SKU or encrypted product id the pack decrypts. Length ≤ 64 |
| `$qty` | string \| int | Quantity `>` `0`. Int or decimal string (`'1'`, `'2.5'`). Invalid → `ok:false` |
| `$opts['host']` | string | Same as `get` |
| `$opts['cart_ref']` | string | Same as `get` (`api`) |
| `$opts['visitor']` | string | Same as `get` |
| `$opts['currency']` | string | Optional ISO-4217 on the pack whitelist |

Pack **MAY** call a **host-picked** catalog pack `CatalogContract@get!` to resolve price. It **MUST NOT** invent `extra1` or `glob` modules.

**Success:**

```php
[
    'ok' => true,
    'cart_ref' => '…pack token…',
    'line_id' => '…new or updated line token…', // encrypt before HTML
    'qty' => '2',
]
```

**Failure:** unknown item; qty ≤ 0; decrypt fail; DSM host missing → `ok:false`.

### `remove($lineId, $opts)`

**Call:** `DotApp::call('{Module}:CartContract@remove!', $lineId, $opts)`

**Input:**

| Arg / key | Type | Meaning |
|-----------|------|---------|
| `$lineId` | string | Line token or ciphertext |
| `$opts['host']` | string | Same as `get` |
| `$opts['cart_ref']` | string | Same as `get` (`api`) |

**Success:** `['ok' => true, 'cart_ref' => '…', 'line_id' => '…removed token…']`.

**Failure:** unknown / decrypt fail / line not on this cart → `ok:false`.

---

## 5. DSM and HTML ids

- `session`: `DSM::use($opts['host'])` then pack-owned keys (`cart.v1`, …). **MUST NOT** `$_SESSION`.
- `api`: persist in the pack’s `{lowercase_modulename}_*` tables with bindings — still no `$_SESSION`.
- `line_id` / `cart_ref` in `data-*` / views: `{{ enc(...) }}`.
- Host UI after add/remove: patch JSON + toast — **MUST NOT** `location.reload()`. [09](../09-DOTAPP-JS-AND-BRIDGE.md).

---

## 6. Hooks

`get` **MUST NOT** fire a hook. `add` / `remove` are ordinary cart edits — **MUST NOT** fire a hook on every add.

If the pack later **locks** a cart for checkout (handoff), that lock **MAY** fire `module.{mod}.cart_locked.hook` with `cart_ref` only — **MUST NOT** line dumps or visitor PII. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`basket`, `bag`, `minicart`)
- `$_SESSION` / `session_start()`
- `glob('app/modules')` or `include` the pack to discover it
- Put a plain `line_id` in HTML
- Use `float` for money
- Leak `getMessage()` or request bodies
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=cart` on the Shop **host**

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `session` uses `DSM::use($hostModule)` only
- Every public HTML line id is encrypted
- No `crcCheck()` on `capabilities` / `get` / `add` / `remove`
