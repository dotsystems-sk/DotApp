# 46 — `slider` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a hero / carousel pack. A host (CMS, Shop) and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** `gallery` (grid / masonry) and **not** `filemanager`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `slider` |
| `extra2` | `v1` |
| `extra3` | `hero` \| `carousel` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'slider',
'extra2' => 'v1',
'extra3' => 'hero',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'slider', 'v1');
$hero = DotApp::call('DACore:Plugins@listByContract!', 'slider', 'v1', 'hero');
```

| extra3 | Meaning |
|--------|---------|
| `hero` | One primary slide (or a short stack) for a landing above-the-fold band |
| `carousel` | Multi-slide pager / autoplay strip. Same `render` I/O |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`fade`, `kenburns`) as `extra5` |

A pack that implements both sets `extra3` to the **primary** mode in `about.php` and lists both in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:SliderContract@…!`

The **host** **MUST NOT** set `extra1=slider` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('slider','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':SliderContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:SliderContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SiteSlider',        // exact module name
    'modes' => ['hero'],             // extra3 this pack actually implements
    'slider_js' => '/assets/modules/SiteSlider/js/slider.js', // '' when CSS-only
    'slider_css' => '/assets/modules/SiteSlider/css/slider.css',
    'max_slides' => 12,
]
```

`slider_js` / `slider_css` **MUST** be under `/assets/modules/{Module}/…` or empty. **MUST NOT** be a remote CDN URL with an embedded key.

**Failure:** `['ok' => false, 'message' => 'Slider is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Slide tables that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `render($sliderRef)`

**About:** Return the hero / carousel markup for one slider the host already authorized.

**Call:** `DotApp::call('{Module}:SliderContract@render!', $sliderRef)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$sliderRef` | string | Encrypted slider id. Empty / decrypt `false` / unknown → `ok:false` |

**MUST NOT** accept a raw integer, a path, or a URL as `$sliderRef`.

**Success:**

```php
[
    'ok' => true,
    'html' => '<div class="siteslider_hero">…</div>',
]
```

`html` is a **fragment**. The pack **MUST** use Renderer templates — **MUST NOT** concatenate a slide list in the controller. Slide ids in markup **MUST** be `{{ enc(SiteSlider.slide.id): $id }}`. Titles / CTAs **MUST** be `htmlspecialchars` before `{{ var: }}`.

Slide `href` values **MUST** be pack- or host-owned paths the operator stored in the pack (charset `[A-Za-z0-9._:/-]`, leading `/`, or `https://` on an allowlisted host in **pack** settings). **MUST NOT** copy `$request` into `href` or `header()`.

Image `src` **MUST** be `/assets/modules/{Module}/…` or a filemanager public URL. **MUST NOT** `app/runtime`.

Cap slides at `max_slides`.

**Failure:** decrypt fail; missing / unpublished slider → `['ok' => false, 'message' => 'This slider is not available.']`.

`render` **MUST NOT** throw.

---

## 5. Encrypted ids and HTML (**MUST**)

Every slider id and slide id that leaves PHP toward HTML uses `{{ enc(SiteSlider.slider.id): $id }}` (unique `$key2`). Decrypt `false` → reject. Still check rights / ownership in PHP.

`{{ var: }}` does **not** escape. JS uses `.text()` for titles.

---

## 6. HTTP (pack-owned, not `SliderContract`)

Pack admin CRUD: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again. Uploads: `$dotapp().uploadFile` + `$request->upload()`. Reject `.php` / executables (extension + `finfo` + headers).

Deletes: `Notiflix.Confirm` — never `alert()`. After save: patch `reply.html` + toast — **MUST NOT** `location.reload()`.

The host page **MAY** load `slider_css` / `slider_js` from `capabilities()` via `withMenu` `$css` / `$js` (admin) or the host public layout. The **pack** implements any `$dotapp().fn`. **MUST NOT** copy DACore JS.

The in-process `render!` helper has **no** CRC.

---

## 7. Host render

The host inserts `html` into the landing / category template. **MUST NOT** `eval` the fragment. Buttons in the fragment **MUST** have padding vs the slide (especially below) — [05](../05-VIEWS-TEMPLATES-ASSETS.md) §8c.

`gallery` is a different `extra1`. **MUST NOT** invent `extra1` `hero`, `carousel`, or `banner-rotator`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `render`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.slider_updated.hook` | Slider or its slides saved | `id` (slider), `slide_count` |

**MUST NOT** put image bytes, CTA URLs, or secrets in the payload. Document in the pack `.hooks` only if this event actually fires. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`hero`, `carousel`, `banner`, `swiper`)
- `glob('app/modules')` or `include` the pack to discover it
- Accept a plaintext numeric slider id from the browser
- Build slide `href` from request input or put it in `header()`
- Return `app/runtime` URLs
- Concatenate a slide list in the controller
- Leak `getMessage()`, disk paths, or request bodies
- `all()` on a growing slide table
- Fire a hook on `render`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `render($sliderRef)` is `{ok, html}`; `$sliderRef` is ciphertext
- Every public HTML id is encrypted
- Markup comes from Renderer templates
- Hooks named in `.hooks` only if `slider_updated` fires
- No `crcCheck()` on `capabilities` / `render`
