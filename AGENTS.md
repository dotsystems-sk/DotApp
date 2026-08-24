# AGENTS.md — DotApp + DACore

You are working on a **DotApp PHP** project (not Laravel/Symfony/CodeIgniter) that has the **DACore** admin module installed.

## Before any edit

1. **Cursor rules mirror (MUST):** compact `.mdc` files live in `AIRULES/cursor/rules/`. `.cursor/rules/` is a non-portable Cursor copy. If that folder is missing files from AIRULES, or AIRULES cursor rules changed: create `.cursor/rules/`, copy `AIRULES/cursor/rules/*.mdc` there, and copy `AIRULES/cursor/AGENTS.md` to project-root `AGENTS.md`. **MUST NOT** write a new law only under `.cursor/`. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2l, `AIRULES/INSTALL.md`.
2. Read `AIRULES/00-AGENT-CONTRACT.md`.
3. Follow the entire `AIRULES/` knowledge base.
4. Edit **only** `app/config.php` and `app/modules/<TargetModule>/`, except when the explicit framework-author exception below applies, or the Cursor **mirror copy** in step 1.
5. **Never** edit framework core unless the explicit framework-author exception below applies. `app/vendor/`, `index.php`, `initializedb.php`, and unrelated framework files remain forbidden.
6. **DACore default:** **Never** edit, patch, delete, or **add** anything under `app/modules/DACore/` (files **or** assets). **Never propose** a DACore edit. Implement in the current module.
7. **DACore exception:** only if the user **themselves** asks to edit DACore **and** confirms they know the next update **wipes** those changes. Then edit DACore for that request. Vague “fix the admin” is not enough. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §1.
8. **Read scope (MUST):** when programming a DACore-bound `<Target>`, **MAY read** only `app/modules/<Target>/`, `app/modules/DACore/` (read-only), framework core (read-only), and `AIRULES/` + examples. **MUST NOT** open, grep, glob, or explore `app/modules/<Sibling>/` for a look, Installation, list, or “how they did it.” **Exception:** the user **named** another module as the thing this work extends / supplements / listens to / Extender-targets — then that named folder + DACore + `<Target>` are allowed. Examples come from `AIRULES/examples/`, not a live sibling. Subagents MUST inherit this allow-list. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §1b.

## Temporary framework-author exception

The user is the author of the DotApp framework and explicitly authorizes the listener-route separation task.

For this task the agent may edit:

- `app/DotApp.php`
- `app/parts/Module.php`
- `app/parts/Listeners.php`
- `dotapper.php` only if its optimizer, generator, or help text requires updating
- framework tests needed for compatibility verification
- relevant AIRULES documentation
- the DACore optimizer overview under `app/modules/DACore/`

Required ownership:

- The main chat model implements and reviews `app/DotApp.php`, `app/parts/Module.php`, and `app/parts/Listeners.php`.
- Grok 4.6 High may inspect dotapper and implement the DACore optimizer overview.
- Grok 4.6 High MUST NOT edit the runtime core files owned by the main model.
- Agents MUST NOT edit the same file in parallel.

Required compatibility:

- Existing modules without `Listeners::initializeRoutes()` MUST continue using `Module::initializeRoutes()` for listener loading.
- Existing `modulesAutoLoader.php` files containing only `$modules` MUST continue to work.
- New optimized files MUST retain the legacy `$modules` map and add a separate listener-route map.
- Listener routes and module initialization routes MUST be matched independently.
- All matching listeners MUST register before any matching module performs full initialization.

This is a temporary exception for this named task only. All unrelated core files remain forbidden.

## Temporary framework-author exception — veto event API

The user explicitly authorizes the main chat model to implement a general veto event API in:

- `app/DotApp.php`
- `app/parts/Events.php`
- new `app/parts/Veto.php`
- framework tests under `app/tests/`
- related AIRULES documentation and examples

For this task, existing `trigger()` behavior MUST remain unchanged. `triggerWithVeto()` returns the first `Veto` object or `null`; all non-`Veto` listener returns remain ignored. Do not wire the API into DACore or another module action in this task.

## Temporary framework-author exception — installer insertion order

The user authorizes removing `ksort` / `krsort` from `app/parts/Installer.php` so `install()` runs `installer()` keys with `foreach` in PHP array order. `uninstall()` reverses that map (`array_reverse`). Do not sort installer keys with `ksort`, `uksort`, `krsort`, or `usort` (`1.0.10` sorts before `1.0.9`). Allowed: `app/parts/Installer.php`, `app/tests/` for this check, related AIRULES, and dropping any DACore sort override that existed only because of core `ksort`.

