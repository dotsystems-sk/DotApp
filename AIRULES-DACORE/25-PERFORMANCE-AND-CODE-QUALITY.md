# 25 — Performance, schema design and readable code (hard law)

Correct + secure is not enough. Code you ship **MUST** also be **cheap** (I/O, memory, CPU) and **readable by a human six months later**. This file is the canonical source for: algorithm/memory rules, I/O minimisation, **index and schema design**, and the **comment / documentation standard**.

**How to use it (cheap):** writing a loop or a transform → §1. Any query → §2. Creating or altering a table → §3 + §4. A list screen → §2 + §5. JS/CSS → §6. **Every** file you write → §7. Then the perf pass in §8.

---

## 0. Four root laws

1. **The smallest I/O wins.** Disk, DB, and network round-trips cost orders of magnitude more than CPU or a tight `if`. The OS page cache hides some of it — **MUST NOT** rely on that. Ask “what is the least data that answers this question?” and fetch exactly that ([06](06-DATABASE.md)).
2. **Memory is bounded, input is not.** Anything that grows with the table (rows, files, uploads, AI text, log lines) is processed **page by page**, never “load it all and filter”. One request **MUST NOT** be able to allocate an unbounded array.
3. **Readable beats clever.** Optimise the code path that **scales** (a loop over the whole table, a query per row, an index that is missing). **MUST NOT** obfuscate a 20-row loop to save microseconds. Every optimisation that is not obvious gets a **`// Why:`** comment (§7).
4. **Optimise your module, not DACore.** Reuse what DACore already exposes (`DotApp::call("DACore:…")`, its assets, its page shell). **MUST NOT** patch, extend, or duplicate `app/modules/DACore/` for performance, and **MUST NOT** read or write `dacore_*` tables directly ([00](00-AGENT-CONTRACT.md) §1).

**PHP 7.4+:** module PHP **MUST** stay on the DotApp floor unless the plan named a higher version ([00](00-AGENT-CONTRACT.md) §2i). Typed properties and `fn` are fine. `match`, `?->`, union/`mixed` types, named arguments, constructor promotion, attributes, `enum`, `readonly`, and `str_contains` / `str_starts_with` / `str_ends_with` are **not**.

---

## 1. Algorithms and memory (**MUST**)

| Situation | Do (**MUST**) | **MUST NOT** |
|-----------|---------------|--------------|
| “Is this id in that set?” | build a **keyed map** once (`$byId[$row['id']] = $row;`) and use `isset()` — O(1) | `in_array()` / a nested `foreach` inside a loop — O(n²) |
| Joining two result sets in PHP | one pass to key the smaller set, one pass to attach | a loop inside a loop |
| Growing a list | `$out[] = $item;` | `array_merge($out, [$item])` in a loop (copies the whole array each turn) |
| Several transforms of a big array | **one** `foreach` that does all of it | chained `array_map` → `array_filter` → `array_map` (a full copy per step) |
| A set that can be large | process in **pages** (`paginate()` / `limit` + a keyset `where`), or a `yield` generator | `->all()` on the whole table, then filter |
| Big intermediate data | `unset($rows);` once mapped; keep **one** representation | keeping the raw rows **and** the mapped copy alive |
| Mutating rows in place | `foreach ($rows as &$row)` (then `unset($row)`) | rebuilding a second full array just to add one key |
| Reading a file | stream it (`fopen` + `fgets` / `fread` in chunks) | `file_get_contents()` on something that can be big |
| Building a long string | collect parts in an array + `implode()` | string concatenation inside nested loops with no need |
| Sorting / filtering DB data | `ORDER BY` + `WHERE` in SQL (index-backed) | fetch, then `usort()` / `array_filter()` in PHP |
| Repeating an expensive pure call | compute once into a local variable before the loop | recomputing `Config::…`, a regex compile, or a date format per row |
| Anything from the client | an explicit **cap** (max rows, max length, max files) | trusting that “nobody would send 100 000 items” ([24](24-ATTACK-VECTORS.md) §7) |

**Complexity rule:** before writing a nested loop, ask what `n` is. `n` = a page (≤ 100) → any readable code is fine. `n` = the table, the log, the upload → it **MUST** be linear and paged.

---

## 2. I/O minimisation (**MUST**)

**Budget per screen:** a list = **one** query (`paginate()` already returns the total). A detail = **one** query, plus one per related list. Anything more needs a reason in a comment. The admin shell (menu, rights, notifications) is DACore’s cost — do not add your own copy of it.

