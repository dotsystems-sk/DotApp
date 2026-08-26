# 00 — Agent Contract (HARD LAWS)

**Read this file before every DotApp task.**  
**AIRULES is the single source of truth.** It supersedes leftover `.cursorrules` / `*_AI_guide.md` / `database_guide.md` from older installs.

This is **DotApp** — not Laravel, Symfony, CodeIgniter, Blade, Twig, Eloquent, or jQuery.

---

## 1. Edit boundaries (three tiers)

### ALLOWED (edit freely when asked)

| Path | Notes |
|------|--------|
| `app/config.php` | **Only** framework file agents may edit. Secrets, DB, drivers, module overrides. |
| `app/modules/<YourModule>/` | Everything inside the module you were asked to create or change. |
| `AIRULES/` | When the user asked to change the handbook. Compact Cursor rules: **`AIRULES/cursor/rules/*.mdc`** — never invent a law only under `.cursor/`. |
| `.cursor/rules/*.mdc` and project-root `AGENTS.md` | **Mirror only** ([§2l](#2l-cursor-rules-live-in-airules-must--law)). Copy from `AIRULES/cursor/`. **MUST NOT** author a new `.mdc` here. |

### ASK FIRST (do not touch unless the user explicitly requests)

| Path | Preferred alternative |
|------|------------------------|
| `app/listeners.php` | Prefer `module.listeners.php` inside your module. |
| `.htaccess` | Prefer `php dotapper.php --create-htaccess`. |
| Another module's folder | Only with explicit permission naming that module. |

### FORBIDDEN (never edit — no exceptions, no “quick fixes”, no “authorized labs”, **not even when the user asks**)

| Path | Why |
|------|-----|
| `app/parts/**` | Framework core libraries |
| `app/DotApp.php` | Framework kernel |
| `app/vendor/**` | Composer dependencies |
| `dotapper.php` | CLI tool binary |
| `index.php` | Front controller |
| `initializedb.php` | Core DB bootstrap |
| `app/runtime/**` | Generated cache/logs/sessions |
| `assets/dotapp/**` (if present as static copies) | Served dynamically; do not hand-patch |
| Any file outside the target module + `app/config.php` | Scope violation |

If you believe a core bug exists: **stop and report it in chat**. **MUST NOT** patch the kernel even if the user then asks you to — implement a workaround in the module.

---

## 2. Mandatory workflow

1. **Identify the target module** (or create one).
2. **Read** the relevant AIRULES docs for the task (routing / views / DB / forms / JS). Rule stack ([§2p](#2p-rule-stack-where-to-look-must--law)): project `AIRULES/` **always**; then `app/modules/<Host>/AIRULES/` if this work extends a named host; then **this** module’s `PLAN/` if it exists. **MUST NOT** open the host’s `PLAN/` when writing a pack.
3. **Generate** with `dotapper.php` whenever possible (module, controller, model, middleware).
4. **Implement** only inside the allowed paths.
5. **Tables:** every table your module owns **MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names or `dotapp_*` for module data. See [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md) §3.
6. **Migrations:** after you add a version in `Installation.php`, **MUST** rename `installed_*_install.php` back to `install.php` so the next page load runs it. Do not leave this for the user. **MUST** probe then `CREATE`/`ALTER` — **MUST NOT** `CREATE TABLE IF NOT EXISTS` ([07](07-SCHEMA-AND-INSTALL.md) §0).
7. **Lists:** any screen that lists records that **can accumulate** (users, logs, items, orders, messages, files, events) **MUST** ship `paginate()` **and** an **interactive AJAX pager** in the **first** version. Empty table today is not an excuse. A pager that reloads the page is not a pager. **Search / list UX:** when **planning**, **ASK** (search, filters, sort, bulk, page size, remember in DSM, CSV only if it fits). Lookup lists **MUST** ship AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. See [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
8. **Module identity:** when planning a new module with visible UI, **ASK once** for display name/purpose, optional logo/banner, placement and colours. Offer text-only/no custom branding; do not block a backend-only module. See [05](05-VIEWS-TEMPLATES-ASSETS.md) §8b.
9. **Finish gate (LAW):** after **every** code chunk **and** before claiming done — **MUST** [§2c](#2c-finish-gate-must--law). **MUST NOT** skip. Tick [17-CHECKLISTS.md](17-CHECKLISTS.md) Finish gate.
10. **Cursor credits:** when **planning** a programming task, **ASK** whether more expensive models may be used. Subagents **MUST inherit** the chat model. See [§2b](#2b-cursor-credits--subagents-must).
11. **PHP version:** when **planning** programming, **ASK** whether to stay on **PHP 7.4+** (DotApp default) or write for a higher version. No answer → **7.4+**. See [§2i](#2i-php-version-must).
12. **Debug / “why doesn’t this work”:** **MUST** follow [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) — grep middleware + count `crcCheck()` **before** guessing a core bug.
13. **Module hooks (LAW):** when a side-effect is worth another module (SMS/mail sent, payment, lockout), **MUST** `Events::trigger('module.{lowercase_modulename}.{hook_name}.hook', …)` with the `Hook:` / `Why:` / `About:` / `Params:` / `Use:` block, and **MUST** document that name in **`app/modules/<YourModule>/.hooks`**. **MUST NOT** fire on every save. Connect by reading **their** `.hooks` and listening in **yours**. Canonical: [§2g](#2g-module-hooks-must--law), [41](41-MODULE-HOOKS.md).
14. **Do not wake other modules:** `Module::initializeRoutes()` lists **only this module’s** URL prefixes (or `[]` for a listener-only module). `Listeners::initializeRoutes()` may list different producer/target prefixes (or `null` to inherit). **MUST NOT** return `['*']` unless the dependency is genuinely global/dynamic and you warned that this listener file registers on every request. A public catch-all **MUST** put `{not:/admin*|/api/v1*|…}` **on the wake string**. After either map changes: `php dotapper.php --optimize-modules`. Canonical: [03](03-MODULES-AND-ROUTING.md) “Keep other modules asleep”.
15. **Extender (judge):** **MUST NOT** Extender every method. Opt in when another module would reasonably **replace this output** (page/block HTML, cart, export). Owner `exists()` + `call()`; an ordinary result returns immediately, while `isOriginal()` alone continues owner logic. Register `extend()` in **`Listeners::register()`** before module initialization; put target URLs in `Listeners::initializeRoutes()`, not the Module map. Prefer a controller string handler. Canonical: [§2h](#2h-extender-judge--not-every-method), [12](12-SERVICES.md) §10.
16. **HTML via Renderer (LAW):** when markup **can** be a template, it **MUST** be a template. PHP prepares data; `Renderer` + `.view.php` / `.layout.php` produce HTML. **MUST NOT** concatenate tables, grids, empty states, pager chrome, trees, or crumbs in Controllers/Libraries. A PHP HTML string is **only** for a named exception ([§2j](#2j-html-via-renderer-must--law), [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c).
17. **Planning depth (LAW):** when they asked to **plan** a **new module**, a **first** major surface, or a **rewrite**, the plan **MUST** be extremely detailed — every nav item (if any), every page, every tab, every control. A long plan is correct. A bullet list of endpoints is not a plan. See [§2k](#2k-module-planning-depth-must), [45](45-MODULE-PLANNING.md).
18. **Cursor rules live in AIRULES (LAW):** compact `.mdc` files **MUST** live in `AIRULES/cursor/rules/`. `.cursor/rules/` is a non-portable Cursor mirror. The agent **MUST** copy the mirror itself ([§2l](#2l-cursor-rules-live-in-airules-must--law), [INSTALL.md](INSTALL.md)).
19. **defaultSettings (LAW):** **MUST** `defaultSettings()` at the start of `initializeRoutes()` (before `return`) **and** at the start of `initialize()`. **MUST NOT** compose wake/`Router` paths from Config another module fills later. See [§2m](#2m-module-defaultsettings-before-routes-must--law).
20. **Module AIRULES (LAW):** a host others extend **MUST** ship `app/modules/<This>/AIRULES/`. When the user names that host, **MUST** read it first. See [§2n](#2n-module-airules-must--law).
21. **PLAN folder (LAW):** a new module / first major surface / rewrite **MUST** write `app/modules/<This>/PLAN/` as a **split folder** (not one chat file) and implement **from that folder**. Chat-only plans fail. **MUST NOT** open a host’s `PLAN/` when writing a pack. See [§2o](#2o-module-plan-folder-must--law), [§2p](#2p-rule-stack-where-to-look-must--law).
22. **Rule stack (LAW):** 1 project `AIRULES/` (always) → 2 named-host `AIRULES/` → 3 **this** module’s `PLAN/`. Priority 1 wins. See [§2p](#2p-rule-stack-where-to-look-must--law).

### Dotapper-first rule

Never hand-create a module skeleton, controller, model, or middleware class that `dotapper.php` can generate. See [02-DOTAPPER-CLI.md](02-DOTAPPER-CLI.md).

```powershell
Set-Location "path\to\project-root"   # must contain index.php + dotapper.php
php .\dotapper.php --create-module=MyModule
php .\dotapper.php --module=MyModule --create-controller=Home
php .\dotapper.php --module=MyModule --create-middleware=Gate
php .\dotapper.php --module=MyModule --create-model=Item
```

`--module=` **must appear before** the create-* flag on the same command line.

### 2b. Cursor credits / subagents (**MUST**)

Users often pick a **cheap** chat model on purpose (Grok 4.6 and similar). AIRULES is written so that model can ship a correct app. Spawning a **premium** subagent (Opus, GPT-5.x, “thinking” / “high” / “xhigh”, cloud agent, best-of-N) **burns a different credit pool**. Doing that unasked is a **bug**.

**When planning programming, ASK in chat** (do not guess, do not skip):

> Stay on this model only, or also use more expensive models for subagents?

If they do not say **yes**: **no expensive models**. Keep working as the parent.

| Work | Model |
|------|--------|
| Write / edit PHP, views, JS, CSS, SQL — programming | **Parent only** (`inherit`). Do **not** “upgrade” to a bigger coder. |
| Hunt a pile of files / broad explore / grep-like | **Composer 2.5** (fast) is OK. |
| A capability the parent **cannot** do (e.g. **generate an image**) | That **specific** tool/model. **ASK** first if it costs extra. Do **not** spawn a premium **coder** for it. |

**MUST:**
- Code-writing / planning subagents: **`inherit`** the parent. Omit premium `model` slugs.
- **MUST NOT** use Composer 2.5 as the programmer that implements the module (too weak for DotApp). File-hunt only.
- **MUST NOT** launch parallel expensive runners, best-of-N, or cloud agents unless the user said yes **in this plan**.
- A silent upgrade “to be safe” is forbidden.

### 2c. Finish gate (MUST — law)

This is a **law**, not a reminder. Skipping it is a **bug**.

**When:** after **every** code chunk you write (route, middleware, controller method, query, form, view, JS handler) **and** again before you tell the user the work is done.

**MUST NOT:**
- Say it works / is ready / is finished until this gate **passed** on the files you just wrote
- Start the next feature with a known miss
- “Looks fine”, “I’ll check later”, or tick boxes from memory

**MUST:** actually **grep and read** this module — `Middleware/`, `module.init.php` (`Router::before`, `Middleware::`), Controllers, views, JS — **plus the diff**. Then tick [17-CHECKLISTS.md](17-CHECKLISTS.md) **Finish gate**.

| Check | How | Fail = stop and fix **now** |
|-------|-----|-----------------------------|
| **CRC once** | Count `crcCheck(` on **this POST’s** pipeline (middleware + `before` + action) **and** the action’s first PHPDoc line `CRCchecking —` | Two calls (first **burns** the token); CRC on GET/HTML login `before`; CRC on `$request->upload()`; PHPDoc names a CRC prefix **and** the action still `crcCheck()`; controller/middleware public method with no `CRCchecking —` first line ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| **IDs encrypted** | Views / JS / JSON: `value=`, `data-*`, hidden, payload | Plain `7` / `{{ var: $id }}` as an id sent to the browser. **MUST** `{{ enc(Shop.item.id): $id }}` with a unique `$key2`. Decrypt `=== false` → reject. Still `Auth::can` / ownership in PHP |
| **Queries bound** | Every SQL in the chunk | User input concatenated into SQL; `$qb->raw()` `?` that is not a binding (comments / `COMMENT` count); mix `?` and `:named` |
| **Inputs** | Request + persist | Password / HTML / hash from `$request->data()` not `data(true)`; missing `form()` / `Validator` where required; persist with only an FE overlay — PHP **MUST** still refuse |
| **Middleware / route conflicts** | `module.init.php` + Middleware vs the action | Login `before` missing **or** handlers outside `Auth::isLogged()`; CRC layer **and** action `crcCheck()`; CRC on a GET gate; two overlapping `before` hooks that both CRC |
| **Visible outcome** | Save / toggle / delete / form JS + `ajaxReply` | Silent `.after()` / no `message`; success with no toast/status; field errors without marking the input ([§2d](#2d-visible-outcome-must--law)) |
| **Catch reported** | `rg -n "catch \(|catch\(" ` + every `execute(` in the chunk | A `catch` (or `execute()` `$err`) that does **not** call the module’s report helper → `dotapp.catch` + `dotapp.catch.error|info`; ad-hoc payload keys; a secret/token/request body inside the payload ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9) |
| **Privilege / records** | Persist + GET view vars + SQL | Secret (TOTP/QR/key) in a read-only view; mutate a more privileged target; `WHERE id` only after decrypt; own password without current; public noauth shipped with no bot warning ([11](11-AUTH-AND-CRYPTO.md) §11) |
| **Threat pass** | The 12 greps in [24](24-ATTACK-VECTORS.md) §11 on this chunk | Injection (SQL/XSS/command/deserialize), input in a header or redirect, unbounded public POST, `getMessage()` / `var_dump` in the reply, upload without ext+MIME+header check, weak randomness for a token |
| **Perf / readability pass** | The greps in [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §8 | `->all()` on a growing table, a query/HTTP/log **inside** `foreach`, `select('*')` on a list, O(n²) lookup or array copy per row, a new `WHERE`/`ORDER BY` column with **no index**, an index with no comment naming its query, a public method with **tags-only PHPDoc** (`@return array<string, mixed>` and no purpose sentence), comments that restate the code |
| **Hooks** | Grep `Events::trigger(` vs `app/modules/<ThisModule>/.hooks` | Useful side-effect (SMS/mail/paid/lockout) with no `module.{mod}.{name}.hook`; old `shop.item.saved` shape; trigger without `Hook:`/`Why:`/`Params:`/`Use:`; hook on a trivial save with no named `Use:`; secrets; `trigger()` inside a growing `foreach`; `.hooks` in `assets/`; `return false` treated as a veto instead of `triggerWithVeto()` + `Veto` ([41](41-MODULE-HOOKS.md)) |
| **Extender** | New page/cart/export renderer **or** `Extender::` in the chunk | Spray on every persist/helper; skipped opt-in on a judged swap; `extend()` delayed to Module `initialize()`; target URLs placed in the Module map; listener-only Module `[]` with omitted/`null` listener routes; `.loaded` used although the point may run during target `initialize()`; `call()` without owner `exists()`; ordinary result does not return immediately; `original()` marker returned/serialized instead of checked with `isOriginal()`; fake `NEXT`; `$request` / secrets in args; Events used to replace a method; `['*']` just to attach; patch of another module to insert `call()` ([12](12-SERVICES.md) §10, [§2h](#2h-extender-judge--not-every-method)) |
| **PHP 7.4+** | Diff of new PHP | PHP 8+ syntax on a 7.4+ module: `match`, `?->`, union/`mixed` types, named args, constructor promotion, `#[…]`, `enum`, `readonly`, `never`, `str_contains` / `str_starts_with` / `str_ends_with` — unless the plan named a higher version ([§2i](#2i-php-version-must)) |
| **MySQL-safe DDL** | `Installation.php` / store `ensureTable` SQL in the chunk | Raw `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS`. Missing `SHOW TABLES LIKE` / `information_schema` probe before CREATE/ALTER ([07](07-SCHEMA-AND-INSTALL.md) §0, [§5](#5-security-non-negotiables) item 24) |
| **HTML via Renderer** | Diff of Controllers / Libraries vs `views/` | A screen or fragment (table, grid, empty state, pager chrome, tree, crumbs, card) built with `$html .=` / `'<table` / `'<tr` / `'<div class=` / `'<ul class=` / a `*Html()` factory in PHP. Fail unless that **one piece** has `// Why:` naming a real exception (sandbox callable drop, pager `<li>` / button, one tiny chip) — never a whole list ([§2j](#2j-html-via-renderer-must--law), [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c) |
| **Comments** | Diff of PHP/JS | Logical step without `// Why:`; new page action without `// About:` / `// Section:`; PHPDoc with no purpose sentence; `Controllers/` / `Middleware/` public method whose PHPDoc does not start with `CRCchecking —` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| **Cursor rules mirror** | `.cursor/rules/` vs `AIRULES/cursor/rules/` | A new `.mdc` exists only under `.cursor/`; AIRULES cursor rules were not copied into `.cursor/rules/` this session / after an AIRULES rule change ([§2l](#2l-cursor-rules-live-in-airules-must--law)) |
| **defaultSettings / routes** | `initializeRoutes()` + `initialize()` + RouteMap wake lists | Config used to build a wake prefix or `Router` path without `defaultSettings()` first; URL composed from another module’s Config that is filled only later; hardcoded foreign fallback that skips their `defaultSettings()` ([§2m](#2m-module-defaultsettings-before-routes-must--law), [03](03-MODULES-AND-ROUTING.md)) |
| **Wake `{not:}`** | Public catch-all in `initializeRoutes()` | Wakes on `/{path*}` and excludes `/admin` only in `initializeCondition` — exclusion **MUST** be on the wake string (`{not:/admin*\|/api/v1*\|…}`) ([03](03-MODULES-AND-ROUTING.md)) |
| **Module AIRULES** | Named host / pack / Extender target + `app/modules/<Named>/AIRULES/` | User named a host and that folder exists but was not read; a pack registered routes the host does not listen to; a **new host** that others extend shipped with no `AIRULES/` folder; module rules treated as a replacement for project AIRULES ([§2n](#2n-module-airules-must--law)) |
| **PLAN folder** | New module / first surface / rewrite + `app/modules/<This>/PLAN/` | No `PLAN/`; chat-only plan; one mega-file instead of a split folder; implement a screen/position not in PLAN; PLAN used to skip project law ([§2o](#2o-module-plan-folder-must--law), [45](45-MODULE-PLANNING.md)) |
| **Rule stack** | Paths opened this session | Host `PLAN/` opened while writing a pack; project `AIRULES/` skipped because a module folder exists; pack ignored host `AIRULES/` ([§2p](#2p-rule-stack-where-to-look-must--law)) |
| **Rest of AIRULES** | Touched files vs [§4](#4-no-foreign-framework-patterns) / [§5](#5-security-non-negotiables) / [17](17-CHECKLISTS.md) | Lists without AJAX pager, `$_SESSION`, Blade, `$.ajax`, `formName` outside `<fo-rm>`, … |

**Pass →** continue or say done. **Fail →** fix **now**. Do not start the next chunk.

User reports a failure **after** ship: [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) (same CRC / middleware hunt).

### 2d. Visible outcome (MUST — law)

The user **MUST** always see what happened. Silent save and silent fail are **bugs**.

**Every** stay-on-page action (save, toggle, delete, upload, login, form): PHP returns `status` + `message` (and `errors` when the problem is a **field**). JS **MUST** show it. Empty `.after()` is forbidden.

**This folder has no DACore shell.** Notiflix does **not** exist. **You MUST build** the feedback in **your** module (`app/modules/<YourModule>/assets/`).

| Kind | Channel (**MUST**) |
|------|-------------------|
| Field validation (wrong email, empty name, …) | **Preferred (FE + BE):** mark the **wrong input** (invalid/red class) **and** put the message **on that field** so the user sees **where** and **what**. PHP **MUST** return `errors` keyed by field (`Validator` shape). FE highlight without PHP re-check is UX only — persist still fails in PHP ([§5](#5-security-non-negotiables)). |
| Success (“Saved”, “Enabled”) | **Your** module toast / status node. Never `alert()`. |
| Non-field failure (CRC, rights, server) | Same module channel + `reply.message`. |

**MUST NOT:** only a generic “Validation failed” toast when the error belongs to a named field; skip success feedback; invent `alert()` / `window.confirm()`.

Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 “Visible outcome”, [19](19-VALIDATION-AND-INPUT.md), [EX-09](examples/EX-09-validation-and-errors.md).

### 2g. Module hooks (MUST — law)

Modules **MUST** talk to each other through `Events::trigger` / `Events::on`, not by patching a neighbour. Event name: **`module.{lowercase_modulename}.{hook_name}.hook`**. Fire **only** when another module (or a future one) could log, show history, or sync — SMS/mail sent, payment captured, lockout, finished workflow. **MUST NOT** fire on every save. When you do fire: document the name in **`app/modules/<YourModule>/.hooks`** and put the `Hook:` / `Why:` / `About:` / `Params:` / `Use:` block immediately above `trigger()` ([41](41-MODULE-HOOKS.md) §3).

**MUST NOT:** skip a **named** useful hook because `hasListener` is false; put secrets on the bus; treat listener returns on `trigger()` as a veto (use **`triggerWithVeto()`** + `new Veto($code, …)` only for an explicit pre-action stop); fire inside `foreach` of a growing list; fire `dotapp.catchall` yourself; invent another module’s event name; use the old `{mod}.{noun}.{happened}` shape; put `.hooks` on a public asset URL.

Canonical: [41](41-MODULE-HOOKS.md). Sample: [EX-16](examples/EX-16-module-hooks.md).

### 2h. Extender (judge — not every method)

`Dotsystems\App\Parts\Extender` lets **one** handler **replace** a target method for the current request. It is **not** Events, hooks, or `triggerWithVeto()`. No `.hooks` row.

**MUST NOT** sprinkle `Extender::exists()` on every helper, persist, CRC, decrypt, or pager. The agent **MUST** judge first: would another module (or a later one) reasonably **replace how this output is produced** without patching this file?

**Yes →** the **owner** opts in: `Extender::exists()`, then `Extender::call(...)`. An ordinary result returns immediately; only `Extender::isOriginal($result)` continues into the owner implementation. Typical yes: **rendering** a page or fragment, drawing a **cart**, building an **export** / invoice, swapping a checkout quote presentation.

**No / “maybe someday on every method” →** skip. Ordinary CRUD, CRC, decrypt, list internals, catch-bus helpers — **MUST NOT** become Extender targets “just in case.”

When the owner **does** opt in, mechanics are MUST:

- One replacement may return its final result or the unique `Extender::original()` marker. The owner **MUST** check that marker with `isOriginal()` and continue locally; it **MUST NOT** return or serialize the marker. There is no `next()` chain.
- The extending module registers `Extender::extend()` in **`Listeners::register()`** (`module.listeners.php`). Matching listeners register **before any matching Module initializes**, removing module-order races. This is cheap registration; **MUST NOT** query, call HTTP, write files, invoke the target, or call `$dotapp->module()` for the target **or itself** merely to attach. A controller-string handler autoloads lazily when `call()` runs.
- `Module::initializeRoutes()` lists only the extending module’s own URLs, or `[]` when it has no runtime routes. `Listeners::initializeRoutes()` **MUST** list the target URLs that can reach the extension point. If the Module map is `[]`, the listener **MUST NOT** omit/return `null`, because it would inherit `[]` and never register. Then `php dotapper.php --optimize-modules`.
- **MUST NOT** use listener `['*']` merely to attach. It is allowed only for a genuinely global/dynamic dependency after warning that `register()` runs on every request.
- Prefer a DotApp controller string such as `Addon:Pricing@quote!`; it is validated at registration and invoked lazily through `DotApp::call()`. Native callables remain allowed but skip DotApp DI and **MUST NOT** capture request/secrets.
- Direct registration is canonical. If registration must wait for the target lifecycle, subscribe from `Listeners::register()` to `dotapp.module.{Target}.init.start` or `.loading`. **MUST NOT** wait for `.loaded`, `.init.end`, or `dotapp.modules.loaded` when the extension point can run during target `initialize()`; those events are too late.
- One replacement. A second `extend()` for the same class+method throws. Recursion into the same target throws.
- Pass **explicit safe arguments** into `call()`. **MUST NOT** forward `$request`, secrets, tokens, CRC, rights blobs, or request bodies.
- Prefer `exists()`; `exist()` is the alias.

**MUST NOT** skip a render/cart/export surface the agent already judged as highly replaceable; spray Extender on every method; register the same target in both listener and Module initialization; use `trigger()` / `triggerWithVeto()` to replace a method; run the original after an ordinary replacement result; use a public scalar `ORIGINAL` sentinel or invent `next()`; patch another module to insert `Extender::call`.

Canonical: [12](12-SERVICES.md) §10. Sample: [EX-17](examples/EX-17-extender.md).

### 2i. PHP version (**MUST**)

DotApp targets **PHP 7.4+**. Modules **MUST** default to that floor.

**When planning programming, ASK in chat** (do not guess, do not skip):

> Stay on PHP 7.4+ (the DotApp default), or write this work for a higher PHP version?

If they do not name a higher version: **stay on 7.4+**. Typed properties, arrow functions (`fn`), `??=`, nullable types (`?int`), and `void` are fine.

**MUST NOT** until they name a higher version: union types (`int|string`), `mixed`, named arguments, `match`, nullsafe `?->`, constructor property promotion, attributes `#[…]`, `enum`, `readonly`, `never`, first-class callables (`strlen(...)`), `str_contains` / `str_starts_with` / `str_ends_with` (use `strpos` / `substr`).

A silent PHP 8+ upgrade “because it is nicer” is a **bug**.

### 2j. HTML via Renderer (MUST — law)

When the markup **can** be a template, it **MUST** be a template. PHP prepares data. The framework `Renderer` produces HTML. This is a **law**, not a style preference.

**MUST:** pages, tables, lists/grids, cards, empty states, pager chrome, trees, crumbs, and AJAX fragments live in `.view.php` / `.layout.php`. Produce them with `setLayout` + `setLayoutVar` + `renderLayout()` (or `setView` + `renderView()`). The same layout is the first paint and the AJAX `html` patch.

**MUST NOT:** concatenate a screen or fragment in Controllers / Libraries (`$html .= '<table'`, `'<tr'`, `'<div class='`, `listHtml()` / `iconsHtml()` / `treeHtml()` / `crumbsHtml()` factories). “It is shorter in PHP” / “the JSON already has html” is a **bug**.

**Exception — only when a template cannot do that one piece, and only that piece:**

1. The chunk has `// Why:` naming the real problem (Renderer sandbox would drop a callable key; a pager item callback returning one `<li>` / button; one tiny escaped chip such as a status badge).
2. That exception is **not** a table, grid, tree, empty state, crumbs bar, or pager wrapper.
3. Convenience, an existing `*Html()` helper, or copying an old string factory is **not** an exception.

Canonical: [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c.

### 2k. Module planning depth (**MUST**)

When the user asks to **plan** a **new module**, a **first version** of a major surface (public site, checkout, editor, dashboard, settings), or a **rewrite** that throws the old product away, the plan **MUST** be **extremely detailed** — something a **product designer** and a **senior application-security engineer** would both accept **and** a builder could implement without inventing a screen.

**Length is not a defect.** Omitting a page, tab, or control to keep the plan short is a **failed plan**.

**MUST write, in the plan, before code:**

1. **Menu / nav inventory.** If the module has any nav (public header, mobile drawer, logged-in links), list **every** item: label, URL, parent, who sees it. This folder has **no** DACore `Menu@register`. No nav → write **`No menu`**.
2. **Screen inventory.** Every page, then every tab/card/panel on that page, then **every control**: what it does, default, where it persists. Example density: Settings → Tab 1 Interface (Show XYZ / Hide side panel) → Tab 2 Frontend (theme select, drawer search). Same for list columns, row actions, and form fields.
3. **UI / UX.** Desktop and mobile regions, hierarchy, empty/loading/error states, toolbar, padding vs parent (especially below Save), interaction model.
4. **Security.** Name CRC-once, encrypted IDs, bound SQL, catch bus, upload/path jail, visible outcomes, HTML via Renderer, and the [24](24-ATTACK-VECTORS.md) §11 threat pass by their real controls — not “we will be careful.” Operator 2FA lock is DACore-only ([§7](#7-dacore-note)).

A short plan is allowed **only** for a small change to an already shipped screen. “It posts” / “Settings + list + edit” is a **failed plan**.

Write that inventory in **`app/modules/<This>/PLAN/`** ([§2o](#2o-module-plan-folder-must--law)). Canonical: [45](45-MODULE-PLANNING.md). UI: [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c–§8d.

### 2l. Cursor rules live in AIRULES (**MUST** — law)

`.cursor/rules/*.mdc` is a **Cursor runtime mirror**. It is **not** portable. The user copies **`AIRULES/`** into another project. A law that exists only under `.cursor/` is **lost**. That is a **bug**.

**Source of truth:** `AIRULES/cursor/rules/*.mdc` and `AIRULES/cursor/AGENTS.md`. How-to: [INSTALL.md](INSTALL.md).

**MUST NOT** create, edit, or leave a new compact law only under `.cursor/rules/` or a project-root `AGENTS.md` that is not a copy.

**MUST (agent, this session before coding — and after any AIRULES cursor-rule change):**

1. Ensure `.cursor/rules/` exists.
2. Copy `AIRULES/cursor/rules/*.mdc` → `.cursor/rules/`.
3. Copy `AIRULES/cursor/AGENTS.md` → project-root `AGENTS.md`.
4. A new compact rule: write it in `AIRULES/cursor/rules/` **first**, then copy.

This mirror copy is the **only** allowed write to `.cursor/rules/` and root `AGENTS.md`. It does **not** authorize inventing project code there.

### 2m. Module `defaultSettings()` before routes (MUST — law)

Config fallbacks run only when **`defaultSettings()`** runs. With `modulesAutoLoader.php`, **this** module can `initialize()` **before** another module. Composing `Router::get` / wake prefixes from a key that is filled later produces the wrong path.

**MUST:**

1. One **`defaultSettings()`** on the module: every `Config::module` default, idempotent (`??` / `IF_NOT_EXIST`). `app/config.php` wins.
2. Call it at the start of **`initializeRoutes()`** — **before** the array you return (wake decision).
3. Call it at the start of **`initialize()`** — then you may read Config to register routes.
4. Paths that must work before a **foreign** module has run `defaultSettings()` **MUST** also list a **literal** URL this module owns. **MUST NOT** invent the other module’s fallback so their `defaultSettings()` never applies.

**MUST NOT** `include` another module’s `module.init.php` to steal defaults.

Deep: [03](03-MODULES-AND-ROUTING.md). Compact: `AIRULES/cursor/rules/18-module-default-settings.mdc`.

### 2n. Module AIRULES (MUST — law)

**`app/modules/<ThisModule>/AIRULES/` is the host contract** — how *other* modules extend *this* one (a public-site host → how to write a theme pack; Shop → how to write a payment pack). It is **not** the development plan of this module. That is `PLAN/` ([§2o](#2o-module-plan-folder-must--law)). Lookup order: [§2p](#2p-rule-stack-where-to-look-must--law).

Project `AIRULES/` is the **framework** contract and **always applies**. A host that others will extend **MUST** also write module AIRULES, because project rules cannot list every route **this** host listens to. Inventing a public `Router::get` in a pack that the host never wakes is a **bug**.

**MUST write** `app/modules/<ThisModule>/AIRULES/` when **this** module is a **host** or an **extendable surface** (picks packs, publishes stems, owns public catch-alls, or expects Extender/listener modules). English. Index first (`README.md`), then topic files. A short `app/modules/<ThisModule>/AIRULES.md` pointer to that folder is allowed.

**A host handbook MUST also say:** packs write **`PLAN/` in the pack**, not in the host; do **not** send pack authors to this host’s `PLAN/`.

**MUST follow** when the folder exists: **project `AIRULES/` + `app/modules/<Host>/AIRULES/`**. Module rules **add** host-specific MUST/MUST NOT. They **MAY** name a host-only exception. They **MUST NOT** weaken project law (CRC once, PHP 7.4+, finish gate). Conflict → project AIRULES wins; say so in chat.

**When the user names a host** (“create a theme for Site”, “listen to Shop”, “Extender for Shop cart”):

1. Target = the pack / extender you are programming — write **that** module’s `PLAN/`.
2. Named folder = that host.
3. **MUST** read `app/modules/<Host>/AIRULES/` **before** coding if it exists.
4. **MUST NOT** read `app/modules/<Host>/PLAN/`.

**MUST NOT** skip a present host `AIRULES/` and invent routes; open the host’s `PLAN/` while programming a pack; put host-only law only under project `AIRULES/`; invent a law only under `.cursor/` ([§2l](#2l-cursor-rules-live-in-airules-must--law)).

**What a host handbook MUST cover** (when the surface exists): routes this module registers (wake + `Router`); view stems / vars; what a pack **MUST NOT** register; how to build URLs the host will resolve (including `{not:}` if used); that a pack **MUST** create `app/modules/<Pack>/PLAN/`.

Compact: `AIRULES/cursor/rules/19-module-airules.mdc`.

### 2o. Module PLAN folder (MUST — law)

**`PLAN/` is this module’s portable development plan** — how *we* keep building *this* module. It is **not** the contract for other modules. That is `AIRULES/` in the host ([§2n](#2n-module-airules-must--law)). A chat-only plan or a single file in Cursor history is **not portable**. Split files in a folder so another agent / machine can continue.

**Applies** when [§2k](#2k-module-planning-depth-must) applies (new module, first major surface, rewrite). A tiny edit to a shipped screen may skip a new PLAN file.

**MUST:**

1. Create `app/modules/<ThisModule>/PLAN/` in the same work as the scaffold (after `dotapper --create-module`).
2. Write the [§2k](#2k-module-planning-depth-must) / [45](45-MODULE-PLANNING.md) inventory **into that folder** (English): index (`README.md`); **laws**; **rules**; **menu/nav**; **screens**; **positions**. Optional `PLAN/assets/`. **MUST NOT** dump everything into one file.
3. Implement **from this module’s PLAN**. A screen, tab, control, or position that is not in PLAN is a **bug** — add it to PLAN first.
4. When **this** module’s `PLAN/` exists, **MUST** read it **before** coding this module.
5. A host that others will extend **MUST** also write `AIRULES/` in the same work ([§2n](#2n-module-airules-must--law)). The host’s `PLAN/` stays private to host development.

**MUST NOT:**

- Skip `PLAN/` because the chat already has a long plan.
- Treat `PLAN/` as project AIRULES or as the host handbook.
- Read another module’s `PLAN/` when writing a pack for that host.
- Weaken project law or host AIRULES from a PLAN file.
- Keep the only copy of the plan outside the module.

Compact: `AIRULES/cursor/rules/21-module-plan-folder.mdc`. Deep: [45](45-MODULE-PLANNING.md). Stack: [§2p](#2p-rule-stack-where-to-look-must--law).

### 2p. Rule stack — where to look (MUST — law)

Agents **MUST** know where laws live and what wins. Folders travel with the project; a Cursor chat does not.

| Priority (1 wins) | Path | Who writes it | Who reads it | What it is |
|-------------------|------|---------------|--------------|------------|
| **1 — always** | project `AIRULES/` | this rulebook | **everyone, every task** | Hard laws. CRC, PHP 7.4+, finish gate. |
| **2 — if this work extends a named host** | `app/modules/<Host>/AIRULES/` | the **host** | packs / listeners / Extenders **for that host** | How to extend the host. May add host-only MUST/MUST NOT. **MUST NOT** weaken priority 1. |
| **3 — the module you are building** | `app/modules/<This>/PLAN/` | **this** module’s authors | only agents **continuing this module** | Portable plan. Not a contract for others. **MUST NOT** weaken 1 or 2. |

**Example — theme pack for a finished public-site host:**

1. Project `AIRULES/` — still applies.
2. `app/modules/<Host>/AIRULES/` — how packs must register.
3. `app/modules/<YourPack>/PLAN/` — **your** pack plan.
4. **Do not** open `app/modules/<Host>/PLAN/`. **Do not** skip step 1 because the host has its own rules.

**Example — you are building the host itself:** write `<Host>/AIRULES/` (for future pack authors) **and** `<Host>/PLAN/` (for host development). Same two folders, two jobs.

**MUST NOT** invent a third place for law (only `.cursor/`, a gist, one mega-file in chat). Compact laws still live in `AIRULES/cursor/rules/` and are **mirrored** ([§2l](#2l-cursor-rules-live-in-airules-must--law)).

---

## 3. No-invention rule

If an API is not documented in AIRULES:

1. Open the real source under `app/parts/<Class>.php` (read-only).
2. Quote the actual method signature.
3. Use only what exists.

**Do not** invent methods because they exist in Laravel/Eloquent/jQuery.

## 3b. Never ignore a return value

DotApp uses **four different failure styles**. Getting this wrong silently breaks code:

| Style | Examples |
|-------|----------|
| Callback pair `($ok, $err)` | `execute()`, `Entity::save()` — **omitting `$err` makes it throw** |
| `false` / `null` | `Crypto::decrypt` → `false`, `Cache::load` → `null`, `Auth::login` → `false` on bad input |
| Envelope array | `HttpHelper::request`, `FastSearch::*` → check `['success']` |
| Exceptions | AI, SchemaBuilder, QueryBuilder build errors, `Auth::createUser` |

Also: `first()` is unsafe on an empty result, a missing view renders `""`, and `Email::send()` returns an **array of error strings**.

**Mandatory reading: [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md).**

---

## 4. No foreign-framework patterns

| Forbidden guess | DotApp reality |
|-----------------|----------------|
| `DB::table('x')`, Eloquent models | `DB::module("RAW")->q(fn($qb)=>...)->all()` |
| Blade `{{ $x }}`, `@if`, `@extends` | `{{ var: $x }}`, `{{ if }}` … `{{ /if }}` |
| `$html .= '<table'` / `listHtml()` factory in a Library | `Renderer` + `.layout.php`; PHP prepares data. PHP markup **only** for a named exception ([§2j](#2j-html-via-renderer-must--law), [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c) |
| `Route::prefix()->group()`, named routes | Imperative `Router::get(...)` in `module.init.php` |
| Register all login-required URLs then login-middleware only | Prefix + `Router::before([$area, $area.'/*'], login 403)` **and** `if (Auth::isLogged())` — page **MUST NEVER** show ([03](03-MODULES-AND-ROUTING.md)) |
| Instance controllers + `$this->` | `public static function` controllers |
| `$`, `jQuery`, `$.ajax` | `$dotapp`, `$dotapp().load(...)` |
| `<form>` + manual CSRF only | Prefer `<fo-rm>` + `{{ formName(handler) }}` |
| `{{ formName }}` after `</fo-rm>` | **MUST** between `<fo-rm>` and `</fo-rm>` |
| Plain IDs in HTML/JSON (`value="7"`, `data-id="7"`) | **MUST** `{{ enc(Shop.item.id): $id }}` — unique `$key2` per field |
| `<fo-rm>` around every row button / D&D | `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)) |
| List/form still clickable during `load()` | Cover the region with **your module preloaders** until done — desktop **and** mobile ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Desktop-only public header / hover menu / no mobile drawer | Overlay drawer L/R; lock page scroll while open; drawer itself scrolls ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Logs / users / items dumped with `->all()`, no pager, or “few rows now so skip” | **MUST** `paginate()` + interactive AJAX on **first ship** ([06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Load the whole table / N+1 in `foreach` / `select('*')` for a list | Smallest I/O: `exists()` / `COUNT(*)` / needed columns / one `join` ([06](06-DATABASE.md)) |
| Lookup list with no search / JS-filter of `->all()` | **ASK** in the plan; articles/catalog **MUST** AJAX search unless declined ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `$request->data()` for password / HTML / `Auth::login` | **MUST** `$request->data(true)` — `protect()` rewrites `)`, `=`, `%` ([19](19-VALIDATION-AND-INPUT.md)) |
| TOTP/QR in a read-only view; edit a more privileged user; `WHERE id` only | [11](11-AUTH-AND-CRYPTO.md) §11 — mutate right + SQL owner; **warn** if public noauth is bot-bait |
| Custom OTP digit widget / jQuery 2FA plugin | **MUST** `$dotapp().twoFactor` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `alert()` / `window.confirm()` to delete | Graphical dialog first, then `load()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Prompt-echo UI copy (“this user can hide the icon…”) | Product copy a software company would ship ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| `f-form` attribute | **Does not exist** — use `<fo-rm>` |
| `$_SESSION` / `session_start()` | **MUST** `DSM::use('Shop')` ([20](20-CACHE-LOGGER-SESSION.md)) |
| JS overlay / modal as the only save or 2FA gate | **MUST** re-check in PHP; FE is UX only ([08](08-FORMS-AND-SECURITY.md)) |
| File/ZIP in `FormData` + `load()` / `<fo-rm>` | **MUST** `$dotapp().uploadFile` + `$request->upload()`; PHP rejects `.php` ([09](09-DOTAPP-JS-AND-BRIDGE.md)) |
| `crcCheck()` in middleware **and** in the action | **MUST** once — first call **burns** the token ([08](08-FORMS-AND-SECURITY.md)) |
| Shipping a chunk / claiming done without the finish gate | **MUST** [§2c](#2c-finish-gate-must--law) after every chunk — grep, do not imagine |
| Silent save / empty `.after()` / no field error on a named field | **MUST** [§2d](#2d-visible-outcome-must--law) — user sees success and fail |
| Tags-only PHPDoc (`/** @return array<string, mixed> */`) / unlabeled Why prose | Purpose sentence **then** tags; **`// Why:`** / **`// About:`** / **`// Section:`** ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| Controller action with no `CRCchecking —` first line, or that line names a CRC prefix **and** the body still `crcCheck()` | First PHPDoc line names the **real** layer; prefix **XOR** action ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7, [08](08-FORMS-AND-SECURITY.md)) |
| Patch another module / fire `shop.item.saved` | Read **their** `.hooks`, `Events::on` in **yours**; name is `module.{mod}.{name}.hook` ([41](41-MODULE-HOOKS.md)) |
| Premium Cursor subagent (Opus / GPT-5 / xhigh) without asking | **MUST inherit** the chat model; **ASK** in the plan ([00](00-AGENT-CONTRACT.md) §2b) |
| Short plan (“Settings + list + edit”) for a new module / first surface / rewrite | Extremely detailed inventory: every nav item, page, tab, control ([§2k](#2k-module-planning-depth-must), [45](45-MODULE-PLANNING.md)) |
| Chat-only plan / one mega-file / no `app/modules/<This>/PLAN/` | Write a **split** `PLAN/` folder then code from it ([§2o](#2o-module-plan-folder-must--law)) |
| Open host `PLAN/` while writing a pack | Read host `AIRULES/` only; write `PLAN/` in **this** pack ([§2p](#2p-rule-stack-where-to-look-must--law)) |
| New compact law only under `.cursor/rules/` | Write `AIRULES/cursor/rules/*.mdc` first, then copy the mirror ([§2l](#2l-cursor-rules-live-in-airules-must--law)) |
| Wake `/{path*}` then skip `/admin` only in `initializeCondition` | `{not:/admin*\|/api/v1*}` **on the wake string** ([03](03-MODULES-AND-ROUTING.md)) |
| `Router` / wake from Config before `defaultSettings()` | Call `defaultSettings()` first ([§2m](#2m-module-defaultsettings-before-routes-must--law)) |
| Skip a present host `AIRULES/` / pack invents the host’s public catch-alls | Read `app/modules/<Host>/AIRULES/` first ([§2n](#2n-module-airules-must--law)) |
| `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS` in installer SQL | Probe first (`SHOW TABLES LIKE` / `information_schema`), then `CREATE TABLE` / `ALTER TABLE` without `IF NOT EXISTS` ([07](07-SCHEMA-AND-INSTALL.md) §0) |

Full table: [14-ANTIPATTERNS.md](14-ANTIPATTERNS.md).

---

## 5. Security non-negotiables

1. **Preferred form stack (default for all interactive forms):**
   - Markup: `<fo-rm>` + `{{ formName(handler) }}` (not `f-form`, not Laravel `_token` alone)
   - **MUST:** `{{ formName(handler) }}` sits **between** `<fo-rm …>` and `</fo-rm>` — never before `<fo-rm>`, never after `</fo-rm>` (outside the pair the tag is left unchanged: silent failure)
   - Script: **`/assets/dotapp/dotapp.js` first** (injects random per-session keys — without it secure forms fail)
   - JS: `$dotapp().form(...).before().after()` + `parseReply` + **MUST** block while in flight (**your module preloaders** — desktop **and** mobile)
   - **MUST:** after success, patch the DOM (`reply.html` / data) and a short toast. `<fo-rm>` does **not** reload. No `location.reload()`. `redirectTo` only when leaving the page ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
   - PHP: `$request->crcCheck()` **once** then `$request->form([...], "handler", ...)` then `ajaxReply`
   - Full sample: [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md)
2. This stack is **stronger than plain CSRF** (binds handler + action + method, CRC, one-time tokens, JS key material). Use it **only for real HTML forms** (several fields + submit). **MUST NOT** wrap row actions (toggle, delete, reorder, drag-and-drop, paginate) in `<fo-rm>` — those are `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)).
3. Never skip CRC/CSRF for endpoints that receive `$dotapp().load()` / secure forms. **MUST** `crcCheck()` **once** per request — API prefix **or** action, **never both** (first call **burns** the token). Canonical: [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md).
4. **MUST encrypt every identifier sent to the browser** (`<option value>`, `data-*`, hidden, JSON). Use `{{ enc(Shop.user.id): $id }}` / `Crypto::encrypt($id, 'Shop.user.id')` with a **different `$key2` per field**. Never `value="7"` / `data-id="7"`. Decrypt with the **same** `$key2`; `false` → reject. **MUST still** `Auth::can()` / ownership — encryption is not a substitute for rights ([11](11-AUTH-AND-CRYPTO.md) §8).
5. Never interpolate user input into SQL — use QueryBuilder bindings or `raw($sql, $bindings)`. **MUST NOT** put `?` in `$qb->raw()` unless it is a real binding — comments and `COMMENT 'SMS?'` count too ([06](06-DATABASE.md)).
6. On new apps, generate real `app.c_enc_key` / `rm_key` / `rmrcm_key` (see [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md)).
7. Module settings must have **fallbacks** if the user did not fill `app/config.php`.
8. **MUST paginate accumulating lists** (users, logs, items, …) with an **interactive** pager (`$dotapp().load()`). Shipping the list with no pager, or changing pages by reloading the document, is incomplete. Lookup lists **MUST** ship **interactive AJAX search** unless the user declined; other lists: **ASK** in the plan. [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
9. **MUST** store app session state with **`DSM::use('Shop')`**. **MUST NOT** `$_SESSION` or `session_start()` ([20](20-CACHE-LOGGER-SESSION.md)).
10. **MUST** re-check every persist in **PHP** (`Auth::can`, 2FA code, ownership, validation). `crcCheck()` is transport — **once** per request, not again. Frontend modal/overlay/disabled control is **UX only**. Removing the overlay **MUST** still fail on the server ([08](08-FORMS-AND-SECURITY.md)).
11. **MUST** upload files with **`$dotapp().uploadFile`**. **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()` on that endpoint. **MUST** reject `.php` and other executables (extension + `finfo` MIME + headers); FE `accept=` is UX only ([09](09-DOTAPP-JS-AND-BRIDGE.md)).
12. **MUST** take passwords, HTML, and other round-trip values from `$request->data(true)` / `$request->query(true)` (original). `$request->data()` is the **protected** copy (`protect()`). Login/createUser/installer **MUST NOT** hash the protected string. **MUST** show every login failure (`crcCheck`, `form()` `null`/`false`, `Auth::login === false`). Canonical: [19](19-VALIDATION-AND-INPUT.md).
13. **Login-required / admin routes (MUST):** prefix `/{ModuleName}/…` (or a subtree). Cover HTML with `Router::before([$area, $area . '/*'], '#Shop:Gate@login!')` returning `Response` 403. **POST API:** `/api/v1/auth|noauth/{Module}/…` + `Gate@loginAndCrc` / `Gate@crc` at the **start** of `initialize()`; handlers **MUST NOT** `crcCheck()` again. Register login-only handlers **only** inside `if (Auth::isLogged() === true)`. Those pages **MUST NEVER** render for anonymous users. Canonical: [03](03-MODULES-AND-ROUTING.md).
14. **Documentation (MUST):** English. Every file/class gets a docblock. Every public/static method in **`Controllers/`** and **`Middleware/`** starts PHPDoc with **`CRCchecking —`** naming **where** CRC runs (exact prefix/middleware, or `this action`, or `none` for GET/upload/helper) — then a **purpose sentence** — tags alone (`@return array<string, mixed>`) are a **bug**. Then `@param` / `@return` / `@throws` with **meaning**, not only types. Inline comments **MUST** use the labels **`// Why:`** (every logical step), **`// About:`** (what the chunk is / what the record represents), **`// Section:`** (menu or route). **MUST NOT** restate the code (`// increment i`), prompt-echo, omit the labels, or leave dead code / bare `TODO`. **MUST NOT** write `CRCchecking — prefix … MUST NOT crcCheck()` and then call `crcCheck()` in that method. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7, [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md).
15. **Errors (MUST):** persist handlers in `try/catch` (`\Throwable`) — log, structured `ajaxReply`, **never** leak `$e->getMessage()`, **never** empty `catch`. `execute()` **MUST** get **both** callbacks (`$ok` and `$err`); omitting `$err` **throws**. **Every `catch` and every `execute()` `$err` MUST also report to the catch bus:** `Events::trigger('dotapp.catch', $payload)` then `dotapp.catch.error` (aborted) or `dotapp.catch.info` (recovered/expected), with the fixed payload (`severity, module, source, operation, message, exception, code, file, line, time` + `context` ids/counts, `user_id`) — no secrets, tokens or request bodies in it. Route it through **one** report helper per module so a future debugger listener cannot break the reply. Canonical: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.
16. **Cheap I/O (MUST):** pick the smallest load — `exists()` / `COUNT(*)` / `limit(1)` / `select` only used columns / `paginate()` / one `join`. **MUST NOT** `->all()` then filter, N+1 in `foreach`, or `Config::db('cache')` “for speed”. Canonical: [06](06-DATABASE.md), [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §2.
17. **Visible outcome (MUST):** the user always sees success and failure. Public: **preferred** mark the wrong field (red + message on the input) — PHP returns `errors`. You **build** your own toast/status. Canonical: [§2d](#2d-visible-outcome-must--law).
18. **Privilege and records (MUST):** no secret in a read-only view; no grant/mutate above the actor; SQL scoped to owner; own password needs current password; live routes; lockout covers 2FA if you built lockout. Public noauth that bots can hammer: **MUST warn** in chat (CAPTCHA optional — not MUST). Canonical: [11](11-AUTH-AND-CRYPTO.md) §11.
19. **Known attack vectors (MUST):** the catalogue in [24-ATTACK-VECTORS.md](24-ATTACK-VECTORS.md) is **law** — injection (SQL, XSS, command, template, deserialization), channels (headers, redirect, mail, SSRF, mass assignment), identity (CSRF, fixation, brute force, enumeration), access control (IDOR, escalation, tampered fields), browser headers, files/paths, abuse/rate limit, leaks, crypto, third-party/AI. **MUST NOT** ship a chunk that enables one. Open only the sections for the surface you touch, then run the **threat pass** ([24](24-ATTACK-VECTORS.md) §11) on the diff. A vector not listed there is still forbidden — apply the nearest row and **say it in chat**.
20. **Performance, schema and readability (MUST):** [25-PERFORMANCE-AND-CODE-QUALITY.md](25-PERFORMANCE-AND-CODE-QUALITY.md) is **law** — smallest I/O, bounded memory (page big sets, no O(n²), no full-array copies), **indexes designed for the queries you actually wrote** (FK + every `WHERE`/`JOIN`/`ORDER BY` column; composite order equality → range → sort; leftmost prefix; no duplicate prefix indexes), sane column types, cheap frontend, and the documentation standard (§7: **`CRCchecking —` first** on controller/middleware public methods, PHPDoc **purpose sentence** then tags, labeled **`Why:`** / **`About:`** / **`Section:`**). Run the perf pass ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §8) with the finish gate.
21. **Module hooks (MUST):** useful side-effects **MUST** `Events::trigger('module.{mod}.{hook_name}.hook', …)` with the comment block and a `.hooks` row. **MUST NOT** fire on every save. Listen in **your** module; do not patch the owner. No secrets on the bus. Canonical: [41](41-MODULE-HOOKS.md).
22. **Extender (judge — not every method):** opt in when another module would reasonably replace this **output** (page/block, cart, export). Owner: `exists()` + `call()`; ordinary result returns, only `isOriginal()` continues owner logic. Extender: `extend()` in `Listeners::register()`, target URLs in `Listeners::initializeRoutes()`, own Module routes only (or `[]`), controller string preferred. **MUST NOT** use `.loaded` for an initialize-time point, spray on every method, invent `next()`, return the marker, use Events to replace a method, pass `$request`/secrets, or patch the owner. Canonical: [§2h](#2h-extender-judge--not-every-method), [12](12-SERVICES.md) §10.
23. **PHP version (MUST):** default **PHP 7.4+**. When **planning**, **ASK** whether to stay on 7.4+ or write for a higher version. No answer → 7.4+. **MUST NOT** ship PHP 8+ syntax (`match`, `?->`, union/`mixed`, named args, promotion, attributes, `enum`, `readonly`, `str_contains`, …) unless they named a higher version. Canonical: [§2i](#2i-php-version-must).
24. **MySQL-safe installer DDL (MUST):** probe first, then `CREATE TABLE` / `ALTER TABLE`. **MUST NOT** emit `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS`, or `CREATE INDEX IF NOT EXISTS` — older MySQL errors; `ADD COLUMN IF NOT EXISTS` is MariaDB-only. Table probe = `SHOW TABLES LIKE` after `[A-Za-z0-9_]+` whitelist. Column/index probe = `information_schema` scoped to `DATABASE()` with bindings. Helpers live in **your** module. `DROP TABLE IF EXISTS` on uninstall is allowed. `$qb->createTableIfNotExist()` is allowed (it already probes and emits `CREATE TABLE` without `IF NOT EXISTS`). Canonical: [07](07-SCHEMA-AND-INSTALL.md) §0.
25. **HTML via Renderer (MUST):** when markup can be a template, it **MUST** be a template. PHP prepares data; `Renderer` + `.view.php` / `.layout.php` produce HTML. **MUST NOT** concatenate tables, grids, empty states, pager chrome, trees, or crumbs in Controllers/Libraries. A PHP HTML string is **only** for a named one-piece exception (`// Why:` + sandbox drop / pager `<li>` or button / one tiny chip). Canonical: [§2j](#2j-html-via-renderer-must--law), [05](05-VIEWS-TEMPLATES-ASSETS.md) §1c.
26. **Planning depth (MUST):** a plan for a new module / first major surface / rewrite **MUST** list every nav item (or `No menu`), every page, every tab, and every control (what it does, default, persist). Length is not a defect. Canonical: [§2k](#2k-module-planning-depth-must), [45](45-MODULE-PLANNING.md).
27. **Cursor rules live in AIRULES (MUST):** compact `.mdc` files live in `AIRULES/cursor/rules/`. The agent **MUST** copy them into `.cursor/rules/` and `AGENTS.md` to the project root. **MUST NOT** invent a law only under `.cursor/`. Canonical: [§2l](#2l-cursor-rules-live-in-airules-must--law), [INSTALL.md](INSTALL.md).
28. **defaultSettings before routes (MUST):** **MUST** `defaultSettings()` in `initializeRoutes()` before `return` **and** at the start of `initialize()`. **MUST NOT** compose wake/`Router` paths from Config filled later. Canonical: [§2m](#2m-module-defaultsettings-before-routes-must--law), [03](03-MODULES-AND-ROUTING.md).
29. **Module AIRULES (MUST):** a host **MUST** ship `app/modules/<This>/AIRULES/`. Named host + unread folder = fail. **MUST NOT** open the host’s `PLAN/` when writing a pack. Canonical: [§2n](#2n-module-airules-must--law), [§2p](#2p-rule-stack-where-to-look-must--law).
30. **PLAN folder (MUST):** a new module / first major surface / rewrite **MUST** write `app/modules/<This>/PLAN/` (split files: laws, rules, positions) and implement from it. Chat-only / one-file plans fail. **MUST NOT** read a host’s `PLAN/` when writing a pack. Canonical: [§2o](#2o-module-plan-folder-must--law), [§2p](#2p-rule-stack-where-to-look-must--law), [45](45-MODULE-PLANNING.md).
31. **Rule stack (MUST):** project `AIRULES/` always; then named-host `AIRULES/`; then **this** module’s `PLAN/`. Priority 1 wins. Canonical: [§2p](#2p-rule-stack-where-to-look-must--law).

---

## 6. Identity reminder (paste into new PHP files)

```php
<?php
/**
 * DOTAPP MODULE FILE
 * - Controllers: Module:Controller@method!  (! = no DI params)
 * - Database: DB::module("RAW")->q(...)->all()|first()|execute() — execute MUST both callbacks; persist try/catch; raw() every ? is a placeholder (not in comments)
 * - Tables: {lowercase_modulename}_*  (Shop → shop_items) — NEVER items or dotapp_*
 * - Templates: {{ var: $x }}  — NOT {{ $x }}. VIEW = outer file; setLayout+renderView fills {{ content }} in that view (or renderLayout / str_replace). HTML via Renderer (LAW, 00 §2j / 05 §1c): pages/tables/grids/empty/pager/tree/crumbs MUST be layouts. PHP markup ONLY for a named one-piece exception — NOT $html .= '<table' factories
 * - Forms: <fo-rm> only for real multi-field submit; row actions = load() + data-*; crcCheck() once (API prefix XOR action)
 * - FE ids: {{ enc(Shop.item.id): $id }} unique $key2 per field; Auth::can still required
 * - Privilege: no secret in read-only views; no escalate; SQL owner scope; own password needs current; warn user if public noauth is bot-bait (11 §11)
 * - Attacks (24): escape before echo ({{ var: }} does NOT escape); whitelist sort columns + insert columns; no input in header()/redirect/HttpHelper URL; no eval/exec/unserialize on input; random_bytes for tokens; hash_equals for secrets; throttle public POST
 * - JS: $dotapp — NOT jQuery $; after save/toggle MUST patch DOM + toast (no reload); MUST module preloaders until request ends (desktop+mobile)
 * - Public site nav: mobile drawer slides L/R over the page; lock document scroll while open; drawer list scrolls; contacts+compact search in the drawer unless large search is its own mobile section
 * - Lists: accumulating records (users/logs/items) MUST paginate() on first ship + AJAX pager — NOT all() dump, NOT ?page= / location.reload()
 * - Cheap I/O: exists/COUNT/limit(1)/needed columns/paginate/one join — NOT all() then filter, NOT N+1
 * - Memory: page big sets, keyed map instead of in_array in a loop, unset the raw copy, stream files — NOT load-all-then-filter
 * - Indexes (25 §3): FK + every WHERE/JOIN/ORDER BY column; composite = equality → range → sort; leftmost prefix; one comment line per index naming its query
 * - Docs (25 §7): Controllers/Middleware public PHPDoc MUST start with CRCchecking — (exact prefix/middleware XOR this action XOR none); then purpose sentence, then @param/@return/@throws with meaning — NOT tags-only (`@return array<string, mixed>`); NOT prefix CRC + crcCheck() in the same method; inline MUST use labels // Why: (logical step), // About: (what this chunk is), // Section: (menu/route) — NOT narration of the code, NOT unlabeled Why prose
 * - Catch bus (18 §9): every catch + every execute() $err → one report helper → Events::trigger('dotapp.catch', $p) then 'dotapp.catch.error'|'.info'; payload = severity, module, source, operation, message, exception, code, file, line, time, context (ids/counts), user_id — NO secrets/tokens/bodies
 * - Hooks (41): useful side-effects (SMS/mail/paid/lockout) MUST Events::trigger('module.{mod}.{name}.hook') + Hook/Why/About/Params/Use block + .hooks — NOT every save; NOT secrets; NOT patch the other module; NOT old shop.item.saved shape. Pre-action stop = triggerWithVeto + Veto only.
 * - Extender (12 §10): judge first — opt in on replaceable output (page/block HTML, cart, export), NOT every method; owner exists()/call(); ordinary result returns, only isOriginal() continues; Extender::extend in Listeners::register(); target URLs in listener map; own Module map or []; prefer 'Module:Controller@method!'; NOT next(), marker response, .loaded for initialize-time, Events, $request/secrets, duplicate registration
 * - After a new Installation.php version: rename installed_*_install.php → install.php (agent does it)
 * - Installer DDL (LAW, 07 §0): probe SHOW TABLES LIKE / information_schema then CREATE/ALTER. MUST NOT CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS / ADD INDEX IF NOT EXISTS. Helpers in this module
 * - Search: ASK in the plan; lookup lists (articles/products) MUST AJAX search unless declined — debounce, 3+ chars, SQL+paginate, NOT JS filter
 * - 2FA boxes: $dotapp().twoFactor — do not invent OTP widgets
 * - Deletes: graphical confirm first — never alert()/confirm()
 * - UI copy: product language — never prompt-echo / “this user can…”
 * - Session: DSM::use('Shop') — NEVER $_SESSION / session_start()
 * - Save checks: PHP MUST re-verify — FE modal/overlay is UX only
 * - Files: $dotapp().uploadFile — NEVER FormData + load()/fo-rm; PHP MUST reject .php (ext+MIME+headers)
 * - Request: data() = protected; data(true) = original — MUST true for passwords/HTML/hashes
 * - Login-required routes: prefix /{Module}/… + Gate@login 403 Response; MUST register handlers inside Auth::isLogged()
 * - Comments: English; labels Why: / About: / Section: — not every line, not unlabeled
 * - PHP: default 7.4+; ASK in the plan for a higher version — NOT match / ?-> / union mixed / named args / promotion / attributes / enum / readonly / str_contains unless they said yes (00 §2i)
 * - Cursor: inherit parent model for subagents; ASK before expensive models; Composer 2.5 = file hunt only, not the coder
 * - Finish gate (LAW): after every chunk grep crcCheck once, enc ids, bound SQL, data(true), middleware vs action, Events::trigger vs .hooks — 00 §2c / 41
 * - Visible outcome (LAW): user always sees save/fail; public = mark the wrong field; build your own toast — 00 §2d
 * - HTML via Renderer (LAW): when it can be a template it MUST be; PHP HTML string only for a named one-piece exception — 00 §2j / 05 §1c
 * - Planning depth (LAW): new module / first surface / rewrite plan MUST inventory every nav item (or No menu), page, tab, control — length is OK — 00 §2k / 45
 * - Cursor rules (LAW, 00 §2l): compact .mdc live in AIRULES/cursor/rules/. Agent MUST copy them to .cursor/rules/ + AGENTS.md to project root. MUST NOT invent a law only under .cursor/
 * - defaultSettings (LAW, 00 §2m): call defaultSettings() in initializeRoutes() before return and at the start of initialize(). MUST NOT build Router/wake paths from Config filled later. Literal own path if the other module has not run yet
 * - URL {not:} (03): exclude before the positive match — /{path*}{not:/admin*|/api/v1*}. /admin* not /admin/* for exact /admin. Public catch-all MUST put {not:} on the wake string
 * - Host AIRULES (00 §2n): extendable hosts MUST ship app/modules/<This>/AIRULES/; read it first when the user names that host
 * - PLAN folder (LAW, 00 §2o): write app/modules/<This>/PLAN/ as a split folder (not one chat file). Implement from it. MUST NOT read a host PLAN when writing a pack
 * - Rule stack (LAW, 00 §2p): 1 project AIRULES (always) 2 host AIRULES (if extending a named host) 3 this module PLAN. Priority 1 wins
 * - Edit only this module + app/config.php. Never edit app/parts/, app/DotApp.php, dotapper.php, index.php — not even if the user asks. The kernel is frozen.
 * See AIRULES/00-AGENT-CONTRACT.md
 */
```

---

## 7. DACore note

DACore is an **optional admin module**, not part of the framework core.  
Part 1 (this folder) must remain usable **without** DACore.  
Do not call `DACore:*` APIs unless the user explicitly requested DACore integration (Part 2).  
**Notiflix is DACore-only.** It is not available here. Public sites **MUST** ship module preloaders ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3).  
Operator 2FA lock and step-up on dangerous admin actions are **DACore-only** (Part 2). Do not invent that flow in a framework-only app.

---

## 8. Conflict resolution

| Conflict | Winner |
|----------|--------|
| Leftover `.cursorrules` / `*_AI_guide.md` vs AIRULES | **AIRULES** |
| `.cursor/rules/*.mdc` vs `AIRULES/cursor/rules/*.mdc` | **`AIRULES/cursor/`** — `.cursor/` is a mirror ([§2l](#2l-cursor-rules-live-in-airules-must--law)) |
| Leftover `database_guide.md` invented APIs | **Ignore** — follow [06-DATABASE.md](06-DATABASE.md) |
| User asks to edit `app/parts/` / `app/DotApp.php` / `dotapper.php` / `index.php` | **MUST NOT.** The kernel is frozen. Say so in chat; implement in `app/modules/<YourModule>/` (+ `app/config.php` only). |

---

## 9. Minimum reading map by task

| Task | Theory | Example (open one) |
|------|--------|--------------------|
| **Anything (always)** | **18** error handling / return values — incl. **§9 catch bus** (`dotapp.catch`) | — |
| Plan / Cursor credits | **00 §2b** — ASK before expensive subagents; inherit parent; Composer 2.5 = file hunt only | — |
| Plan / PHP version | **00 §2i** — ASK 7.4+ (default) vs a higher PHP; no answer → 7.4+ | — |
| Plan / new module, first major surface, or rewrite | **00 §2k** + **§2o** + **§2p** / **[45](45-MODULE-PLANNING.md)** — `PLAN/` in **this** module (split files); packs read host `AIRULES/`, not host `PLAN/` | — |
| Cursor `.mdc` / copied AIRULES folder | **00 §2l** — source is `AIRULES/cursor/rules/`; agent **MUST** copy into `.cursor/rules/` | [INSTALL.md](INSTALL.md) |
| `defaultSettings` / Config-built routes | **00 §2m** / **03** — call defaults before wake `return` and before `Router` | [EX-03](examples/EX-03-module-scaffold.md) |
| URL `{not:}` / public catch-all | **03** path parameters — exclude **before** the positive match; on the wake string | — |
| Host / pack handbook | **00 §2n** + **§2p** — `app/modules/<Host>/AIRULES/`; **MUST NOT** open host `PLAN/` when writing a pack | — |
| **After every code chunk** | **00 §2c** finish gate — CRC once, enc IDs, bound SQL, inputs, middleware conflicts | [17](17-CHECKLISTS.md) Finish gate |
| Stay-on-page save / errors | **00 §2d** visible outcome — mark the wrong field; your own toast/status | [EX-09](examples/EX-09-validation-and-errors.md), [EX-06](examples/EX-06-dotapp-js-boot.md) |
| New module | 00, 02, 03 | [EX-03](examples/EX-03-module-scaffold.md) |
| Page / list / AJAX HTML fragment | **00 §2j** / **05 §1c** — Renderer + layout; PHP markup only for a named one-piece exception | [EX-05](examples/EX-05-renderer-page.md) |
| Route / middleware | 03, 04 | EX-03 — prefix `Gate@login` 403 + handlers inside `Auth::isLogged()` |
| Template / CSS / JS page | 05 (incl. **§1c HTML via Renderer law**, §8 product copy), **09 §3** public mobile nav | [EX-05](examples/EX-05-renderer-page.md), [EX-06](examples/EX-06-dotapp-js-boot.md) |
| Public website header / nav | **09 §3** “Public website mobile navigation” — drawer overlay, lock page scroll | [EX-05](examples/EX-05-renderer-page.md) |
| Stay-on-page save / toggle (live DOM) | **09 §3** (block-while-in-flight, desktop+mobile), **08** | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Paginated list (users, logs, items) | **06**, **09 §3** “Paginate accumulating lists” — **MUST** ship, **MUST** be AJAX | [EX-04](examples/EX-04-database-crud.md), **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| List search (articles, catalog, …) | **09 §3** “Interactive AJAX search” — **ASK** in the plan; lookup lists **MUST** unless declined | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| List UX (filters, sort, empty, bulk, …) | **09 §3** “List UX” — **ASK** / **MUST** table | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Delete (confirm dialog) | **09 §3** “Confirm before delete” | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Custom `$dotapp` library / jQuery port | **09 §4** (esp. §4.C) | **[EX-15](examples/EX-15-dotapp-js-library.md)** |
| Database query | 06, 18 | [EX-04](examples/EX-04-database-crud.md) — `raw()`: every `?` is a placeholder, including comments |
| Tables / migrations | 07 (rename `installed_*` → `install.php` after a new version); **07 §0** probe-then-CREATE, **no** `CREATE TABLE IF NOT EXISTS` | [EX-13](examples/EX-13-schema-migrations.md) |
| **Secure form (HTML fields + submit)** | **08, 09** | **[EX-01](examples/EX-01-secure-form-complete.md)**, [EX-02](examples/EX-02-secure-form-edit-api.md) |
| AJAX without a form (`load` only) | **08, 09** | [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 |
| Encrypt IDs / unique `$key2` | **11 §8, 05, 08** | [EX-02](examples/EX-02-secure-form-edit-api.md), [EX-14](examples/EX-14-auth-and-2fa.md) |
| Validation / error responses | 19 | [EX-09](examples/EX-09-validation-and-errors.md) — **`data(true)`** = original; `data()` = protected |
| Config / keys | 10 | [EX-08](examples/EX-08-config-secrets.md) |
| Bridge click | 09 | [EX-07](examples/EX-07-bridge.md) |
| Auth / 2FA / permissions | **11** (incl. §11 privilege / secrets / SQL owner / bot **warn**), **09** (`twoFactor`), **19** (`data(true)`) | [EX-14](examples/EX-14-auth-and-2fa.md) |
| **Any attack surface (input, auth, output, upload, public endpoint)** | **24** attack vectors — open the matching section, then §11 threat pass | [EX-01](examples/EX-01-secure-form-complete.md), [EX-14](examples/EX-14-auth-and-2fa.md) |
| **New table / migration / any loop or query you care about** | **25** performance — §1 memory, §2 I/O, **§3 indexes**, §4 column types, §5 big lists, §6 frontend | [EX-13](examples/EX-13-schema-migrations.md), [EX-04](examples/EX-04-database-crud.md) |
| **Every file you write (CRCchecking + PHPDoc purpose + Why/About/Section)** | **25 §7** | [EX-01](examples/EX-01-secure-form-complete.md) |
| **Module hooks / connect modules** | **41** — `module.{mod}.{name}.hook` + `.hooks` (not every save); listener own routes; **`triggerWithVeto` / `Veto`** | **[EX-16](examples/EX-16-module-hooks.md)** |
| **Replace a judged output (Extender)** | **12 §10** / **00 §2h** — owner `exists()`/`call()`; ordinary result returns, only `isOriginal()` continues; `extend()` in `Listeners::register()`; target URLs in the **listener** map; own Module routes or `[]`; controller string preferred | **[EX-17](examples/EX-17-extender.md)** |
| Cache / logs / sessions | 20 | [EX-10](examples/EX-10-cache-logger-session.md) |
| Email / SMS / QR | 21 | [EX-11](examples/EX-11-email-sms-qr.md) |
| AI / search / MCP | 22 | [EX-12](examples/EX-12-ai-search-mcp.md) |
| Services index | 12 (`dotapp.catchall` = core debug funnel; **`triggerWithVeto` / `Veto`**); **41** = `module.{mod}.{name}.hook` + `.hooks` | [EX-16](examples/EX-16-module-hooks.md) |
| Tests | 13 | — |
| Anything uncertain | 14, 15, **23**, then `app/parts/` | examples/README.md |
| **Debug / “it doesn’t work”** | **23** (grep middleware + `crcCheck` count first; §1b catch trail; **§1c `dotapp.catchall` event tracer**) | [EX-01](examples/EX-01-secure-form-complete.md), [EX-10](examples/EX-10-cache-logger-session.md) |
| **Debug tool / see all events** | **01** `dotapp.catchall` (core fires every `trigger()`); **18** §9 `dotapp.catch` (failures); **41** `module.{mod}.{name}.hook` + `.hooks` | [EX-10](examples/EX-10-cache-logger-session.md), [EX-16](examples/EX-16-module-hooks.md) |
