# AGENTS.md — DotApp

You are working on a **DotApp PHP** project (not Laravel/Symfony/CodeIgniter).

## Before any edit

1. **Cursor rules mirror (MUST):** compact `.mdc` files live in `AIRULES/cursor/rules/`. `.cursor/rules/` is a non-portable Cursor copy. If that folder is missing files from AIRULES, or AIRULES cursor rules changed: create `.cursor/rules/`, copy `AIRULES/cursor/rules/*.mdc` there, and copy `AIRULES/cursor/AGENTS.md` to project-root `AGENTS.md`. **MUST NOT** write a new law only under `.cursor/`. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2l, `AIRULES/INSTALL.md`.
2. Read `AIRULES/00-AGENT-CONTRACT.md`.
3. Follow the entire `AIRULES/` knowledge base.
4. Edit **only** `app/config.php` and `app/modules/<TargetModule>/`, plus the Cursor **mirror copy** in step 1.
5. **Never** edit `app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, or other modules — **not even if the user asks**. The kernel is frozen. Implement in the module.

## Cursor credits (**MUST**)

When **planning** programming, **ASK** whether more expensive models may be used. If the user does not say yes: stay on **this** chat model. Subagents that write or plan code **MUST inherit** (`inherit`). **MUST NOT** silently spawn Opus / GPT-5 / thinking / xhigh / cloud / best-of-N. **Composer 2.5** is OK **only** for hunting a pile of files — **not** as the programmer. A bigger model is for a capability this one lacks (e.g. generate an image) — **ASK** if it costs extra. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2b.

## PHP version (**MUST**)

When **planning** programming, **ASK** whether to stay on **PHP 7.4+** (the DotApp default) or write for a higher version. If they do not name a higher version: **PHP 7.4+**. **MUST NOT** ship PHP 8+ syntax (`match`, `?->`, union/`mixed`, named args, constructor promotion, attributes, `enum`, `readonly`, `str_contains` / `str_starts_with` / `str_ends_with`) unless they said yes. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2i.

## Planning depth (**MUST** — law)

When they asked to **plan** a **new module**, a **first** major surface, or a **rewrite**, the plan **MUST** be extremely detailed: every nav item (or `No menu`), every page, every tab, every control (what it does, default, persist). A long plan is correct. A bullet list of endpoints is a failed plan. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2k, `AIRULES/45-MODULE-PLANNING.md`.

## Finish gate (**MUST** — law)

After **every** code chunk (route, middleware, controller, query, form, view, JS) **and** before saying done: **MUST** grep this module — do not imagine the result. **MUST NOT** claim done if any row fails.

1. **CRC once** — count `crcCheck(` on that POST (middleware + `before` + action). Two calls = first **burns** the token. No CRC on GET. No CRC on `$request->upload()`. New public controller/middleware PHPDoc **MUST** start with `CRCchecking —` naming that layer; if it names a prefix, the action **MUST NOT** `crcCheck()`.
2. **IDs** — no plain `value="7"` / `data-id="7"` / `{{ var: $id }}` as an id. **MUST** `{{ enc(Shop.item.id): $id }}` unique `$key2`. Decrypt `false` → reject. Still `Auth::can` / ownership in PHP.
3. **Queries** — bindings only. No user input in SQL. `$qb->raw()`: every `?` is a placeholder (comments count).
4. **Inputs** — passwords/HTML/hashes from `$request->data(true)`. Persist re-checked in PHP. FE overlay is UX only.
5. **Middleware** — login `before` + handlers inside `Auth::isLogged()`. CRC prefix **XOR** action `crcCheck()`, never both. No overlapping CRC `before` hooks.
6. **Privilege / records** — no TOTP/QR/key in a read-only view; no mutate of a more privileged target; SQL owner scope; own password needs current; public noauth: **warn** about bots (CAPTCHA not MUST). Canonical: `AIRULES/11-AUTH-AND-CRYPTO.md` §11.
7. **Attacks** — `htmlspecialchars` before `{{ var: }}` (it does **not** escape) and `.text()` in JS; whitelist sort + writable columns; no request data in `header()` / redirect / `HttpHelper` URL; no `eval` / `exec` / `unserialize` / `include $x`; `random_bytes` for tokens, `hash_equals` for secrets; `throttle()` on public POST; no `getMessage()` / `var_dump` in the reply. Catalogue + the 12-grep threat pass: `AIRULES/24-ATTACK-VECTORS.md`.
8. **Catch reported** — every `catch` **and** every `execute()` `$err` calls the module report helper: `Events::trigger('dotapp.catch', $p)` then `dotapp.catch.error` (aborted) / `dotapp.catch.info` (recovered). Fixed payload (`severity, module, source, operation, message, exception, code, file, line, time` + `context` ids/counts, `user_id`), no secrets/tokens/bodies, and the user still sees the outcome. Canonical: `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9.
9. **Perf / readability** — no `->all()` on a growing table, no query/HTTP/log inside `foreach` (prefetch + keyed map), no `select('*')` on a list, no O(n²) or per-row array copy, every new `WHERE`/`ORDER BY` column indexed (composite: equality → range → sort), every index carries a comment naming its query, every public method a PHPDoc **purpose sentence** then tags (not tags-only), every logical step **`// Why:`**, page actions **`// About:`** / **`// Section:`**. Canonical + greps: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §8.
10. **Hooks** — useful side-effects fire `module.{mod}.{name}.hook` + `Hook:`/`Why:`/`About:`/`Params:`/`Use:` + `.hooks`; not on every save; no old `shop.item.saved` shape; no secrets; no `trigger()` inside a growing `foreach`. Pre-action stop = `triggerWithVeto()` + `Veto` (not `return false`). Listener map may cover the producer URL. Canonical: `AIRULES/41-MODULE-HOOKS.md`.
11. **Extender** — judge first, **not** every method. Owner `exists()` + `call()`; ordinary result returns, only `isOriginal()` continues owner logic. Extender `extend()` belongs in `Listeners::register()` before Module initialization. Target URLs in listener map; own Module routes or `[]`; controller string preferred. Not `next()`, marker response, `.loaded` for initialize-time, Events, or `$request`/secrets. Canonical: `AIRULES/12-SERVICES.md` §10, `AIRULES/00-AGENT-CONTRACT.md` §2h.
12. **PHP 7.4+** — unless the plan named a higher version: no `match`, `?->`, union/`mixed`, named args, constructor promotion, attributes, `enum`, `readonly`, `str_contains` / `str_starts_with` / `str_ends_with`. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2i.
13. **MySQL-safe DDL** — `Installation.php` / `ensureTable` has no `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS`. Probe then `CREATE`/`ALTER`. Canonical: `AIRULES/07-SCHEMA-AND-INSTALL.md` §0.
14. **HTML via Renderer** — when markup can be a template, it **MUST** be a template. Grep Controllers/Libraries for `$html .=` / `'<table` / `'<tr` / `'<div class=` / `*Html(` factories. A PHP HTML string is **only** for a named one-piece exception (`// Why:` + sandbox drop / pager `<li>` / one tiny chip) — never a table, grid, tree, empty state, crumbs, or pager wrapper. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2j, `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1c.

Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2c. Tick `AIRULES/17-CHECKLISTS.md` Finish gate.

## Visible outcome (**MUST** — law)

Every save / toggle / delete / form **MUST** tell the user what happened. Silent success and silent fail are bugs. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2d.

- **Field errors (preferred on public FE + BE):** PHP returns `errors` keyed by field. JS marks that input (red/invalid) **and** shows the message **on the field** (where + what).
- **Success / non-field fail:** **your** module toast / status node + `reply.message`. Notiflix does not exist here. Never `alert()`.
- Empty `.after()` is forbidden.

## HTML via Renderer (**MUST** — law)

When markup **can** be a template, it **MUST** be a template. PHP prepares data. `Renderer` + `.view.php` / `.layout.php` produce HTML. **MUST NOT** concatenate tables, grids, empty states, pager chrome, trees, or crumbs in Controllers/Libraries. A PHP HTML string is **only** for a named one-piece exception (`// Why:` + sandbox drop / pager `<li>` / one tiny chip). Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2j, `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1c.

## Non-negotiable syntax

- Routes: `Module:Controller@method!` (`!` = no DI parameters in the method).
- Controllers: `public static function`.
- **PHP 7.4+ (ASK in plan):** default language is PHP 7.4+. **ASK** whether to stay on 7.4+ or write for a higher version. No answer → 7.4+. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2i.
- **Module identity (ASK in plan):** for a new module with visible UI, ask once for display name/purpose, optional logo/banner, placement, colours and alt text. Offer text-only/no custom branding; skip for backend-only modules. Never invent or hotlink branding. Canonical: `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §8b.
- **Planning depth (MUST):** new module / first surface / rewrite — inventory every nav item (or `No menu`), page, tab, and control in the plan. Length is OK. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2k, `AIRULES/45-MODULE-PLANNING.md`.
- Login-required routes: **MUST** prefix `/{ModuleName}/…` (subtree if the module has public pages). Cover HTML with `Router::before([$area, $area . '/*'], '#Shop:Gate@login!')` (403). **POST API:** `/api/v1/auth|noauth/{Module}/…` + `Gate@loginAndCrc` / `Gate@crc` at the start of `initialize()`; action **MUST NOT** `crcCheck()` again. Register handlers only inside `if (Auth::isLogged() === true) { … }`. Canonical: `AIRULES/03-MODULES-AND-ROUTING.md`.
- **Docs (MUST):** English. Every public method in `Controllers/` and `Middleware/` **MUST** start PHPDoc with **`CRCchecking —`** (exact prefix/middleware, or `this action`, or `none` for GET/upload/helper) — then a **purpose sentence**, then `@param` / `@return` / `@throws` with meaning — tags-only (`@return array<string, mixed>`) is a bug. **MUST NOT** document prefix CRC and still `crcCheck()` in that method. Inline **`// Why:`** / **`// About:`** / **`// Section:`**. **MUST NOT** restate the code, prompt-echo, omit the labels, or leave dead code / bare `TODO`. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §7, `AIRULES/08-FORMS-AND-SECURITY.md`.
- **Hooks MUST:** useful side-effects `Events::trigger('module.{mod}.{name}.hook')` + comment block + `app/modules/<This>/.hooks`. **MUST NOT** fire on every save. Listen in **your** `module.listeners.php` (`Listeners::initializeRoutes()` may cover the producer URL). Pre-action stop = `triggerWithVeto()` + `Veto`. Canonical: `AIRULES/41-MODULE-HOOKS.md`.
- **Extender (judge — not every method):** owner `exists()` + `call()`; ordinary result returns, only `isOriginal()` continues; `extend()` in `Listeners::register()`; target URLs in explicit listener routes; Module owns only its routes or `[]`; prefer a controller string. **MUST NOT** invent `next()`, return the marker, use listener `['*']` just to attach, or `.loaded` for initialize-time. Canonical: `AIRULES/12-SERVICES.md` §10, EX-17.
- **Catch bus MUST:** every `catch` and every `execute()` `$err` reports `dotapp.catch` + `dotapp.catch.error|info` through **one** helper per module (listener exceptions propagate, so the helper wraps its own `trigger()` calls). Payload keys are fixed; secrets, tokens and request bodies **MUST NOT** be in it. Canonical: `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9.
- DB: `DB::module("RAW")->q(function ($qb) { ... })->all()|first()|execute()`. **MUST** `execute($ok, $err)` — both callbacks. Persist in `try/catch`. **MUST NOT** put `?` in `$qb->raw()` unless it is a real binding — comments count (`COMMENT 'SMS?'` throws). Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md`.
- **Tables MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names or `dotapp_*` for module data.
- **Installer DDL MUST be MySQL-safe:** probe first (`SHOW TABLES LIKE` / `information_schema` + `DATABASE()`), then `CREATE TABLE` / `ALTER TABLE`. **MUST NOT** `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS`. Helpers in **this** module. `$qb->createTableIfNotExist()` is OK. Canonical: `AIRULES/07-SCHEMA-AND-INSTALL.md` §0, `AIRULES/00-AGENT-CONTRACT.md` §5 item 24.
- Templates: `{{ var: $x }}`, `{{ if }}...{{ /if }}`, `{{ foreach }}...{{ /foreach }}` — **not** Blade `{{ $x }}` / `endif`. **VIEW = outer file:** `setView` + `setLayout` + `renderView()` inserts the layout at `{{ content }}` in the view (or `renderLayout()` / inject a string). **HTML via Renderer (LAW):** when it can be a template it **MUST** be; **MUST NOT** `$html .= '<table'` factories. PHP markup **only** for a named one-piece exception. User-visible strings **MUST** be product copy — never prompt-echo / “this user can…”. Canonical: `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1b, §1c, §8.
- Forms: `<fo-rm>` + `{{ formName(handler) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** for real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop, paginate) **MUST** be `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. + **`/assets/dotapp/dotapp.js`** + PHP `crcCheck()` **once** (API prefix **or** action — never both) + `form()` for real forms. Sample: `AIRULES/examples/EX-01-secure-form-complete.md`. Row-action sample: `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- **Request MUST:** `$request->data(true)` / `$request->query(true)` = original. `$request->data()` is **protected** (`protect()`). **MUST** use original for passwords, HTML, hashes. **MUST** show every login failure. Canonical: `AIRULES/19-VALIDATION-AND-INPUT.md`.
- **Lists MUST paginate:** users, logs, items, orders, messages — any collection that can grow. Ship `paginate()` + an **interactive AJAX** pager in the first version (even if the table is empty today). **MUST NOT** dump `->all()`. **MUST NOT** change pages by reloading the site (`<a href="?page=">`, `location.reload()`). Overlay the list while the request runs; patch rows **and** pager from JSON. **Search / list UX:** **ASK** when planning (search, filters, sort, bulk, page size, DSM remember, CSV only if it fits). Lookup lists **MUST** AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. No toast-undo after delete. Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3, `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- **Cheap I/O (MUST):** smallest load — `exists()` / `COUNT(*)` / `limit(1)` / only needed columns / one `join`. **MUST NOT** `->all()` then filter, N+1 in `foreach`, or `Config::db('cache')` for speed. Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §2.
- **Memory (MUST):** page anything that grows; keyed map + `isset()` instead of `in_array()` in a loop; no `array_merge` per iteration; `unset` the raw copy after mapping; stream files. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §1.
- **Indexes (MUST):** every FK + every `WHERE` / `JOIN` / `ORDER BY` column on a growing table; composite order **equality → range → sort**; leftmost prefix counts; no index duplicating a composite prefix; one comment line per index naming its query; a later index = a **new** `Installation.php` version guarded by `indexExists()`. Columns: realistic `VARCHAR`, `decimal` money, FK `bigInteger()->unsigned()` to match `id()`. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §3–§4.
- **Session MUST use DSM:** `DSM::use('Shop')->set/get/delete`. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: `AIRULES/20-CACHE-LOGGER-SESSION.md`, `AIRULES/examples/EX-10-cache-logger-session.md`.
- **Save checks MUST run in PHP.** Frontend modal/overlay/disabled button is UX only. Skipping the overlay **MUST** still fail on the server. Canonical: `AIRULES/08-FORMS-AND-SECURITY.md`.
- **Files MUST use `$dotapp().uploadFile`.** **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()`. **MUST** reject `.php` / executables (extension + `finfo` MIME + headers); FE `accept=` is UX only. Canonical: `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md`.
- JS: `$dotapp` — **not** `$` / `$.ajax`. After a successful `fo-rm` / `load` **MUST** update the DOM from JSON (`html` / data) and a short toast — no `location.reload()`. **MUST** overlay the form/list with **your module preloaders** until the request ends. UX **MUST** work on desktop **and** mobile. **Public website nav:** overlay drawer from the left or right; lock page scroll while open; the drawer itself scrolls; contacts + compact search in the drawer unless large search is its own mobile section (`AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3). `redirectTo` only when leaving the page. 2FA boxes: **`$dotapp().twoFactor`**. Deletes: graphical confirm first — never `alert()` / `window.confirm()`.

## Debug (user: it doesn’t work)

**MUST** read `AIRULES/23-DEBUG-PLAYBOOK.md`. Grep `crcCheck` in **this module’s** `Middleware/`, `module.init.php` (`->before`), and the controller. Two calls on one request: the first **burns** the token. If you write CRC middleware, the action **MUST NOT** `crcCheck()` again. Missing business event: open the owner’s `.hooks`, then grep `Events::trigger(` there (`AIRULES/41-MODULE-HOOKS.md`).

## Scaffolding

Prefer `php dotapper.php` generators. Run from project root. Put `--module=` **before** `--create-controller|model|middleware`.

## Deep docs

| Topic | File |
|-------|------|
| Contract | `AIRULES/00-AGENT-CONTRACT.md` |
| CLI | `AIRULES/02-DOTAPPER-CLI.md` |
| Routing | `AIRULES/03-MODULES-AND-ROUTING.md` |
| Controllers | `AIRULES/04-CONTROLLERS-AND-RESPONSES.md` |
| Views | `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` (§1c = HTML via Renderer law) |
| Database | `AIRULES/06-DATABASE.md` |
| Schema / installer DDL | `AIRULES/07-SCHEMA-AND-INSTALL.md` (§0 = probe-then-CREATE, no `CREATE TABLE IF NOT EXISTS`) |
| Forms | `AIRULES/08-FORMS-AND-SECURITY.md` |
| Frontend | `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` (§3 = live UX + overlays + **AJAX pagination**; §4 = `$dotapp().fn`; §4.C = jQuery ports) |
| Config/secrets | `AIRULES/10-CONFIG-AND-SECRETS.md` |
| Cache / session | `AIRULES/20-CACHE-LOGGER-SESSION.md` (DSM — never `$_SESSION`) |
| Antipatterns | `AIRULES/14-ANTIPATTERNS.md` |
| Checklists | `AIRULES/17-CHECKLISTS.md` (**Finish gate** = 00 §2c) |
| **Finish gate (after every chunk)** | `AIRULES/00-AGENT-CONTRACT.md` §2c |
| **Visible outcome (save/fail)** | `AIRULES/00-AGENT-CONTRACT.md` §2d |
| **HTML via Renderer (templates, not PHP factories)** | `AIRULES/00-AGENT-CONTRACT.md` §2j, `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1c |
| **Catch bus (`dotapp.catch` in every catch)** | `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9 |
| **Event tracer (`dotapp.catchall` — core fires every trigger)** | `AIRULES/01-ARCHITECTURE.md`, `AIRULES/23-DEBUG-PLAYBOOK.md` §1c |
| **Debug / “it doesn’t work”** | `AIRULES/23-DEBUG-PLAYBOOK.md` (§1b = read the catch trail first) |
| **Attack vectors (law) + threat pass** | `AIRULES/24-ATTACK-VECTORS.md` (§11 = the 12 greps) |
| **Performance, indexes, PHPDoc purpose + Why/About/Section** | `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` (§3 indexes, §7 comments, §8 perf pass) |
| **Module hooks (`module.{mod}.{name}.hook` + `.hooks`)** | `AIRULES/41-MODULE-HOOKS.md` (sample: `AIRULES/examples/EX-16-module-hooks.md`) |
| **Extender (judge — not every method)** | `AIRULES/12-SERVICES.md` §10 (sample: `AIRULES/examples/EX-17-extender.md`) |
| **Planning depth (new module / first surface / rewrite)** | `AIRULES/00-AGENT-CONTRACT.md` §2k, `AIRULES/45-MODULE-PLANNING.md` |
| **Cursor rules live in AIRULES (mirror to `.cursor/`)** | `AIRULES/00-AGENT-CONTRACT.md` §2l, `AIRULES/INSTALL.md` |

AIRULES is the single source of truth.
