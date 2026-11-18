# 15 — Known Issues and Doc Corrections

Verified against the framework source. Agents must design around these.

---

## 1. Inverted / misleading APIs

| API | Reality |
|-----|---------|
| `Router::hasRoute()` | returns **`false`** when the route *would* match |
| `$request->isValidCSRF($token)` | returns **`true`** when the token is **unused** (i.e. valid) |
| `DSM::status()` | returns **`$this`**, not the PHP session status |
| `Auth::logged()` | listed on the facade but **does not exist** → `\BadMethodCallException`; use `isLogged()` |
| `Renderer::useCache(true)` | calls `cachePageExists`/`cachePageSave` that exist only on legacy `Cache_OLD` → **fatal**; do not enable |
| `Config::db('cache') = true` + ORM | `Entity::save()` needs `deleteKeys()` on the cache driver; **no shipped driver implements it** → throws |

---

## 2. Declared but not implemented

| API | Status |
|-----|--------|
| `DB::migrate()` | no driver implementation — use `Installation.php` |
| `Databaser::whereHas / whereDoesntHave / withCount / with()` | stored in state, **never reach SQL** |
| `SchemaBuilder::timestamps()` | **does not exist** — declare columns manually |
| `QueryBuilder::count() / selectRaw / whereExists / whereColumn` | do not exist |
| `RequestObj::headers()` | does not exist |
| `Response::send() / static status()` | do not exist |
| AI tool/function calling, AI streaming | not implemented |
| MCP tool `authentication` | stored, **never enforced** — guard the route yourself |
| MCP HTTP route | none — register it yourself |
| FastSearch `'default'` driver | **nothing registers it** — `Config::searchDriver(...)` is mandatory |
| SMS providers | interface only, **no concrete provider ships** |
| Auth password reset / login lockout / roles | tables exist, **no core flow**; `hasRole()` unusable |
| Email `fromName` / `replyTo` / mark-as-read | not supported |
| `Injector::bind()` / `singleton()` | broken (typo `dotAapp()`) — use `$dotApp->bind()` |

---

## 3. Library-specific bugs

| Area | Issue |
|------|-------|
| MeiliSearch driver | `updateIndexSettings()` broken; facet aggregation corrupts hits |
| Algolia driver | **throws on construction** without `algolia_app_id`; `limit = 0` → division by zero |
| Typesense driver | `limit = 0` → division by zero |
| Redis cache driver | `delete()` / `clear()` use wrong key variables |
| Memcached cache driver | `clear()` **flushes the entire server**, not just your namespace |
| DB session driver | bugs in `regenerate_id` (`session_id($new)` and sliding expiry are fixed) |
| Redis session driver | **throws on construction** if any `redis_*` config is empty; uses `KEYS` scans |
| `Collection::load()` | success callback arity mismatch |
| `Entity::find()` | callback assumes a RAW row shape |
| Email 2FA code generation | gated on `tfa_auth` instead of `tfa_email` |
| `Module::di()` / `Controller::di()` | no return value — use `call()` |
| `Api.php` legacy dispatch | invokes the handler method twice |
| Template sandbox (calls) | dangerous functions are **silently stripped** — a call simply does nothing |
| Template sandbox (vars) | a callable variable name or nested value (`time`, `copy`, `count`, `key`, …) silently drops that whole bag | prefix keys; do not patch Renderer ([05](05-VIEWS-TEMPLATES-ASSETS.md) §5) |
| `removeUnusedCss(true)` | deletes rules for JS-added/dynamic classes and overwrites cache CSS |
| `dotapper.php` | universal `exit(1)` after commands; `--create-example-module` references a missing template |
| Bridge storage limit | `Config::bridge('storage_limit')` (200) evicts oldest listeners FIFO — many bridge buttons on one page can stop working |
| HTTP 405 | not implemented anywhere |
| 404 fallback | ends with `die()` if no listener/error view is registered |

---

## 4. Fixed in core 2026-11-14 — what changed for module code

These were bugs. On this core they work as written below. On a project with an older core, **update the framework**; do not write a workaround.

