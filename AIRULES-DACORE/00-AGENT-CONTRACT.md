# 00 — Agent Contract (HARD LAWS)

**Read this file before every DotApp task.**  
**AIRULES is the single source of truth.** It supersedes leftover `.cursorrules` / `*_AI_guide.md` / `database_guide.md` from older installs.

This is **DotApp** — not Laravel, Symfony, CodeIgniter, Blade, Twig, Eloquent, or jQuery.

---

## 1. Edit boundaries

### ALLOWED (edit freely when asked)

| Path | Notes |
|------|--------|
| `app/config.php` | **Only** framework file agents may edit. Secrets, DB, drivers, module overrides. |
| `app/modules/<YourModule>/` | **Only the module you are currently programming** — PHP, views, **and that module’s assets**. Not another module. Not DACore. |

### ASK FIRST (do not touch unless the user explicitly requests)

| Path | Preferred alternative |
|------|------------------------|
| `app/listeners.php` | Prefer `module.listeners.php` inside your module. |
| `.htaccess` | Prefer `php dotapper.php --create-htaccess`. |
| Another **your-project** module's folder | Only with explicit permission naming that module. **Never propose** editing DACore instead. |

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
| Any file outside the target module + `app/config.php` | Scope violation (DACore: see below) |

If you believe a core bug exists: **stop and ask the user**. Do not patch core.

### DACore files — strict default, informed exception only

**Default (MUST):** do **not** edit, patch, add, or delete anything under `app/modules/DACore/` — PHP, views, **assets**, menu rows, settings, anything. Implement in the **current** module only. Consume `DotApp::call("DACore:…")`.

**MUST NOT propose** a DACore edit. Never offer “I can patch DACore”, “let me change the admin shell”, or “this belongs in DACore”. Solve it in `app/modules/<YourModule>/`.

**Exception (user-initiated, informed):** edit DACore **only** when **all** of these are true:

1. The user **themselves** asked to change DACore / `app/modules/DACore/` (not a vague “fix the admin”).
2. You **warned once** that the next DACore package update **wipes** local changes; the durable path is their own module.
3. They **confirmed** they know that and still want the DACore edit.

Then — and only then — you may edit DACore **for that request**. Otherwise: **strict ban**.

