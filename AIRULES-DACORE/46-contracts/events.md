# 46 — `events` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A host (CMS, Shop, ERP) and an events-and-tickets pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `events` |
| `extra2` | `v1` |
| `extra3` | `ticket` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit the key) |

```php
'extra1' => 'events',
'extra2' => 'v1',
'extra3' => 'ticket',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'events', 'v1');
$ticket = DotApp::call('DACore:Plugins@listByContract!', 'events', 'v1', 'ticket');
```

| extra3 | Meaning |
|--------|---------|
| `ticket` | Paged event catalogue + book a quantity of tickets |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

**Kind:** peer. **Controller:** `{Module}:EventsContract@…!`

The **host** **MUST NOT** set `extra1=events` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('events','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':EventsContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:EventsContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Events',            // exact module name
    'modes' => ['ticket'],
    'families' => ['generic', 'cms', 'shop'],
    'page_size' => 20,
    'max_qty' => 20,
    'iso8601' => true,
]
```

**Failure:** `['ok' => false, 'message' => 'Events are not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(Events.event.id): $id }}` / `{{ enc(Events.ticket.id): $ticketId }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still `Auth::can` / ownership in PHP.

`list` is a growing catalogue: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `list($opts)`

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `page` | int | 1-based page. Invalid / omitted → `1` |
| `q` | string | Optional title filter (bound `LIKE`, not raw SQL) |
| `from` | string | Optional inclusive start filter, ISO-8601 |
| `to` | string | Optional end filter, ISO-8601 |

`$from` / `$to` follow the same ISO-8601 rules as [booking.md](booking.md) §5. Unparseable → `ok:false`. Omitted keys mean “no time filter”. A filter window is **not** a licence to dump the table — still `page_size`.

**Success:**

```php
[
    'ok' => true,
    'items' => [
        [
            'id' => '…ciphertext…',  // eventRef for book()
            'title' => 'Autumn fair',
            'starts_at' => '2026-09-12T10:00:00+02:00',
            'ends_at' => '2026-09-12T18:00:00+02:00',
            'open' => true,
            'remaining' => 40,       // int tickets left; 0 when sold out
        ],
    ],
    'page' => 1,
    'last_page' => 4,
    'total' => 71,
]
```

`title` is plain text. The host **MUST** `htmlspecialchars` before `{{ var: }}` and use `.text()` in JS.

**Failure:** bad page (still coerced to `1` when the only error is the integer); unparseable range; rights. Empty catalogue is **success** with `items => []`, `total => 0`, `last_page => 1`.

### `book($eventRef, $qty)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$eventRef` | string | Encrypted event id from `list` `items[].id` |
| `$qty` | int | Tickets to issue, `1`…`max_qty`. Non-int / `< 1` / `> max_qty` → `ok:false` |

**Success:**

```php
[
    'ok' => true,
    'ticket_id' => '…ciphertext…',
]
```

One `ticket_id` is the handle for this booking (the pack **MAY** store `$qty` on that row). v1 does **not** return an array of per-seat ids.

**Failure:** decrypt fail; unknown / closed event; not enough remaining; rights. Product copy (`Not enough tickets left.`). **MUST NOT** leak remaining stock math beyond the public `remaining` on `list`.

**MUST NOT** decrypt another module’s `{{ enc }}` `$key2`.

---

## 5. Qty and stock

- `$qty` is an integer. A string `"2"` **MAY** be accepted after `(int)` only when it is a whole number in range; `"2.5"` / `"all"` → `ok:false`.
- Decrement remaining in the **same** persist as the ticket insert. **MUST NOT** `all()` tickets to count sold.
- `remaining` on `list` is a hint; `book` **MUST** re-check in PHP.

---

## 6. Ids and tables

- Pack tables **MUST** be `{lowercase_modulename}_*` (example: `events_events`, `events_tickets`). Never `dacore_*`.
- HTML **MUST NOT** print raw integer event or ticket ids.
- Decrypt `false` → reject.
- Indexes: list filter + sort (`starts_at`, search prefix as the pack’s query needs); `book` event id. One comment line per index naming that query.

---

## 7. Hooks

Fire only after a ticket **persists** — **not** on `list`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.ticket_booked.hook` | `book` inserted | `id` (ticket id), `qty` |

**MUST NOT** put attendee names, emails, titles, or secrets in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`event`, `tickets`, `ticketing`)
- `glob('app/modules')` or `include` the pack to discover it
- `all()` on events or tickets
- Accept `$qty` outside `1`…`max_qty`
- Return a list of raw seat ids
- Leak `getMessage()`, SQL, or request bodies
- Set `extra1=events` on the host
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML event / ticket id is encrypted
- `list` is `COUNT` + `LIMIT` with pager meta
- `book` returns `{ok, ticket_id}`
- Hooks named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `list` / `book`