| API | Behaviour now |
|-----|---------------|
| `q(...)->exists()` / `doesntExist()` | Run the query given to `q()` (before: the previous query's builder) |
| `paginate($perPage, $page)` | Returns the real `total` and `last_page` (before: `total` was always 0) |
| `where('col', '=', null)` / `'!='` | `col IS NULL` / `col IS NOT NULL` |
| `where('col', null)` — two arguments | `col IS NULL`. That matches every row where the column is NULL. Check the PHP value first and refuse; do not run the query ([06](06-DATABASE.md) Where) |
| `where('col', '>', null)` and other comparisons with `null` | `col > NULL` — matches nothing |
| `where('col', $value)` — two arguments, not `null` and not a list | `col = ?` (before: the value was dropped). `''` and `'like'` are values, not a raw condition or an operator |
| `where('col', [1, 2])` / `where('col', 'IN', [1, 2])` | `col IN (?, ?)` |
| First `where()` on an expression — `where('DATE(created)', …)` | Kept intact (before: leading `A` `N` `D` `O` `R` letters were stripped) |
| `whereIn('col', [])` | `1 = 0` — matches nothing (before: `IN ()` syntax error) |
| `select('t.*')` | `` `t`.* `` (before: `` `t`.`*` ``) |
| `limit('10')` / `offset('20')` | Bound as integers. A second call keeps the last value. `offset()` without `limit()` is valid on MySQL and SQLite. `resetLimitOffset()` removes only LIMIT and OFFSET |
| Clause order (`limit` before `where`, `set` after `where`) | Same SQL and the same bindings. Placeholders are emitted in clause order, not call order |
| `having()` called twice | Joined with `AND` |
| `$request->data(true)` | The original on every call, including the first read of the body |
| `Cache::use('A')` and `Cache::use('B')` | Separate keys. `clear()` of the file driver still empties the whole cache **folder** by design — give a cache its own folder if it must be cleared alone |
| `setView('Module:name', $fallback)` / `setLayout(…)` | The fallback is used (before: ignored with the `Module:` prefix) |
| View / layout variable named `code` | A normal variable (before: it replaced the template source). `this`, `vars`, `canrewrite`, `recursively_noncallable_check` are still skipped |
| `{{ formName(x) }}` with several forms on one page | Takes `method` / `action` from **its own** `<fo-rm>` (before: from the first form of the page) |
| `Validator` `numeric` / `number` | Accepts integers (`42`, `"42"`) |
| `Validator` `required`, `in` | Accept numbers from a JSON body (`5`, `in:1,2` for `1`) |
| `Validator` `regex:` | May contain a comma (`regex:/^[a-z]{2,4}$/`). A `\|` inside the pattern is still impossible — it separates rules |
| `Validator::isIpAddress()` | Accepts compressed IPv6 (`::1`) |
| `Response::cookie()` / `removeCookie()` | `expires`, `path`, `domain`, `secure`, `httponly`, `samesite` reach the browser; removal works |
| Middleware chain | A middleware that returns a `Response` stops the chain (before: only the last one could) |
| `Auth::login()` | A database fault returns `error => 99` (before: `error => 0`) |
| `Auth::autoLogin()` | Logs in the owner of the token and only while `expires_at` is in the future |
| `TOTP::generate()` | Accepts lowercase / spaced / unpadded secrets and `SHA256` in any case |
| `DotApp::hasListener()` | Case-insensitive, like `on()` and `trigger()` |
| `Module::settings()` writes | Return `false` when the file could not be written |
| `php dotapper.php --test` | Runs (before: died silently with exit 255 on a duplicate class in `app/tests`) |
| `dotapp.js` `.find()` `.children()` `.siblings()` `.clone()` `.last()` `.nth()` `.length`, `uploadFile` progress | Work ([26](26-CONTROL-SLICE.md) §4) |
| `dotapp.js` `bridge().onResponseCode()` | Several status codes can be registered; a handled code no longer also fires `onError` |
| `dotapp.js` `sendInput().before()` | Runs before the request (a `false` return cancels it); `onError` fires once per failure |
| `dotapp.js` `live()` with `bodychange` / `attrchange` / `resizechange` / `positionchange` / `visibilitychange` | Attaches the handler |
| `dotapp.js` `check([...])` on a `<select multiple>`, `hashRouter()` for the hash the page loaded with, `computed()` on a selector instance, `twoFactor` invalid class | Work |
| `dotapp.js` `validator.isUrl()` / `isUuid()` | Accept query strings, ports and any UUID version; no slow regular expression |
| `dotapp.template.js` | `{{ if }}` / `{{ elseif }}` / `{{ else }}` render exactly one branch, nested `{{ if }}` and nested `{{ foreach }}` work, trailing text is no longer doubled |
| `dotapp.reactive.js` | `reactive-variable` updates its variable, `reactive-template` renders, retries stop after the configured maximum and then call `onError` |

---

## 5. Leftover documentation (older installs)

These files are **no longer shipped**. If an older project still has them, ignore them and follow AIRULES.

### Leftover `.cursorrules`

| Teaches | Reality |
|---------|---------|
| `{{ $variable }}` | **`{{ var: $variable }}`** |
| `{{ endif }}` / `{{ endforeach }}` | **`{{ /if }}` / `{{ /foreach }}`** |
| "never edit `app/config.php`" | AIRULES: `config.php` **is** the only editable core file |
| ask before using dotapper | AIRULES: **prefer** dotapper for scaffolds |

### Leftover `database_guide.md` / `ai_database_guide.md`

Ignore claims of: `DB::getConnection()`, `selectRaw()`, `whereExists()`, `whereColumn()`, chained `->find(123)`, a 5-argument `join()`, a public `$entity->validate()`, and "ORM requires PDO" (both drivers support ORM).

### Leftover module `*_AI_guide.md`

DotApper no longer generates these. If an older module still has one, ignore it and follow AIRULES.

| Claim | Reality |
|-------|---------|
| layout vars available in `renderView()` | final eval uses **view vars only** |
| view vars work for `renderLayout()` | use `setLayoutVar` |
| closing `{{ /layout }}` | not supported — layout tags are includes |

### Secure form naming

Docs/users sometimes say `f-form`. The real tag is **`<fo-rm>`**.

`{{ formName }}` left as text almost always means the opening `<fo-rm>` contains `{{ var: }}` (or any `{{ }}`). That compiles to `<?php … ?>` before the form pass; the `>` in `?>` hides `method`. Putting `method` before the variable still drops `action` onto the current page. Write both attributes as literal text. Body tags are unaffected. [08](08-FORMS-AND-SECURITY.md).

---

## 6. Conflict resolution order

1. `app/parts/*.php` source
2. AIRULES
3. Module guides
4. Stock root guides / `.cursorrules`
