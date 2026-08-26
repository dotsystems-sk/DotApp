# 46 — `seo` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop) and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `analytics` (pixels) and **not** `sitemap`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `seo` |
| `extra2` | `v1` |
| `extra3` | `meta` \| `schema` \| `full` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'seo',
'extra2' => 'v1',
'extra3' => 'full',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'seo', 'v1');
$full = DotApp::call('DACore:Plugins@listByContract!', 'seo', 'v1', 'full');
```

| extra3 | Meaning |
|--------|---------|
| `meta` | `title`, `description`, `canonical` only. `jsonld` omitted or null |
| `schema` | Structured data (`jsonld`). Title / description / canonical **MAY** be empty strings |
| `full` | Meta + `jsonld` |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`opengraph`, `twitter`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:SeoContract@…!`

The **host** (CMS, Shop) **MUST NOT** set `extra1=seo` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('seo','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':SeoContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:SeoContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SeoKit',            // exact module name
    'modes' => ['full'],             // extra3 this pack actually implements
    'jsonld' => true,                // false when extra3=meta
    'canonical' => true,
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'SEO is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing override tables: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `metaFor($urlOrRef)`

**Call:** `DotApp::call('{Module}:SeoContract@metaFor!', $urlOrRef)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$urlOrRef` | string | Public path (`/blog/hello`) **or** a pack-stable ref the host already authorized (`article:12` as ciphertext if it left HTML). Empty → `ok:false`. **MUST NOT** be a full absolute URL taken from `$request` and passed into `header()` / redirect / `HttpHelper` |

The host resolves the current page, then calls this helper. **MUST NOT** pass raw query input as a URL the pack will fetch.

**Success (`full` / `meta`):**

```php
[
    'ok' => true,
    'title' => 'Autumn catalogue',
    'description' => 'Wool layers for the season.',
    'canonical' => '/shop/autumn',
    'jsonld' => [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Autumn catalogue',
    ],
]
```

**Success (`meta`):** same keys; `jsonld` is omitted or `null`.

**Success (`schema`):** `jsonld` required; `title` / `description` / `canonical` **MAY** be `''`.

`canonical` is a **path or same-origin URL** the host already would print. The pack **MUST NOT** return `javascript:` or a tracker query string.

`jsonld` is a **PHP array** the host `json_encode`s into `<script type="application/ld+json">`. **MUST NOT** be a string of arbitrary HTML.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'No SEO record for this page.',
]
```

Unknown ref, decrypt fail, not found. **MUST NOT** leak whether another owner’s record exists. **MUST NOT** `getMessage()`.

---

## 5. Host escaping (**MUST**)

`{{ var: }}` does **not** escape. The **host** **MUST** `htmlspecialchars` `title`, `description`, and `canonical` before the template (or put them in attributes with the same escape).

`jsonld` goes through `json_encode` (Unicode flags the host already uses) — **not** `{{ var: }}` of a pre-built `<script>` string.

JS must not `.html()` these strings.

---

## 6. No tracker scripts (**MUST**)

`metaFor` returns text and optional schema. It **MUST NOT** return:

- analytics / pixel / tag-manager snippets
- `extra1=analytics` payloads
- remote `<script src>`
- hidden iframes

Pixels belong on `analytics`. Feeds belong on `sitemap`.

---

## 7. Persist (optional pack admin)

v1 has no `save` method. If the pack stores operator overrides on its own admin pages, that HTTP stays on `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again. Those pages are not this contract. Contract helpers stay **no CRC**.

A persist **MAY** fire `seo_saved` (§8). `metaFor` itself **MUST NOT** fire a hook (it is a read).

Override browse **MUST** paginate if the set grows ([40](../40-DACORE-LIST-PAGER.md)).

Host **MUST** call `metaFor` in-process after pick:

```php
$reply = DotApp::call($module . ':SeoContract@metaFor!', $urlOrRef);
if (!is_array($reply) || empty($reply['ok'])) {
    // omit tags — MUST NOT leak $urlOrRef internals
}
```

---

## 8. Hooks

Fire only after a useful persist — **not** on `metaFor`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.seo_saved.hook` | Operator override stored | `ref` (path or encrypted target), `has_jsonld` (0\|1) |

**MUST NOT** put title, description, jsonld, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`meta`, `schema`, `opengraph`)
- `glob('app/modules')` or `include` the pack to discover it
- Return tracker scripts or remote script tags
- Leak `getMessage()` or put user input into `header()` / redirect
- Fire a hook on every `metaFor`
- Return `jsonld` as an HTML string
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=seo` on the host

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Host `htmlspecialchars` title / description / canonical
- `jsonld` is an array, not HTML
- No tracker scripts in the reply
- Every method has input table + success/fail PHP arrays
- Hooks named in `.hooks` if a persist fires; `metaFor` is silent
- No `crcCheck()` on `capabilities` / `metaFor`
