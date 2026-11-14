# 46 — `maps` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a maps / geocode pack. A `<Host>` and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This role is **not** IP geolocation. DACore already owns **`IpGeoDriver`** (registry style A). **MUST NOT** set `extra1=ip-geo` or invent a peer that looks up an IP. [30](../30-DACORE-OVERVIEW.md) §3a.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `maps` |
| `extra2` | `v1` |
| `extra3` | `tiles` \| `geocode` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'maps',
'extra2' => 'v1',
'extra3' => 'geocode',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'maps', 'v1');
$geocode = DotApp::call('DACore:Plugins@listByContract!', 'maps', 'v1', 'geocode');
```

| extra3 | Meaning |
|--------|---------|
| `tiles` | Raster / vector tiles for a public map. `geocode()` **MAY** return `ok:false`. Tile URLs are **pack-proxied** or key-free templates (see §6) |
| `geocode` | Forward geocode a place query → `lat`, `lon`, `label`. `tiles` assets optional |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** put an API key, map id, or tile host here |

A pack that implements both sets `extra3` to the **primary** mode in `about.php` and lists both in `capabilities()['modes']`.

**Kind:** peer. **Controller:** `{Module}:MapsContract@…!`

The **host** **MUST NOT** set `extra1=maps` on itself.

Vendor API keys **MUST NOT** appear in `extra1`…`extra5`, `capabilities()`, or method replies.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('maps','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':MapsContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:MapsContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SiteMaps',          // exact module name
    'modes' => ['geocode'],          // extra3 this pack actually implements
    'geocode' => true,
    'tiles' => false,
    'tile_url' => '',                // see §6; '' when tiles is off
    'map_js' => '/assets/modules/SiteMaps/js/map.js', // '' when unused
    'map_css' => '/assets/modules/SiteMaps/css/map.css',
]
```

`map_js` / `map_css` **MUST** be `/assets/modules/{Module}/…` or empty.

**MUST NOT** return API keys, session tokens, vendor account ids, or signed URLs that embed a secret.

**Failure:** `['ok' => false, 'message' => 'Maps are not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Geocode log tables that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `geocode($query)`

**About:** Forward-geocode a place string. **MUST NOT** accept an IP address as the product input (that is `IpGeoDriver`).

**Call:** `DotApp::call('{Module}:MapsContract@geocode!', $query)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$query` | string | Place text (street, city, venue). Trimmed. Length 1–200. Empty → `ok:false` |

**MUST NOT** treat `$query` as an IP (`127.0.0.1`, `::1`, dotted IPv4 / IPv6). If the string looks like an IP, return `ok:false` with product copy — do not call an IP-geo API.

**MUST NOT** interpolate `$query` into a vendor URL path without encoding; **MUST NOT** put `$query` into `header()` / `HttpHelper` as a full URL the request supplied.

**Success:**

```php
[
    'ok' => true,
    'lat' => '48.14816',    // decimal **string** (not float)
    'lon' => '17.10674',    // decimal **string**
    'label' => 'Bratislava, Slovakia', // plain text; host escapes
]
```

`lat` / `lon` are decimal **strings** with a `.` separator. Range: lat `[-90, 90]`, lon `[-180, 180]`. **MUST NOT** `float` in the reply (JSON numbers that lose precision are avoided by strings).

`label` is a single display line. **MUST NOT** include the API key, vendor request id secrets, or raw vendor JSON.

**Failure:** `geocode` not in `modes`; empty / IP-like query; vendor down; no hit → `['ok' => false, 'message' => 'That place could not be found.']`.

v1 returns **one** best hit. No `items[]` array.

`tiles`-only packs: `geocode` returns `ok:false` with product copy (`'Geocoding is not available.'`).

---

## 5. Not IP geo (**MUST**)

| Need | Use |
|------|-----|
| Country / city from an **IP** | `DACore:IpGeoDriver@…!` (DACore registry) |
| Lat/lon from a **place name** | this `MapsContract@geocode!` |
| Map tiles | this pack’s `tile_url` / proxy |

**MUST NOT** invent `extra1` `ip-geo`, `geoip`, or `location`. **MUST NOT** call `IpGeoDriver` from `geocode($query)`.

---

## 6. Tiles and keys (**MUST**)

When `tiles` is true, `tile_url` is one of:

- a **pack-owned** proxy path, e.g. `/api/v1/noauth/SiteMaps/tile/{z}/{x}/{y}` (placeholders only; the pack substitutes integers it validated), or
- a template **without** an API key, e.g. `https://tile.example.test/{z}/{x}/{y}.png` on a host the operator allowlisted.

**MUST NOT** put `?key=` / `?apikey=` / signed tokens in `tile_url` or in any contract reply. If the vendor requires a key, the pack **proxies** tiles: browser hits the pack; pack adds the key server-side.

Tile HTTP: validate `z`/`x`/`y` as integers in range; **MUST NOT** open-proxy arbitrary URLs (SSRF). Timeout + max bytes in the pack.

`map_js` loaded by the host **MUST NOT** contain the API key in source.

---

## 7. HTTP (pack-owned, not `MapsContract`)

Public geocode form (if any): `/api/v1/noauth/{Module}/…` + `#DACore:AuthTest@CRC!` **XOR** action `crcCheck()` — never both. **MUST** `throttle()`. Then call `geocode()` in-process.

Admin settings: `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again.

The in-process `geocode!` helper has **no** CRC.

---

## 8. Hooks

**MUST NOT** fire a hook on every `geocode` (flood).

No v1 hook is required. A pack that persists a **saved place** (operator pin) **MAY** fire:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.map_place_saved.hook` | Place row stored | `id` |

**MUST NOT** put `lat`/`lon`/`label`/`query`, API keys, or request bodies in the payload. Document in the pack `.hooks` only if fired. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`mapbox`, `osm`, `geocode`, `ip-geo`, `geoip`)
- Return an API key in `capabilities`, `geocode`, `tile_url`, or HTML
- Treat `$query` as an IP or call `IpGeoDriver` from this contract
- `glob('app/modules')` or `include` the pack to discover it
- Open-proxy tiles to an arbitrary URL (SSRF)
- Use `float` for `lat` / `lon` in the reply
- Leak `getMessage()`, vendor bodies, or request bodies
- `all()` on a growing geocode-log table
- Fire a hook per `geocode`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `geocode($query)` is `{ok, lat, lon, label}` with decimal **strings**
- No API key in any reply; tiles proxied or key-free
- Not IP geo — no `IpGeoDriver` here
- Hooks named in `.hooks` only if `map_place_saved` fires
- No `crcCheck()` on `capabilities` / `geocode`
