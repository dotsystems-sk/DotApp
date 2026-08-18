# 17 — Checklists

## Pre-flight (before coding)

- [ ] Identified target module name (or will create via dotapper)
- [ ] Read `00-AGENT-CONTRACT.md`
- [ ] Read task-specific AIRULES doc (views/DB/forms/JS)
- [ ] Confirmed edits stay in `app/config.php` and/or `app/modules/<Target>/`
- [ ] Will not edit `app/parts/`, `DotApp.php`, `dotapper.php`, other modules

## Scaffold checklist

- [ ] Ran `php dotapper.php --create-module=...` from project root
- [ ] Controllers/models/middleware created via `--module=... --create-*`
- [ ] `--module=` appeared **before** create flag
- [ ] Namespaces match `Dotsystems\App\Modules\{Name}\...`

## Routing / controller checklist

- [ ] Routes registered in `module.init.php` (or intentional listeners)
- [ ] Callable strings use `Module:Controller@method!`
- [ ] No DI type-hints when using `!`
- [ ] Methods are `public static`
- [ ] Params via `$request->matchData()`
- [ ] No named routes / Laravel group APIs invented

## Template checklist

- [ ] Variables: `{{ var: $x }}` only
- [ ] Closers: `{{ /if }}` `{{ /foreach }}` (not endif/endforeach)
- [ ] VIEW is the outer file; `setLayout` + `renderView()` fills `{{ content }}` in that view — or `renderLayout()` / inject a string ([05](05-VIEWS-TEMPLATES-ASSETS.md) §1b)
- [ ] Layouts via `{{ layout:... }}` / Renderer setLayout
- [ ] Assets via `/assets/modules/{Module}/...`
- [ ] Script `/assets/dotapp/dotapp.js` before module JS
- [ ] User-visible strings are product copy — not prompt-echo / “this user can…” ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8)

## Database checklist

- [ ] `DB::module("RAW")->q(...)->all()` (not `first()` unguarded)
- [ ] `first()` replaced with `all()` + `[0] ?? null`, or `exists()`
- [ ] **Every `execute()` has BOTH callbacks** (missing error callback = throws)
- [ ] `insert_id` read as `$exec['insert_id'] ?? $db->inserted_id()` (empty on cache hit)
- [ ] Bindings for all user values (`?` xor `:named`, never mixed)
- [ ] No `DB::table`, Eloquent, `getConnection`, `selectRaw`, chain `find`, `count()`
- [ ] Schema via `Installation.php` / SchemaBuilder (never `migrate()`)
- [ ] **All module tables named `{lowercase_modulename}_*`** (Shop → `shop_items`) — never `items`, `dotapp_*`, or `dacore_*`
- [ ] Transactions wrapped in `try/catch` with `rollback()`
- [ ] Growing lists (users, logs, items, orders) use `paginate()` on **first ship** — not `->all()` into the view; “few rows now” is not a skip ([06](06-DATABASE.md))

## Error-handling checklist (see 18)

- [ ] `Crypto::decrypt` result compared `=== false`
- [ ] `Cache::load` result compared `!== null`
- [ ] `HttpHelper::request` / `FastSearch::*` checked via `['success']`
- [ ] `Validator::validate` checked with `=== true`
- [ ] `$request->form(...)` has an error callback and `null`/`false` guards
- [ ] `Email::send` checked with `!== true` (returns an array of errors)
- [ ] `Auth::login` checked for `false` before array access
- [ ] AI / SchemaBuilder / raw DDL wrapped in `try/catch`
- [ ] Renderer output checked for `''` (missing view fails silently)
- [ ] No empty `catch {}` — failures are logged via `Logger::use()->error(...)`
- [ ] Client receives a structured error, never a raw exception message

## Secure form checklist (PREFERRED path)

