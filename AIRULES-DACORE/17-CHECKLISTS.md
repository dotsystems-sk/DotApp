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

## Database checklist

- [ ] `DB::module("RAW")->q(...)->all()` (not `first()` unguarded)
- [ ] `first()` replaced with `all()` + `[0] ?? null`, or `exists()`
- [ ] **Every `execute()` has BOTH callbacks** (missing error callback = throws)
- [ ] `insert_id` read as `$exec['insert_id'] ?? $db->inserted_id()` (empty on cache hit)
- [ ] Bindings for all user values (`?` xor `:named`, never mixed)
- [ ] No `DB::table`, Eloquent, `getConnection`, `selectRaw`, chain `find`, `count()`
- [ ] Schema via `Installation.php` / SchemaBuilder (never `migrate()`)
- [ ] Transactions wrapped in `try/catch` with `rollback()`

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
- [ ] `{{ formName(handler) }}` inside the fo-rm
- [ ] Page loads **`/assets/dotapp/dotapp.js`** before module JS (session keys!)
- [ ] JS: `$dotapp().form` + `parseReply` (+ optional `loading`/`loader`)
- [ ] PHP: `crcCheck()` then `form([...], "handler", ...)` then `ajaxReply`
- [ ] Followed `AIRULES/examples/EX-01-secure-form-complete.md` when implementing

## Config / secrets checklist

- [ ] New installs: real `c_enc_key`, `rm_key`, `rmrcm_key`, unique `app.name`
- [ ] Module settings have fallbacks if unset
- [ ] Secrets not committed carelessly

## DACore checklist (when the task touches the admin)

- [ ] No file under `app/modules/DACore/` was modified
- [ ] No direct SQL on `dacore_*` or `users_rights*` tables (uninstall menu cleanup excepted)
- [ ] Admin routes prefixed with `Config::module("DACore","prefixUrl")`
- [ ] Routes guarded by your own `#YourModule:Rights@check!` (not `#DACore:AuthTest@check!`)
- [ ] Allow-lists include `dotapp.root`
- [ ] Page rendered via `DACore:Page@withMenu!`, body contains no `<html>`/`<head>`
- [ ] Shell assets (`dotapp.js`, dotgrid, colors.css, core.css, modals, Notiflix) not re-added
- [ ] Forms use `<dot-col any="12" md="6" ldesktop="6">` and `ri ri-*` icons
- [ ] Menu / rights / AI tools registered in `Installation.php` only
- [ ] `Menu@register` checked `!== true`; rights helpers checked `=== null`
- [ ] AI tool `rights` non-empty and wildcard-free; `controller` ends with `!`
- [ ] AI handler signature `($data, $aiobj)` returning JSON with `result` + `message`
- [ ] `dacore_ai_tools` existence verified before registering tools
- [ ] Uninstaller removes tools, rights, prefixed menu rows and your tables

## Pre-commit / before “done”

- [ ] No core file modifications in the diff
- [ ] No Laravel/Blade/jQuery APIs introduced
- [ ] `--list-routes` or manual route review if routes changed
- [ ] Tests added/updated when logic is non-trivial (`--module=X --test`)
- [ ] Checklists above satisfied for touched areas
- [ ] User-facing summary mentions AIRULES docs followed

## Red flags — stop and fix

- Diff touches `app/parts/**`
- Template contains `{{ $var }}` or `@if`
- Code contains `DB::table` / Eloquent / `$this->db`
- Frontend uses `$('#...')` or `$.ajax`
- Form uses invented `f-form`
- Handler skips `crcCheck` for DotApp JS POST
- `execute()` called with a single callback
- `->first()` used without a guard
- A return value is used without checking its failure form
- `Renderer::useCache(true)` or `Config::db('cache', true)` enabled (both broken)
- `Auth::hasRole()` / `Auth::logged()` used (non-functional)
