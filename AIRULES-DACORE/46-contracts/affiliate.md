# 46 — `affiliate` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is **attribute an order to a partner**. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `affiliate` |
| `extra2` | `v1` |
| `extra3` | `partners` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'affiliate',
'extra2' => 'v1',
'extra3' => 'partners',
'extra4' => 'shop',
'extra5' => '',
```

`about.php` extras are quoted strings (not HTML nowdoc). Re-install the pack after changing them.

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'affiliate', 'v1');
```

| extra3 | Meaning |
|--------|---------|
| `partners` | Code / click attribution |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:AffiliateContract@…!`

The **host** **MUST NOT** set `extra1=affiliate` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('affiliate','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':AffiliateContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:AffiliateContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'AffPack',
    'modes' => ['partners'],
    'code_max' => 32,
]
```

**Failure:** `['ok' => false, 'message' => 'Affiliate tracking is not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Partner ids in HTML **MUST** be `{{ enc(...) }}`. Decrypt `false` → `ok:false`.

Public click ingest **MUST** `throttle()`.

### `attribute($opts)`

**Call:** `DotApp::call('{Module}:AffiliateContract@attribute!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `code` | string | Partner code. Charset `[A-Za-z0-9._-]`. Length ≤ `code_max`. Compare with `hash_equals` if the pack stores hashes |
| `click_id` | string | Optional encrypted click id from a prior landing |
| `order_ref` | string | Encrypted host order id |

Exactly one of `code` / `click_id` plus `order_ref`.

**Success:**

```php
[
    'ok' => true,
    'partner_id' => '…ciphertext…',
]
```

**Failure:**

```php
['ok' => false, 'message' => 'This referral could not be applied.']
```

Unknown code, expired click, and decrypt fail use **the same** copy. **MUST NOT** confirm a valid unused code to strangers (enumeration). **MUST NOT** `getMessage()`.

---

## 5. Hooks

Fire after a stored attribution.

| Event | When | Payload |
|-------|------|---------|
| `module.{mod}.affiliate_attributed.hook` | Order linked | `partner_id`, `order_ref` |

**MUST NOT** put codes, emails, or names in the payload. Document in `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 6. MUST NOT

- Invent `extra1` (`partners`, `referral`, `aff`)
- Echo whether a code is valid on a public miss
- PII in hooks
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Encrypted partner / order ids
- Public ingest throttled
- Hooks named in `.hooks` if fired
- No `crcCheck()` on these helpers
