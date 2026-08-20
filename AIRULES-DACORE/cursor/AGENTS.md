# AGENTS.md — DotApp + DACore

You are working on a **DotApp PHP** project (not Laravel/Symfony/CodeIgniter) that has the **DACore** admin module installed.

## Before any edit

1. Read `AIRULES/00-AGENT-CONTRACT.md`.
2. Follow the entire `AIRULES/` knowledge base.
3. Edit **only** `app/config.php` and `app/modules/<TargetModule>/` (the module you are programming — including **its** assets).
4. **Never** edit `app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`.
5. **DACore default:** **Never** edit, patch, delete, or **add** anything under `app/modules/DACore/` (files **or** assets). **Never propose** a DACore edit. Implement in the current module.
6. **DACore exception:** only if the user **themselves** asks to edit DACore **and** confirms they know the next update **wipes** those changes. Then edit DACore for that request. Vague “fix the admin” is not enough. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §1.

## Cursor credits (**MUST**)

When **planning** programming, **ASK** whether more expensive models may be used. If the user does not say yes: stay on **this** chat model. Subagents that write or plan code **MUST inherit** (`inherit`). **MUST NOT** silently spawn Opus / GPT-5 / thinking / xhigh / cloud / best-of-N. **Composer 2.5** is OK **only** for hunting a pile of files — **not** as the programmer. A bigger model is for a capability this one lacks (e.g. generate an image) — **ASK** if it costs extra. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2b.

## Finish gate (**MUST** — law)

After **every** code chunk (route, middleware, controller, query, form, view, JS) **and** before saying done: **MUST** grep this module — do not imagine the result. **MUST NOT** claim done if any row fails.

