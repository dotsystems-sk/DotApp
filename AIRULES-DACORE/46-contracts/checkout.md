# 46 — `checkout` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a checkout pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `checkout` |
| `extra2` | `v1` |
| `extra3` | `session` \| `api` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit unused) |

```php
'extra1' => 'checkout',
'extra2' => 'v1',
'extra3' => 'session',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'checkout', 'v1');
$api = DotApp::call('DACore:Plugins@listByContract!', 'checkout', 'v1', 'api');
```

| extra3 | Meaning |
|--------|---------|
| `session` | Checkout draft in **DSM** under the **host** module name. **MUST** `DSM::use($hostModule)` — **MUST NOT** `$_SESSION` |
| `api` | Headless checkout identified by `checkout_id`. Still no `$_SESSION` |

**Kind:** peer. **Controller:** `{Module}:CheckoutContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=checkout` on itself.

Payment / tax / shipping / cart are **other** reserved roles. This pack **MAY** `DotApp::call` those contracts **only after** the host picked each module (`listByContract!`). **MUST NOT** invent `extra1` (`pay`, `psp`, `tax-engine`).

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('checkout','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CheckoutContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CheckoutContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'OnePage',            // exact module name
    'modes' => ['session'],           // extra3 this pack actually implements
    'dsm' => true,
    'uses_payment' => true,           // will call PaymentContract after host pick
    'uses_tax' => true,
    'uses_shipping' => false,
    'currencies' => ['EUR'],
]
```

**Failure:** `['ok' => false, 'message' => 'Checkout is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Totals are **DECIMAL STRINGS**. **MUST NOT** `float`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Pack.checkout.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

**PHP re-checks totals** on `complete` (and again before any payment call). Client-sent totals / tax / shipping are UX only — skipping the overlay **MUST** still fail on the server.

### `start($cartRef, $opts)`

**Call:** `DotApp::call('{Module}:CheckoutContract@start!', $cartRef, $opts)`

**Input:**

| Arg / key | Type | Meaning |
|-----------|------|---------|
| `$cartRef` | string | Cart token or ciphertext from the **host-picked** cart pack (`CartContract@get`) |
| `$opts['host']` | string | Host module name (`<Host>`). Required for `session`. `DSM::use($host)` |
| `$opts['visitor']` | string | Optional bound visitor token the host already resolved. Length ≤ 64 |
| `$opts['currency']` | string | Optional ISO-4217 on the pack whitelist |

The pack **MAY** call `{Cart}:CartContract@get!` using the host’s selected cart module name. **MUST NOT** invent `extra1=cart`.

**Success:**

```php
[
    'ok' => true,
    'checkout_id' => '…pack-stable token…', // encrypt before HTML
]
```

**Failure:** empty cart; decrypt fail; unknown host; cart locked elsewhere → `ok:false`.

### `complete($checkoutId, $opts)`

**Call:** `DotApp::call('{Module}:CheckoutContract@complete!', $checkoutId, $opts)`

**Input:**

| Arg / key | Type | Meaning |
|-----------|------|---------|
| `$checkoutId` | string | Token or ciphertext from `start` |
| `$opts['host']` | string | Same as `start` |
| `$opts['payment_module']` | string | Optional. Exact module name the **host already picked** via `listByContract!('payment','v1')`. Empty = host completes pay later |
| `$opts['return_urls']` | array | Optional. Same keys as [payment.md](payment.md) `createPayment` |
| `$opts['tax_module']` | string | Optional. Host-picked `tax` module name |
| `$opts['shipping_module']` | string | Optional. Host-picked `shipping` module name |
| `$opts['shipping_rate_id']` | string | Optional. Token from `ShippingContract@rates` |
| `$opts['dest']` | array | Optional dest for tax / shipping (country / postal). **MUST NOT** appear in hooks |

**MUST** in PHP before persist:

1. Load the checkout + cart (bindings only).
2. Re-sum line amounts (decimal strings / `bccomp`).
3. If `$opts['tax_module']` is set, `TaxContract@quote!` and compare — mismatch → `ok:false`.
4. If shipping is required, `ShippingContract@rates!` / stored `rate_id` — mismatch → `ok:false`.
5. Only then create the host order / call payment.

Payment call (when `$opts['payment_module']` is a picked name):

```php
$pay = DotApp::call(
    $opts['payment_module'] . ':PaymentContract@createPayment!',
    $orderRef,
    $amount,      // decimal string
    $currency,
    $returnUrls
);
```

**MUST NOT** invent `extra1`. **MUST NOT** `glob` for a payment module. If `createPayment` returns `ok:false`, `complete` returns `ok:false` (no order persist, or host-defined pending — pack **MUST** document one behavior and keep it).

**Success:**

```php
[
    'ok' => true,
    'order_ref' => 'ORD-10041',       // host / pack order token; encrypt before HTML if shown as an id
    'checkout_id' => '…same token…',
    'payment_id' => '',               // set when payment ran; encrypt before HTML
    'redirect_url' => '',             // from PaymentContract when present
]
```

**Failure:** totals mismatch; decrypt fail; unpaid when the pack requires payment; payment `ok:false` → `ok:false`. **MUST NOT** leak PAN, CVV, gateway tokens, or `getMessage()`.

---

## 5. Totals, DSM, payment handoff

- `session`: `DSM::use($opts['host'])`. **MUST NOT** `$_SESSION`. [20](../20-CACHE-LOGGER-SESSION.md).
- Re-check is **this** pack’s PHP — a JS overlay is UX only. [08](../08-FORMS-AND-SECURITY.md).
- Payment / tax / shipping module names come from **host settings**, not from request `data()` as a free module picker (whitelist against the persisted pick).
- `checkout_id` / `order_ref` / `payment_id` in HTML: `{{ enc(...) }}`.
- HTTP confirm / return on the pack: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` — **no** CRC on `CheckoutContract@…!`.

---

## 6. Hooks

`start` **MUST NOT** fire a hook.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.checkout_completed.hook` | Order persisted (after totals re-check) | `checkout_id`, `order_ref` |
| `module.{mod}.checkout_paid.hook` | Only if this pack saw `payment_captured` and stored it | `checkout_id`, `order_ref`, `payment_id` |

**MUST NOT** put PAN, addresses, cart lines, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`checkout-flow`, `onepage`, `pay`)
- Call a payment / tax / shipping pack the host did not pick
- `$_SESSION` / `session_start()`
- Trust client totals
- `glob('app/modules')` or `include` the pack to discover it
- Leak `getMessage()`, PAN, or gateway tokens
- Put a plain `checkout_id` in HTML
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=checkout` on `<Host>`

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `complete` re-checks totals in PHP
- Payment only via host-picked `PaymentContract` — no invented `extra1`
- Every public HTML checkout / order / payment id is encrypted
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `start` / `complete`