| Need | Do (**MUST**) | **MUST NOT** |
|------|---------------|--------------|
| Does it exist? | `exists()` | `->all()` then `count($rows)` |
| How many? | `select('COUNT(*) as total')` + `all()` ([40](40-DACORE-LIST-PAGER.md) §4) | fetch rows to count them; trust `paginate()['total']` |
| One row | `where(...)->limit(1)` + only the columns you use | `select('*')` for a list screen |
| Related data for N rows | **one** prefetch with `whereIn('id', $ids)`, keyed by id | a query inside `foreach` (**N+1**) |
| Same data twice in one request | a local variable / a `static` memo in the helper | running the identical query again |
| Expensive data shared across requests | `Cache` with a TTL **and** invalidation on write ([20](20-CACHE-LOGGER-SESSION.md)) | caching a `WHERE id = ?` lookup; `Config::db('cache')` “for speed” ([06](06-DATABASE.md) §8) |
| Per-user state | `DSM::use('Shop')` | a DB round-trip on every page for something session-shaped |
| Menu / rights / notifications | what DACore exposes, once per page render ([31](31-DACORE-MENU.md), [32](32-DACORE-RIGHTS.md), [37](37-DACORE-NOTIFICATIONS.md)) | your own `SELECT` on `dacore_*`; a rights query per row |
| Inbox notifications | `DACore:Notifications@push` **on the event** | pushing from `Installation.php` or on every request ([37](37-DACORE-NOTIFICATIONS.md)) |
| Many inserts | **one** transaction around the loop, or one `raw()` with a generated `VALUES (?,?),(?,?)` list (placeholders counted exactly — [06](06-DATABASE.md)) | one auto-committed insert per row (`insert()` is single-row) |
| Insert-or-update | `onDuplicateKeyUpdate()` | `SELECT` then branch to `INSERT`/`UPDATE` |
| Counters / “last activity” | one `UPDATE … SET x = x + 1` | read, add in PHP, write back (also a race — [24](24-ATTACK-VECTORS.md) §4) |
| Logging in a loop | aggregate, log **once** after the loop | `Logger` per row |
| Notify other modules | **one** `module.{mod}.{name}.hook` after a **useful** side-effect (or one **batch** after the loop) | `Events::trigger` **inside** `foreach` of a growing list; a hook on every save; skip a named SMS/mail hook “for perf” ([41](41-MODULE-HOOKS.md)) |
| Another module’s files / boot | DACore `dacore_modules` / `Plugins@listByContract!` / `@listByExtra!` / that module’s own matching route | `include` another module; `glob(app/modules)` on a request; `initializeRoutes() => ['*']` without a global job ([03](03-MODULES-AND-ROUTING.md), [35](35-DACORE-INSTALL.md) §3c, [46](46-DACORE-EXTRA-CONTRACTS.md)) |
| External API | one `HttpHelper::request` with a timeout; retry only transient ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md)) | a call per row |

### The N+1 pattern you MUST use

```php
// One page of orders, then ONE query for all their customers.
// Keyed map instead of a query per row: 2 queries instead of 1 + N.
$page = DB::module("RAW")->q(function ($qb) use ($perPage, $pageNo) {
    $qb->select('id, customer_id, total, created_at')->from('shop_orders')
       ->orderBy('created_at', 'DESC');
})->paginate($perPage, $pageNo, function ($e) { /* log + generic message */ });

$customerIds = array_values(array_unique(array_column($page['data'], 'customer_id')));

$customers = [];
if ($customerIds !== []) {
    $rows = DB::module("RAW")->q(function ($qb) use ($customerIds) {
        // Only the columns the list prints — no SELECT *.
        $qb->select('id, name')->from('shop_customers')->whereIn('id', $customerIds);
    })->all();
    foreach ($rows as $row) {
        $customers[(int) $row['id']] = $row['name'];   // O(1) lookup below
    }
}
```

---

## 3. Index design (**MUST**)

An index is not decoration: **every** column a growing table is filtered, joined, or ordered by **MUST** be reachable by an index. A `WHERE` with no index = a full table scan = the slowest thing your module does.

API: `$t->index($columns, $name = null)`, `$t->unique($columns, $name = null)`, `$t->fullTextIndex($columns, $name = null)` (MySQL/PostgreSQL), `$t->foreign(...)`, `$sb->indexExists($table, $name)` — [07](07-SCHEMA-AND-INSTALL.md). `$columns` accepts a **string or an array** (array = composite). Auto names are `idx_a_b` / `uniq_a_b`; pass an explicit name when you may need `dropIndex()` later. Index only **your** tables — never alter `dacore_*` / `users_rights*`.

