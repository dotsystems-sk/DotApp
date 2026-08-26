# 46 — `label` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is **render a shipping label**. The host may already have picked a `shipping` pack — do not invent a second extra1. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `label` |
| `extra2` | `v1` |
| `extra3` | `shipping` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'label',
'extra2' => 'v1',
'extra3' => 'shipping',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'label', 'v1');
```

| extra3 | Meaning |
|--------|---------|
| `shipping` | Label for a shipment / parcel ref |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:LabelContract@…!`

The **host** **MUST NOT** set `extra1=label` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('label','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':LabelContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:LabelContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'LabelPack',
    'modes' => ['shipping'],
    'formats' => ['pdf', 'png'],
]
```

**Failure:** `['ok' => false, 'message' => 'Labels are not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Shipment refs in HTML **MUST** be `{{ enc(...) }}`. Decrypt `false` → `ok:false`.

### `render($opts)`

**Call:** `DotApp::call('{Module}:LabelContract@render!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `shipment_ref` | string | Encrypted shipment id (often from a `shipping` pack `createShipment`) |
| `format` | string | Optional. Whitelist from `formats`. Default `pdf` |

**Success:**

```php
[
    'ok' => true,
    'url' => '/api/v1/auth/LabelPack/label-download/…',  // or empty
    'pdf_ref' => '…ciphertext…',                         // or empty
    'mime' => 'application/pdf',
]
```

At least one of `url` / `pdf_ref`. Download HTTP uses the pack’s auth route. **MUST NOT** return raw PDF bytes in this helper if large — use `pdf_ref` + download.

**Failure:**

```php
['ok' => false, 'message' => 'The label could not be created.']
```

Unknown shipment, decrypt fail, and unsupported format share this copy. **MUST NOT** dump the delivery address, tracking number, or `getMessage()`.

---

## 5. Hooks

Fire after a label file is stored — **not** on a cache hit if the pack treats that as read.

| Event | When | Payload |
|-------|------|---------|
| `module.{mod}.label_rendered.hook` | New label file | `pdf_ref` or omit, `shipment_ref` |

**MUST NOT** put addresses or tracking numbers in the payload. Document in `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 6. MUST NOT

- Invent `extra1` (`shipping-label`, `zpl`, `labelary`)
- Duplicate `shipping` as extra1 on the host
- Address dumps in replies / hooks
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Encrypted refs
- Format whitelist
- Hooks named in `.hooks` if fired
- No `crcCheck()` on these helpers
