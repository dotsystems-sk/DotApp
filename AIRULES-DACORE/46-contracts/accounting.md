# 46 — `accounting` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

An ERP / Shop **host** and a ledger **pack** must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `invoice` (sales documents) and **not** `report` / `bi` (tables / charts).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `accounting` |
| `extra2` | `v1` |
| `extra3` | `ledger` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'accounting',
'extra2' => 'v1',
'extra3' => 'ledger',
'extra4' => 'erp',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'accounting', 'v1');
$ledger = DotApp::call('DACore:Plugins@listByContract!', 'accounting', 'v1', 'ledger');
```

| extra3 | Meaning |
|--------|---------|
| `ledger` | Double-entry general ledger: `post` balanced journals, `balance` by account |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `ifrs` / `gaap` here |

**Kind:** peer. **Controller:** `{Module}:LedgerContract@…!`

The **host** **MUST NOT** set `extra1=accounting` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('accounting','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':LedgerContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:LedgerContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'LedgerPack',          // exact module name
    'modes' => ['ledger'],
    'families' => ['erp'],
    'currency' => 'EUR',
    'amount_scale' => 2,
    'balance_sign' => 'debit_positive', // debit_positive | credit_positive
    'lists' => true,                    // journal / account browse in pack admin
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'The ledger is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`.

If `lists` is `true`, journal and account lists **MUST** paginate with `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)). v1 has **no** `LedgerContract@list`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Account ids that leave PHP toward HTML **MUST** be `{{ enc(Ledger.account.ref): $accountRef }}` with a unique `$key2`. Incoming encrypted refs: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights in PHP.

Amounts **MUST** be decimal **strings** at `amount_scale`. **MUST NOT** `float`. Growing `ledger_*` journals: **MUST NOT** `all()`.

### `post($entries)`

**Call:** `DotApp::call('{Module}:LedgerContract@post!', $entries)`

**Input** `$entries` — list of two or more entry arrays. **MUST** balance.

| Key | Type | Meaning |
|-----|------|---------|
| `account_ref` | string | Encrypted account id or pack-stable code (e.g. `4000`) |
| `debit` | string | Decimal string, `>= 0` |
| `credit` | string | Decimal string, `>= 0` |
| `memo` | string | Optional short note. Escape before any view. Max 190 |

**Line rules:**

- Exactly one of `debit` / `credit` is `> 0`; the other is `'0.00'` (or `'0'` normalized to scale). Both non-zero or both zero → `ok:false`.
- Sum of all `debit` strings **MUST** equal sum of all `credit` strings at `amount_scale`.
- Compare with integer minor units or `bccomp` (PHP 7.4). **MUST NOT** `==` on floats.

**Success:**

```php
[
    'ok' => true,
    'journal_id' => '…ciphertext or pack token…',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'The journal is not balanced.',
]
```

Unbalanced `$entries`, a single line, empty list, unknown `account_ref`, decrypt fail, rights. **Imbalance is never silent.** **MUST NOT** persist any line of that journal. Product copy **MAY** differ for rights (`You cannot post this journal.`) — **MUST NOT** leak SQL or `getMessage()`.

### `balance($accountRef)`

**Call:** `DotApp::call('{Module}:LedgerContract@balance!', $accountRef)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$accountRef` | string | Encrypted account id or pack-stable code |

**Success:**

```php
[
    'ok' => true,
    'account_ref' => '…same family…',
    'amount' => '1500.00',
]
```

`amount` is a signed decimal **string**. Sign follows `balance_sign` from `capabilities()` (`debit_positive` = debits increase the number).

**Failure:**

```php
[
    'ok' => false,
    'message' => 'This account is not available.',
]
```

Unknown / decrypt fail. **MUST NOT** invent `0.00` for a missing account. **MUST NOT** distinguish “no such account” vs “no rights” with different copy that enumerates the chart.

---

## 5. Balance math (**MUST**)

1. Normalize every amount to `amount_scale` before compare or store.
2. Reject scientific notation, thousands separators, and currency symbols.
3. Charset for raw amount strings: optional leading `-` on `balance` only; `post` sides are non-negative strings.
4. **MUST NOT** correct an imbalance by inserting a suspense line unless the host sent that line.
5. Growing `ledger_*` journals: index the columns used in `WHERE` / `ORDER BY`; comment names the query ([25](../25-PERFORMANCE-AND-CODE-QUALITY.md)).

Account picker = native `<select>` or paged `dotSelect2` of the pack chart — **MUST NOT** a bare text box that requires an exact remembered account code when the pack can list accounts.

---

## 6. Encrypted ids and lists

Journal and account ids in HTML: `{{ enc(...) }}` unique `$key2`. Decrypt `=== false` → reject.

If `lists` is `true`, journal / account browse **MUST** page (`COUNT` + `LIMIT`). **MUST NOT** `select('*')` on a growing journal list.

Memo text: host / pack **MUST** `htmlspecialchars` before `{{ var: }}`.

---

## 7. Host persist and HTTP

Host **MUST** call `post` in-process after the operator picked the pack. A client-sent `journal_id` or totals are UX only — PHP re-validates balance before persist.

Admin POST stays on `/api/v1/auth/{Host|Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again. Contract helpers have **no CRC**.

**MUST NOT** expose `post` on `/api/v1/noauth/…`.

`balance` is a read — **MUST NOT** fire a hook.

---

## 8. Hooks

Fire only after a useful persist — **not** on `balance`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.ledger_posted.hook` | Balanced journal persisted | `journal_id`, `entry_count`, `debit_total` (string) |

**MUST NOT** put memos, account names, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`gl`, `bookkeeping`, `finance`)
- Persist an unbalanced `post`
- Return `float` amounts or a silent `ok:true` on imbalance
- `glob('app/modules')` or `include` the pack to discover it
- Leak `getMessage()`, secrets, or request bodies
- `all()` on a growing journal table
- Invent `0.00` for a missing account on `balance`
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=accounting` on the host

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Account / journal ids in HTML are encrypted
- `post` refuses imbalance with `ok:false` and no write
- `balance` amount is a decimal string
- Every method has input table + success/fail PHP arrays
- If `lists` is true, browse lists are paged
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `post` / `balance`
