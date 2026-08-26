# 46 — `calendar` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop, ERP) and an agenda pack must be able to interoperate from this page alone. Machine catalog: `DACore\Libraries\ExtraContracts` role `calendar`, controller `CalendarContract`.

v1 is a **bounded agenda window**. Ticket sales are `events`. Slot booking is `booking`. **MUST NOT** `all()` a growing event table.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `calendar` |
| `extra2` | `v1` |
| `extra3` | `agenda` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'calendar',
'extra2' => 'v1',
'extra3' => 'agenda',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'calendar', 'v1');
$agenda = DotApp::call('DACore:Plugins@listByContract!', 'calendar', 'v1', 'agenda');
```

| extra3 | Meaning |
|--------|---------|
| `agenda` | Bounded range (max 62 calendar days) or a paged range of agenda items. **No** unbounded dump |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** invent `ics` / `sync` here |

**Kind:** peer. **Controller:** `{Module}:CalendarContract@…!`

The **host** **MUST NOT** set `extra1=calendar` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('calendar','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':CalendarContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:CalendarContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'AgendaKit',         // exact module name
    'modes' => ['agenda'],
    'families' => ['generic', 'cms', 'erp'],
    'iso8601' => true,
    'max_range_days' => 62,
    'paged' => true,
    'page_size' => 100,
]
```

`max_range_days` **MUST** be `62` or lower in v1. `paged` true means a span longer than 62 days is still allowed **only** when `$page` is used and each page is `page_size` (see §4). `paged` false means any span `> max_range_days` → `ok:false`.

**Failure:** `['ok' => false, 'message' => 'Calendar is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Event ids that leave PHP toward HTML **MUST** be `{{ enc(AgendaKit.event.id): $id }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still `Auth::can` / ownership in PHP.

`$from` and `$to` are **ISO-8601** (see §5). Bind them in SQL — **MUST NOT** interpolate into `$qb->raw()`.

**MUST NOT** `all()` on a growing agenda table. **MUST NOT** return an unbounded `items` array.

### `range($from, $to, $page = 1)`

**Call:** `DotApp::call('{Module}:CalendarContract@range!', $from, $to, $page)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$from` | string | Inclusive start, ISO-8601 |
| `$to` | string | End, ISO-8601 (v1 default: inclusive date / exclusive datetime) |
| `$page` | int | Optional 1-based page. Invalid / omitted → `1`. Ignored when `paged` is false **and** the span is ≤ 62 days (single bounded payload) |

**Bounds (MUST):**

1. Unparseable / empty / `$from` after `$to` → `ok:false`.
2. Calendar-day span `> 62` and `paged` is false → `ok:false` (`Choose a shorter date range.`).
3. Calendar-day span `> 62` and `paged` is true → allowed; still `COUNT(*)` + `LIMIT` / `OFFSET` at `page_size`. **MUST NOT** load every matching row.
4. Span `≤ 62` days: pack **MAY** return all overlapping items in one page when `total` ≤ `page_size`; otherwise it **MUST** page. Never `all()` then slice in PHP.

An item **overlaps** the window when its `starts_at` / `ends_at` intersects `[$from, $to)`. **MUST NOT** filter in PHP after loading the table.

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',
            'title' => 'Board meeting',
            'starts_at' => '2026-08-25T09:00:00+02:00',
            'ends_at' => '2026-08-25T10:30:00+02:00',
            'all_day' => false,
        ],
    ],
    'page' => 1,
    'last_page' => 1,
    'total' => 3,
]
```

When `paged` is false and the span is ≤ 62 days, `page` / `last_page` **MUST** still be present (`1` / `1` when everything fits).

`total` is `COUNT(*)` of the overlapping set, not `count($items)`.

`title` is plain text. The host **MUST** `htmlspecialchars` before `{{ var: }}` and use `.text()` in JS.

**Failure:** bad range; span too long without paging; rights. Empty window is **success** with `items => []`, `total => 0`, `last_page => 1`.

v1 has **no** `save` / `book` on this controller. Creating events is the pack’s own admin UI, not this peer surface.

---

## 5. ISO-8601 and host HTTP

Accepted v1 forms (same as [booking.md](booking.md) §5):

- Datetime with offset: `2026-08-25T09:00:00+02:00`
- Datetime UTC: `2026-08-25T07:00:00Z`
- Date only: `2026-08-25` (pack treats as that local calendar day)

Unparseable, empty, or `$from` after `$to` → `ok:false`. **MUST NOT** `str_contains` (PHP 7.4). Parse with `DateTime::createFromFormat` / `DateTime` and reject `false`.

`range` is in-process. **No CRC** on `CalendarContract@range!`.

If the host wraps `range` in an AJAX month view, that POST lives on `/api/v1/auth/{Host}/…` + `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again. Encrypted `data-page` when paged ([40](../40-DACORE-LIST-PAGER.md)). Overlay until the request ends.

A public calendar POST **MUST** `throttle()`. Contract methods still have **no CRC**.

---

## 6. Admin / UI

Pack choice = `<select>` / `dotSelect2`. Agenda result lists / month grids paginate or stay inside the 62-day cap. Date range is two date controls, not an open-ended “all future” dump.

Deletes in pack admin: graphical confirm (`Notiflix.Confirm` on admin). Toast the outcome.

No media picker. This role has no `picker_js`.

---

## 7. Bounds, ids, and tables v1 (**MUST**)

- Event ids in HTML are encrypted. Decrypt `false` → reject.
- Pack tables **MUST** be `{lowercase_modulename}_*` (example: `agendakit_events`). Never `dacore_*`.
- Indexes: range / overlap query (`starts_at` / `ends_at` sort). Composite order equality → range → sort. One comment line per index naming the `range` query.
- Missing `$to`, open end, or “all future” → `ok:false`. **MUST NOT** unbounded `all()`.

---

## 8. Hooks

`range` is a read. **MUST NOT** fire a hook on `range`.

If the pack’s own admin persists an event, fire only after a useful persist:

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.calendar_event_saved.hook` | Event created or replaced in pack admin | `event_id` |

**MUST NOT** put titles, attendee emails, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

This contract does not require a hook when the pack only reads.

---

## 9. MUST NOT

- Invent `extra1` (`agenda`, `calendars`, `schedule`)
- `glob('app/modules')` or `include` the pack to discover it
- `all()` or an unbounded `$from`/`$to` (open end, “all future”, missing `$to`)
- Skip the 62-day cap when `paged` is false
- Return raw integer event ids
- Leak `getMessage()`, SQL, or request bodies
- Set `extra1=calendar` on the host
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1 (`extra3=agenda`)
- Host lists with `listByContract!`
- Every public HTML event id is encrypted
- `range` returns `{ok, items[]}` with pager meta
- Span capped at 62 days **or** paged
- No unbounded `all()`
- No `crcCheck()` on `capabilities` / `range`