- [ ] Markup uses `<fo-rm>` (not `f-form`, prefer over plain `<form>`+CSRF alone)
- [ ] `{{ formName(handler) }}` **MUST** sit **between** `<fo-rm>` and `</fo-rm>` (never after `</fo-rm>`)
- [ ] Page loads **`/assets/dotapp/dotapp.js`** before module JS (session keys!)
- [ ] JS: `$dotapp().form` + `parseReply` + **MUST** block while in flight (Notiflix preferred **or** module preloaders; desktop **and** mobile; remove overlay on success **and** error)
- [ ] Success **MUST** patch the DOM (`reply.html` / data) + short toast — no `location.reload()` while staying on the page ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-06](examples/EX-06-dotapp-js-boot.md))
- [ ] Row actions (toggle/delete/reorder/drag-and-drop) use `$dotapp().load()` + encrypted `data-*` — **not** one `<fo-rm>` per button
- [ ] PHP: `crcCheck()` then `form([...], "handler", ...)` then `ajaxReply`
- [ ] Followed `AIRULES/examples/EX-01-secure-form-complete.md` when implementing
- [ ] New / ported `$dotapp` libraries follow [09](09-DOTAPP-JS-AND-BRIDGE.md) §4 / [EX-15](examples/EX-15-dotapp-js-library.md) (`dotapp-register`, `fn()`, `this.load` — no `$.ajax`)
- [ ] 2FA code boxes use `$dotapp().twoFactor` — not a custom OTP widget ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-14](examples/EX-14-auth-and-2fa.md))
- [ ] Deletes use a graphical confirm dialog first — never `alert()` / `window.confirm()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] Accumulating lists have an **interactive AJAX** pager (`type="button"` + `$dotapp().load()`, overlay while in flight, patch rows **and** pager) — not missing, not `<a href="?page=">` / `location.reload()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] Lookup lists (articles, products, catalog, …) have **interactive AJAX search** (debounce, 3+ chars, SQL + `paginate()`) unless the user declined; other lists were **asked** in the plan — not JS-filter of `->all()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] List plan **asked** filters / sort / bulk / page size / DSM remember / CSV-if-it-fits; empty state + sticky header + match highlight shipped when required ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] File/ZIP uploads use **`$dotapp().uploadFile`** + `$request->upload()` — not `FormData` on `load()` / `<fo-rm>`. PHP rejects `.php` / executables (extension + `finfo` MIME + headers) ([09](09-DOTAPP-JS-AND-BRIDGE.md))

## Config / secrets checklist

- [ ] New installs: real `c_enc_key`, `rm_key`, `rmrcm_key`, unique `app.name`
- [ ] Module settings have fallbacks if unset
- [ ] App session state uses **`DSM::use('Shop')`** — not `$_SESSION` / `session_start()` ([20](20-CACHE-LOGGER-SESSION.md))
- [ ] Persist handlers re-check in **PHP** (2FA, rights, validation) — FE overlay/modal is not the gate ([08](08-FORMS-AND-SECURITY.md))
- [ ] Secrets not committed carelessly

## DACore checklist (when the task touches the admin)

- [ ] No file under `app/modules/DACore/` was modified, added, or deleted — **unless** the user **themselves** asked to edit DACore **and** confirmed the update wipe ([00](00-AGENT-CONTRACT.md) §1)
- [ ] Did **not** propose a DACore edit; new CSS/JS/views live in the **current** module’s assets
- [ ] No direct SQL on `dacore_*` or `users_rights*` tables (uninstall menu cleanup excepted)
- [ ] Admin routes prefixed with `Config::module("DACore","prefixUrl")`
- [ ] Routes guarded by your own `#YourModule:Rights@check!` (not `#DACore:AuthTest@check!`)
- [ ] Allow-lists include `dotapp.root`
- [ ] Page rendered via `DACore:Page@withMenu!`, body contains no `<html>`/`<head>`
- [ ] Shell assets (`dotapp.js`, dotgrid, colors.css, core.css, modals, Notiflix) not re-added
- [ ] Extra widgets (charts, ported controls) use **module** CSS/JS on `withMenu` `$css`/`$js` — classes `{lowercase_modulename}_*`, colors match the admin palette
- [ ] **Searched DACore first** (read-only `app/modules/DACore/` assets/vendor/views + your module) before writing a new library/widget; reused what exists ([33](33-DACORE-PAGES-AND-UI.md))
- [ ] Network calls use `$dotapp().form` / `load` / bridge — never `$.ajax` (jQuery UI widgets OK)
- [ ] After save/toggle on the same page: `reply.html` patched + Notiflix toast — no `location.reload()`
- [ ] In-flight: overlay on the form/list (Notiflix preferred **or** module preloaders); remove on success **and** error; no second tap/drag until done; works on desktop **and** mobile
- [ ] List row actions / drag-and-drop use `$dotapp().load()` + encrypted `data-*` — not one `<fo-rm>` per button
- [ ] Port of jQuery libraries: **searched DACore first**; user was **asked**; plugin was **rewritten** as `$dotapp().fn` (not a `$.fn` wrap). Playbook: 09 §4.C / EX-15. DACore widgets reused when they already exist.
- [ ] Simple forms **prefer** `<dot-col any="12" md="6" ldesktop="6">` and `ri ri-*` icons (custom layout OK when porting)
- [ ] Menu / rights / AI tools registered in `Installation.php` only
- [ ] If this module has a sidebar: own `type => 0` header (one is ideal; more only if needed). **Asked** shared vs module-own before a new module. Many items: `type => 2` groups **or** header + one entry + `withMenu` `$menuId`. `menuid` starts with **this** module. Uninstall deletes only that prefix — not a host module’s menu ([31](31-DACORE-MENU.md))
- [ ] Did **not** register a “Return back” row (DACore appends it on a branch `$menuId`)
- [ ] Trigger file is **`dainstall.php`** (not `install.php`) on **your** module under DACore; `init/` has current copies of `module.init.php` and `module.listeners.php` ([35](35-DACORE-INSTALL.md) §4–§6)
- [ ] Root `module.init.php` / `module.listeners.php` were **not** blanked unless the user asked to export
- [ ] **`app/modules/DACore/` was not given `dainstall.php` / `init/` / inert stubs** — those rules are for plug-in modules only
- [ ] `Menu@register` checked `!== true`; rights helpers checked `=== null`
- [ ] Inbox events use `DACore:Notifications@push` on the event (`!== true` checked) — not installer, not every request, not `INSERT` into `dacore_notifications*` ([37](37-DACORE-NOTIFICATIONS.md))
- [ ] AI tool `rights` non-empty and wildcard-free; `controller` ends with `!`
- [ ] AI handler signature `($data, $aiobj)` returning JSON with `result` + `message`
- [ ] Write AI tools that change on-screen data return `ui_events` (`name` = tool id); matching page listens `DACore.AI.UIEvent` and AJAX-refreshes — other pages ignore ([34](34-DACORE-AI-TOOLS.md) §5)
- [ ] `dacore_ai_tools` existence verified before registering tools
- [ ] Uninstaller removes tools, rights, prefixed menu rows and your tables
- [ ] Operators keep at least one 2FA method; your module cannot turn it off
- [ ] Dangerous admin actions re-prompt 2FA (`$dotapp().twoFactor`) and **PHP verifies the code** before persist — not the overlay, not `Auth::confirmTwoFactor` ([08](08-FORMS-AND-SECURITY.md), [32](32-DACORE-RIGHTS.md) §6)
- [ ] Deletes use a graphical confirm (`Notiflix.Confirm` or `$dotapp().modal`) — never `alert()` / `window.confirm()`
- [ ] Menu names, rights name/description, tool `description`, and page copy are product language — not prompt-echo ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8)

