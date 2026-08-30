# 46 — `esign` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and an electronic-signature pack must be able to interoperate from this page alone.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `esign` |
| `extra2` | `v1` |
| `extra3` | `session` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'esign',
'extra2' => 'v1',
'extra3' => 'session',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'esign', 'v1');
$session = DotApp::call('DACore:Plugins@listByContract!', 'esign', 'v1', 'session');
```

| extra3 | Meaning |
|--------|---------|
| `session` | One signing session per `start()`. Host polls `status()` until `signed`, `pending`, or `failed`. Pack MAY redirect the signer to a hosted UI |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No v1 qualifier. **MUST NOT** invent `extra5` tokens (`qes`, `aes`, `otp`) |

**Kind:** peer. **Controller:** `{Module}:EsignContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=esign` on itself.

This role is **not** `dms`, **not** `filemanager`, and **not** `workflow`. Those packs store records, files, or BPM steps. An esign pack only opens a signing session and reports its state.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('esign','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':EsignContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:EsignContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'ESign',              // exact module name
    'modes' => ['session'],           // extra3 this pack actually implements
    'redirect' => true,               // false when the pack has no hosted signer URL
    'poll_seconds' => 5,              // host hint for status() polling; 0 = host chooses
    'signature_fetch_url' => '',      // optional rights-gated HTTP; empty = not offered
]
```

`signature_fetch_url` is **not** a required v1 method. When non-empty it is a pack `/api/v1/auth/{Module}/…` URL. The **host** loads it with `$dotapp().load()` (or a rights-checked download) and an encrypted `session_id`. The pack **MUST** re-check rights / ownership in PHP. **MUST NOT** put image bytes on `start()` or `status()`.

**Failure:** `['ok' => false, 'message' => 'Signature service is not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` with a unique `$key2`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing session lists on the pack’s own admin: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

### `start($documentRef, $signerHint)`

**Input:**

| Arg | Type | Meaning |
|-----|------|---------|
| `$documentRef` | string | Encrypted host document id (invoice, contract, DMS record). Empty / decrypt fail → `ok:false` |
| `$signerHint` | string | Optional signer hint: email address or encrypted user id. **MUST NOT** be a password, OTP, or rights blob. Empty is allowed |

**Success:**

```php
[
    'ok' => true,
    'session_id' => '…ciphertext…',
    'redirect_url' => 'https://…',   // omit the key, or null, when redirect is false
]
```

`redirect_url` is optional. When present it is the pack’s (or provider’s) signer page. Host **MUST NOT** put request data into `header()` / redirect / `HttpHelper` as that URL — use the string the pack returned after its own whitelist.

**Failure:** unknown document, decrypt fail, signer rejected, pack not configured → `ok:false`.

**MUST NOT** return signature image bytes, `bytes_b64`, PNG/SVG, biometric samples, provider tokens, or the raw document body.

### `status($session_id)`

**Input:** `$session_id` string — the encrypted id from `start()`. Decrypt `=== false` → `ok:false`.

**Success:**

```php
[
    'ok' => true,
    'state' => 'signed',   // signed | pending | failed — whitelist only
]
```

| `state` | Meaning |
|---------|---------|
| `pending` | Session exists; signer has not finished |
| `signed` | Pack persisted a completed signature. Fire `esign_completed` **once** |
| `failed` | Provider or pack aborted. Host shows a toast; do not retry blindly in a loop |

**Failure:** unknown / decrypt fail / session gone → `ok:false`.

**MUST NOT** return the signature image, certificate PEM, or provider webhook body in this reply.

---

## 5. Optional signature fetch (not `EsignContract`)

v1 required methods are only `capabilities`, `start`, and `status`. A pack **MAY** offer a separate HTTP fetch when `signature_fetch_url` is non-empty.

- Route: pack `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` if it is a normal POST that is **not** `$request->upload()`.
- Input: encrypted `session_id` (`{{ enc(...) }}`). Decrypt fail → reject.
- Rights: pack `#YourModule:Rights@check!` — **not** `#DACore:AuthTest@check!`.
- Success body may include `mime` + `bytes_b64` or a short-lived `url`. Still **MUST NOT** leak the document file, PAN, or OTP.
- Host **MUST NOT** invent `EsignContract@image` / `@download` as a required v1 method.

---

## 6. Host UI

- Start / poll from the host’s own page. Overlay the card until `start()` or `status()` returns (Notiflix on DACore admin).
- When `redirect_url` is set, leave the page only with `redirectTo` (or an explicit new-window control). Do not `location.reload()` after poll.
- Show every failure with a toast (admin) or a field error (public). Silent `pending` forever is a bug — surface `failed`.
- If the host prints `signerHint` or a document title into HTML, **MUST** `htmlspecialchars` first (`{{ var: }}` does **not** escape).

---

## 7. Hooks

Fire only after a useful persist — **not** on `start()`, **not** on `pending` `status()`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.esign_completed.hook` | Session state first becomes `signed` | `session_id` **only** |

**MUST NOT** put signature bytes, certificates, signer email, document bodies, tokens, or `redirect_url` in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`e-sign`, `docusign`, `signature`, `qes`)
- Invent `extra3` (`qes`, `aes`, `otp`, `multi`) — v1 is `session` only
- `glob('app/modules')` or `include` the pack to discover it
- Return signature image bytes on `start()` or `status()`
- Leak `getMessage()`, provider secrets, OTP, PAN, or request bodies
- `all()` on a growing session table
- `eval` / `exec` / `unserialize` of signer or provider payloads
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Every public HTML id (`session_id`, `documentRef`) is encrypted
- `start` / `status` never include image bytes
- Hook `esign_completed` named in `.hooks` if fired — payload is `session_id` only
- No `crcCheck()` on `capabilities` / `start` / `status`
