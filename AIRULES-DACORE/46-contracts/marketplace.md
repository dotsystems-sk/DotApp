# 46 — `marketplace` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is a **vendor directory**. It is **not** the <Host> **host** and **not** `catalog`. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `marketplace` |
| `extra2` | `v1` |
| `extra3` | `vendors` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'marketplace',
'extra2' => 'v1',
'extra3' => 'vendors',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'marketplace', 'v1');
```

| extra3 | Meaning |
|--------|---------|
| `vendors` | Paged vendor list only in v1 |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:MarketplaceContract@…!`

The **host** **MUST NOT** set `extra1=marketplace` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('marketplace','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':MarketplaceContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:MarketplaceContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Vendors',
    'modes' => ['vendors'],
    'page_size' => 20,
]
```

**Failure:** `['ok' => false, 'message' => 'Marketplace is not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Vendor ids in HTML **MUST** be `{{ enc(...) }}`. Decrypt `false` → `ok:false`. **MUST NOT** `all()`.

### `listVendors($opts)`

**Call:** `DotApp::call('{Module}:MarketplaceContract@listVendors!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `page` | int | 1-based. Invalid → 1 |
| `q` | string | Optional display-name filter (bound `LIKE`) |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',
            'name' => 'Acme',           // display only
        ],
    ],
    'page' => 1,
    'last_page' => 4,
    'total' => 71,
]
```

`total` is `COUNT(*)`. Needed columns only. Host `htmlspecialchars` on `name`.

**Failure:**

```php
['ok' => false, 'message' => 'Vendors could not be listed.']
```

**MUST NOT** return emails, tax ids, bank accounts, or `getMessage()`.

---

## 5. Hooks

v1 **MUST NOT** fire on `listVendors`.

---

## 6. MUST NOT

- Invent `extra1` (`vendors`, `sellers`, `multi-vendor`)
- `all()` / `select('*')` on vendors
- PII in the list
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Paged `COUNT` + `LIMIT`
- Encrypted vendor ids
- No `crcCheck()` on these helpers
