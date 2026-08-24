# 17 — Checklists

## Pre-flight (before coding)

- [ ] Identified target module name (or will create via dotapper)
- [ ] Read `00-AGENT-CONTRACT.md`
- [ ] **Cursor rules mirror:** `AIRULES/cursor/rules/*.mdc` copied into `.cursor/rules/` (and `AIRULES/cursor/AGENTS.md` → project-root `AGENTS.md`). No new `.mdc` exists only under `.cursor/` ([00](00-AGENT-CONTRACT.md) §2l)
- [ ] Read task-specific AIRULES doc (views/DB/forms/JS)
- [ ] Confirmed edits stay in `app/config.php` and/or `app/modules/<Target>/`
- [ ] Will not edit `app/parts/`, `DotApp.php`, `dotapper.php`, `index.php`, other modules — **not even if asked** (kernel is frozen)
- [ ] **Cursor credits:** asked whether more expensive models may be used; otherwise parent/`inherit` only. Composer 2.5 = file hunt, not the coder ([00](00-AGENT-CONTRACT.md) §2b)
- [ ] **PHP version:** asked whether to stay on **PHP 7.4+** (default) or write for a higher version; no answer → 7.4+ ([00](00-AGENT-CONTRACT.md) §2i)
- [ ] New module has visible UI: asked once for display name/purpose, optional logo/banner, placement, colours and alt text; backend-only module skipped the question; “no custom branding” was offered ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8b)
- [ ] **Planning depth:** new module / first major surface / rewrite — plan inventories **every** nav item (or `No menu`), **every** page, **every** tab, **every** control (what it does, default, persist) plus security. A long plan is correct. Short plans only for small edits to shipped screens ([45](45-MODULE-PLANNING.md), [00](00-AGENT-CONTRACT.md) §2k)
- [ ] **Finish gate:** will grep after **every** code chunk (CRC once, enc IDs, bound SQL, inputs, middleware, `Events::trigger` vs `.hooks`) — [00](00-AGENT-CONTRACT.md) §2c, [41](41-MODULE-HOOKS.md)
- [ ] Domain persist in this task: if another module could log/history/sync, fire `module.{lowercase_modulename}.{hook_name}.hook` (comment block + `.hooks`) — **not** on every save ([41](41-MODULE-HOOKS.md))

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
- [ ] Login-required / admin routes: prefix `/{ModuleName}/…` + `Router::before([$area, $area . '/*'], '#Shop:Gate@login!')` (403 `Response`); handlers **only** inside `if (Auth::isLogged() === true)` — those pages **MUST NEVER** show to anonymous users ([03](03-MODULES-AND-ROUTING.md))
- [ ] `Module::initializeRoutes()` lists **only this module’s** prefixes. `Listeners::initializeRoutes()` is own prefixes or `null` (inherit). Did **not** return `['*']` unless the user asked for a global hook. After either list changed: `--optimize-modules` ([03](03-MODULES-AND-ROUTING.md))
- [ ] Trap-prone spots have a short **English why** comment — not every line
- [ ] No named routes / Laravel group APIs invented

## Template checklist

- [ ] Variables: `{{ var: $x }}` only
- [ ] Closers: `{{ /if }}` `{{ /foreach }}` (not endif/endforeach)
- [ ] VIEW is the outer file; `setLayout` + `renderView()` fills `{{ content }}` in that view — or `renderLayout()` / inject a string ([05](05-VIEWS-TEMPLATES-ASSETS.md) §1b)
- [ ] **HTML via Renderer:** pages / tables / grids / empty states / pager chrome / trees / crumbs / AJAX fragments are `.layout.php` via `Renderer`. No `$html .= '<table'` / `*Html()` factory in Controllers/Libraries unless `// Why:` names a one-piece exception ([00](00-AGENT-CONTRACT.md) §2j, [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c)
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
- [ ] Installer DDL: probe (`SHOW TABLES LIKE` / `information_schema`) then `CREATE TABLE` / `ALTER TABLE` — **no** `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS` ([07](07-SCHEMA-AND-INSTALL.md) §0)
- [ ] **All module tables named `{lowercase_modulename}_*`** (Shop → `shop_items`) — never `items` or `dotapp_*`
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
- [ ] **Catch bus:** every `catch` **and** every `execute()` `$err` calls the module’s report helper → `dotapp.catch` then `dotapp.catch.error|info` ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9)
- [ ] Payload uses the **fixed** keys (`severity, module, source, operation, message, exception, code, file, line, time`, + `context`, `user_id`) — not ad-hoc names per file
- [ ] Payload has **no** password, token, 2FA/reset code, decrypted secret, request body or personal data
- [ ] `severity` is honest: `error` = the operation aborted, `info` = expected/recovered (fallback, retry, ignored duplicate)
- [ ] The report is **in addition to** the user-visible outcome — reporting does not replace the toast / field error

