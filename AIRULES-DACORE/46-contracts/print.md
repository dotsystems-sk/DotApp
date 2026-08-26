# 46 — `print` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **v1 peer contract** for reserved role `print`. A host (CMS, Shop, ERP, POS) and a print-queue pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). Machine catalog: `DACore\Libraries\ExtraContracts` role `print`, controller `PrintContract`, methods `capabilities`, `submit`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `print` |
| `extra2` | `v1` |
| `extra3` | `job` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'print',
'extra2' => 'v1',
'extra3' => 'job',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'print', 'v1');
$job = DotApp::call('DACore:Plugins@listByContract!', 'print', 'v1', 'job');
```

| extra3 | Meaning |
|--------|---------|
| `job` | Host submits one print job (`document` + `printer` + `copies`). Pack queues it and returns `job_id`. **MUST NOT** invent `raw`, `zpl`, or `cups` as extra3 in v1 |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No v1 qualifier. **MUST NOT** invent `extra5` tokens (`cups`, `raw`, `zpl`) |

**Kind:** peer. **Controller:** `{Module}:PrintContract@…!`

The **host** **MUST NOT** set `extra1=print` on itself.

This role is **not** `pdf` (create a file), **not** `label` (render a shipping label), and **not** `pos` (ticket / tender). Those packs may **call** this pack to send an already-rendered document to a printer. **MUST NOT** invent `extra1=printer`.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('print','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PrintContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

Printer picker = `<select>` / `dotSelect2` of `printers[]` (or the pack’s paged picker). **MUST NOT** a typed print URI.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PrintContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PrintQueue',         // exact module name
    'modes' => ['job'],               // extra3 this pack actually implements
    'families' => ['generic', 'shop', 'erp'],
    'max_copies' => 20,
    'printers' => [                   // bounded office set — not a growing catalogue
        [
            'id' => '…ciphertext…',
            'name' => 'Front desk',
            'location' => 'Lobby',
        ],
    ],
]
```

`printers` is a **bounded** choice (native `<select>` / `dotSelect2`). Opening it **MUST** show names without typing an exact remembered id. Host **MUST** `htmlspecialchars` `name` / `location` before `{{ var: }}`.

If the site has a growing printer directory, that list lives on the **pack’s** own paged admin ([40](../40-DACORE-LIST-PAGER.md)) — **MUST NOT** `all()` into `capabilities()`. `submit()` still accepts an encrypted `printerRef` the operator already selected. In that case `printers` **MAY** be `[]` and `printers_paged` `true`:

```php
'printers' => [],
'printers_paged' => true,
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Print queue is not ready.',
]
```

Product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing job tables: `COUNT(*)` + `LIMIT` / `OFFSET` — **MUST NOT** `all()`.

### `submit($docRef, $printerRef, $copies)`

**Call:** `DotApp::call('{Module}:PrintContract@submit!', $docRef, $printerRef, $copies)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$docRef` | string | Encrypted document id (PDF `download_id`, invoice id, label `pdf_ref`, DMS record). Empty / decrypt fail → `ok:false` |
| `$printerRef` | string | Encrypted printer id from `printers[]` or the pack’s paged picker. Decrypt fail / unknown → `ok:false` |
| `$copies` | int | Copy count. **MUST** be an integer `>= 1` and `<= max_copies`. Float / string / `0` → `ok:false` (do not silently cast `'2; drop'`) |

**Success:**

```php
[
    'ok' => true,
    'job_id' => '…ciphertext…',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not queue this print job.',
]
```

Unknown document, unknown printer, copies out of range, queue down → that generic copy. **MUST NOT** return the document bytes, raw CUPS/ZPL payload, or printer credentials. **MUST NOT** leak `getMessage()`.

The pack **MUST** re-check rights / ownership on `$docRef` in PHP. Frontend copy-count UI is not the gate.

After a successful persist the pack **MUST** fire `print_submitted` with `job_id` and `copies` (§8).

---

## 5. HTTP (not `PrintContract`)

These helpers are in-process. **No CRC** on `capabilities` / `submit`.

Host “Print” buttons use `$dotapp().load()` + encrypted `data-*` (or a multi-field `<fo-rm>` for printer + copies). Overlay until `submit()` returns. Toast success (“Queued 2 copies.”) or fail. No `location.reload()`.

Pack job-list admin POST: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` — action **MUST NOT** `crcCheck()` again. Rights via `#YourModule:Rights@check!`.

Cancel / delete of a job (pack admin) uses `Notiflix.Confirm` — never `alert()`. **MUST NOT** `$request->upload()` for v1 `submit`.

---

## 6. Host UI

- Printer = `<select>` / `dotSelect2` from `capabilities()['printers']`, or the pack’s paged picker when `printers_paged` is true. **MUST NOT** a typed absolute print URI (`ipp://…`, `\\server\queue`).
- Copies = number input; PHP still rejects out-of-range.
- HTML `job_id` / `docRef` / `printerRef` = `{{ enc(...) }}`.
- This role has **no** `picker_js`. **MUST NOT** invent `$dotapp().printPicker`.
- Search DACore first before a new print widget ([33](../33-DACORE-PAGES-AND-UI.md)).

---

## 7. Encrypted refs and copies jail v1 (**MUST**)

1. `$docRef`, `$printerRef`, and returned `job_id` are ciphertext in HTML. Decrypt `false` → `ok:false`. Still rights / ownership in PHP.
2. `$copies` is a range-checked **int**. **MUST NOT** accept a string that is not a clean decimal integer.
3. Printer identity is a pack id, not a user-typed URI / UNC / IP.
4. Growing job / printer tables: `COUNT(*)` + `LIMIT`. Index `printer_id` + `created` / `id` for the job list (comment names that query).
5. Tables `{lowercase_modulename}_*` — never `dacore_*`.
6. **MUST NOT** put document bytes or printer secrets in the JSON reply.

---

## 8. Hooks

Fire only after a useful persist — **not** on `capabilities()`, **not** on a failed submit.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.print_submitted.hook` | Job row persisted after `submit()` | `job_id`, `copies` |

**MUST NOT** put document bytes, printer credentials, `docRef` plaintext, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`printer`, `cups`, `spooler`, `pos-print`)
- Invent `extra3` (`raw`, `zpl`, `cups`) — v1 is `job` only
- `glob('app/modules')` or `include` the pack to discover it
- Accept a typed printer URL / UNC path from the host request
- `all()` on a growing job or printer table
- Leak `getMessage()`, device secrets, or document bytes
- Fire `print_submitted` without `job_id` and `copies`
- Set `extra1=print` on the CMS / Shop / ERP / POS **host**
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1 (`extra3=job`, `extra2=v1`)
- Host lists with `listByContract!`
- Every public HTML id (`job_id`, `docRef`, `printerRef`) is encrypted
- `$copies` is a range-checked int
- Hook `print_submitted` named in `.hooks` if fired — payload is `job_id`, `copies`
- No `crcCheck()` on `capabilities` / `submit`
