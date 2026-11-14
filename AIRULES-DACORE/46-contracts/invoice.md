# 46 — `invoice` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and an invoice **pack** must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `invoice` |
| `extra2` | `v1` |
| `extra3` | `sales` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'invoice',
'extra2' => 'v1',
'extra3' => 'sales',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'invoice', 'v1');
$sales = DotApp::call('DACore:Plugins@listByContract!', 'invoice', 'v1', 'sales');
```

| extra3 | Meaning |
|--------|---------|
| `sales` | Customer / sales invoices (`create` + `get`). **MUST NOT** invent `purchase` / `credit-note` as extra3 in v1 |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1 |

**Kind:** peer. **Controller:** `{Module}:InvoiceContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=invoice` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('invoice','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':InvoiceContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:InvoiceContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SalesInvoices',       // exact module name
    'modes' => ['sales'],
    'families' => ['shop', 'erp'],
    'currency' => 'EUR',
    'amount_scale' => 2,
    'lists' => true,                   // pack admin / host browse exists
    'buyer_kinds' => ['contact', 'user', 'token'],
]
```

**Failure:** `['ok' => false, 'message' => 'Invoicing is not ready.']` — product copy, no `getMessage()`.

If `lists` is `true` (or the pack otherwise exposes an invoice list), that list **MUST** paginate with `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)). **MUST NOT** `all()`. v1 has **no** `InvoiceContract@list` — browse is pack/host admin, not this controller.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Invoice.doc.id): $invoiceId }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

Money **MUST** be decimal **strings**. Scale = `amount_scale` from `capabilities()` (default `2`). **MUST NOT** persist `float`.

### `create($buyerRef, $lines)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$buyerRef` | string | Encrypted contact / user id **or** pack-stable buyer token. Empty → `ok:false` |
| `$lines` | array | Non-empty list of line arrays (below) |

**Line keys:**

| Key | Type | Meaning |
|-----|------|---------|
| `sku` | string | Optional product token. Max 64, charset `[A-Za-z0-9._-]` |
| `name` | string | Line label. Escape before any view. Max 190 |
| `qty` | string | Decimal string, `> 0` |
| `unit_amount` | string | Decimal string, `>= 0` |
| `tax_rate` | string | Optional decimal string |

**MUST NOT** accept `invoice_id`, `total`, PAN, or a rights blob on a line.

If `$buyerRef` decrypts to a **user** id (`buyer_kinds` includes `user`): follow [42](../42-DACORE-USER-ORIGIN.md) — join `dacore_users_profiles`, bind the pack’s exact origin, `origin_id` `> 0`, **never** treat `dacore.legacy` as allowed. Mismatch / missing profile → generic `ok:false`. **MUST NOT** expose a foreign-origin buyer. `UserPolicy@findByExtra` is **not** authorization.

**Success:**

```php
[
    'ok' => true,
    'invoice_id' => '…ciphertext or pack-stable token…',
]
```

Pack **MAY** also return `'total' => '120.00'` (decimal string). Hosts **MUST** accept a reply that only has `ok` + `invoice_id`.

**Failure:** bad buyer; empty lines; bad amounts; rights → `ok:false`. **MUST NOT** persist a draft on failure.

### `get($id)`

**Input:** encrypted id or pack-stable token (string).

**Success:**

```php
[
    'ok' => true,
    'invoice_id' => '…same id family…',
    'buyer_ref' => '…encrypted or token…',
    'status' => 'open',                // whitelist: open | paid | void
    'currency' => 'EUR',
    'total' => '120.00',
    'tax_total' => '20.00',
    'lines' => [
        [
            'sku' => 'SKU-1',
            'name' => 'Hourly support',
            'qty' => '2',
            'unit_amount' => '50.00',
            'line_total' => '100.00',
        ],
    ],
]
```

Line `name` is display copy — host **MUST** escape it before `{{ var: }}`.

**Failure:** decrypt fail / unknown / gone / not owned → `ok:false` (same generic message). **MUST NOT** leak whether the row exists for another owner.

---

## 5. Amounts and ids

- Totals, tax, qty, unit amounts: decimal **strings** at `amount_scale`.
- HTML invoice ids: `{{ enc(...) }}` unique `$key2`. Decrypt `=== false` → reject.
- Growing invoice tables: `COUNT` + `LIMIT` on any list the pack ships (`lists` in `capabilities()`).
- **MUST NOT** put user input into SQL. Bound `LIKE` only if the pack searches.

---

## 6. Hooks

Fire only after a useful persist — **not** on `get`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.invoice_created.hook` | Invoice persisted | `invoice_id`, `line_count`, `total` (string), `currency` |

**MUST NOT** put buyer email, line text dumps, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`billing`, `invoices`, `sales-invoice`)
- Invent extra3 `purchase` / `proforma` in v1
- `glob('app/modules')` or `include` the pack to discover it
- Return `float` amounts
- Leak `getMessage()`, PAN, secrets, or request bodies
- `all()` on a growing invoice table
- Cross-origin buyer lookup when `$buyerRef` is a user id ([42](../42-DACORE-USER-ORIGIN.md))
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML invoice / buyer id is encrypted
- Amounts are decimal strings
- If `lists` is true, the browse list is paged (`COUNT` + `LIMIT`)
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `create` / `get`
