# 46 — `newsletter` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

This page is the **v1 peer contract** for a recipient-list pack. A `<Host>` and a pack must be able to interoperate from this page alone. Density matches [filemanager.md](filemanager.md).

**Sending mail is still `DACore:Email@send!`.** The pack **MUST NOT** invent SMTP, `mail()`, or a second sender UI. Read [38](../38-DACORE-EMAIL.md) when the pack actually sends. `extra1` **MUST NOT** be `email`.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `newsletter` |
| `extra2` | `v1` |
| `extra3` | `list` \| `list-segment` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty (omit) |

```php
'extra1' => 'newsletter',
'extra2' => 'v1',
'extra3' => 'list',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'newsletter', 'v1');
$list = DotApp::call('DACore:Plugins@listByContract!', 'newsletter', 'v1', 'list');
```

| extra3 | Meaning |
|--------|---------|
| `list` | Subscribe / unsubscribe against one list. `$listRef` is an encrypted list id |
| `list-segment` | Same subscribe I/O; the pack also stores **segments** (filters). `$listRef` is still a **list** id. Segment ids are pack-admin only in v1 |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** put an SMTP host or API key here |

**Kind:** peer. **Controller:** `{Module}:NewsletterContract@…!`

The **host** **MUST NOT** set `extra1=newsletter` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('newsletter','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':NewsletterContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

The host (or pack) settings `<select>` for **which DACore sender / template** to use on confirm or campaign mail comes from `DACore:Email@listSenders!` / `listTemplates!` — encrypted tokens in HTML. **MUST NOT** clone DACore SMTP forms.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:NewsletterContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'NewsList',          // exact module name
    'modes' => ['list'],             // extra3 this pack actually implements
    'double_opt_in' => true,         // confirm mail via DACore:Email@send!
    'unsubscribe_url' => '/api/v1/noauth/NewsList/unsubscribe', // pack public route or ''
]
```

**MUST NOT** return SMTP passwords, sender secrets, or subscriber emails.

**Failure:** `['ok' => false, 'message' => 'Newsletter lists are not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Subscriber tables that grow: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

Public HTTP that wraps `subscribe` **MUST** `throttle()`. Warn that an unauthenticated form is bot-bait (CAPTCHA is not MUST; `captcha` is a separate `extra1`).

### `subscribe($email, $listRef)`

**About:** Add (or re-add) an address to one list. Persist is PHP; a disabled button is UX only.

**Call:** `DotApp::call('{Module}:NewsletterContract@subscribe!', $email, $listRef)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$email` | string | Original address (`$request->data(true)` on HTTP). Trim + basic format check. Empty / illegal → `ok:false` |
| `$listRef` | string | Encrypted list id. Decrypt `false` / unknown / unpublished → `ok:false` |

**Success:** `['ok' => true]`.

Idempotent if already subscribed (still `ok:true`). Double opt-in: row stays pending until the confirm token is used; `ok:true` still means “accepted”, not “confirmed”.

**Failure:** bad email, decrypt fail, list gone, throttle at the HTTP layer already answered → `['ok' => false, 'message' => 'Could not subscribe that address.']`.

**MUST NOT** echo whether the email already exists on another list (enumeration). Same generic copy for duplicate / foreign.

When `double_opt_in` is true, the pack sends the confirm message with **`DACore:Email@send!`** (stored sender token + template slug + vars). Vars **MAY** include a confirm URL with a **token** (not the email). **MUST NOT** invent SMTP.

### `unsubscribe($token)`

**About:** Honor an unsubscribe token from mail or the pack public form.

**Call:** `DotApp::call('{Module}:NewsletterContract@unsubscribe!', $token)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$token` | string | One-time or durable unsubscribe token (`random_bytes`, stored hashed, `hash_equals`). **MUST NOT** be the raw email. Empty / unknown / spent → `ok:false` |

Admin “remove this subscriber” **MAY** pass an encrypted subscriber id **as** `$token` only when the pack documents that alias and decrypts it — still **MUST NOT** accept a raw email as `$token`.

**Success:** `['ok' => true]`. Idempotent if already unsubscribed.

**Failure:** `['ok' => false, 'message' => 'This unsubscribe link is not valid.']`.

Graphical confirm is **not** required on a one-click mail link; pack admin deletes still use `Notiflix.Confirm`.

---

## 5. Mail is DACore only (**MUST**)

Confirm, welcome, campaign, and unsubscribe-ack mail:

```php
DotApp::call('DACore:Email@send!', $senderTokenOrKey, $templateSlug, $vars);
```

Check the documented return (`!== true` / error array — [38](../38-DACORE-EMAIL.md)). Report `dotapp.catch` through the **pack** helper on abort.

**MUST NOT** `fsockopen` SMTP, `mail()`, or a vendor SDK that bypasses DACore senders. **MUST NOT** set `extra1=email`.

`$vars` **MUST NOT** include passwords. A confirm / unsubscribe **URL** uses the token, not the email.

---

## 6. Tokens and public HTTP (**MUST**)

- Tokens: `random_bytes`, store a hash, compare with `hash_equals`.
- Public subscribe / unsubscribe POST: `/api/v1/noauth/{Module}/…` + `#DACore:AuthTest@CRC!` **XOR** action `crcCheck()` — never both. **MUST** `throttle()`.
- Action that only `$request->upload()` is N/A here.
- In-process `subscribe!` / `unsubscribe!` have **no** CRC.

List / subscriber ids in admin HTML: `{{ enc(NewsList.list.id): $id }}`. Decrypt `false` → reject.

---

## 7. Lists vs segments

`list`: one `$listRef` per subscribe.

`list-segment`: the pack may filter a list into segments for **campaign** UI (pack admin). v1 `subscribe` / `unsubscribe` **do not** take a segment id. A host **MUST NOT** invent `segmentRef` on this contract.

Growing subscriber / campaign lists **MUST** paginate ([40](../40-DACORE-LIST-PAGER.md)).

---

## 8. Hooks

Fire only after a useful persist — **not** on a no-op idempotent re-subscribe if nothing changed (pack **MAY** skip).

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.newsletter_subscribed.hook` | New pending or confirmed row stored | `id` (subscriber row), `list_id` (internal int for the bus, not email) |
| `module.{mod}.newsletter_unsubscribed.hook` | Unsubscribe persisted | `id`, `list_id` |

**MUST NOT** put email, tokens, mail bodies, or request bodies in the payload. Use id / count only. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`email`, `smtp`, `mailchimp`, `listserv`)
- Invent SMTP or bypass `DACore:Email@send!`
- `glob('app/modules')` or `include` the pack to discover it
- Put email or tokens on the hook bus
- Accept a raw email as `unsubscribe($token)`
- Leak `getMessage()`, subscriber tables, or request bodies
- `all()` on a growing subscriber table
- Skip `throttle()` on the public subscribe POST
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- `subscribe($email, $listRef)` is `{ok}`; `$listRef` is ciphertext
- `unsubscribe($token)` is `{ok}`; token is not the email
- Mail goes through `DACore:Email@send!` only
- Hooks named in `.hooks` if fired; payload is id / count, **no email**
- Public POST is throttled; CRC once on HTTP, never on the helper
- No `crcCheck()` on `capabilities` / `subscribe` / `unsubscribe`
