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

## Non-negotiable syntax

- Routes: `Module:Controller@method!` (`!` = no DI parameters in the method).
- Controllers: `public static function`.
- DB: `DB::module("RAW")->q(function ($qb) { ... })->all()|first()|execute()`.
- **Tables MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names, `dotapp_*`, or `dacore_*` for module data.
- Templates: `{{ var: $x }}`, `{{ if }}...{{ /if }}`, `{{ foreach }}...{{ /foreach }}` — **not** Blade `{{ $x }}` / `endif`. **VIEW = outer file:** `setView` + `setLayout` + `renderView()` inserts the layout at `{{ content }}` in the view (or `renderLayout()` / inject a string). User-visible strings **MUST** be product copy (a software company would ship it) — never prompt-echo / “this user can…”. Canonical: `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` §1b, §8.
- Forms: `<fo-rm>` + `{{ formName(handler) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** for real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop, paginate) **MUST** be `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. + **`/assets/dotapp/dotapp.js`** + PHP `crcCheck()` + `form()` for real forms. Sample: `AIRULES/examples/EX-01-secure-form-complete.md`. Row-action sample: `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- **Lists MUST paginate:** users, logs, items, orders, messages — any collection that can grow. Ship `paginate()` + an **interactive AJAX** pager in the first version (even if the table is empty today). **MUST NOT** dump `->all()`. **MUST NOT** change pages by reloading the admin shell (`<a href="?page=">`, `location.reload()`). Overlay the list while the request runs; patch rows **and** pager from JSON. **Search / list UX:** **ASK** when planning (search, filters, sort, bulk, page size, DSM remember, CSV only if it fits). Lookup lists **MUST** AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. No toast-undo after delete. Canonical: `AIRULES/06-DATABASE.md`, `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3, `AIRULES/33-DACORE-PAGES-AND-UI.md` §3.
- **Session MUST use DSM:** `DSM::use('Shop')->set/get/delete`. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: `AIRULES/20-CACHE-LOGGER-SESSION.md`, `AIRULES/examples/EX-10-cache-logger-session.md`.
- **Save checks MUST run in PHP.** Frontend modal/overlay/disabled button is UX only. Skipping the overlay **MUST** still fail on the server. Canonical: `AIRULES/08-FORMS-AND-SECURITY.md`. DACore 2FA: `AIRULES/32-DACORE-RIGHTS.md` §6.
- **Files MUST use `$dotapp().uploadFile`.** **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()`. **MUST** reject `.php` / executables (extension + `finfo` MIME + headers); FE `accept=` is UX only. Canonical: `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md`.
- JS: `$dotapp` — **not** `$` / `$.ajax`. After a successful `fo-rm` / `load` **MUST** update the DOM from JSON (`html` / data) and a short toast — no `location.reload()`. **MUST** overlay the form/list until the request ends. **DACore admin:** Notiflix (preferred) **or** your module preloaders. **Public website:** you **MUST** build preloaders yourself (Notiflix is DACore-only). UX **MUST** work on desktop **and** mobile. **Public website nav:** overlay drawer from the left or right; lock page scroll while open; the drawer itself scrolls; contacts + compact search in the drawer unless large search is its own mobile section (`AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §3). `redirectTo` only when leaving the page. 2FA boxes: **`$dotapp().twoFactor`**. Deletes: graphical confirm first (`Notiflix.Confirm` on admin) — never `alert()` / `window.confirm()`. DACore operators **MUST** keep 2FA on; dangerous admin actions **MUST** step-up 2FA (`AIRULES/32-DACORE-RIGHTS.md` §6). AI write tools: `ui_events` + `DACore.AI.UIEvent` on the matching page only (`AIRULES/34-DACORE-AI-TOOLS.md` §5).

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
| Checklists | `AIRULES/17-CHECKLISTS.md` |
| **DACore overview** | `AIRULES/30-DACORE-OVERVIEW.md` |
| DACore menu | `AIRULES/31-DACORE-MENU.md` |
| DACore rights | `AIRULES/32-DACORE-RIGHTS.md` |
| DACore pages / UI | `AIRULES/33-DACORE-PAGES-AND-UI.md` |
| DACore AI tools | `AIRULES/34-DACORE-AI-TOOLS.md` |
| DACore installer | `AIRULES/35-DACORE-INSTALL.md` |
| DACore quirks | `AIRULES/36-DACORE-KNOWN-ISSUES.md` |
| DACore notifications | `AIRULES/37-DACORE-NOTIFICATIONS.md` |

