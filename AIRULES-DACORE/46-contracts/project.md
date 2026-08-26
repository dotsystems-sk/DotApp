# 46 — `project` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A CMS / ERP **host** and a project **pack** must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `project` |
| `extra2` | `v1` |
| `extra3` | `tasks` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'project',
'extra2' => 'v1',
'extra3' => 'tasks',
'extra4' => 'erp',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'project', 'v1');
$tasks = DotApp::call('DACore:Plugins@listByContract!', 'project', 'v1', 'tasks');
```

| extra3 | Meaning |
|--------|---------|
| `tasks` | Projects contain tasks. v1 API is `list` + `save` (create / update a task) |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `kanban` here |

**Kind:** peer. **Controller:** `{Module}:ProjectContract@…!`

The **host** **MUST NOT** set `extra1=project` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('project','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ProjectContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ProjectContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'WorkProjects',        // exact module name
    'modes' => ['tasks'],
    'families' => ['erp', 'generic'],
    'page_size' => 20,
    'statuses' => ['open', 'doing', 'done'],
    'assignee_link' => false,          // true only if save accepts encrypted user_id
]
```

**Failure:** `['ok' => false, 'message' => 'Projects are not ready.']` — product copy, no `getMessage()`.

`list` is paged. **MUST NOT** `all()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Project and task ids that leave PHP toward HTML **MUST** be `{{ enc(Project.task.id): $taskId }}` / `{{ enc(Project.project.id): $projectId }}` with a **unique** `$key2` each. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP.

### `list($opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `project_id` | string | Encrypted project id or pack token. Required unless the pack documents a default inbox |
| `page` | int | 1-based. Invalid → `1` |
| `q` | string | Optional title filter. Bound `LIKE`, not raw SQL |
| `status` | string | Optional. Whitelist = `statuses` from `capabilities()`. Unknown → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'task_id' => '…ciphertext…',
            'project_id' => '…ciphertext…',
            'title' => 'Prepare launch checklist',
            'status' => 'open',
            'due' => '2026-09-01',     // Y-m-d or empty
        ],
    ],
    'page' => 1,
    'last_page' => 2,
    'total' => 28,
]
```

Host **MUST** escape `title` before `{{ var: }}`.

**Failure:** decrypt fail / unknown project / bad status → `ok:false`. Empty page is **success** with `items` `[]`.

**MUST:** `COUNT(*)` for `total` / `last_page`, then `LIMIT` / `OFFSET`. **MUST NOT** `all()` then slice. **MUST NOT** use `QueryObject::paginate()['total']` as the only count if that helper is not this pack’s documented path — prefer an explicit `COUNT`.

### `save($task)`

**Input** `$task` array — whitelist only:

| Key | Type | Meaning |
|-----|------|---------|
| `id` | string | Optional encrypted task id. Omit = create |
| `project_id` | string | Encrypted project id or token. Required on create |
| `title` | string | Required. Max 190 |
| `status` | string | Whitelist from `capabilities()`. Default `open` |
| `due` | string | Optional `Y-m-d` or empty |
| `assignee_id` | string | Optional encrypted user id. **Only** when `assignee_link` is true |

Unknown keys **MUST** be dropped. **MUST NOT** accept description HTML unless a later contract version adds it; v1 title-only keeps the peer small.

If `assignee_id` is set: follow [42](../42-DACORE-USER-ORIGIN.md) — INNER JOIN profiles, exact origin, `origin_id` `> 0`, **never** `dacore.legacy`. Mismatch → generic `ok:false`. **MUST NOT** assign a foreign-origin user. The pack **MUST NOT** own the Auth session.

**Success:**

```php
[
    'ok' => true,
    'task_id' => '…ciphertext…',
    'project_id' => '…ciphertext…',
    'created' => false,
]
```

**Failure:** missing title / project; decrypt fail; illegal status; origin fail → `ok:false`. **MUST NOT** persist on failure.

---

## 5. Paging and indexes

- `list` **MUST** ship pager meta (`page`, `last_page`, `total`) on first version.
- Index `project_id` + `status` + sort column (equality → range → sort). One comment line per index naming the `list` query ([25](../25-PERFORMANCE-AND-CODE-QUALITY.md)).
- Admin HTML lists that wrap this contract **MUST** follow [40](../40-DACORE-LIST-PAGER.md) (`card-footer dacore-list-pager`, encrypted `data-page`, `$dotapp().live(el, e)`).

---

## 6. Hooks

Fire only after a useful persist — **not** on `list`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.project_task_saved.hook` | Task created or updated | `task_id`, `project_id`, `created` (bool), `status` |

**MUST NOT** put titles, due dates, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`projects`, `tasks`, `jira`)
- `glob('app/modules')` or `include` the pack to discover it
- Return plaintext project / task ids in HTML
- `all()` on a growing task table
- Persist `status` / sort columns from the request without a whitelist
- Cross-origin assignee when `assignee_link` is used ([42](../42-DACORE-USER-ORIGIN.md))
- Leak `getMessage()`, secrets, or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML project / task id is encrypted
- `list` is `COUNT` + `LIMIT` with pager meta
- `save` whitelist + status whitelist
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `list` / `save`
