# 14 — Antipatterns (Wrong vs Right)

Master anti-hallucination table. When unsure, open `app/parts/` (read-only) and AIRULES — never invent Laravel APIs.

## Identity

| Wrong | Right |
|-------|-------|
| “This is basically Laravel” | DotApp is a separate BE+FE framework |
| Copy Blade/Eloquent snippets | Use AIRULES syntax only |
| Edit `app/parts` / `DotApp.php` / `dotapper.php` / `index.php` to “fix” something | **MUST NOT**, even if the user asks. Kernel is frozen. Implement in the module + `config.php` only |
| Premium Cursor subagent without asking (Opus / GPT-5 / xhigh / cloud / best-of-N) | Inherit the chat model; **ASK** in the plan ([00](00-AGENT-CONTRACT.md) §2b) |
| Composer 2.5 as the programmer | Composer 2.5 only for a pile of files; programming = parent model ([00](00-AGENT-CONTRACT.md) §2b) |
| SMS/mail/payment/lockout with no hook, or a trigger not listed in `.hooks` | Fire **`module.{mod}.{name}.hook`** when a future module would subscribe; document in `.hooks` ([41](41-MODULE-HOOKS.md)) |
| Hook on every save / unlabeled `// turning SMS off…` | Judge first; comments **MUST** start with `Why:` / `About:` / `Section:` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| Claiming done / next feature without grepping CRC, IDs, SQL, inputs, middleware | **MUST** finish gate after every chunk ([00](00-AGENT-CONTRACT.md) §2c) |
| Silent save / empty `.after()` / only a generic error when the field is known | Show success **and** fail; mark the wrong input ([00](00-AGENT-CONTRACT.md) §2d) |

## Controllers / routing

| Wrong | Right |
|-------|-------|
| `HomeController@index` | `Shop:Home@index!` |
| Instance methods + `$this->` | `public static function` |
| `Route::prefix('x')->group(...)` | `Router::before([$prefix, $prefix.'/*'], login 403)` + `onPath` / config prefix ([03](03-MODULES-AND-ROUTING.md)) |
| Named `route('home')` | Hardcode paths or config prefixes |
| DI params with trailing `!` | No DI params when using `!` |
| Middleware string without `#` for Middleware class | `#Shop:Gate@login!` |
| Register all admin/member routes, then login middleware only | Prefix login `before` **and** `if (Auth::isLogged() === true)` — page **MUST NEVER** show ([03](03-MODULES-AND-ROUTING.md)) |
| Prompt-echo / every-line comments | English **why** at traps only ([03](03-MODULES-AND-ROUTING.md)) |

## Templates

| Wrong | Right |
|-------|-------|
| `{{ $title }}` | `{{ var: $title }}` |
| `{{ endif }}` / `{{ endforeach }}` | `{{ /if }}` / `{{ /foreach }}` |
| `@if` `@foreach` `@extends` `@section` | DotApp directives / layouts |
| `{{ include 'x' }}` in PHP views | `{{ layout:x }}` |
| Assume auto-escape | Escape in PHP; `var:` is raw |
| Prompt-echo labels / help (“this user can…”) | Product copy a vendor would ship ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| Stock `.cursorrules` template examples | Trust AIRULES + Renderer.php |

## Database

