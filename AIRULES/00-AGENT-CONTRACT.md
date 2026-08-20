# 00 — Agent Contract (HARD LAWS)

**Read this file before every DotApp task.**  
**AIRULES is the single source of truth.** It supersedes leftover `.cursorrules` / `*_AI_guide.md` / `database_guide.md` from older installs.

This is **DotApp** — not Laravel, Symfony, CodeIgniter, Blade, Twig, Eloquent, or jQuery.

---

## 1. Edit boundaries (three tiers)

### ALLOWED (edit freely when asked)

| Path | Notes |
|------|--------|
| `app/config.php` | **Only** framework file agents may edit. Secrets, DB, drivers, module overrides. |
| `app/modules/<YourModule>/` | Everything inside the module you were asked to create or change. |

### ASK FIRST (do not touch unless the user explicitly requests)

| Path | Preferred alternative |
|------|------------------------|
| `app/listeners.php` | Prefer `module.listeners.php` inside your module. |
| `.htaccess` | Prefer `php dotapper.php --create-htaccess`. |
| Another module's folder | Only with explicit permission naming that module. |

### FORBIDDEN (never edit — no exceptions, no “quick fixes”, no “authorized labs”)

| Path | Why |
|------|-----|
| `app/parts/**` | Framework core libraries |
| `app/DotApp.php` | Framework kernel |
| `app/vendor/**` | Composer dependencies |
| `dotapper.php` | CLI tool binary |
| `index.php` | Front controller |
| `initializedb.php` | Core DB bootstrap |
| `app/runtime/**` | Generated cache/logs/sessions |
| `assets/dotapp/**` (if present as static copies) | Served dynamically; do not hand-patch |
| Any file outside the target module + `app/config.php` | Scope violation |

If you believe a core bug exists: **stop and ask the user**. Do not patch core.

---

## 2. Mandatory workflow