## DACore rules (hard)

DACore is as sacred as framework core **by default**. It is updated as a package; **any edit or extra file inside it is wiped on update.**

- **Never** edit, patch, delete, or **add** anything under `app/modules/DACore/` unless the **informed exception** in `AIRULES/00-AGENT-CONTRACT.md` §1 applies
- **MUST NOT propose** a DACore edit. Put all new admin features in **the current module** (`app/modules/<YourModule>/`) — including **that** module’s assets
- Use only what DACore already exposes: `DotApp::call("DACore:…")`
- Never write directly to `dacore_menu` / `dacore_ai_tools` / `dacore_installations` / `dacore_modules` / `dacore_plugin_logs` / `dacore_settings` / `dacore_notifications` / `dacore_notifications_inbox` / `users_rights*`
- Render admin pages with `DACore:Page@withMenu!`
- **MUST search DACore first** before a new JS/CSS library, `$dotapp().fn` widget, or page chrome: grep `app/modules/DACore/` (read-only: assets, vendor, views) and `app/modules/<YourModule>/assets/`. The base already has many subpages and libraries. If it exists, **use it** — do not fork or copy DACore files into your module. Write new code only when the search finds nothing, and only in **your** module. Canonical: `AIRULES/33-DACORE-PAGES-AND-UI.md` “Search DACore first”.
- Prefer DACore widgets; **MUST** add module CSS/JS (`$css`/`$js`) when the shell has no equivalent (charts, ported UI). Classes `{lowercase_modulename}_*`. Match admin colors. Never patch DACore (unless the informed exception applies).
- Admin JS is **`$dotapp`**. jQuery may coexist for UI only. **Requests MUST** use `$dotapp().form` / `load` / bridge — never `$.ajax`. Porting jQuery **is** writing a new `$dotapp().fn` library: **ask**, then rewrite (do not wrap `$.fn`). If DACore already ships the widget, use it. See `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` §4.C and `AIRULES/examples/EX-15-dotapp-js-library.md`.
- Guard routes with your own `#YourModule:Rights@check!` — `#DACore:AuthTest@check!` ignores passed rights
- Register menu / rights / AI tools in **your** `Installation.php`
- Push inbox notifications with `DACore:Notifications@push` **on the event** — not from `Installation.php`, not every request (`AIRULES/37-DACORE-NOTIFICATIONS.md`)
- If this module has a sidebar: own `type => 0` header (one is ideal). **ASK** before a new DACore module: shared full menu vs module-own (`withMenu` `$menuId`). From ~5 items, group with `type => 2` or use header + **one** entry. `menuid` starts with **your** module. Do not register “Return back”. An extension may use another module’s `parent`; uninstall deletes only **your** prefix (`AIRULES/31-DACORE-MENU.md`)
- **Your** modules that work **under** DACore: **`dainstall.php`** (never `install.php`). Keep `init/module.init.php` and `init/module.listeners.php` in sync with the live files. Blank the root init files **only** when the user asks to export for the DACore installer. **MUST NOT** apply this to `app/modules/DACore/` itself (do not rename DACore’s installer, do not add `init/` there, do not blank DACore’s `module.init.php`). Canonical: `AIRULES/35-DACORE-INSTALL.md` §4–§6.
- If asked to “just change DACore”: **do not jump in**. Implement in the current module. Edit DACore **only** after they confirm they accept the update wipe (`AIRULES/00-AGENT-CONTRACT.md` §1).

AIRULES is the single source of truth.