| Rule (**MUST**) | Why |
|-----------------|-----|
| Index every **foreign key** column | joins and `WHERE parent_id = ?` are the most common queries |
| Index (or cover with a composite) every column in `WHERE`, `JOIN`, `ORDER BY` on a table that grows | otherwise every admin page scans the table |
| **Composite order = equality → range → sort** | `idx(user_id, created_at)` serves `WHERE user_id = ? ORDER BY created_at DESC` in one shot |
| Respect the **leftmost prefix** | `idx(user_id, created_at)` also serves `WHERE user_id = ?`, but **not** `WHERE created_at > ?` alone |
| Prefer **one good composite** over three single-column indexes | and **MUST NOT** add a single-column index that is already the leftmost prefix of a composite |
| `unique()` on natural keys (`email`, `slug`, `(module, key)`) | correctness **and** it is the lookup index — do not add a second plain index on the same columns |
| Low-cardinality flags (`active`, `type`) belong **inside** a composite, in front of the selective column (`idx(active, created_at)`) | an index on a 2-value column alone is never used |
| Keep it to a **handful** of indexes per table, each serving a real query | every index slows `INSERT`/`UPDATE` and costs disk + cache |
| Text search: `fullTextIndex()` (or `LIKE 'term%'`) | `LIKE '%term%'` **cannot** use a normal index — when the product needs infix search, **tell the user** it will scan and offer FULLTEXT / FastSearch ([22](22-AI-SEARCH-MCP.md)) |
| Write **one comment line above each index** naming the query it serves | the next agent must not delete or duplicate it blindly |
| Adding an index later = a **new version** in `Installation.php` + `alterTable` guarded by `indexExists()` | migrations are the only way schema changes reach existing installs ([07](07-SCHEMA-AND-INSTALL.md), [35](35-DACORE-INSTALL.md)) |
| Verify, do not guess: run the real query with `EXPLAIN` through `raw()` in dev when the plan matters | “I added an index” is not evidence it is used |

```php
$qb->createTableIfNotExist('shop_orders', function ($t) {
    $t->id();                                     // BIGINT AUTO_INCREMENT PK
    // FK type MUST match the referenced id() column, or the constraint fails.
    $t->bigInteger('user_id')->unsigned();
    $t->string('status', 20)->nullable(false)->default('new');
    $t->decimal('total', 10, 2)->default(0);
    $t->datetime('created_at');

    // Orders of one customer, newest first (the list screen + the API).
    $t->index(['user_id', 'created_at'], 'idx_shop_orders_user_created');
    // Admin filter "open orders by date" — status is low-cardinality, so it leads.
    $t->index(['status', 'created_at'], 'idx_shop_orders_status_created');
});
```

---

## 4. Schema design (**MUST**)

| Rule (**MUST**) | **MUST NOT** |
|-----------------|--------------|
| Tables named `{lowercase_modulename}_*` ([07](07-SCHEMA-AND-INSTALL.md)) | unprefixed / `dotapp_*` / `dacore_*` names for module data |
| Smallest correct type per column: realistic `VARCHAR` length, `tinyInteger` / `boolean` flags, `integer` where `bigInteger` is pointless | `string($name)` (defaults to 255) for a 20-char status; a `text` column you always read in lists |
| `id()` is **always** `BIGINT AUTO_INCREMENT` (its `$defaultType` argument is ignored) — FK columns pointing at it **MUST** be `bigInteger()->unsigned()` | mismatched FK types (the constraint fails, or the join cannot use the index) |
| Money in `DECIMAL(10,2)`, times in `DATETIME` (UTC unless the product says otherwise) | `FLOAT` for money, a string for a date |
| `NOT NULL` + a sensible default; `NULL` only when “unknown” is a real state | nullable everything (kills index usefulness and forces `IS NULL` branches) |
| Anything you filter, join, or sort by gets its **own column** | a JSON / serialised blob you later need to search |
| `created_at` (+ `updated_at` when it is shown or sorted) declared **manually** — `timestamps()` does not exist ([07](07-SCHEMA-AND-INSTALL.md)) — and indexed when used for ordering | ordering a list by an unindexed column |
| A denormalised counter only when reads dominate **and** it is written in the same transaction | a cached count that silently drifts |
| Split rarely-read `TEXT` into a side table when the main table is listed often | a 60-column table where the list query drags blobs through cache |
| `utf8mb4` (emoji/diacritics safe) | `utf8` (3-byte) on new tables |

