# 46 — `storage` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This is the **object-storage** peer (put / get / delete / signed URL). It is **not** `filemanager` (no jail picker, no admin browse UI) and **not** `dms` (no records / versions / ACL). A host and a pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `storage` |
| `extra2` | `v1` |
| `extra3` | `local` \| `s3` \| `sftp` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'storage',
'extra2' => 'v1',
'extra3' => 's3',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'storage', 'v1');
$s3 = DotApp::call('DACore:Plugins@listByContract!', 'storage', 'v1', 's3');
```

| extra3 | Meaning |
|--------|---------|
| `local` | Pack-owned disk under **its** jail (module tree / `app/runtime` as the pack documents). **No** public URL unless the object is under that pack’s `assets/` |
| `s3` | S3-compatible bucket. Endpoint and keys stay **in the pack** |
| `sftp` | Remote SFTP root. Host and keys stay **in the pack** |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** put a bucket name, host, access key, or path here. |

**Kind:** peer. **Controller:** `{Module}:StorageContract@…!`

The **host** **MUST NOT** set `extra1=storage` on itself. **MUST NOT** treat this pack as a `filemanager` picker.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('storage','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':StorageContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake. There is **no** admin picker JS for this role.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:StorageContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'S3Store',           // exact module name
    'modes' => ['s3'],               // extra3 this pack actually implements
    'signed_url' => true,            // false when extra3=local and no token route
    'max_put_bytes' => 10485760,     // 0 = pack default; host MUST still cap
    'key_prefix' => 'cms/',          // optional logical prefix; not a secret
]
```

**Failure:** `['ok' => false, 'message' => 'Object storage is not ready.']` — product copy, no `getMessage()`, no endpoint, no key.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Object keys that leave PHP toward HTML **MUST** be `{{ enc(...) }}` (unique `$key2`). Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing object **indexes** (if the pack lists): `COUNT` + `LIMIT` — **MUST NOT** `all()`. This role has **no** `list` / picker in v1.

Credentials (access key, secret, SFTP password, local root outside the documented jail) live in the **pack** (`Config::module` / secrets). **MUST NOT** appear in `extra1`…`extra5`, hook payloads, or replies.

### `put($opts)`

**Call:** `DotApp::call('{Module}:StorageContract@put!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `key` | string | Object key. Relative. Charset `[A-Za-z0-9._/-]`. **MUST NOT** `..`, absolute path, or `\` |
| `body` | string | Raw bytes. Host passes **original** bytes (not `protect()`) |
| `mime` | string | Optional MIME (`application/pdf`). Empty → pack probes or `application/octet-stream` |
| `meta` | array | Optional small flags (`filename`, `owner_ref`). **MUST NOT** secrets, PAN, passwords |
| `id` | string | Optional. Encrypted id of an object to **replace**. Decrypt `false` → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext or pack token…',
    'key' => 'invoices/2026/a.pdf',
    'size' => 12044,
    'mime' => 'application/pdf',
]
```

**Failure:** illegal key; over `max_put_bytes`; backend refuse; decrypt fail → `ok:false`. **MUST NOT** return the body or credentials.

### `get($opts)`