1. **CRC once** — count `crcCheck(` on that POST (middleware + `before` + `#DACore:AuthTest@CRC!` / `LoginAndCRC!` + action). Two calls = first **burns** the token. No CRC on GET. No CRC on `$request->upload()`. Action **MUST NOT** `crcCheck()` after a CRC prefix.
2. **IDs** — no plain `value="7"` / `data-id="7"` / `{{ var: $id }}` as an id. **MUST** `{{ enc(Shop.item.id): $id }}` unique `$key2`. Decrypt `false` → reject. Still `Auth::can` / ownership in PHP.
3. **Queries** — bindings only. No user input in SQL. `$qb->raw()`: every `?` is a placeholder (comments count).
4. **Inputs** — passwords/HTML/hashes from `$request->data(true)`. Persist re-checked in PHP (incl. step-up 2FA). FE overlay is UX only.
5. **Middleware** — login `before` + handlers inside `Auth::isLogged()`. CRC prefix **XOR** action `crcCheck()`, never both. Rights via `#YourModule:Rights@check!` — **not** `#DACore:AuthTest@check!` (it ignores passed rights). Diff **MUST NOT** touch `app/modules/DACore/` unless the informed exception.
6. **Privilege / records** — no TOTP/QR/key in a read-only view; no mutate of a more privileged target; SQL owner scope; own password needs current; public noauth: **warn** about bots (CAPTCHA not MUST). Canonical: `AIRULES/11-AUTH-AND-CRYPTO.md` §11.
7. **Attacks** — `htmlspecialchars` before `{{ var: }}` (it does **not** escape) and `.text()` in JS; whitelist sort + writable columns; no request data in `header()` / redirect / `HttpHelper` URL; no `eval` / `exec` / `unserialize` / `include $x`; `random_bytes` for tokens, `hash_equals` for secrets; `throttle()` on public POST; no `getMessage()` / `var_dump` in the reply; no write to `dacore_*` / `users_rights*`. Catalogue + the 12-grep threat pass: `AIRULES/24-ATTACK-VECTORS.md`.
8. **Catch reported** — every `catch` **and** every `execute()` `$err` calls the module report helper: `Events::trigger('dotapp.catch', $p)` then `dotapp.catch.error` (aborted) / `dotapp.catch.info` (recovered). Fixed payload (`severity, module, source, operation, message, exception, code, file, line, time` + `context` ids/counts, `user_id`), no secrets/tokens/rights/bodies, helper and listener in **your** module, and the user still sees the toast. Canonical: `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9.
9. **Perf / readability** — no `->all()` on a growing table, no query/HTTP/rights check/log inside `foreach` (prefetch + keyed map), no `select('*')` on a list, no O(n²) or per-row array copy, every new `WHERE`/`ORDER BY` column indexed (composite: equality → range → sort), every index carries a comment naming its query, no duplicate of a library DACore ships, every public method a docblock, every logical step a **why** line. Canonical + greps: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §8.

Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2c. Tick `AIRULES/17-CHECKLISTS.md` Finish gate.

## Visible outcome (**MUST** — law)

Every save / toggle / delete / form **MUST** tell the user what happened. Silent success and silent fail are bugs. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2d.

- **DACore admin:** **MUST** grep `app/modules/DACore/` read-only first (Notiflix.Notify / Confirm / Block, `$dotapp().toast()`, `dotapp.toasts.js`). Use the shell. Do **not** invent a second toast library. Outcome channel = **toast**.
- **Public site:** **you** build feedback. Field errors **preferred:** red input + message **on that field**. PHP returns `errors`. Persist still in PHP.
- Empty `.after()` is forbidden.

## Non-negotiable syntax

- Routes: `Module:Controller@method!` (`!` = no DI parameters in the method).
- Controllers: `public static function`.
- Login-required / admin routes: **MUST** HTML `{prefixUrl}/{ModuleName}/…` + `Gate@login`. **POST API:** `/api/v1/auth|noauth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` / `@CRC!` at the start of `initialize()`; action **MUST NOT** `crcCheck()` again. Register handlers only inside `if (Auth::isLogged() === true) { … }`. Canonical: `AIRULES/03-MODULES-AND-ROUTING.md`, `AIRULES/32-DACORE-RIGHTS.md`.
- **Docs (MUST):** English. Docblock on the file/class **and** on every public/static method (purpose, `@param`, `@return`, `@throws`) + a short **why** line above every logical step (guard, decision, formula, named constant, query shape, trap). **MUST NOT** restate the code, prompt-echo, or leave dead code / bare `TODO`. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §7.
- **Catch bus MUST:** every `catch` and every `execute()` `$err` reports `dotapp.catch` + `dotapp.catch.error|info` through **one** helper per module (listener exceptions propagate, so the helper wraps its own `trigger()` calls). Payload keys are fixed; secrets, tokens, rights blobs and request bodies **MUST NOT** be in it; nothing for this goes under `app/modules/DACore/`; a listener **MUST NOT** push a DACore notification per failure. Canonical: `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9.
- DB: `DB::module("RAW")->q(function ($qb) { ... })->all()|first()|execute()`. **MUST** `execute($ok, $err)` — both callbacks. Persist in `try/catch`. **MUST NOT** put `?` in `$qb->raw()` unless it is a real binding — comments count (`COMMENT 'SMS?'` throws). Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md`.
- **Tables MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names, `dotapp_*`, or `dacore_*` for module data.
- Templates: `{{ var: $x }}`, `{{ if }}...{{ /if }}`, `{{ foreach }}...{{ /foreach }}` — **not** Blade `{{ $x }}` / `endif`. **VIEW = outer file:** `setView` + `setLayout` + `renderView()` inserts the layout at `{{ content }}` in the view (or `renderLayout()` / inject a string). User-visible strings **MUST** be product copy (a software company would ship it) — never prompt-echo / “this user can…”. Canonical: `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1b, §8.
- Forms: `<fo-rm>` + `{{ formName(handler) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** for real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop, paginate) **MUST** be `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. + **`/assets/dotapp/dotapp.js`** + PHP `crcCheck()` **once** (API prefix **or** action — never both) + `form()` for real forms. Sample: `AIRULES/examples/EX-01-secure-form-complete.md`. Row-action sample: `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- **Request MUST:** `$request->data(true)` / `$request->query(true)` = original. `$request->data()` is **protected** (`protect()`). **MUST** use original for passwords, HTML, hashes. **MUST** show every login failure. Canonical: `AIRULES/19-VALIDATION-AND-INPUT.md`.
- **Lists MUST paginate:** users, logs, items, orders, messages — any collection that can grow. Ship `paginate()` + an **interactive AJAX** pager in the first version (even if the table is empty today). **MUST NOT** dump `->all()`. **MUST NOT** change pages by reloading the admin shell (`<a href="?page=">`, `location.reload()`). Overlay the list while the request runs; patch rows **and** pager from JSON. **Search / list UX:** **ASK** when planning (search, filters, sort, bulk, page size, DSM remember, CSV only if it fits). Lookup lists **MUST** AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. No toast-undo after delete. Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3, `AIRULES/33-DACORE-PAGES-AND-UI.md` §3.
- **Cheap I/O (MUST):** smallest load — `exists()` / `COUNT(*)` / `limit(1)` / only needed columns / one `join`. **MUST NOT** `->all()` then filter, N+1 in `foreach`, or `Config::db('cache')` for speed. Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §2.
- **Memory (MUST):** page anything that grows; keyed map + `isset()` instead of `in_array()` in a loop; no `array_merge` per iteration; `unset` the raw copy after mapping; stream files. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §1.
- **Indexes (MUST):** every FK + every `WHERE` / `JOIN` / `ORDER BY` column on a growing table; composite order **equality → range → sort**; leftmost prefix counts; no index duplicating a composite prefix; one comment line per index naming its query; a later index = a **new** `Installation.php` version guarded by `indexExists()`. Columns: realistic `VARCHAR`, `decimal` money, FK `bigInteger()->unsigned()` to match `id()`. **Your** tables only — never `dacore_*`. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §3–§4.
- **Session MUST use DSM:** `DSM::use('Shop')->set/get/delete`. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: `AIRULES/20-CACHE-LOGGER-SESSION.md`, `AIRULES/examples/EX-10-cache-logger-session.md`.
- **Save checks MUST run in PHP.** Frontend modal/overlay/disabled button is UX only. Skipping the overlay **MUST** still fail on the server. Canonical: `AIRULES/08-FORMS-AND-SECURITY.md`. DACore 2FA: `AIRULES/32-DACORE-RIGHTS.md` §6.
- **Files MUST use `$dotapp().uploadFile`.** **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()`. **MUST** reject `.php` / executables (extension + `finfo` MIME + headers); FE `accept=` is UX only. Canonical: `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md`.
- JS: `$dotapp` — **not** `$` / `$.ajax`. After a successful `fo-rm` / `load` **MUST** update the DOM from JSON (`html` / data) and a short toast — no `location.reload()`. **MUST** overlay the form/list until the request ends. **DACore admin:** Notiflix (preferred) **or** your module preloaders. **Public website:** you **MUST** build preloaders yourself (Notiflix is DACore-only). UX **MUST** work on desktop **and** mobile. **Public website nav:** overlay drawer from the left or right; lock page scroll while open; the drawer itself scrolls; contacts + compact search in the drawer unless large search is its own mobile section (`AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3). `redirectTo` only when leaving the page. 2FA boxes: **`$dotapp().twoFactor`**. Deletes: graphical confirm first (`Notiflix.Confirm` on admin) — never `alert()` / `window.confirm()`. DACore operators **MUST** keep 2FA on; dangerous admin actions **MUST** step-up 2FA (`AIRULES/32-DACORE-RIGHTS.md` §6). AI write tools: `ui_events` + `DACore.AI.UIEvent` on the matching page only (`AIRULES/34-DACORE-AI-TOOLS.md` §5).

## Debug (user: it doesn’t work)

**MUST** read `AIRULES/23-DEBUG-PLAYBOOK.md`. Grep `crcCheck` in **this module’s** `Middleware/`, `module.init.php` (`->before`), and the controller. Two calls on one request: the first **burns** the token. If you write CRC middleware, the action **MUST NOT** `crcCheck()` again. DACore: grep `AuthTest` — it ignores passed rights; do not patch DACore.

## Scaffolding

Prefer `php dotapper.php` generators. Run from project root. Put `--module=` **before** `--create-controller|model|middleware`.

## Deep docs

| Topic | File |
|-------|------|
| Contract | `AIRULES/00-AGENT-CONTRACT.md` |
| CLI | `AIRULES/02-DOTAPPER-CLI.md` |
| Routing | `AIRULES/03-MODULES-AND-ROUTING.md` |
| Controllers | `AIRULES/04-CONTROLLERS-AND-RESPONSES.md` |
| Views | `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` |
| Database | `AIRULES/06-DATABASE.md` |
| Forms | `AIRULES/08-FORMS-AND-SECURITY.md` |
| Frontend | `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` (§3 = live UX + overlays + **AJAX pagination**; §4 = `$dotapp().fn`; §4.C = jQuery ports) |
| Config/secrets | `AIRULES/10-CONFIG-AND-SECRETS.md` |
| Cache / session | `AIRULES/20-CACHE-LOGGER-SESSION.md` (DSM — never `$_SESSION`) |
| Antipatterns | `AIRULES/14-ANTIPATTERNS.md` |
| Checklists | `AIRULES/17-CHECKLISTS.md` (**Finish gate** = 00 §2c) |
| **Finish gate (after every chunk)** | `AIRULES/00-AGENT-CONTRACT.md` §2c |
| **Visible outcome (save/fail)** | `AIRULES/00-AGENT-CONTRACT.md` §2d |
| **Catch bus (`dotapp.catch` in every catch)** | `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9 |
| **Debug / “it doesn’t work”** | `AIRULES/23-DEBUG-PLAYBOOK.md` (§1b = read the catch trail first) |
| **Attack vectors (law) + threat pass** | `AIRULES/24-ATTACK-VECTORS.md` (§11 = the 12 greps) |
| **Performance, indexes, docblocks** | `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` (§3 indexes, §7 comments, §8 perf pass) |
| **DACore overview** | `AIRULES/30-DACORE-OVERVIEW.md` |
| DACore menu | `AIRULES/31-DACORE-MENU.md` |
| DACore rights | `AIRULES/32-DACORE-RIGHTS.md` |
| DACore pages / UI | `AIRULES/33-DACORE-PAGES-AND-UI.md` |
| DACore AI tools | `AIRULES/34-DACORE-AI-TOOLS.md` |
| DACore installer | `AIRULES/35-DACORE-INSTALL.md` |
| DACore quirks | `AIRULES/36-DACORE-KNOWN-ISSUES.md` |
| DACore notifications | `AIRULES/37-DACORE-NOTIFICATIONS.md` |
| DACore email senders | `AIRULES/38-DACORE-EMAIL.md` |
| DACore SMS drivers | `AIRULES/39-DACORE-SMS.md` |

## DACore rules (hard)

DACore is as sacred as framework core **by default**. It is updated as a package; **any edit or extra file inside it is wiped on update.**

- **Never** edit, patch, delete, or **add** anything under `app/modules/DACore/` unless the **informed exception** in `AIRULES/00-AGENT-CONTRACT.md` §1 applies
- **MUST NOT propose** a DACore edit. Put all new admin features in **the current module** (`app/modules/<YourModule>/`) — including **that** module’s assets
- Use only what DACore already exposes: `DotApp::call("DACore:…")`
- Never write directly to `dacore_menu` / `dacore_ai_tools` / `dacore_installations` / `dacore_modules` / `dacore_plugin_logs` / `dacore_settings` / `dacore_notifications` / `dacore_notifications_inbox` / `dacore_email_senders` / `dacore_email_templates` / `dacore_sms_senders` / `users_rights*`
- Render admin pages with `DACore:Page@withMenu!`
- **Active sidebar (MUST):** edit/detail URLs **MUST** keep the registered list/section leaf highlighted. Pass `withMenu` 7th `$currentFile` (the registered list URL) when the path is not under that leaf (`/Shop/users/4` vs `/Shop/users-list`). Walk-up already covers `/Shop/items/4` if the leaf is `/Shop/items`. **MUST NOT** register a menu row per edit URL. Canonical: `AIRULES/31-DACORE-MENU.md` Active sidebar.
- **MUST search DACore first** before a new JS/CSS library, `$dotapp().fn` widget, or page chrome: grep `app/modules/DACore/` (read-only: assets, vendor, views) and `app/modules/<YourModule>/assets/`. The base already has many subpages and libraries. If it exists, **use it** — do not fork or copy DACore files into your module. Write new code only when the search finds nothing, and only in **your** module. Canonical: `AIRULES/33-DACORE-PAGES-AND-UI.md` “Search DACore first”.
- Prefer DACore widgets; **MUST** add module CSS/JS (`$css`/`$js`) when the shell has no equivalent (charts, ported UI). Classes `{lowercase_modulename}_*`. Match admin colors. Never patch DACore (unless the informed exception applies).
- Admin JS is **`$dotapp`**. jQuery may coexist for UI only. **Requests MUST** use `$dotapp().form` / `load` / bridge — never `$.ajax`. Porting jQuery **is** writing a new `$dotapp().fn` library: **ask**, then rewrite (do not wrap `$.fn`). If DACore already ships the widget, use it. See `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §4.C and `AIRULES/examples/EX-15-dotapp-js-library.md`.
- Guard routes with your own `#YourModule:Rights@check!` — `#DACore:AuthTest@check!` ignores passed rights
- Register menu / rights / AI tools in **your** `Installation.php`
- Push inbox notifications with `DACore:Notifications@push` **on the event** — not from `Installation.php`, not every request (`AIRULES/37-DACORE-NOTIFICATIONS.md`)
- **Sending mail?** Read `AIRULES/38-DACORE-EMAIL.md` — do not invent SMTP
- **Sending SMS?** Read `AIRULES/39-DACORE-SMS.md` — do not invent a gateway
- If this module has a sidebar: own `type => 0` header (one is ideal). **ASK** before a new DACore module: shared full menu vs module-own (`withMenu` `$menuId`). From ~5 items, group with `type => 2` or use header + **one** entry. `menuid` starts with **your** module. Do not register “Return back”. An extension may use another module’s `parent`; uninstall deletes only **your** prefix (`AIRULES/31-DACORE-MENU.md`)
- **Your** modules: while coding use **`install.php`** and **live** init files. After a new migration, rename `installed_*_install.php` → `install.php`. User asks to zip a **DACore-bound** module (including create+zip): **MUST** rename `install.php` → **`dainstall.php` in the zip**, copy live init into **`init/`**, inert root stubs, **no** `install.php` in the zip — DACore **rejects** `install.php` and **never runs** Installation without `dainstall.php`. Working tree stays `install.php`. A non-DACore module: no zip. **MUST NOT** pack `app/modules/DACore/`. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2e, `AIRULES/35-DACORE-INSTALL.md` §4–§5.
- If asked to “just change DACore”: **do not jump in**. Implement in the current module. Edit DACore **only** after they confirm they accept the update wipe (`AIRULES/00-AGENT-CONTRACT.md` §1).

AIRULES is the single source of truth.
