# 46 — `fleet` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a vehicle pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `fleet` |
| `extra2` | `v1` |
| `extra3` | `vehicles` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit the key) |

```php
'extra1' => 'fleet',
'extra2' => 'v1',
'extra3' => 'vehicles',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'fleet', 'v1');
$vehicles = DotApp::call('DACore:Plugins@listByContract!', 'fleet', 'v1', 'vehicles');
```

| extra3 | Meaning |
|--------|---------|
| `vehicles` | One-record get / whitelist save for a vehicle. **No** fleet dump |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a content-management host family |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

**Kind:** peer. **Controller:** `{Module}:FleetContract@…!`

The **host** **MUST NOT** set `extra1=fleet` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('fleet','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':FleetContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:FleetContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Fleet',             // exact module name
    'modes' => ['vehicles'],
    'families' => ['generic', 'erp'],
    'writable' => [
        'plate',
        'make',
        'model',
        'year',
        'status',
        'notes',
    ],
    'statuses' => ['active', 'idle', 'retired'],
    'plate_display' => true,         // plate is a display string (see §5)
]
```

**Failure:** `['ok' => false, 'message' => 'Fleet register is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Fleet.vehicle.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still `Auth::can` / ownership in PHP.

**MUST NOT** add a v1 `list` that returns every vehicle. **MUST NOT** `all()` on a growing vehicle table.

### `get($id)`

**Input:** `$id` string — encrypted vehicle id or pack-stable token.

**Success:**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',
    'plate' => 'BA-123AB',           // display string — see §5
    'make' => 'Ford',
    'model' => 'Transit',
    'year' => 2022,                  // int, or 0 when unknown
    'status' => 'active',
    'notes' => '',
]
```

**Failure:** decrypt fail, unknown id, gone row → `ok:false`.

### `save($id, $fields)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$id` | string | Encrypted id to update, or `''` / `null` to create |
| `$fields` | array | **Whitelist keys only**. Unknown keys are ignored and **MUST NOT** be persisted |

**Writable keys (v1):**

| Key | Type | Rule |
|-----|------|------|
| `plate` | string | Display string, length 1–32, charset `[A-Za-z0-9 .-]`. Unique inside the pack. **Not** HTML |
| `make` | string | Plain text, length 1–64 |
| `model` | string | Plain text, length 1–64 |
| `year` | int | `1900`–`2100`, or `0` when unknown. **MUST NOT** float |
| `status` | string | Exact token from `capabilities.statuses` |
| `notes` | string | Plain text, length 0–2000. **MUST NOT** store HTML |

**MUST NOT** persist `id`, `vin`, `user_id`, timestamps, or a request spread. Create still needs `plate` + `make` + `model`. Update may send a subset. Empty whitelist after filter → `ok:false`.

**Success (create):**

```php
[
    'ok' => true,
    'id' => '…ciphertext…',
    'created' => true,
]
```

**Success (update):** `['ok' => true, 'id' => '…ciphertext…', 'created' => false]`.

**Failure:** decrypt fail on a non-empty `$id`; illegal `plate` / `year` / `status`; missing required create fields; rights.

---

## 5. Plate is a display string (**MUST**)

`plate` is **not** an id and **not** markup. The pack stores and returns the raw registration text.

The **host MUST** run `htmlspecialchars($plate, ENT_QUOTES, 'UTF-8')` before `{{ var: }}` (`{{ var: }}` does **not** escape). In JS the host **MUST** `.text()`, never `.html()`, for the plate.

**MUST NOT** use the plate as a primary key in HTML (`data-id`, `value`, query string). The encrypted vehicle id is the only public handle.

---

## 6. Ids and tables

- Pack tables **MUST** be `{lowercase_modulename}_*` (example: `fleet_vehicles`). Never `dacore_*`.
- HTML **MUST NOT** print a raw integer id.
- Decrypt `false` → reject.
- `get` / `save` load **one** row. **MUST NOT** `all()` then pick.

---

## 7. Hooks

Fire only after a **new** vehicle row is created — **not** on every `save` update, **not** on `get`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.vehicle_created.hook` | Insert succeeded | `id` |

**MUST NOT** put `plate`, notes, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`vehicles`, `cars`, `garage`)
- `glob('app/modules')` or `include` the pack to discover it
- `all()` / unbounded `list` on vehicles
- Persist columns outside the `save` whitelist
- Print `plate` unescaped into HTML / `.html()`
- Use `plate` as the HTML id
- Leak `getMessage()`, SQL, VIN, or request bodies
- Set `extra1=fleet` on the host
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML vehicle id is encrypted
- Host `htmlspecialchars` on `plate` before `{{ var: }}`
- `save` whitelist only; no request spread
- No `all()` dump
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `get` / `save`
