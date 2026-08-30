# 46 — `crm` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6. Linked users: [42](../42-DACORE-USER-ORIGIN.md).

A `<Host>` and a CRM **pack** must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `crm` |
| `extra2` | `v1` |
| `extra3` | `contacts` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'crm',
'extra2' => 'v1',
'extra3' => 'contacts',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'crm', 'v1');
$contacts = DotApp::call('DACore:Plugins@listByContract!', 'crm', 'v1', 'contacts');
```

| extra3 | Meaning |
|--------|---------|
| `contacts` | People / organisations the host looks up and upserts. **MUST NOT** invent `pipeline` as extra3 in v1 |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1 |

**Kind:** peer. **Controller:** `{Module}:CrmContract@…!`

The **host** **MUST NOT** set `extra1=crm` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('crm','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CrmContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CrmContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'ContactsPack',        // exact module name
    'modes' => ['contacts'],
    'families' => ['shop', 'erp'],
    'page_size' => 20,
    'user_link' => true,               // optional encrypted user_id on upsert / find
    'fields' => [                      // upsert whitelist the pack accepts
        'display_name',
        'email',
        'phone',
        'company',
        'note',
        'source',
        'user_id',
    ],
]
```

**Failure:** `['ok' => false, 'message' => 'Contacts are not ready.']` — product copy, no `getMessage()`.

`find` is paged. `page_size` is the pack page length (1–100). **MUST NOT** `all()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Contact ids that leave PHP toward HTML **MUST** be `{{ enc(Crm.contact.id): $contactId }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

### `find($q, $page)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$q` | string | Optional name / email filter. Bound `LIKE`, not raw SQL. Empty = unfiltered page |
| `$page` | int | 1-based. Invalid / `< 1` → `1` |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',
            'display_name' => 'Ada Example',
            'company' => 'Example Ltd',
            'email' => 'ada@example.com',   // omit when empty
            'user_id' => '…ciphertext…',    // only when user_link and linked
        ],
    ],
    'page' => 1,
    'last_page' => 4,
    'total' => 73,
]
```

Host **MUST** `htmlspecialchars` `display_name` / `company` / `email` before `{{ var: }}`. JS: `.text()`, not `.html()`.

**Failure:** query error (reported via the pack catch helper) → `ok:false`. Empty result is **success** with `items` `[]`, `total` `0`.

### `upsert($fields)`

**Input** `$fields` — associative array. **Whitelist only** = `fields` from `capabilities()`. Unknown keys **MUST** be dropped (not persisted).

| Key | Type | Meaning |
|-----|------|---------|
| `id` | string | Optional encrypted contact id. Omit = create |
| `display_name` | string | Required on create. Max 190 |
| `email` | string | Optional. **MUST** use original input if the host collected it (`$request->data(true)` on HTTP) |
| `phone` | string | Optional. Max 32 |
| `company` | string | Optional. Max 190 |
| `note` | string | Optional short note. Max 500. Escape before views |
| `source` | string | Optional host token (`shop.checkout`). Max 64 |
| `user_id` | string | Optional encrypted `{prefix}users.id` — only if `user_link` is true |

**MUST NOT** accept `password`, `rights`, `origin`, `national_id`, `salary`, TOTP, hashes, or a request-body dump.

**Success:**

```php
[
    'ok' => true,
    'contact_id' => '…ciphertext…',
    'created' => true,
]
```

**Failure:** empty `display_name` on create; decrypt fail; whitelist-empty after drop; rights; origin fail on `user_id` → `ok:false`.

---

## 5. Origin and user ids (**MUST** — [42](../42-DACORE-USER-ORIGIN.md))

Origin is **provenance on one global account**, not a tenant sandbox. Auth email / session are global.

When `user_id` is present (upsert or a find row the pack stores):

1. Decrypt. `=== false` → generic `ok:false`.
2. Resolve the user with a **bound** lookup. Join `{prefix}users` to `dacore_users_profiles` with **INNER JOIN**. Bind `p.origin_id` and the pack’s exact origin token.
3. `origin_id` **MUST** be `> 0`. Token **MUST** be on the pack’s server-side allow-list.
4. **MUST NOT** allow `dacore.legacy` (it is also the missing-profile / read-error fallback).
5. Mismatch, missing profile, `UserPolicy@read` fallback, or catalog error → **deny + generic reply**. **MUST NOT** disclose “another module’s account”.
6. **MUST NOT** `stampOrigin` / restamp from `upsert`. Creating an Auth user is a **host** flow: `registerOrigin` → `Auth::createUser` → bound id lookup → `stampOrigin` → re-read exact token/id. The CRM pack **MUST NOT** own the Auth session.
7. `UserPolicy@findByExtra` is **not** authorization. **MUST NOT** use those ids as the owner check.
8. List / find SQL that can return a linked user **MUST** apply the same origin bind. **MUST NOT** return a foreign-origin contact because the encrypted id decrypted.

Cross-origin IDOR on a contact or linked user is a **failed** contract.

---

## 6. Hooks

Fire only after a useful persist — **not** on `find`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.crm_contact_upserted.hook` | Contact created or replaced | `contact_id`, `created` (bool), `user_id` (id or omit) |

**MUST NOT** put emails, phones, notes, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`contacts`, `leads`, `salesforce`)
- `glob('app/modules')` or `include` the pack to discover it
- Persist keys outside the `fields` whitelist
- Return a contact or user from another origin
- Allow `dacore.legacy` as a custom-module origin
- Put emails in hooks
- Leak `getMessage()`, passwords, rights blobs, or request bodies
- `all()` on a growing contact table
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML contact / user id is encrypted
- `find` is `COUNT` + `LIMIT` with pager meta
- `upsert` whitelist only; origin checked when `user_id` is set ([42](../42-DACORE-USER-ORIGIN.md))
- Hooks carry ids, not emails
- No `crcCheck()` on `capabilities` / `find` / `upsert`