---

## 5. Lists at scale (**MUST**)

- `paginate()` on the first ship, plus a **cap** on `per_page` (a client asking for 100 000 rows is [24](24-ATTACK-VECTORS.md) §7).
- `select` only the columns the row prints. A list query that drags `TEXT` bodies is the usual cause of a slow admin.
- Deep pages: `LIMIT … OFFSET 200000` still walks 200 000 rows. For log-like tables prefer **keyset** paging (`WHERE id < :lastId ORDER BY id DESC LIMIT :n`) and say so in a comment; for normal admin lists `paginate()` is fine.
- `COUNT(*)` over a huge table is not free — run it once per list request (same as [40](40-DACORE-LIST-PAGER.md) §4). **MUST NOT** reuse `QueryObject::paginate()['total']` (often 0). For very large log tables consider counting only on the first page.
- Send back **only what changed**: patch the affected rows + the pager from JSON ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [33](33-DACORE-PAGES-AND-UI.md) §3). **MUST NOT** re-render the whole table or reload the admin shell after one toggle.
- Never base64 an image into a JSON reply.

---

## 6. Frontend cost (**MUST**)

**Search DACore first** ([33](33-DACORE-PAGES-AND-UI.md)): the shell already ships jQuery, Notiflix, charts, tables and other libraries. Loading a second library that does the same job is the most expensive mistake on an admin page.

| Rule (**MUST**) | **MUST NOT** |
|-----------------|--------------|
| Reuse a DACore asset when it exists; otherwise **one** CSS + **one** JS in **your** module’s `assets/` | a duplicate library, a file per widget, or a CDN `<script>` ([24](24-ATTACK-VECTORS.md) §10) |
| Delegate events on a stable parent (`live`) — one handler for the whole list | binding a handler per row, then rebinding after every AJAX patch |
| Cache the lookup in a variable: `const $list = $dotapp('#list');` | re-querying the same selector inside a loop |
| Build the fragment, then write to the DOM **once** | appending inside a loop (layout thrash) |
| Debounce search / resize input (≈250 ms, 3+ chars) | a request per keystroke |
| Images: real dimensions + `loading="lazy"` where below the fold | full-size photos scaled down by CSS |
| Read layout (`width`, `offset*`) **before** a batch of writes | alternating read/write in a loop |

---

## 7. Professional code and comments (**MUST**)

The next reader is a human (or a weaker agent) with no memory of this chat. Code **MUST** be skimmable: a **PHPDoc purpose sentence** says *what the function is for*, tags say *shape*, labeled inline comments say *where / what this block is / why this step*.

**MUST — layers of documentation:**

1. **File / class docblock:** what this file owns, which module, the traps that apply (CRC once, encrypted ids, rights guard, DSM). New PHP files also carry the identity header from [00](00-AGENT-CONTRACT.md) §6.
2. **Method PHPDoc** on every public/static method **and** every private/protected helper that is more than a one-line getter — **`CRCchecking —` first** on every public method in `Controllers/` and `Middleware/` (see below), then a **purpose sentence**, then tags.
3. **Labeled inline comments** — the keyword is **part of the law** (so a reader can grep). English. Place each kind where it orients; **MUST NOT** stack all three on every line.

| Keyword | Meaning | Where (**MUST**) |
|---------|---------|------------------|
| `// Why:` | Why this **decision** exists (guard, formula, trap) — **not** what the next line does | Above every **logical step** (same places agents already comment). The label **MUST** be `Why:` — a bare `// turning SMS off…` is incomplete |
| `// About:` | What this **chunk** is: creates a DB row; that row **represents** X | Once per action / library method / non-obvious block — not on every guard |
| `// Section:` | Which **admin menu** or **route** this code belongs to | File or action that serves a page (DACore leaf or public URL) |

### CRCchecking first line (**MUST** — law)

`$request->crcCheck()` is **one-shot**. The first valid call **burns** the token; a second call in the same request returns `false` and the FE looks broken ([08](08-FORMS-AND-SECURITY.md)). The next reader **MUST** see **where** CRC already ran **without** opening `module.init.php`.

**MUST:** every **public** method in `Controllers/` and `Middleware/` starts its PHPDoc with this **first line** (immediately after `/**`):

```
CRCchecking — <where it runs>. <what this method MUST / MUST NOT do>.
```