See [§7](#7-dacore-is-sacred-same-rank-as-framework-core).

---

## 2. Mandatory workflow

1. **Identify the target module** (or create one). If it is a **new DACore-bound module**, **ASK in chat first**: shared full sidebar vs **module-own** menu (`Page@withMenu` `$menuId`). Do not guess. From about five items, do not dump leaves under a header. [31](31-DACORE-MENU.md).
2. **Read** the relevant AIRULES docs for the task (routing / views / DB / forms / JS).
3. **Generate** with `dotapper.php` whenever possible (module, controller, model, middleware).
4. **Implement** only inside the allowed paths.
5. **Tables:** every table your module owns **MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names, `dotapp_*`, or `dacore_*` for module data. See [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md) §3.
6. **Lists:** any screen that lists records that **can accumulate** (users, logs, items, orders, messages, files, events) **MUST** ship `paginate()` **and** an **interactive AJAX pager** in the **first** version. Empty table today is not an excuse. A pager that reloads the admin shell is not a pager. **Search / list UX:** when **planning**, **ASK** (search, filters, sort, bulk, page size, remember in DSM, CSV only if it fits). Lookup lists **MUST** ship AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. See [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
7. **Verify** against [17-CHECKLISTS.md](17-CHECKLISTS.md) before claiming done.

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
| Instance controllers + `$this->` | `public static function` controllers |
| `$`, `jQuery`, `$.ajax` | `$dotapp`, `$dotapp().load(...)` |
| `<form>` + manual CSRF only | Prefer `<fo-rm>` + `{{ formName(handler) }}` |
| `{{ formName }}` after `</fo-rm>` | **MUST** between `<fo-rm>` and `</fo-rm>` |
| Plain IDs in HTML/JSON (`value="7"`, `data-id="7"`) | **MUST** `{{ enc(Shop.item.id): $id }}` — unique `$key2` per field |
| `<fo-rm>` around every row button / D&D | `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)) |
| List/form still clickable during `load()` | Cover the region — **DACore admin:** Notiflix or module preloaders; **public site:** module preloaders ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Logs / users / items dumped with `->all()`, no pager, or “few rows now so skip” | **MUST** `paginate()` + interactive AJAX on **first ship** ([06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Lookup list with no search / JS-filter of `->all()` | **ASK** in the plan; articles/catalog **MUST** AJAX search unless declined ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `<a href="?page=2">` for an in-app list | Forbidden — that reloads the admin shell. Use `DACore:Page@paginate!` **`$callable`** ([33](33-DACORE-PAGES-AND-UI.md) §3) |
| Custom OTP digit widget / jQuery 2FA plugin | **MUST** `$dotapp().twoFactor` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `alert()` / `window.confirm()` to delete | Graphical dialog first (`Notiflix.Confirm` on admin, module modal on the public site) ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Prompt-echo UI copy (“this user can hide the icon…”) | Product copy a software company would ship ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| New admin library without searching DACore | **MUST** grep `app/modules/DACore/` (read-only) + your module first ([33](33-DACORE-PAGES-AND-UI.md)) |
| `f-form` attribute | **Does not exist** — use `<fo-rm>` |
| `$_SESSION` / `session_start()` | **MUST** `DSM::use('Shop')` ([20](20-CACHE-LOGGER-SESSION.md)) |
| JS overlay / modal as the only save or 2FA gate | **MUST** re-check in PHP; FE is UX only ([08](08-FORMS-AND-SECURITY.md)) |
| File/ZIP in `FormData` + `load()` / `<fo-rm>` | **MUST** `$dotapp().uploadFile` + `$request->upload()`; PHP rejects `.php` ([09](09-DOTAPP-JS-AND-BRIDGE.md)) |

Full table: [14-ANTIPATTERNS.md](14-ANTIPATTERNS.md).

---

## 5. Security non-negotiables

1. **Preferred form stack (default for all interactive forms):**
   - Markup: `<fo-rm>` + `{{ formName(handler) }}` (not `f-form`, not Laravel `_token` alone)
   - **MUST:** `{{ formName(handler) }}` sits **between** `<fo-rm …>` and `</fo-rm>` — never before `<fo-rm>`, never after `</fo-rm>` (outside the pair the tag is left unchanged: silent failure)
   - Script: **`/assets/dotapp/dotapp.js` first** (injects random per-session keys — without it secure forms fail)
   - JS: `$dotapp().form(...).before().after()` + `parseReply` + **MUST** block while in flight (**DACore admin:** Notiflix preferred **or** module preloaders. **Public site:** module preloaders — Notiflix is DACore-only)
   - **MUST:** after success, patch the DOM (`reply.html` / data) and a short toast. `<fo-rm>` does **not** reload. No `location.reload()`. `redirectTo` only when leaving the page ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
   - PHP: `$request->crcCheck()` then `$request->form([...], "handler", ...)` then `ajaxReply`
   - Full sample: [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md)
2. This stack is **stronger than plain CSRF** (binds handler + action + method, CRC, one-time tokens, JS key material). Use it **only for real HTML forms** (several fields + submit). **MUST NOT** wrap row actions (toggle, delete, reorder, drag-and-drop, paginate) in `<fo-rm>` — those are `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)).
3. Never skip CRC/CSRF for endpoints that receive `$dotapp().load()` / secure forms.
4. **MUST encrypt every identifier sent to the browser** (`<option value>`, `data-*`, hidden, JSON). Use `{{ enc(Shop.user.id): $id }}` / `Crypto::encrypt($id, 'Shop.user.id')` with a **different `$key2` per field**. Never `value="7"` / `data-id="7"`. Decrypt with the **same** `$key2`; `false` → reject. **MUST still** `Auth::can()` / ownership — encryption is not a substitute for rights ([11](11-AUTH-AND-CRYPTO.md) §8).
5. Never interpolate user input into SQL — use QueryBuilder bindings or `raw($sql, $bindings)`.
6. On new apps, generate real `app.c_enc_key` / `rm_key` / `rmrcm_key` (see [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md)).
7. Module settings must have **fallbacks** if the user did not fill `app/config.php`.
8. **MUST paginate accumulating lists** (users, logs, items, …) with an **interactive** pager (`$dotapp().load()`). Shipping the list with no pager, or changing pages by reloading the document, is incomplete. Lookup lists **MUST** ship **interactive AJAX search** unless the user declined; other lists: **ASK** in the plan. [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
9. **MUST** store app session state with **`DSM::use('Shop')`**. **MUST NOT** `$_SESSION` or `session_start()` ([20](20-CACHE-LOGGER-SESSION.md)).
10. **MUST** re-check every persist in **PHP** (`crcCheck`, `Auth::can`, 2FA code, ownership, validation). Frontend modal/overlay/disabled control is **UX only**. Removing the overlay **MUST** still fail on the server ([08](08-FORMS-AND-SECURITY.md)).
11. **MUST** upload files with **`$dotapp().uploadFile`**. **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()` on that endpoint. **MUST** reject `.php` and other executables (extension + `finfo` MIME + headers); FE `accept=` is UX only ([09](09-DOTAPP-JS-AND-BRIDGE.md)).

---

## 6. Identity reminder (paste into new PHP files)

```php
<?php
/**
 * DOTAPP MODULE FILE
 * - Controllers: Module:Controller@method!  (! = no DI params)
 * - Database: DB::module("RAW")->q(...)->all()|first()|execute()
 * - Tables: {lowercase_modulename}_*  (Shop → shop_items) — NEVER items, dotapp_*, or dacore_*
 * - Templates: {{ var: $x }}  — NOT {{ $x }}. VIEW = outer file; setLayout+renderView fills {{ content }} in that view (or renderLayout / str_replace)
 * - Forms: <fo-rm> only for real multi-field submit; row actions = load() + data-* (not fo-rm)
 * - FE ids: {{ enc(Shop.item.id): $id }} unique $key2 per field; Auth::can still required
 * - JS: $dotapp — NOT jQuery $; after save/toggle MUST patch DOM + toast (no reload); MUST overlay until request ends (DACore admin: Notiflix or module; public site: module preloaders; desktop+mobile)
 * - Lists: accumulating records (users/logs/items) MUST paginate() on first ship + AJAX pager — NOT all() dump, NOT ?page= / location.reload()
 * - Search: ASK in the plan; lookup lists (articles/products) MUST AJAX search unless declined — debounce, 3+ chars, SQL+paginate, NOT JS filter
 * - 2FA boxes: $dotapp().twoFactor — do not invent OTP widgets
 * - Deletes: graphical confirm first — never alert()/confirm()
 * - UI copy: product language — never prompt-echo / “this user can…”
 * - Session: DSM::use('Shop') — NEVER $_SESSION / session_start()
 * - Save checks: PHP MUST re-verify — FE modal/overlay is UX only
 * - Files: $dotapp().uploadFile — NEVER FormData + load()/fo-rm; PHP MUST reject .php (ext+MIME+headers)
 * - Your module under DACore: dainstall.php (NOT install.php); init/ copies; blank root only on user-asked export. NEVER apply this to app/modules/DACore/
 * - DACore: search DACore (read-only) + this module before writing a new library/widget — do not reinvent
 * - DACore: operators MUST keep 2FA on; dangerous actions MUST step-up 2FA (32 §6)
 * - DACore AI writes: ui_events (name = tool id) + DACore.AI.UIEvent on the matching page only
 * - Edit only this module + app/config.php (this module’s assets too). Do not edit other modules.
 * - Never edit app/parts/. Never edit app/modules/DACore/ unless the user themselves asked and confirmed the update wipe (00 §1).
 * - Never propose a DACore edit — implement in this module.
 * See AIRULES/00-AGENT-CONTRACT.md
 */
```

---

## 7. DACore is sacred (same rank as framework core)

This rulebook variant covers **framework + DACore**. DACore is an admin-UI **module**, not framework core — treat its files like core **by default**.

**Why:** DACore is installed and updated as a complete package. Any edit, patch, extra file, or “small addition” inside `app/modules/DACore/` **vanishes on the next DACore update**. There is no merge.

**Default:** **MUST NOT** edit DACore (files, assets, menu, settings). **MUST NOT** propose a DACore edit. Work **only** in the module you are programming (`app/modules/<YourModule>/`, including **that** module’s assets). Use public APIs: `DotApp::call("DACore:…")`.

**Informed exception:** If the user **on their own** asks to edit DACore **and** confirms they accept the update wipe, you may edit DACore for that request. Warn once, then wait. Vague “fix the admin” is **not** permission. Details: [§1](#dacore-files--strict-default-informed-exception-only).

| Never (default) | Instead |
|-----------------|---------|
| Edit any existing file under `app/modules/DACore/` | Current module + `DotApp::call("DACore:…")` |
| **Add** controllers, views, JS, CSS, SQL, or any other file into DACore | Create **your own** module: `app/modules/<YourModule>/` |
| Offer / suggest a DACore patch | Implement in the current module; do not mention DACore as a place to write |
| Quick-fix a DACore bug in place without an informed ask | Warn (wipe on update); only proceed if they still insist on editing DACore |
| Fork / copy DACore internals into DACore | Read DACore source **read-only**; call only documented APIs |

| Rule | Detail |
|------|--------|
| **Never write directly** to `dacore_menu`, `dacore_ai_tools`, `dacore_installations`, `dacore_modules`, `dacore_plugin_logs`, `dacore_settings`, `dacore_notifications`, `dacore_notifications_inbox`, `{prefix}users_rights*` | Use the registration / push APIs |
| Register menu / rights / AI tools | In **your** `Installation.php`, not per request |
| **`dainstall.php` + `init/`** | **Your** modules that work **under** DACore (`app/modules/Shop/`, …). **MUST** `dainstall.php` (not `install.php`). Keep copies of `module.init.php` / `module.listeners.php` in `init/`. Blank the root files only when the user asks to **export**. DACore unpacks, runs `dainstall.php`, then copies `init/` into the root on success. **MUST NOT** do any of this to `app/modules/DACore/` itself. [35](35-DACORE-INSTALL.md) §4–§6. |
| **New module menu** | **ASK** before scaffolding: shared sidebar vs module-own (`withMenu` `$menuId`). Group `type => 2` when many items stay in the global tree; large modules: header + **one** entry, inner pages pass the branch id. Do not register “Return back”. [31](31-DACORE-MENU.md) |
| Render admin pages | `DACore:Page@withMenu!` — never build your own HTML shell |
| Missing widgets / ported UI | **MUST search DACore first** (read-only). Then **MUST** add CSS/JS in **your** module (`$css`/`$js` on `withMenu`) only if nothing fits. Prefix classes `{lowercase_modulename}_*`. Match DACore colors. Never patch DACore. |
| Admin JS / ports | DACore runs on **`$dotapp`**. jQuery may coexist for **UI only**. **All requests** use `$dotapp().form` / `load` / bridge — never `$.ajax`. Porting jQuery **is** writing a new `$dotapp().fn` library — **ask**, then rewrite (do not wrap `$.fn`). Playbook: [09](09-DOTAPP-JS-AND-BRIDGE.md) §4.C, [EX-15](examples/EX-15-dotapp-js-library.md). If DACore already ships the widget, use it. |
| **Notiflix** | **DACore admin shell only.** On `Page@withMenu!` you may use it (preferred) **or** your module overlay. Public / front-office pages **MUST** ship **module preloaders** — Notiflix is not there. Preloaders are **MUST** either way ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3). |
| **Operator 2FA** | DACore operators **MUST** have at least one 2FA method (TOTP / SMS / email) and **MUST NOT** be able to turn it off. Dangerous admin actions **MUST** re-prompt with `$dotapp().twoFactor` and verify in **your** module — not `Auth::confirmTwoFactor` (login stage 2 only). See [32](32-DACORE-RIGHTS.md) §6. |
| **AI write → open page** | Write tools **MUST** return `ui_events` (`name` = tool id) when an admin screen shows that data. Page JS listens `DACore.AI.UIEvent`, filters by name, AJAX-refreshes — no `location.reload()`. Wrong page **MUST NOT** refresh. No secrets in payload. [34](34-DACORE-AI-TOOLS.md) §5. |
| **Lists / pager** | Users, logs, items **MUST** `paginate()` + **interactive AJAX** on first ship. Lookup lists **MUST** AJAX search unless declined; **ASK** on other lists. [09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [33](33-DACORE-PAGES-AND-UI.md) §3. |
| **Search DACore first** | Before a new JS/CSS library, `$dotapp().fn` widget, or page chrome: grep `app/modules/DACore/` (read-only) and your module. The base already has many subpages and libraries. Use what exists; write new code only in **your** module. [33](33-DACORE-PAGES-AND-UI.md) “Search DACore first”. |
| Permission guard | Your own `#YourModule:Rights@check!` — `#DACore:AuthTest@check!` **ignores** the rights you pass |
| Admin routes | Always prefixed with `Config::module("DACore","prefixUrl")` |

Start at [30-DACORE-OVERVIEW.md](30-DACORE-OVERVIEW.md).

---

## 8. Conflict resolution

| Conflict | Winner |
|----------|--------|
| Leftover `.cursorrules` / `*_AI_guide.md` vs AIRULES | **AIRULES** |
| Leftover `database_guide.md` invented APIs | **Ignore** — follow [06-DATABASE.md](06-DATABASE.md) |
| User explicit instruction to edit core | Ask once to confirm; still prefer not to |
| User wants a DACore change | **Do not propose** editing DACore. Implement in the current module. **If they themselves** ask to edit `app/modules/DACore/` **and** confirm they accept the update wipe: then edit DACore for that request. Otherwise **strict ban**. |

---

## 9. Minimum reading map by task

| Task | Theory | Example (open one) |
|------|--------|--------------------|
| **Anything (always)** | **18** error handling / return values | — |
| New module | 00, 02, 03 | [EX-03](examples/EX-03-module-scaffold.md) |
| Route / middleware | 03, 04 | EX-03 |
| Template / CSS / JS page | 05 (incl. §8 product copy), 09 | [EX-05](examples/EX-05-renderer-page.md), [EX-06](examples/EX-06-dotapp-js-boot.md) |
| Stay-on-page save / toggle (live DOM) | **09 §3** (block-while-in-flight, desktop+mobile), **08** | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Paginated list (users, logs, items) | **06**, **09 §3** “Paginate accumulating lists” — **MUST** ship, **MUST** be AJAX | [EX-04](examples/EX-04-database-crud.md), **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| List search (articles, catalog, …) | **09 §3** “Interactive AJAX search” — **ASK** in the plan; lookup lists **MUST** unless declined | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| List UX (filters, sort, empty, bulk, …) | **09 §3** “List UX” — **ASK** / **MUST** table | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Delete (confirm dialog) | **09 §3** “Confirm before delete” | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Custom `$dotapp` library / jQuery port | **09 §4** (esp. §4.C) | **[EX-15](examples/EX-15-dotapp-js-library.md)** |
| Database query | 06, 18 | [EX-04](examples/EX-04-database-crud.md) |
| Tables / migrations | 07 | [EX-13](examples/EX-13-schema-migrations.md) |
| **Secure form (HTML fields + submit)** | **08, 09** | **[EX-01](examples/EX-01-secure-form-complete.md)**, [EX-02](examples/EX-02-secure-form-edit-api.md) |
| AJAX without a form (`load` only) | **08, 09** | [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 |
| Encrypt IDs / unique `$key2` | **11 §8, 05, 08** | [EX-02](examples/EX-02-secure-form-edit-api.md), [EX-14](examples/EX-14-auth-and-2fa.md) |
| Validation / error responses | 19 | [EX-09](examples/EX-09-validation-and-errors.md) |
| Config / keys | 10 | [EX-08](examples/EX-08-config-secrets.md) |
| Bridge click | 09 | [EX-07](examples/EX-07-bridge.md) |
| Auth / 2FA / permissions | **11**, **09** (`twoFactor`) | [EX-14](examples/EX-14-auth-and-2fa.md) |
| Cache / logs / sessions | 20 | [EX-10](examples/EX-10-cache-logger-session.md) |
| Email / SMS / QR | 21 | [EX-11](examples/EX-11-email-sms-qr.md) |
| AI / search / MCP | 22 | [EX-12](examples/EX-12-ai-search-mcp.md) |
| Services index | 12 | — |
| Tests | 13 | — |
| Anything uncertain | 14, 15, then `app/parts/` | examples/README.md |

### DACore admin layer

| Task | Theory | Example |
|------|--------|---------|
| **Any DACore work (start here)** | **30** | — |
| New admin module | 30, **31 (ASK menu layout)**, 32, 35 | [EX-D01](examples/EX-D01-dacore-module-skeleton.md) |
| Menu items | **31** (grouping, shared vs module-own) | EX-D01 |
| Permissions / route guards | 32 | EX-D01 |
| Operator 2FA / dangerous admin actions | **32 §6**, **09** (`twoFactor`) | [EX-14](examples/EX-14-auth-and-2fa.md) |
| Admin page, dotgrid, tables | 33 (incl. §3 AJAX pager; **search DACore first**) | [EX-D02](examples/EX-D02-dacore-admin-page.md) |
| New admin library / widget / `$dotapp().fn` | **33** “Search DACore first”, **09 §4** | [EX-15](examples/EX-15-dotapp-js-library.md) |
| AI tools | 34 (incl. §5 `ui_events`) | [EX-D03](examples/EX-D03-dacore-ai-tool.md) |
| Installer wiring | **35** (`dainstall.php`, `init/`, export) | [EX-D04](examples/EX-D04-dacore-installer.md) |
| Inbox notifications | **37** | [EX-D05](examples/EX-D05-dacore-notifications.md) |
| DACore quirks | 36 | — |
