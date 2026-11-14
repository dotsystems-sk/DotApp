# 46 — `payment` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a payment pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `payment` |
| `extra2` | `v1` |
| `extra3` | `card` \| `bank` \| `wallet` \| `cash` \| `cod` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit unused) |

```php
'extra1' => 'payment',
'extra2' => 'v1',
'extra3' => 'card',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'payment', 'v1');
$card = DotApp::call('DACore:Plugins@listByContract!', 'payment', 'v1', 'card');
```

| extra3 | Meaning |
|--------|---------|
| `card` | Card / hosted-fields / redirect gateway. `createPayment` often returns `redirect_url` |
| `bank` | Bank transfer / SEPA / wire. `redirect_url` may be empty; `status` stays `pending` until capture |
| `wallet` | Wallet / instant app (not a DACore SMS/email sender). May return `redirect_url` |
| `cash` | In-person cash. No redirect. `capture` marks collected |
| `cod` | Cash on delivery. No redirect. `capture` after delivery |

**Kind:** peer. **Controller:** `{Module}:PaymentContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=payment` on itself. Checkout / cart packs **MUST NOT** invent a second role (`pay`, `psp`, `gateway`) — they call this controller **after** the host picked a payment module.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('payment','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PaymentContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

A checkout pack **MAY** call `{Payment}:PaymentContract@createPayment!` only after that host pick. It **MUST NOT** invent `extra1`.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PaymentContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'CardPay',            // exact module name
    'modes' => ['card'],              // extra3 this pack actually implements
    'currencies' => ['EUR', 'USD'],   // ISO-4217 whitelist this pack accepts
    'partial_capture' => true,
    'partial_refund' => true,
    'redirect' => true,               // false for cash / cod
    'capture_immediate' => false,     // true when createPayment already captures
]
```

**Failure:** `['ok' => false, 'message' => 'Payment is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Amounts and rates are **DECIMAL STRINGS** (`'19.99'`, `'0.00'`). **MUST NOT** `float` / `double` in arguments or replies.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Pack.payment.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

Currency codes **MUST** be ISO-4217 (exactly three ASCII letters) and on the pack whitelist from `capabilities()`. Unknown code → `ok:false`.

### `createPayment($orderRef, $amount, $currency, $returnUrls)`

**Call:** `DotApp::call('{Module}:PaymentContract@createPayment!', $orderRef, $amount, $currency, $returnUrls)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$orderRef` | string | Host order / ticket token (not PAN). Charset `[A-Za-z0-9._-]`, length ≤ 64. Empty → `ok:false` |
| `$amount` | string | Gross to collect. Decimal string, `>` `0`. **MUST NOT** float |
| `$currency` | string | ISO-4217 on the pack whitelist |
| `$returnUrls` | array | Host-owned https URLs (see table). **MUST NOT** put user input or secrets in the query |

`$returnUrls` keys:

| Key | Type | Meaning |
|-----|------|---------|
| `success` | string | Host URL after the payer finishes (card / wallet redirect) |
| `cancel` | string | Host URL if the payer aborts |
| `fail` | string | Host URL on gateway decline |
| `notify` | string | Optional pack webhook the pack already registered. Omit when unused |

`cash` / `cod`: `success` / `cancel` / `fail` may be empty strings. Pack **MUST NOT** invent a redirect.

**Success:**

```php
[
    'ok' => true,
    'payment_id' => '…pack-stable token…', // encrypt before HTML
    'redirect_url' => 'https://pay.example/session/…', // omit or '' when cash / cod
]
```

**Failure:** bad amount, unknown currency, duplicate `$orderRef` the pack refuses, gateway down → `ok:false`. **MUST NOT** leak PAN, CVV, gateway tokens, or `getMessage()`.

### `capture($paymentId, $opts)`

**Call:** `DotApp::call('{Module}:PaymentContract@capture!', $paymentId, $opts)`

**Input** `$paymentId`: pack token or ciphertext the pack decrypts.

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `amount` | string | Optional partial capture. Decimal string. Omit = remaining authorized |
| `currency` | string | Optional. When set, **MUST** match the payment’s currency |

**Success:**

```php
[
    'ok' => true,
    'payment_id' => '…same token…',
    'status' => 'captured',           // captured | authorized (partial left)
    'amount' => '19.99',              // captured this call (decimal string)
    'currency' => 'EUR',
]
```

**Failure:** unknown / decrypt fail; already captured (unless partial left); `partial_capture` false and amount ≠ full; cash/cod before the operator marked collected → `ok:false`.

### `refund($paymentId, $amount, $currency)`

**Call:** `DotApp::call('{Module}:PaymentContract@refund!', $paymentId, $amount, $currency)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$paymentId` | string | Same as `capture` |
| `$amount` | string | Refund this call. Decimal string, `>` `0`, ≤ captured remainder |
| `$currency` | string | ISO-4217. **MUST** match the payment |

**Success:**

```php
[
    'ok' => true,
    'payment_id' => '…same token…',
    'status' => 'refunded',           // refunded | captured (partial left)
    'amount' => '5.00',
    'currency' => 'EUR',
]
```

**Failure:** not captured; amount too large; `partial_refund` false and amount ≠ captured; decrypt fail → `ok:false`.

### `status($paymentId)`

**Call:** `DotApp::call('{Module}:PaymentContract@status!', $paymentId)`

**Input:** `$paymentId` string (token or ciphertext).

**Success:**

```php
[
    'ok' => true,
    'payment_id' => '…same token…',
    'status' => 'pending',            // pending | authorized | captured | refunded | failed | cancelled | expired
    'amount' => '19.99',              // original create amount (decimal string)
    'captured' => '0.00',
    'refunded' => '0.00',
    'currency' => 'EUR',
    'order_ref' => 'ORD-10041',
]
```

**Failure:** unknown / decrypt fail → `ok:false`.

---

## 5. Amounts, HTML ids, HTTP

- Amounts / captured / refunded / rates: **strings**. Compare with `bccomp` or integer cents in PHP 7.4 — **MUST NOT** `(float)`.
- `payment_id` in a view, `data-*`, or pager: `{{ enc(Pack.payment.id): $id }}`. Decrypt `false` → reject.
- Gateway return / webhook HTTP on the pack uses `/api/v1/auth|noauth/{Module}/…`. CRC: `#DACore:AuthTest@CRC!` or `@LoginAndCRC!` on that route — **not** on `PaymentContract@…!`.
- **MUST NOT** put PAN, CVV, track data, or gateway session secrets in replies, hooks, logs, or HTML.

---

## 6. Hooks

Fire only after a useful persist — **not** on `status` or `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.payment_captured.hook` | Capture (full or last partial) persisted | `id` (payment_id), `amount` (decimal string), `currency` |
| `module.{mod}.payment_refunded.hook` | Refund persisted | `id`, `amount`, `currency` |
| `module.{mod}.payment_failed.hook` | Terminal decline stored | `id`, `order_ref` |

**MUST NOT** put PAN, CVV, card expiry, gateway tokens, redirect URLs with secrets, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`pay`, `psp`, `gateway`, `stripe`)
- `glob('app/modules')` or `include` the pack to discover it
- Use `float` for money
- Leak `getMessage()`, PAN, CVV, or gateway tokens
- Put a plain `payment_id` in HTML
- `all()` a growing payments table to find one row
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=payment` on `<Host>`

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML `payment_id` is encrypted
- Amounts are decimal strings
- Hooks named in `.hooks` if fired — `payment_captured` has `id`, `amount`, `currency` only
- No `crcCheck()` on `capabilities` / `createPayment` / `capture` / `refund` / `status`
