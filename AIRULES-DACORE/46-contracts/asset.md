# 46 — `asset` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop, ERP) and a fixed-asset pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `asset` |
| `extra2` | `v1` |
| `extra3` | `register` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit the key) |

```php
'extra1' => 'asset',
'extra2' => 'v1',
'extra3' => 'register',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'asset', 'v1');
$register = DotApp::call('DACore:Plugins@listByContract!', 'asset', 'v1', 'register');
```

| extra3 | Meaning |
|--------|---------|
| `register` | One-record get / whitelist save for a fixed-asset row. **No** catalogue dump |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

**Kind:** peer. **Controller:** `{Module}:AssetContract@…!`

The **host** (CMS, Shop, ERP) **MUST NOT** set `extra1=asset` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('asset','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':AssetContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:AssetContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Assets',            // exact module name
    'modes' => ['register'],         // extra3 this pack actually implements
    'families' => ['generic', 'erp'],
    'writable' => [
        'code',
        'name',
        'status',
        'location',
        'acquired_on',
        'notes',
    ],
    'statuses' => ['active', 'idle', 'retired', 'disposed'],
]
```

**Failure:** `['ok' => false, 'message' => 'Asset register is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Assets.asset.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still `Auth::can` / ownership in PHP.

**MUST NOT** add a v1 `list` that returns the whole register. **MUST NOT** `all()` on a growing asset table. The host already holds an encrypted id (from a previous `save`, a host page that loaded one row, or another peer that minted a token **this** pack can decrypt).

### `get($id)`

**Input:** `$id` string — encrypted asset id or pack-stable token.

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',
    'code' => 'VEH-014',
    'name' => 'Delivery van',
    'status' => 'active',            // whitelist in capabilities.statuses
    'location' => 'Depot A',
    'acquired_on' => '2024-03-01',   // YYYY-MM-DD or ''
    'notes' => 'Front tyre set 2026',
]
```

Display strings (`name`, `location`, `notes`, `code`) are **plain text**. The host **MUST** `htmlspecialchars` before `{{ var: }}` (`{{ var: }}` does **not** escape) and use `.text()` in JS.

**Failure:** decrypt fail, unknown id, gone row → `ok:false`.

### `save($id, $fields)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$id` | string | Encrypted id to update, or `''` / `null` to create |
| `$fields` | array | **Whitelist keys only** (see table). Unknown keys are ignored and **MUST NOT** be persisted |

**Writable keys (v1):**

| Key | Type | Rule |
|-----|------|------|
| `code` | string | `[A-Za-z0-9._-]`, length 1–64. Unique inside the pack |
| `name` | string | Plain text, length 1–191 |
| `status` | string | Exact token from `capabilities.statuses` |
| `location` | string | Plain text, length 0–191 |
| `acquired_on` | string | `YYYY-MM-DD` or `''`. **MUST NOT** a free datetime |
| `notes` | string | Plain text, length 0–2000. **MUST NOT** store HTML |

**MUST NOT** persist `id`, `user_id`, `created_at`, `updated_at`, money, serial, or any request spread (`$request->data()` as `$fields`). Create still needs `code` + `name`. Update may send a subset; omitted keys stay unchanged. Empty whitelist after filter → `ok:false`.

**Success (create):**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',          // host stores this; HTML uses enc()
    'created' => true,
]
```

**Success (update):** `['ok' => true, 'id' => '…ciphertext…', 'created' => false]`.

**Failure:** decrypt fail on a non-empty `$id`; illegal `code` / `status` / date; missing required create fields; rights.

---

## 5. Ids and tables

- Pack tables **MUST** be `{lowercase_modulename}_*` (example: `assets_register`). Never `dacore_*`.
- HTML **MUST NOT** print a raw integer id (`value="7"`, `data-id="7"`, `{{ var: $id }}` as the id).
- Decrypt `false` → reject. A guessed integer **MUST NOT** load another operator’s row.
- `get` / `save` load **one** row (`limit(1)` / primary key). **MUST NOT** `all()` then pick.

---

## 6. Hooks

Fire only after a **new** register row is created — **not** on every `save` update, **not** on `get`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.asset_created.hook` | Insert succeeded | `id` |

**MUST NOT** put name, notes, location, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`assets`, `fixed-asset`, `register`)
- `glob('app/modules')` or `include` the pack to discover it
- `all()` / unbounded `list` on the register
- Persist columns outside the `save` whitelist
- Leak `getMessage()`, SQL, or request bodies
- Set `extra1=asset` on the host
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML id is encrypted
- `save` whitelist only; no request spread
- No `all()` dump
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `get` / `save`
