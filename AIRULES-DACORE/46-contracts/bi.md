# 46 — `bi` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is a **named chart query**. It is **not** `report` (tables) and **not** raw SQL from the host. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `bi` |
| `extra2` | `v1` |
| `extra3` | `chart` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'bi',
'extra2' => 'v1',
'extra3' => 'chart',
'extra4' => 'erp',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'bi', 'v1');
```

| extra3 | Meaning |
|--------|---------|
| `chart` | Named series |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:BiContract@…!`

The **host** **MUST NOT** set `extra1=bi` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('bi','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':BiContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:BiContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Charts',
    'modes' => ['chart'],
    'charts' => [
        ['key' => 'revenue_month', 'label' => 'Revenue'],
    ],
    'max_points' => 366,
]
```

`charts` is a bounded whitelist. Host picker = `<select>`.

**Failure:** `['ok' => false, 'message' => 'Charts are not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

### `query($opts)`

**Call:** `DotApp::call('{Module}:BiContract@query!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `chart_key` | string | Whitelist from `charts[].key` |
| `from` | string | ISO date `YYYY-MM-DD` |
| `to` | string | ISO date. Range **MUST** be bounded (pack cap, example 366 days) |

**Success:**

```php
[
    'ok' => true,
    'series' => [
        [
            'name' => 'revenue',
            'points' => [
                ['x' => '2026-08-01', 'y' => '120.00'],
            ],
        ],
    ],
]
```

Point count ≤ `max_points`. `y` is a decimal **string**. **MUST NOT** `all()` then chart in PHP without LIMIT/GROUP in SQL.

**Failure:**

```php
['ok' => false, 'message' => 'This chart could not be loaded.']
```

Unknown key and inverted / huge range share this copy. **MUST NOT** leak SQL or `getMessage()`.

Host **MUST** search DACore first before adding a chart library ([33](../33-DACORE-PAGES-AND-UI.md)).

---

## 5. Hooks

v1 **MUST NOT** fire on `query` (read).

---

## 6. MUST NOT

- Invent `extra1` (`charts`, `dashboard-data`)
- Host-supplied SQL
- Unbounded point arrays
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Chart key + date range whitelisted
- Bounded points; decimal strings
- No `crcCheck()` on these helpers
