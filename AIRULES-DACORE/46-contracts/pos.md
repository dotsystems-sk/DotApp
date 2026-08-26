# 46 — `pos` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A Shop / ERP **host** and a POS **pack** must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `pos` |
| `extra2` | `v1` |
| `extra3` | `retail` \| `hospitality` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'pos',
'extra2' => 'v1',
'extra3' => 'retail',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'pos', 'v1');
$retail = DotApp::call('DACore:Plugins@listByContract!', 'pos', 'v1', 'retail');
```

| extra3 | Meaning |
|--------|---------|
| `retail` | Counter / barcode ticket: SKU lines, qty, unit amount, then `tender` |
| `hospitality` | Open table / cover ticket: seat or cover on lines; same `ticket` / `tender` shape |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `kiosk` / `handheld` here |

**Kind:** peer. **Controller:** `{Module}:PosContract@…!`

The **host** (Shop, ERP) **MUST NOT** set `extra1=pos` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('pos','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PosContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Optional **payment** satellite (parent §5c / §8): a second `<select>` from `listByContract!('payment','v1')`, persisted in **host** settings. The POS pack **MAY** call that payment pack **only after** the host pick. Discovery of payment packs is the host’s job. **MUST NOT** `glob('app/modules')` or `include` a payment module to find it.

Discovery **MUST NOT** boot the POS pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PosContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'RetailPos',           // exact module name
    'modes' => ['retail'],             // extra3 this pack actually implements
    'families' => ['shop', 'erp'],
    'currency' => 'EUR',               // ISO 4217
    'amount_scale' => 2,               // decimal places for totals / tenders
    'tender_methods' => ['cash', 'card', 'wallet', 'bank', 'cod'],
    'tax_included' => true,
    'open_tickets' => false,           // hospitality often true (admin list MUST page)
    'payment_peer' => true,            // pack MAY call PaymentContract after host pick
]
```

**Failure:** `['ok' => false, 'message' => 'Point of sale is not ready.']` — product copy, no `getMessage()`.

If `open_tickets` is `true`, any admin / host list of open tickets **MUST** use `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)). v1 has **no** `PosContract@list`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Pos.ticket.id): $ticketId }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

Money **MUST** be decimal **strings** (`'19.90'`), never `float`. Scale = `amount_scale` from `capabilities()` (default `2`). **MUST NOT** add money with `+` on floats.

### `ticket($lines)`

**Input** `$lines` — non-empty list of line arrays:

| Key | Type | Meaning |
|-----|------|---------|
| `sku` | string | Retail product / barcode token. Empty allowed in hospitality open items. Max 64, charset `[A-Za-z0-9._-]` |
| `name` | string | Line label. **MUST** `htmlspecialchars` before any view. Max 190 |
| `qty` | string | Decimal string, `> 0` |
| `unit_amount` | string | Decimal string, `>= 0`, same scale |
| `tax_rate` | string | Optional decimal string (`'0.20'`). Omit = pack default |
| `seat` | string | Hospitality optional (table / cover). Empty in retail |

**MUST NOT** accept `id`, `total`, `ticket_id`, PAN, or a payment token on a line.

**Success:**

```php
[
    'ok' => true,
    'ticket_id' => '…ciphertext or pack-stable token…',
    'total' => '27.80',
]
```

`ticket_id` is what the host stores and later passes to `tender`. When the host renders it in HTML it **MUST** encrypt with a unique `$key2`.

**Failure:** empty `$lines`; bad qty / amount; unknown sku when the pack requires one; hospitality pack given a retail-only field it rejects → `ok:false`. **MUST NOT** persist a partial ticket on failure.

### `tender($ticket_id, $method, $amount)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$ticket_id` | string | Ciphertext or pack-stable token from `ticket` |
| `$method` | string | Whitelist only: values from `tender_methods` (`cash` \| `card` \| `wallet` \| `bank` \| `cod` — **MUST NOT** invent `stripe`) |
| `$amount` | string | Decimal string, `> 0`, same scale as `total` |

Decrypt fail / unknown ticket / already closed / method not in `tender_methods` / amount that would overpay without a documented cash-change rule → `ok:false`, no persist.

**Success:**

```php
[
    'ok' => true,
    'tender_id' => '…ciphertext or pack token…',
    'ticket_id' => '…same ticket…',
    'paid' => true,
    'remaining' => '0.00',
    'change' => '0.00',
]
```

Split tenders: `paid` is `false` while `remaining` `> 0`. Cash overpay **MAY** return `change` as a decimal string and still `paid` `true`. Card / wallet / bank **MUST NOT** silently keep an overpay.

---

## 5. Payment pack (optional)

`tender` **MAY** settle `card` / `wallet` / `bank` by calling a **payment** peer after the host stored that module name.

```php
$pay = DotApp::call(
    $paymentModule . ':PaymentContract@createPayment!',
    $ticketRef,
    $amount,
    $currency,
    $returnUrls
);
```

Shape: parent [§8](../46-DACORE-EXTRA-CONTRACTS.md). Amounts are decimal **strings**. Then `capture` / `status` as that pack requires. Hook on the **payment** pack is `payment_captured` (id, amount, currency) — **not** PAN or gateway tokens.

**MUST:**

- Host pick first (`listByContract!('payment','v1')` + settings). Empty payment module → POS **MUST** refuse card / wallet / bank with product copy, or accept only `cash` / `cod`.
- Pass a ticket / order **ref**, not a card number.
- Treat payment `ok:false` as tender `ok:false`. **MUST NOT** mark the ticket paid.

**MUST NOT:** invent `extra1=pay`; call payment discovery from inside POS `capabilities()`; store PAN, CVV, or gateway raw replies in the POS ticket.

`cash` / `cod` **MAY** complete inside POS with no payment pack.

---

## 6. Hooks

Fire only after a useful persist — **not** on `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.pos_ticket_opened.hook` | Ticket row created | `ticket_id`, `line_count`, `total` (string), `mode` |
| `module.{mod}.pos_tendered.hook` | Tender persisted | `ticket_id`, `tender_id`, `method`, `amount` (string), `paid` |

**MUST NOT** put PAN, line names, SKUs as a dump, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`till`, `register`, `point-of-sale`)
- `glob('app/modules')` or `include` the pack (or a payment pack) to discover it
- Return `float` totals or tender amounts
- Leak `getMessage()`, PAN, OTP, or request bodies
- `all()` on a growing open-ticket table
- Put a plaintext numeric ticket id in HTML (`value="7"`)
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML ticket / tender id is encrypted
- Amounts are decimal strings at `amount_scale`
- Payment peer only after host pick; no PAN
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `ticket` / `tender`
