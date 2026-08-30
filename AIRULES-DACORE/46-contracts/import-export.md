# 46 — `import-export` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a data-exchange pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `import-export` |
| `extra2` | `v1` |
| `extra3` | `csv` \| `xml` \| `feed` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'import-export',
'extra2' => 'v1',
'extra3' => 'csv',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'import-export', 'v1');
$csv = DotApp::call('DACore:Plugins@listByContract!', 'import-export', 'v1', 'csv');
```

| extra3 | Meaning |
|--------|---------|
| `csv` | Delimited files. Host uploads via `$dotapp().uploadFile` to `upload_url` |
| `xml` | XML documents. Same upload path. Pack **MUST** parse with a real XML parser — **MUST NOT** `eval` / regex-execute |
| `feed` | Operator-configured remote feed. `upload_url` **MAY** be `''`. Import uses an encrypted `feed_id` from the pack’s own settings — **MUST NOT** a typed URL from the request (SSRF) |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No v1 qualifier. **MUST NOT** invent `extra5` tokens (`xlsx`, `edi`, `json`) |

**Kind:** peer. **Controller:** `{Module}:ImportExportContract@…!`

The **host** **MUST NOT** set `extra1=import-export` on itself.

This role is **not** `filemanager` (jail / picker), **not** `storage` (object put/get), and **not** `report` (tabular run). It exchanges host records in a declared format.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('import-export','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ImportExportContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ImportExportContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'DataPort',           // exact module name
    'modes' => ['csv'],               // extra3 this pack actually implements
    'upload_url' => '/api/v1/auth/DataPort/import-upload', // '' when extra3=feed only
    'max_upload_bytes' => 5242880,
    'accept_mimes' => ['text/csv', 'text/plain', 'application/xml', 'text/xml'],
    'export_page_size' => 100,        // hard cap for export() / import() pages
    'export_stream' => true,          // false when the pack only returns download_id
    'kinds' => ['products', 'orders'], // whitelist for export $kind — not request-free text
]
```

`upload_url` is empty when the pack cannot accept a file (feed-only). Host **MUST** hide the upload control then.

**Failure:** `['ok' => false, 'message' => 'Import and export is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing jobs / rows: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `import($opts)`

Starts or continues a job from an **already stored** upload or feed. The file itself arrives on HTTP (§5) — **MUST NOT** pass file bytes through `DotApp::call`.

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `upload_id` | string | Encrypted id from the upload action. Required for `csv` / `xml` unless `job_id` is set |
| `feed_id` | string | Encrypted pack feed id. Required for `extra3=feed` unless `job_id` is set. **MUST NOT** a raw URL |
| `job_id` | string | Encrypted job id to continue a paged import |
| `kind` | string | Whitelist from `capabilities()['kinds']` (or pack-fixed). Unknown → `ok:false` |
| `page` | int | 1-based page of source rows. Invalid → 1 |
| `mapping` | array | Optional column map. Keys and values **MUST** be pack-whitelisted identifiers — **MUST NOT** SQL, PHP, or file paths |

**Success (running / paged):**

```php
[
    'ok' => true,
    'job_id' => '…ciphertext…',
    'state' => 'running',   // queued | running | done | failed
    'page' => 1,
    'last_page' => 4,
    'rows_ok' => 80,
    'rows_fail' => 2,
    'total' => 320,         // COUNT of source rows — not count($pageRows)
]
```

**Success (finished):** `state` = `done`, same pager fields, `last_page` ≥ 1.

**Failure:** unknown kind, decrypt fail, executable rejected earlier, mapping illegal, feed missing → `ok:false`.

The pack **MUST** process one page per call (or a bounded internal chunk ≤ `export_page_size`). **MUST NOT** load the whole file into one `all()` / one in-memory array of unbounded rows.

### `export($kind, $filters, $page)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$kind` | string | Whitelist from `capabilities()['kinds']`. Unknown → `ok:false` |
| `$filters` | array | Optional filters. Keys **MUST** be pack-whitelisted (`status`, `from`, `to`, …). Values bound in SQL — **MUST NOT** raw SQL or sort-column names from the request |
| `$page` | int | 1-based page. Invalid → 1 |

**Success — stream page** (when `export_stream` is true):

```php
[
    'ok' => true,
    'kind' => 'products',
    'page' => 1,
    'last_page' => 6,
    'total' => 541,
    'rows' => [
        ['sku' => 'A-1', 'qty' => '3'],
    ],
]
```

`rows` is **this page only**. Host may write a file locally. **MUST NOT** return the entire catalogue in one call.

**Success — stored file** (always allowed; required when `export_stream` is false):

```php
[
    'ok' => true,
    'download_id' => '…ciphertext…',
    'page' => 1,
    'last_page' => 6,
    'total' => 541,
]
```

The pack **MAY** return both a page of `rows` and a `download_id` after the last page. Host downloads the file on a pack `/api/v1/auth/{Module}/…` route with encrypted `download_id` and rights. **MUST NOT** a public unauthenticated URL for a data dump.

**Failure:** unknown kind, illegal filter, decrypt fail → `ok:false`.

---

## 5. HTTP import upload (not `ImportExportContract@import` as HTTP)

Same pattern as [filemanager.md](filemanager.md) §5.

Host **MUST** `$dotapp().uploadFile` to `upload_url` from `capabilities()`. Pack PHP: `$request->upload()` — **MUST NOT** `crcCheck()`. Reject `.php` / executables (extension + `finfo` + headers). `accept=` is UX only.

CRC on the upload **route** is `#DACore:AuthTest@LoginAndCRC!` on `/api/v1/auth/{Module}/…` if the action is a normal POST that is **not** `$request->upload()`. Upload actions skip CRC.

