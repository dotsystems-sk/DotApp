# 46 — `reviews` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is **star reviews**. It is **not** `comments` (no thread) and **not** `forum`. A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `reviews` |
| `extra2` | `v1` |
| `extra3` | `stars` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'reviews',
'extra2' => 'v1',
'extra3' => 'stars',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'reviews', 'v1');
```

| extra3 | Meaning |
|--------|---------|
| `stars` | Integer 1–5 plus optional text |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused |

**Kind:** peer. **Controller:** `{Module}:ReviewsContract@…!`

The **host** **MUST NOT** set `extra1=reviews` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('reviews','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ReviewsContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ReviewsContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'StarPack',
    'modes' => ['stars'],
    'min_stars' => 1,
    'max_stars' => 5,
    'page_size' => 20,
]
```

**Failure:** `['ok' => false, 'message' => 'Reviews are not ready.']`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Review ids in HTML **MUST** be `{{ enc(...) }}`. Decrypt `false` → `ok:false`. **MUST NOT** `all()`.

Public `add` **MUST** `throttle()`.

### `list($opts)`

**Call:** `DotApp::call('{Module}:ReviewsContract@list!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `target_ref` | string | Encrypted product / page id |
| `page` | int | 1-based. Invalid → 1 |

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',
            'stars' => 5,
            'text' => 'Good',           // already stored; host htmlspecialchars
        ],
    ],
    'page' => 1,
    'last_page' => 2,
    'total' => 33,
    'average' => '4.50',              // decimal string
]
```

`total` is `COUNT(*)`. **Failure:** decrypt fail.

### `add($opts)`

**Call:** `DotApp::call('{Module}:ReviewsContract@add!', $opts)`

| Key | Type | Meaning |
|-----|------|---------|
| `target_ref` | string | Encrypted target id |
| `stars` | int | `min_stars`…`max_stars` |
| `text` | string | Optional. Original (`data(true)`). Pack length-caps |

**Success:** `['ok' => true, 'id' => '…']`.

**Failure:**

```php
['ok' => false, 'message' => 'The review could not be saved.']
```

Bad stars and decrypt fail share this copy. PHP re-validates. **MUST NOT** `getMessage()`.

---

## 5. Hooks

Fire after a stored review — **not** on `list`.

| Event | When | Payload |
|-------|------|---------|
| `module.{mod}.reviews_added.hook` | Review stored | `id`, `stars`, `target_ref` |

**MUST NOT** put `text` in the payload. Document in `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 6. MUST NOT

- Invent `extra1` (`ratings`, `stars`, `testimonials`)
- `all()` reviews
- Review text in hooks
- `glob('app/modules')` to discover the pack
- PHP 8+ syntax unless the plan named a higher version

---

## 7. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Paged list; encrypted ids
- Public add throttled
- Host escapes `text`
- Hooks named in `.hooks` if fired
- No `crcCheck()` on these helpers
