# 17 — Checklists

## Pre-flight (before coding)

- [ ] Identified target module name (or will create via dotapper)
- [ ] Read `00-AGENT-CONTRACT.md`
- [ ] Read task-specific AIRULES doc (views/DB/forms/JS)
- [ ] Confirmed edits stay in `app/config.php` and/or `app/modules/<Target>/`
- [ ] Will not edit `app/parts/`, `DotApp.php`, `dotapper.php`, other modules
- [ ] **Cursor credits:** asked whether more expensive models may be used; otherwise parent/`inherit` only. Composer 2.5 = file hunt, not the coder ([00](00-AGENT-CONTRACT.md) §2b)
- [ ] **Finish gate:** will grep after **every** code chunk (CRC once, enc IDs, bound SQL, inputs, middleware / AuthTest) — [00](00-AGENT-CONTRACT.md) §2c

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
- [ ] Login-required / admin routes: `{prefixUrl}/{ModuleName}/…` + `Router::before([$admin, $admin . '/*'], '#Shop:Gate@login!')` (403 `Response`); handlers **only** inside `if (Auth::isLogged() === true)` — those pages **MUST NEVER** show to anonymous users ([03](03-MODULES-AND-ROUTING.md), [32](32-DACORE-RIGHTS.md))
- [ ] Trap-prone spots have a short **English why** comment — not every line
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
- [ ] `$qb->raw()` has no `?` except real bindings (not in comments / `COMMENT '…'`) ([06](06-DATABASE.md))
- [ ] After a new version, `installed_*_install.php` renamed back to `install.php` ([07](07-SCHEMA-AND-INSTALL.md))
- [ ] **All module tables named `{lowercase_modulename}_*`** (Shop → `shop_items`) — never `items`, `dotapp_*`, or `dacore_*`
- [ ] Transactions wrapped in `try/catch` with `rollback()`
- [ ] Growing lists (users, logs, items, orders) use `paginate()` on **first ship** — not `->all()` into the view; “few rows now” is not a skip ([06](06-DATABASE.md))
- [ ] Cheap I/O: `exists()` / `COUNT(*)` / `limit(1)` / only needed columns / no N+1 in `foreach` ([06](06-DATABASE.md))

## Error-handling checklist (see 18)

- [ ] `Crypto::decrypt` result compared `=== false`
- [ ] `Cache::load` result compared `!== null`
- [ ] `HttpHelper::request` / `FastSearch::*` checked via `['success']`
- [ ] `Validator::validate` checked with `=== true`
- [ ] `$request->form(...)` has an error callback and `null`/`false` guards
- [ ] `Email::send` checked with `!== true` (returns an array of errors)
- [ ] `Auth::login` checked for `false` before array access; login/install **shows** every failure (`crcCheck`, `form()` `null`/`false`, `false` login)
- [ ] Passwords / HTML / hashes from `$request->data(true)` — not `$request->data()` ([19](19-VALIDATION-AND-INPUT.md))
- [ ] AI / SchemaBuilder / raw DDL wrapped in `try/catch`
- [ ] Persist / save handlers wrapped in `try/catch` (`\Throwable`) — log + structured reply, never leak `$e->getMessage()`
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
- [ ] PHP: `crcCheck()` **once** (API prefix **or** action) then `form([...], "handler", ...)` then `ajaxReply` — **never both** ([08](08-FORMS-AND-SECURITY.md), [32](32-DACORE-RIGHTS.md))
- [ ] Passwords / HTML from `$request->data(true)` — not `$request->data()` ([19](19-VALIDATION-AND-INPUT.md))
- [ ] Failures show `reply.message` (including `crcCheck` / `form()` reject / `Auth::login === false`)
- [ ] Followed `AIRULES/examples/EX-01-secure-form-complete.md` when implementing
- [ ] New / ported `$dotapp` libraries follow [09](09-DOTAPP-JS-AND-BRIDGE.md) §4 / [EX-15](examples/EX-15-dotapp-js-library.md) (`dotapp-register`, `fn()`, `this.load` — no `$.ajax`)
- [ ] 2FA code boxes use `$dotapp().twoFactor` — not a custom OTP widget ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-14](examples/EX-14-auth-and-2fa.md))
- [ ] Deletes use a graphical confirm dialog first — never `alert()` / `window.confirm()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] Accumulating lists have an **interactive AJAX** pager (`type="button"` + `$dotapp().load()`, overlay while in flight, patch rows **and** pager) — not missing, not `<a href="?page=">` / `location.reload()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] Lookup lists (articles, products, catalog, …) have **interactive AJAX search** (debounce, 3+ chars, SQL + `paginate()`) unless the user declined; other lists were **asked** in the plan — not JS-filter of `->all()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] List plan **asked** filters / sort / bulk / page size / DSM remember / CSV-if-it-fits; empty state + sticky header + match highlight shipped when required ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
- [ ] File/ZIP uploads use **`$dotapp().uploadFile`** + `$request->upload()` — not `FormData` on `load()` / `<fo-rm>`. PHP rejects `.php` / executables (extension + `finfo` MIME + headers) ([09](09-DOTAPP-JS-AND-BRIDGE.md))
- [ ] **Public website:** mobile nav is a L/R overlay drawer; page behind does not scroll while open; the drawer list scrolls; contacts + compact search live in the drawer unless large search is its own mobile section ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)

## Config / secrets checklist

- [ ] New installs: real `c_enc_key`, `rm_key`, `rmrcm_key`, unique `app.name`
- [ ] Module settings have fallbacks if unset
- [ ] App session state uses **`DSM::use('Shop')`** — not `$_SESSION` / `session_start()` ([20](20-CACHE-LOGGER-SESSION.md))
- [ ] Persist handlers re-check in **PHP** (2FA, rights, validation) — FE overlay/modal is not the gate ([08](08-FORMS-AND-SECURITY.md))
- [ ] Privilege / records: no secret in read-only views; no escalate; SQL owner scope; own password needs current; public noauth **warned** for bots ([11](11-AUTH-AND-CRYPTO.md) §11)
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
- [ ] Edit/detail admin pages keep the list/section leaf active: `withMenu` 7th `$currentFile` when the URL is not under that leaf — no extra menu row per edit URL ([31](31-DACORE-MENU.md))
- [ ] Trigger while coding is **`install.php`** + live root init files; `installed_*` renamed back after a new version ([07](07-SCHEMA-AND-INSTALL.md), [35](35-DACORE-INSTALL.md) §4)
- [ ] Zip / `dainstall.php` / `init/` **only** for a **DACore-bound** module and only when the user asked to pack; working tree restored ([35](35-DACORE-INSTALL.md) §4–§5)
- [ ] Root init files were **not** blanked unless packing a zip
- [ ] **`app/modules/DACore/` was not given `dainstall.php` / `init/` / inert stubs**
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

## Debug checklist (user: “it doesn’t work”)

Canonical: [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) (DACore hunts = §7).

- [ ] Grepped `crcCheck` in **this module**: `Middleware/`, `module.init.php` (`->before` / `Middleware::`), Controllers, listeners
- [ ] Counted `crcCheck()` on the failing route — **not** middleware + controller (first call burns the token)
- [ ] If this module’s middleware calls `crcCheck()`, the action does **not**
- [ ] Passwords/HTML from `$request->data(true)`
- [ ] `form()` error callback + `null`/`false` guarded; JS shows `reply.message`
- [ ] Upload endpoints do **not** `crcCheck()`
- [ ] Admin routes use `#Shop:Rights@check!` — not `#DACore:AuthTest@check!` as a rights guard
- [ ] Did **not** “fix” it by editing `app/modules/DACore/`

