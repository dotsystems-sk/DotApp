# 23 — Debug playbook (“it doesn’t work”)

This file is **extra hunt rules**, not a replacement for [08](08-FORMS-AND-SECURITY.md) / [19](19-VALIDATION-AND-INPUT.md). Use it when the user asks **why** a form, login, AJAX save, installer, or list request fails.

**After writing code** (before the user reports a bug): **MUST** run the finish gate — [00](00-AGENT-CONTRACT.md) §2c. This playbook is the **reactive** hunt. **MUST NOT** open a live browser to hunt or “prove” a POST unless the user asked ([00](00-AGENT-CONTRACT.md) §2r).

**MUST** search before inventing a core or DACore bug. **MUST NOT** patch `app/parts/`, `DotApp.php`, or `app/modules/DACore/` (unless the informed exception in [00](00-AGENT-CONTRACT.md) §1).

Framework hunts: sections 1–6. DACore extra hunts: section 7. Quirks: [36](36-DACORE-KNOWN-ISSUES.md).

---

## 1. Trigger

Read this file when the user says: doesn’t work, 400, Bad request, empty error, login fails, installer fails, “I clicked Save and nothing”, CRC / CSRF, middleware, admin POST fails.

Open **one** sample after the hunt: [EX-01](examples/EX-01-secure-form-complete.md) (forms), [EX-14](examples/EX-14-auth-and-2fa.md) (login).

---

## 1b. Read the catch trail first (**MUST** when the code already ships it)

Every `catch` and every `execute()` `$err` in this project reports to the catch bus ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9). Read that trail **before** guessing: it names the failing `operation`, the `source`, and the real `message`.

```php
// Temporary, in YOUR module's module.listeners.php — remove when done.
// Never add a debug file under app/modules/DACore/.
Events::on('dotapp.catch', function ($payload) {
    Logger::use('debug')->error($payload['operation'] ?? 'unknown', $payload);
});
```

| Signal in the payload | Read it as |
|-----------------------|------------|
| `severity => 'error'`, `source` = the action | The handler aborted — start at `file`/`line` |
| `severity => 'info'` repeated many times | A “recovered” path is firing constantly — usually the real bug |
| no event at all for a failing click | The failure is **before** your handler (CRC / `AuthTest` prefix / rights / route) → §2 |
| `operation` present but the user saw nothing | Visible outcome is missing ([00](00-AGENT-CONTRACT.md) §2d — admin = toast) — a second bug, fix both |

If the module has `catch` blocks that do **not** report, add the report helper first — that is the fix, not a detour. DACore’s own internals do not report to this bus and **MUST NOT** be patched to do so.

Log files need `Config::logger('core_log_enabled', true)` ([20](20-CACHE-LOGGER-SESSION.md) §2); the bus itself fires regardless.

---

## 1c. Event tracer — `dotapp.catchall` (when building a debugger, or hunting a missing event)

The core fires **`dotapp.catchall` on every `trigger()`** except itself ([01](01-ARCHITECTURE.md) Built-in events). That is the **one** listener a debug tool **MUST** use to see the whole event stream. Implement it in **your** module — **MUST NOT** add anything under `app/modules/DACore/`. It is **not** `dotapp.catch` (failures only — §1b).

```php
// Temporary, in YOUR module's module.listeners.php — remove or gate when done.
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
| A named business step (`module.shop.sms_sent.hook`) | That exact name from the owner’s `.hooks` ([41](41-MODULE-HOOKS.md)) |
| Log lines | `dotapp.log` ([20](20-CACHE-LOGGER-SESSION.md)) |

**MUST NOT:** `Events::trigger('dotapp.catchall', …)` (core already does it); trigger other events from this listener; persist every event without an opt-in flag; log `$result` wholesale (secrets); push a DACore inbox notification per event ([37](37-DACORE-NOTIFICATIONS.md)). Canonical: [12](12-SERVICES.md) §2.

**Hunting a missing business event:** open `app/modules/<Owner>/.hooks` (Fired section), then grep `Events::trigger(` in that module. If the name is not there, it was never fired — **MUST NOT** invent it. For a module that plugs into the admin shell, start with **`app/modules/DACore/.hooks`**. Ordinary saves may have **no** hook by design ([41](41-MODULE-HOOKS.md) §6). Catchall will only show names that were actually triggered.

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
| DACore route wrappers | `#DACore:AuthTest@check!` and your `#Shop:Rights@check!` — AuthTest is **not** a rights guard ([32](32-DACORE-RIGHTS.md), [36](36-DACORE-KNOWN-ISSUES.md) §1) |

**Two or more `crcCheck()` on one request = the first call burned the one-time token; the second returns `false`.** That is the usual “Bad request” after a generic CRC middleware.

Read the action’s first PHPDoc line **`CRCchecking —`**. If it already names a CRC prefix/middleware (`#DACore:AuthTest@CRC!` / `LoginAndCRC!` / Gate), the body **MUST NOT** call `crcCheck()`. A missing `CRCchecking —` line on a public controller/middleware method is a docs bug ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7).

