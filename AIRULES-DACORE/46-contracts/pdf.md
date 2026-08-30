# 46 — `pdf` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a PDF-render pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `pdf` |
| `extra2` | `v1` |
| `extra3` | `render` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'pdf',
'extra2' => 'v1',
'extra3' => 'render',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'pdf', 'v1');
$render = DotApp::call('DACore:Plugins@listByContract!', 'pdf', 'v1', 'render');
```

| extra3 | Meaning |
|--------|---------|
| `render` | Pack fills a **shipped** template and returns a download id or URL. No user-uploaded PHP templates |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No v1 qualifier. **MUST NOT** invent `extra5` tokens (`html`, `wkhtml`, `dompdf`) |

**Kind:** peer. **Controller:** `{Module}:PdfContract@…!`

The **host** **MUST NOT** set `extra1=pdf` on itself.

This role is **not** `invoice` (sales document API), **not** `label` (shipping label), **not** `print` (queue a printer), and **not** `filemanager`. Those packs may **call** this pack after the operator picked it.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('pdf','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PdfContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PdfContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PdfEngine',          // exact module name
    'modes' => ['render'],            // extra3 this pack actually implements
    'template_keys' => [              // whitelist — host <select>, not a typed path
        'invoice.v1',
        'packing.v1',
        'letter.v1',
    ],
    'data_keys' => [                  // union of allowed $data keys across templates
        'title',
        'number',
        'issued_on',
        'lines_count',
        'total',
    ],
    'max_data_bytes' => 65536,
    'download_ttl_seconds' => 3600,
]
```

A pack **MAY** document per-template key sets in its own admin help. v1 `render()` still rejects any `$data` key that is not in `data_keys`.

**Failure:** `['ok' => false, 'message' => 'PDF renderer is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing download tables: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `render($templateKey, $data)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$templateKey` | string | **MUST** be one of `capabilities()['template_keys']`. Unknown / path / `..` → `ok:false` |
| `$data` | array | Values for the template. **Every** key **MUST** be in `data_keys`. Extra keys → `ok:false` (do not silently drop in a way that hides a host bug — reject) |

**`$data` value rules (v1):**

- Scalars (`string`, `int`, `float` as decimal **strings** for money, `bool`) or a bounded list of arrays of scalars (line items).
- String length and `strlen(json_encode($data))` **MUST** stay ≤ `max_data_bytes`.
- **MUST NOT** PHP source, closures, file paths, SQL, HTML from `$request->data()` without the host having escaped/sanitized it first.
- Money **MUST** be decimal strings, not `float`.
- Encrypted host ids inside `$data` stay encrypted until the **host** decrypted them in PHP and passed a safe display string.

**Success — stored download:**

```php
[
    'ok' => true,
    'download_id' => '…ciphertext…',
]
```

**Success — URL** (instead of `download_id`, or both):

```php
[
    'ok' => true,
    'url' => '/api/v1/auth/PdfEngine/pdf-download', // pack auth download, not runtime
]
```

When both are present, `url` **MUST** still require login + rights + the encrypted `download_id` (query or POST). A public `/assets/modules/{Module}/…` URL is allowed only for a non-secret sample; live invoices **MUST** be auth-gated.

**Failure:** unknown template, illegal data key, oversize, render error → `ok:false`. **MUST NOT** leak `getMessage()` from the PDF library.

---

## 5. Templates (**MUST**)

- Templates **ship inside the pack** (`views/` or pack assets). Operator picks a **key** from `template_keys`.
- **MUST NOT** accept a user-supplied PHP file, Blade/Twig path, `include $x`, or HTML that the pack `eval`s.
- **MUST NOT** `Renderer` a view name built from `$request->data()`.
- HTML that becomes a PDF **MUST** escape host strings (`htmlspecialchars`) inside the pack before layout.
- Pack **MUST NOT** execute `$data` values as PHP (`eval`, `create_function`, variable-variables of callables).

Host template picker = native `<select>` / `dotSelect2` of `template_keys`. **MUST NOT** a bare text path.

---

## 6. Host download UI

- After `render()`, overlay until the reply arrives. Toast success / fail.
- Download: `$dotapp().load()` or a same-origin link that POSTs the encrypted `download_id` to the pack auth route. **MUST NOT** `FormData` + `load()` of the PDF bytes as an upload.
- **MUST NOT** `location.reload()` only to fetch the file.
- HTML that shows `download_id` uses `{{ enc(...) }}` — never a plain integer.

---

## 7. Hooks

Fire only after a useful persist — **not** on a failed render, **not** on `capabilities()`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.pdf_rendered.hook` | Pack stored a downloadable PDF | `download_id`, `template_key` |

**MUST NOT** put PDF bytes, `$data`, or secrets in the payload. Skip the hook when the pack returns only an ephemeral `url` and stores nothing. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`pdf-render`, `dompdf`, `wkhtml`, `invoice-pdf`)
- Invent `extra3` (`html`, `merge`, `form-fill`) — v1 is `render` only
- `glob('app/modules')` or `include` the pack to discover it
- User-supplied PHP templates or `include` of a request path
- `eval` of `$templateKey` or `$data`
- `all()` on a growing download table
- Public URL under `app/runtime`
- Leak `getMessage()`, disk paths, or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `template_keys` and `data_keys` are whitelists; unknown → `ok:false`
- Every public HTML `download_id` is encrypted
- No user-supplied PHP templates
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `render`
