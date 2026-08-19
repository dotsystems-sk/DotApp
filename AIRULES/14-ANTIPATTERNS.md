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
| Edit core to add config API | Use `Config::module` / `Config::set` |

## DACore

| Wrong | Right |
|-------|-------|
| Assume DACore is required | Part 1 modules work without it |
| Call `DACore:*` in bare apps | Only when user requested admin integration |
| Edit DACore files to plug in | Use public `DotApp::call` APIs (Part 2) |
