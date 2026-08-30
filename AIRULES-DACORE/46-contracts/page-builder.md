# 46 — `page-builder` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **visual page** peer (block catalogue + render a stored page). It is **not** `editor` (no `sanitize` of a free HTML field) and **not** `template` (no theme zip). A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `page-builder` |
| `extra2` | `v1` |
| `extra3` | `blocks` \| `sections` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'page-builder',
'extra2' => 'v1',
'extra3' => 'blocks',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'page-builder', 'v1');
$blocks = DotApp::call('DACore:Plugins@listByContract!', 'page-builder', 'v1', 'blocks');
```

| extra3 | Meaning |
|--------|---------|
| `blocks` | Small reusable widgets (hero, text, image). `listBlocks` is that catalogue |
| `sections` | Larger layout sections (header band, product grid). Same methods; `kind` on items is `section` |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`gutenberg`, `elementor`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:PageBuilderContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=page-builder` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('page-builder','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PageBuilderContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PageBuilderContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PageKit',           // exact module name
    'modes' => ['blocks'],           // extra3 this pack actually implements
    'page_size' => 20,               // listBlocks() page length
    'builder_js' => '/assets/modules/PageKit/js/builder.js', // '' if host chrome only
    'builder_css' => '/assets/modules/PageKit/css/builder.css',
]
```

**Failure:** `['ok' => false, 'message' => 'The page builder is not ready.']` — product copy, no `getMessage()`.

`builder_js` / `builder_css` are optional. **MUST NOT** return a license key or remote editor token.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Block ids and page refs that leave PHP toward HTML **MUST** be `{{ enc(...) }}` (unique `$key2`). Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing catalogues: `COUNT(*)` + `LIMIT` / `OFFSET` — **MUST NOT** `all()`.

### `listBlocks($opts)`

**Call:** `DotApp::call('{Module}:PageBuilderContract@listBlocks!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `page` | int | 1-based page. Invalid → 1 |
| `q` | string | Optional name filter (bound `LIKE`, not raw SQL). Empty = no filter |
| `kind` | string | Optional `block` \| `section`. Empty = this pack’s extra3 default. Unknown → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',
            'name' => 'Hero banner',
            'kind' => 'block',          // block | section
            'label' => 'Full-width title', // short; host escapes
        ],
    ],
    'page' => 1,
    'last_page' => 3,
    'total' => 41,
]
```

`last_page` comes from `COUNT(*)` of the filtered set, not `count($items)`.

**Failure:** unknown `kind`, decrypt fail on a filter the pack does not use → `ok:false`.

**MUST NOT** return block HTML, PHP class names, or a dump of the schema.

### `renderPage($pageRef)`

**Call:** `DotApp::call('{Module}:PageBuilderContract@renderPage!', $pageRef)`

**Input:**

| Argument | Type | Meaning |
|----------|------|---------|
| `$pageRef` | string | Encrypted pack page id or pack-stable token. Empty / decrypt `false` → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'html' => '<article class="pagekit_page">…</article>',
]
```

`html` is a fragment the host inserts. The pack builds it with Renderer templates ([05](../05-VIEWS-TEMPLATES-ASSETS.md) §1c) — **MUST NOT** `$html .= '<div class='` a whole page in the controller.

Operator-entered strings inside the fragment are `htmlspecialchars` in the pack before the template. **MUST NOT** `eval` `$pageRef` or the stored document.

**Failure:** unknown page, decrypt fail, unpublished when the pack requires a public flag → `['ok' => false, 'message' => 'This page could not be rendered.']`.

---

## 5. Encrypted block ids (**MUST**)

Every block id in `listBlocks` items, every page ref in admin HTML, and any `data-*` the builder JS posts **MUST** be ciphertext (`{{ enc(PageKit.block.id): $id }}`, unique `$key2`). Pager `data-page` is ciphertext when the pack ships an admin list ([40](../40-DACORE-LIST-PAGER.md)).

Decrypt `=== false` → `ok:false`. Still check rights / ownership in PHP.

Plain `value="7"` / `data-id="7"` / `{{ var: $id }}` as an id is a fail.

---

## 6. Paging (`listBlocks`)

`listBlocks` is a growing catalogue. **MUST** `COUNT(*)` + `LIMIT` / `OFFSET`. `page_size` from `capabilities` (host **MAY** pass a smaller `page` only — it **MUST NOT** pass a page size that loads the whole table).

`last_page` is from the count, not `count($items)`.

If the pack also has an admin HTML list, that screen follows [40](../40-DACORE-LIST-PAGER.md) (`dacore-list-pager`, encrypted `data-page`, `$dotapp().live` first arg is the element). The in-process helper still has **no** CRC.

---

## 7. Host page vs builder pack

The host owns the public URL and `withMenu` / public layout. After pick it calls `renderPage` and inserts `html`.

Optional `builder_js`: host loads it via `$css` / `$js`. The pack implements `$dotapp().fn` — **MUST NOT** copy DACore JS. Saves on the builder screen are pack HTTP (`/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`); those actions **MUST NOT** `crcCheck()` again. Then the action **MAY** persist and later `renderPage` stays in-process.

`editor` is a different `extra1`. A page-builder pack **MUST NOT** set `extra1=editor`. A `<Host>` **MAY** pick both modules.

After AJAX save on the same page: patch `reply.html` + toast — **MUST NOT** `location.reload()`. Overlay until the request ends.

---

## 8. Hooks

Fire only after a useful persist — **not** on `listBlocks` / `renderPage`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.page_published.hook` | Page published or replaced for public render | `page_id`, `block_count` |

**MUST NOT** put HTML, block JSON, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

A draft autosave **MUST NOT** fire this hook (flood).

---

## 9. MUST NOT

- Invent `extra1` (`builder`, `gutenberg`, `landing-builder`)
- Set `extra1=editor` or `extra1=template` on this pack
- `glob('app/modules')` or `include` the pack to discover it
- Return plaintext numeric block or page ids in HTML
- `all()` the block catalogue
- `eval` / `unserialize` the page document
- Leak `getMessage()` or stored HTML on the hook bus
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `listBlocks` uses `COUNT` + `LIMIT`; `last_page` is not `count($items)`
- Every block / page id in HTML is encrypted
- `renderPage($pageRef)` returns `{ok, html}` via Renderer
- Hooks named in `.hooks` if a publish persist fires
- No `crcCheck()` on `capabilities` / `listBlocks` / `renderPage`
