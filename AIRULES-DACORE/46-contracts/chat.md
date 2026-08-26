# 46 — `chat` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the v1 peer contract. A host (CMS, Shop, ERP) and a live-chat pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

This is **not** DACore notifications, email, or SMS (`extra1` `notification` / `email` / `sms` are forbidden). Inbox push stays `DACore:Notifications@push` on a real event — not from `send`. This is **not** `helpdesk` (tickets) and **not** `forum` (board).

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `chat` |
| `extra2` | `v1` |
| `extra3` | `live` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'chat',
'extra2' => 'v1',
'extra3' => 'live',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'chat', 'v1');
$live = DotApp::call('DACore:Plugins@listByContract!', 'chat', 'v1', 'live');
```

| extra3 | Meaning |
|--------|---------|
| `live` | Host sends a message into a named channel; history UI stays in the pack |

| extra4 | Meaning |
|--------|---------|
| `generic` | Any host family |
| `cms` | Tuned for a CMS host |
| `shop` | Tuned for a shop host |
| `erp` | Tuned for an ERP host |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier in v1. **MUST NOT** invent `websocket` / `widget` as `extra5` |

**Kind:** peer. **Controller:** `{Module}:ChatContract@…!`

The **host** **MUST NOT** set `extra1=chat` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('chat','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':ChatContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:ChatContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'LiveChat',          // exact module name
    'modes' => ['live'],
    'families' => ['generic', 'shop'],
    'channels' => [                  // bounded channel picker
        [
            'id' => '…ciphertext…',  // {{ enc(LiveChat.channel.id) }} unique $key2
            'title' => 'Support',
            'open' => true,
        ],
    ],
    'body_max' => 2000,
]
```

**About:** `channels[]` is a **bounded** choice (`<select>` / `dotSelect2`). Do not hide known channels behind a typed slug. Growing transcript lists are the pack’s own paged UI (`COUNT` + `LIMIT`) — v1 does **not** add `listMessages`.

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Chat is not ready.',
]
```

Product copy only. **MUST NOT** `getMessage()` or dump channel SQL.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted channel ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing transcripts: pack pages them — **MUST NOT** `all()`.

### `send($channelRef, $body)`

**Call:** `DotApp::call('{Module}:ChatContract@send!', $channelRef, $body)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$channelRef` | string | Encrypted channel id or pack-stable token. Empty → `ok:false` |
| `$body` | string | Message text. Trimmed. Length 1…`body_max`. HTTP collectors **MUST** `$request->data(true)` (original) |

**Success:**

```php
[
    'ok' => true,
    'message_id' => '…ciphertext…',
]
```

**Failure:**

```php
[
    'ok' => false,
    'message' => 'Could not send this message.',
]
```

Decrypt fail, unknown / closed channel, empty or too-long body, persist fail. **MUST NOT** persist on failure. **MUST NOT** echo `$body` in the reply. **MUST NOT** distinguish “unknown channel” vs “closed” vs “bad user” with different copy (enumeration).

**About:** The host that shows a composer **MUST** `htmlspecialchars` the draft. Actor identity (if stored) is the current Auth user the **host** already gated; the pack **MUST NOT** invent a user from the request. **MUST NOT** put the body on the hook (§8).

---

## 5. HTML, ids, and escaping (**MUST**)

1. Channel and message ids in HTML = `{{ enc(...) }}` unique `$key2`. Decrypt `false` → reject. Still `Auth::can` / ownership in PHP.
2. Host **MUST** `htmlspecialchars` any channel title from `capabilities()` before `{{ var: }}` — the tag does **not** escape.
3. JS inserts use `.text()`, not `.html()`, for titles and bodies.
4. Channel picker = native `<select>` / `dotSelect2` of `channels[]`. **MUST NOT** a bare text slug box for a known channel list ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §3).

---

## 6. Transport and pack UI

Live UI (polling / pack JS) is **the pack’s** assets via `withMenu` `$js` if the host embeds a widget. **MUST NOT** copy DACore JS. HTTP stays `$dotapp().load` / `form` — never `$.ajax`.

Public pack POST: `#DACore:AuthTest@LoginAndCRC!` / `@CRC!` **XOR** action `crcCheck()`. These **contract** helpers stay **no CRC**.

Public noauth composer (if the pack ships one) **MUST** `throttle()` and warn that bots can post (CAPTCHA not MUST; a `captcha` pack is a separate `extra1`).

---

## 7. Scope vs other roles (**MUST**)

**MUST NOT** use this role for email, SMS, or DACore inbox. Those are DACore registries ([38](../38-DACORE-EMAIL.md), [39](../39-DACORE-SMS.md), [37](../37-DACORE-NOTIFICATIONS.md)).

Tickets belong on `helpdesk`. Boards belong on `forum`. **MUST NOT** invent `extra1` `livechat` / `messenger`.

v1 has **no** `ChatContract@list`. Transcript browse is pack admin, paged ([40](../40-DACORE-LIST-PAGER.md)).

Host **MUST** call `send` in-process after pick:

```php
$reply = DotApp::call($module . ':ChatContract@send!', $channelRef, $body);
if (!is_array($reply) || empty($reply['ok'])) {
    // toast — MUST NOT echo $body
}
```

Pack tables `{lowercase_modulename}_*` (example: `livechat_messages`). Index `channel_id` for the transcript page query; comment names that query. **MUST NOT** write `dacore_*`.

A persist `catch` / `execute()` `$err` reports `dotapp.catch` through the pack helper, then `ok:false` with product copy. The operator still sees a toast.

---

## 8. Hooks

Fire only after a useful persist — **not** on `capabilities`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.chat_sent.hook` | Message row inserted | `channel_id`, `message_id` |

**MUST NOT** put the body, MSISDN, email, or IP in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`livechat`, `messenger`, `websocket`, `support-chat`)
- Use `email` / `sms` / `notification` as a competing role
- `glob('app/modules')` or `include` the pack to discover it
- Put **body** on `chat_sent`
- Echo `$body` in the `send` reply
- `all()` on a growing message table
- Leak `getMessage()`, request bodies, or secrets
- PHP 8+ syntax unless the plan named a higher version
- Set `extra1=chat` on the host

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML channel / message id is encrypted
- `send` returns `message_id`, not the body
- Every method has input table + success/fail PHP arrays
- `chat_sent` carries ids only (no body); named in `.hooks` if fired
- No `crcCheck()` on `capabilities` / `send`
