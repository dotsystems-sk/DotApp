# 23 — Debug playbook (“it doesn’t work”)

This file is **extra hunt rules**, not a replacement for [08](08-FORMS-AND-SECURITY.md) / [19](19-VALIDATION-AND-INPUT.md). Use it when the user asks **why** a form, login, AJAX save, installer, or list request fails.

**After writing code** (before the user reports a bug): **MUST** run the finish gate — [00](00-AGENT-CONTRACT.md) §2c. This playbook is the **reactive** hunt.

**MUST** search before inventing a core bug. **MUST NOT** patch `app/parts/` or `DotApp.php`.

---

## 1. Trigger

Read this file when the user says: doesn’t work, 400, Bad request, empty error, login fails, installer fails, “I clicked Save and nothing”, CRC / CSRF, middleware.

Open **one** sample after the hunt: [EX-01](examples/EX-01-secure-form-complete.md) (forms), [EX-14](examples/EX-14-auth-and-2fa.md) (login).

---

## 1b. Read the catch trail first (**MUST** when the code already ships it)

Every `catch` and every `execute()` `$err` in this project reports to the catch bus ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9). Read that trail **before** guessing: it names the failing `operation`, the `source`, and the real `message`.

```php
// Temporary, in your module's module.listeners.php — remove when done.
Events::on('dotapp.catch', function ($payload) {
    Logger::use('debug')->error($payload['operation'] ?? 'unknown', $payload);
});
```

| Signal in the payload | Read it as |
|-----------------------|------------|
| `severity => 'error'`, `source` = the action | The handler aborted — start at `file`/`line` |
| `severity => 'info'` repeated many times | A “recovered” path is firing constantly — usually the real bug |
| no event at all for a failing click | The failure is **before** your handler (CRC / middleware / route) → §2 |
| `operation` present but the user saw nothing | Visible outcome is missing ([00](00-AGENT-CONTRACT.md) §2d) — a second bug, fix both |

If the module has `catch` blocks that do **not** report, add the report helper first — that is the fix, not a detour.

Log files need `Config::logger('core_log_enabled', true)` ([20](20-CACHE-LOGGER-SESSION.md) §2); the bus itself fires regardless.

---

## 1c. Event tracer — `dotapp.catchall` (when building a debugger, or hunting a missing event)

The core fires **`dotapp.catchall` on every `trigger()`** except itself ([01](01-ARCHITECTURE.md) Built-in events). That is the **one** listener a debug tool **MUST** use to see the whole event stream. It is **not** `dotapp.catch` (failures only — §1b).

```php
// Temporary, in your module's module.listeners.php — remove or gate when done.
Events::on('dotapp.catchall', function ($result, $eventname, ...$data) {
    try {
        Logger::use('debug')->warning($eventname, ['argc' => count($data)]);
    } catch (\Throwable $ignored) {
        // A throw here aborts the original event — never let that happen.
    }
});
```

| Need | Subscribe to |
|------|----------------|
| Every event in the request (boot, router, module, log, catch reports) | `dotapp.catchall` — core |
| Structured failures (`operation`, `source`, `message`) | `dotapp.catch` — your report helper |
| Log lines | `dotapp.log` ([20](20-CACHE-LOGGER-SESSION.md)) |
| A named business step (`module.shop.sms_sent.hook`) | That exact name from the owner’s `.hooks` ([41](41-MODULE-HOOKS.md)) |

**Hunting a missing business event:** open `app/modules/<Owner>/.hooks` (Fired section), then grep `Events::trigger(` in that module. If the name is not there, it was never fired — **MUST NOT** invent it. Ordinary saves may have **no** hook by design ([41](41-MODULE-HOOKS.md)). Catchall will only show names that were actually triggered.

**MUST NOT:** `Events::trigger('dotapp.catchall', …)` (core already does it); trigger other events from this listener; persist every event without an opt-in flag; log `$result` wholesale (secrets). Canonical: [12](12-SERVICES.md) §2.

---

## 2. Grep first (**MUST**)

Count every `$request->crcCheck()` / `crcCheck(` on the **failing route’s pipeline** — not only the controller.

| Where | What to open |
|-------|----------------|
| Module middleware | `app/modules/<Module>/Middleware/*.php` |
| Route hooks | `module.init.php` — `->before(`, `->middleware(`, `Middleware::use`, `Middleware::register` |
| Listeners | `module.listeners.php`, `app/listeners.php` (ask before editing listeners) |
| The action | `Controllers/*.php` of the POST URL |
| Shared “security” helpers | any `Crc` / `Secure` / `Gate` class in **this** module |

**Two or more `crcCheck()` on one request = the first call burned the one-time token; the second returns `false`.** That is the usual “Bad request” after a generic CRC middleware.

