# 46 — `report` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is a **paged table report**. It is **not** `bi` (charts) and **not** raw SQL from the host. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `report` |
| `extra2` | `v1` |
| `extra3` | `table` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'report',
'extra2' => 'v1',
'extra3' => 'table',
'extra4' => 'erp',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'report', 'v1');
```

| extra3 | Meaning |
|--------|---------|
| `table` | Named reports, paged rows |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:ReportContract@…!`

The **host** **MUST NOT** set `extra1=report` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('report','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ReportContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ReportContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'ErpReports',
    'modes' => ['table'],
    'reports' => [
        ['key' => 'sales_daily', 'label' => 'Daily sales'],
    ],
    'page_size' => 50,
]
```

`reports` is a **bounded** whitelist. Host picker = `<select>` of `key`. **MUST NOT** a typed report name.

**Failure:** `['ok' => false, 'message' => 'Reports are not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

### `run($opts)`

**Call:** `DotApp::call('{Module}:ReportContract@run!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `report_key` | string | Whitelist from `reports[].key` |
| `filters` | array | Optional. Keys whitelist per report (`from`, `to` ISO date). Values bound — **MUST NOT** column names from the request |
| `page` | int | 1-based. Invalid → 1 |

**Success:**

```php
[
    'ok' => true,
    'columns' => ['day', 'total'],    // pack-owned
    'rows' => [
        ['day' => '2026-08-01', 'total' => '120.00'],
    ],
    'page' => 1,
    'last_page' => 3,
    'total' => 120,
]
```

`total` is `COUNT(*)` of the filtered set. Needed columns only. **MUST NOT** `select('*')` or `all()` then slice. Money as decimal strings.

**Failure:**

```php
['ok' => false, 'message' => 'This report could not be run.']
```

Unknown key and illegal filter key share this copy. **MUST NOT** leak SQL, column lists from the request, or `getMessage()`.

Row ids if present **MUST** be encrypted when they leave toward HTML.

---

## 5. Hooks

v1 **MUST NOT** fire on `run` (read).

---

## 6. MUST NOT

- Invent `extra1` (`reports`, `export-table`)
- User-chosen SQL / columns / `order by` from the request
- `select('*')` / unbounded `all()`
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Report key + filters whitelisted
- Paged `COUNT` + `LIMIT`
- No `crcCheck()` on these helpers