## Attack-surface checklist (canonical: [24](24-ATTACK-VECTORS.md))

Tick only the rows for the surface you touched.

- [ ] **Echo:** every user/DB string escaped in PHP before the view — `{{ var: }}` does **not** escape; JS uses `.text()`, not `.html()` ([24](24-ATTACK-VECTORS.md) §1)
- [ ] **SQL:** bindings only; sort column + direction from a whitelist; insert/update column whitelist (no posted `right` / `user_id` / `price`) ([24](24-ATTACK-VECTORS.md) §1–§2)
- [ ] **Channels:** no input in `header()` / `Response::redirect` / mail headers; `HttpHelper` URL not from the request ([24](24-ATTACK-VECTORS.md) §2)
- [ ] **No interpreters on input:** no `eval` / `exec` / `system` / `unserialize` / `include $x` / user-named callable ([24](24-ATTACK-VECTORS.md) §1)
- [ ] **Identity:** token rotated after login; secrets via `hash_equals`; failure messages do not enumerate users; reset token `random_bytes` + hashed + single use + TTL; operator 2FA on + step-up on dangerous actions ([24](24-ATTACK-VECTORS.md) §3, [32](32-DACORE-RIGHTS.md) §6)
- [ ] **Access:** `Auth::can` in the action; rights via **your** `Rights@check` (not `AuthTest@check`); owner predicate in the query; no escalation; no secret behind a read-only right ([24](24-ATTACK-VECTORS.md) §4)
- [ ] **Headers:** `nosniff`, frame protection, `no-store` on admin pages, `rel="noopener"` on `target="_blank"`, no wildcard CORS with credentials ([24](24-ATTACK-VECTORS.md) §5)
- [ ] **Files:** extension + `finfo` MIME + header check, no path built from input, size/count caps, non-executing directory ([24](24-ATTACK-VECTORS.md) §6)
- [ ] **Abuse:** public POST throttled; list paginated with a page-size cap; notifications pushed on the event only; bot **warning** given for public `noauth` ([24](24-ATTACK-VECTORS.md) §7)
- [ ] **Leaks:** generic error message + log; no `var_dump` / `print_r` / `console.log(payload)`; no secrets, rights, or PII in `ajaxReply` or logs ([24](24-ATTACK-VECTORS.md) §8)
- [ ] **Crypto:** `Crypto` facade with a unique `$key2`, `random_bytes` for tokens, `hash_hmac` + `hash_equals` for signatures ([24](24-ATTACK-VECTORS.md) §9)
- [ ] **Third party / AI:** DACore searched first, then self-hosted + pinned in **your** module; model output never executed or echoed raw; AI write tools re-check rights; webhook signature verified ([24](24-ATTACK-VECTORS.md) §10)
- [ ] **Diff:** nothing under `app/modules/DACore/`; no direct write to `dacore_*` / `users_rights*` ([24](24-ATTACK-VECTORS.md) §0)

