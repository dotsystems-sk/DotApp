# 46 — `workflow` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **v1 peer contract** for reserved role `workflow`. A `<Host>` and a BPM pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). Machine catalog: `DACore\Libraries\ExtraContracts` role `workflow`, controller `WorkflowContract`, methods `capabilities`, `start`, `advance`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `workflow` |
| `extra2` | `v1` |
| `extra3` | `bpm` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'workflow',
'extra2' => 'v1',
'extra3' => 'bpm',
'extra4' => 'erp',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'workflow', 'v1');
$bpm = DotApp::call('DACore:Plugins@listByContract!', 'workflow', 'v1', 'bpm');
```

| extra3 | Meaning |
|--------|---------|
| `bpm` | Named definitions. Host starts an instance on a subject and advances it by a whitelist action. **MUST NOT** invent `approval`, `state`, or `camunda` as extra3 in v1 |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `extra5` tokens (`human`, `async`, `saga`) |

**Kind:** peer. **Controller:** `{Module}:WorkflowContract@…!`

The **host** **MUST NOT** set `extra1=workflow` on itself.

This role is **not** `dms` `records-workflow` (document versions + ACL). DMS stays `DmsContract` ([dms.md](dms.md)). A helpdesk / invoice / shop host **MAY** start a BPM instance after its own persist; it does not become this role.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('workflow','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':WorkflowContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

Definition and action pickers on the host page use the arrays from `capabilities()` — **MUST NOT** a typed definition name.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:WorkflowContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Flow',              // exact module name
    'modes' => ['bpm'],             // extra3 this pack actually implements
    'families' => ['generic', 'erp'],
    'definitions' => [              // whitelist — only these keys are legal in start()
        [
            'key' => 'order_fulfil', // [a-z0-9._-] 1–64
            'title' => 'Order fulfilment',
            'actions' => ['approve', 'reject', 'hold'],
        ],
    ],
]
```

**About:** `definitions[].key` and `actions[]` are **pack-owned whitelists**. Host `<select>` / `dotSelect2` of `key` and of `actions` for the chosen definition. Unknown key → `start` / `advance` `ok:false`.

A pack **MAY** omit `families` when it accepts every `extra4` family. `definitions` **MUST** be bounded (shipped keys). A growing operator catalogue of definitions lives on the **pack’s** own paged admin ([40](../40-DACORE-LIST-PAGER.md)) — **MUST NOT** `all()` into `capabilities()`. In that case `definitions` **MAY** be `[]` and `definitions_paged` `true`; `start()` still accepts only a whitelist key the operator already selected.

```php
'definitions' => [],
'definitions_paged' => true,
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Workflow is not ready.',
]
```

Product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted instance / subject ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing instance lists (pack admin) use `COUNT(*)` + `LIMIT` / `OFFSET` — **MUST NOT** `all()`.

### `start($definition, $subjectRef)`

**Call:** `DotApp::call('{Module}:WorkflowContract@start!', $definition, $subjectRef)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$definition` | string | Whitelist key from `capabilities().definitions[].key` (or a key already chosen from the pack’s paged definition picker). Charset `[a-z0-9._-]`, length 1–64 |
| `$subjectRef` | string | Encrypted host subject (order, ticket, page) or pack-stable token. Empty / decrypt fail → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'instance_id' => '…ciphertext…',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not start this workflow.',
]
```

Unknown definition, decrypt fail, illegal `$subjectRef`, persist fail → that generic copy. **MUST NOT** leak SQL, class names, or `getMessage()`.

**About:** the pack stores `definition` + an opaque `subjectRef` (and its own instance row). **MUST NOT** parse `$subjectRef` as SQL, a class name, a file path, or a callable. **MUST NOT** `include` a path from the ref. Host still owns subject rights; pack **MUST** reject a second start only if the pack’s own uniqueness rule says so (same generic fail, **or** idempotent `ok:true` + existing `instance_id` — pick one and document in the pack).

### `advance($instanceId, $action)`

**Call:** `DotApp::call('{Module}:WorkflowContract@advance!', $instanceId, $action)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$instanceId` | string | Encrypted instance id from `start`. Decrypt `=== false` → `ok:false` |
| `$action` | string | Whitelist token from that definition’s `actions[]`. Charset `[a-z0-9._-]`, length 1–64 |

**Success:**

```php
[
    'ok' => true,
    'instance_id' => '…ciphertext…',
    'state' => 'approved',           // pack-owned token, not user text
]
```

`state` is a pack token (`[a-z0-9._-]`). Host **MUST** `htmlspecialchars` it before `{{ var: }}`.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not advance this workflow.',
]
```