## Cursor credits (**MUST**)

When **planning** programming, **ASK** whether more expensive models may be used. If the user does not say yes: stay on **this** chat model. Subagents that write or plan code **MUST inherit** (`inherit`). **MUST NOT** silently spawn Opus / GPT-5 / thinking / xhigh / cloud / best-of-N. **Composer 2.5** is OK **only** for hunting a pile of files — **not** as the programmer. That hunt **MUST** stay in the current module + DACore + a user-named dependency (`AIRULES/00-AGENT-CONTRACT.md` §1b) — **MUST NOT** search `app/modules/*`. A bigger model is for a capability this one lacks (e.g. generate an image) — **ASK** if it costs extra. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2b.

## PHP version (**MUST**)

When **planning** programming, **ASK** whether to stay on **PHP 7.4+** (the DotApp default) or write for a higher version. If they do not name a higher version: **PHP 7.4+**. **MUST NOT** ship PHP 8+ syntax (`match`, `?->`, union/`mixed`, named args, constructor promotion, attributes, `enum`, `readonly`, `str_contains` / `str_starts_with` / `str_ends_with`) unless they said yes. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2i.

## Planning depth (**MUST** — law)

When they asked to **plan** a **new module**, a **first** major surface, or a **rewrite**, the plan **MUST** be extremely detailed: every `DACore:Menu@register` row (or `No menu`), every page, every tab, every control (what it does, default, persist). A long plan is correct. A bullet list of endpoints is a failed plan. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2k, `AIRULES/45-MODULE-PLANNING.md`.

## Finish gate (**MUST** — law)

After **every** code chunk (route, middleware, controller, query, form, view, JS) **and** before saying done: **MUST** grep this module — do not imagine the result. **MUST NOT** claim done if any row fails.