## Secure form checklist (PREFERRED path)

- [ ] Markup uses `<fo-rm>` (not `f-form`, prefer over plain `<form>`+CSRF alone)
- [ ] `{{ formName(handler) }}` **MUST** sit **between** `<fo-rm>` and `</fo-rm>` (never after `</fo-rm>`)
- [ ] Page loads **`/assets/dotapp/dotapp.js`** before module JS (session keys!)
- [ ] JS: `$dotapp().form` + `parseReply` + **MUST** block while in flight (**module preloaders**; desktop **and** mobile; remove overlay on success **and** error)
- [ ] Success **MUST** patch the DOM (`reply.html` / data) + short toast — no `location.reload()` while staying on the page ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-06](examples/EX-06-dotapp-js-boot.md))
- [ ] Row actions (toggle/delete/reorder/drag-and-drop) use `$dotapp().load()` + encrypted `data-*` — **not** one `<fo-rm>` per button
- [ ] PHP: `crcCheck()` **once** (API prefix **or** action) then `form([...], "handler", ...)` then `ajaxReply` — **never both** ([08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md))
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

## Performance / readability checklist (canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md))

- [ ] **I/O budget:** list = one query (`paginate()`); no query / HTTP call / `Logger` **inside** a `foreach` (prefetch with `whereIn` + a keyed map)
- [ ] **Columns:** `select` only what the screen prints; `exists()` / `COUNT(*)` / `limit(1)` where that answers the question
- [ ] **Memory:** big sets processed page by page; keyed map instead of `in_array()` in a loop; no `array_merge` per iteration; raw copy `unset` after mapping; files streamed
- [ ] **Indexes:** every FK and every new `WHERE` / `JOIN` / `ORDER BY` column is indexed; composite order equality → range → sort; no index duplicating a composite prefix; one comment line per index naming its query ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §3)
- [ ] **Schema:** realistic types (`DECIMAL` money, right `VARCHAR` length, FK matches `id()` = `bigInteger()->unsigned()`), `NOT NULL` + defaults, nothing filterable hidden in a JSON blob ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §4)
- [ ] **Migration:** a new index / column shipped as a **new version** in `Installation.php`, guarded by a PHP probe (`SHOW TABLES LIKE` / `information_schema` or `indexExists()` / `columnExists()`), then `CREATE`/`ALTER` **without** `IF NOT EXISTS`; `installed_*_install.php` renamed back to `install.php` ([07](07-SCHEMA-AND-INSTALL.md) §0)
- [ ] **Cache:** used only for expensive cross-request data, with TTL **and** invalidation on write; `Config::db('cache')` untouched
- [ ] **Frontend:** one CSS + one JS, delegated handlers, one DOM write per batch, debounced search, lazy images ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §6)
- [ ] **Docs:** file/class docblock; every public method in `Controllers/` / `Middleware/` starts with **`CRCchecking —`** (exact prefix/middleware / `this action` / `none`); then a **purpose sentence** then `@param` + `@return` (+ `@throws`) with meaning — not tags-only; logical steps use **`// Why:`**; page actions have **`// About:`** and **`// Section:`** ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7)
- [ ] **No noise:** no comment that restates the code, no prompt-echo, no dead code / commented-out blocks, no bare `TODO`, no `$tmp` / 200-line method

