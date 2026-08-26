# 46 — `filemanager` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **density template** for every file in this folder. A host (CMS, Shop) and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `filemanager` |
| `extra2` | `v1` |
| `extra3` | `full` \| `picker` \| `storage` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty \| `assets-only` |

```php
'extra1' => 'filemanager',
'extra2' => 'v1',
'extra3' => 'full',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'filemanager', 'v1');
$full = DotApp::call('DACore:Plugins@listByContract!', 'filemanager', 'v1', 'full');
```

| extra3 | Meaning |
|--------|---------|
| `full` | Admin workspace + picker + list / mkdir / resolve / publicUrl / delete |
| `picker` | Choose existing files only (CMS inserts an image). `mkdir` / `delete` / upload may return `ok:false` |
| `storage` | list / mkdir / resolve / publicUrl / delete — **no** picker JS |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Jail: this module / other modules’ `assets/` / `app/runtime` |
| `assets-only` | Public URLs only from `assets/`; runtime jail off |

**Kind:** peer. **Controller:** `{Module}:MediaContract@…!`

The **host** (CMS) **MUST NOT** set `extra1=filemanager` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('filemanager','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':MediaContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:MediaContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'DAFiles',           // exact module name
    'modes' => ['full'],             // extra3 this pack actually implements
    'jails' => ['module', 'assets', 'runtime'], // omit runtime when extra5=assets-only
    'public_urls' => true,           // false when no assets jail
    'picker_js' => '/assets/modules/DAFiles/js/picker.js', // '' when extra3=storage
    'picker_css' => '/assets/modules/DAFiles/css/picker.css',
    'upload_url' => '/api/v1/auth/DAFiles/media-upload', // '' when picker-only
]
```

**Failure:** `['ok' => false, 'message' => 'File manager is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `list($opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `jail` | string | `module` \| `assets` \| `runtime` — whitelist only |
| `path` | string | Relative path inside that jail. Empty = jail root. **MUST NOT** `..` or absolute |
| `page` | int | 1-based page. Invalid → 1 |
| `q` | string | Optional name filter (bound `LIKE`, not raw SQL) |
| `mime` | string | Optional prefix `image/` or exact type |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext or pack token…',
            'name' => 'hero.jpg',
            'kind' => 'file',          // file | dir
            'mime' => 'image/jpeg',
            'size' => 12044,
            'public_url' => '/assets/modules/Shop/img/hero.jpg', // or null
        ],
    ],
    'page' => 1,
    'last_page' => 3,
    'total' => 41,
]
```

**Failure:** unknown jail, path escape, decrypt fail → `ok:false`.

### `mkdir($opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `jail` | string | Same whitelist as `list` |
| `path` | string | Parent relative path |
| `name` | string | New folder name. Charset `[A-Za-z0-9._-]`, no slash |

**Success:** `['ok' => true]`. **Failure:** `picker` mode without mkdir support; illegal name; path escape.

### `resolve($id)`

**Input:** encrypted id or pack-stable token (string).

**Success:**

```php
[
    'ok' => true,
    'name' => 'hero.jpg',
    'mime' => 'image/jpeg',
    'size' => 12044,
    'public_url' => '/assets/modules/Shop/img/hero.jpg', // null if not public
    'jail' => 'assets',
]
```

**Failure:** unknown / decrypt fail / file gone.

### `publicUrl($id)`

**Input:** same as `resolve`.

**Success:** `['ok' => true, 'url' => '/assets/modules/{Module}/…']` **only** when the file is under `/assets/modules/{Module}/…`.

**Failure:** runtime jail, missing file, not public → `ok:false` (no guessed URL).

### `delete($id)`

**Input:** same as `resolve`. Optional. Graphical confirm is **host/pack UI** (`Notiflix.Confirm` on admin) — never `alert()`.

**Success:** `['ok' => true]`. **Failure:** `picker` mode; not found; rights.

---

## 5. HTTP upload (not `MediaContract@upload`)

Host **MUST** `$dotapp().uploadFile` to `upload_url` from `capabilities()`. Pack PHP: `$request->upload()` — **MUST NOT** `crcCheck()`. Reject `.php` / executables (extension + `finfo` + headers). `accept=` is UX only.

CRC on the upload **route** is `#DACore:AuthTest@LoginAndCRC!` on `/api/v1/auth/{Module}/…` if the action is a normal POST that is **not** `$request->upload()`. Upload actions skip CRC.

---

## 6. JS picker

When `extra3` is `full` or `picker` and `picker_js` is non-empty:

1. Host page loads that CSS/JS via `withMenu` `$css` / `$js` (or inject after pick).
2. Host calls **`$dotapp().mediaPicker({ module: 'DAFiles', target: $input })`**.
3. The **pack** implements `$dotapp().fn('mediaPicker')`. **MUST NOT** copy DACore JS.

`storage` mode: no picker fn.

---

## 7. Jail v1 (**MUST**)

Allowed areas: this-module tree, another **installed** module’s `assets/` only, or `app/runtime`. Area = `<select>`, **not** a typed absolute path. Runtime is **never** a public URL. Copy-path stays hidden unless the folder is under `assets/` and the operator enabled that setting.

---

## 8. Hooks

Fire only after a useful persist — **not** on `list`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.media_stored.hook` | File created or replaced | `id`, `mime`, `size`, `jail` |
| `module.{mod}.media_deleted.hook` | File deleted | `id`, `jail` |

**MUST NOT** put bytes, absolute paths, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`files`, `media`, `fm`, `dafiles`)
- `glob('app/modules')` or `include` the pack to discover it
- Return a public URL for `app/runtime`
- Leak `getMessage()`, raw disk paths, or request bodies
- `all()` on a growing file table
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML id is encrypted
- Upload uses `$dotapp().uploadFile` + `$request->upload()`
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `list` / `resolve` / upload