1. **CRC once** — count `crcCheck(` on that POST (middleware + `before` + `#DACore:AuthTest@CRC!` / `LoginAndCRC!` + action). Two calls = first **burns** the token. No CRC on GET. No CRC on `$request->upload()`. Action **MUST NOT** `crcCheck()` after a CRC prefix. New public controller/middleware PHPDoc **MUST** start with `CRCchecking —` naming that layer.
2. **IDs** — no plain `value="7"` / `data-id="7"` / `{{ var: $id }}` as an id. **MUST** `{{ enc(Shop.item.id): $id }}` unique `$key2`. Decrypt `false` → reject. Still `Auth::can` / ownership in PHP.
3. **Queries** — bindings only. No user input in SQL. `$qb->raw()`: every `?` is a placeholder (comments count).
4. **Inputs** — passwords/HTML/hashes from `$request->data(true)`. Persist re-checked in PHP (if the plan named step-up, PHP still refuses without a valid code). FE overlay is UX only.
5. **Middleware** — login `before` + handlers inside `Auth::isLogged()`. CRC prefix **XOR** action `crcCheck()`, never both. Rights via `#YourModule:Rights@check!` — **not** `#DACore:AuthTest@check!` (it ignores passed rights). Diff **MUST NOT** touch `app/modules/DACore/` unless the informed exception.
6. **Privilege / records** — no TOTP/QR/key in a read-only view; no mutate of a more privileged target; SQL owner scope; own password needs current; public noauth: **warn** about bots (CAPTCHA not MUST). Canonical: `AIRULES/11-AUTH-AND-CRYPTO.md` §11.
7. **Attacks** — `htmlspecialchars` before `{{ var: }}` (it does **not** escape) and `.text()` in JS; whitelist sort + writable columns; no request data in `header()` / redirect / `HttpHelper` URL; no `eval` / `exec` / `unserialize` / `include $x`; `random_bytes` for tokens, `hash_equals` for secrets; `throttle()` on public POST; no `getMessage()` / `var_dump` in the reply; no write to `dacore_*` / `users_rights*`. Catalogue + the 12-grep threat pass: `AIRULES/24-ATTACK-VECTORS.md`.
8. **Catch reported** — every `catch` **and** every `execute()` `$err` calls the module report helper: `Events::trigger('dotapp.catch', $p)` then `dotapp.catch.error` (aborted) / `dotapp.catch.info` (recovered). Fixed payload (`severity, module, source, operation, message, exception, code, file, line, time` + `context` ids/counts, `user_id`), no secrets/tokens/rights/bodies, helper and listener in **your** module, and the user still sees the toast. Canonical: `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9.
9. **Perf / readability** — no `->all()` on a growing table, no query/HTTP/rights check/log inside `foreach` (prefetch + keyed map), no `select('*')` on a list, no O(n²) or per-row array copy, every new `WHERE`/`ORDER BY` column indexed (composite: equality → range → sort), every index carries a comment naming its query, no duplicate of a library DACore ships, every public method a PHPDoc **purpose sentence** then tags (not tags-only), every logical step **`// Why:`**, page actions **`// About:`** / **`// Section:`**. Canonical + greps: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §8.
10. **Layout / UX-UI** — every new button has padding vs the parent (especially **bottom**); not flush to the card/page edge; centered or aligned to siblings. `pt-0` footers still have `pb-*` / CSS `padding-bottom`. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2f.
11. **HTML via Renderer** — when markup can be a template, it **MUST** be a template. Grep Controllers/Libraries for `$html .=` / `'<table` / `'<tr` / `'<div class=` / `*Html(` factories. A PHP HTML string is **only** for a named one-piece exception (`// Why:` + sandbox drop / `Page@paginate!` `<li>` / one tiny chip) — never a table, grid, tree, empty state, crumbs, or pager wrapper. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2j, `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1c.
12. **Hooks** — fire **`module.{lowercase_modulename}.{hook_name}.hook`** only when another module could log, show history, or sync (SMS/mail sent, payment, lockout) — **MUST NOT** on every save. Above `trigger()`: `Hook:` / `Why:` / `About:` / `Params:` / `Use:`. Same name in `app/modules/<ThisModule>/.hooks`. No secrets. No `trigger()` inside `foreach` of a growing list. DACore-bound: read `app/modules/DACore/.hooks` first. Canonical: `AIRULES/41-MODULE-HOOKS.md` §6.
13. **Extender** — judge first, not every method. Owner `exists()` + `call()`; ordinary result returns, only `isOriginal()` continues owner logic. Extender `extend()` belongs in `Listeners::register()` before Module initialization. Target listener routes; own Module routes or `[]`; controller string preferred. No `next()`, marker response, `.loaded` for initialize-time, Events, `$request`/secrets, or DACore patch. Canonical: `AIRULES/12-SERVICES.md` §10.
14. **PHP 7.4+** — unless the plan named a higher version: no `match`, `?->`, union/`mixed`, named args, constructor promotion, attributes, `enum`, `readonly`, `str_contains` / `str_starts_with` / `str_ends_with`. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2i.
15. **Read scope** — tool paths stayed in `<Target>` + DACore (+ a sibling **only** if the user named it as the extend/listen/Extender target). No copy of a sibling’s cards/CSS/chrome. Examples from `AIRULES/examples/`, not a live neighbour. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §1b.
16. **MySQL-safe DDL** — `Installation.php` / `ensureTable` has no `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS`. Probe then `CREATE`/`ALTER`. Canonical: `AIRULES/07-SCHEMA-AND-INSTALL.md` §0.

Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2c. Tick `AIRULES/17-CHECKLISTS.md` Finish gate.

## Visible outcome (**MUST** — law)

Every save / toggle / delete / form **MUST** tell the user what happened. Silent success and silent fail are bugs. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2d.

- **DACore admin:** **MUST** grep `app/modules/DACore/` read-only first (Notiflix.Notify / Confirm / Block, `$dotapp().toast()`, `dotapp.toasts.js`). Use the shell. Do **not** invent a second toast library. Outcome channel = **toast**.
- **Public site:** **you** build feedback. Field errors **preferred:** red input + message **on that field**. PHP returns `errors`. Persist still in PHP.
- Empty `.after()` is forbidden.

## Layout / UX-UI (**MUST** — law)

General UX/UI principles **MUST** be followed **at all costs**. After adding a button: check **padding vs the parent** (especially **below**), and place it deliberately (centered or same rhythm as siblings). A Save glued to the card edge is a **bug**. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2f, `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §8c.

## HTML via Renderer (**MUST** — law)

