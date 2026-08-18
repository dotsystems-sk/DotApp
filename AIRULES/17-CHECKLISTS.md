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
- [ ] **All module tables named `{lowercase_modulename}_*`** (Shop → `shop_items`) — never `items` or `dotapp_*`
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
- [ ] JS: `$dotapp().form` + `parseReply` + **MUST** block while in flight (**module preloaders**; desktop **and** mobile; remove overlay on success **and** error)
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

## Pre-commit / before “done”

- [ ] No core file modifications in the diff
- [ ] No Laravel/Blade/jQuery APIs introduced
- [ ] `--list-routes` or manual route review if routes changed
- [ ] Tests added/updated when logic is non-trivial (`--module=X --test`)
- [ ] Checklists above satisfied for touched areas
- [ ] Users/logs/items (or any accumulating list) shipped with **interactive AJAX** pager — not omitted, not a full-page `?page=` reload
- [ ] Lookup lists shipped with AJAX search (or the user declined); other lists were asked in the plan
- [ ] User-facing summary mentions AIRULES docs followed

## Red flags — stop and fix

- Diff touches `app/parts/**`
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
- List/form still clickable during `load()`; overlay not removed on the error path; missing module preloaders
- Custom OTP digit widget instead of `$dotapp().twoFactor`
- Delete via `alert()` / `window.confirm()` or with no graphical confirm
- Prompt-echo UI copy (“this user can…”, “as requested…”) instead of product language
- Growing list (users, logs, items, …) with **no pager**, or a pager that reloads via `<a href="?page=">` / `location.reload()` — both are incomplete
- Lookup list (articles, catalog, …) with **no search**, or search that filters `->all()` in JS / reloads the page
- `$_SESSION` / `session_start()` in module code — use `DSM::use('Shop')`
- JS overlay/modal as the only 2FA or save check — PHP must refuse without valid proof
- File/ZIP in `FormData` + `load()` / `<fo-rm>` — use `$dotapp().uploadFile`
- Upload accepts `.php` or trusts client MIME — reject in PHP (`finfo` + extension)
- `execute()` called with a single callback
- `->first()` used without a guard
- A return value is used without checking its failure form
- `Renderer::useCache(true)` or `Config::db('cache', true)` enabled (both broken)
- `Auth::hasRole()` / `Auth::logged()` used (non-functional)
