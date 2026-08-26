# 46 — `barcode` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop, ERP, warehouse) and a barcode-render pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** `label` (shipping label PDF) and **not** `pdf` (document render). A barcode pack only encodes a short payload into an image.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `barcode` |
| `extra2` | `v1` |
| `extra3` | `code128` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'barcode',
'extra2' => 'v1',
'extra3' => 'code128',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'barcode', 'v1');
$code128 = DotApp::call('DACore:Plugins@listByContract!', 'barcode', 'v1', 'code128');
```

| extra3 | Meaning |
|--------|---------|
| `code128` | Code 128 symbology only. `render()` draws that barcode. **MUST NOT** invent `qr`, `ean13`, `upc` as `extra3` in v1 |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No v1 qualifier. **MUST NOT** invent `png` / `svg` as `extra5` (those are `formats` on `capabilities()`) |

**Kind:** peer. **Controller:** `{Module}:BarcodeContract@…!`

The **host** **MUST NOT** set `extra1=barcode` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('barcode','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':BarcodeContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:BarcodeContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Barcode',            // exact module name
    'modes' => ['code128'],           // extra3 this pack actually implements
    'formats' => ['png', 'svg'],      // whitelist for render() $format
    'max_payload_len' => 80,          // Code 128 practical cap; pack MUST reject longer
    'max_width' => 600,
    'max_height' => 200,
    'url_ttl_seconds' => 300,         // when render() returns url; 0 = no URL mode
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Barcode renderer is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`.

### `render($payload, $format)`

**Call:** `DotApp::call('{Module}:BarcodeContract@render!', $payload, $format)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$payload` | string | Characters to encode. Length **MUST** be `>= 1` and `<= max_payload_len`. Pack strips only what Code 128 cannot encode — **MUST NOT** `eval`, `include`, or treat the string as PHP/JS |
| `$format` | string | Image format from `capabilities()['formats']`. Typical whitelist: `png` \| `svg`. Unknown → `ok:false` |

**Success — inline bytes:**

```php
[
    'ok' => true,
    'mime' => 'image/png',
    'bytes_b64' => 'iVBORw0KGgo…',
]
```

**Success — URL** (instead of, or in addition to, `bytes_b64` when the pack stores a short-lived file):

```php
[
    'ok' => true,
    'mime' => 'image/svg+xml',
    'url' => '/assets/modules/Barcode/tmp/…',  // or a rights-checked auth download
]
```

The pack **MUST** return `mime` and **at least one** of `bytes_b64` or `url`. A public `url` is allowed **only** under `/assets/modules/{Module}/…` (or a pack auth download). **MUST NOT** a path under `app/runtime` as a public URL.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not render this barcode.',
]
```

Empty payload, over `max_payload_len`, illegal characters for Code 128, unknown format. **MUST NOT** leak `getMessage()`, raw disk paths, or the payload on an error log that leaves the pack.

---

## 5. Host display (**MUST**)

If the host shows the payload next to the image (SKU, tracking number, warehouse code):

- PHP: `htmlspecialchars($payload, ENT_QUOTES, 'UTF-8')` before `{{ var: }}` — the tag does **not** escape.
- JS: `.text()` — **MUST NOT** `.html()` of the payload.

The image: `<img alt="">` with a safe `src` from `url`, or a `data:{mime};base64,{bytes_b64}` built in PHP after the pack returned both. Host **MUST NOT** concatenate `$payload` into `src`.

Format choice = native `<select>` from `capabilities()['formats']`. **MUST NOT** a free-text format box.

---

## 6. URLs, TTL, and HTTP

When `url_ttl_seconds` is `0`, `render` **MUST NOT** return `url` (inline `bytes_b64` only).

A stored file under pack `assets/` **MAY** be deleted after TTL. Host **MUST** treat a missing URL as `ok:false` on a later fetch — do not guess a path.

Contract helpers have **no CRC**. Pack admin “preview” POST stays on `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. The action **MUST NOT** `crcCheck()` again.

---

## 7. Payload safety v1 (**MUST**)

- **MUST NOT** `eval` / `exec` / `unserialize` / `create_function` / `include` of `$payload` or `$format`.
- **MUST NOT** treat `$format` as a file extension from the request without the `formats` whitelist.
- Width / height (if the pack draws) stay inside `max_width` / `max_height`.
- v1 has **no** `BarcodeContract@list`. Stored files (if any) are pack admin, paged if the set grows.

Host **MUST** call `render` in-process after pick:

```php
$reply = DotApp::call($module . ':BarcodeContract@render!', $payload, $format);
if (!is_array($reply) || empty($reply['ok'])) {
    // toast — MUST NOT eval $payload
}
```

---

## 8. Hooks

`render()` is a draw, not a business persist. **MUST NOT** fire a hook on every barcode.

If the pack **stores** a durable labeled file that another module would archive, it **MAY** fire one event — only then:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.barcode_stored.hook` | Pack persisted a file (not an ephemeral draw) | `id`, `format`, `bytes` (size int, **not** the image) |

**MUST NOT** put `bytes_b64`, the payload string, or disk paths in the payload. Default v1 packs skip this hook. Document in `.hooks` if fired. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`barcodes`, `code128`, `qr`, `zxing`)
- Invent `extra3` (`qr`, `ean13`, `upc`, `datamatrix`) — v1 is `code128` only
- `glob('app/modules')` or `include` the pack to discover it
- `eval` / `exec` / `unserialize` / `create_function` of `$payload` or `$format`
- Return a public URL for `app/runtime`
- Leak `getMessage()` or request bodies
- Skip the payload length cap
- Show `$payload` in HTML without `htmlspecialchars` / `.text()`
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=barcode` on the host

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `render()` enforces `max_payload_len` and a format whitelist
- Host escapes payload if it is shown
- Every method has input table + success/fail PHP arrays
- No `eval` of payload
- No hook on ephemeral `render`; `barcode_stored` named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `render`
