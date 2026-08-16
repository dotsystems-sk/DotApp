# 15 — Known Issues and Doc Corrections

Verified against the framework source. Agents must design around these.

---

## 1. Dangerous return-value traps

| Issue | Detail | Agent action |
|-------|--------|--------------|
| `execute()` without error callback | **throws `\Exception`** on any DB error | always pass both callbacks |
| `first()` on empty result | RAW: undefined-index warning · ORM: **fatal** on `null->getItem(0)` | use `all()` + `[0] ?? null`, or `exists()` |
| `$execution_data` on cache hit | **empty array** | `$exec['insert_id'] ?? $db->inserted_id()` |
| `Crypto::decrypt` failure | returns **`false`** | compare `=== false` |
| `Cache::load` miss | returns **`null`** | compare `!== null` |
| `Email::send` failure | returns **`string[]`** | compare `!== true` |
| `Auth::login` malformed input | returns **`false`** | check before array access |
| `$request->form` mismatch | `false` (method) / **`null`** (handler) / throws (no error callback) | provide the error callback and guard |
| Missing view/layout | renders **`""`**, only a log warning | use the fallback argument, check for `''` |
| `Entity::save()/delete()` | return **`void`** | never test the return value |
| `trigger()` | returns `$result` unchanged, listener returns ignored | do not collect results from events |
| `Events::on($route, $event, $cb)` | returns **`false` and does not register** on route mismatch | register unconditionally or verify |

---

## 2. Inverted / misleading APIs

| API | Reality |
|-----|---------|
| `Router::hasRoute()` | returns **`false`** when the route *would* match |
| `$request->isValidCSRF($token)` | returns **`true`** when the token is **unused** (i.e. valid) |
| `DSM::status()` | returns **`$this`**, not the PHP session status |
| `Auth::logged()` | listed on the facade but **does not exist** → `\BadMethodCallException`; use `isLogged()` |
| `Renderer::useCache(true)` | calls `cachePageExists`/`cachePageSave` that exist only on legacy `Cache_OLD` → **fatal**; do not enable |
| `Config::db('cache') = true` + ORM | `Entity::save()` needs `deleteKeys()` on the cache driver; **no shipped driver implements it** → throws |

---

## 3. Declared but not implemented

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

## 4. Library-specific bugs

| Area | Issue |
|------|-------|
| MeiliSearch driver | `updateIndexSettings()` broken; facet aggregation corrupts hits |
| Algolia driver | **throws on construction** without `algolia_app_id`; `limit = 0` → division by zero |
| Typesense driver | `limit = 0` → division by zero |
| Redis cache driver | `delete()` / `clear()` use wrong key variables |
| Memcached cache driver | `clear()` **flushes the entire server**, not just your namespace |
| DB session driver | bugs in `regenerate_id` |
| Redis session driver | **throws on construction** if any `redis_*` config is empty; uses `KEYS` scans |
| `dotapp.js` `.last()` / `.nth()` | buggy — use `.get(i)` / `.all()` |
| `Collection::load()` | success callback arity mismatch |
| `Entity::find()` | callback assumes a RAW row shape |
| Email 2FA code generation | gated on `tfa_auth` instead of `tfa_email` |
| `Module::di()` / `Controller::di()` | no return value — use `call()` |
| `Api.php` legacy dispatch | invokes the handler method twice |
| Template sandbox | dangerous functions are **silently stripped** — a call simply does nothing |
| `removeUnusedCss(true)` | deletes rules for JS-added/dynamic classes and overwrites cache CSS |
| `dotapper.php` | universal `exit(1)` after commands; `--create-example-module` references a missing template |
| Bridge storage limit | `Config::bridge('storage_limit')` (200) evicts oldest listeners FIFO — many bridge buttons on one page can stop working |
| HTTP 405 | not implemented anywhere |
| 404 fallback | ends with `die()` if no listener/error view is registered |

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

---

## 6. Conflict resolution order

1. `app/parts/*.php` source
2. AIRULES
3. Module guides
4. Stock root guides / `.cursorrules`