## Attack-surface checklist (canonical: [24](24-ATTACK-VECTORS.md))

Tick only the rows for the surface you touched.

- [ ] **Echo:** every user/DB string escaped in PHP before the view — `{{ var: }}` does **not** escape; JS uses `.text()`, not `.html()` ([24](24-ATTACK-VECTORS.md) §1)
- [ ] **SQL:** bindings only; sort column + direction from a whitelist; insert/update column whitelist (no posted `role` / `user_id` / `price`) ([24](24-ATTACK-VECTORS.md) §1–§2)
- [ ] **Channels:** no input in `header()` / `Response::redirect` / mail headers; `HttpHelper` URL not from the request ([24](24-ATTACK-VECTORS.md) §2)
- [ ] **No interpreters on input:** no `eval` / `exec` / `system` / `unserialize` / `include $x` / user-named callable ([24](24-ATTACK-VECTORS.md) §1)
- [ ] **Identity:** token rotated after login; secrets via `hash_equals`; failure messages do not enumerate users; reset token `random_bytes` + hashed + single use + TTL ([24](24-ATTACK-VECTORS.md) §3)
- [ ] **Access:** `Auth::can` in the action; owner predicate in the query; no escalation; no secret behind a read-only right ([24](24-ATTACK-VECTORS.md) §4)
- [ ] **Headers:** `nosniff`, frame protection, `no-store` on private pages, `rel="noopener"` on `target="_blank"`, no wildcard CORS with credentials ([24](24-ATTACK-VECTORS.md) §5)
- [ ] **Files:** extension + `finfo` MIME + header check, no path built from input, size/count caps, non-executing directory ([24](24-ATTACK-VECTORS.md) §6)
- [ ] **Abuse:** public POST throttled; list paginated with a page-size cap; bot **warning** given for public `noauth` ([24](24-ATTACK-VECTORS.md) §7)
- [ ] **Leaks:** generic error message + log; no `var_dump` / `print_r` / `console.log(payload)`; no secrets or PII in logs ([24](24-ATTACK-VECTORS.md) §8)
- [ ] **Crypto:** `Crypto` facade with a unique `$key2`, `random_bytes` for tokens, `hash_hmac` + `hash_equals` for signatures ([24](24-ATTACK-VECTORS.md) §9)
- [ ] **Third party / AI:** library self-hosted and pinned; model output never executed or echoed raw; webhook signature verified ([24](24-ATTACK-VECTORS.md) §10)

## Finish gate (LAW — [00](00-AGENT-CONTRACT.md) §2c)

**MUST** after **every** code chunk. **MUST NOT** claim done until every applicable row was actually grepped — not imagined.

