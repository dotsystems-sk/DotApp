# 46 — `cookie-consent` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **cookie notice / CMP** peer. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `cookie-consent` |
| `extra2` | `v1` |
| `extra3` | `notice` \| `cmp` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'cookie-consent',
'extra2' => 'v1',
'extra3' => 'notice',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'cookie-consent', 'v1');
$cmp = DotApp::call('DACore:Plugins@listByContract!', 'cookie-consent', 'v1', 'cmp');
```

| extra3 | Meaning |
|--------|---------|
| `notice` | Accept / reject all. `record` stores two flags (`necessary` always true, `optional` bool) |
| `cmp` | Per-category. `record` stores `categories` map |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:ConsentContract@…!`

The **host** **MUST NOT** set `extra1=cookie-consent` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('cookie-consent','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ConsentContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ConsentContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'CookieBar',
    'modes' => ['notice'],
    'categories' => ['necessary', 'analytics', 'marketing'], // cmp; notice MAY be ['necessary','optional']
]
```

**Failure:** `['ok' => false, 'message' => 'Consent banner is not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Consent ids in HTML **MUST** be `{{ enc(...) }}`. Decrypt `false` → `ok:false`.

### `banner($opts)`

**Call:** `DotApp::call('{Module}:ConsentContract@banner!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `locale` | string | Optional `sk` \| `en` |

**Success:** `['ok' => true, 'html' => '…']`.

Markup from a pack template. **MUST NOT** load a remote tracker from this HTML.

**Failure:** not configured → `ok:false`.

### `record($opts)`

**Call:** `DotApp::call('{Module}:ConsentContract@record!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `categories` | array | Keys from `capabilities.categories`. Values bool. Unknown keys dropped |
| `optional` | bool | `notice` mode only (when `categories` omitted) |

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext or empty if cookie-only…',
]
```

**Failure:**

```php
['ok' => false, 'message' => 'Your choice could not be saved.']
```

Empty or illegal maps share this copy. **MUST NOT** store name, email, or a dump of `$_COOKIE` beyond the category flags.

Public POST that calls `record` **MUST** `throttle()`.

---

## 5. Hooks

Fire only after a stored choice — **not** on `banner`.

| Event | When | Payload |
|-------|------|---------|
| `module.{mod}.consent_recorded.hook` | Choice stored | `id` (or omit), `category_count` |

**MUST NOT** put the category map or personal data in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 6. MUST NOT

- Invent `extra1` (`gdpr`, `cmp`, `cookies`)
- Inject analytics pixels from the banner (that is `analytics`)
- `glob('app/modules')` to discover the pack
- PII in hooks / replies
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Encrypted consent id if stored
- Public record throttled
- Hooks named in `.hooks` if fired
- No `crcCheck()` on these helpers
