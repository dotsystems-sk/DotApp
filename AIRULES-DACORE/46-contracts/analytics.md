# 46 — `analytics` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a web-analytics pack. A host (CMS, Shop) and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** `captcha`, **not** DACore inbox notifications, and **not** IP geo (`IpGeoDriver`). **PII is not required** to track.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `analytics` |
| `extra2` | `v1` |
| `extra3` | `pixel` \| `server` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'analytics',
'extra2' => 'v1',
'extra3' => 'pixel',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'analytics', 'v1');
$pixel = DotApp::call('DACore:Plugins@listByContract!', 'analytics', 'v1', 'pixel');
```

| extra3 | Meaning |
|--------|---------|
| `pixel` | Browser snippet. `capabilities()` returns **`snippet_url` local only**. Host prints a `<script src>` (or the pack’s tiny boot). `track()` **MAY** still record server-side if the pack wants both |
| `server` | Host calls `track()` in PHP (checkout paid, form sent). No browser snippet required (`snippet_url` `''`) |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** put a measurement id or API secret here |

A pack that implements both sets `extra3` to the **primary** mode in `about.php` and lists both in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:AnalyticsContract@…!`

The **host** **MUST NOT** set `extra1=analytics` on itself.

Vendor API secrets **MUST NOT** appear in `extra1`…`extra5`. Those live in the **pack’s** settings.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('analytics','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':AnalyticsContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:AnalyticsContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SiteStats',         // exact module name
    'modes' => ['pixel'],            // extra3 this pack actually implements
    'snippet_url' => '/assets/modules/SiteStats/js/pixel.js', // local only; '' in server-only
    'event_names' => ['page_view', 'purchase', 'signup'], // track() whitelist
    'prop_keys' => ['path', 'ref', 'value', 'currency'], // track() $props whitelist
]
```

**`snippet_url` (pixel mode) MUST be local only:** `/assets/modules/{Module}/…`. **MUST NOT** be `https://www.google-analytics.com/…`, a vendor collect URL, or any URL with an API key / measurement id in the query.

If the pack wraps a vendor pixel, **its** local JS loads the vendor. `capabilities()` still returns the **local** path only.

**MUST NOT** return API secrets, measurement ids, or cookies.

**Failure:** `['ok' => false, 'message' => 'Analytics is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Event tables that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `track($name, $props)`

**About:** Record one event. Works **without** PII. Unknown names / keys are rejected or dropped — never stored as a free-form dump.

**Call:** `DotApp::call('{Module}:AnalyticsContract@track!', $name, $props)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$name` | string | **MUST** be in `capabilities()['event_names']`. Charset `[a-z0-9_]`, length ≤ 64. Unknown → `ok:false` |
| `$props` | array | Associative. **Keys MUST be in `capabilities()['prop_keys']`**. Unknown keys are **dropped**, not stored. Values: string / int / decimal-string. Nested arrays / objects → `ok:false` |

**MUST NOT** require `email`, `user_id`, `phone`, `name`, or IP. Hosts **MAY** omit all props (`[]`).

**MUST NOT** accept or persist: passwords, tokens, CRC, PAN, TOTP, rights blobs, raw request bodies, or `email` / `phone` even if a host sends them — those keys **MUST NOT** appear on `prop_keys`.

`path` if allowed: host-relative `/…`, charset `[A-Za-z0-9._:/-]`, **MUST NOT** `..` or a full URL from the request used as an open redirect.

`currency` if allowed: ISO-4217 three letters. `value` if allowed: decimal **string**, not `float`.

**Success:** `['ok' => true]`.

**Failure:** unknown `$name`; non-scalar leftover after whitelist; backend down → `['ok' => false, 'message' => 'Event could not be recorded.']`.

`pixel` mode: the browser snippet **MAY** POST to a pack noauth route that then calls `track()` in-process. That HTTP route **MUST** `throttle()` and CRC **once**. The helper itself has **no** CRC.

---

## 5. Pixel snippet — local only (**MUST**)

When `extra3` includes `pixel` and `snippet_url` is non-empty:

1. Host public layout prints `<script src="{{ var: $snippetUrl }}"></script>` where `$snippetUrl` is the **capabilities** path (already local).
2. **MUST NOT** print a vendor URL from extras or from `$request`.
3. The pack JS **MUST NOT** read passwords from the page.

`server` mode: host calls `track()` after a useful server event (paid, subscribed). **MUST NOT** fire `track` inside `foreach` of a growing list — one event or a later batch API (not in v1).

---

## 6. Whitelist and cheap I/O (**MUST**)

`event_names` and `prop_keys` are the only writable vocabulary. Hosts **MUST NOT** spread `$request->data()` into `$props`.

Admin event logs **MUST** paginate ([40](../40-DACORE-LIST-PAGER.md)). **MUST NOT** `select('*')` on the event table for a list.

---

## 7. No PII required (**MUST**)

A correct integration tracks `page_view` with `{path:'/about'}` only.

**MUST NOT** document email as required. If the operator later wants a hashed user key, that is a **later** contract version or a pack-private setting — not a v1 required prop.

This is **not** `DACore:IpGeoDriver`. **MUST NOT** use `extra1=ip-geo`.

---

## 8. Hooks

**MUST NOT** fire a hook on every `track` (flood).

No v1 hook is required. If the pack later persists a **daily rollup** row, it **MAY** fire:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.analytics_rolled_up.hook` | Daily (or job) aggregate stored | `day` (`YYYY-MM-DD`), `event_count` |

**MUST NOT** put paths, props, emails, or IPs in the payload. Document in the pack `.hooks` only if fired. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`ga`, `gtag`, `pixel`, `stats`, `ip-geo`)
- Return a remote vendor URL or API key from `capabilities`
- Require PII on `track`
- Put `email` / `phone` / secrets on `prop_keys`
- `glob('app/modules')` or `include` the pack to discover it
- Fire a hook per `track`
- Leak `getMessage()`, measurement ids, or request bodies
- `all()` on a growing event table
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Pixel `snippet_url` is `/assets/modules/{Module}/…` or empty
- `track($name, $props)` uses both whitelists; no PII required
- No secrets in extras or replies
- Hooks named in `.hooks` only if a rollup event fires
- No `crcCheck()` on `capabilities` / `track`
