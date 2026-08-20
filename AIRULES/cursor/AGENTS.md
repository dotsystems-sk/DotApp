# AGENTS.md — DotApp

You are working on a **DotApp PHP** project (not Laravel/Symfony/CodeIgniter).

## Before any edit

1. Read `AIRULES/00-AGENT-CONTRACT.md`.
2. Follow the entire `AIRULES/` knowledge base.
3. Edit **only** `app/config.php` and `app/modules/<TargetModule>/`.
4. **Never** edit `app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, or other modules.

## Cursor credits (**MUST**)

When **planning** programming, **ASK** whether more expensive models may be used. If the user does not say yes: stay on **this** chat model. Subagents that write or plan code **MUST inherit** (`inherit`). **MUST NOT** silently spawn Opus / GPT-5 / thinking / xhigh / cloud / best-of-N. **Composer 2.5** is OK **only** for hunting a pile of files — **not** as the programmer. A bigger model is for a capability this one lacks (e.g. generate an image) — **ASK** if it costs extra. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2b.

## Finish gate (**MUST** — law)

After **every** code chunk (route, middleware, controller, query, form, view, JS) **and** before saying done: **MUST** grep this module — do not imagine the result. **MUST NOT** claim done if any row fails.

1. **CRC once** — count `crcCheck(` on that POST (middleware + `before` + action). Two calls = first **burns** the token. No CRC on GET. No CRC on `$request->upload()`.
2. **IDs** — no plain `value="7"` / `data-id="7"` / `{{ var: $id }}` as an id. **MUST** `{{ enc(Shop.item.id): $id }}` unique `$key2`. Decrypt `false` → reject. Still `Auth::can` / ownership in PHP.
3. **Queries** — bindings only. No user input in SQL. `$qb->raw()`: every `?` is a placeholder (comments count).
4. **Inputs** — passwords/HTML/hashes from `$request->data(true)`. Persist re-checked in PHP. FE overlay is UX only.
5. **Middleware** — login `before` + handlers inside `Auth::isLogged()`. CRC prefix **XOR** action `crcCheck()`, never both. No overlapping CRC `before` hooks.
6. **Privilege / records** — no TOTP/QR/key in a read-only view; no mutate of a more privileged target; SQL owner scope; own password needs current; public noauth: **warn** about bots (CAPTCHA not MUST). Canonical: `AIRULES/11-AUTH-AND-CRYPTO.md` §11.
7. **Attacks** — `htmlspecialchars` before `{{ var: }}` (it does **not** escape) and `.text()` in JS; whitelist sort + writable columns; no request data in `header()` / redirect / `HttpHelper` URL; no `eval` / `exec` / `unserialize` / `include $x`; `random_bytes` for tokens, `hash_equals` for secrets; `throttle()` on public POST; no `getMessage()` / `var_dump` in the reply. Catalogue + the 12-grep threat pass: `AIRULES/24-ATTACK-VECTORS.md`.

Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2c. Tick `AIRULES/17-CHECKLISTS.md` Finish gate.

## Visible outcome (**MUST** — law)

Every save / toggle / delete / form **MUST** tell the user what happened. Silent success and silent fail are bugs. Canonical: `AIRULES/00-AGENT-CONTRACT.md` §2d.

- **Field errors (preferred on public FE + BE):** PHP returns `errors` keyed by field. JS marks that input (red/invalid) **and** shows the message **on the field** (where + what).
- **Success / non-field fail:** **your** module toast / status node + `reply.message`. Notiflix does not exist here. Never `alert()`.
- Empty `.after()` is forbidden.

## Non-negotiable syntax

- Routes: `Module:Controller@method!` (`!` = no DI parameters in the method).
- Controllers: `public static function`.
- Login-required routes: **MUST** prefix `/{ModuleName}/…` (subtree if the module has public pages). Cover HTML with `Router::before([$area, $area . '/*'], '#Shop:Gate@login!')` (403). **POST API:** `/api/v1/auth|noauth/{Module}/…` + `Gate@loginAndCrc` / `Gate@crc` at the start of `initialize()`; action **MUST NOT** `crcCheck()` again. Register handlers only inside `if (Auth::isLogged() === true) { … }`. Canonical: `AIRULES/03-MODULES-AND-ROUTING.md`.
- Comments: English, short **why** at traps — not every line, not prompt-echo.
- DB: `DB::module("RAW")->q(function ($qb) { ... })->all()|first()|execute()`. **MUST** `execute($ok, $err)` — both callbacks. Persist in `try/catch`. **MUST NOT** put `?` in `$qb->raw()` unless it is a real binding — comments count (`COMMENT 'SMS?'` throws). Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/18-ERROR-HANDLING-AND-RETURN-VALUES.md`.
- **Tables MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names or `dotapp_*` for module data.
- Templates: `{{ var: $x }}`, `{{ if }}...{{ /if }}`, `{{ foreach }}...{{ /foreach }}` — **not** Blade `{{ $x }}` / `endif`. **VIEW = outer file:** `setView` + `setLayout` + `renderView()` inserts the layout at `{{ content }}` in the view (or `renderLayout()` / inject a string). User-visible strings **MUST** be product copy — never prompt-echo / “this user can…”. Canonical: `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1b, §8.
- Forms: `<fo-rm>` + `{{ formName(handler) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** for real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop, paginate) **MUST** be `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. + **`/assets/dotapp/dotapp.js`** + PHP `crcCheck()` **once** (API prefix **or** action — never both) + `form()` for real forms. Sample: `AIRULES/examples/EX-01-secure-form-complete.md`. Row-action sample: `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- **Request MUST:** `$request->data(true)` / `$request->query(true)` = original. `$request->data()` is **protected** (`protect()`). **MUST** use original for passwords, HTML, hashes. **MUST** show every login failure. Canonical: `AIRULES/19-VALIDATION-AND-INPUT.md`.
- **Lists MUST paginate:** users, logs, items, orders, messages — any collection that can grow. Ship `paginate()` + an **interactive AJAX** pager in the first version (even if the table is empty today). **MUST NOT** dump `->all()`. **MUST NOT** change pages by reloading the site (`<a href="?page=">`, `location.reload()`). Overlay the list while the request runs; patch rows **and** pager from JSON. **Search / list UX:** **ASK** when planning (search, filters, sort, bulk, page size, DSM remember, CSV only if it fits). Lookup lists **MUST** AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. No toast-undo after delete. Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3, `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- **Cheap I/O (MUST):** smallest load — `exists()` / `COUNT(*)` / `limit(1)` / only needed columns / one `join`. **MUST NOT** `->all()` then filter, N+1 in `foreach`, or `Config::db('cache')` for speed. Canonical: `AIRULES/06-DATABASE.md`.
- **Session MUST use DSM:** `DSM::use('Shop')->set/get/delete`. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: `AIRULES/20-CACHE-LOGGER-SESSION.md`, `AIRULES/examples/EX-10-cache-logger-session.md`.
- **Save checks MUST run in PHP.** Frontend modal/overlay/disabled button is UX only. Skipping the overlay **MUST** still fail on the server. Canonical: `AIRULES/08-FORMS-AND-SECURITY.md`.
- **Files MUST use `$dotapp().uploadFile`.** **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()`. **MUST** reject `.php` / executables (extension + `finfo` MIME + headers); FE `accept=` is UX only. Canonical: `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md`.
- JS: `$dotapp` — **not** `$` / `$.ajax`. After a successful `fo-rm` / `load` **MUST** update the DOM from JSON (`html` / data) and a short toast — no `location.reload()`. **MUST** overlay the form/list with **your module preloaders** until the request ends. UX **MUST** work on desktop **and** mobile. **Public website nav:** overlay drawer from the left or right; lock page scroll while open; the drawer itself scrolls; contacts + compact search in the drawer unless large search is its own mobile section (`AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3). `redirectTo` only when leaving the page. 2FA boxes: **`$dotapp().twoFactor`**. Deletes: graphical confirm first — never `alert()` / `window.confirm()`.

## Debug (user: it doesn’t work)

**MUST** read `AIRULES/23-DEBUG-PLAYBOOK.md`. Grep `crcCheck` in **this module’s** `Middleware/`, `module.init.php` (`->before`), and the controller. Two calls on one request: the first **burns** the token. If you write CRC middleware, the action **MUST NOT** `crcCheck()` again.

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
| **Debug / “it doesn’t work”** | `AIRULES/23-DEBUG-PLAYBOOK.md` |
| **Attack vectors (law) + threat pass** | `AIRULES/24-ATTACK-VECTORS.md` (§11 = the 12 greps) |

AIRULES is the single source of truth.
