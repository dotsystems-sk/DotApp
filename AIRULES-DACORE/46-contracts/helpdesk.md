# 46 — `helpdesk` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A Shop / CMS / ERP **host** and a helpdesk **pack** must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md). This is **not** `chat` (live channel) and **not** `kb` (articles).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `helpdesk` |
| `extra2` | `v1` |
| `extra3` | `tickets` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'helpdesk',
'extra2' => 'v1',
'extra3' => 'tickets',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'helpdesk', 'v1');
$tickets = DotApp::call('DACore:Plugins@listByContract!', 'helpdesk', 'v1', 'tickets');
```

| extra3 | Meaning |
|--------|---------|
| `tickets` | Support tickets: `open` then `reply`. **MUST NOT** invent `chat` as extra3 (that is `extra1=chat`) |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `zendesk` / `imap` as `extra5` |

**Kind:** peer. **Controller:** `{Module}:HelpdeskContract@…!`

The **host** **MUST NOT** set `extra1=helpdesk` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('helpdesk','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':HelpdeskContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:HelpdeskContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'SupportDesk',         // exact module name
    'modes' => ['tickets'],
    'families' => ['generic', 'shop'],
    'body_ref_kinds' => ['order', 'invoice', 'token'],
    'lists' => true,                   // pack admin ticket list
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Helpdesk is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()`.

If `lists` is `true`, the ticket list **MUST** paginate with `COUNT` + `LIMIT` ([40](../40-DACORE-LIST-PAGER.md)). v1 has **no** `HelpdeskContract@list`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ticket ids that leave PHP toward HTML **MUST** be `{{ enc(Helpdesk.ticket.id): $ticketId }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Still check rights / ownership in PHP. Growing ticket / reply tables: **MUST NOT** `all()`.

### `open($subject, $body_ref)`

**Call:** `DotApp::call('{Module}:HelpdeskContract@open!', $subject, $body_ref)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$subject` | string | Short title. Max 190. Empty → `ok:false`. **MUST** escape before any view |
| `$body_ref` | string | Host ciphertext or pack-stable token for the **related record** (order, invoice, page) — **not** the ticket message body |

`$body_ref` is an opaque pointer so the pack can show “about this order” without the host stuffing HTML into `open`. Empty `$body_ref` is allowed only when `body_ref_kinds` includes `token` and the pack documents anonymous tickets; otherwise `ok:false`.

**MUST NOT** accept the message HTML/text as `$body_ref`. First public reply uses `reply`.

Optional extra keys are **not** in v1. Hosts that need a contact attach it in **their** table keyed by the returned `ticket_id`.

**Success:**

```php
[
    'ok' => true,
    'ticket_id' => '…ciphertext or pack token…',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not open this ticket.',
]
```

Empty subject; illegal `$body_ref`; decrypt fail when the ref must decrypt. **MUST NOT** persist on failure. **MUST NOT** echo `$subject` or `$body_ref` in the reply.

### `reply($ticket_id, $body)`

**Call:** `DotApp::call('{Module}:HelpdeskContract@reply!', $ticket_id, $body)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$ticket_id` | string | Ciphertext or pack-stable token from `open` |
| `$body` | string | Reply text. HTTP collectors **MUST** `$request->data(true)` (original). Escape before `{{ var: }}` |

**Success:**

```php
[
    'ok' => true,
    'ticket_id' => '…same ticket…',
    'reply_id' => '…ciphertext or pack token…',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not add this reply.',
]
```

Decrypt fail / unknown ticket / closed ticket / empty body. **MUST NOT** persist on failure. **MUST NOT** echo `$body` back in the JSON (the host already has it).

---

## 5. Body vs body_ref (**MUST**)

| Piece | Where | Rule |
|-------|-------|------|
| Subject | `open` | Title only |
| Related record | `open` `$body_ref` | Encrypted host id or token — **not** message bytes |
| Message | `reply` `$body` | Stored by the pack; **MUST NOT** appear in hooks |
| HTML views | Pack / host templates | `htmlspecialchars` — `{{ var: }}` does **not** escape |

JS inserts use `.text()`, not `.html()`, for subject and body.

---

## 6. Encrypted ids and lists

Every ticket id and reply id that leaves PHP toward HTML uses `{{ enc(...) }}` with a unique `$key2`. Decrypt `false` → reject.

If `lists` is `true`, pack admin **MUST** page (`COUNT` + `LIMIT`). Pager `data-page` is ciphertext ([40](../40-DACORE-LIST-PAGER.md)).

---

## 7. Public HTTP and CRC

Public HTTP open / reply on the pack’s own `/api/v1/auth|noauth/{Module}/…` uses that route’s CRC prefix (`LoginAndCRC!` / `CRC!`). These **contract** helpers stay in-process with **no CRC**. The action **MUST NOT** `crcCheck()` after a CRC prefix.

Public noauth **MUST** `throttle()` and warn that bots can open tickets (CAPTCHA not MUST; a `captcha` pack is a separate `extra1`).

After a successful `fo-rm` / `load` on the same page: patch `reply.html` + toast — **MUST NOT** `location.reload()`. Overlay until the request ends.

---

## 8. Hooks

Fire only after a useful persist — **not** on a later `get` if the pack adds one.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.ticket_opened.hook` | Ticket created | `ticket_id` |
| `module.{mod}.ticket_replied.hook` | Reply persisted | `ticket_id`, `reply_id` |

`ticket_opened` **MUST** carry the **id**, **not** the body (and not `$subject` / `$body_ref` text). Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`support`, `tickets`, `zendesk`)
- Treat `$body_ref` as message HTML
- Put ticket body or subject in `ticket_opened` / `ticket_replied`
- `glob('app/modules')` or `include` the pack to discover it
- Leak `getMessage()`, secrets, or request bodies
- `all()` on a growing ticket table
- Plaintext ticket id in HTML
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=helpdesk` on the host

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML ticket / reply id is encrypted
- `open` takes `$body_ref`, not the message; `reply` takes `$body`
- Every method has input table + success/fail PHP arrays
- Hook `ticket_opened` is id-only; named in `.hooks` if fired
- If `lists` is true, the admin list is paged
- No `crcCheck()` on `capabilities` / `open` / `reply`
