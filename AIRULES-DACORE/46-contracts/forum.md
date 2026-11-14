# 46 — `forum` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the v1 peer contract. A `<Host>` and a forum pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `forum` |
| `extra2` | `v1` |
| `extra3` | `board` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'forum',
'extra2' => 'v1',
'extra3' => 'board',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'forum', 'v1');
$board = DotApp::call('DACore:Plugins@listByContract!', 'forum', 'v1', 'board');
```

| extra3 | Meaning |
|--------|---------|
| `board` | One or more boards; host creates threads and posts through the pack |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1 |

**Kind:** peer. **Controller:** `{Module}:ForumContract@…!`

The **host** **MUST NOT** set `extra1=forum` on itself.

Machine catalog methods are `capabilities`, `thread`, `post`. Growing thread lists **MUST** page: this contract adds `listThreads($boardRef, $page)` (`COUNT` + `LIMIT`). A single-thread read is `listThreads` plus the pack’s own thread page, or `thread` after create (the returned `thread_id`).

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('forum','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ForumContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ForumContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Boards',            // exact module name
    'modes' => ['board'],
    'families' => ['generic', 'cms'],
    'boards' => [                    // bounded board picker
        [
            'id' => '…ciphertext…',  // {{ enc(Boards.board.id) }} unique $key2
            'title' => 'General',
            'open' => true,          // false = locked (thread/post → ok:false)
        ],
    ],
    'title_max' => 180,
    'body_max' => 8000,
    'per_page' => 20,
]
```

**About:** `boards[]` is a **bounded** choice (`<select>` / `dotSelect2`). Do not hide boards behind a typed name. A huge board catalogue stays in pack admin; v1 capabilities stay bounded.

**Failure:** `['ok' => false, 'message' => 'Forum is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `thread($boardRef, $title)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$boardRef` | string | Encrypted board id or pack-stable token |
| `$title` | string | Thread title. Trimmed. Length 1…`title_max` from capabilities |

**Success:**

```php
[
    'ok' => true,
    'thread_id' => '…ciphertext…',
]
```

**Failure:** decrypt fail, unknown / locked board, empty or too-long title → `ok:false` with a generic line, e.g. `Could not start this thread.` **MUST NOT** distinguish “no such board” from “locked” in a way that enumerates hidden boards.

**About:** creates a thread. Does **not** accept the first post body (that is `post`). Host `htmlspecialchars` `$title` before `{{ var: }}` if it echoes the input.

### `listThreads($boardRef, $page)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$boardRef` | string | Encrypted board id or pack-stable token |
| `$page` | int | 1-based page. Invalid / `< 1` → `1` |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'thread_id' => '…ciphertext…',
            'title' => 'Welcome',
            'post_count' => 12,
            'updated_at' => 1724600000, // unix int
        ],
    ],
    'page' => 1,
    'last_page' => 3,
    'total' => 41,
]
```

**About:** `COUNT(*)` + `LIMIT` / `OFFSET`. `per_page` from capabilities (default 20). **MUST NOT** `all()`. **MUST NOT** return post bodies. Host `htmlspecialchars` each `title` before `{{ var: }}`.

**Failure:** decrypt fail, unknown board → generic `Could not load threads.` (same line whether missing or closed).

### `post($threadId, $body)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$threadId` | string | Encrypted thread id (`thread_id` from `thread` / `listThreads`) |
| `$body` | string | Post body. Trimmed. Length 1…`body_max` |

**Success:**

```php
[
    'ok' => true,
    'post_id' => '…ciphertext…',
]
```

**Failure:** decrypt fail, unknown / locked thread, empty or too-long body → generic `Could not publish this post.`

**About:** does **not** echo `$body`. Host that renders a composer preview **MUST** `htmlspecialchars` first. Row actions in admin stay `$dotapp().load()` + encrypted `data-*`, not one `<fo-rm>` per post.

Reading a thread’s posts for a host-owned page is **not** a separate v1 method: use the pack’s thread UI, or page titles via `listThreads`. Do not `all()` posts in PHP to build a table.

---

## 5. HTML and inputs

1. Board, thread, and post ids in HTML = `{{ enc(...) }}` unique `$key2`.
2. `{{ var: }}` does **not** escape — host **MUST** `htmlspecialchars` titles (and any body it displays).
3. Passwords/HTML from the request use `$request->data(true)` on the **pack’s** HTTP forms, not on these helpers (in-process strings).
4. Persist (closed board, flood, rights) is **PHP** in the pack. Host UI is not the gate.
5. Public pack POST routes still `#DACore:AuthTest@LoginAndCRC!` / `@CRC!` **XOR** action `crcCheck()` — these contract methods stay **no CRC**.

---

## 6. Hooks

Fire only after a useful persist — **not** on `listThreads` or `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.forum_posted.hook` | Post row inserted | `board_id`, `thread_id`, `post_id` |

**MUST NOT** put title or body in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md). Creating a thread without a post does **not** fire `forum_posted`; fire only when `post` persists.

---

## 7. MUST NOT

- Invent `extra1` (`forums`, `board`, `discussion`, `phpbb`)
- `glob('app/modules')` or `include` the pack to discover it
- Put post **body** or title on `forum_posted`
- `all()` on a growing thread or post table
- Return plaintext ids in HTML
- Leak `getMessage()`, request bodies, or secrets
- PHP 8+ syntax unless the plan named a higher version

---

## 8. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML board / thread / post id is encrypted
- `listThreads` uses `COUNT` + `LIMIT`
- `forum_posted` carries ids only (no body)
- Host `htmlspecialchars` titles before views
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `thread` / `listThreads` / `post`