Write the **real** middleware / prefix / route — not the word “middleware”.

| First line (shape) | When |
|--------------------|------|
| `CRCchecking — prefix `#DACore:AuthTest@LoginAndCRC!` on POST `/api/v1/auth/Shop/*` (`module.init.php`). This action MUST NOT crcCheck().` | Versioned logged-in POST API — CRC already ran in `before` |
| `CRCchecking — prefix `#DACore:AuthTest@CRC!` on POST `/api/v1/noauth/Shop/*` (`module.init.php`). This action MUST NOT crcCheck().` | Versioned public POST API |
| `CRCchecking — prefix `#Shop:Gate@loginAndCrc!` on POST `/api/v1/auth/Shop/*` (`module.init.php`). This action MUST NOT crcCheck().` | Module-own Gate CRC (no DACore AuthTest on that route) |
| `CRCchecking — this action (`$request->crcCheck()` once). No CRC prefix on this route.` | Isolated POST with no API CRC prefix ([EX-01](examples/EX-01-secure-form-complete.md)) |
| `CRCchecking — this middleware (`$request->crcCheck()`). Actions under this prefix MUST NOT crcCheck().` | The Gate / CRC middleware method itself |
| `CRCchecking — none (GET HTML). CRC is forbidden on GET.` | Page render (admin or public) |
| `CRCchecking — none (`$request->upload()`). CRC is forbidden on upload.` | File endpoint |
| `CRCchecking — none (not a route).` | Public helper on the controller that is not an HTTP entry |

**MUST NOT:** omit the line on a controller/middleware public method; name a prefix/middleware **and** call `crcCheck()` in the same method (double CRC); write `this action` when a CRC `before` (`AuthTest@CRC!` / `LoginAndCRC!` / Gate) already covers the route; put CRC on GET or upload; invent a middleware name that is not in `module.init.php`.

Finish gate: grep `CRCchecking` vs `crcCheck(` in the same method ([00](00-AGENT-CONTRACT.md) §2c).

### PHPDoc purpose (**MUST** — law)

Tags **without** a description are a **bug**. A file of functions that only show `@param` / `@return array<string, mixed>` cannot be scanned — the reader still has to reconstruct what each method is **for**.

**Required order:**

1. **`CRCchecking — …`** — first line on every public method in `Controllers/` and `Middleware/` (see above). Other classes skip this line.
2. **Summary sentence** (English) — what this function is for. **MUST** exist even on a short helper.
3. Optional extra paragraph — when it runs, surprising behaviour, what it does **not** do.
4. `@param` — type **and** meaning (not only `string $id`).
5. `@return` — type **and** meaning (not only `array<string, mixed>` / `void`).
6. `@throws` when it throws.

```php
/**
 * CRCchecking — prefix `#DACore:AuthTest@LoginAndCRC!` on POST `/api/v1/auth/Shop/*` (`module.init.php`). This action MUST NOT crcCheck().
 *
 * Toggles the "active" flag of one item owned by the current user.
 *
 * Decrypts the posted id, checks ownership in SQL, and returns JSON for the
 * live list patch. Encryption is not authorization — the WHERE still scopes
 * to the owner.
 *
 * @param  Request $request POST with an encrypted `id` field.
 * @return Response JSON: status + message (+ the patched row HTML).
 * @throws \Throwable Only from the DB layer; caught and logged inside.
 */