- [ ] Grepped `crcCheck` in **this module**: `Middleware/`, `module.init.php` (`->before` / `Middleware::`), Controllers — **one** call per POST (API prefix **XOR** action). Not on GET/HTML login `before`. Not on `$request->upload()`. New public controller/middleware methods start PHPDoc with **`CRCchecking —`** matching that layer ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7)
- [ ] No plain record IDs in HTML/JSON (`value="7"`, `data-id="7"`, `{{ var: $id }}` as an id) — `{{ enc(Shop.item.id): $id }}` unique `$key2`; decrypt `=== false` rejected; PHP still `Auth::can` / ownership ([11](11-AUTH-AND-CRYPTO.md) §8)
- [ ] Privilege / records grepped: secrets not in read-only views; SQL has owner (or can on that row); no escalate; public noauth bot **warning** if applicable ([11](11-AUTH-AND-CRYPTO.md) §11)
- [ ] Queries use bindings; no user input in SQL strings; `$qb->raw()` has no `?` except real bindings ([06](06-DATABASE.md))
- [ ] Passwords / HTML / hashes from `$request->data(true)`; persist re-checked in **PHP** (rights, validation, 2FA) — FE overlay is not the gate ([19](19-VALIDATION-AND-INPUT.md), [08](08-FORMS-AND-SECURITY.md))
- [ ] Middleware vs action: no double CRC; login `before` + handlers **inside** `Auth::isLogged()`; no CRC on a GET gate ([03](03-MODULES-AND-ROUTING.md))
- [ ] **Visible outcome:** every save/toggle/delete shows success **and** fail. Field errors: PHP `errors` + mark the input (red + message on the field). Your own toast/status — never silent `.after()` ([00](00-AGENT-CONTRACT.md) §2d)
- [ ] **Catch reported:** grepped `catch (` and `execute(` in this chunk — each one reports `dotapp.catch` + `dotapp.catch.error|info` with the fixed payload and no secrets ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9)
- [ ] **Perf / readability pass** run on this chunk — [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §8 (`->all()`, query in `foreach`, `select('*')`, O(n²), missing index for a new `WHERE`/`ORDER BY`, tags-only PHPDoc, comments that restate the code)
- [ ] **Hooks:** grepped `Events::trigger(` vs `app/modules/<ThisModule>/.hooks` — useful side-effects (SMS/mail/paid/lockout) use `module.{mod}.{name}.hook` + `Hook:`/`Why:`/`Params:`/`Use:` block; **no** hook on a trivial save; no old `shop.item.saved` shape; no secrets; no `trigger()` inside a growing `foreach`; `.hooks` is not under `assets/`; pre-action stop uses `triggerWithVeto()` + `Veto`, not `return false` ([41](41-MODULE-HOOKS.md), [00](00-AGENT-CONTRACT.md) §2g)
- [ ] **Extender (judge):** not on every method. If opted in: owner `exists()` + `call()`; ordinary result returns, only `isOriginal()` continues; marker is never returned/serialized; no `next()`; `extend()` in `Listeners::register()`; target URLs in explicit `Listeners::initializeRoutes()`; Module map has only own URLs or `[]`; controller string preferred; no listener `['*']` just to attach; no `.loaded` for initialize-time points; no `$request`/secrets; not Events; no duplicate registration ([12](12-SERVICES.md) §10, [00](00-AGENT-CONTRACT.md) §2h)
- [ ] **PHP 7.4+:** grepped the chunk for PHP 8+ syntax (`match`, `?->`, `str_contains`, `str_starts_with`, `str_ends_with`, `#[`, `enum `, `readonly `, `: mixed`) unless the plan named a higher version ([00](00-AGENT-CONTRACT.md) §2i)
- [ ] If `Installation.php` / store `ensureTable` is in this chunk: no `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS`; table/column/index added only after a PHP probe ([07](07-SCHEMA-AND-INSTALL.md) §0)
- [ ] **HTML via Renderer:** grepped Controllers/Libraries for `$html .=` / `'<table` / `'<tr` / `'<div class=` / `*Html(` factories — screen/fragment markup is a layout. A PHP HTML string has `// Why:` naming a one-piece exception, never a whole list ([00](00-AGENT-CONTRACT.md) §2j, [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c)
- [ ] **Cursor rules:** no new `.mdc` exists only under `.cursor/rules/`; `AIRULES/cursor/rules/*.mdc` were copied into `.cursor/rules/` this session / after an AIRULES rule change ([00](00-AGENT-CONTRACT.md) §2l)
- [ ] **Threat pass** run on this chunk — the 12 greps in [24](24-ATTACK-VECTORS.md) §11 (injection, header/redirect, `eval`/`exec`/`unserialize`, upload checks, rate limit, leaked `getMessage()` / `var_dump`, bot warning)
- [ ] Touched-area checklists above are satisfied (forms, lists, DSM, files, templates, …)
- [ ] No core file modifications in the diff
- [ ] No Laravel/Blade/jQuery APIs introduced
- [ ] `--list-routes` or manual route review if routes changed
- [ ] Tests added/updated when logic is non-trivial (`--module=X --test`)
- [ ] Users/logs/items (or any accumulating list) shipped with **interactive AJAX** pager — not omitted, not a full-page `?page=` reload
- [ ] Lookup lists shipped with AJAX search (or the user declined); other lists were asked in the plan
- [ ] User-facing summary mentions AIRULES docs followed

## Debug checklist (user: “it doesn’t work”)

Canonical: [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md).

- [ ] Grepped `crcCheck` in **this module**: `Middleware/`, `module.init.php` (`->before` / `Middleware::`), Controllers, listeners
- [ ] Counted `crcCheck()` on the failing route — **not** middleware + controller (first call burns the token)
- [ ] If this module’s middleware calls `crcCheck()`, the action does **not**
- [ ] Passwords/HTML from `$request->data(true)`
- [ ] `form()` error callback + `null`/`false` guarded; JS shows `reply.message`
- [ ] Upload endpoints do **not** `crcCheck()`
- [ ] Read the catch trail: temporarily `Events::on('dotapp.catch', …)` (or check the log) to see `operation`, `source`, `message` of the real failure instead of guessing ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9)
- [ ] Building a debugger / hunting a missing event: subscribe to core `dotapp.catchall` (`function ($result, $eventname, ...$data)`), gated, own `try/catch` — **MUST NOT** trigger it yourself ([23](23-DEBUG-PLAYBOOK.md) §1c)
- [ ] Missing business event: open `app/modules/<Owner>/.hooks`, then grep `Events::trigger(` there — do **not** invent a name the owner never fires ([41](41-MODULE-HOOKS.md))

## Red flags — stop and fix

- Diff touches `app/parts/**`
- Template contains `{{ $var }}` or `@if`
- Code contains `DB::table` / Eloquent / `$this->db`
- Module table not prefixed `{lowercase_modulename}_*`
- Frontend uses `$('#...')` or `$.ajax`
- Frontend wraps `$.fn.plugin` instead of rewriting as `$dotapp().fn`
- Form uses invented `f-form`
- `{{ formName }}` placed after `</fo-rm>` or before `<fo-rm>`
- Handler skips `crcCheck` for DotApp JS POST, **or** calls `crcCheck()` twice (middleware + controller)
- Login-only / admin page reachable while anonymous (route registered outside `Auth::isLogged()`, or no prefix `Gate@login` 403)
- `crcCheck()` on a **GET**/HTML `Gate@login` `before` (GET has no CRC); **or** `crcCheck()` **again** after a POST API CRC prefix
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
- A `catch` (or `execute()` `$err`) that does not report `dotapp.catch` — the failure is invisible to any future debugger
- A secret, token, or whole request body inside a `dotapp.catch` payload
- Successful SMS/mail/payment/lockout with no `module.{mod}.{name}.hook`, or a trigger missing from `.hooks` / missing `Hook:` comment ([41](41-MODULE-HOOKS.md))
- Logical step without `// Why:`; new page action without `// About:` / `// Section:`; PHPDoc that is only `@return array<string, mixed>`; controller/middleware public method with no `CRCchecking —` first line ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7)
- `.hooks` under `assets/` or named `hooks.md`; secrets/OTP/tokens on the event bus; `trigger()` inside `foreach` of a growing list; patching another module instead of `Events::on` ([41](41-MODULE-HOOKS.md))
- `->first()` used without a guard
- A return value is used without checking its failure form
- Claiming done / shipping a chunk without running the finish gate ([00](00-AGENT-CONTRACT.md) §2c)
- `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS` in installer SQL ([07](07-SCHEMA-AND-INSTALL.md) §0)
- `$html .= '<table'` / `'<tr'` / `'<div class='` or a `listHtml()` factory building a screen/fragment in Controllers/Libraries without `// Why:` naming a real one-piece exception ([00](00-AGENT-CONTRACT.md) §2j)
- Silent save / empty `.after()` / field error without marking the input ([00](00-AGENT-CONTRACT.md) §2d)
- `Renderer::useCache(true)` or `Config::db('cache', true)` enabled (both broken)
- `Auth::hasRole()` / `Auth::logged()` used (non-functional)