## Pre-commit / before “done”

- [ ] No core file modifications in the diff
- [ ] No `app/modules/DACore/` files in the diff (edit, add, or delete) — unless an informed, user-initiated DACore edit was confirmed ([00](00-AGENT-CONTRACT.md) §1)
- [ ] No Laravel/Blade/jQuery APIs introduced
- [ ] `--list-routes` or manual route review if routes changed
- [ ] Tests added/updated when logic is non-trivial (`--module=X --test`)
- [ ] Checklists above satisfied for touched areas
- [ ] Users/logs/items (or any accumulating list) shipped with **interactive AJAX** pager — not omitted, not a full-page `?page=` reload
- [ ] Lookup lists shipped with AJAX search (or the user declined); other lists were asked in the plan
- [ ] User-facing summary mentions AIRULES docs followed

## Red flags — stop and fix

- Diff touches `app/parts/**`
- Diff touches `app/modules/DACore/` without an informed, user-initiated ask ([00](00-AGENT-CONTRACT.md) §1)
- Agent proposed a DACore patch instead of implementing in the current module
- Template contains `{{ $var }}` or `@if`
- Code contains `DB::table` / Eloquent / `$this->db`
- Module table not prefixed `{lowercase_modulename}_*`
- Frontend uses `$('#...')` or `$.ajax`
- Frontend wraps `$.fn.plugin` instead of rewriting as `$dotapp().fn`
- Form uses invented `f-form`
- `{{ formName }}` placed after `</fo-rm>` or before `<fo-rm>`
- Handler skips `crcCheck` for DotApp JS POST
- Success path is `location.reload()` / empty `.after()` while staying on the page
- One `<fo-rm>` per row button (up/down/toggle/delete) or drag-and-drop via forms
- List/form still clickable during `load()`; overlay not removed on the error path; no preloaders because Notiflix was skipped
- Custom OTP digit widget instead of `$dotapp().twoFactor`
- Delete via `alert()` / `window.confirm()` or with no graphical confirm
- Prompt-echo UI copy (“this user can…”, “as requested…”) instead of product language
- Growing list (users, logs, items, …) with **no pager**, or a pager that reloads via `<a href="?page=">` / `location.reload()` — both are incomplete
- Lookup list (articles, catalog, …) with **no search**, or search that filters `->all()` in JS / reloads the page
- `$_SESSION` / `session_start()` in module code — use `DSM::use('Shop')`
- JS overlay/modal as the only 2FA or save check — PHP must refuse without valid proof
- File/ZIP in `FormData` + `load()` / `<fo-rm>` — use `$dotapp().uploadFile`
- Upload accepts `.php` or trusts client MIME — reject in PHP (`finfo` + extension)
- Write AI tool with no `ui_events` / `location.reload()` after AI chat write; wrong page refreshing another domain’s tool
- Dangerous DACore action without step-up 2FA; UI that turns off an operator’s 2FA
- New admin library/widget without grepping DACore (read-only) and the current module first
- DACore-bound **your-module** still has `install.php`, or no `dainstall.php` / `init/` copies
- `dainstall.php` / `init/` / inert stubs applied to `app/modules/DACore/` itself
- Root `module.init.php` blanked without the user asking to export
- `execute()` called with a single callback
- `->first()` used without a guard
- A return value is used without checking its failure form
- `Renderer::useCache(true)` or `Config::db('cache', true)` enabled (both broken)
- `Auth::hasRole()` / `Auth::logged()` used (non-functional)