1. **Identify the target module** (or create one).
2. **Read** the relevant AIRULES docs for the task (routing / views / DB / forms / JS).
3. **Generate** with `dotapper.php` whenever possible (module, controller, model, middleware).
4. **Implement** only inside the allowed paths.
5. **Tables:** every table your module owns **MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names or `dotapp_*` for module data. See [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md) §3.
6. **Migrations:** after you add a version in `Installation.php`, **MUST** rename `installed_*_install.php` back to `install.php` so the next page load runs it. Do not leave this for the user. [07](07-SCHEMA-AND-INSTALL.md).
7. **Lists:** any screen that lists records that **can accumulate** (users, logs, items, orders, messages, files, events) **MUST** ship `paginate()` **and** an **interactive AJAX pager** in the **first** version. Empty table today is not an excuse. A pager that reloads the page is not a pager. **Search / list UX:** when **planning**, **ASK** (search, filters, sort, bulk, page size, remember in DSM, CSV only if it fits). Lookup lists **MUST** ship AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. See [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
8. **Finish gate (LAW):** after **every** code chunk **and** before claiming done — **MUST** [§2c](#2c-finish-gate-must--law). **MUST NOT** skip. Tick [17-CHECKLISTS.md](17-CHECKLISTS.md) Finish gate.
9. **Cursor credits:** when **planning** a programming task, **ASK** whether more expensive models may be used. Subagents **MUST inherit** the chat model. See [§2b](#2b-cursor-credits--subagents-must).
10. **Debug / “why doesn’t this work”:** **MUST** follow [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) — grep middleware + count `crcCheck()` **before** guessing a core bug.

### Dotapper-first rule

Never hand-create a module skeleton, controller, model, or middleware class that `dotapper.php` can generate. See [02-DOTAPPER-CLI.md](02-DOTAPPER-CLI.md).

```powershell
Set-Location "path\to\project-root"   # must contain index.php + dotapper.php
php .\dotapper.php --create-module=MyModule
php .\dotapper.php --module=MyModule --create-controller=Home
php .\dotapper.php --module=MyModule --create-middleware=Gate
php .\dotapper.php --module=MyModule --create-model=Item
```

`--module=` **must appear before** the create-* flag on the same command line.

### 2b. Cursor credits / subagents (**MUST**)

Users often pick a **cheap** chat model on purpose (Grok 4.6 and similar). AIRULES is written so that model can ship a correct app. Spawning a **premium** subagent (Opus, GPT-5.x, “thinking” / “high” / “xhigh”, cloud agent, best-of-N) **burns a different credit pool**. Doing that unasked is a **bug**.

**When planning programming, ASK in chat** (do not guess, do not skip):

> Stay on this model only, or also use more expensive models for subagents?

If they do not say **yes**: **no expensive models**. Keep working as the parent.

| Work | Model |
|------|--------|
| Write / edit PHP, views, JS, CSS, SQL — programming | **Parent only** (`inherit`). Do **not** “upgrade” to a bigger coder. |
| Hunt a pile of files / broad explore / grep-like | **Composer 2.5** (fast) is OK. |
| A capability the parent **cannot** do (e.g. **generate an image**) | That **specific** tool/model. **ASK** first if it costs extra. Do **not** spawn a premium **coder** for it. |

**MUST:**
- Code-writing / planning subagents: **`inherit`** the parent. Omit premium `model` slugs.
- **MUST NOT** use Composer 2.5 as the programmer that implements the module (too weak for DotApp). File-hunt only.
- **MUST NOT** launch parallel expensive runners, best-of-N, or cloud agents unless the user said yes **in this plan**.
- A silent upgrade “to be safe” is forbidden.

### 2c. Finish gate (MUST — law)

This is a **law**, not a reminder. Skipping it is a **bug**.

**When:** after **every** code chunk you write (route, middleware, controller method, query, form, view, JS handler) **and** again before you tell the user the work is done.

**MUST NOT:**
- Say it works / is ready / is finished until this gate **passed** on the files you just wrote
- Start the next feature with a known miss
- “Looks fine”, “I’ll check later”, or tick boxes from memory

**MUST:** actually **grep and read** this module — `Middleware/`, `module.init.php` (`Router::before`, `Middleware::`), Controllers, views, JS — **plus the diff**. Then tick [17-CHECKLISTS.md](17-CHECKLISTS.md) **Finish gate**.

| Check | How | Fail = stop and fix **now** |
|-------|-----|-----------------------------|
| **CRC once** | Count `crcCheck(` on **this POST’s** pipeline (middleware + `before` + action) | Two calls (first **burns** the token); CRC on GET/HTML login `before`; CRC on `$request->upload()` |
| **IDs encrypted** | Views / JS / JSON: `value=`, `data-*`, hidden, payload | Plain `7` / `{{ var: $id }}` as an id sent to the browser. **MUST** `{{ enc(Shop.item.id): $id }}` with a unique `$key2`. Decrypt `=== false` → reject. Still `Auth::can` / ownership in PHP |
| **Queries bound** | Every SQL in the chunk | User input concatenated into SQL; `$qb->raw()` `?` that is not a binding (comments / `COMMENT` count); mix `?` and `:named` |
| **Inputs** | Request + persist | Password / HTML / hash from `$request->data()` not `data(true)`; missing `form()` / `Validator` where required; persist with only an FE overlay — PHP **MUST** still refuse |
| **Middleware / route conflicts** | `module.init.php` + Middleware vs the action | Login `before` missing **or** handlers outside `Auth::isLogged()`; CRC layer **and** action `crcCheck()`; CRC on a GET gate; two overlapping `before` hooks that both CRC |
| **Visible outcome** | Save / toggle / delete / form JS + `ajaxReply` | Silent `.after()` / no `message`; success with no toast/status; field errors without marking the input ([§2d](#2d-visible-outcome-must--law)) |
| **Catch reported** | `rg -n "catch \(|catch\(" ` + every `execute(` in the chunk | A `catch` (or `execute()` `$err`) that does **not** call the module’s report helper → `dotapp.catch` + `dotapp.catch.error|info`; ad-hoc payload keys; a secret/token/request body inside the payload ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9) |
| **Privilege / records** | Persist + GET view vars + SQL | Secret (TOTP/QR/key) in a read-only view; mutate a more privileged target; `WHERE id` only after decrypt; own password without current; public noauth shipped with no bot warning ([11](11-AUTH-AND-CRYPTO.md) §11) |
| **Threat pass** | The 12 greps in [24](24-ATTACK-VECTORS.md) §11 on this chunk | Injection (SQL/XSS/command/deserialize), input in a header or redirect, unbounded public POST, `getMessage()` / `var_dump` in the reply, upload without ext+MIME+header check, weak randomness for a token |
| **Perf / readability pass** | The greps in [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §8 | `->all()` on a growing table, a query/HTTP/log **inside** `foreach`, `select('*')` on a list, O(n²) lookup or array copy per row, a new `WHERE`/`ORDER BY` column with **no index**, an index with no comment naming its query, a public method with no docblock, comments that restate the code |
| **Rest of AIRULES** | Touched files vs [§4](#4-no-foreign-framework-patterns) / [§5](#5-security-non-negotiables) / [17](17-CHECKLISTS.md) | Lists without AJAX pager, `$_SESSION`, Blade, `$.ajax`, `formName` outside `<fo-rm>`, … |

**Pass →** continue or say done. **Fail →** fix **now**. Do not start the next chunk.

User reports a failure **after** ship: [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) (same CRC / middleware hunt).

### 2d. Visible outcome (MUST — law)

The user **MUST** always see what happened. Silent save and silent fail are **bugs**.

**Every** stay-on-page action (save, toggle, delete, upload, login, form): PHP returns `status` + `message` (and `errors` when the problem is a **field**). JS **MUST** show it. Empty `.after()` is forbidden.

**This folder has no DACore shell.** Notiflix does **not** exist. **You MUST build** the feedback in **your** module (`app/modules/<YourModule>/assets/`).

| Kind | Channel (**MUST**) |
|------|-------------------|
| Field validation (wrong email, empty name, …) | **Preferred (FE + BE):** mark the **wrong input** (invalid/red class) **and** put the message **on that field** so the user sees **where** and **what**. PHP **MUST** return `errors` keyed by field (`Validator` shape). FE highlight without PHP re-check is UX only — persist still fails in PHP ([§5](#5-security-non-negotiables)). |
| Success (“Saved”, “Enabled”) | **Your** module toast / status node. Never `alert()`. |
| Non-field failure (CRC, rights, server) | Same module channel + `reply.message`. |

**MUST NOT:** only a generic “Validation failed” toast when the error belongs to a named field; skip success feedback; invent `alert()` / `window.confirm()`.

Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 “Visible outcome”, [19](19-VALIDATION-AND-INPUT.md), [EX-09](examples/EX-09-validation-and-errors.md).

---

## 3. No-invention rule

If an API is not documented in AIRULES:

1. Open the real source under `app/parts/<Class>.php` (read-only).
2. Quote the actual method signature.
3. Use only what exists.

**Do not** invent methods because they exist in Laravel/Eloquent/jQuery.

## 3b. Never ignore a return value

DotApp uses **four different failure styles**. Getting this wrong silently breaks code:

| Style | Examples |
|-------|----------|
| Callback pair `($ok, $err)` | `execute()`, `Entity::save()` — **omitting `$err` makes it throw** |
| `false` / `null` | `Crypto::decrypt` → `false`, `Cache::load` → `null`, `Auth::login` → `false` on bad input |
| Envelope array | `HttpHelper::request`, `FastSearch::*` → check `['success']` |
| Exceptions | AI, SchemaBuilder, QueryBuilder build errors, `Auth::createUser` |

Also: `first()` is unsafe on an empty result, a missing view renders `""`, and `Email::send()` returns an **array of error strings**.

**Mandatory reading: [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md).**

---

## 4. No foreign-framework patterns

| Forbidden guess | DotApp reality |
|-----------------|----------------|
| `DB::table('x')`, Eloquent models | `DB::module("RAW")->q(fn($qb)=>...)->all()` |
| Blade `{{ $x }}`, `@if`, `@extends` | `{{ var: $x }}`, `{{ if }}` … `{{ /if }}` |
| `Route::prefix()->group()`, named routes | Imperative `Router::get(...)` in `module.init.php` |
| Register all login-required URLs then login-middleware only | Prefix + `Router::before([$area, $area.'/*'], login 403)` **and** `if (Auth::isLogged())` — page **MUST NEVER** show ([03](03-MODULES-AND-ROUTING.md)) |
| Instance controllers + `$this->` | `public static function` controllers |
| `$`, `jQuery`, `$.ajax` | `$dotapp`, `$dotapp().load(...)` |
| `<form>` + manual CSRF only | Prefer `<fo-rm>` + `{{ formName(handler) }}` |
| `{{ formName }}` after `</fo-rm>` | **MUST** between `<fo-rm>` and `</fo-rm>` |
| Plain IDs in HTML/JSON (`value="7"`, `data-id="7"`) | **MUST** `{{ enc(Shop.item.id): $id }}` — unique `$key2` per field |
| `<fo-rm>` around every row button / D&D | `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)) |
| List/form still clickable during `load()` | Cover the region with **your module preloaders** until done — desktop **and** mobile ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Desktop-only public header / hover menu / no mobile drawer | Overlay drawer L/R; lock page scroll while open; drawer itself scrolls ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Logs / users / items dumped with `->all()`, no pager, or “few rows now so skip” | **MUST** `paginate()` + interactive AJAX on **first ship** ([06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Load the whole table / N+1 in `foreach` / `select('*')` for a list | Smallest I/O: `exists()` / `COUNT(*)` / needed columns / one `join` ([06](06-DATABASE.md)) |
| Lookup list with no search / JS-filter of `->all()` | **ASK** in the plan; articles/catalog **MUST** AJAX search unless declined ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `$request->data()` for password / HTML / `Auth::login` | **MUST** `$request->data(true)` — `protect()` rewrites `)`, `=`, `%` ([19](19-VALIDATION-AND-INPUT.md)) |
| TOTP/QR in a read-only view; edit a more privileged user; `WHERE id` only | [11](11-AUTH-AND-CRYPTO.md) §11 — mutate right + SQL owner; **warn** if public noauth is bot-bait |
| Custom OTP digit widget / jQuery 2FA plugin | **MUST** `$dotapp().twoFactor` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `alert()` / `window.confirm()` to delete | Graphical dialog first, then `load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Prompt-echo UI copy (“this user can hide the icon…”) | Product copy a software company would ship ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| `f-form` attribute | **Does not exist** — use `<fo-rm>` |
| `$_SESSION` / `session_start()` | **MUST** `DSM::use('Shop')` ([20](20-CACHE-LOGGER-SESSION.md)) |
| JS overlay / modal as the only save or 2FA gate | **MUST** re-check in PHP; FE is UX only ([08](08-FORMS-AND-SECURITY.md)) |
| File/ZIP in `FormData` + `load()` / `<fo-rm>` | **MUST** `$dotapp().uploadFile` + `$request->upload()`; PHP rejects `.php` ([09](09-DOTAPP-JS-AND-BRIDGE.md)) |
| `crcCheck()` in middleware **and** in the action | **MUST** once — first call **burns** the token ([08](08-FORMS-AND-SECURITY.md)) |
| Shipping a chunk / claiming done without the finish gate | **MUST** [§2c](#2c-finish-gate-must--law) after every chunk — grep, do not imagine |
| Silent save / empty `.after()` / no field error on a named field | **MUST** [§2d](#2d-visible-outcome-must--law) — user sees success and fail |
| Premium Cursor subagent (Opus / GPT-5 / xhigh) without asking | **MUST inherit** the chat model; **ASK** in the plan ([00](00-AGENT-CONTRACT.md) §2b) |

Full table: [14-ANTIPATTERNS.md](14-ANTIPATTERNS.md).

---

## 5. Security non-negotiables

1. **Preferred form stack (default for all interactive forms):**
   - Markup: `<fo-rm>` + `{{ formName(handler) }}` (not `f-form`, not Laravel `_token` alone)
   - **MUST:** `{{ formName(handler) }}` sits **between** `<fo-rm …>` and `</fo-rm>` — never before `<fo-rm>`, never after `</fo-rm>` (outside the pair the tag is left unchanged: silent failure)
   - Script: **`/assets/dotapp/dotapp.js` first** (injects random per-session keys — without it secure forms fail)
   - JS: `$dotapp().form(...).before().after()` + `parseReply` + **MUST** block while in flight (**your module preloaders** — desktop **and** mobile)
   - **MUST:** after success, patch the DOM (`reply.html` / data) and a short toast. `<fo-rm>` does **not** reload. No `location.reload()`. `redirectTo` only when leaving the page ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
   - PHP: `$request->crcCheck()` **once** then `$request->form([...], "handler", ...)` then `ajaxReply`
   - Full sample: [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md)
2. This stack is **stronger than plain CSRF** (binds handler + action + method, CRC, one-time tokens, JS key material). Use it **only for real HTML forms** (several fields + submit). **MUST NOT** wrap row actions (toggle, delete, reorder, drag-and-drop, paginate) in `<fo-rm>` — those are `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)).
3. Never skip CRC/CSRF for endpoints that receive `$dotapp().load()` / secure forms. **MUST** `crcCheck()` **once** per request — API prefix **or** action, **never both** (first call **burns** the token). Canonical: [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md).
4. **MUST encrypt every identifier sent to the browser** (`<option value>`, `data-*`, hidden, JSON). Use `{{ enc(Shop.user.id): $id }}` / `Crypto::encrypt($id, 'Shop.user.id')` with a **different `$key2` per field**. Never `value="7"` / `data-id="7"`. Decrypt with the **same** `$key2`; `false` → reject. **MUST still** `Auth::can()` / ownership — encryption is not a substitute for rights ([11](11-AUTH-AND-CRYPTO.md) §8).
5. Never interpolate user input into SQL — use QueryBuilder bindings or `raw($sql, $bindings)`. **MUST NOT** put `?` in `$qb->raw()` unless it is a real binding — comments and `COMMENT 'SMS?'` count too ([06](06-DATABASE.md)).
6. On new apps, generate real `app.c_enc_key` / `rm_key` / `rmrcm_key` (see [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md)).
7. Module settings must have **fallbacks** if the user did not fill `app/config.php`.
8. **MUST paginate accumulating lists** (users, logs, items, …) with an **interactive** pager (`$dotapp().load()`). Shipping the list with no pager, or changing pages by reloading the document, is incomplete. Lookup lists **MUST** ship **interactive AJAX search** unless the user declined; other lists: **ASK** in the plan. [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
9. **MUST** store app session state with **`DSM::use('Shop')`**. **MUST NOT** `$_SESSION` or `session_start()` ([20](20-CACHE-LOGGER-SESSION.md)).
10. **MUST** re-check every persist in **PHP** (`Auth::can`, 2FA code, ownership, validation). `crcCheck()` is transport — **once** per request, not again. Frontend modal/overlay/disabled control is **UX only**. Removing the overlay **MUST** still fail on the server ([08](08-FORMS-AND-SECURITY.md)).
11. **MUST** upload files with **`$dotapp().uploadFile`**. **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()` on that endpoint. **MUST** reject `.php` and other executables (extension + `finfo` MIME + headers); FE `accept=` is UX only ([09](09-DOTAPP-JS-AND-BRIDGE.md)).
12. **MUST** take passwords, HTML, and other round-trip values from `$request->data(true)` / `$request->query(true)` (original). `$request->data()` is the **protected** copy (`protect()`). Login/createUser/installer **MUST NOT** hash the protected string. **MUST** show every login failure (`crcCheck`, `form()` `null`/`false`, `Auth::login === false`). Canonical: [19](19-VALIDATION-AND-INPUT.md).
13. **Login-required / admin routes (MUST):** prefix `/{ModuleName}/…` (or a subtree). Cover HTML with `Router::before([$area, $area . '/*'], '#Shop:Gate@login!')` returning `Response` 403. **POST API:** `/api/v1/auth|noauth/{Module}/…` + `Gate@loginAndCrc` / `Gate@crc` at the **start** of `initialize()`; handlers **MUST NOT** `crcCheck()` again. Register login-only handlers **only** inside `if (Auth::isLogged() === true)`. Those pages **MUST NEVER** render for anonymous users. Canonical: [03](03-MODULES-AND-ROUTING.md).
14. **Documentation (MUST):** English. Every file/class gets a docblock; every public/static method gets a docblock (purpose, `@param`, `@return`, `@throws`); every **logical step** in the body gets a short **why** line (guard, decision, formula, magic value, query shape, trap). **MUST NOT** restate the code (`// increment i`), prompt-echo, or leave dead code / bare `TODO`. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7, [03](03-MODULES-AND-ROUTING.md).
15. **Errors (MUST):** persist handlers in `try/catch` (`\Throwable`) — log, structured `ajaxReply`, **never** leak `$e->getMessage()`, **never** empty `catch`. `execute()` **MUST** get **both** callbacks (`$ok` and `$err`); omitting `$err` **throws**. **Every `catch` and every `execute()` `$err` MUST also report to the catch bus:** `Events::trigger('dotapp.catch', $payload)` then `dotapp.catch.error` (aborted) or `dotapp.catch.info` (recovered/expected), with the fixed payload (`severity, module, source, operation, message, exception, code, file, line, time` + `context` ids/counts, `user_id`) — no secrets, tokens or request bodies in it. Route it through **one** report helper per module so a future debugger listener cannot break the reply. Canonical: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.
16. **Cheap I/O (MUST):** pick the smallest load — `exists()` / `COUNT(*)` / `limit(1)` / `select` only used columns / `paginate()` / one `join`. **MUST NOT** `->all()` then filter, N+1 in `foreach`, or `Config::db('cache')` “for speed”. Canonical: [06](06-DATABASE.md), [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §2.
17. **Visible outcome (MUST):** the user always sees success and failure. Public: **preferred** mark the wrong field (red + message on the input) — PHP returns `errors`. You **build** your own toast/status. Canonical: [§2d](#2d-visible-outcome-must--law).
18. **Privilege and records (MUST):** no secret in a read-only view; no grant/mutate above the actor; SQL scoped to owner; own password needs current password; live routes; lockout covers 2FA if you built lockout. Public noauth that bots can hammer: **MUST warn** in chat (CAPTCHA optional — not MUST). Canonical: [11](11-AUTH-AND-CRYPTO.md) §11.
19. **Known attack vectors (MUST):** the catalogue in [24-ATTACK-VECTORS.md](24-ATTACK-VECTORS.md) is **law** — injection (SQL, XSS, command, template, deserialization), channels (headers, redirect, mail, SSRF, mass assignment), identity (CSRF, fixation, brute force, enumeration), access control (IDOR, escalation, tampered fields), browser headers, files/paths, abuse/rate limit, leaks, crypto, third-party/AI. **MUST NOT** ship a chunk that enables one. Open only the sections for the surface you touch, then run the **threat pass** ([24](24-ATTACK-VECTORS.md) §11) on the diff. A vector not listed there is still forbidden — apply the nearest row and **say it in chat**.
20. **Performance, schema and readability (MUST):** [25-PERFORMANCE-AND-CODE-QUALITY.md](25-PERFORMANCE-AND-CODE-QUALITY.md) is **law** — smallest I/O, bounded memory (page big sets, no O(n²), no full-array copies), **indexes designed for the queries you actually wrote** (FK + every `WHERE`/`JOIN`/`ORDER BY` column; composite order equality → range → sort; leftmost prefix; no duplicate prefix indexes), sane column types, cheap frontend, and the documentation standard (§7: file + method docblocks, a **why** line per logical step). Run the perf pass ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §8) with the finish gate.

---

## 6. Identity reminder (paste into new PHP files)

```php
<?php
/**
 * DOTAPP MODULE FILE
 * - Controllers: Module:Controller@method!  (! = no DI params)
 * - Database: DB::module("RAW")->q(...)->all()|first()|execute() — execute MUST both callbacks; persist try/catch; raw() every ? is a placeholder (not in comments)
 * - Tables: {lowercase_modulename}_*  (Shop → shop_items) — NEVER items or dotapp_*
 * - Templates: {{ var: $x }}  — NOT {{ $x }}. VIEW = outer file; setLayout+renderView fills {{ content }} in that view (or renderLayout / str_replace)
 * - Forms: <fo-rm> only for real multi-field submit; row actions = load() + data-*; crcCheck() once (API prefix XOR action)
 * - FE ids: {{ enc(Shop.item.id): $id }} unique $key2 per field; Auth::can still required
 * - Privilege: no secret in read-only views; no escalate; SQL owner scope; own password needs current; warn user if public noauth is bot-bait (11 §11)
 * - Attacks (24): escape before echo ({{ var: }} does NOT escape); whitelist sort columns + insert columns; no input in header()/redirect/HttpHelper URL; no eval/exec/unserialize on input; random_bytes for tokens; hash_equals for secrets; throttle public POST
 * - JS: $dotapp — NOT jQuery $; after save/toggle MUST patch DOM + toast (no reload); MUST module preloaders until request ends (desktop+mobile)
 * - Public site nav: mobile drawer slides L/R over the page; lock document scroll while open; drawer list scrolls; contacts+compact search in the drawer unless large search is its own mobile section
 * - Lists: accumulating records (users/logs/items) MUST paginate() on first ship + AJAX pager — NOT all() dump, NOT ?page= / location.reload()
 * - Cheap I/O: exists/COUNT/limit(1)/needed columns/paginate/one join — NOT all() then filter, NOT N+1
 * - Memory: page big sets, keyed map instead of in_array in a loop, unset the raw copy, stream files — NOT load-all-then-filter
 * - Indexes (25 §3): FK + every WHERE/JOIN/ORDER BY column; composite = equality → range → sort; leftmost prefix; one comment line per index naming its query
 * - Docs (25 §7): file + class + method docblock (@param/@return/@throws) and a short WHY line above every logical step — NOT narration of the code
 * - Catch bus (18 §9): every catch + every execute() $err → one report helper → Events::trigger('dotapp.catch', $p) then 'dotapp.catch.error'|'.info'; payload = severity, module, source, operation, message, exception, code, file, line, time, context (ids/counts), user_id — NO secrets/tokens/bodies
 * - After a new Installation.php version: rename installed_*_install.php → install.php (agent does it)
 * - Search: ASK in the plan; lookup lists (articles/products) MUST AJAX search unless declined — debounce, 3+ chars, SQL+paginate, NOT JS filter
 * - 2FA boxes: $dotapp().twoFactor — do not invent OTP widgets
 * - Deletes: graphical confirm first — never alert()/confirm()
 * - UI copy: product language — never prompt-echo / “this user can…”
 * - Session: DSM::use('Shop') — NEVER $_SESSION / session_start()
 * - Save checks: PHP MUST re-verify — FE modal/overlay is UX only
 * - Files: $dotapp().uploadFile — NEVER FormData + load()/fo-rm; PHP MUST reject .php (ext+MIME+headers)
 * - Request: data() = protected; data(true) = original — MUST true for passwords/HTML/hashes
 * - Login-required routes: prefix /{Module}/… + Gate@login 403 Response; MUST register handlers inside Auth::isLogged()
 * - Comments: English, short, why at traps — not every line
 * - Cursor: inherit parent model for subagents; ASK before expensive models; Composer 2.5 = file hunt only, not the coder
 * - Finish gate (LAW): after every chunk grep crcCheck once, enc ids, bound SQL, data(true), middleware vs action — 00 §2c
 * - Visible outcome (LAW): user always sees save/fail; public = mark the wrong field; build your own toast — 00 §2d
 * - Edit only this module + app/config.php. Never edit app/parts/.
 * See AIRULES/00-AGENT-CONTRACT.md
 */
```

---

## 7. DACore note

DACore is an **optional admin module**, not part of the framework core.  
Part 1 (this folder) must remain usable **without** DACore.  
Do not call `DACore:*` APIs unless the user explicitly requested DACore integration (Part 2).  
**Notiflix is DACore-only.** It is not available here. Public sites **MUST** ship module preloaders ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3).  
Operator 2FA lock and step-up on dangerous admin actions are **DACore-only** (Part 2). Do not invent that flow in a framework-only app.

---

## 8. Conflict resolution

| Conflict | Winner |
|----------|--------|
| Leftover `.cursorrules` / `*_AI_guide.md` vs AIRULES | **AIRULES** |
| Leftover `database_guide.md` invented APIs | **Ignore** — follow [06-DATABASE.md](06-DATABASE.md) |
| User explicit instruction to edit core | Ask once to confirm; still prefer not to |

---

## 9. Minimum reading map by task

| Task | Theory | Example (open one) |
|------|--------|--------------------|
| **Anything (always)** | **18** error handling / return values — incl. **§9 catch bus** (`dotapp.catch`) | — |
| Plan / Cursor credits | **00 §2b** — ASK before expensive subagents; inherit parent; Composer 2.5 = file hunt only | — |
| **After every code chunk** | **00 §2c** finish gate — CRC once, enc IDs, bound SQL, inputs, middleware conflicts | [17](17-CHECKLISTS.md) Finish gate |
| Stay-on-page save / errors | **00 §2d** visible outcome — mark the wrong field; your own toast/status | [EX-09](examples/EX-09-validation-and-errors.md), [EX-06](examples/EX-06-dotapp-js-boot.md) |
| New module | 00, 02, 03 | [EX-03](examples/EX-03-module-scaffold.md) |
| Route / middleware | 03, 04 | EX-03 — prefix `Gate@login` 403 + handlers inside `Auth::isLogged()` |
| Template / CSS / JS page | 05 (incl. §8 product copy), **09 §3** public mobile nav | [EX-05](examples/EX-05-renderer-page.md), [EX-06](examples/EX-06-dotapp-js-boot.md) |
| Public website header / nav | **09 §3** “Public website mobile navigation” — drawer overlay, lock page scroll | [EX-05](examples/EX-05-renderer-page.md) |
| Stay-on-page save / toggle (live DOM) | **09 §3** (block-while-in-flight, desktop+mobile), **08** | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Paginated list (users, logs, items) | **06**, **09 §3** “Paginate accumulating lists” — **MUST** ship, **MUST** be AJAX | [EX-04](examples/EX-04-database-crud.md), **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| List search (articles, catalog, …) | **09 §3** “Interactive AJAX search” — **ASK** in the plan; lookup lists **MUST** unless declined | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| List UX (filters, sort, empty, bulk, …) | **09 §3** “List UX” — **ASK** / **MUST** table | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Delete (confirm dialog) | **09 §3** “Confirm before delete” | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Custom `$dotapp` library / jQuery port | **09 §4** (esp. §4.C) | **[EX-15](examples/EX-15-dotapp-js-library.md)** |
| Database query | 06, 18 | [EX-04](examples/EX-04-database-crud.md) — `raw()`: every `?` is a placeholder, including comments |
| Tables / migrations | 07 (rename `installed_*` → `install.php` after a new version) | [EX-13](examples/EX-13-schema-migrations.md) |
| **Secure form (HTML fields + submit)** | **08, 09** | **[EX-01](examples/EX-01-secure-form-complete.md)**, [EX-02](examples/EX-02-secure-form-edit-api.md) |
| AJAX without a form (`load` only) | **08, 09** | [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 |
| Encrypt IDs / unique `$key2` | **11 §8, 05, 08** | [EX-02](examples/EX-02-secure-form-edit-api.md), [EX-14](examples/EX-14-auth-and-2fa.md) |
| Validation / error responses | 19 | [EX-09](examples/EX-09-validation-and-errors.md) — **`data(true)`** = original; `data()` = protected |
| Config / keys | 10 | [EX-08](examples/EX-08-config-secrets.md) |
| Bridge click | 09 | [EX-07](examples/EX-07-bridge.md) |
| Auth / 2FA / permissions | **11** (incl. §11 privilege / secrets / SQL owner / bot **warn**), **09** (`twoFactor`), **19** (`data(true)`) | [EX-14](examples/EX-14-auth-and-2fa.md) |
| **Any attack surface (input, auth, output, upload, public endpoint)** | **24** attack vectors — open the matching section, then §11 threat pass | [EX-01](examples/EX-01-secure-form-complete.md), [EX-14](examples/EX-14-auth-and-2fa.md) |
| **New table / migration / any loop or query you care about** | **25** performance — §1 memory, §2 I/O, **§3 indexes**, §4 column types, §5 big lists, §6 frontend | [EX-13](examples/EX-13-schema-migrations.md), [EX-04](examples/EX-04-database-crud.md) |
| **Every file you write (docblocks + why comments)** | **25 §7** | [EX-01](examples/EX-01-secure-form-complete.md) |
| Cache / logs / sessions | 20 | [EX-10](examples/EX-10-cache-logger-session.md) |
| Email / SMS / QR | 21 | [EX-11](examples/EX-11-email-sms-qr.md) |
| AI / search / MCP | 22 | [EX-12](examples/EX-12-ai-search-mcp.md) |
| Services index | 12 | — |
| Tests | 13 | — |
| Anything uncertain | 14, 15, **23**, then `app/parts/` | examples/README.md |
| **Debug / “it doesn’t work”** | **23** (grep middleware + `crcCheck` count first) | [EX-01](examples/EX-01-secure-form-complete.md) |