Read the action’s first PHPDoc line **`CRCchecking —`**. If it already names a CRC prefix/middleware, the body **MUST NOT** call `crcCheck()`. A missing `CRCchecking —` line on a public controller/middleware method is a docs bug ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7).

Canonical: [08](08-FORMS-AND-SECURITY.md) “`crcCheck()` burns the token”.

---

## 3. If you write middleware

**MUST:** `crcCheck()` lives in **one** place.

| Middleware does | Controller / action |
|-----------------|---------------------|
| Prefix already called `crcCheck()` (`#Shop:Gate@crc!` / `@loginAndCrc!`) | **MUST NOT** call `crcCheck()` again — only `form()` / persist |
| HTML `Gate@login` only (no CRC) | **MUST** `crcCheck()` **once** in the action if that POST has no API prefix |
| No prefix CRC on that POST | **MUST** `crcCheck()` **once** in the action |

**MUST NOT** CRC-`before` on GET or on `*`. The intended catch-all is **POST** `/api/v1/auth|noauth/{Module}/*` ([03](03-MODULES-AND-ROUTING.md)). HTML `Gate@login` stays CRC-free.

`$request->form()` does **not** run `crcCheck()`.

---

## 4. Hunt order (POST / AJAX)

Work top-down. Stop when you find a match.

1. **`crcCheck()` count** (section 2). Middleware + controller is guilty until proven otherwise.
2. **`formName` placement** — must sit **between** `<fo-rm>` and `</fo-rm>`. Outside the pair the tag is left unchanged (silent fail).
3. **`/assets/dotapp/dotapp.js`** loaded **before** module JS (session keys). Missing → CRC/CSRF fields wrong.
4. **`$request->form(...)`** — missing error callback throws; method mismatch → `false`; wrong handler name → `null`. Guard all three and **show** `reply.message`.
5. **Original vs protected input** — every persisted URL/setting/title/token/password/HTML/hash **MUST** be `$request->data(true)` (secure fields: `['data']`). `$request->data()` runs `protect()` (`)`, `=`, `%`, … become a different string). Login never matches and stored URLs break. [19](19-VALIDATION-AND-INPUT.md).
6. **`Auth::login` === `false`** (malformed) vs `['error']` 1–5 / 99. **MUST** map every branch to a visible message — silent 400 is a frontend/handler bug. [11](11-AUTH-AND-CRYPTO.md), [EX-14](examples/EX-14-auth-and-2fa.md).
7. **File/ZIP** — `FormData` + `load()` / `<fo-rm>` cannot carry CRC. **MUST** `$dotapp().uploadFile` + `$request->upload()` — **MUST NOT** `crcCheck()` on that endpoint. [09](09-DOTAPP-JS-AND-BRIDGE.md).
8. **JS / payload** — `$dotapp().form` / `load` posts fields under `data`. Unwrap it; do not read a flat `$request->data()['id']`. Handled product failure is HTTP 200 + `status: 0` + `message`; HTTP 400/500 enters onError `(status, bodyText)` and often becomes generic “Request failed”. Raw `fetch` / `$.ajax` fails `crcCheck()`.
9. **DDL / installer** — `$qb->raw()` treats **every** `?` as a placeholder, including `COMMENT 'SMS?'`. [06](06-DATABASE.md).

---

## 5. Symptom → look here

| User sees | Hunt |
|-----------|------|
| 400 / “Bad request” / empty toast on Save | `crcCheck()` twice; `crcCheck()` on GET; `form()` `null`/`false`; JS ignores `reply.message` |
| Login always wrong password | `$request->data()` instead of `data(true)`; installer hashed the protected password |
| Works once, second click fails | Token already burned (double submit without new token, or double `crcCheck`) |
| Upload “Request failed” | `crcCheck()` on upload endpoint, or file stuffed into `load()` `FormData` |
| Toggle/Delete says “Request failed” but PHP has a message | flat read instead of nested `data`; handled product failure returned as HTTP 400/500; hunt every sibling ([00](00-AGENT-CONTRACT.md) §2q) |
| CREATE TABLE never appears | `?` in `$qb->raw()` comments |
| Blank page / empty body | missing view → `""`; check Renderer fallback |
| Heading/flag renders but `foreach` is empty | Renderer sandbox dropped the whole bag because a variable name or nested value is callable (`time`, `copy`, `count`, `key`, `header`) |
| `formName` visible as text | tag **outside** `<fo-rm>…</fo-rm>` |

---

## 6. After you find it

Fix **in the current module** only. Remove the extra `crcCheck()`, or move CRC to the controller and leave middleware as Auth/rights. Do not add a “CRC cache” in core. Do not tell the user to patch `RequestObj.php`.