## Finish gate (LAW — [00](00-AGENT-CONTRACT.md) §2c)

**MUST** after **every** code chunk. **MUST NOT** claim done until every applicable row was actually grepped — not imagined.

- [ ] Grepped `crcCheck` in **this module**: `Middleware/`, `module.init.php` (`->before` / `Middleware::` / `#DACore:AuthTest@CRC!` / `LoginAndCRC!`), Controllers — **one** call per POST (API prefix **XOR** action). Not on GET/HTML login `before`. Not on `$request->upload()`. Action does **not** `crcCheck()` after a CRC prefix
- [ ] No plain record IDs in HTML/JSON (`value="7"`, `data-id="7"`, `{{ var: $id }}` as an id) — `{{ enc(Shop.item.id): $id }}` unique `$key2`; decrypt `=== false` rejected; PHP still `Auth::can` / ownership ([11](11-AUTH-AND-CRYPTO.md) §8)
- [ ] Privilege / records grepped: secrets not in read-only views; SQL has owner (or can on that row); no escalate; public noauth bot **warning** if applicable ([11](11-AUTH-AND-CRYPTO.md) §11)
- [ ] Queries use bindings; no user input in SQL strings; `$qb->raw()` has no `?` except real bindings ([06](06-DATABASE.md))
- [ ] Passwords / HTML / hashes from `$request->data(true)`; persist re-checked in **PHP** (rights, validation, step-up 2FA) — FE overlay is not the gate ([19](19-VALIDATION-AND-INPUT.md), [08](08-FORMS-AND-SECURITY.md), [32](32-DACORE-RIGHTS.md) §6)
- [ ] Middleware vs action: no double CRC; login `before` + handlers **inside** `Auth::isLogged()`; no CRC on a GET gate; rights via `#YourModule:Rights@check!` — **not** `#DACore:AuthTest@check!` ([03](03-MODULES-AND-ROUTING.md), [32](32-DACORE-RIGHTS.md))
- [ ] **Visible outcome:** every save/toggle/delete shows success **and** fail. **Admin:** grepped DACore, then **toast** (Notiflix / `$dotapp().toast()`). **Public:** mark the wrong field (red + message on the input). Never silent `.after()` ([00](00-AGENT-CONTRACT.md) §2d)
- [ ] **Threat pass** run on this chunk — the 12 greps in [24](24-ATTACK-VECTORS.md) §11 (injection, header/redirect, `eval`/`exec`/`unserialize`, upload checks, rate limit, leaked `getMessage()` / `var_dump`, bot warning, no `dacore_*` write)
- [ ] Touched-area checklists above are satisfied (forms, lists, DSM, files, templates, DACore, …)
- [ ] No core file modifications in the diff
- [ ] No `app/modules/DACore/` files in the diff (edit, add, or delete) — unless an informed, user-initiated DACore edit was confirmed ([00](00-AGENT-CONTRACT.md) §1)
- [ ] No Laravel/Blade/jQuery APIs introduced
- [ ] `--list-routes` or manual route review if routes changed
- [ ] Tests added/updated when logic is non-trivial (`--module=X --test`)
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
- Handler skips `crcCheck` for DotApp JS POST, **or** calls `crcCheck()` twice (middleware + controller)
- Login-only / admin page reachable while anonymous (route registered outside `Auth::isLogged()`, or no prefix `Gate@login` 403)
- `crcCheck()` on a **GET**/HTML `Gate@login` `before` (GET has no CRC); **or** `crcCheck()` **again** after `#DACore:AuthTest@CRC!` / `LoginAndCRC!` / `check`
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
- DACore-bound **your-module** uses `dainstall.php` / inert root **while still coding** (that is zip-only)
- New `Installation.php` version left as `installed_*_install.php` (next load will not run it)
- `dainstall.php` / `init/` / inert stubs applied to `app/modules/DACore/` itself
- Root `module.init.php` blanked without the user asking to export
- `execute()` called with a single callback
- `->first()` used without a guard
- A return value is used without checking its failure form
- Claiming done / shipping a chunk without running the finish gate ([00](00-AGENT-CONTRACT.md) §2c)
- Silent save / empty `.after()` / admin without a DACore toast / public field error without marking the input ([00](00-AGENT-CONTRACT.md) §2d)
- `Renderer::useCache(true)` or `Config::db('cache', true)` enabled (both broken)
- `Auth::hasRole()` / `Auth::logged()` used (non-functional)
