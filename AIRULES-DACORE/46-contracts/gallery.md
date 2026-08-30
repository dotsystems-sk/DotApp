# 46 — `gallery` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a gallery pack. A `<Host>` and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** `filemanager` (jail picker), **not** `slider` (hero / carousel), and **not** `storage`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `gallery` |
| `extra2` | `v1` |
| `extra3` | `grid` \| `masonry` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'gallery',
'extra2' => 'v1',
'extra3' => 'grid',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'gallery', 'v1');
$grid = DotApp::call('DACore:Plugins@listByContract!', 'gallery', 'v1', 'grid');
```

| extra3 | Meaning |
|--------|---------|
| `grid` | Equal-cell grid. `render` HTML uses a uniform column layout |
| `masonry` | Staggered heights. Same `render` I/O; CSS/JS differs |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`lightbox`, `justified`) as `extra5` |

A pack that implements both sets `extra3` to the **primary** mode in `about.php` and lists both in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:GalleryContract@…!`

The **host** **MUST NOT** set `extra1=gallery` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('gallery','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':GalleryContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:GalleryContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SiteGallery',       // exact module name
    'modes' => ['grid'],             // extra3 this pack actually implements
    'picker_js' => '',               // optional pack admin picker; '' when unused
    'picker_css' => '',
    'max_items' => 60,               // items one render() will include
]
```

**MUST NOT** return API keys, disk paths, or raw numeric gallery ids meant for HTML.

**Failure:** `['ok' => false, 'message' => 'Gallery is not ready.']` — product copy, no `getMessage()`.

If `picker_js` is non-empty, the **pack** implements `$dotapp().fn` for that file. **MUST NOT** copy DACore JS. v1 does **not** require a picker; the host **MAY** store an encrypted gallery id from the pack’s own admin.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Item lists that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()` then filter.

### `render($galleryRef)`

**About:** Return the gallery markup for one gallery the host already authorized.

**Call:** `DotApp::call('{Module}:GalleryContract@render!', $galleryRef)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$galleryRef` | string | Encrypted gallery id (`Crypto::encrypt`, unique `$key2`). Empty / decrypt `false` / unknown → `ok:false` |

**MUST NOT** accept a raw integer `7`, a filesystem path, or a URL as `$galleryRef`.

**Success:**

```php
[
    'ok' => true,
    'html' => '<div class="sitegallery_grid">…</div>',
]
```

`html` is a **fragment** (no `<html>` / admin shell). The pack **MUST** build it with Renderer + `.view.php` / `.layout.php` — **MUST NOT** concatenate a table/grid in the controller. Item ids inside the markup **MUST** be `{{ enc(SiteGallery.item.id): $id }}`. Captions **MUST** be `htmlspecialchars` before `{{ var: }}`.

Public image `src` **MUST** be under `/assets/modules/{Module}/…` or a filemanager `public_url` the host already resolved. **MUST NOT** emit `app/runtime` URLs.

Cap visible items at `max_items`. Extra rows wait for a pack pager on the pack’s own page — v1 `render` is one fragment.

**Failure:** decrypt fail; missing gallery; unpublished when the caller is public → `['ok' => false, 'message' => 'This gallery is not available.']`.

`render` **MUST NOT** throw.

---

## 5. Encrypted ids and HTML (**MUST**)

Every gallery id and item id that leaves PHP toward HTML uses `{{ enc(SiteGallery.gallery.id): $id }}` (unique `$key2` per field). Decrypt `false` → reject. Still check rights / ownership in PHP.

`{{ var: }}` does **not** escape. JS inserts use `.text()`, not `.html()`, for captions.

Pager `data-page` on a pack admin list is ciphertext ([40](../40-DACORE-LIST-PAGER.md)).

---

## 6. HTTP (pack-owned, not `GalleryContract`)

Pack admin CRUD is `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again. Uploads: `$dotapp().uploadFile` + `$request->upload()` — **MUST NOT** `crcCheck()` on upload. Reject `.php` / executables (extension + `finfo` + headers).

Deletes: `Notiflix.Confirm` on admin — never `alert()`. After save, patch `reply.html` + toast — **MUST NOT** `location.reload()`. Overlay until the request ends.

The in-process `render!` helper has **no** CRC.

---

## 7. Host render

The host owns the article / product page. After pick it calls `render($encryptedId)` and inserts `html` into its template (or a `{content}` slot). **MUST NOT** `eval` the fragment.

Classes: `{lowercase_modulename}_*` (e.g. `.sitegallery_grid`). Match admin colors only on pack admin pages; public CSS is the pack’s own.

`slider` (`extra1=slider`) is a different role. **MUST NOT** treat a gallery pack as a hero slider or invent `extra1` `albums`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `render`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.gallery_updated.hook` | Gallery items replaced or the gallery row saved | `id` (gallery), `item_count` |

**MUST NOT** put bytes, captions, absolute paths, or secrets in the payload. Document in the pack `.hooks` only if this event actually fires. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`albums`, `photos`, `lightbox`, `media-gallery`)
- `glob('app/modules')` or `include` the pack to discover it
- Accept a plaintext numeric gallery id from the browser
- Return `app/runtime` URLs
- Concatenate a grid/table in the controller (`$html .= '<div class='`)
- Leak `getMessage()`, disk paths, or request bodies
- `all()` on a growing item table
- Fire a hook on `render`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `render($galleryRef)` is `{ok, html}`; `$galleryRef` is ciphertext
- Every public HTML id is encrypted
- Markup comes from Renderer templates
- Hooks named in `.hooks` only if `gallery_updated` fires
- No `crcCheck()` on `capabilities` / `render`