**Call:** `DotApp::call('{Module}:StorageContract@get!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `id` | string | Encrypted id or pack-stable token. Preferred when the host stored `put`’s `id` |
| `key` | string | Object key when the host stored `key` instead. Same charset / no `..` as `put` |
| `as` | string | `body` (default) \| `meta`. `meta` **MUST NOT** include `body` |

Exactly one of `id` / `key` is required. Both set: `id` wins after decrypt.

**Success (`as=body`):**

```php
[
    'ok' => true,
    'id' => '…token…',
    'key' => 'invoices/2026/a.pdf',
    'body' => '...bytes...',
    'mime' => 'application/pdf',
    'size' => 12044,
]
```

**Success (`as=meta`):** same without `body`.

**Failure:** unknown / decrypt fail / missing object → `ok:false`. **MUST NOT** leak bucket, host, or disk path.

Host **MUST** cap how large a `body` it accepts into memory. Unbounded blobs belong on a pack HTTP download route, not this helper.

### `delete($opts)`

**Call:** `DotApp::call('{Module}:StorageContract@delete!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `id` | string | Encrypted id or pack token |
| `key` | string | Object key (same rules as `put`) |

Exactly one of `id` / `key` is required.

**Success:** `['ok' => true]`.

**Failure:** not found; rights; decrypt fail → `ok:false`. Graphical confirm is **host** UI (`Notiflix.Confirm` on admin) — never `alert()`.

### `signedUrl($opts)`

**Call:** `DotApp::call('{Module}:StorageContract@signedUrl!', $opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `id` | string | Encrypted id or pack token |
| `key` | string | Object key (same rules as `put`) |
| `expiry` | int | Lifetime in **seconds** from now. Missing / non-int / `< 1` → pack default (document it, example `300`). **MUST NOT** be a string timestamp |
| `method` | string | Optional `GET` (default) \| `PUT`. Whitelist only |

Exactly one of `id` / `key` is required.

**Success:**

```php
[
    'ok' => true,
    'url' => 'https://…time-limited…',
    'expires_in' => 300,
]
```

`expires_in` is the **int seconds** actually applied.

**Failure:** `signed_url` false; `local` without a token route; unknown object; decrypt fail → `ok:false` (no guessed URL, no raw disk path).

---

## 5. Credentials and extras (**MUST**)

Bucket, region, endpoint, access key, secret, SFTP host/user/password, and local roots stay in the **pack** (installer / module config / secrets). `extra3` is only `local` \| `s3` \| `sftp`. **MUST NOT** put any of those values in `extra1`…`extra5`.

A failed remote call reports `dotapp.catch` through the pack helper, then `ok:false` product copy. **MUST NOT** leak `getMessage()` or the provider body.

---

## 6. Signed URL HTTP

`signedUrl()` itself is in-process and has **no** CRC.

`local` **MAY** return a time-limited URL on the **pack’s** `/api/v1/…` route. That HTTP route uses the pack’s own CRC / login prefix — **not** `signedUrl()`.

`s3` / `sftp` **MAY** return a vendor pre-signed URL. The reply **MUST NOT** include the signing key. `expiry` is always **int seconds**, never a datetime string.

Host **MUST NOT** put the signed URL into a hook payload or a log that another module can read as a secret.

---

## 7. Local jail vs remote

`local` objects live only in the pack’s documented jail (this-module tree, that pack’s `assets/`, or `app/runtime`). Area is **not** a typed absolute path from the host.

Runtime is **never** a public URL. A `get` `body` is bytes in PHP — **MUST NOT** be turned into `/assets/…` unless the file is actually under `/assets/modules/{Module}/…`.

`s3` / `sftp` have no host-visible disk path. **MUST NOT** return a guessed local path on failure.

This role has **no** `$dotapp().mediaPicker`. Choosing a file in admin is `filemanager`.

---

## 8. Hooks

Fire only after a useful persist — **not** on `get` / `signedUrl` / `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.object_stored.hook` | Object created or replaced | `id`, `key` (relative), `size`, `mime` |
| `module.{mod}.object_deleted.hook` | Object deleted | `id`, `key` |

**MUST NOT** put bytes, credentials, absolute paths, or signed URLs in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`s3`, `blob`, `object-store`, `files`)
- Implement an admin picker (`mediaPicker`) — that is `filemanager`
- `glob('app/modules')` or `include` the pack to discover it
- Put secrets in extras, replies, or hooks
- Return a public URL for `app/runtime` or a raw local disk path
- Leak `getMessage()`, endpoints, or request bodies
- `all()` on a growing object index
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Peer calls use `StorageContract` after the operator picked a module
- Every HTML object id is encrypted
- `signedUrl` `expiry` is int seconds
- Credentials only in the pack
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `put` / `get` / `delete` / `signedUrl`
