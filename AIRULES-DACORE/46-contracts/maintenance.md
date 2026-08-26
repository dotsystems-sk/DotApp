# 46 — `maintenance` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop, ERP) and a work-order pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `asset` (register) and **not** `fleet` (vehicles) — those are separate `extra1` roles.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `maintenance` |
| `extra2` | `v1` |
| `extra3` | `workorders` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit the key) |

```php
'extra1' => 'maintenance',
'extra2' => 'v1',
'extra3' => 'workorders',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'maintenance', 'v1');
$workorders = DotApp::call('DACore:Plugins@listByContract!', 'maintenance', 'v1', 'workorders');
```

| extra3 | Meaning |
|--------|---------|
| `workorders` | Open a work order against an asset token; close it by encrypted id |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent `pm` / `cm` as `extra5` |

**Kind:** peer. **Controller:** `{Module}:MaintenanceContract@…!`

The **host** **MUST NOT** set `extra1=maintenance` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('maintenance','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':MaintenanceContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:MaintenanceContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Maintenance',       // exact module name
    'modes' => ['workorders'],
    'families' => ['generic', 'erp'],
    'summary_max' => 500,
    'asset_ref' => 'encrypted-local', // this pack decrypts assetRef (see §5)
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Maintenance is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Work-order ids that leave PHP toward HTML **MUST** be `{{ enc(Maintenance.workorder.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still `Auth::can` / ownership in PHP.

**MUST NOT** add a v1 `list` that dumps every work order. **MUST NOT** `all()` on a growing work-order table.

### `open($assetRef, $summary)`

**Call:** `DotApp::call('{Module}:MaintenanceContract@open!', $assetRef, $summary)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$assetRef` | string | Encrypted asset token **this pack** can resolve (see §5). Empty → `ok:false` |
| `$summary` | string | Plain-text reason, length 1–`summary_max`. **MUST NOT** HTML |

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',          // work-order id for HTML / close()
    'status' => 'open',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not open this work order.',
]
```

Decrypt fail; unknown asset token; empty / too-long summary; rights. **MUST NOT** persist on failure. **MUST NOT** return the summary (the host already has it).

The host **MUST** `htmlspecialchars` `$summary` before `{{ var: }}` when it echoes the text back.

### `close($id)`

**Call:** `DotApp::call('{Module}:MaintenanceContract@close!', $id)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$id` | string | Encrypted work-order id from `open` (or a pack-stable token this pack minted) |

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',
    'status' => 'closed',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not close this work order.',
]
```

Decrypt fail; unknown id; already closed; rights. Idempotent close of an already-closed row **MAY** return `ok:false` with product copy (`This work order is already closed.`) — **MUST NOT** throw.

After a successful transition to closed, fire the hook in §8.

Graphical confirm is **host/pack UI** (`Notiflix.Confirm` on admin) — never `alert()`.

---

## 5. `assetRef` (**MUST**)

`$assetRef` is an encrypted id or pack-stable token **this maintenance pack** can decrypt (`Maintenance.asset.ref` or the pack’s own asset row).

**MUST NOT** decrypt another module’s `{{ enc }}` `$key2`. A host that also picked an `asset` pack **MUST** mint or store a token **this** pack understands (operator linked the asset inside this pack, or this pack issued the token). Passing `AssetContract@get` ciphertext into `open` **MUST** fail closed (`ok:false`) unless this pack issued that ciphertext.

Asset picker (if the pack lists assets) = `<select>` / paged `dotSelect2` — **MUST NOT** a typed absolute path or raw integer id.

---

## 6. Ids, tables, and lists

- Pack tables **MUST** be `{lowercase_modulename}_*` (example: `maintenance_workorders`). Never `dacore_*`.
- HTML **MUST NOT** print a raw integer work-order id.
- Decrypt `false` → reject.
- `open` inserts one row. `close` updates one row by primary key. **MUST NOT** `all()`.
- Pack admin work-order browse **MUST** paginate with `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)). v1 has **no** `MaintenanceContract@list`.

---

## 7. Host HTTP and rights

Host **MUST** call these helpers in-process after the operator picked the pack. Admin POST stays on `/api/v1/auth/{Host|Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again. Contract methods themselves have **no CRC**.

Rights via `#YourModule:Rights@check!` — **not** `#DACore:AuthTest@check!`.

**MUST NOT** expose `open` / `close` on `/api/v1/noauth/…`.

---

## 8. Hooks

Fire only after a work order **becomes closed** — **not** on `open`, **not** on a failed `close`.

| Event | When | Payload (ids only) |
|-------|------|--------------------|
| `module.{mod}.workorder_closed.hook` | `close` persisted | `id` |

**MUST NOT** put `summary`, asset text, operator names, or secrets in the payload. **`id` only.** Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`workorder`, `work-orders`, `maint`)
- `glob('app/modules')` or `include` the pack to discover it
- `all()` / unbounded list of work orders
- Decrypt a foreign pack’s asset ciphertext
- Put `summary` or extra keys on `workorder_closed`
- Leak `getMessage()`, SQL, or request bodies
- Set `extra1=maintenance` on the host
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML work-order id is encrypted
- `open($assetRef, $summary)` / `close($id)` only
- Every method has input table + success/fail PHP arrays
- `workorder_closed` payload is `id` only; named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `open` / `close`