Upload success JSON (pack action, not the in-process helper):

```php
[
    'ok' => true,
    'upload_id' => '…ciphertext…',
]
```

Host then calls `ImportExportContract@import!` with that `upload_id`. **MUST NOT** send the file bytes through `form()` / `load()` / `FormData`.

Feed mode: no upload. Operator configures the feed on the **pack** admin (`<select>` of stored feeds). Host passes only the encrypted `feed_id`. Pack **MUST NOT** `HttpHelper` a URL taken from `$request->data()`.

---

## 6. Host UI

- Import: overlay the card for the duration of `uploadFile` and each `import()` page. Toast on success and fail. Patch job status from JSON — no `location.reload()`.
- Export: overlay while `export()` runs. If `download_id` is set, trigger a rights-checked download; do not dump `rows` into `innerHTML` without `htmlspecialchars` / `.text()`.
- Kind / format = native `<select>` from `capabilities()['kinds']` and `modes`. **MUST NOT** a bare text box for a known format.
- Growing job history on the pack admin **MUST** paginate ([40](../40-DACORE-LIST-PAGER.md)).

---

## 7. Hooks

Fire only after a useful persist — **not** on `capabilities()`, **not** on every export page while `state` is still running.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.import_finished.hook` | Import job reaches `done` or `failed` | `job_id`, `rows_ok`, `rows_fail`, `state` |
| `module.{mod}.export_ready.hook` | Pack persisted a downloadable export | `download_id`, `kind`, `total` |

**MUST NOT** put file bytes, CSV/XML bodies, filters, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`importexport`, `csv`, `xlsx`, `etl`, `sync`)
- Invent `extra3` (`xlsx`, `json`, `edi`) — v1 is `csv` \| `xml` \| `feed`
- `glob('app/modules')` or `include` the pack to discover it
- `all()` on a growing source, job, or export table
- Pass a user-typed URL into `HttpHelper` / `header()` / redirect
- `crcCheck()` on `$request->upload()`
- Leak `getMessage()`, disk paths, or request bodies
- `eval` / `exec` / `unserialize` of CSV/XML cells
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML id (`upload_id`, `job_id`, `download_id`, `feed_id`) is encrypted
- Import files use `$dotapp().uploadFile` + `$request->upload()`; executables rejected
- `export()` is paged — no unbounded `all()`
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `import` / `export` / upload