Canonical: [08](08-FORMS-AND-SECURITY.md) “`crcCheck()` burns the token”.

---

## 3. If you write middleware

**MUST:** `crcCheck()` lives in **one** place.

| Middleware does | Controller / action |
|-----------------|---------------------|
| Prefix already called `crcCheck()` (`#DACore:AuthTest@CRC!` / `LoginAndCRC!`, or `check` on `POST /dacore/*`) | **MUST NOT** call `crcCheck()` again — only `form()` / persist |
| HTML `Gate@login` only (no CRC) | **MUST** `crcCheck()` **once** in the action if that POST has no API prefix |
| No prefix CRC on that POST | **MUST** `crcCheck()` **once** in the action |

**MUST NOT** CRC-`before` on GET or on `*`. The intended catch-all is **POST** `/api/v1/auth|noauth/{Module}/*` ([03](03-MODULES-AND-ROUTING.md), [32](32-DACORE-RIGHTS.md)). HTML `Gate@login` stays CRC-free.

`$request->form()` does **not** run `crcCheck()`.

**MUST NOT** use `#DACore:AuthTest@check!` as a permission guard — it **ignores** the rights you pass. Copy your own `Rights` middleware ([32](32-DACORE-RIGHTS.md)).

---

## 4. Hunt order (POST / AJAX)

Work top-down. Stop when you find a match.

1. **`crcCheck()` count** (section 2). Middleware + controller is guilty until proven otherwise.
2. **`formName` placement** — must sit **between** `<fo-rm>` and `</fo-rm>`. Outside the pair the tag is left unchanged (silent fail).
3. **`/assets/dotapp/dotapp.js`** loaded **before** module JS (session keys). Missing → CRC/CSRF fields wrong.
4. **`$request->form(...)`** — missing error callback throws; method mismatch → `false`; wrong handler name → `null`. Guard all three and **show** `reply.message`.
5. **Original vs protected input** — persist / passwords / HTML / URLs / hashes **MUST** be `$request->data(true)` (secure fields: `['data']`). `$request->data()` runs `protect()` (`)`, `=`, `%`, … become a different string — `?q=` → `?&#61;`). SQL protection is named / `?` bindings, not `protect()`. Login/installer then “never matches”; settings Save stores a broken href. [19](19-VALIDATION-AND-INPUT.md), [06](06-DATABASE.md).
6. **`Auth::login` === `false`** (malformed) vs `['error']` 1–5 / 99. **MUST** map every branch to a visible message — silent 400 is a frontend/handler bug. [11](11-AUTH-AND-CRYPTO.md), [EX-14](examples/EX-14-auth-and-2fa.md).
7. **File/ZIP** — `FormData` + `load()` / `<fo-rm>` cannot carry CRC. **MUST** `$dotapp().uploadFile` + `$request->upload()` — **MUST NOT** `crcCheck()` on that endpoint. [09](09-DOTAPP-JS-AND-BRIDGE.md).
8. **JS** — `$dotapp().form` / `load` + `parseReply`. Raw `fetch` / `$.ajax` fails `crcCheck()`. `.after()` **MUST** show `reply.message` on `status != 1` and on HTTP 400. Admin: Notiflix toast OK. Public site: your module UI — Notiflix is DACore-only.
9. **DDL / installer** — `$qb->raw()` treats **every** `?` as a placeholder, including `COMMENT 'SMS?'`. [06](06-DATABASE.md).