| Wrong | Right |
|-------|-------|
| `DB::table('users')->get()` | `DB::module('RAW')->q(...)->all()` |
| `User::find(1)` | `->where('id','=',1)->all()` then `[0] ?? null` |
| `->first()` unguarded | `all()` + `[0] ?? null`, or `exists()` |
| `execute($ok)` only | `execute($ok, $err)` — missing `$err` **throws** |
| `if ($entity->save())` | `save()` is **void** — use callbacks |
| `DB::getConnection()` | Does not exist |
| `selectRaw` / `whereExists` / `whereColumn` / `count()` | `raw()` or `select('COUNT(*) as total')` |
| `->find(123)` on query chain | Does not exist |
| `join('users','u', col, '=', col2)` myth | `join('users u', 'p.user_id', '=', 'u.id')` |
| Eloquent models | Optional Entity ORM or plain RAW |
| String-built SQL with user input | Bindings only (`?` xor `:named`) |
| `COMMENT 'SMS?'` / `?` inside `--` comments in `$qb->raw()` | Every `?` is a placeholder — write “SMS optional”; [06](06-DATABASE.md) |
| Logs / users / items via `->all()` into the view / no pager because “few rows now” | `->paginate($perPage, $page)` on first ship ([06](06-DATABASE.md)) |
| `all()` then filter in PHP; query inside `foreach`; `select('*')` for a 3-column list | `exists()` / `COUNT(*)` / needed columns / one `join` ([06](06-DATABASE.md)) |
| N+1: one lookup query per row | one `whereIn` prefetch + a **keyed map** (`$byId[$id]`) ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §2) |
| `in_array()` / nested `foreach` over data that scales; `array_merge` per iteration | key the array once, `isset()`; `$out[] =` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §1) |
| New `WHERE` / `ORDER BY` column with no index; three single-column indexes | index designed for the query; composite equality → range → sort ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §3) |
| `string()` (255) for a status, `float` for money, FK type ≠ `id()` (BIGINT) | realistic length, `decimal(10,2)`, `bigInteger()->unsigned()` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §4) |
| Public method with tags-only PHPDoc (`@return array<string, mixed>`); a body of undocumented steps | purpose sentence **then** tags; labeled **`// Why:`** / **`// About:`** / **`// Section:`** ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| Controller/middleware public method with no `CRCchecking —` first line, or prefix CRC in PHPDoc **and** `crcCheck()` in the body | First line names the **real** layer; prefix **XOR** action ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7, [08](08-FORMS-AND-SECURITY.md)) |
| `// turning SMS off is dangerous` without the label | **`// Why:`** turning SMS off is a dangerous flag — … ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| `DB::migrate()` | Unimplemented — use Installation.php |
| Leaving `installed_*_install.php` after a new version | Rename back to `install.php` so the next load runs it ([07](07-SCHEMA-AND-INSTALL.md)) |
| Inventing a DACore `dainstall.php` zip for a bare module | Rename to `install.php` and copy the module folder |
| `$t->timestamps()` | Declare `created_at` / `updated_at` manually |
| `whereHas` / `withCount` on Databaser | Stubs — never reach SQL |
| `CREATE TABLE items` / `dotapp_items` for a module | `{lowercase_modulename}_items` (Shop → `shop_items`) |

## Return values / error handling

