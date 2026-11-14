# 46 — `sitemap` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `seo` (per-URL meta) and **not** `newsletter`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `sitemap` |
| `extra2` | `v1` |
| `extra3` | `xml` \| `rss` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'sitemap',
'extra2' => 'v1',
'extra3' => 'xml',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'sitemap', 'v1');
$xml = DotApp::call('DACore:Plugins@listByContract!', 'sitemap', 'v1', 'xml');
```

| extra3 | Meaning |
|--------|---------|
| `xml` | Sitemap XML (`urlset` / sitemap index). `build` returns `{ok, xml}` or `{ok, url}` |
| `rss` | RSS / Atom feed XML. Same return shape. `build` with `format=xml` when the pack is `rss`-only → `ok:false` |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a content-management host family |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`index`, `news`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:SitemapContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=sitemap` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('sitemap','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':SitemapContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:SitemapContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SiteMap',           // exact module name
    'modes' => ['xml'],              // extra3 this pack actually implements
    'formats' => ['xml'],            // xml and/or rss this build() accepts
    'max_urls_per_build' => 50000,
    'page_size' => 1000,             // internal walk size — not an all() dump
    'public_url' => true,            // false when build never returns url
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Sitemap is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing catalogues: **MUST NOT** `all()`.

### `build($opts)`

**Call:** `DotApp::call('{Module}:SitemapContract@build!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `format` | string | `xml` \| `rss` — must be in `capabilities.formats` and match a mode the pack implements |
| `type` | string | Optional host family: `article` \| `product` \| `page` \| `all`. Unknown → `ok:false`. Default `all` |
| `return` | string | `inline` (default) → `xml` string. `url` → public file under the pack `assets/` |
| `page` | int | Optional 1-based chunk when the pack splits a sitemap index. Invalid → 1. **MUST NOT** mean “load everything” |

**Success (inline):**

```php
[
    'ok' => true,
    'xml' => '<?xml version="1.0" encoding="UTF-8"?>…',
    'url_count' => 120,
]
```

**Success (stored file):**

```php
[
    'ok' => true,
    'url' => '/assets/modules/SiteMap/sitemap.xml',
    'url_count' => 120,
]
```

`url` is allowed **only** under `/assets/modules/{Module}/…`. Runtime / `app/runtime` is never a public sitemap URL.

Exactly one of `xml` or `url` is set on success. **MUST NOT** return both plus a tracker snippet.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not build this sitemap.',
]
```

Unknown format, unknown type, write fail, rights, over `max_urls_per_build` without paging. **MUST NOT** leak `getMessage()`, disk paths, or dump XML on the error bus.

---

## 5. Internal paging (**MUST**)

`build` walks the host or pack source with `COUNT` + `LIMIT` (or repeated `page_size` queries) until the chunk is full or the set ends.

**MUST NOT** `$qb->all()` an unbounded article / product / URL table and then concatenate XML in PHP.

A sitemap index of many files is built the same way: one page of URLs per file, then an index document. Memory: stream or append; **MUST NOT** keep every row array after mapping (`unset` the raw copy).

`url_count` is an integer for hooks and the operator toast. It is not a substitute for `COUNT` during the walk.

---

## 6. XML content and headers

The pack owns well-formed XML. Locs are paths or absolute URLs the host configured — **MUST NOT** put `$request` data into those locs, `header()`, or `HttpHelper` URLs.

The host that prints `xml` sets `Content-Type` to `application/xml` (or `application/rss+xml`) on **its** route. The pack helper does not send headers.

Format / type pickers = native `<select>` of `formats[]` and the `type` whitelist — **MUST NOT** a free-text format box.

---

## 7. Host trigger and public GET

Typical host: cron or admin “Rebuild” → `build(['format' => 'xml', 'return' => 'url'])` → toast. Overlay the card until the request ends. **MUST NOT** `location.reload()`.

Public `GET /sitemap.xml` may `build` inline **or** redirect to the stored `url`. That GET has **no** CRC.

Admin rebuild POST stays on `/api/v1/auth/{Host|Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again. Contract helpers stay **no CRC**.

When `public_url` is `false`, `return=url` → `ok:false`. Use `inline`.

Host **MUST** call `build` in-process after pick:

```php
$reply = DotApp::call($module . ':SitemapContract@build!', $opts);
if (!is_array($reply) || empty($reply['ok'])) {
    // toast — MUST NOT dump XML
}
```

---

## 8. Hooks

Fire only after a useful persist or a completed rebuild — **not** on a dry capabilities call.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.sitemap_built.hook` | Build finished | `format`, `url_count`, `return` (`inline`\|`url`) |

**MUST NOT** put the XML body, loc lists, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`feed`, `rss`, `robots`)
- `glob('app/modules')` or `include` the pack to discover it
- `all()` an unbounded URL / article / product table
- Return a public URL outside `/assets/modules/{Module}/`
- Leak `getMessage()` or dump XML on the hook bus
- Put `$request` data into loc URLs or `header()`
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=sitemap` on the host

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `build` pages internally (`page_size` / `COUNT` + `LIMIT`)
- Success is `{ok, xml}` **or** `{ok, url}` under pack assets
- Every method has input table + success/fail PHP arrays
- Hooks named in `.hooks` if fired; payload is counts/format only
- No `crcCheck()` on `capabilities` / `build`