Decrypt fail, unknown instance, action not on that definition’s list, terminal state → that generic copy. **MUST NOT** name the next legal actions in the error (no state oracle for hidden instances).

**About:** `$action` is a **whitelist string**, not a PHP callback name. **MUST NOT** `call_user_func($action)`, `eval`, or variable-functions. The pack maps `$action` with `if` / a lookup array (PHP 7.4: no `match`).

---

## 5. HTTP (not `WorkflowContract`)

These helpers are in-process. **No CRC** on `capabilities` / `start` / `advance`.

Pack operator BPM UI (list instances, pick a definition) uses the pack’s own `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` at `initialize()` — then the action **MUST NOT** `crcCheck()` again. Rights via `#YourModule:Rights@check!` — **not** `#DACore:AuthTest@check!`.

Host pages that wrap `start` / `advance` after a button use `$dotapp().load()` + encrypted `data-*` (row action) or a real `<fo-rm>` only when there are multiple fields. Overlay until the reply. Toast success / fail. **MUST NOT** `location.reload()`.

**MUST NOT** `$request->upload()` on v1 start / advance.

---

## 6. Host UI

- Pack choice = `<select>` / `dotSelect2` from `listByContract!`.
- Definition = `<select>` / `dotSelect2` of `capabilities()['definitions']` (or the pack’s paged picker when `definitions_paged` is true). **MUST NOT** a typed definition slug.
- Action = `<select>` of that definition’s `actions[]`. **MUST NOT** a free-text action box.
- Instance ids in HTML = `{{ enc(...) }}` unique `$key2`.
- Growing instance lists on pack admin follow [40](../40-DACORE-LIST-PAGER.md) (encrypted `data-page`, `$dotapp().live` first arg is the element, overlay).
- This role has **no** `picker_js`. **MUST NOT** invent `$dotapp().workflowPicker`.

---

## 7. Ids, subjects, and jail v1 (**MUST**)

1. Instance ids in HTML = `{{ enc(...) }}`. Decrypt `false` → `ok:false`. Still `Auth::can` / ownership in PHP on the **host subject** before `start` / `advance`. Pack **MUST** still refuse unknown instances.
2. `$definition` and `$action` are pack whitelist tokens. **MUST NOT** a column, table, class, or file path from the request.
3. `$subjectRef` is opaque. Pack stores it; hooks **MUST NOT** dump the decrypted subject.
4. Tables `{lowercase_modulename}_*` (example `flow_instances`). Composite index for pack admin lists: `definition` + `state` + `id` / `created` (comment names that query). Later indexes = new installer version + `indexExists()`.
5. **MUST NOT** take a user-chosen SQL identifier from `$definition` / `$action` / `$subjectRef`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.workflow_started.hook` | Instance inserted | `instance_id`, `definition` |
| `module.{mod}.workflow_advanced.hook` | State changed | `instance_id`, `definition`, `action`, `state` |

**MUST NOT** put `$subjectRef` ciphertext reuse as a secret, request bodies, or rights blobs. `definition` / `action` / `state` are short whitelist tokens only. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`bpm`, `camunda`, `approval`, `state-machine`)
- Invent `extra3` (`approval`, `state`, `human`) — v1 is `bpm` only
- Treat this as `dms` `records-workflow`
- `glob('app/modules')` or `include` the pack to discover it
- Accept a user-typed definition or action outside the whitelist
- `call_user_func` / `eval` / dynamic `include` from `$action`
- `all()` on a growing instance table
- Leak `getMessage()`, subject payloads, or secrets
- Set `extra1=workflow` on `<Host>`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1 (`extra3=bpm`, `extra2=v1`, `extra4` family)
- Host lists with `listByContract!`
- Every public HTML instance id is encrypted
- `start` / `advance` keys are pack whitelists
- Incoming decrypt `false` → `ok:false`
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `start` / `advance`
