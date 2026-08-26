# 46 — `tax` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (Shop, ERP, invoice) and a tax pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `pricing` (list / promo) and **not** `invoice` (document store).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `tax` |
| `extra2` | `v1` |
| `extra3` | `percent` \| `rules` \| `external` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit unused) |

```php
'extra1' => 'tax',
'extra2' => 'v1',
'extra3' => 'percent',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'tax', 'v1');
$rules = DotApp::call('DACore:Plugins@listByContract!', 'tax', 'v1', 'rules');
```

| extra3 | Meaning |
|--------|---------|
| `percent` | One operator rate (or a tiny fixed table). `quote` applies that rate |
| `rules` | Destination / product-class matrix inside the pack |
| `external` | Remote calculator the pack owns. Reply shape is still this page. Secret stays in the pack |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent `vat` / `gst` as `extra5` |

**Kind:** peer. **Controller:** `{Module}:TaxContract@…!`

The **host** (Shop, ERP) **MUST NOT** set `extra1=tax` on itself. Checkout **MAY** call this controller **after** the host picked a tax module — **MUST NOT** invent `extra1`.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('tax','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':TaxContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:TaxContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'VatRules',           // exact module name
    'modes' => ['rules'],             // extra3 this pack actually implements
    'currencies' => ['EUR'],          // ISO-4217 line amounts this pack accepts
    'inclusive' => false,             // true when line amounts already include tax
    'need_dest' => true,              // false for a single percent with no zones
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Tax is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()` or leak provider URLs.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Line amounts, `tax_amount`, and `rate` are **DECIMAL STRINGS**. **MUST NOT** `float`.

`rate` is a fraction of one (`'0.20'` = 20 %), not `'20'`. When lines use mixed rates, `rate` is the **effective** blended fraction the pack computed (still a decimal string).

Growing rules tables: prefetch + keyed map — **MUST NOT** `all()` then filter, **MUST NOT** query inside `foreach` of lines.

### `quote($lines, $dest)`

**Call:** `DotApp::call('{Module}:TaxContract@quote!', $lines, $dest)`

**Input** `$lines` array of line rows (1..200). Empty → `ok:false`. **MUST NOT** `all()` a catalog to build this — the **host** passes only the current cart / invoice lines.

| Key | Type | Meaning |
|-----|------|---------|
| `amount` | string | Line net (or gross when `inclusive`). Decimal string, `>=` `0` |
| `qty` | string | Quantity, decimal string (`'1'`, `'2.5'`). Invalid → `'1'` |
| `tax_class` | string | Optional class token (`standard`, `reduced`, `zero`). Whitelist in the pack. Unknown → `ok:false` when `extra3=rules` |
| `item_ref` | string | Optional host SKU / product token. Length ≤ 64. Pack **MUST NOT** query the host catalog unless `item_ref` is on a pack-owned table |

**Input** `$dest` array:

| Key | Type | Meaning |
|-----|------|---------|
| `country` | string | ISO-3166-1 alpha-2. Required when `need_dest` is true |
| `postal` | string | Optional postal. Charset `[A-Za-z0-9 -]`, length ≤ 16 |
| `region` | string | Optional region code. Length ≤ 16 |
| `vat_id` | string | Optional buyer VAT id. Length ≤ 32. **MUST NOT** appear in hooks |

When `need_dest` is false, `$dest` **MAY** be `[]`.

**Success:**

```php
[
    'ok' => true,
    'tax_amount' => '3.98',           // decimal string, total tax for all lines
    'rate' => '0.20',                 // effective fraction (decimal string)
    'currency' => 'EUR',              // same as line currency; host MUST pass one currency
    'inclusive' => false,
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not calculate tax.',
]
```

Empty lines; more than 200 lines; mixed currencies the pack refuses; unknown country / tax_class; external timeout. **MUST NOT** leak `getMessage()`, VAT id, street, or provider status.

The host **MUST** re-check totals in PHP before persist (checkout / invoice). A client-sent `tax_amount` is UX only.

---

## 5. Amounts and destination (**MUST**)

- Compare money with `bccomp` or integer cents — **MUST NOT** `(float)`.
- Host **MUST** pass one currency that is on `capabilities()['currencies']`.
- `quote` is a calculation — **MUST NOT** fire a hook on every quote.
- Destination in `$dest` stays with the host / pack store. Hooks (if any later persist) use ids only.
- `vat_id` **MUST NOT** appear in replies beyond what the host already sent, in logs, or on the hook bus.

---

## 6. External mode and HTTP

When `extra3=external`, the pack holds the calculator URL and secret in **its** config. Those values **MUST NOT** appear in `about.php` extras, `capabilities()`, `quote` replies, or hooks.

A failed remote call reports `dotapp.catch` through the pack helper and returns `ok:false` with product copy.

Contract helpers have **no CRC**. Host checkout POST stays on `/api/v1/auth/{Host}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again.

---

## 7. Host persist (**MUST**)

Skipping a frontend overlay **MUST** still fail if the host does not re-`quote` in PHP before writing an order / invoice.

v1 has **no** `TaxContract@list`. Operator rate / zone tables (if any) are pack admin, paged with `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)).

Tax class picker = native `<select>` of the pack whitelist — **MUST NOT** a free-text class box for a known set.

Host **MUST** call `quote` in-process after pick:

```php
$reply = DotApp::call($module . ':TaxContract@quote!', $lines, $dest);
if (!is_array($reply) || empty($reply['ok'])) {
    // toast / field error — MUST NOT leak VAT id
}
```

---

## 8. Hooks

`quote` **MUST NOT** fire a hook.

If the pack later **persists** a filed return / jurisdiction lock (not v1 `quote`), that persist **MAY** fire:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.tax_filed.hook` | Filed return / lock stored | `quote_id` / counts only |

**MUST NOT** put VAT id, address, or line amounts in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`vat`, `gst`, `sales-tax`)
- `glob('app/modules')` or `include` the pack to discover it
- Use `float` for money or rate
- Leak `getMessage()`, VAT id, or request bodies
- Trust a browser-sent tax total without a new `quote`
- `all()` a growing rules table per line inside `foreach` — prefetch + keyed map
- Fire a hook on every `quote`
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=tax` on the Shop **host**

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `tax_amount` and `rate` are decimal strings
- Host PHP re-quotes before persist
- Every method has input table + success/fail PHP arrays
- No hook on `quote`; `tax_filed` named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `quote`
