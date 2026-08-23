# 14 — Antipatterns (Wrong vs Right)

Master anti-hallucination table. When unsure, open `app/parts/` (read-only) and AIRULES — never invent Laravel APIs.

## Identity

| Wrong | Right |
|-------|-------|
| “This is basically Laravel” | DotApp is a separate BE+FE framework |
| Copy Blade/Eloquent snippets | Use AIRULES syntax only |
| Edit `app/parts` to “fix” something | Ask user; edit module + `config.php` only |
| Premium Cursor subagent without asking (Opus / GPT-5 / xhigh / cloud / best-of-N) | Inherit the chat model; **ASK** in the plan ([00](00-AGENT-CONTRACT.md) §2b) |
| Composer 2.5 as the programmer | Composer 2.5 only for a pile of files; programming = parent model ([00](00-AGENT-CONTRACT.md) §2b) |
| Claiming done / next feature without grepping CRC, IDs, SQL, inputs, middleware | **MUST** finish gate after every chunk ([00](00-AGENT-CONTRACT.md) §2c) |
| PHP 8+ syntax (`match`, `?->`, `str_contains`, union/`mixed`, named args, promotion, `enum`) without asking | Default **PHP 7.4+**; **ASK** in the plan ([00](00-AGENT-CONTRACT.md) §2i) |
| Silent save / empty `.after()` / invent a second toast instead of grepping DACore | Admin: search DACore then **toast**. Public: mark the wrong field ([00](00-AGENT-CONTRACT.md) §2d) |
| SMS/mail/payment/lockout with no hook, or a trigger not listed in `.hooks` | Fire **`module.{mod}.{name}.hook`** when a future module would subscribe; document in `.hooks` ([41](41-MODULE-HOOKS.md)) |
| Hook on every save / unlabeled `// turning SMS off…` | Judge first; comments **MUST** start with `Why:` / `About:` / `Section:` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |

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
| `setLayoutVar('featureRows', [['key' => 'time', …]])` / var named `copy` / `count` / `header` | Sandbox **drops** any bag value (or var name) that `is_callable()` — `time()` exists. Prefix keys or pass escaped HTML ([05](05-VIEWS-TEMPLATES-ASSETS.md) §5) |
| Empty `foreach` → patch `app/parts/Renderer.php` | Work around in the module. Heading with no rows is almost always a dropped var |
| `date(` / `header(` / `file_get_contents(` inside a `.layout.php` | Sandbox **strips** those calls; logic belongs in the controller ([05](05-VIEWS-TEMPLATES-ASSETS.md) §5) |
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
| Logs / users / items via `->all()` into the view / no pager because “few rows now” | COUNT + LIMIT + [40](40-DACORE-LIST-PAGER.md) pager on first ship |
| `all()` then filter in PHP; query inside `foreach`; `select('*')` for a 3-column list | `exists()` / `COUNT(*)` / needed columns / one `join` ([06](06-DATABASE.md)) |
| N+1: one lookup (or rights) query per row | one `whereIn` prefetch + a **keyed map** (`$byId[$id]`) ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §2) |
| `in_array()` / nested `foreach` over data that scales; `array_merge` per iteration | key the array once, `isset()`; `$out[] =` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §1) |
| New `WHERE` / `ORDER BY` column with no index; three single-column indexes | index designed for the query; composite equality → range → sort ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §3) |
| `string()` (255) for a status, `float` for money, FK type ≠ `id()` (BIGINT) | realistic length, `decimal(10,2)`, `bigInteger()->unsigned()` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §4) |
| Public method with tags-only PHPDoc (`@return array<string, mixed>`); a body of undocumented steps | purpose sentence **then** tags; labeled **`// Why:`** / **`// About:`** / **`// Section:`** ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| Controller/middleware public method with no `CRCchecking —` first line, or prefix CRC in PHPDoc **and** `crcCheck()` in the body | First line names the **real** layer; prefix **XOR** action ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7, [08](08-FORMS-AND-SECURITY.md)) |
| `// turning SMS off is dangerous` without the label | **`// Why:`** turning SMS off is a dangerous flag — … ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| `DB::migrate()` | Unimplemented — use Installation.php |
| `$t->timestamps()` | Declare `created_at` / `updated_at` manually |
| `whereHas` / `withCount` on Databaser | Stubs — never reach SQL |
| `CREATE TABLE items` / `dotapp_items` / `dacore_*` for a module | `{lowercase_modulename}_items` (Shop → `shop_items`) |

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
| `catch` that only logs | log **and** report the catch bus **and** show the user the outcome (admin = toast) ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9) |
| `execute()` `$err` that only returns a message | it is a failure like any other — report `dotapp.catch` + `dotapp.catch.error` |
| Different payload keys in every file (`err`, `msg`, `where`) | the **fixed** keys from [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9 (`severity, module, source, operation, message, …`) |
| `'trace' => $e->getTraceAsString()` on every `info` event | `error` only, and only if the project wants it (payload size) |
| `$payload['context'] = $request->data(true)` | ids and counts only — never the request body, tokens, rights or passwords |
| `Events::trigger('dotapp.catch', …)` inline in 20 `catch` blocks | **one** report helper per module — listener exceptions propagate |
| A `dotapp.catch` listener that pushes a DACore notification every time | rate-limited threshold, or notify on the real business event ([37](37-DACORE-NOTIFICATIONS.md)) |
| Adding the report helper / listener inside `app/modules/DACore/` | **your** module only — DACore is wiped on update |
| Collecting results from `trigger()` | returns `$result` unchanged |
| `Events::trigger('dotapp.catchall', …)` | **Core** already fires it on every other `trigger()` — subscribe with `Events::on('dotapp.catchall', …)` in **your** module ([01](01-ARCHITECTURE.md)) |
| Heavy / throwing `dotapp.catchall` listener | cheap + own `try/catch` — a throw **aborts the original event** ([23](23-DEBUG-PLAYBOOK.md) §1c) |
| A `dotapp.catchall` listener that pushes a DACore notification every time | opt-in tracer only — that event fires on **every** `trigger()` ([37](37-DACORE-NOTIFICATIONS.md)) |
| Skip `Events::trigger` because `hasListener` is false / “nobody listens yet” | **MUST** fire a **decided** useful hook ([41](41-MODULE-HOOKS.md)) |
| Trigger on every item save “just in case” | Fire only when `Use:` names a real consumer ([41](41-MODULE-HOOKS.md)) |
| `shop.item.saved` / `{mod}.{noun}.{happened}` | `module.shop.sms_sent.hook` ([41](41-MODULE-HOOKS.md)) |
| `Events::trigger` without `Hook:` / `Params:` / `Use:` | The five-line block above `trigger()` ([41](41-MODULE-HOOKS.md) §3) |
| Treat listener `return false` as a veto | `trigger()` **ignores** returns — persist is already done ([41](41-MODULE-HOOKS.md)) |
| Patch another module (or DACore) to “add a call” | Read **their** `.hooks`, `Events::on` in **yours** ([41](41-MODULE-HOOKS.md)) |
| `Extender::exists` on every persist / helper | Judge first: opt in on render/cart/export swap points ([00](00-AGENT-CONTRACT.md) §2h) |
| `Events::trigger` / `triggerWithVeto` to replace a method | `Extender::extend` + owner `exists()` / `call()` ([12](12-SERVICES.md) §10) |
| `Extender::extend` delayed to Module `initialize()` / target URLs in Module map | `Listeners::register()` + target listener routes; Module owns only its URLs or `[]` ([12](12-SERVICES.md) §10) |
| Listener `['*']` just to attach | Exact target URL surfaces; global only when genuinely dynamic and warned |
| `.loaded` when the point may run in target `initialize()` | Direct registration, or earlier `.init.start` / `.loading` |
| Pass `$request` / tokens into `Extender::call` | Explicit ids, flags, already-safe scalars |
| Public string/integer `Extender::ORIGINAL` sentinel | Unique `Extender::original()` marker checked with `isOriginal()` |
| Return/serialize the `original()` marker | Continue the owner implementation locally; only ordinary results leave the method |
| Add `next()` while only one extender is permitted | Use `original()`; there is no second handler in a strict single replacement |
| Patch DACore to insert `Extender::call` | Only the **owner** of the target method opts in |
| Invent `module.blog.*` from Shop / fire `dotapp.*` for business | Prefix = **this** module’s lowercase name ([41](41-MODULE-HOOKS.md)) |
| `hooks.md` / `.hooks` under `assets/` | Filename **`.hooks`** at the module **root** — not a public page ([41](41-MODULE-HOOKS.md)) |
| Password / TOTP / CRC / request body on `Events::trigger` | Ids, counts, flags only ([41](41-MODULE-HOOKS.md), [24](24-ATTACK-VECTORS.md) §8) |
| `Events::trigger` inside `foreach` of a growing list | One **batch** event after the loop ([41](41-MODULE-HOOKS.md), [25](25-PERFORMANCE-AND-CODE-QUALITY.md)) |
| Skip a useful SMS/mail hook “for performance” | Unused `trigger()` is cheap; spraying every save is noise ([41](41-MODULE-HOOKS.md)) |
| Shop admin `SELECT` all `{prefix}users` / list DACore operators | INNER JOIN `dacore_users_profiles`, bind expected `p.origin_id` + token, paginate, and re-check every write. A DACore-replacement UI needs an **ASK** + warning ([42](42-DACORE-USER-ORIGIN.md)) |
| Treat origin as tenant DB, permission, sandbox, or module-local session | Users/email/username/Auth session are global; **your module** enforces exact origin in login, 2FA, every gate and list/write ([42](42-DACORE-USER-ORIGIN.md)) |
| Custom login checks origin only once / route gate checks only `Auth::isLogged()` | Check after credentials, before/on/after 2FA and on every authenticated route; mismatch/error → `Auth::logout()` + generic failure ([42](42-DACORE-USER-ORIGIN.md)) |
| Assume `Auth::createUser` returned id / fire-and-forget `registerOrigin` or `stampOrigin` | Check catalog id, bound exact id lookup, stamp, then `read` exact token/id before success ([42](42-DACORE-USER-ORIGIN.md)) |
| Leave `dacore.legacy` on accounts your module created | Abort visibly; never authenticate/expose until stamp + re-read equality passes ([42](42-DACORE-USER-ORIGIN.md)) |
| Treat `findByExtra` ids as ownership | Global discovery only; final access requires joined origin predicate or module-owned membership ([42](42-DACORE-USER-ORIGIN.md)) |
| `eval` / `include` request path / grant `dotapp.root` from a shop form | Non-escalatable identity code ([24](24-ATTACK-VECTORS.md), [42](42-DACORE-USER-ORIGIN.md)) |

## Forms / frontend

| Wrong | Right |
|-------|-------|
| `f-form` | **`<fo-rm>`** |
| jQuery `$` / `$.ajax` | `$dotapp` / `$dotapp().load` |
| Plain `<form>` without formName for DotApp JS | `<fo-rm>` + `{{ formName(x) }}` |
| `{{ formName }}` after `</fo-rm>` or before `<fo-rm>` | **MUST** put it **between** `<fo-rm>` and `</fo-rm>` |
| Skip `crcCheck` | Always for DotApp transport — **once** (API prefix **or** action) ([08](08-FORMS-AND-SECURITY.md), [32](32-DACORE-RIGHTS.md)) |
| `crcCheck()` in prefix **and** again in the controller | First call **burns** the token; second returns `false` (`POST /dacore/*` already CRC’d) |
| Static `/app/parts/js/dotapp.js` on pages | `/assets/dotapp/dotapp.js` |
| Edit `app/parts/js/` to add a plugin | Your module `assets/js` + `$dotapp().fn` on `dotapp-register` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4, [EX-15](examples/EX-15-dotapp-js-library.md)) |
| Wrap `$.fn.plugin` / `$(el).plugin()` and call it a `$dotapp` port | Rewrite vanilla + `$dotapp().fn` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4.C, [EX-15](examples/EX-15-dotapp-js-library.md)) |
| Register a library on the `dotapp` event | `dotapp-register` |
| Assume getter chaining | Many getters return values |
| `location.reload()` after `fo-rm` / `load` (stay on page) | Return `html` in JSON, patch the DOM, short toast ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-06](examples/EX-06-dotapp-js-boot.md)) |
| Empty `.after()` / `alert()` | Toast (DACore: Notiflix) + live list; `redirectTo` only when leaving the page |
| One `<fo-rm>` per row button / drag-and-drop via forms | `type="button"` + encrypted `data-*` + `$dotapp().load()` ([08](08-FORMS-AND-SECURITY.md)) |
| List still clickable / second drag during `load()` | Cover the wrapper (Notiflix preferred **or** module preloaders) until success **and** error — desktop **and** mobile ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Custom OTP / jQuery 2FA digit widget | `$dotapp().twoFactor` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-14](examples/EX-14-auth-and-2fa.md)) |
| `alert()` / `window.confirm()` on delete | Graphical dialog (`Notiflix.Confirm` on admin), then `load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Growing list with no pager / `<a href="?page=">` / `e.currentTarget` | [40](40-DACORE-LIST-PAGER.md): `live(el, e)`, encrypted `data-page`, COUNT |
| Articles/catalog list with no search / JS-filter of `->all()` | **ASK** in the plan; lookup lists **MUST** AJAX search (SQL + `paginate()`, 3+ chars) ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Naked empty table / unvalidated `ORDER BY` / JS-only sort / toast-undo after delete | Empty state **MUST**; sort whitelist; confirm is enough ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| File/ZIP in `FormData` + `load()` / `<fo-rm>` | `$dotapp().uploadFile` + `$request->upload()` ([09](09-DOTAPP-JS-AND-BRIDGE.md)) |
| Desktop-only public header / hover-only nav / no drawer | Overlay drawer L/R; lock page scroll; drawer list scrolls; contacts+compact search in the drawer ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Save / primary button flush to the card or page edge | Padding vs parent (esp. **bottom**); center or match sibling footers ([00](00-AGENT-CONTRACT.md) §2f, [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c) |
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
| `include` another module’s `about.php` / `module.init.php` to list it or pick a template | Read `dacore_modules` / `DACore:Plugins@listByExtra!`; keep `initializeRoutes()` to **this** module ([03](03-MODULES-AND-ROUTING.md), [35](35-DACORE-INSTALL.md) §3c) |
| `initializeRoutes() => ['*']` “so the catalog works” | Own prefixes + `--optimize-modules`. `['*']` only for a user-asked global hook |

## DACore

| Wrong | Right |
|-------|-------|
| Edit files in `app/modules/DACore/` (default) | Current module only; **MUST NOT propose** a DACore edit |
| Add a file / controller / view / asset into `app/modules/DACore/` | Create **your own** module — DACore updates wipe extras |
| Offer “I can patch DACore” | Implement in `app/modules/<YourModule>/` |
| “Quick-fix” a DACore bug in place without an informed ask | Warn (update wipe); proceed **only** if they still insist on editing DACore ([00](00-AGENT-CONTRACT.md) §1) |
| `INSERT INTO dacore_menu ...` | `DACore:Menu@register` |
| `INSERT INTO dacore_notifications ...` | `DACore:Notifications@push` ([37](37-DACORE-NOTIFICATIONS.md)) |
| `Parts\Email::send` / `Config::email` as the default in a DACore module | **ASK** first; then `DACore:Email@send` ([38](38-DACORE-EMAIL.md)) |
| `Parts\Sms` / `SmsProvider` as the default in a DACore module | **ASK** first; then `DACore:Sms@send` ([39](39-DACORE-SMS.md)) |
| Clone SMTP admin pages in your module | Operators use DACore Email senders; your module only **picks** sender/template ids |
| Module-owned inbox table / second bell UI | DACore navbar + `{prefix}/dacore/notifications` |
| `Notifications@push` in `Installation.php` or every request | Call on the event in **your** controller/service — or `Events::on` their `module.{mod}.{name}.hook` from **your** listeners ([41](41-MODULE-HOOKS.md)) |
| Own sidebar with no header (`type => 0`) | One header per module; more only if you need more sections ([31](31-DACORE-MENU.md)) |
| Ten `type => 1` leaves under a header in the global sidebar | **ASK** shared vs module-own; group with `type => 2`, or header + one entry ([31](31-DACORE-MENU.md)) |
| Guess the menu layout on a new DACore module | Ask in chat first — do not scaffold until the user picks |
| Nest groups under a `withMenu` `$menuId` | Branch is one level; inner items are direct children of that id |
| Register a “Return back” menu row | DACore appends it when `$menuId !== ''` |
| Edit/detail admin URL with no active sidebar item | `withMenu` 7th `$currentFile` = registered list URL ([31](31-DACORE-MENU.md)) |
| Menu row per edit/detail URL | One leaf; subpages pass `$currentFile` |
| Extension uninstall `DELETE … LIKE 'Host.%'` | Delete only **your** `menuid` prefix ([31](31-DACORE-MENU.md), [36](36-DACORE-KNOWN-ISSUES.md)) |
| `INSERT INTO dacore_ai_tools ...` | `DACore:AITools@register` |
| Write to `{prefix}users_rights_list` | `DACore:Rights@createRight!` |
| Build your own admin HTML shell | `DACore:Page@withMenu!` |
| Never add CSS because the shell exists / smash a ported chart into a DACore table | Module `$css`/`$js` on `withMenu`; classes `{modulename}_*`; DACore colors |
| `$.ajax` / `$.post` on admin pages (even if jQuery is loaded) | `$dotapp().form` / `$dotapp().load` / `dotbridge` |
| Keep jQuery plugins on a port without asking, or wrap `$.fn` | **Ask**, then **rewrite** as `$dotapp().fn` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §4.C, [EX-15](examples/EX-15-dotapp-js-library.md)). If DACore already ships it, use it. |
| New select/table/modal/toast/date lib without searching DACore | Grep `app/modules/DACore/` (read-only) + your module first; reuse ([33](33-DACORE-PAGES-AND-UI.md)) |
| `#DACore:AuthTest@check!` with rights | Your own `#YourModule:Rights@check!` |
| `Auth::hasRole()` | `Auth::can(['dotapp.root', 'Mod.right'])` |
| Register menu/rights/tools per request | In `Installation.php` |
| Shipping a DACore zip that still has `install.php`, or that lacks `dainstall.php` / `init/` / `about.php` | Rename `install.php` → `dainstall.php` on a **copy**; copy live init into `init/`; inert root stubs; include `about.php`. DACore **rejects** `install.php` and a missing/invalid `about.php`, and **never runs** Installation without `dainstall.php` ([00](00-AGENT-CONTRACT.md) §2e, [35](35-DACORE-INSTALL.md) §3b, §4–§5) |
| `dainstall.php` / inert root **while coding** | Live `install.php` + live init files until the user asks to pack a **DACore** module ([35](35-DACORE-INSTALL.md) §4–§5) |
| `dainstall.php` zip for a module that is not for DACore | Rename `installed_*` → `install.php` and copy the folder ([07](07-SCHEMA-AND-INSTALL.md)) |
| Leaving `installed_*_install.php` after a new version | Rename back to `install.php` so the next load runs it ([07](07-SCHEMA-AND-INSTALL.md)) |
| Applying `dainstall.php` / `init/` / inert stubs to `app/modules/DACore/` | Forbidden — that is the host installer, not a plug-in module |
| Blank root `module.init.php` without an export request | Keep live root files while developing; inert stubs only when the user asks to pack |
| Hardcode `/dacore` | `Config::module("DACore","prefixUrl")` |
| AI tool `rights => []` | Explicit list — empty hides it from everyone |
| AI tool `rights => ['Mod.*']` | No wildcards for AI tools |
| Bootstrap `col-md-6` in admin forms | `<dot-col any="12" md="6" ldesktop="6">` |
| Re-add `dotapp.js` / dotgrid / core.css | The shell already loads them |
| Ignoring `Menu@register` / `AITools@register` / `Notifications@push` return | They return `bool`, never throw or log |
| Dangerous admin action without a second 2FA prompt | Step-up `$dotapp().twoFactor` + **PHP** verifies before persist ([32](32-DACORE-RIGHTS.md) §6) |
| 2FA overlay/modal as the only gate; save writes anyway | PHP refuses without a valid code ([08](08-FORMS-AND-SECURITY.md), [32](32-DACORE-RIGHTS.md) §6) |
| Dangerous flag turned off on the same save as other settings | General save ignores “off”; separate 2FA handler ([32](32-DACORE-RIGHTS.md) §6) |
| Let an operator turn 2FA off | Forbidden — at least one method MUST stay on |
| Write AI tool with no page refresh / `location.reload()` after chat write | `ui_events` + `DACore.AI.UIEvent` listener; filter by tool id ([34](34-DACORE-AI-TOOLS.md) §5) |
| Secrets in `ui_events` payload | Ids and view hints only |

Details: [30](30-DACORE-OVERVIEW.md)–[39](39-DACORE-SMS.md).
