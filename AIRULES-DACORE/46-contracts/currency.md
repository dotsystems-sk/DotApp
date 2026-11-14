# 46 — `currency` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a currency pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `pricing` (list / promo) and **not** `payment` (collect money).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `currency` |
| `extra2` | `v1` |
| `extra3` | `table` \| `feed` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit unused) |

```php
'extra1' => 'currency',
'extra2' => 'v1',
'extra3' => 'table',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'currency', 'v1');
$feed = DotApp::call('DACore:Plugins@listByContract!', 'currency', 'v1', 'feed');
```

| extra3 | Meaning |
|--------|---------|
| `table` | Operator-maintained rates inside the pack. Missing pair → `convert` `ok:false` |
| `feed` | Periodic / live feed the pack owns. Secret / API key stays in the pack. Timeout → `ok:false` |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a content-management host family |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`ecb`, `openexchangerates`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:CurrencyContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=currency` on itself. Checkout / invoice **MAY** call this controller **after** the host picked a currency module — **MUST NOT** invent `extra1` (`fx`, `forex`).

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('currency','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CurrencyContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CurrencyContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'FxTable',            // exact module name
    'modes' => ['table'],             // extra3 this pack actually implements
    'currencies' => ['EUR', 'USD', 'CZK'], // ISO-4217 whitelist — host MUST use this list
    'base' => 'EUR',                  // pack base; convert MAY go via base
    'decimals' => 2,                  // default scale for amount (int 0..4)
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Currency is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`, feed hostnames, or API keys.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

`$amount`, converted `amount`, and `rate` are **DECIMAL STRINGS**. **MUST NOT** `float` / `double`.

ISO-4217 **MUST** be a whitelist: exactly three ASCII letters, uppercase, and a member of `capabilities()['currencies']`. Host and pack **MUST** reject anything else (`eur`, `EURO`, `USD `, user SQL). **MUST NOT** interpolate `$from` / `$to` into SQL.

Ids that leave PHP toward HTML (if the pack stores pair rows) **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing rate tables: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `convert($amount, $from, $to)`

**Call:** `DotApp::call('{Module}:CurrencyContract@convert!', $amount, $from, $to)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$amount` | string | Source amount. Decimal string, `>=` `0`. Empty, scientific notation, thousands separators, or currency symbols → `ok:false` |
| `$from` | string | ISO-4217 on the pack whitelist (exactly three ASCII letters, uppercase) |
| `$to` | string | ISO-4217 on the pack whitelist |

Same `$from` and `$to`: success with `rate` `'1'` and the same amount (still a decimal string, pack scale from `decimals`).

**Success:**

```php
[
    'ok' => true,
    'amount' => '21.50',              // converted (decimal string)
    'rate' => '1.075',                // multiply source by this rate (decimal string)
    'from' => 'EUR',
    'to' => 'USD',
]
```

`rate` meaning: `$to_amount ≈ bcmul($amount, $rate, $scale)`. Pack documents scale (from `decimals`). Host **MUST NOT** re-float. Compare with `bccomp` or integer minor units — **MUST NOT** `(float)`.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not convert this amount.',
]
```

Unknown / lowercase / 4-letter code; empty amount; missing pair in `table`; feed timeout. **MUST NOT** leak feed keys, HTTP status, or `getMessage()`. **MUST NOT** persist on failure.

---

## 5. Amounts and ISO whitelist (**MUST**)

- Charset for `$amount`: optional digits + one `.` + fraction. No `-` on convert (amounts `>= 0`).
- Scale = `decimals` from `capabilities()` (int `0`…`4`). Pack normalizes before reply.
- Host picker for display currency: native `<select>` or `dotSelect2` of `currencies[]` — **MUST NOT** a bare text box for a known ISO list ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §3).
- Host **MUST** reject a code that is not on `currencies[]` before calling `convert`. Pack still re-checks.
- `base` is informational. Host **MUST NOT** assume every pair exists; missing pair is `ok:false`.

---

## 6. Feed HTTP and secrets

Feed refresh HTTP lives on the pack (`/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`). That route’s action **MUST NOT** `crcCheck()` again after the prefix. **No CRC** on `CurrencyContract@convert!`.

Provider URL and secret stay in **pack** config. **MUST NOT** appear in `about.php` extras, `capabilities()`, convert replies, or hooks.

**MUST NOT** `HttpHelper` a URL built from request data. A failed remote call reports `dotapp.catch` through the pack helper and returns `ok:false` with product copy.

---

## 7. Host checkout / invoice use

Host **MUST** call `convert` in-process after the operator picked the pack:

```php
$reply = DotApp::call($module . ':CurrencyContract@convert!', $amount, $from, $to);
if (!is_array($reply) || empty($reply['ok'])) {
    // toast / field error — MUST NOT leak $reply internals
}
```

A client-sent converted amount is UX only — PHP re-converts before persist.

**MUST NOT** expose `convert` on `/api/v1/noauth/…` as an open FX calculator without `throttle()`.

v1 has **no** `CurrencyContract@list`. Operator rate tables (if any) are pack admin, paged with `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)).

`table` mode: index the pair columns used in `WHERE` (`from`, `to`); one comment line names the `convert` lookup ([25](../25-PERFORMANCE-AND-CODE-QUALITY.md) §3).

A failed feed or table read reports `dotapp.catch` through the pack helper, then returns `ok:false` with product copy. The operator still sees a toast.

---

## 8. Hooks

`convert` **MUST NOT** fire a hook.

If the operator **persists** a table rate, that save **MAY** fire:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.fx_rate_saved.hook` | Operator rate row stored | `from`, `to` (ISO codes only) |

**MUST NOT** put API keys, rates as secrets, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`fx`, `forex`, `exchange`)
- Invent `extra3` (`ecb`, `live`) — v1 is `table` \| `feed` only
- `glob('app/modules')` or `include` the pack to discover it
- Accept a currency code that is not on the ISO-4217 whitelist
- Use `float` for amount or rate
- Leak `getMessage()` or feed secrets
- Put `$from` / `$to` into raw SQL
- Fire a hook on every `convert`
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=currency` on `<Host>`

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `amount` and `rate` are decimal strings
- Convert rejects codes outside `currencies[]`
- Every method has input table + success/fail PHP arrays
- No hook on `convert`; `fx_rate_saved` named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `convert`
