# 46 — `hr` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6. Linked users: [42](../42-DACORE-USER-ORIGIN.md).

A host (ERP, Shop) and an employee-directory pack must be able to interoperate from this page alone. Machine catalog: `DACore\Libraries\ExtraContracts` role `hr`, controller `HrContract`.

v1 is **directory lookup**. Payroll, contracts, and national-id cards stay **inside** the pack UI, not this peer. **MUST NOT** return national IDs, salaries, or passwords.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `hr` |
| `extra2` | `v1` |
| `extra3` | `employees` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'hr',
'extra2' => 'v1',
'extra3' => 'employees',
'extra4' => 'erp',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'hr', 'v1');
$employees = DotApp::call('DACore:Plugins@listByContract!', 'hr', 'v1', 'employees');
```

| extra3 | Meaning |
|--------|---------|
| `employees` | Paged `find` by generic display name. Payroll / contracts stay in the pack admin |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `payroll` / `pii` here |

**Kind:** peer. **Controller:** `{Module}:HrContract@…!`

The **host** (ERP) **MUST NOT** set `extra1=hr` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('hr','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':HrContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:HrContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PeopleDir',           // exact module name
    'modes' => ['employees'],
    'families' => ['erp'],
    'page_size' => 20,                 // find() page length (1–100)
    'user_link' => false,              // internal only; MUST NOT appear on find rows
]
```

**Failure:** `['ok' => false, 'message' => 'The employee directory is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Employee ids that leave PHP toward HTML **MUST** be `{{ enc(PeopleDir.employee.id): $employeeId }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

Growing employee tables: `COUNT(*)` + `LIMIT` / `OFFSET` — **MUST NOT** `all()`.

### `find($q, $page)`

**Call:** `DotApp::call('{Module}:HrContract@find!', $q, $page)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$q` | string | Optional display-name filter. Bound `LIKE` on the **display name** column only. Empty = unfiltered page. **MUST NOT** `LIKE` national ids, salaries, or passwords |
| `$page` | int | 1-based. Invalid / `< 1` → `1` |

**Success — reply fields are only these:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',
            'display_name' => 'Jordan Example',
        ],
    ],
    'page' => 1,
    'last_page' => 5,
    'total' => 94,
]
```

`total` is `COUNT(*)` of the filtered set, not `count($items)`. Host pager chrome follows [40](../40-DACORE-LIST-PAGER.md) when this list is an admin result list.

**Generic display name only.** Host **MUST** `htmlspecialchars` `display_name` before `{{ var: }}`. JS fills names with `.text()`, not `.html()`.

**MUST NOT** return in this contract reply (item or top-level):

- National identifiers (SSN, birth number, passport, tax id)
- Salaries, bands, grades, bank / IBAN
- Passwords, hashes, TOTP, recovery codes
- Email, phone, home address, birth date
- `user_id` / origin token / rights
- Photos as bytes

A richer employee card is **pack admin**, behind pack rights — **not** `HrContract@find`.

**Failure:** query error (pack catch helper) → `ok:false`. Empty page is **success** with `items` `[]`, `total` `0`, `last_page` `1`.

v1 has **no** `get` / `save`. Hosts **MUST NOT** call invented extra methods.

---

## 5. Host HTTP (not on the contract)

`find` is in-process. **No CRC** on `HrContract@find!`.

If the host wraps `find` in an admin AJAX page, that POST lives on `/api/v1/auth/{Host}/…` + `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again. Encrypted `data-page` ([40](../40-DACORE-LIST-PAGER.md)). Overlay the list until the request ends.

**MUST NOT** expose `find` on `/api/v1/noauth/…`. An employee directory is not a public search.

---

## 6. Admin / UI

Pack choice = `<select>` / `dotSelect2`. Employee **result** lists paginate. A single-employee picker on a host form may use `dotSelect2` with server paging when the directory is large; opening it **MUST** show initial results. **MUST NOT** a bare text box that requires an exact remembered name.

No media picker. This role has no `picker_js`.

---

## 7. Identity, PII, and origin v1 (**MUST**)

- Employee ids in HTML are encrypted. Decrypt `false` → reject.
- Reply rows: `id` + `display_name` only. **MUST NOT** national IDs, salaries, or passwords (or hashes / TOTP).
- Tables `{lowercase_modulename}_*` ; index display name for the paged `find` query (comment names that query). **MUST NOT** write `dacore_*` / `users_rights*`.
- `$q` is bound. Sort column is a whitelist (`display_name`, `id`), never request-driven SQL.

If the pack **internally** links `{prefix}users.id` (the contract reply still **MUST NOT** include `user_id`):

- Follow [42](../42-DACORE-USER-ORIGIN.md) on every write and on the SQL that feeds `find`.
- INNER JOIN `dacore_users_profiles`, bind exact origin, `origin_id` `> 0`.
- **MUST NOT** allow `dacore.legacy`.
- Foreign origin → omit the row or generic `ok:false` on a targeted lookup. **MUST NOT** leak that the person exists under another module.
- `UserPolicy@findByExtra` is **not** authorization.
- The HR pack **MUST NOT** own the Auth session or `stampOrigin` from `find`.

Cross-origin IDOR on an employee or linked user is a **failed** contract.

---

## 8. Hooks

`find` is a read. **MUST NOT** fire a hook on `find`.

If the pack’s own admin persists an employee **outside** this contract, that persist hook carries **ids only**:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.hr_employee_saved.hook` | Employee created or status changed in pack admin | `employee_id` — **MUST NOT** name if treated as PII; id is enough |

**MUST NOT** put national ids, salaries, emails, passwords, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`people`, `employees`, `payroll`)
- Return national IDs, salaries, or passwords (or hashes / TOTP) from `find`
- Return anything except encrypted `id` + `display_name` on each item
- `glob('app/modules')` or `include` the pack to discover it
- `LIKE` against national-id or salary columns
- Allow `dacore.legacy` when a user link exists internally
- Leak `getMessage()`, secrets, or request bodies
- `all()` on a growing employee table
- Set `extra1=hr` on the ERP **host**
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1 (`extra3=employees`)
- Host lists with `listByContract!`
- Every public HTML employee id is encrypted
- `find` is `COUNT` + `LIMIT` with pager meta
- Reply items are `id` + `display_name` only
- No national ID / salary / password in the JSON
- No `crcCheck()` on `capabilities` / `find`
