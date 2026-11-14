# 46 — `dms` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **document-records** peer (records, versions, ACL). It is **not** `filemanager` (no file jail / picker) and **not** `storage` (no raw object put/get). A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `dms` |
| `extra2` | `v1` |
| `extra3` | `records` \| `records-workflow` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'dms',
'extra2' => 'v1',
'extra3' => 'records',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'dms', 'v1');
$wf = DotApp::call('DACore:Plugins@listByContract!', 'dms', 'v1', 'records-workflow');
```

| extra3 | Meaning |
|--------|---------|
| `records` | Create / version / resolve / ACL. No workflow state machine |
| `records-workflow` | Same plus a pack-owned workflow `state` (draft / review / released as the pack documents) |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** put an ACL blob or role name here. |

**Kind:** peer. **Controller:** `{Module}:DmsContract@…!`

The **host** **MUST NOT** set `extra1=dms` on itself. **MUST NOT** call `MediaContract` on a DMS pack.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('dms','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':DmsContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:DmsContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'DocVault',          // exact module name
    'modes' => ['records-workflow'], // extra3 this pack actually implements
    'workflow' => true,              // true only when extra3=records-workflow
    'acl_actions' => ['view', 'edit', 'version', 'delete', 'workflow'],
]
```

**Failure:** `['ok' => false, 'message' => 'The document store is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Record ids that leave PHP toward HTML **MUST** be `{{ enc(Pack.record.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still `Auth::can` / ownership in PHP. Growing record lists belong to the pack’s **own** admin pager ([40](../40-DACORE-LIST-PAGER.md)) — **MUST NOT** `all()`. v1 peer methods do **not** include `list`.

### `create($opts)`

**Call:** `DotApp::call('{Module}:DmsContract@create!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `title` | string | Record title. Host `htmlspecialchars` before any view. Pack stores original or protected per its rules; **MUST NOT** put HTML from `$request->data()` without `data(true)` when the host forwards HTML |
| `type` | string | Record type whitelist (`contract`, `policy`, …). Pack rejects unknown types |
| `payload` | array | Metadata / structured fields. **MUST NOT** passwords, OTP, PAN, rights blobs |
| `owner_id` | int | Optional owner user id. Pack still enforces ACL |
| `state` | string | Optional. **Only** when `extra3=records-workflow`. Whitelist. Ignored in `records` mode |

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext or pack token…',
    'version' => 1,
    'state' => 'draft',              // omit or '' when extra3=records
]
```

**Failure:** validation; unknown type; rights → `ok:false`.

### `version($opts)`

**Call:** `DotApp::call('{Module}:DmsContract@version!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `id` | string | Encrypted record id or pack-stable token |
| `payload` | array | New version body / metadata (same secret rules as `create`) |
| `note` | string | Optional short revision note (not a dump of the payload) |
| `state` | string | Optional workflow state (`records-workflow` only) |

**Success:**

```php
[
    'ok' => true,
    'id' => '…same record token…',
    'version' => 2,
    'state' => 'review',
]
```

**Failure:** decrypt fail; unknown id; ACL deny; `records` mode with a required workflow-only field the pack rejects → `ok:false`.

### `resolve($id)`

**Call:** `DotApp::call('{Module}:DmsContract@resolve!', $id)`

**Input:** encrypted record id or pack-stable token (string).

**Success:**

```php
[
    'ok' => true,
    'id' => '…token…',
    'title' => 'Master service agreement',
    'type' => 'contract',
    'version' => 2,
    'state' => 'review',             // '' when extra3=records
    'owner_id' => 14,
]
```

**MUST NOT** return the full ACL table, rights names, or payload secrets. Host **MUST** `htmlspecialchars` `title` before `{{ var: }}`.

**Failure:** unknown / decrypt fail / gone → `ok:false`.

### `aclCheck($id, $action)`

**Call:** `DotApp::call('{Module}:DmsContract@aclCheck!', $id, $action)`

**Input:**

| Argument | Type | Meaning |
|----------|------|---------|
| `$id` | string | Encrypted record id or pack token |
| `$action` | string | Whitelist: `view` \| `edit` \| `version` \| `delete` \| `workflow`. Unknown → treat as not allowed or `ok:false` |

**Success (check completed):**

```php
[
    'ok' => true,
    'allowed' => true,
]
```

or `['ok' => true, 'allowed' => false]` when the actor must not proceed. Host **MUST** refuse the write when `allowed` is false. Pack **MUST** still enforce ACL inside `create` / `version` — this helper is not the only gate.

**Failure (cannot decide):** decrypt fail; unknown id → `['ok' => false, 'message' => 'The record could not be checked.']`. **MUST NOT** omit `ok`. Do not leak why (owner vs missing) beyond product copy.

---

## 5. Records vs files (**MUST**)

| This role (`dms`) | Not this role |
|-------------------|---------------|
| Versioned **records** + ACL | `filemanager` jail / picker / `publicUrl` |
| Encrypted **record** ids | Raw disk paths |
| `aclCheck` | Object `signedUrl` (`storage`) |

A DMS **MAY** store a `storage` / `filemanager` id **inside** `payload` as a token — it does not become those roles.

---

## 6. Encrypted ids and ACL

Every record id that leaves PHP toward HTML uses `{{ enc(DocVault.record.id): $id }}` (unique `$key2`). Decrypt `=== false` → reject. Still check rights / ownership in PHP.

`aclCheck` is `{ok, allowed}`. `ok:false` means the check did not run. `ok:true` + `allowed:false` means the actor must stop.

**MUST NOT** return the ACL table, `users_rights*` names, or a “this user can…” string. Product copy only.

Host **MUST NOT** treat `aclCheck` as enough to skip pack-side checks on `create` / `version`.

---

## 7. Workflow mode

`extra3=records` **MUST** ignore `state` on `create` / `version`. `capabilities.workflow` is `false`. `acl_actions` **MUST NOT** require `workflow`.

`extra3=records-workflow` **MAY** accept a whitelisted `state`. Unknown state → `ok:false`. The pack owns the transition table. **MUST NOT** put that table in extras.

v1 has no `transition($id, $from, $to)` method. A state change is a `version()` with `state`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `resolve` / `aclCheck`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.record_created.hook` | Record created | `id`, `type`, `version` |
| `module.{mod}.record_versioned.hook` | New version stored | `id`, `type`, `version`, `state` (if any) |

**MUST NOT** put payload bodies, ACL dumps, or secrets in the hook. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`documents`, `docstore`, `records`)
- Implement `filemanager` picker APIs on this controller
- `glob('app/modules')` or `include` the pack to discover it
- Put plain numeric record ids in HTML
- Leak `getMessage()`, rights blobs, or request bodies
- `all()` on a growing records table
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Peer calls use `DmsContract` after the operator picked a module
- Every HTML record id is encrypted
- `aclCheck` returns `{ok, allowed}`
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `create` / `version` / `resolve` / `aclCheck`
