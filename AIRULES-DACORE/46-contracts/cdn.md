# 46 — `cdn` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a CDN pack. A host (CMS, Shop, ERP) and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `cdn` |
| `extra2` | `v1` |
| `extra3` | `purge` \| `rewrite` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'cdn',
'extra2' => 'v1',
'extra3' => 'purge',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'cdn', 'v1');
$purge = DotApp::call('DACore:Plugins@listByContract!', 'cdn', 'v1', 'purge');
```

| extra3 | Meaning |
|--------|---------|
| `purge` | Invalidate cached objects by URL list and/or prefix. `rewriteUrl` may return `ok:false` |
| `rewrite` | Map a host-local public path to a CDN URL. `purge` may return `ok:false` |

A pack that implements both sets `extra3` to the **primary** mode it advertises in `about.php` and lists **both** in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:CdnContract@…!`

The **host** (CMS, Shop) **MUST NOT** set `extra1=cdn` on itself.

Vendor account ids, API tokens, zone secrets, and signing keys **MUST NOT** appear in `extra1`…`extra5`. Those live in the **pack’s** settings (operator form), never in `dacore_modules`.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('cdn','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CdnContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CdnContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'EdgeCdn',           // exact module name
    'modes' => ['purge', 'rewrite'], // extra3 values this pack actually implements
    'purge' => true,
    'rewrite' => true,
    'public_host' => 'cdn.example.test', // hostname only; '' when rewrite is off
    'max_purge_urls' => 50,          // cap for one purge() call
]
```

**MUST NOT** return API keys, zone tokens, account secrets, raw vendor endpoints that embed credentials, or request bodies.

**Failure:** `['ok' => false, 'message' => 'CDN is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing purge logs in the pack admin: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `purge($spec)`

**About:** Ask the vendor to drop cached copies. The pack talks to the CDN from **its** settings; the host only names public URLs or a prefix.

**Input** `$spec` array — at least one of `urls` or `prefix` is required:

| Key | Type | Meaning |
|-----|------|---------|
| `urls` | string[] | Public `https://` URLs already on this CDN, or host-relative paths starting with `/`. Empty array = omit. Length ≤ `max_purge_urls` |
| `prefix` | string | Path prefix to invalidate, e.g. `/assets/modules/Shop/`. Charset `[A-Za-z0-9._:/-]`, must start with `/`, **MUST NOT** `..` |

**Success:**

```php
[
    'ok' => true,
    'purged' => 12,   // count accepted by the vendor, or 0 when prefix-only and vendor has no count
    'prefix' => true, // true when a prefix was sent
]
```

**Failure:** `purge` not in `modes`; both `urls` and `prefix` empty; illegal URL / prefix; vendor not configured → `ok:false`. **MUST NOT** leak vendor `getMessage()`.

Each `urls[]` entry **MUST** be a path starting with `/` **or** an `https://` URL whose host equals `public_host` from `capabilities()` (or a host the operator allowlisted in **pack** settings). **MUST NOT** fetch those URLs (this is invalidate, not SSRF GET). **MUST NOT** accept `file://`, `gopher:`, or credential-bearing URLs (`https://user:pass@…`).

### `rewriteUrl($localPath)`

**About:** Turn a host-local public path into the CDN URL the browser should use.

**Input:** `$localPath` string.

| Constraint | Rule |
|------------|------|
| Shape | Relative public path starting with `/` |
| Charset | `[A-Za-z0-9._:/-]` plus a single optional `?` query of safe keys the **host** already owns |
| Reject | Absolute disk paths, `..`, `http://`, `https://`, scheme-relative `//`, NUL |

**Success:**

```php
[
    'ok' => true,
    'url' => 'https://cdn.example.test/assets/modules/Shop/img/hero.jpg',
]
```

When rewrite is configured but the path is already a no-op (same origin), `url` **MAY** equal the original path (still `ok:true`).

**Failure:** `rewrite` not in `modes`; illegal path; rewrite host not configured → `ok:false` (no guessed URL, no secret).

---

## 5. Credentials (**MUST**)

Vendor tokens stay in the pack’s own settings table / config. Host and extras **MUST NOT** carry them.

`capabilities()`, `purge()`, and `rewriteUrl()` replies **MUST NOT** include those tokens, signing secrets, or Authorization headers.

---

## 6. HTTP (pack-owned, not `CdnContract`)

Admin “Purge now” on the pack’s page is a normal POST: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. That action **MUST NOT** `crcCheck()` again. The in-process `CdnContract@purge!` helper has **no** CRC.

**MUST NOT** put `$request` data into `header()` / redirect / `HttpHelper` URL.

---

## 7. Path v1 (**MUST**)

`rewriteUrl` and `purge` prefixes are **public URL paths**, not jail disk paths. **MUST NOT** resolve to `app/runtime` or a filesystem absolute. Runtime is never a public CDN object in v1.

---

## 8. Hooks

Fire only after a useful persist / vendor side-effect — **not** on `capabilities` or a no-op rewrite.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.cdn_purged.hook` | Vendor accepted a purge | `purged` (count), `had_prefix` (0\|1) |

**MUST NOT** put URLs, tokens, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`edge`, `cloudfront`, `cloudflare`, `assets-cdn`)
- Put API keys, zone tokens, or account secrets in `extra1`…`extra5`
- `glob('app/modules')` or `include` the pack to discover it
- Return a rewritten URL for a disk path or `app/runtime`
- Fetch purge URLs (SSRF) or accept off-host / credential URLs
- Leak `getMessage()`, vendor payloads, or request bodies
- `all()` on a growing purge-log table
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- No secrets in extras or contract replies
- `rewriteUrl` only accepts host-relative public paths
- `purge` is URL-list and/or prefix; vendor errors stay generic
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `purge` / `rewriteUrl`
