# 14 — Antipatterns (Wrong vs Right)

Master anti-hallucination table. When unsure, open `app/parts/` (read-only) and AIRULES — never invent Laravel APIs.

## Identity

| Wrong | Right |
|-------|-------|
| “This is basically Laravel” | DotApp is a separate BE+FE framework |
| Copy Blade/Eloquent snippets | Use AIRULES syntax only |
| Edit `app/parts` to “fix” something | Ask user; edit module + `config.php` only |

## Controllers / routing

| Wrong | Right |
|-------|-------|
| `HomeController@index` | `Shop:Home@index!` |
| Instance methods + `$this->` | `public static function` |
| `Route::prefix('x')->group(...)` | Manual prefix / `onPath` / Middleware group |
| Named `route('home')` | Hardcode paths or config prefixes |
| DI params with trailing `!` | No DI params when using `!` |
| Middleware string without `#` for Middleware class | `#Shop:Gate@check!` |

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
| Logs / users / items via `->all()` into the view / no pager because “few rows now” | `->paginate($perPage, $page)` on first ship ([06](06-DATABASE.md)) |
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
| Skip `crcCheck` | Always for DotApp transport |
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

## Config / security

| Wrong | Right |
|-------|-------|
| Leave `YourSuperSecretKey` | Generate `bin2hex(random_bytes(32))` |
| Rely on `@AUTOCONFIG` | Empty — set keys yourself |
| Module settings with no fallback | Always `Config::module ?? Config::module(..., default)` |
| Edit core to add config API | Use `Config::module` / `Config::set` |

## DACore

| Wrong | Right |
|-------|-------|
| Edit files in `app/modules/DACore/` | Public `DotApp::call()` APIs only |
| Add a file / controller / view into `app/modules/DACore/` | Create **your own** module — DACore updates wipe extras |
| “Quick-fix” a DACore bug in place | Refuse; work around it from `app/modules/<YourModule>/` |
| `INSERT INTO dacore_menu ...` | `DACore:Menu@register` |
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
| Hardcode `/dacore` | `Config::module("DACore","prefixUrl")` |
| AI tool `rights => []` | Explicit list — empty hides it from everyone |
| AI tool `rights => ['Mod.*']` | No wildcards for AI tools |
| Bootstrap `col-md-6` in admin forms | `<dot-col any="12" md="6" ldesktop="6">` |
| Re-add `dotapp.js` / dotgrid / core.css | The shell already loads them |
| Ignoring `Menu@register` / `AITools@register` return | They return `bool`, never throw or log |
| Dangerous admin action without a second 2FA prompt | Step-up `$dotapp().twoFactor` + verify in your module ([32](32-DACORE-RIGHTS.md) §6) |
| Let an operator turn 2FA off | Forbidden — at least one method MUST stay on |
| Write AI tool with no page refresh / `location.reload()` after chat write | `ui_events` + `DACore.AI.UIEvent` listener; filter by tool id ([34](34-DACORE-AI-TOOLS.md) §5) |
| Secrets in `ui_events` payload | Ids and view hints only |

Details: [30](30-DACORE-OVERVIEW.md)–[36](36-DACORE-KNOWN-ISSUES.md).
