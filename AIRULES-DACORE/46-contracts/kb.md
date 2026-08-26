# 46 — `kb` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the v1 peer contract. A host (CMS, Shop, ERP) and a knowledge-base pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `kb` |
| `extra2` | `v1` |
| `extra3` | `articles` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'kb',
'extra2' => 'v1',
'extra3' => 'articles',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'kb', 'v1');
$articles = DotApp::call('DACore:Plugins@listByContract!', 'kb', 'v1', 'articles');
```

| extra3 | Meaning |
|--------|---------|
| `articles` | Searchable published articles; host renders after escape |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1 |

**Kind:** peer. **Controller:** `{Module}:KbContract@…!`

The **host** **MUST NOT** set `extra1=kb` on itself.

This is **not** `search` (site index), `helpdesk` (tickets), or `dms` (records + ACL). Site-wide search stays `SearchContract`.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('kb','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':KbContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:KbContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'HelpCenter',        // exact module name
    'modes' => ['articles'],
    'families' => ['generic', 'cms'],
    'q_max' => 120,
    'per_page' => 20,
    'body_max' => 20000,             // stored article size; get() may truncate excerpt
]
```

**Failure:** `['ok' => false, 'message' => 'Knowledge base is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted article ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `search($q, $page)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$q` | string | Search text. Trimmed. Empty = first page of published articles (optional pack behavior) or `ok:false` if the pack requires a query. Length 0…`q_max` |
| `$page` | int | 1-based page. Invalid / `< 1` → `1` |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',  // {{ enc(HelpCenter.article.id) }} unique $key2
            'title' => 'Reset your password',
            'excerpt' => 'Steps to…',
        ],
    ],
    'page' => 1,
    'last_page' => 4,
    'total' => 73,
    'q' => 'password',               // echoed sanitized (length-capped), not raw SQL
]
```

**About:** bound `LIKE` / pack fulltext — **MUST NOT** put `$q` in SQL except as a placeholder. `COUNT(*)` + `LIMIT` / `OFFSET`. `per_page` from capabilities (default 20). **MUST NOT** `select('*')`. **MUST NOT** return unpublished drafts to a public host. Host **MUST** `htmlspecialchars` `title`, `excerpt`, and `q` before `{{ var: }}`.

**Failure:** too-long `$q`, query error → generic `Could not search articles.`

### `get($id)`

**Input:** encrypted article id or pack-stable token (string).

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',
    'title' => 'Reset your password',
    'body' => 'Plain text or stored markup. Host escapes.',
    'updated_at' => 1724600000,
]
```

**Failure:** decrypt fail, missing, unpublished → **one** generic `Article is not available.` (no draft vs missing enum).

**About:** v1 `body` is a **string**. The host **MUST** run `htmlspecialchars` (or an allow-list sanitizer the host owns) **before** `{{ var: }}`. `{{ var: }}` does **not** escape. Pack **MUST NOT** return a trusted HTML document that the host concatenates into the page. If the pack stores HTML, the **host** still escapes or sanitizes; v1 does not add `renderHtml`.

---

## 5. Host escape (**MUST**)

1. Every string from `search` / `get` / `capabilities` that reaches a view **MUST** be `htmlspecialchars` in PHP (or `.text()` in JS).
2. Article ids in HTML = `{{ enc(...) }}` unique `$key2`. Never `value="7"` / `data-id="7"`.
3. Sort / filter columns stay pack-owned. **MUST NOT** take a column name from `$q`.
4. Public pack search POST: throttle + CRC **XOR** prefix. These helpers stay **no CRC**.
5. Empty search results = `ok:true`, `items => []`, `total => 0` — not `ok:false`.

---

## 6. Hooks

`search` and `get` are reads. **MUST NOT** fire a hook on them.

Fire only if the pack persists a useful side-effect **outside** this contract (publish / unpublish in pack admin). If it does:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.kb_published.hook` | Article became public | `article_id` |

**MUST NOT** put title or body in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 7. MUST NOT

- Invent `extra1` (`knowledge`, `docs`, `help-center`, `wiki`)
- Treat this as `search` / `helpdesk` / `dms`
- `glob('app/modules')` or `include` the pack to discover it
- Skip host `htmlspecialchars` (“the pack is trusted”)
- `all()` or `select('*')` on a growing article table
- Leak `getMessage()`, draft titles to the public host, or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML article id is encrypted
- `search` uses `COUNT` + `LIMIT` and bound `$q`
- Host `htmlspecialchars` title / excerpt / body before views
- No hook on `search` / `get`
- No `crcCheck()` on `capabilities` / `search` / `get`