public static function toggle($request)
{
    // Section: DACore → Shop → Items (`{prefixUrl}/Shop/items`)
    // About: flip shop_items.active for one catalog product the storefront lists.
    // Why: the id arrives encrypted, so the actor cannot enumerate rows —
    // the ownership check still runs in SQL (encryption is not authorization).
    $id = Crypto::decrypt($request->data(true)['id'] ?? '', 'Shop.item.id');
    if ($id === false) {
        return self::reply($request, 0, 'This item is no longer available.');
    }
    // Why: turning the flag off is a persist, not a GET — CRC and rights already ran.
    // ...
}
```

**Section** examples: `DACore → Users → Two-factor (`{prefixUrl}/dacore/users/{id}/two-factor`)` · `Public: POST /api/v1/auth/Shop/checkout`.

**About** examples: `insert one shop_items row; it is the catalog product the public site lists` · `send the 2FA SMS after PHP verified the actor`.

A `Events::trigger('module.…hook'` needs its **own** five-line `Hook:` / `Why:` / `About:` / `Params:` / `Use:` block ([41](41-MODULE-HOOKS.md) §3) — that does not replace `Section:` on the action.

| Wrong (fail the gate) | Right |
|-----------------------|--------|
| Controller/middleware public method with no `CRCchecking —` first line | First line names the **real** prefix/middleware/action/`none` |
| `CRCchecking — prefix … MUST NOT crcCheck()` **and** `$request->crcCheck()` in the body | Prefix **XOR** action — never both |
| `/** @return array<string, mixed> */` only | `CRCchecking —` (if a route) then purpose sentence **above** the tags |
| `@return mixed` / `@return array` with no meaning | `@return array{id:int,title:string}\|null` plus “null when missing or forbidden” |
| Docblock that restates the method name (`toggleItem` → `Toggles item.`) | Say **what in the product** it changes and **who** it is for |
| Prompt-echo in PHPDoc (`As requested, this saves…`) | Product-facing purpose, same tone as comments |

**MUST NOT:**

- Restate the code (`// increment the counter`, `// return the result`, `// import the class`).
- Prompt-echo (`// as requested`, `// per the audit`, `// this user can…`) — comments describe the code, not the chat.
- Leave commented-out blocks, dead branches, or a bare `TODO` with no reason and no owner.
- Ship names like `$a`, `$tmp`, `$data2`, or a 200-line method. Split at roughly 50 lines / one responsibility; use early `return` instead of nesting four levels deep.
- Repeat the same 15 lines in three actions — extract a `private static` helper in the same controller.
- Bury a magic number (`if ($x > 86400)`) — name it (`const TOKEN_TTL_SECONDS = 86400;`).

**Style:** English comments; `StudlyCase` classes, `camelCase` methods/variables, `snake_case` DB columns; CSS classes `{lowercase_modulename}_*` ([33](33-DACORE-PAGES-AND-UI.md)); follow the surrounding module’s existing formatting rather than introducing a new one. User-visible strings stay product copy ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8).

---

## 8. Perf / quality pass (**MUST** — run it, do not imagine it)

Part of the finish gate ([00](00-AGENT-CONTRACT.md) §2c). Grep **your module + the diff**.

| # | Grep / look at | Fail if |
|---|----------------|---------|
| 1 | `->all()` | a table that can grow is dumped without `paginate()` / `limit` |
| 2 | `foreach` blocks | a query, an HTTP call, a rights check, a `Logger` line, or `Events::trigger` **inside** the loop of a growing list (N+1 / N events) |
| 3 | `select('*')`, `select("*")` | a list screen fetching columns it never prints |
| 4 | `in_array(`, `array_merge(`, `array_map(` | O(n²) lookup or a full array copy per iteration on data that scales |
| 5 | new `where(` / `orderBy(` columns | the column is not covered by an index (leftmost prefix counts) → add it in a **new** `Installation.php` version |
| 6 | `createTable` / `alterTable` in the diff | an index with no comment naming its query; a duplicate of a composite prefix; `VARCHAR(255)`/`FLOAT` money; missing `NOT NULL` default; a touch on `dacore_*` |
| 7 | `Cache::`, `Config::db('cache')` | caching something cheap, no TTL, or no invalidation on write |
| 8 | `file_get_contents(`, `json_encode(` | a whole big file / an unbounded payload in memory |
| 9 | `$css`, `$js`, new assets | a library DACore already ships; a second file that could be one |
| 10 | new public methods | no PHPDoc **purpose sentence**; tags-only (`@return array<string, mixed>` with no description); missing `@param` / `@return` meaning; a `Controllers/` / `Middleware/` public method whose PHPDoc does **not** start with `CRCchecking —`; that line names a CRC prefix **and** the body still calls `crcCheck(` |
| 11 | the diff, comment by comment | comments that restate the code or echo the prompt; a logical step with no `// Why:`; a new page action with no `// About:` / `// Section:`; leftover `TODO` / dead code |
| 12 | `match (`, `?->`, `str_contains(`, `str_starts_with(`, `str_ends_with(`, `#[`, `enum `, `readonly `, `: mixed` | PHP 8+ syntax on a 7.4+ module unless the plan named a higher version ([00](00-AGENT-CONTRACT.md) §2i) |

**MUST NOT** skip a **named** useful `module.{mod}.{name}.hook` “for performance” — unused `trigger()` is cheap. **MUST NOT** fire a hook on every save to dodge this row ([41](41-MODULE-HOOKS.md)).

**Pass →** continue or say done. **Fail →** fix now.
