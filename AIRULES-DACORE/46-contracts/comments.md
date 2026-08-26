# 46 — `comments` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop) and a pack must be able to interoperate from this page alone. This is **not** `reviews` (stars) and **not** `forum` (board).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `comments` |
| `extra2` | `v1` |
| `extra3` | `threaded` \| `flat` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'comments',
'extra2' => 'v1',
'extra3' => 'threaded',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'comments', 'v1');
$threaded = DotApp::call('DACore:Plugins@listByContract!', 'comments', 'v1', 'threaded');
```

| extra3 | Meaning |
|--------|---------|
| `threaded` | `list` / `add` accept an optional parent comment. Replies nest under that parent. `parent_id` empty = top-level |
| `flat` | No parent. `add` with `parent_id` → `ok:false`. `list` is a single paged stream per target |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`moderated`, `guest`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:CommentsContract@…!`

The **host** (CMS, Shop) **MUST NOT** set `extra1=comments` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('comments','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CommentsContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CommentsContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'BlogComments',      // exact module name
    'modes' => ['threaded'],         // extra3 this pack actually implements
    'target_types' => ['article', 'product', 'page'], // host whitelist the pack accepts
    'max_body' => 4000,              // characters; pack rejects longer
    'page_size' => 20,               // list() page length
    'guest_ok' => false,             // true only when pack accepts a guest author_ref
]
```

**Failure:** `['ok' => false, 'message' => 'Comments are not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

Public HTTP that wraps `add` (if the pack ships a form) lives on the pack’s `/api/v1/auth|noauth/{Module}/…`. That route uses `#DACore:AuthTest@LoginAndCRC!` or `@CRC!` **XOR** action `crcCheck()` — never both. The in-process `CommentsContract` methods still have **no** CRC.

### `list($opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `target_type` | string | Must be in `capabilities.target_types`. Unknown → `ok:false` |
| `target_id` | string | Encrypted host record id (article / product / page). Decrypt `false` → `ok:false` |
| `parent_id` | string | Optional. Encrypted comment id. `threaded` only: list direct replies. Empty = top-level page. `flat` + non-empty → `ok:false` |
| `page` | int | 1-based page. Invalid → 1 |
| `q` | string | Optional body-prefix filter (bound `LIKE`, not raw SQL). Empty = no filter |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',
            'parent_id' => '',          // ciphertext or empty
            'author_ref' => '…ciphertext or pack token…',
            'body' => 'Thanks for the write-up.', // plain text; host escapes
            'created_at' => '2026-08-25 12:00:00',
            'reply_count' => 2,         // 0 in flat mode
        ],
    ],
    'page' => 1,
    'last_page' => 3,
    'total' => 41,
]
```

`last_page` comes from `COUNT(*)` of the filtered set, not `count($items)`.

**Failure:** unknown type, decrypt fail, path-like `target_id`, `flat` + parent → `ok:false`.

### `add($opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `target_type` | string | Same whitelist as `list` |
| `target_id` | string | Encrypted host record id |
| `parent_id` | string | Optional encrypted parent. Required empty in `flat`. In `threaded`, empty = top-level. Unknown parent → `ok:false` |
| `body` | string | Comment text. Host/pack **MUST** take the original (`$request->data(true)` on HTTP). Trim + length ≤ `max_body`. Empty → `ok:false` |
| `author_ref` | string | Host-stable author token (encrypted user id or guest token the pack issued). **MUST NOT** be a password, raw email, or session id |

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',
]
```

**Failure:** missing body, oversize, unknown parent, decrypt fail, guest when `guest_ok` is false, rights.

Persist still runs in PHP. A disabled button or overlay is UX only.

### `remove($id)`

**Input:** encrypted comment id (string).

Graphical confirm is **host/pack UI** (`Notiflix.Confirm` on admin) — never `alert()`.

**Success:** `['ok' => true]`. **Failure:** not found, decrypt fail, rights.

A threaded pack that removes a parent **MUST** either reject while replies exist or delete the subtree in one transaction. Reply bodies still **MUST NOT** appear in the hook payload.

---

## 5. Encrypted ids and HTML

Every comment id, parent id, target id, and stored consent-like author token that leaves PHP toward HTML uses `{{ enc(BlogComments.comment.id): $id }}` (unique `$key2` per field). Pager `data-page` is ciphertext when the pack ships a public list ([40](../40-DACORE-LIST-PAGER.md)).

`{{ var: }}` does **not** escape. The **host** (or pack view) **MUST** `htmlspecialchars` `body` and any author label before the template. JS inserts use `.text()`, not `.html()`.

---

## 6. Public HTTP (optional pack form)

If the pack renders a public `<fo-rm>`:

- Throttle the public POST.
- Warn that an unauthenticated form is bot-bait (CAPTCHA is not MUST; a `captcha` pack is a separate `extra1`).
- Action **MUST NOT** `crcCheck()` after `#DACore:AuthTest@CRC!` / `LoginAndCRC!`.
- Then the action may call `CommentsContract@add!` in-process.

The host **MAY** call `add` / `remove` from its own authenticated admin route instead. That is still in-process, no CRC on the helper.

---

## 7. Host render

The host owns the article / product page. It calls `list` after pick, patches `reply.html` + toast on AJAX add/remove, and **MUST NOT** `location.reload()`. Overlay the list until the request ends.

`reviews` (`extra1=reviews`) is stars. **MUST NOT** treat a comments pack as a review pack or invent `extra1` `discussions`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `list`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.comments_added.hook` | Comment stored | `id`, `target_type`, `parent_id` (empty string if none), `reply_count` |
| `module.{mod}.comments_removed.hook` | Comment deleted | `id`, `target_type` |

**MUST NOT** put `body`, author email, IP, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`discussions`, `chat`, `reviews` as a synonym)
- `glob('app/modules')` or `include` the pack to discover it
- Return plaintext numeric ids in HTML
- Leak `getMessage()`, comment bodies on the hook bus, or request bodies
- `all()` on a growing comment table
- Fire hooks on `list`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML id is encrypted
- `list` uses `COUNT` + `LIMIT`; `last_page` is not `count($items)`
- Hooks named in `.hooks` if fired; payloads are ids only
- No `crcCheck()` on `capabilities` / `list` / `add` / `remove`
- `body` escaped before `{{ var: }}`