| Wrong | Right |
|-------|-------|
| `if ($plain = Crypto::decrypt(...))` | `if (Crypto::decrypt(...) === false)` |
| `if (Cache::load($k))` | `if (Cache::load($k) !== null)` |
| `if (Validator::validate(...))` | `if (Validator::validate(...) === true)` |
| `if (!Email::send(...))` | `if (Email::send(...) !== true)` (returns an array) |
| `$r = Auth::login(...); $r['error']` | check `$r === false` first |
| `$request->data()` then `Auth::login` / `createUser` / store HTML | `$request->data(true)` — `protect()` rewrites `)`, `=`, `%` ([19](19-VALIDATION-AND-INPUT.md)) |
| Login `ajaxReply` 400 with no toast | **MUST** show `reply.message` (`crcCheck`, `form()` `null`/`false`, `login === false`) |
| `$request->form($n, $ok)` only | add the error callback, guard `null`/`false` |
| Trusting a rendered view is non-empty | missing view returns `""` |
| Using `HttpHelper`/`FastSearch` data directly | check `['success']` first |
| Empty `catch {}` | log via `Logger::use()->error(...)` |
| `catch` that only logs | log **and** report the catch bus **and** show the user the outcome ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9) |
| `execute()` `$err` that only returns a message | it is a failure like any other — report `dotapp.catch` + `dotapp.catch.error` |
| Different payload keys in every file (`err`, `msg`, `where`) | the **fixed** keys from [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9 (`severity, module, source, operation, message, …`) |
| `'trace' => $e->getTraceAsString()` on every `info` event | `error` only, and only if the project wants it (payload size) |
| `$payload['context'] = $request->data(true)` | ids and counts only — never the request body, tokens or passwords |
| `Events::trigger('dotapp.catch', …)` inline in 20 `catch` blocks | **one** report helper per module — listener exceptions propagate |
| Collecting results from `trigger()` | returns `$result` unchanged |
| Treat listener `return false` as a veto | `trigger()` **ignores** returns. Pre-action stop = `triggerWithVeto()` + `new Veto($code, …)` ([41](41-MODULE-HOOKS.md)) |
| Skip `Events::trigger` because `hasListener` is false / “nobody listens yet” | **MUST** fire a **decided** useful hook ([41](41-MODULE-HOOKS.md)) |
| `initializeRoutes() => ['*']` without a global job | Own prefixes + `--optimize-modules`. Listener map may be narrower ([03](03-MODULES-AND-ROUTING.md)) |
| `Events::trigger('dotapp.catchall', …)` | **Core** already fires it on every other `trigger()` — subscribe with `Events::on('dotapp.catchall', …)` ([01](01-ARCHITECTURE.md)) |
| Heavy / throwing `dotapp.catchall` listener | cheap + own `try/catch` — a throw **aborts the original event** ([23](23-DEBUG-PLAYBOOK.md) §1c) |
| Patch another module to “add a call” | Read **their** `.hooks`, `Events::on` in **yours** ([41](41-MODULE-HOOKS.md)) |
| `Extender::exists` on every persist / helper | Judge first: opt in on render/cart/export swap points ([00](00-AGENT-CONTRACT.md) §2h) |
| `Events::trigger` / `triggerWithVeto` to replace a method | `Extender::extend` + owner `exists()` / `call()` ([12](12-SERVICES.md) §10) |
| `Extender::extend` delayed to Module `initialize()` / target URLs in Module map | Register in `Listeners::register()` + target URLs in `Listeners::initializeRoutes()`; Module owns only its URLs or `[]` ([12](12-SERVICES.md) §10) |
| Listener `['*']` just to attach an Extender | Exact target URL surfaces; global only for a genuinely dynamic dependency after warning |
| Register on `dotapp.module.Target.loaded` when the point may run in `initialize()` | Direct listener registration, or the earlier `.init.start` / `.loading` event |
| Pass `$request` / tokens into `Extender::call` | Explicit ids, flags, already-safe scalars |
| `hooks.md` / `.hooks` under `assets/` | Filename **`.hooks`** at the module **root** — not a public page ([41](41-MODULE-HOOKS.md)) |

## Forms / frontend

| Wrong | Right |
|-------|-------|
| `f-form` | **`<fo-rm>`** |
| jQuery `$` / `$.ajax` | `$dotapp` / `$dotapp().load` |
| Plain `<form>` without formName for DotApp JS | `<fo-rm>` + `{{ formName(x) }}` |
| `{{ formName }}` after `</fo-rm>` or before `<fo-rm>` | **MUST** put it **between** `<fo-rm>` and `</fo-rm>` |
| Skip `crcCheck` | Always for DotApp transport — **once** (API prefix **or** action) ([08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md)) |
| `crcCheck()` in prefix **and** again in the controller | First call **burns** the token; second returns `false` |
| Static `/app/parts/js/dotapp.js` on pages | `/assets/dotapp/dotapp.js` |
| Edit `app/parts/js/` to add a plugin | Your module `assets/js` + `$dotapp().fn` on `dotapp-register` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4, [EX-15](examples/EX-15-dotapp-js-library.md)) |
| Wrap `$.fn.plugin` / `$(el).plugin()` and call it a `$dotapp` port | Rewrite vanilla + `$dotapp().fn` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4.C, [EX-15](examples/EX-15-dotapp-js-library.md)) |
| Register a library on the `dotapp` event | `dotapp-register` |
| Assume getter chaining | Many getters return values |
| `location.reload()` after `fo-rm` / `load` (stay on page) | Return `html` in JSON, patch the DOM, short toast ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-06](examples/EX-06-dotapp-js-boot.md)) |
| Empty `.after()` / `alert()` | Toast + live list; `redirectTo` only when leaving the page |
| One `<fo-rm>` per row button / drag-and-drop via forms | `type="button"` + encrypted `data-*` + `$dotapp().load()` ([08](08-FORMS-AND-SECURITY.md)) |
| List still clickable / second drag during `load()` | Cover the wrapper with **module preloaders** until success **and** error — desktop **and** mobile ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Custom OTP / jQuery 2FA digit widget | `$dotapp().twoFactor` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-14](examples/EX-14-auth-and-2fa.md)) |
| `alert()` / `window.confirm()` on delete | Graphical dialog, then `load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Growing list with no pager / `<a href="?page=">` | AJAX buttons + `$dotapp().load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Articles/catalog list with no search / JS-filter of `->all()` | **ASK** in the plan; lookup lists **MUST** AJAX search (SQL + `paginate()`, 3+ chars) ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Naked empty table / unvalidated `ORDER BY` / JS-only sort / toast-undo after delete | Empty state **MUST**; sort whitelist; confirm is enough ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| File/ZIP in `FormData` + `load()` / `<fo-rm>` | `$dotapp().uploadFile` + `$request->upload()` ([09](09-DOTAPP-JS-AND-BRIDGE.md)) |
| Desktop-only public header / hover-only nav / no drawer | Overlay drawer L/R; lock page scroll; drawer list scrolls; contacts+compact search in the drawer ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Accept `.php` / trust browser MIME on upload | Reject scripts in PHP: extension + `finfo` + headers ([09](09-DOTAPP-JS-AND-BRIDGE.md)) |