When markup **can** be a template, it **MUST** be a template. PHP prepares data. `Renderer` + `.view.php` / `.layout.php` produce HTML. **MUST NOT** concatenate tables, grids, empty states, pager chrome, trees, or crumbs in Controllers/Libraries. A PHP HTML string is **only** for a named one-piece exception (`// Why:` + sandbox drop / `Page@paginate!` `<li>` / one tiny chip). Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2j, `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1c.

## Non-negotiable syntax

- Routes: `Module:Controller@method!` (`!` = no DI parameters in the method).
- Controllers: `public static function`.
- **PHP 7.4+ (ASK in plan):** default language is PHP 7.4+. **ASK** whether to stay on 7.4+ or write for a higher version. No answer → 7.4+. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2i.
- **Module identity (ASK in plan):** for every new DACore-bound module, ask once for public name/purpose; installer preview as text-only, compact logo near heading, or wide banner; existing local asset + alt text; and optional landing/header placement. No preference → text-only, do not block. Installer image = optimised raster in **your** `about-assets/`, referenced from `about.php`; sidebar Remix `icon` is separate. No external image/SVG/tracker and no DACore patch. If this is a **pack** or a **host that picks packs** (CMS templates): **ASK** `extra1`…`extra5` tokens (`AIRULES/35-DACORE-INSTALL.md` §3c). Canonical: `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §8b, `AIRULES/35-DACORE-INSTALL.md` §3b–§3c.
- **Planning depth (MUST):** new module / first surface / rewrite — inventory every `Menu@register` row (or `No menu`), page, tab, and control in the plan. Length is OK. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2k, `AIRULES/45-MODULE-PLANNING.md`.
- Login-required / admin routes: **MUST** HTML `{prefixUrl}/{ModuleName}/…` + `Gate@login`. **POST API:** `/api/v1/auth|noauth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` / `@CRC!` at the start of `initialize()`; action **MUST NOT** `crcCheck()` again. Register handlers only inside `if (Auth::isLogged() === true) { … }`. Canonical: `AIRULES/03-MODULES-AND-ROUTING.md`, `AIRULES/32-DACORE-RIGHTS.md`.
- **Docs (MUST):** English for normal modules and DACore. For the explicitly authorized listener-route core task, comments in `app/DotApp.php`, `app/parts/Module.php`, `app/parts/Listeners.php`, and related `dotapper.php` changes may use natural Slovak without diacritics. Every public method in `Controllers/` and `Middleware/` **MUST** start PHPDoc with **`CRCchecking —`** (exact prefix/middleware such as `#DACore:AuthTest@LoginAndCRC!`, or `this action`, or `none` for GET/upload/helper) — then a **purpose sentence**, then `@param` / `@return` / `@throws` with meaning — tags-only (`@return array<string, mixed>`) is a bug. **MUST NOT** document prefix CRC and still `crcCheck()` in that method. Inline comments **MUST** use labels **`// Why:`** (every logical step), **`// About:`** (what this chunk is / what the record represents), **`// Section:`** (admin menu or route). **MUST NOT** restate the code, prompt-echo, omit the labels, or leave dead code / bare `TODO`. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §7, `AIRULES/08-FORMS-AND-SECURITY.md`.
- **Catch bus MUST:** every `catch` and every `execute()` `$err` reports `dotapp.catch` + `dotapp.catch.error|info` through **one** helper per module (listener exceptions propagate, so the helper wraps its own `trigger()` calls). Payload keys are fixed; secrets, tokens, rights blobs and request bodies **MUST NOT** be in it; nothing for this goes under `app/modules/DACore/`; a listener **MUST NOT** push a DACore notification per failure. Canonical: `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9.
- **Hooks MUST:** useful side-effects `Events::trigger('module.{lowercase_modulename}.{hook_name}.hook', $payload)` with the `Hook:`/`Why:`/`About:`/`Params:`/`Use:` block and the same name in `app/modules/<ThisModule>/.hooks`. **MUST NOT** fire on every save. Listen in **your** module after reading **their** `.hooks` — do not patch the owner. A **DACore-bound** module **MUST** read **`app/modules/DACore/.hooks` first** (catalog of events DACore already fires). Canonical: `AIRULES/41-MODULE-HOOKS.md` §6, sample `AIRULES/examples/EX-16-module-hooks.md`.
- **Extender:** owner returns an ordinary `call()` result and continues only for `isOriginal()`; `extend()` in `Listeners::register()`; target URLs in explicit listener routes; own Module routes or `[]`; controller string preferred. No `next()`, marker response, listener `['*']` just to attach, `.loaded` for initialize-time, duplicate, or DACore patch. Canonical: `AIRULES/12-SERVICES.md` §10, EX-17.
- DB: `DB::module("RAW")->q(function ($qb) { ... })->all()|first()|execute()`. **MUST** `execute($ok, $err)` — both callbacks. Persist in `try/catch`. **MUST NOT** put `?` in `$qb->raw()` unless it is a real binding — comments count (`COMMENT 'SMS?'` throws). Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md`.
- **Tables MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names, `dotapp_*`, or `dacore_*` for module data.
- **Installer DDL MUST be MySQL-safe:** probe first (`SHOW TABLES LIKE` / `information_schema` + `DATABASE()`), then `CREATE TABLE` / `ALTER TABLE`. **MUST NOT** `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS`. Helpers in **this** module — not DACore `SetupGuard`. `$qb->createTableIfNotExist()` is OK. Canonical: `AIRULES/07-SCHEMA-AND-INSTALL.md` §0, `AIRULES/00-AGENT-CONTRACT.md` §5 item 30.
- **Installer keys MUST** run in written `installer()` order (`foreach`). **MUST NOT** `ksort` / `uksort` / `krsort` / `usort` that map (`1.0.10` sorts before `1.0.9`). Uninstall is reverse of that order. Canonical: `AIRULES/07-SCHEMA-AND-INSTALL.md`, `AIRULES/00-AGENT-CONTRACT.md` §5 item 26.
- **Installer keys MUST be quoted text:** every `installer()` / `uninstaller()` key is `'1.0.0' =>` / `"1.0.0" =>` in the source file. **MUST NOT** `self::…`, `static::…`, class constants, variables, or any other expression as the **key** — DACore greps `Installation.php` as text, does not run PHP, and rejects the package (`Installation.php has no version keys`, version `0.0.0`). Constants belong **inside** the callback. Canonical: `AIRULES/35-DACORE-INSTALL.md` §2, `AIRULES/00-AGENT-CONTRACT.md` §5 item 27.
- **DACore zip MUST use the handbook packer:** copy `AIRULES/examples/EX-D09-dacore-pack-zip.php.txt` to the project root as `dacore-pack-zip.php`, run `php dacore-pack-zip.php {Module} {version}`, delete the `.php` copy. **MUST NOT** invent a packer or leave `dacore-pack-zip.php` in the repo. Canonical: `AIRULES/examples/EX-D09-dacore-pack-zip.md`, `AIRULES/35-DACORE-INSTALL.md` §5.
- Templates: `{{ var: $x }}`, `{{ if }}...{{ /if }}`, `{{ foreach }}...{{ /foreach }}` — **not** Blade `{{ $x }}` / `endif`. **VIEW = outer file:** `setView` + `setLayout` + `renderView()` inserts the layout at `{{ content }}` in the view (or `renderLayout()` / inject a string). **HTML via Renderer (LAW):** when it can be a template it **MUST** be; **MUST NOT** `$html .= '<table'` factories. PHP markup **only** for a named one-piece exception. User-visible strings **MUST** be product copy (a software company would ship it) — never prompt-echo / “this user can…”. Canonical: `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1b, §1c, §8.
- Forms: `<fo-rm>` + `{{ formName(handler) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** for real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop, paginate) **MUST** be `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. + **`/assets/dotapp/dotapp.js`** + PHP `crcCheck()` **once** (API prefix **or** action — never both) + `form()` for real forms. Sample: `AIRULES/examples/EX-01-secure-form-complete.md`. Row-action sample: `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- **Request MUST:** `$request->data(true)` / `$request->query(true)` = original. `$request->data()` is **protected** (`protect()`). **MUST** use original for passwords, HTML, hashes. **MUST** show every login failure. Canonical: `AIRULES/19-VALIDATION-AND-INPUT.md`.
- **Lists vs pickers:** growing result lists (users, logs, items, orders, messages) **MUST** paginate via `AIRULES/40-DACORE-LIST-PAGER.md` and use server-backed search where appropriate. A bounded one-value choice (installed modules, languages, statuses, backup target) **MUST** be native `<select>` or existing `$dotapp(el).dotSelect2()`; opening it shows choices without requiring an exact remembered name. **MUST NOT** use a bare text/search input, empty `datalist`, or custom typeahead for known choices. Only genuinely large remote choices use AJAX `dotSelect2` with initial results + server paging/search. Canonical: `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3, `AIRULES/40-DACORE-LIST-PAGER.md`.
- **Cheap I/O (MUST):** smallest load — `exists()` / `COUNT(*)` / `limit(1)` / only needed columns / one `join`. **MUST NOT** `->all()` then filter, N+1 in `foreach`, or `Config::db('cache')` for speed. Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §2.
- **Memory (MUST):** page anything that grows; keyed map + `isset()` instead of `in_array()` in a loop; no `array_merge` per iteration; `unset` the raw copy after mapping; stream files. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §1.
- **Indexes (MUST):** every FK + every `WHERE` / `JOIN` / `ORDER BY` column on a growing table; composite order **equality → range → sort**; leftmost prefix counts; no index duplicating a composite prefix; one comment line per index naming its query; a later index = a **new** `Installation.php` version guarded by `indexExists()`. Columns: realistic `VARCHAR`, `decimal` money, FK `bigInteger()->unsigned()` to match `id()`. **Your** tables only — never `dacore_*`. Canonical: `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` §3–§4.
- **Session MUST use DSM:** `DSM::use('Shop')->set/get/delete`. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: `AIRULES/20-CACHE-LOGGER-SESSION.md`, `AIRULES/examples/EX-10-cache-logger-session.md`.
- **Save checks MUST run in PHP.** Frontend modal/overlay/disabled button is UX only. Skipping the overlay **MUST** still fail on the server. Canonical: `AIRULES/08-FORMS-AND-SECURITY.md`. Step-up 2FA: **ASK** in the plan (default no). If yes, DACore installer modal + `$dotapp().twoFactor` `{ autoSubmit: true }` — `AIRULES/32-DACORE-RIGHTS.md` §6, `AIRULES/examples/EX-D10-stepup-2fa-modal.md`.
- **Files MUST use `$dotapp().uploadFile`.** **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()`. **MUST** reject `.php` / executables (extension + `finfo` MIME + headers); FE `accept=` is UX only. Canonical: `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md`.
- JS: `$dotapp` — **not** `$` / `$.ajax`. After a successful `fo-rm` / `load` **MUST** update the DOM from JSON (`html` / data) and a short toast — no `location.reload()`. **MUST** overlay the form/list until the request ends. **DACore admin:** Notiflix (preferred) **or** your module preloaders. **Public website:** you **MUST** build preloaders yourself (Notiflix is DACore-only). UX **MUST** work on desktop **and** mobile. **Public website nav:** overlay drawer from the left or right; lock page scroll while open; the drawer itself scrolls; contacts + compact search in the drawer unless large search is its own mobile section (`AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3). `redirectTo` only when leaving the page. 2FA boxes: **`$dotapp().twoFactor`**. Deletes: graphical confirm first (`Notiflix.Confirm` on admin) — never `alert()` / `window.confirm()`. DACore operators **MUST** keep 2FA on. A second 2FA prompt: **ASK** in the plan (default no); when named, installer modal + `{ autoSubmit: true }` (`AIRULES/32-DACORE-RIGHTS.md` §6, `AIRULES/examples/EX-D10-stepup-2fa-modal.md`). AI write tools: `ui_events` + `DACore.AI.UIEvent` on the matching page only (`AIRULES/34-DACORE-AI-TOOLS.md` §5).

## Debug (user: it doesn’t work)

**MUST** read `AIRULES/23-DEBUG-PLAYBOOK.md`. Grep `crcCheck` in **this module’s** `Middleware/`, `module.init.php` (`->before`), and the controller. Two calls on one request: the first **burns** the token. If you write CRC middleware, the action **MUST NOT** `crcCheck()` again. DACore: grep `AuthTest` — it ignores passed rights; do not patch DACore. Missing business event: open the owner’s `.hooks` (DACore-bound: **`app/modules/DACore/.hooks` first**), then grep `Events::trigger(` there (`AIRULES/41-MODULE-HOOKS.md` §6).

## Scaffolding

Prefer `php dotapper.php` generators. Run from project root. Put `--module=` **before** `--create-controller|model|middleware`.

## Deep docs

| Topic | File |
|-------|------|
| Contract | `AIRULES/00-AGENT-CONTRACT.md` |
| **Read scope (this module + DACore only)** | `AIRULES/00-AGENT-CONTRACT.md` §1b |
| CLI | `AIRULES/02-DOTAPPER-CLI.md` |
| Routing | `AIRULES/03-MODULES-AND-ROUTING.md` |
| Controllers | `AIRULES/04-CONTROLLERS-AND-RESPONSES.md` |
| Views | `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` (§1c = HTML via Renderer law; §8c = button padding / UX-UI layout law) |
| Database | `AIRULES/06-DATABASE.md` |
| Forms | `AIRULES/08-FORMS-AND-SECURITY.md` |
| Frontend | `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` (§3 = live UX + overlays; **pager law = 40**) |
| **List pager (HTML / live(el, e) / COUNT)** | `AIRULES/40-DACORE-LIST-PAGER.md` |
| Config/secrets | `AIRULES/10-CONFIG-AND-SECRETS.md` |
| Cache / session | `AIRULES/20-CACHE-LOGGER-SESSION.md` (DSM — never `$_SESSION`) |
| Antipatterns | `AIRULES/14-ANTIPATTERNS.md` |
| Checklists | `AIRULES/17-CHECKLISTS.md` (**Finish gate** = 00 §2c) |
| **Finish gate (after every chunk)** | `AIRULES/00-AGENT-CONTRACT.md` §2c |
| **HTML via Renderer (templates, not PHP factories)** | `AIRULES/00-AGENT-CONTRACT.md` §2j, `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1c |
| **Visible outcome (save/fail)** | `AIRULES/00-AGENT-CONTRACT.md` §2d |
| **Catch bus (`dotapp.catch` in every catch)** | `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md` §9 |
| **Event tracer (`dotapp.catchall` — core fires every trigger)** | `AIRULES/01-ARCHITECTURE.md`, `AIRULES/23-DEBUG-PLAYBOOK.md` §1c — listener in **your** module |
| **Debug / “it doesn’t work”** | `AIRULES/23-DEBUG-PLAYBOOK.md` (§1b = read the catch trail first) |
| **Attack vectors (law) + threat pass** | `AIRULES/24-ATTACK-VECTORS.md` (§11 = the 12 greps) |
| **Performance, indexes, PHPDoc purpose + Why/About/Section** | `AIRULES/25-PERFORMANCE-AND-CODE-QUALITY.md` (§3 indexes, §7 comments, §8 perf pass) |
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
| **List pager** | `AIRULES/40-DACORE-LIST-PAGER.md` |
| **Module hooks (`module.{mod}.{name}.hook` + `.hooks`)** | `AIRULES/41-MODULE-HOOKS.md` (sample: `AIRULES/examples/EX-16-module-hooks.md`) |
| **Dashboard widgets + settings panels** | `AIRULES/42-DACORE-UI-CONTRIBUTIONS.md` |
| **Outbound webhook drivers** | `AIRULES/43-DACORE-WEBHOOKS.md` |
| **Framework / DB / module backups** | `AIRULES/44-DACORE-BACKUPS.md` |
| **Outbound HTTPS/HMAC webhooks** | `AIRULES/43-DACORE-WEBHOOKS.md` |
| **User origin / custom login / shop accounts** | `AIRULES/42-DACORE-USER-ORIGIN.md` |
| **Extender (judge — not every method)** | `AIRULES/12-SERVICES.md` §10 (sample: `AIRULES/examples/EX-17-extender.md`) |
| **Planning depth (new module / first surface / rewrite)** | `AIRULES/00-AGENT-CONTRACT.md` §2k, `AIRULES/45-MODULE-PLANNING.md` |

## DACore rules (hard)

DACore is as sacred as framework core **by default**. It is updated as a package; **any edit or extra file inside it is wiped on update.**

- **Never** edit, patch, delete, or **add** anything under `app/modules/DACore/` unless the **informed exception** in `AIRULES/00-AGENT-CONTRACT.md` §1 applies
- **MUST NOT propose** a DACore edit. Put all new admin features in **the current module** (`app/modules/<YourModule>/`) — including **that** module’s assets
- Use only what DACore already exposes: `DotApp::call("DACore:…")`
- Never write directly to `dacore_menu` / `dacore_ai_tools` / `dacore_installations` / `dacore_modules` / `dacore_plugin_logs` / `dacore_settings` / `dacore_notifications` / `dacore_notifications_inbox` / `dacore_email_senders` / `dacore_email_templates` / `dacore_sms_senders` / `users_rights*`
- Render admin pages with `DACore:Page@withMenu!`
- **Active sidebar (MUST):** edit/detail URLs **MUST** keep the registered list/section leaf highlighted. Pass `withMenu` 7th `$currentFile` (the registered list URL) when the path is not under that leaf (`/Shop/users/4` vs `/Shop/users-list`). Walk-up already covers `/Shop/items/4` if the leaf is `/Shop/items`. **MUST NOT** register a menu row per edit URL. Canonical: `AIRULES/31-DACORE-MENU.md` Active sidebar.
- **MUST search DACore first** before a new JS/CSS library, `$dotapp().fn` widget, or page chrome: grep `app/modules/DACore/` (read-only: assets, vendor, views) and `app/modules/<YourModule>/assets/`. The base already has many subpages and libraries. If it exists, **use it** — do not fork or copy DACore files into your module. Write new code only when the search finds nothing, and only in **your** module. **MUST NOT** grep a sibling module for chrome or an example. Canonical: `AIRULES/33-DACORE-PAGES-AND-UI.md` “Search DACore first”, `AIRULES/00-AGENT-CONTRACT.md` §1b.
- **MUST read `app/modules/DACore/.hooks` first** when programming a DACore-bound module (new or existing): that file is the catalog of `module.dacore.*.hook` and `.veto` events. Subscribe in **your** `module.listeners.php`. Do not invent `module.dacore.*` names or patch DACore to “add a call”. Canonical: `AIRULES/41-MODULE-HOOKS.md` §6.
- **User origin (MUST):** users/email/session are global; origin is provenance, not a sandbox or tenant Auth store. A shop/custom identity flow **MUST** check `registerOrigin`, resolve the created id by bound lookup, `stampOrigin` + re-read exact token/id, use generic duplicate/foreign replies, and enforce exact origin after login, before/on/after 2FA, in every route gate, and in joined/bound list/write SQL. Mismatch → `Auth::logout()`. Never allow `dacore.legacy` in a custom gate: it is also the missing-profile/schema/read-error fallback. DACore `dacore_login` is its form allow-list only, not a fail-closed module boundary. **ASK** before listing another origin; no RCE, cross-origin IDOR or escalation. Canonical: `AIRULES/42-DACORE-USER-ORIGIN.md`.
- Prefer DACore widgets; **MUST** add module CSS/JS (`$css`/`$js`) when the shell has no equivalent (charts, ported UI). Classes `{lowercase_modulename}_*`. Match admin colors. Never patch DACore (unless the informed exception applies).
- Admin JS is **`$dotapp`**. jQuery may coexist for UI only. **Requests MUST** use `$dotapp().form` / `load` / bridge — never `$.ajax`. Porting jQuery **is** writing a new `$dotapp().fn` library: **ask**, then rewrite (do not wrap `$.fn`). If DACore already ships the widget, use it. See `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §4.C and `AIRULES/examples/EX-15-dotapp-js-library.md`.
- Guard routes with your own `#YourModule:Rights@check!` — `#DACore:AuthTest@check!` ignores passed rights
- Register menu / rights / AI tools in **your** `Installation.php`
- **Installer user groups (DACore 1.0.8+):** after `Rights@createRight!`, create immutable bundles with `DACore:Roles@createGroup!($name, $description, $creator, $groupid, [['module' => ..., 'rightname' => ...]])`. Stable identity is **`(creator, groupid)`**, never numeric role id; installer groups are `editable = 0`. Assign/remove/delete only with `Roles@assignGroup!` / `removeGroup!` / `deleteGroup!`; **MUST NOT** write `users_roles*` or `users_rights` directly. Canonical: `AIRULES/32-DACORE-RIGHTS.md` §1.
- Push inbox notifications with `DACore:Notifications@push` **on the event** — not from `Installation.php`, not every request (`AIRULES/37-DACORE-NOTIFICATIONS.md`)
- **Sending mail?** Read `AIRULES/38-DACORE-EMAIL.md` — do not invent SMTP
- **Sending SMS?** Read `AIRULES/39-DACORE-SMS.md` — do not invent a gateway
- If this module has a sidebar: own `type => 0` header (one is ideal). **ASK** before a new DACore module: shared nested (`0` → `2` → `1`, `withMenu` `$menuId` `''`) vs module-own. **No answer → shared nested.** Module-own **only** if the user explicitly chose it. From ~5 items, nest under `type => 2`. `menuid` starts with **your** module. Do not register “Return back”. An extension may use another module’s `parent`; uninstall deletes only **your** prefix (`AIRULES/31-DACORE-MENU.md`)
- **Your** modules: while coding use **`install.php`** and **live** init files. After a new migration, rename `installed_*_install.php` → `install.php`. User asks to zip a **DACore-bound** module (including create+zip): **MUST** copy `AIRULES/examples/EX-D09-dacore-pack-zip.php.txt` → `dacore-pack-zip.php`, run `php dacore-pack-zip.php {Module} {version}`, **delete** the `.php`. **MUST NOT** invent a packer. Zip has **`dainstall.php`**, **`init/`**, inert root stubs, **no** `install.php` — DACore **rejects** `install.php` and **never runs** Installation without `dainstall.php`. **MUST** include root **`.hooks`** when the module fires `module.{this}.*` hooks (`AIRULES/41-MODULE-HOOKS.md`). Working tree stays `install.php`. A non-DACore module: no zip. **MUST NOT** pack `app/modules/DACore/`. Canonical: `AIRULES/examples/EX-D09-dacore-pack-zip.md`, `AIRULES/00-AGENT-CONTRACT.md` §2e, `AIRULES/35-DACORE-INSTALL.md` §4–§5.
- **Installer/uninstaller failure (MUST):** callback `return` / `false` is ignored by the outer lifecycle. Every critical failure reports then throws a generic `RuntimeException`; otherwise DACore may accept a broken install or delete the module folder after partial cleanup. Check API returns, DB execute results, final installation marker, and earlier required markers. Uninstall deletes only this module’s menu prefix and throws to keep folder/registry retryable. Canonical: `AIRULES/35-DACORE-INSTALL.md` “failure propagation”.
- If asked to “just change DACore”: **do not jump in**. Implement in the current module. Edit DACore **only** after they confirm they accept the update wipe (`AIRULES/00-AGENT-CONTRACT.md` §1).

AIRULES is the single source of truth.
