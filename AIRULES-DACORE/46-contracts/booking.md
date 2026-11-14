# 46 — `booking` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a reservation-slot pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `booking` |
| `extra2` | `v1` |
| `extra3` | `slot` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit the key) |

```php
'extra1' => 'booking',
'extra2' => 'v1',
'extra3' => 'slot',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'booking', 'v1');
$slot = DotApp::call('DACore:Plugins@listByContract!', 'booking', 'v1', 'slot');
```

| extra3 | Meaning |
|--------|---------|
| `slot` | Bounded / paged availability window, then book one slot for a party |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a content-management host family |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

**Kind:** peer. **Controller:** `{Module}:BookingContract@…!`

The **host** **MUST NOT** set `extra1=booking` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('booking','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':BookingContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:BookingContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Bookings',          // exact module name
    'modes' => ['slot'],
    'families' => ['generic', 'cms', 'shop'],
    'iso8601' => true,
    'max_range_days' => 31,
    'page_size' => 50,
    'resource_ref' => 'encrypted-local',
    'party_ref' => 'encrypted-local',
]
```

**Failure:** `['ok' => false, 'message' => 'Booking is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2` (`Bookings.resource.id`, `Bookings.slot.id`, `Bookings.booking.id`, `Bookings.party.id`). Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still `Auth::can` / ownership in PHP.

`$from` and `$to` are **ISO-8601** (see §5). Bind them in SQL — **MUST NOT** interpolate into `$qb->raw()`.

Growing slot lists: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `availability($resourceRef, $from, $to, $page = 1)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$resourceRef` | string | Encrypted resource id **this pack** minted |
| `$from` | string | Inclusive start, ISO-8601 |
| `$to` | string | Exclusive or inclusive end, ISO-8601 — pack **MUST** document which in `capabilities` if it differs; v1 default **inclusive date / exclusive datetime** |
| `$page` | int | Optional 1-based page. Invalid / omitted → `1` |

The span `$from`…`$to` **MUST** be ≤ `max_range_days` (calendar days). Longer → `ok:false`. The page is still `page_size` rows (`COUNT` + `LIMIT` / `OFFSET`). **MUST NOT** return an unbounded slot array.

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',  // slotRef for book()
            'starts_at' => '2026-08-25T09:00:00+02:00',
            'ends_at' => '2026-08-25T10:00:00+02:00',
            'open' => true,
        ],
    ],
    'page' => 1,
    'last_page' => 2,
    'total' => 17,
]
```

Display datetimes stay ISO-8601 in JSON. The host formats for the operator and **MUST** `htmlspecialchars` any label it adds.

**Failure:** decrypt fail; unknown resource; unparseable / inverted range; span too long; rights.

### `book($slotRef, $partyRef)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$slotRef` | string | Encrypted slot id from `availability` `items[].id` |
| `$partyRef` | string | Encrypted party token **this pack** can resolve (customer / guest row this pack minted). **MUST NOT** a raw user id integer |

**Success:**

```php
[
    'ok' => true,
    'booking_id' => '…ciphertext…',
]
```

**Failure:** decrypt fail; unknown / already taken slot; unknown party; rights. Product copy only (`That slot is no longer available.`).

**MUST NOT** decrypt another module’s `{{ enc }}` `$key2` for `$resourceRef` / `$slotRef` / `$partyRef`.

---

## 5. ISO-8601 (**MUST**)

Accepted v1 forms:

- Datetime with offset: `2026-08-25T09:00:00+02:00`
- Datetime UTC: `2026-08-25T07:00:00Z`
- Date only: `2026-08-25` (pack treats as that local calendar day)

Unparseable, empty, or `$from` after `$to` → `ok:false`. **MUST NOT** `str_contains` (PHP 7.4). Parse with `DateTime::createFromFormat` / `DateTime` and reject `false`.

---

## 6. Ids and tables

- Pack tables **MUST** be `{lowercase_modulename}_*` (example: `bookings_slots`, `bookings_reservations`). Never `dacore_*`.
- HTML **MUST NOT** print raw integer ids for resource, slot, booking, or party.
- Decrypt `false` → reject.
- Indexes: every `WHERE` / `ORDER BY` used by `availability` / `book` (resource + time range + sort). One comment line per index naming that query.

---

## 7. Hooks

Fire only after a booking **persists** — **not** on `availability`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.booking_created.hook` | `book` inserted | `id` (booking id) |

**MUST NOT** put party names, emails, slot labels, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`reservation`, `reservations`, `slots`)
- `glob('app/modules')` or `include` the pack to discover it
- `all()` or an unbounded `$from`/`$to` window
- Accept non-ISO datetimes
- Pass a raw integer as `$partyRef` / `$slotRef`
- Leak `getMessage()`, PAN, OTP, or request bodies
- Set `extra1=booking` on the host
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML id is encrypted
- `availability` is paged and span-capped
- `book` returns `{ok, booking_id}`
- Datetimes are ISO-8601
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `availability` / `book`