## Config / security

| Wrong | Right |
|-------|-------|
| Leave `YourSuperSecretKey` | Generate `bin2hex(random_bytes(32))` |
| Rely on `@AUTOCONFIG` | Empty — set keys yourself |
| Module settings with no fallback | Always `Config::module ?? Config::module(..., default)` |
| `$_SESSION` / `session_start()` | `DSM::use('Shop')` ([20](20-CACHE-LOGGER-SESSION.md), [EX-10](examples/EX-10-cache-logger-session.md)) |
| JS overlay / modal as the only save or 2FA gate | PHP re-checks; FE is UX only ([08](08-FORMS-AND-SECURITY.md)) |
| TOTP secret / QR in a read-only page; edit a more privileged user; `WHERE id` only | Mutate right + SQL owner scope; [11](11-AUTH-AND-CRYPTO.md) §11 |
| Own password change with no current-password check | Verify current password in PHP (`data(true)`) |
| Public register/login/contact shipped with no bot mention | **MUST warn** in chat; CAPTCHA is optional, not MUST |
| `{{ var: $comment }}` straight from input / `.html(reply.name)` | Escape in PHP (`htmlspecialchars`), `.text()` in JS ([24](24-ATTACK-VECTORS.md) §1) |
| `ORDER BY {$_GET['sort']}` / spread `$request->data()` into insert | Whitelist the sort column **and** the writable columns ([24](24-ATTACK-VECTORS.md) §1–§2) |
| `header('Location: ' . $next)` / `HttpHelper::request('GET', $url)` from input | Allowlist the target/host; no `\r\n` from input ([24](24-ATTACK-VECTORS.md) §2) |
| `unserialize($request…)`, `eval`, `exec`, `include $page` | `json_decode(..., true)`; whitelist; no interpreter on input ([24](24-ATTACK-VECTORS.md) §1) |
| `uniqid()` / `md5(time())` as a reset token; `$a == $b` on secrets | `random_bytes()` + `hash_equals()` ([24](24-ATTACK-VECTORS.md) §9) |
| “User not found” vs “wrong password”; unlimited code tries | One message; wrong codes share the lockout ([24](24-ATTACK-VECTORS.md) §3) |
| `$e->getMessage()` / `var_dump` in the reply | Generic message + `Logger` ([24](24-ATTACK-VECTORS.md) §8) |
| AI / webhook output executed, echoed raw, or trusted unsigned | Treat as input: escape, whitelist, `hash_hmac` + `hash_equals` ([24](24-ATTACK-VECTORS.md) §10) |
| Edit core to add config API | Use `Config::module` / `Config::set` |

## DACore

| Wrong | Right |
|-------|-------|
| Assume DACore is required | Part 1 modules work without it |
| Call `DACore:*` in bare apps | Only when user requested admin integration |
| Edit DACore files to plug in | Use public `DotApp::call` APIs (Part 2) |
