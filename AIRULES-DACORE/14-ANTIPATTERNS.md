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
| Silent save / empty `.after()` / invent a second toast instead of grepping DACore | Admin: search DACore then **toast**. Public: mark the wrong field ([00](00-AGENT-CONTRACT.md) §2d) |

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
| Collecting results from `trigger()` | returns `$result` unchanged |

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
| Growing list with no pager / `<a href="?page=">` | AJAX buttons + `$dotapp().load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [33](33-DACORE-PAGES-AND-UI.md) §3) |
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
| Edit files in `app/modules/DACore/` (default) | Current module only; **MUST NOT propose** a DACore edit |
| Add a file / controller / view / asset into `app/modules/DACore/` | Create **your own** module — DACore updates wipe extras |
| Offer “I can patch DACore” | Implement in `app/modules/<YourModule>/` |
| “Quick-fix” a DACore bug in place without an informed ask | Warn (update wipe); proceed **only** if they still insist on editing DACore ([00](00-AGENT-CONTRACT.md) §1) |
| `INSERT INTO dacore_menu ...` | `DACore:Menu@register` |
| `INSERT INTO dacore_notifications ...` | `DACore:Notifications@push` ([37](37-DACORE-NOTIFICATIONS.md)) |
| Module-owned inbox table / second bell UI | DACore navbar + `{prefix}/dacore/notifications` |
| `Notifications@push` in `Installation.php` or every request | Call on the event in **your** controller/service |
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

Details: [30](30-DACORE-OVERVIEW.md)–[37](37-DACORE-NOTIFICATIONS.md).
