# 46 — `image-optimizer` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for an image optimizer pack. A host (CMS, Shop) and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** `filemanager` (browse / picker) and **not** `storage` (object put/get). Listing and picking files uses [filemanager.md](filemanager.md) (`MediaContract`).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `image-optimizer` |
| `extra2` | `v1` |
| `extra3` | `local` \| `remote` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'image-optimizer',
'extra2' => 'v1',
'extra3' => 'local',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'image-optimizer', 'v1');
$local = DotApp::call('DACore:Plugins@listByContract!', 'image-optimizer', 'v1', 'local');
```

| extra3 | Meaning |
|--------|---------|
| `local` | Read bytes from an encrypted file / pack id (or a filemanager id the host already resolved). **MUST NOT** accept a raw URL |
| `remote` | May fetch `https://` **only** when the URL host is on the operator allowlist in **pack** settings |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** put a worker URL, API key, or allowlist host here |

A pack that implements both sets `extra3` to the **primary** mode in `about.php` and lists both in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:ImageOptContract@…!`

The **host** **MUST NOT** set `extra1=image-optimizer` on itself.

The optimizer **MUST NOT** invent `extra1` (`images`, `thumbnails`, `imgopt`, `tinypng`).

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('image-optimizer','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ImageOptContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ImageOptContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'ImgOpt',            // exact module name
    'modes' => ['local'],            // extra3 this pack actually implements
    'formats' => ['jpeg', 'png', 'webp'], // output whitelist
    'max_width' => 4096,
    'max_height' => 4096,
    'accepts_url' => false,          // true only when extra3 includes remote AND allowlist is non-empty
    'stores_variant' => true,        // true when optimize() returns an encrypted id
]
```

**MUST NOT** return API keys, remote worker secrets, or raw disk paths.

**Failure:** `['ok' => false, 'message' => 'Image optimizer is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Variant tables that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `optimize($sourceRef, $opts)`

**About:** Produce a resized / recompressed variant. The host names the source; the pack owns the encoder.

**Call:** `DotApp::call('{Module}:ImageOptContract@optimize!', $sourceRef, $opts)`

**Input** `$sourceRef` string:

| extra3 | Allowed `$sourceRef` |
|--------|----------------------|
| `local` | Encrypted pack id **or** encrypted filemanager / host file id the pack can resolve in-process. **MUST NOT** a raw `http(s)://` URL, `file://`, or absolute disk path |
| `remote` | Same ids as `local`, **or** an `https://` URL whose host is on the pack-settings allowlist |

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `width` | int | Target width in px. `0` = keep source. Cap at `max_width`. Invalid → `ok:false` |
| `height` | int | Target height in px. `0` = keep source. Cap at `max_height` |
| `format` | string | Whitelist only: `jpeg` \| `png` \| `webp` \| `avif` \| `gif` — must be in `capabilities()['formats']` |

Missing `format` → pack default from its settings (still a whitelist value). Other keys **MUST** be ignored (no request-spread into the encoder).

**Success — at least one of `id` or `url`:**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',  // encrypted variant id when stores_variant
    'url' => '/assets/modules/ImgOpt/var/ab12.webp', // public URL or ''
    'width' => 800,
    'height' => 450,
    'format' => 'webp',
]
```

`url` is allowed **only** when the file is under `/assets/modules/{Module}/…`. Runtime / non-public variants return `url` `''` and a usable `id`.

When `stores_variant` is false, `id` **MAY** be `''` if `url` is a public assets path. When `stores_variant` is true, `id` **MUST** be ciphertext.

**Failure:** decrypt fail; unknown id; `local` pack given a URL; `remote` URL host not allowlisted; illegal format / size; encoder error → `ok:false`. **MUST NOT** leak `getMessage()` or the fetched URL.

---

## 5. SSRF (**MUST**)

The **host** passes an **id**, not a raw URL, unless `extra3=remote` **and** the URL is allowlisted in **pack** settings.

Pack rules when `accepts_url` is true:

- Scheme `https` only (no `http`, `file`, `gopher`, `data`).
- Host **exact-match** (or configured parent suffix) against the operator allowlist. Empty allowlist → treat as `local` (`accepts_url` false).
- **MUST NOT** follow redirects to a host off the list.
- **MUST NOT** send pack secrets as query string to the remote.
- Timeouts and a max byte size live in the pack. Oversized body → `ok:false`.

`extra1`…`extra5` **MUST NOT** hold allowlist hosts, API keys, or worker URLs.

---

## 6. HTTP (pack-owned, not `ImageOptContract`)

Admin “Optimize” on the pack’s page is a normal POST: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. That action **MUST NOT** `crcCheck()` again. Upload of a new source, if any, is `$dotapp().uploadFile` + `$request->upload()` — **MUST NOT** `crcCheck()` on upload. Reject `.php` / executables (extension + `finfo` + headers).

The in-process `optimize!` helper has **no** CRC.

---

## 7. Ids and jail (**MUST**)

Encrypted ids in HTML: `{{ enc(ImgOpt.variant.id): $id }}` (unique `$key2`). Decrypt `false` → reject. Still check rights / ownership in PHP.

A local source file **MUST** sit in a pack jail, a filemanager jail the host already picked, or `/assets/modules/{InstalledModule}/…`. **MUST NOT** read `app/config.php` or arbitrary disk paths from the request.

---

## 8. Hooks

Fire only after a useful persist — **not** on `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.image_optimized.hook` | Variant stored | `id`, `format`, `width`, `height` |

**MUST NOT** put bytes, source URLs, absolute paths, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`images`, `thumbnails`, `imgopt`, `tinypng`)
- Accept a raw URL in `local` mode
- Fetch a remote URL unless `extra3=remote` and the host is allowlisted in pack settings
- Put allowlists, API keys, or worker URLs in extras
- Return a public URL for a runtime / non-assets file
- Leak `getMessage()`, disk paths, or request bodies
- `all()` on a growing variant table
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Host passes encrypted ids; raw URLs only for allowlisted `remote`
- Every public HTML id is encrypted
- `url` only under `/assets/modules/{Module}/…`
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `optimize`
