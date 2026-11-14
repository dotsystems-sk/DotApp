# 46 — `page-cache` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a full-page or fragment cache pack. A `<Host>` and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** `cdn` (edge purge / rewrite) and **not** framework `Cache::` used as a host-only shortcut. Discovery still uses extras.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `page-cache` |
| `extra2` | `v1` |
| `extra3` | `full` \| `fragment` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'page-cache',
'extra2' => 'v1',
'extra3' => 'full',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'page-cache', 'v1');
$full = DotApp::call('DACore:Plugins@listByContract!', 'page-cache', 'v1', 'full');
```

| extra3 | Meaning |
|--------|---------|
| `full` | One key = one public HTML document (article, product page). Host stores the rendered page |
| `fragment` | One key = one HTML chunk (menu, teaser). Host stores a fragment, not a layout shell with secrets |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** put a Redis host, password, or namespace here |

A pack that implements both sets `extra3` to the **primary** mode in `about.php` and lists both in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:PageCacheContract@…!`

The **host** **MUST NOT** set `extra1=page-cache` on itself.

Backend credentials (Redis, Memcached, disk path) **MUST NOT** appear in `extra1`…`extra5`. Those live in the **pack’s** settings.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('page-cache','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':PageCacheContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:PageCacheContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'PageCache',         // exact module name
    'modes' => ['full'],             // extra3 this pack actually implements
    'max_ttl' => 86400,              // seconds; store() caps at this
    'default_ttl' => 300,
    'max_html_bytes' => 1048576,     // reject larger $html
    'prefix_forget' => true,         // false when forget() accepts exact key only
]
```

**MUST NOT** return backend hosts, passwords, disk paths, or connection strings.

**Failure:** `['ok' => false, 'message' => 'Page cache is not ready.']` — product copy, no `getMessage()`.

v1 has **no** `fetch` / `get` on this contract. The host reads through the pack only if it later adds a private helper; interoperability in v1 is **store** + **forget**. A host that needs a read **MAY** keep its own key map and treat a miss as “render then `store`”.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Admin “cached keys” lists that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `store($key, $html, $ttl)`

**About:** Persist HTML under a whitelist key. **MUST NOT** store secrets.

**Call:** `DotApp::call('{Module}:PageCacheContract@store!', $key, $html, $ttl)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$key` | string | Cache key. Charset **`[A-Za-z0-9._:/-]`**, length 1–128. **MUST** start with a letter or digit. **MUST NOT** `..`, `\`, NUL, spaces, or `?` |
| `$html` | string | Markup the host already rendered. Length ≤ `max_html_bytes`. Empty → `ok:false` |
| `$ttl` | int | Seconds to keep. `0` = `default_ttl`. Negative / non-int → `ok:false`. Cap at `max_ttl` |

**Success:** `['ok' => true]`.

**Failure:** illegal key; oversize HTML; backend down; `full` pack given a fragment-only key the pack refuses → `['ok' => false, 'message' => 'The page could not be cached.']`.

`$html` **MUST NOT** contain passwords, CSRF / CRC tokens, session ids, 2FA codes, TOTP secrets, PAN, Authorization headers, or raw `$request` bodies. The **host** is responsible for stripping those before `store`. The pack **MUST NOT** invent a secret scanner as the only gate; it still **MUST** refuse keys that fail the charset.

Unknown extra arguments **MUST** be ignored (no request-spread into the backend key).

### `forget($spec)`

**About:** Drop one key or a prefix group (publish, unpublish, save).

**Call:** `DotApp::call('{Module}:PageCacheContract@forget!', $spec)`

**Input** `$spec` — string **or** array. Exactly one of `key` / `prefix` must be usable:

| Form | Meaning |
|------|---------|
| string | Treated as exact `$key` (same charset as `store`) |
| `['key' => 'cms/page/12']` | Exact delete |
| `['prefix' => 'cms/page/']` | Delete every stored key that starts with that prefix |

| Key | Type | Meaning |
|-----|------|---------|
| `key` | string | Same charset as `store`. Empty = omit |
| `prefix` | string | Same charset, length 1–128, **MUST** end with `/` or a complete segment. **MUST NOT** `..`. Requires `prefix_forget` true |

Both `key` and `prefix` set → `ok:false`. Neither set → `ok:false`.

**Success:**

```php
[
    'ok' => true,
    'forgotten' => 3,   // rows/keys removed; 0 is still ok when already gone
]
```

**Failure:** illegal charset; `prefix` when `prefix_forget` is false; backend down → `ok:false`.

`forget` of an unknown key is **idempotent** (`ok:true`, `forgotten` 0).

---

## 5. Key whitelist (**MUST**)

Allowed characters: `A–Z`, `a–z`, `0–9`, `.`, `_`, `:`, `/`, `-`.

**MUST NOT:**

- Use a session id, CSRF token, email, or password as `$key` or as a key suffix
- Interpolate `$request->query()` into the key without a host-owned whitelist (locale `en` / `sk` is fine; raw `q=` is not)
- Store under `app/runtime` paths as keys that leak disk layout

Recommended shape: `{host}/{type}/{encrypted-or-stable-id}` e.g. `cms/article/ab12`. Numeric ids in the key are host-internal (not HTML). Ids that **leave** PHP toward HTML stay encrypted.

---

## 6. HTTP (pack-owned, not `PageCacheContract`)

Admin “Flush cache” on the pack’s page is a normal POST: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. That action **MUST NOT** `crcCheck()` again. Graphical confirm (`Notiflix.Confirm`) before a prefix flush — never `alert()`.

The in-process `store!` / `forget!` helpers have **no** CRC.

---

## 7. HTML is not a secret store (**MUST**)

Cached HTML is **public-page** markup (or a public fragment). **MUST NOT** cache:

- DACore admin shells, operator tables, or rights names
- Forms that embed a live CRC / CSRF hidden field meant for one session
- Personalized carts, tokens, or “logged-in as” strings

`{{ var: }}` does **not** escape. The **host** `htmlspecialchars` user strings **before** `store` if those strings will be printed later from the cache.

A later host `fetch` (out of v1) **MUST** still treat the blob as untrusted markup from the host’s own earlier render — not as a place to `eval`.

---

## 8. Hooks

Fire only after a useful persist — **not** on every `store` (flood).

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.page_cache_forgotten.hook` | `forget` removed at least one key, or a prefix flush ran | `forgotten` (count), `had_prefix` (0\|1) |

**MUST NOT** put `$key`, `$html`, URLs, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`cache`, `fullpage`, `redis`, `html-cache`)
- `glob('app/modules')` or `include` the pack to discover it
- Store secrets, CSRF/CRC tokens, session ids, or PAN in `$html`
- Use a key outside `[A-Za-z0-9._:/-]`
- Put backend hosts or passwords in extras or replies
- Leak `getMessage()`, disk paths, or request bodies
- `all()` on a growing key index
- Fire a hook on every `store`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `store($key, $html, $ttl)` rejects illegal keys and oversize HTML
- `forget` accepts a key or a prefix; charset matches `store`
- No secrets in stored HTML or hook payloads
- Hooks named in `.hooks` if `page_cache_forgotten` fires
- No `crcCheck()` on `capabilities` / `store` / `forget`