---

## 5. Symptom → look here

| User sees | Hunt |
|-----------|------|
| 400 / “Bad request” / empty toast on Save | `crcCheck()` twice; `crcCheck()` on GET; `form()` `null`/`false`; JS ignores `reply.message` |
| Toggle / delete / test / purge → “Request failed” | `load()` nests fields under `data`; PHP read `$request->data()['id']`; HTTP 400/500 hid `reply.message`. Unwrap + HTTP 200. Then **grep this module** for every sibling ([00](00-AGENT-CONTRACT.md) §2q, [09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Login always wrong password | `$request->data()` instead of `data(true)`; installer hashed the protected password |
| Works once, second click fails | Token already burned (double submit without new token, or double `crcCheck`) |
| Upload “Request failed” | `crcCheck()` on upload endpoint, or file stuffed into `load()` `FormData` |
| CREATE TABLE never appears | `?` in `$qb->raw()` comments |
| Blank page / empty body | missing view → `""`; check Renderer fallback |
| Card/title shows, `foreach` empty / one var missing | Renderer sandbox **dropped** the bag: a string value or var name is `is_callable` (`time`, `copy`, `count`, `key`, `header`, …). Prefix keys or pass escaped HTML. **MUST NOT** patch Renderer ([05](05-VIEWS-TEMPLATES-ASSETS.md) §5) |
| `formName` visible as text | tag **outside** `<fo-rm>…</fo-rm>` |
| Admin 403 / rights ignored | `#DACore:AuthTest@check!` used as the guard ([36](36-DACORE-KNOWN-ISSUES.md) §1) |
| “Fix it in DACore” | Implement in **this** module. Do not patch DACore ([00](00-AGENT-CONTRACT.md) §1) |
| Apache **Not Found** on `/admin/…/{long-token}` | Path token is raw `Crypto::encrypt` (`%2F` = slash). Seal to `[A-Za-z0-9_-]+` in **this** module ([11](11-AUTH-AND-CRYPTO.md) §8, [36](36-DACORE-KNOWN-ISSUES.md) §16) |

---

## 6. After you find it

Fix **in the current module** only. Remove the extra `crcCheck()`, or move CRC to the controller and leave middleware as Auth/rights. Do not add a “CRC cache” in core. Do not tell the user to patch `RequestObj.php` or DACore.

---

## 7. DACore extra hunts

Do these **after** the framework list when the failing URL is under the admin shell.

1. **Rights middleware** — grep `AuthTest`. If the route uses `#DACore:AuthTest@check!` with a rights array, those rights are **discarded**. Switch to `#Shop:Rights@check!`.
2. **Menu / blank admin body / no active leaf** — `withMenu` `$menuId`, `type => 0` header, do not register Return back. Edit/detail with nothing highlighted: missing 7th `$currentFile` (registered list URL) when the path is not under that leaf (`/users/4` vs `/users-list`). [31](31-DACORE-MENU.md), [33](33-DACORE-PAGES-AND-UI.md).
3. **Installer** — live `install.php`; after a new version rename `installed_*` → `install.php`. Admin password from the wizard: `$request->data(true)`. [35](35-DACORE-INSTALL.md).
4. **2FA** — operators keep 2FA on; dangerous actions need step-up. Boxes: `$dotapp().twoFactor`. [32](32-DACORE-RIGHTS.md) §6.
5. **Did someone edit `app/modules/DACore/`?** Those changes vanish on update and are the wrong fix. Revert the idea; implement in the current module.
6. **Pager click does nothing / CRC on page 2** — `$dotapp().live` first arg is the **element** (`function (el, e)`), not `e.currentTarget`. **MUST NOT** `history.replaceState` / `?page=` (Referer CRC). Total “1–10 of 10”: COUNT via `all()`, not `paginate()['total']`. [40](40-DACORE-LIST-PAGER.md).
7. **Apache Not Found on an edit URL with a long token** — `%2F` in the path. Seal Crypto tokens in **this** module. [36](36-DACORE-KNOWN-ISSUES.md) §16, [33](33-DACORE-PAGES-AND-UI.md) §13.

Full DACore trap list: [36](36-DACORE-KNOWN-ISSUES.md).
