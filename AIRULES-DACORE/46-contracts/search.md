# 46 — `search` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop) and a pack must be able to interoperate from this page alone. This is **not** `kb` (articles) and **not** `bi` (charts).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `search` |
| `extra2` | `v1` |
| `extra3` | `sql` \| `fulltext` \| `external` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'search',
'extra2' => 'v1',
'extra3' => 'sql',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'search', 'v1');
$sql = DotApp::call('DACore:Plugins@listByContract!', 'search', 'v1', 'sql');
```

| extra3 | Meaning |
|--------|---------|
| `sql` | Bound `LIKE` / equality on pack tables. **MUST NOT** interpolate `$q` into SQL |
| `fulltext` | Engine `MATCH` / equivalent on a pack index table. Still bindings only |
| `external` | Pack calls a remote index it configured. API keys stay in pack config, never in extras or replies |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`meilisearch`, `elastic`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:SearchContract@…!`

The **host** (CMS, Shop) **MUST NOT** set `extra1=search` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('search','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':SearchContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:SearchContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SiteSearch',        // exact module name
    'modes' => ['sql'],              // extra3 this pack actually implements
    'types' => ['article', 'product', 'page'],
    'fields' => ['title', 'body', 'sku', 'excerpt'], // only these keys accepted by index()
    'page_size' => 20,
    'external' => false,             // true when extra3=external
]
```

**Failure:** `['ok' => false, 'message' => 'Search is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `index($type, $id, $fields)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$type` | string | Must be in `capabilities.types`. Unknown → `ok:false` |
| `$id` | string\|int | Host record id. When it came from HTML, it is ciphertext and decrypt `false` → `ok:false`. In-process host calls **MAY** pass a positive int the host already authorized |
| `$fields` | array | Associative. **Keys MUST be in `capabilities.fields`**. Unknown keys are dropped, not stored. Values are scalars (string / int / float as string). Nested arrays / objects → `ok:false` |

**Success:** `['ok' => true]`. Upsert on `($type, $id)`.

**Failure:** unknown type, decrypt fail, empty whitelist intersection, non-scalar field.

The pack **MUST NOT** build SQL from field keys. Writable columns are the whitelist, not `$fields` keys from the request.

### `query($q, $page)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$q` | string | Search text. Trimmed. Empty → `ok:false` or empty hit set with `total` 0 — pack picks one and documents it; never raw SQL |
| `$page` | int | 1-based page. Invalid → 1 |

Optional third `$opts` array is **not** in v1. Type filters belong in a later contract version.

**Success:**

```php
[
    'ok' => true,
    'hits' => [
        [
            'type' => 'article',
            'id' => '…ciphertext or host token…',
            'title' => 'Autumn catalogue',
            'snippet' => '…plain excerpt…',
        ],
    ],
    'page' => 1,
    'last_page' => 4,
    'total' => 73,
]
```

`last_page` comes from `COUNT(*)` (or the engine’s total), not `count($hits)`.

**Failure:** engine down, decrypt fail on an internal cursor → `ok:false`.

`$q` is a bound parameter (`LIKE` with escaped wildcards, `MATCH … AGAINST (?)`, or a remote query body). **MUST NOT** concatenate `$q` into SQL, `$qb->raw()`, or a remote URL path.

### `remove($type, $id)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$type` | string | Same whitelist as `index` |
| `$id` | string\|int | Same id rules as `index` |

**Success:** `['ok' => true]` (idempotent if already gone). **Failure:** unknown type, decrypt fail.

---

## 5. Field whitelist

`capabilities.fields` is the only writable map. Hosts **MUST NOT** spread `$request->data()` into `index()`. Extra keys are ignored. The pack **MUST NOT** add a column because a host sent a new key.

`title` / `snippet` in `query` hits are plain text. The **host** `htmlspecialchars` before `{{ var: }}`.

---

## 6. Paging and cheap I/O

`query` pages with `COUNT` + `LIMIT`/`OFFSET` (or the engine equivalent). **MUST NOT** `all()` the index table then filter in PHP.

`index` / `remove` are single-row writes. **MUST NOT** `trigger()` inside `foreach` of a host rebuild — the host batches, or the pack exposes one rebuild job later. v1 has no rebuild method.

---

## 7. External mode

When `extra3=external`, the pack holds the provider URL and secret in **its** config. Those values **MUST NOT** appear in `about.php` extras, `capabilities()`, `query` hits, or hooks.

A failed remote call reports `dotapp.catch` through the pack helper and returns `ok:false` with product copy.

---

## 8. Hooks

Fire only after a useful persist — **not** on `query`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.search_indexed.hook` | Document upserted | `type`, `id` |
| `module.{mod}.search_removed.hook` | Document removed | `type`, `id` |

**MUST NOT** put field bodies, `$q`, API keys, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`fulltext`, `elastic`, `finder`)
- `glob('app/modules')` or `include` the pack to discover it
- Interpolate user input into SQL or `$qb->raw()`
- Put API keys in extras, capabilities, or replies
- `all()` on a growing index table
- Fire hooks on `query`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML id is encrypted
- `index` keys stay on the `fields` whitelist
- `query` is paged; no raw user SQL
- Hooks named in `.hooks` if fired; payloads are type + id only
- No `crcCheck()` on `capabilities` / `index` / `query` / `remove`
