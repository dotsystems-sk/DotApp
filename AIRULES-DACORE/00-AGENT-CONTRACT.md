# 00 — Agent Contract (HARD LAWS)

**Read this file before every DotApp task.**  
**AIRULES is the single source of truth.** It supersedes leftover `.cursorrules` / `*_AI_guide.md` / `database_guide.md` from older installs.

This is **DotApp** — not Laravel, Symfony, CodeIgniter, Blade, Twig, Eloquent, or jQuery.

---

## 1. Edit boundaries

### ALLOWED (edit freely when asked)

| Path | Notes |
|------|--------|
| `app/config.php` | **Only** framework file agents may edit. Secrets, DB, drivers, module overrides. |
| `app/modules/<YourModule>/` | **Only the module you are currently programming** — PHP, views, **and that module’s assets**. Not another module. Not DACore. |

### ASK FIRST (do not touch unless the user explicitly requests)

| Path | Preferred alternative |
|------|------------------------|
| `app/listeners.php` | Prefer `module.listeners.php` inside your module. |
| `.htaccess` | Prefer `php dotapper.php --create-htaccess`. |
| Another **your-project** module's folder | Only with explicit permission naming that module. **Never propose** editing DACore instead. |

### FORBIDDEN (except the explicit framework-author exception below)

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
| Any file outside the target module + `app/config.php` | Scope violation (DACore: see below) |

If you believe a core bug exists: **stop and ask the user**. Do not patch core unless the explicit framework-author exception below applies.

### Framework-author exception

The user is the author of the DotApp framework.

When the user explicitly names and authorizes a framework-core task, the agent may edit only the core files required by that task.

Current authorized task:

- Separate listener routes from module initialization routes.
- Preserve compatibility with existing modules and old `modulesAutoLoader.php` files.
- Extend the module optimizer to store initialization routes and listener routes separately.
- Inspect dotapper generators and optimizer integration for the old route format.
- Update the DACore optimization overview for the new format.

Allowed core paths for this task:

- `app/DotApp.php`
- `app/parts/Module.php`
- `app/parts/Listeners.php`
- `dotapper.php` only when its optimizer, help text, or module scaffolding requires updating
- framework tests required for this task
- relevant AIRULES documentation

The authorization does not include:

- `index.php`
- `initializedb.php`
- `app/vendor/**`
- `app/runtime/**`
- unrelated files under `app/parts/**`
- unrelated framework behavior

The main chat model MUST implement and review the runtime logic in `app/DotApp.php`, `app/parts/Module.php`, and `app/parts/Listeners.php`.

For this task, the user explicitly permits the Grok 4.6 High model to inspect dotapper and implement the DACore optimization overview. It MUST NOT modify the runtime core files owned by the main model.

This exception is temporary and applies only to the listener-route separation task.

### Framework-author exception — veto event API

The user explicitly authorizes a general core veto API without integrating it into any module action yet.

Allowed paths for this task:

- `app/DotApp.php`
- `app/parts/Events.php`
- new `app/parts/Veto.php`
- framework tests under `app/tests/`
- relevant AIRULES documentation and examples

Required compatibility:

- Existing `trigger()` behavior MUST remain unchanged.
- Only `triggerWithVeto()` observes listener returns.
- Only a returned `Dotsystems\App\Parts\Veto` object stops dispatch.
- `false`, `null`, strings, arrays and other old listener returns MUST remain non-vetoing.
- No DACore or module business action is wired to veto as part of this core-only task.

The main chat model MUST implement and review this runtime core change.

### DACore files — strict default, informed exception only

**Default (MUST):** do **not** edit, patch, add, or delete anything under `app/modules/DACore/` — PHP, views, **assets**, menu rows, settings, anything. Implement in the **current** module only. Consume `DotApp::call("DACore:…")`.

**MUST NOT propose** a DACore edit. Never offer “I can patch DACore”, “let me change the admin shell”, or “this belongs in DACore”. Solve it in `app/modules/<YourModule>/`.

**Exception (user-initiated, informed):** edit DACore **only** when **all** of these are true:

1. The user **themselves** asked to change DACore / `app/modules/DACore/` (not a vague “fix the admin”).
2. You **warned once** that the next DACore package update **wipes** local changes; the durable path is their own module.
3. They **confirmed** they know that and still want the DACore edit.

Then — and only then — you may edit DACore **for that request**. Otherwise: **strict ban**.

See [§7](#7-dacore-is-sacred-same-rank-as-framework-core).

---

## 2. Mandatory workflow

1. **Identify the target module** (or create one). If it is a **new DACore-bound module**, **ASK in chat first**: shared full sidebar vs **module-own** menu (`Page@withMenu` `$menuId`). Do not guess. From about five items, do not dump leaves under a header. [31](31-DACORE-MENU.md). **Also ASK one grouped identity question:** public display name + one-sentence purpose; installer preview as text-only, compact logo near the heading, or wide banner above the summary; existing local asset + alt text; and whether the identity also appears on the module landing/header. The menu Remix icon is separate from the logo. No preference → clean text-only preview, do not block work. [05](05-VIEWS-TEMPLATES-ASSETS.md) §8b, [35](35-DACORE-INSTALL.md) §3b. **Sending mail, inbox notifications, or SMS?** Open [38](38-DACORE-EMAIL.md) / [37](37-DACORE-NOTIFICATIONS.md) / [39](39-DACORE-SMS.md) — do not invent SMTP or a gateway. **DACore hooks (MUST):** before scaffolding a DACore-bound module, **MUST** read **`app/modules/DACore/.hooks`** (read-only catalog of `module.dacore.*.hook` and `.veto`) so the module can subscribe instead of reinventing login/lockout/mail/SMS/template-delete events. **Also ASK** for `about.php` copy: module description HTML, license HTML, and changelog HTML for **1.0.0**. If the user has not given that text, **ASK** — do not invent legal terms or a fake changelog. **Pack vs host:** if this module is a **theme/gateway/locale pack**, or a **host** that will pick among packs (CMS “choose template”), **ASK** the `extra1`…`extra5` tokens ([35](35-DACORE-INSTALL.md) §3c). A normal app module omits extras.
2. **Read** the relevant AIRULES docs for the task (routing / views / DB / forms / JS).
3. **Generate** with `dotapper.php` whenever possible (module, controller, model, middleware).
4. **Implement** only inside the allowed paths.
5. **Tables:** every table your module owns **MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names, `dotapp_*`, or `dacore_*` for module data. See [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md) §3.
6. **Migrations:** after you add a version in `Installation.php`, **MUST** rename `installed_*_install.php` back to `install.php` so the next page load runs it. Do not leave this for the user. Develop with **`install.php`** and **live** root init files. Pack `dainstall.php` + `init/` **only** for a **DACore-bound** module and only when the user asks ([35](35-DACORE-INSTALL.md) §4–§5). A non-DACore module: no zip — `install.php` and copy the folder. **Create + zip in one ask:** [§2e](#2e-dacore-installable-zip-must--law). **MUST** keep `about.php` in the module root: every `Installation.php` version key **MUST** have a matching changelog entry. If the user did not supply the release notes, **ASK**.
7. **Lists:** any screen that lists records that **can accumulate** (users, logs, items, orders, messages, files, events) **MUST** ship SQL paging **and** an **interactive AJAX pager** in the **first** version. Empty table today is not an excuse. A pager that reloads the admin shell is not a pager. **HTML / classes / `live(el, e)` / encrypted `data-page` / COUNT vs `paginate()['total']`:** follow [40-DACORE-LIST-PAGER.md](40-DACORE-LIST-PAGER.md) **as law**. **Search / list UX:** when **planning**, **ASK** (search, filters, sort, bulk, page size, remember in DSM, CSV only if it fits). Lookup lists **MUST** ship AJAX search unless declined. Empty state, sticky header, match highlight: **MUST**. See [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
8. **Finish gate (LAW):** after **every** code chunk **and** before claiming done — **MUST** [§2c](#2c-finish-gate-must--law). **MUST NOT** skip. Tick [17-CHECKLISTS.md](17-CHECKLISTS.md) Finish gate.
9. **Cursor credits:** when **planning** a programming task, **ASK** whether more expensive models may be used. Subagents **MUST inherit** the chat model. See [§2b](#2b-cursor-credits--subagents-must).
10. **PHP version:** when **planning** programming, **ASK** whether to stay on **PHP 7.4+** (DotApp default) or write for a higher version. No answer → **7.4+**. See [§2i](#2i-php-version-must).
11. **Debug / “why doesn’t this work”:** **MUST** follow [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) — grep middleware + count `crcCheck()` **before** guessing a core/DACore bug.
12. **Do not wake other modules:** `Module::initializeRoutes()` lists only this module’s URL prefixes (or `[]` for a listener-only module). `Listeners::initializeRoutes()` may list different producer/target prefixes. **MUST NOT** use `['*']` unless the dependency is genuinely global/dynamic and you warned that this listener registers on every request. **MUST NOT** `include` / `require` / `DotApp::call` another module just to list it or show its about/changelog — that lives in DACore `dacore_modules`. After route-map changes: `php dotapper.php --optimize-modules`. Canonical: [03](03-MODULES-AND-ROUTING.md) “Keep other modules asleep”.
13. **Module hooks (LAW):** when a side-effect is worth another module (SMS/mail sent, payment, lockout), **MUST** `Events::trigger('module.{lowercase_modulename}.{hook_name}.hook', …)` with the `Hook:` / `Why:` / `About:` / `Params:` / `Use:` block, and **MUST** document that name in **`app/modules/<YourModule>/.hooks`**. **MUST NOT** fire on every save. Connect by reading **their** `.hooks` and listening in **yours**. A **DACore-bound** module **MUST** read **`app/modules/DACore/.hooks` first** — that is the catalog of events DACore already offers. Canonical: [§2g](#2g-module-hooks-must--law), [41](41-MODULE-HOOKS.md) §6.
14. **Extender (judge):** **MUST NOT** Extender every method. Owner `exists()` + `call()`; an ordinary result returns immediately, while `isOriginal()` alone continues owner logic. Extender registers in **`Listeners::register()`** before Module initialization. Target URLs belong in the listener map; Module keeps its own URLs or `[]`; controller string preferred. **MUST NOT** patch DACore to insert `Extender::call`. Canonical: [§2h](#2h-extender-judge--not-every-method), [12](12-SERVICES.md) §10.
15. **User origin (LAW):** a shop, custom register/login, member area, user import/lookup, or any user list **MUST** follow [42](42-DACORE-USER-ORIGIN.md). Origin is provenance on one globally unique account—not a sandbox, right, tenant store, or module-local Auth session. **ASK** the exact allowed token(s), DACore-form access (default no), and whether this module lists users. Register → create → bound id lookup → stamp → re-read exact token/id; check origin after login, before/on/after 2FA, and in every authenticated module gate. Listing another origin (a DACore-replacement admin) requires explicit confirmation after a whole-app/DB risk warning. **MUST NOT** ship RCE, cross-origin IDOR, or privilege escalation.

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
| **CRC once** | Count `crcCheck(` on **this POST’s** pipeline (middleware + `before` + `#DACore:AuthTest@CRC!` / `LoginAndCRC!` + action) **and** the action’s first PHPDoc line `CRCchecking —` | Two calls (first **burns** the token); CRC on GET/HTML login `before`; CRC on `$request->upload()`; action `crcCheck()` after a CRC API prefix; PHPDoc names a CRC prefix **and** the action still `crcCheck()`; controller/middleware public method with no `CRCchecking —` first line ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| **IDs encrypted** | Views / JS / JSON: `value=`, `data-*`, hidden, payload | Plain `7` / `{{ var: $id }}` as an id sent to the browser. **MUST** `{{ enc(Shop.item.id): $id }}` with a unique `$key2`. Decrypt `=== false` → reject. Still `Auth::can` / ownership in PHP. **Pager:** encrypted `data-page` ([40](40-DACORE-LIST-PAGER.md)) |
| **Queries bound** | Every SQL in the chunk | User input concatenated into SQL; `$qb->raw()` `?` that is not a binding (comments / `COMMENT` count); mix `?` and `:named` |
| **Inputs** | Request + persist | Password / HTML / hash from `$request->data()` not `data(true)`; missing `form()` / `Validator` where required; persist with only an FE overlay — PHP **MUST** still refuse (incl. step-up 2FA on dangerous admin actions) |
| **Middleware / route conflicts** | `module.init.php` + Middleware vs the action | Login `before` missing **or** handlers outside `Auth::isLogged()`; CRC layer **and** action `crcCheck()`; CRC on a GET gate; `#DACore:AuthTest@check!` used as a rights guard (it **ignores** passed rights — use `#YourModule:Rights@check!`) |
| **DACore files** | The diff | Any edit/add/delete under `app/modules/DACore/` unless the informed exception in [§1](#dacore-files--strict-default-informed-exception-only) |
| **Visible outcome** | Save / toggle / delete / form JS + `ajaxReply` | Silent `.after()` / no `message`; admin save without a DACore toast; public field errors without marking the input ([§2d](#2d-visible-outcome-must--law)) |
| **Layout / UX-UI** | Diff of views, CSS, and chrome (buttons, footers, cards, modals) | Control flush to the parent edge; missing padding (especially **below** a Save button); uncentered/cramped vs siblings; `pt-0` footer with no `pb-*`; desktop-only placement ([§2f](#2f-layout-and-uxui-must--law)) |
| **Catch reported** | `rg -n "catch \(|catch\(" ` + every `execute(` in the chunk | A `catch` (or `execute()` `$err`) that does **not** call the module’s report helper → `dotapp.catch` + `dotapp.catch.error|info`; ad-hoc payload keys; a secret/token/rights blob/request body inside the payload ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9) |
| **Privilege / records** | Persist + GET view vars + SQL | Secret (TOTP/QR/key) in a read-only view; mutate a more privileged target; `WHERE id` only after decrypt; own password without current; public noauth shipped with no bot warning ([11](11-AUTH-AND-CRYPTO.md) §11); custom user UI listing **another origin** without an explicit ASK + warning ([42](42-DACORE-USER-ORIGIN.md)) |
| **Origin / global Auth** | Every create/login/2FA/gate/list/write touching users | `Auth::createUser` id assumed; unchecked `registerOrigin`/`stampOrigin`; no `read()` exact token+positive id verification; route checks only `Auth::isLogged()`; mismatch does not `Auth::logout()`; list does not INNER JOIN `dacore_users_profiles` + bind expected origin; duplicate/foreign response enumerates; `findByExtra` treated as authorization ([42](42-DACORE-USER-ORIGIN.md)) |
| **Perf / readability pass** | The greps in [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §8 | `->all()` on a growing table, a query/HTTP/rights check/log **inside** `foreach`, `select('*')` on a list, O(n²) lookup or array copy per row, a new `WHERE`/`ORDER BY` column with **no index**, an index with no comment naming its query, a duplicate of a library DACore ships, a public method with **tags-only PHPDoc** (`@return array<string, mixed>` and no purpose sentence), comments that restate the code |
| **Threat pass** | The 12 greps in [24](24-ATTACK-VECTORS.md) §11 on this chunk | Injection (SQL/XSS/command/deserialize), input in a header or redirect, unbounded public POST, `getMessage()` / `var_dump` in the reply, upload without ext+MIME+header check, weak randomness for a token, a direct write to `dacore_*` / `users_rights*` |
| **Hooks** | Grep `Events::trigger(` **and** `Events::triggerWithVeto(` vs `app/modules/<ThisModule>/.hooks` | Useful side-effect (SMS/mail/paid/lockout) with no `module.{mod}.{name}.hook`; `.veto` fire without a **Veto contracts** heading; old `shop.item.saved` shape; trigger without `Hook:`/`Why:`/`Params:`/`Use:`; hook on a trivial save with no named `Use:`; secrets; `trigger()` inside a growing `foreach`; `.hooks` in `assets/` ([41](41-MODULE-HOOKS.md)) |
| **Extender** | New page/cart/export renderer **or** `Extender::` in the chunk | Spray on every persist/helper; `extend()` delayed to Module `initialize()`; target URLs in Module map; listener-only Module `[]` with omitted/`null` listener routes; `.loaded` for initialize-time; `call()` without owner `exists()`; ordinary result does not return immediately; `original()` marker returned/serialized instead of checked with `isOriginal()`; fake `NEXT`; `$request` / secrets; Events; `['*']` just to attach; patch of another module or DACore ([12](12-SERVICES.md) §10, [§2h](#2h-extender-judge--not-every-method)) |
| **PHP 7.4+** | Diff of new PHP | PHP 8+ syntax on a 7.4+ module: `match`, `?->`, union/`mixed` types, named args, constructor promotion, `#[…]`, `enum`, `readonly`, `never`, `str_contains` / `str_starts_with` / `str_ends_with` — unless the plan named a higher version ([§2i](#2i-php-version-must)) |
| **Comments** | Diff of PHP/JS | Logical step without `// Why:`; new page action without `// About:` / `// Section:`; PHPDoc with no purpose sentence / tags-only `@return array<string, mixed>`; `Controllers/` / `Middleware/` public method whose PHPDoc does not start with `CRCchecking —` ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| **Rest of AIRULES** | Touched files vs [§4](#4-no-foreign-framework-patterns) / [§5](#5-security-non-negotiables) / [17](17-CHECKLISTS.md) | Lists without the [40](40-DACORE-LIST-PAGER.md) pager, `$_SESSION`, Blade, `$.ajax`, `formName` outside `<fo-rm>`, … |

**Pass →** continue or say done. **Fail →** fix **now**. Do not start the next chunk.

User reports a failure **after** ship: [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) (same CRC / middleware hunt; DACore = §7).

### 2d. Visible outcome (MUST — law)

The user **MUST** always see what happened. Silent save and silent fail are **bugs**.

**Every** stay-on-page action (save, toggle, delete, upload, login, form): PHP returns `status` + `message` (and `errors` when the problem is a **field**). JS **MUST** show it. Empty `.after()` is forbidden.

#### DACore admin (`Page@withMenu!`)

**MUST** grep `app/modules/DACore/` **read-only** first — Notiflix (`Notify`, `Confirm`, `Block`), `$dotapp().toast()`, `$dotapp().modal()`, `assets/js/dotapp.toasts.js`, vendor notify. **Use what the shell already loads.** Do **not** invent a second toast library. Disk is the source of truth ([33](33-DACORE-PAGES-AND-UI.md) “Search DACore first”).

| Kind | Channel (**MUST**) |
|------|-------------------|
| Success / failure / CRC / rights | **Toast** — `Notiflix.Notify.success` / `failure` (or `$dotapp().toast()` if that is what you found in DACore). |
| Field validation | Toast with `reply.message` is the admin outcome. Marking the input is optional extra — **do not skip the toast**. |

#### Public / front-office (no admin shell)

Notiflix is **not** there. **You MUST build** feedback in **your** module.

| Kind | Channel (**MUST**) |
|------|-------------------|
| Field validation | **Preferred (FE + BE):** mark the **wrong input** (invalid/red class) **and** the message **on that field** (where + what). PHP **MUST** return `errors` keyed by field. Persist still re-checked in PHP. |
| Success / non-field failure | **Your** module toast / status node + `reply.message`. Never `alert()`. |

Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 “Visible outcome”, [19](19-VALIDATION-AND-INPUT.md), [33](33-DACORE-PAGES-AND-UI.md), [EX-09](examples/EX-09-validation-and-errors.md), [EX-06](examples/EX-06-dotapp-js-boot.md).

### 2e. DACore installable zip (MUST — law)

When the user asks to **pack / zip / export** a DACore-bound module for the DACore plugin installer — including **“create the module and zip it”** — this is a **law**, not a reminder.

Two different files, two different runners. Mixing them **ships a zip that will not install**.

| File | Who runs it | When |
|------|-------------|------|
| **`install.php`** | **Framework** (`Module::installation()` on the next page load), then it **renames** the file to `installed_*_install.php` | **While coding** in the working tree. **MUST NOT** be inside a DACore zip. |
| **`dainstall.php`** | **DACore plugin installer only** (tables, rights, menu). The framework **never** runs this name. | **Inside the zip only.** Same PHP body as `install.php` (`Installation::module('Shop')->install();`). |

**MUST in the zip (copy / transform / restore — do not leave the working tree packed):**

1. Rename `install.php` (or `installed_*_install.php`) → **`dainstall.php`**. Skipping this rename means DACore **does not run** `Installation` — the zip is not an installer package.
2. **MUST NOT** include `install.php` or `installed_*_install.php`. DACore **rejects** a package that still has `install.php` (it treats it as a framework-only drop).
3. Copy live `module.init.php` → **`init/module.init.php`** and live `module.listeners.php` → **`init/module.listeners.php`**. After a successful `dainstall.php`, DACore copies these over the root. Missing `init/` = routes never go live.
4. Root `module.init.php` / `module.listeners.php` in the zip are **inert stubs** (empty `initialize` / `register`, `initializeRoutes` → `[]`).
5. **MUST** include root **`about.php`** (and `about-assets/` if the HTML references images). DACore **rejects** a zip without a valid `about.php`. Version stays only in `Installation.php`. Pack/host modules: optional `extra1`…`extra5` tokens ([35](35-DACORE-INSTALL.md) §3c).
6. **MUST** include root **`.hooks`** when the module fires `module.{thismodule}.*.hook` events ([41](41-MODULE-HOOKS.md)).

**MUST NOT:** zip the working tree as-is with `install.php`; leave `dainstall.php` / inert stubs in the working module after packing; pack a module that is not for DACore; pack `app/modules/DACore/`.

Canonical steps: [35](35-DACORE-INSTALL.md) §4–§5.

### 2f. Layout and UX/UI (MUST — law)

A control that **works** but looks unfinished is a **bug**, same rank as a silent save. General UX/UI principles **MUST** be followed on **every chunk that adds or moves visible chrome** (buttons, forms, cards, footers, modals, lists, drawers). “The POST succeeds” is not enough.

**When adding a button (or any action control) — MUST, every time:**

1. Check **padding on all sides**, especially **below** (and above). The control **MUST NOT** sit flush against the parent’s edge.
2. Place it **deliberately vs the parent**: centered or aligned to the same rhythm as sibling cards/footers — not shoved left by leftover `text-start` / missing flex.
3. Match the **shell**. **DACore admin:** grep existing `card-footer` / `btn` spacing first (read-only). **Public:** your module CSS. Do not invent a second spacing system.
4. Desktop **and** mobile: adequate touch target, wrap without overflow, no hover-only placement.

**Fail now if:** a Save/primary button is glued to the card or page edge; a footer has `pt-0` with **no** compensating bottom padding; a cluster of buttons is cramped or uncentered vs siblings; a new control ignores the parent’s padding box.

Canonical: [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c, [09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [33](33-DACORE-PAGES-AND-UI.md).

### 2g. Module hooks (MUST — law)

Modules **MUST** talk to each other through `Events::trigger` / `Events::on`, not by patching a neighbour. Event name: **`module.{lowercase_modulename}.{hook_name}.hook`**. Fire **only** when another module (or a future one) could log, show history, or sync — SMS/mail sent, payment captured, lockout, finished workflow. **MUST NOT** fire on every save. When you do fire: document the name in **`app/modules/<YourModule>/.hooks`** and put the `Hook:` / `Why:` / `About:` / `Params:` / `Use:` block immediately above `trigger()` ([41](41-MODULE-HOOKS.md) §3).

**DACore catalog (MUST):** when programming a **DACore-bound** module, **MUST** read **`app/modules/DACore/.hooks`** (read-only) **before** writing listeners. Use the names DACore already fires (`module.dacore.*.hook` and **Veto contracts**) instead of inventing them or patching DACore. Canonical: [41](41-MODULE-HOOKS.md) §6.

**MUST NOT:** skip a **named** useful hook because `hasListener` is false; put secrets on the bus; treat ordinary `trigger()` listener returns as a veto (`triggerWithVeto()` + `Veto` only); fire inside `foreach` of a growing list; fire `dotapp.catchall` yourself; invent another module’s event name; use the old `{mod}.{noun}.{happened}` shape; put `.hooks` on a public asset URL. DACore template list-delete is already gated: `module.dacore.email_template_delete.veto` and `module.dacore.sms_template_delete.veto` ([38](38-DACORE-EMAIL.md), [39](39-DACORE-SMS.md)).

Canonical: [41](41-MODULE-HOOKS.md). Sample: [EX-16](examples/EX-16-module-hooks.md).

### 2h. Extender (judge — not every method)

`Dotsystems\App\Parts\Extender` lets **one** handler **replace** a target method for the current request. It is **not** Events, hooks, or `triggerWithVeto()`. No `.hooks` row.

**MUST NOT** sprinkle `Extender::exists()` on every helper, persist, CRC, decrypt, or pager. The agent **MUST** judge first: would another module (or a later one) reasonably **replace how this output is produced** without patching this file?

**Yes →** the **owner** opts in: `Extender::exists()`, then `Extender::call(...)`. An ordinary result returns immediately; only `Extender::isOriginal($result)` continues into the owner implementation. Typical yes: **rendering** a page or fragment, drawing a **cart**, building an **export** / invoice, swapping a checkout quote presentation.

**No / “maybe someday on every method” →** skip. Ordinary CRUD, CRC, decrypt, list internals, catch-bus helpers — **MUST NOT** become Extender targets “just in case.”

When the owner **does** opt in, mechanics are MUST:

- One replacement may return its final result or the unique `Extender::original()` marker. The owner **MUST** check that marker with `isOriginal()` and continue locally; it **MUST NOT** return or serialize the marker. There is no `next()` chain.
- The extending module registers `Extender::extend()` in **`Listeners::register()`** (`module.listeners.php`). Matching listeners register before every matching Module initializes. This is cheap registration; **MUST NOT** query, HTTP, write files, invoke the target, or call `$dotapp->module()` for the target **or itself** merely to attach. A controller-string handler autoloads lazily at `call()`.
- `Module::initializeRoutes()` contains only the extending module’s own URLs, or `[]`. `Listeners::initializeRoutes()` **MUST** list target URLs. With Module `[]`, listener routes **MUST NOT** omit/return `null` (that inherits `[]`). Then `php dotapper.php --optimize-modules`.
- **MUST NOT** use listener `['*']` merely to attach. Allow it only for a genuinely global/dynamic dependency after warning that `register()` runs on every request.
- Prefer a DotApp controller string (`Addon:Pricing@quote!`). Native callables remain legal but skip DotApp DI and **MUST NOT** capture request/secrets.
- Direct listener registration is canonical. Optional lifecycle wrappers may use `dotapp.module.{Target}.init.start` or `.loading`. **MUST NOT** wait for `.loaded`, `.init.end`, or `dotapp.modules.loaded` when the point can run during target `initialize()`.
- One replacement. A second `extend()` for the same class+method throws. Recursion into the same target throws.
- Pass **explicit safe arguments** into `call()`. **MUST NOT** forward `$request`, secrets, tokens, CRC, rights blobs, or request bodies.
- Prefer `exists()`; `exist()` is the alias.

**MUST NOT** skip a judged surface; spray Extender on every method; register the same target in both listener and Module initialization; use Events to replace a method; run the original after an ordinary replacement result; use a public scalar `ORIGINAL` sentinel or invent `next()`; patch another module **or DACore** to insert `Extender::call`.

Canonical: [12](12-SERVICES.md) §10. Sample: [EX-17](examples/EX-17-extender.md).

### 2i. PHP version (**MUST**)

DotApp targets **PHP 7.4+**. Modules **MUST** default to that floor.

**When planning programming, ASK in chat** (do not guess, do not skip):

> Stay on PHP 7.4+ (the DotApp default), or write this work for a higher PHP version?

If they do not name a higher version: **stay on 7.4+**. Typed properties, arrow functions (`fn`), `??=`, nullable types (`?int`), and `void` are fine.

**MUST NOT** until they name a higher version: union types (`int|string`), `mixed`, named arguments, `match`, nullsafe `?->`, constructor property promotion, attributes `#[…]`, `enum`, `readonly`, `never`, first-class callables (`strlen(...)`), `str_contains` / `str_starts_with` / `str_ends_with` (use `strpos` / `substr`).

A silent PHP 8+ upgrade “because it is nicer” is a **bug**.

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
| Feature/row arrays with `'key' => 'time'` / var named `copy` | Renderer sandbox **drops** callable PHP names; prefix keys or pass escaped HTML ([05](05-VIEWS-TEMPLATES-ASSETS.md) §5) |
| `Route::prefix()->group()`, named routes | Imperative `Router::get(...)` in `module.init.php` |
| Register all login-required URLs then login-middleware only | Prefix + `Router::before([$admin, $admin.'/*'], login 403)` **and** `if (Auth::isLogged())` — page **MUST NEVER** show ([03](03-MODULES-AND-ROUTING.md)) |
| Instance controllers + `$this->` | `public static function` controllers |
| `$`, `jQuery`, `$.ajax` | `$dotapp`, `$dotapp().load(...)` |
| `<form>` + manual CSRF only | Prefer `<fo-rm>` + `{{ formName(handler) }}` |
| `{{ formName }}` after `</fo-rm>` | **MUST** between `<fo-rm>` and `</fo-rm>` |
| Plain IDs in HTML/JSON (`value="7"`, `data-id="7"`) | **MUST** `{{ enc(Shop.item.id): $id }}` — unique `$key2` per field |
| `<fo-rm>` around every row button / D&D | `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)) |
| List/form still clickable during `load()` | Cover the region — **DACore admin:** Notiflix or module preloaders; **public site:** module preloaders ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Desktop-only public header / hover menu / no mobile drawer | Overlay drawer L/R; lock page scroll while open; drawer itself scrolls ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Logs / users / items dumped with `->all()`, no pager, or “few rows now so skip” | **MUST** COUNT + LIMIT + [40](40-DACORE-LIST-PAGER.md) pager on **first ship** ([06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Admin edit URL leaves the sidebar with no active item | `withMenu` 7th `$currentFile` = registered list URL ([31](31-DACORE-MENU.md)) |
| Load the whole table / N+1 in `foreach` / `select('*')` for a list | Smallest I/O: `exists()` / `COUNT(*)` / needed columns / one `join` ([06](06-DATABASE.md)) |
| Lookup list with no search / JS-filter of `->all()` | **ASK** in the plan; articles/catalog **MUST** AJAX search unless declined ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `<a href="?page=2">` / `replaceState` / `e.currentTarget` pager | Forbidden — [40](40-DACORE-LIST-PAGER.md): buttons, `live(el, e)`, encrypted `data-page` |
| `$request->data()` for password / HTML / `Auth::login` | **MUST** `$request->data(true)` — `protect()` rewrites `)`, `=`, `%` ([19](19-VALIDATION-AND-INPUT.md)) |
| TOTP/QR in a read-only view; edit a more privileged user; `WHERE id` only | [11](11-AUTH-AND-CRYPTO.md) §11 — mutate right + SQL owner; **warn** if public noauth is bot-bait |
| Custom OTP digit widget / jQuery 2FA plugin | **MUST** `$dotapp().twoFactor` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| `alert()` / `window.confirm()` to delete | Graphical dialog first (`Notiflix.Confirm` on admin, module modal on the public site) ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3) |
| Prompt-echo UI copy (“this user can hide the icon…”) | Product copy a software company would ship ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| Save button flush to the card edge / `pt-0` footer with no bottom padding | Padding vs parent (esp. below); center or match siblings ([00](00-AGENT-CONTRACT.md) §2f, [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c) |
| New admin library without searching DACore | **MUST** grep `app/modules/DACore/` (read-only) + your module first ([33](33-DACORE-PAGES-AND-UI.md)) |
| `f-form` attribute | **Does not exist** — use `<fo-rm>` |
| `$_SESSION` / `session_start()` | **MUST** `DSM::use('Shop')` ([20](20-CACHE-LOGGER-SESSION.md)) |
| JS overlay / modal as the only save or 2FA gate | **MUST** re-check in PHP; FE is UX only ([08](08-FORMS-AND-SECURITY.md)) |
| File/ZIP in `FormData` + `load()` / `<fo-rm>` | **MUST** `$dotapp().uploadFile` + `$request->upload()`; PHP rejects `.php` ([09](09-DOTAPP-JS-AND-BRIDGE.md)) |
| `crcCheck()` in middleware **and** in the action | **MUST** once — first call **burns** the token ([08](08-FORMS-AND-SECURITY.md)) |
| Shipping a chunk / claiming done without the finish gate | **MUST** [§2c](#2c-finish-gate-must--law) after every chunk — grep, do not imagine |
| Silent save / empty `.after()` / admin without DACore toast / public field error without marking the input | **MUST** [§2d](#2d-visible-outcome-must--law) |
| SMS/mail/payment/lockout with no hook, or a trigger not listed in `.hooks` | Fire **`module.{mod}.{name}.hook`** when a future module would subscribe; document in `.hooks` ([41](41-MODULE-HOOKS.md), [§2g](#2g-module-hooks-must--law)) |
| Hook on every save / old `shop.item.saved` name | Judge first; name is `module.shop.sms_sent.hook` ([41](41-MODULE-HOOKS.md)) |
| Tags-only PHPDoc (`/** @return array<string, mixed> */`) / unlabeled Why prose | Purpose sentence **then** tags; **`// Why:`** / **`// About:`** / **`// Section:`** ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7) |
| Controller action with no `CRCchecking —` first line, or that line names a CRC prefix **and** the body still `crcCheck()` | First PHPDoc line names the **real** layer; prefix **XOR** action ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7, [08](08-FORMS-AND-SECURITY.md)) |
| Patch another module (or DACore) to “add a call” | Read **their** `.hooks`, `Events::on` in **yours** ([41](41-MODULE-HOOKS.md)) |
| Premium Cursor subagent (Opus / GPT-5 / xhigh) without asking | **MUST inherit** the chat model; **ASK** in the plan ([00](00-AGENT-CONTRACT.md) §2b) |
| DACore zip still containing `install.php`, or missing `dainstall.php` / `init/` | **MUST** [§2e](#2e-dacore-installable-zip-must--law) — installer **rejects** `install.php` and **never runs** Installation without `dainstall.php` |

Full table: [14-ANTIPATTERNS.md](14-ANTIPATTERNS.md).

---

## 5. Security non-negotiables

1. **Preferred form stack (default for all interactive forms):**
   - Markup: `<fo-rm>` + `{{ formName(handler) }}` (not `f-form`, not Laravel `_token` alone)
   - **MUST:** `{{ formName(handler) }}` sits **between** `<fo-rm …>` and `</fo-rm>` — never before `<fo-rm>`, never after `</fo-rm>` (outside the pair the tag is left unchanged: silent failure)
   - Script: **`/assets/dotapp/dotapp.js` first** (injects random per-session keys — without it secure forms fail)
   - JS: `$dotapp().form(...).before().after()` + `parseReply` + **MUST** block while in flight (**DACore admin:** Notiflix preferred **or** module preloaders. **Public site:** module preloaders — Notiflix is DACore-only)
   - **MUST:** after success, patch the DOM (`reply.html` / data) and a short toast. `<fo-rm>` does **not** reload. No `location.reload()`. `redirectTo` only when leaving the page ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3)
   - PHP: `$request->crcCheck()` **once** then `$request->form([...], "handler", ...)` then `ajaxReply`
   - Full sample: [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md)
2. This stack is **stronger than plain CSRF** (binds handler + action + method, CRC, one-time tokens, JS key material). Use it **only for real HTML forms** (several fields + submit). **MUST NOT** wrap row actions (toggle, delete, reorder, drag-and-drop, paginate) in `<fo-rm>` — those are `$dotapp().load()` + encrypted `data-*` ([08](08-FORMS-AND-SECURITY.md)).
3. Never skip CRC/CSRF for endpoints that receive `$dotapp().load()` / secure forms. **MUST** `crcCheck()` **once** per request — API prefix **or** action, **never both** (first call **burns** the token). Canonical: [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md), [32](32-DACORE-RIGHTS.md).
4. **MUST encrypt every identifier sent to the browser** (`<option value>`, `data-*`, hidden, JSON). Use `{{ enc(Shop.user.id): $id }}` / `Crypto::encrypt($id, 'Shop.user.id')` with a **different `$key2` per field**. Never `value="7"` / `data-id="7"`. Decrypt with the **same** `$key2`; `false` → reject. **MUST still** `Auth::can()` / ownership — encryption is not a substitute for rights ([11](11-AUTH-AND-CRYPTO.md) §8).
5. Never interpolate user input into SQL — use QueryBuilder bindings or `raw($sql, $bindings)`. **MUST NOT** put `?` in `$qb->raw()` unless it is a real binding — comments and `COMMENT 'SMS?'` count too ([06](06-DATABASE.md)).
6. On new apps, generate real `app.c_enc_key` / `rm_key` / `rmrcm_key` (see [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md)).
7. Module settings must have **fallbacks** if the user did not fill `app/config.php`.
8. **MUST paginate accumulating lists** (users, logs, items, …) with the [40](40-DACORE-LIST-PAGER.md) pager (`$dotapp().load()`, `function (el, e)`, encrypted `data-page`). Shipping the list with no pager, or changing pages by reloading the document / `?page=`, is incomplete. Lookup lists **MUST** ship **interactive AJAX search** unless the user declined; other lists: **ASK** in the plan. [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
9. **MUST** store app session state with **`DSM::use('Shop')`**. **MUST NOT** `$_SESSION` or `session_start()` ([20](20-CACHE-LOGGER-SESSION.md)).
10. **MUST** re-check every persist in **PHP** (`Auth::can`, 2FA code, ownership, validation). `crcCheck()` is transport — **once** per request, not again. Frontend modal/overlay/disabled control is **UX only**. Removing the overlay **MUST** still fail on the server ([08](08-FORMS-AND-SECURITY.md)).
11. **MUST** upload files with **`$dotapp().uploadFile`**. **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP: `$request->upload()` — not `crcCheck()` on that endpoint. **MUST** reject `.php` and other executables (extension + `finfo` MIME + headers); FE `accept=` is UX only ([09](09-DOTAPP-JS-AND-BRIDGE.md)).
12. **MUST** take passwords, HTML, and other round-trip values from `$request->data(true)` / `$request->query(true)` (original). `$request->data()` is the **protected** copy (`protect()`). Login/createUser/installer **MUST NOT** hash the protected string. **MUST** show every login failure (`crcCheck`, `form()` `null`/`false`, `Auth::login === false`). Canonical: [19](19-VALIDATION-AND-INPUT.md).
13. **Login-required / admin routes (MUST):** HTML `{DACore prefixUrl}/{ModuleName}/…` + `Gate@login`. **POST API:** `/api/v1/auth|noauth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!` / `@CRC!` at the **start** of `initialize()`; handlers **MUST NOT** `crcCheck()` again. Register login-only handlers **only** inside `if (Auth::isLogged() === true)`. Those pages **MUST NEVER** render for anonymous users. Canonical: [03](03-MODULES-AND-ROUTING.md), [32](32-DACORE-RIGHTS.md).
14. **Documentation (MUST):** English. Every file/class gets a docblock. Every public/static method in **`Controllers/`** and **`Middleware/`** starts PHPDoc with **`CRCchecking —`** naming **where** CRC runs (exact prefix/middleware such as `#DACore:AuthTest@LoginAndCRC!`, or `this action`, or `none` for GET/upload/helper) — then a **purpose sentence** — tags alone (`@return array<string, mixed>`) are a **bug**. Then `@param` / `@return` / `@throws` with **meaning**, not only types. Inline comments **MUST** use the labels **`// Why:`** (every logical step), **`// About:`** (what the chunk is / what the record represents), **`// Section:`** (admin menu or route). **MUST NOT** restate the code (`// increment i`), prompt-echo, omit the labels, or leave dead code / bare `TODO`. **MUST NOT** write `CRCchecking — prefix … MUST NOT crcCheck()` and then call `crcCheck()` in that method. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7, [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md).
15. **Errors (MUST):** persist handlers in `try/catch` (`\Throwable`) — log, structured `ajaxReply`, **never** leak `$e->getMessage()`, **never** empty `catch`. `execute()` **MUST** get **both** callbacks (`$ok` and `$err`); omitting `$err` **throws**. **Every `catch` and every `execute()` `$err` MUST also report to the catch bus:** `Events::trigger('dotapp.catch', $payload)` then `dotapp.catch.error` (aborted) or `dotapp.catch.info` (recovered/expected), with the fixed payload (`severity, module, source, operation, message, exception, code, file, line, time` + `context` ids/counts, `user_id`) — no secrets, tokens, rights blobs or request bodies in it. Route it through **one** report helper per module (in **your** module, never a file under `app/modules/DACore/`) so a future debugger listener cannot break the reply. Canonical: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.
16. **Cheap I/O (MUST):** pick the smallest load — `exists()` / `COUNT(*)` / `limit(1)` / `select` only used columns / `paginate()` / one `join`. **MUST NOT** `->all()` then filter, N+1 in `foreach`, or `Config::db('cache')` “for speed”. Canonical: [06](06-DATABASE.md), [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §2.
17. **Visible outcome (MUST):** the user always sees success and failure. **DACore admin:** search DACore first, then **toast** (Notiflix / `$dotapp().toast()`). **Public:** mark the wrong field (red + message on the input). Canonical: [§2d](#2d-visible-outcome-must--law).
18. **Privilege and records (MUST):** no secret in a read-only view; no grant/mutate above the actor; SQL scoped to owner; own password needs current password; live routes; lockout covers 2FA if you built lockout. Public noauth that bots can hammer: **MUST warn** in chat (CAPTCHA optional — not MUST). Canonical: [11](11-AUTH-AND-CRYPTO.md) §11.
19. **Known attack vectors (MUST):** the catalogue in [24-ATTACK-VECTORS.md](24-ATTACK-VECTORS.md) is **law** — injection (SQL, XSS, command, template, deserialization), channels (headers, redirect, mail, SSRF, mass assignment), identity (CSRF, fixation, brute force, enumeration), access control (IDOR, escalation, wrong guard, tampered fields), browser headers, files/paths, abuse/rate limit, leaks, crypto, third-party/AI/prompt injection. **MUST NOT** ship a chunk that enables one. Open only the sections for the surface you touch, then run the **threat pass** ([24](24-ATTACK-VECTORS.md) §11) on the diff. Fix a vector in **your** module — **never** by patching `app/modules/DACore/`. A vector not listed there is still forbidden — apply the nearest row and **say it in chat**.
20. **Performance, schema and readability (MUST):** [25-PERFORMANCE-AND-CODE-QUALITY.md](25-PERFORMANCE-AND-CODE-QUALITY.md) is **law** — smallest I/O, bounded memory (page big sets, no O(n²), no full-array copies), **indexes designed for the queries you actually wrote** (FK + every `WHERE`/`JOIN`/`ORDER BY` column; composite order equality → range → sort; leftmost prefix; no duplicate prefix indexes), sane column types, cheap frontend (**reuse DACore assets** instead of a second library), and the documentation standard (§7: **`CRCchecking —` first** on controller/middleware public methods, PHPDoc **purpose sentence** then tags, labeled **`Why:`** / **`About:`** / **`Section:`**). Index and query **your** tables only — never `dacore_*`. Run the perf pass ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §8) with the finish gate.
21. **Layout and UX/UI (MUST):** general UX/UI principles **MUST** be followed **at all costs** on every visible control. Adding a button **MUST** include a padding check vs the parent (especially bottom), deliberate alignment (center / same rhythm as siblings), and desktop+mobile. A flush Save on the card edge is a **bug**. Canonical: [§2f](#2f-layout-and-uxui-must--law), [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c.
22. **Module hooks (MUST):** useful side-effects **MUST** `Events::trigger('module.{mod}.{hook_name}.hook', …)` with the comment block and a `.hooks` row. **MUST NOT** fire on every save. Listen in **your** module; do not patch the owner. A DACore-bound module **MUST** read `app/modules/DACore/.hooks` first. No secrets on the bus. Canonical: [41](41-MODULE-HOOKS.md) §6.
23. **Extender (judge — not every method):** owner `exists()` + `call()`; ordinary result returns, only `isOriginal()` continues owner logic. Extender `extend()` belongs in `Listeners::register()`, target URLs in listener map, own Module routes or `[]`, controller string preferred. **MUST NOT** use `.loaded` for initialize-time, spray on every method, invent `next()`, return the marker, use Events, pass `$request`/secrets, or patch the owner / DACore. Canonical: [§2h](#2h-extender-judge--not-every-method), [12](12-SERVICES.md) §10.
24. **PHP version (MUST):** default **PHP 7.4+**. When **planning**, **ASK** whether to stay on 7.4+ or write for a higher version. No answer → 7.4+. **MUST NOT** ship PHP 8+ syntax (`match`, `?->`, union/`mixed`, named args, promotion, attributes, `enum`, `readonly`, `str_contains`, …) unless they named a higher version. Canonical: [§2i](#2i-php-version-must).
25. **User origin (MUST):** global users/email/session; origin is provenance, not isolation supplied by the framework. Your module **MUST** create and verify its catalog/profile mapping, use generic duplicate/foreign failures, enforce exact origin on login + 2FA + every route gate, and INNER JOIN the profile with a bound origin on lists/writes. DACore `dacore_login` is only its form allow-list. A DACore-replacement user admin **MUST** be asked and warned. No RCE, cross-origin IDOR, or grant of `dotapp.root` / DACore `users.*` from a custom form. Canonical: [42](42-DACORE-USER-ORIGIN.md).

---

## 6. Identity reminder (paste into new PHP files)

```php
<?php
/**
 * DOTAPP MODULE FILE
 * - Controllers: Module:Controller@method!  (! = no DI params)
 * - Database: DB::module("RAW")->q(...)->all()|first()|execute() — execute MUST both callbacks; persist try/catch; raw() every ? is a placeholder (not in comments)
 * - Tables: {lowercase_modulename}_*  (Shop → shop_items) — NEVER items, dotapp_*, or dacore_*
 * - Templates: {{ var: $x }}  — NOT {{ $x }}. VIEW = outer file; setLayout+renderView fills {{ content }} in that view (or renderLayout / str_replace)
 * - Forms: <fo-rm> only for real multi-field submit; row actions = load() + data-*; crcCheck() once (API prefix XOR action)
 * - FE ids: {{ enc(Shop.item.id): $id }} unique $key2 per field; Auth::can still required
 * - Privilege: no secret in read-only views; no escalate; SQL owner scope; own password needs current; warn user if public noauth is bot-bait (11 §11)
 * - Attacks (24): escape before echo ({{ var: }} does NOT escape); whitelist sort columns + insert columns; no input in header()/redirect/HttpHelper URL; no eval/exec/unserialize on input; random_bytes for tokens; hash_equals for secrets; throttle public POST; rights via YourModule:Rights@check
 * - JS: $dotapp — NOT jQuery $; after save/toggle MUST patch DOM + toast (no reload); MUST overlay until request ends (DACore admin: Notiflix or module; public site: module preloaders; desktop+mobile)
 * - Public site nav: mobile drawer slides L/R over the page; lock document scroll while open; drawer list scrolls; contacts+compact search in the drawer unless large search is its own mobile section
 * - Lists: accumulating records (users/logs/items) MUST COUNT+LIMIT + AIRULES/40 pager on first ship — NOT all() dump, NOT ?page= / replaceState / e.currentTarget
 * - Packs/host: about.php extra1…extra5 tokens so a CMS can list templates — NOT glob/include other modules (35 §3c)
 * - Cheap I/O: exists/COUNT/limit(1)/needed columns/paginate/one join — NOT all() then filter, NOT N+1
 * - Memory: page big sets, keyed map instead of in_array in a loop, unset the raw copy, stream files — NOT load-all-then-filter
 * - Indexes (25 §3): FK + every WHERE/JOIN/ORDER BY column; composite = equality → range → sort; leftmost prefix; one comment line per index naming its query; never touch dacore_*
 * - Docs (25 §7): Controllers/Middleware public PHPDoc MUST start with CRCchecking — (exact prefix/middleware XOR this action XOR none); then purpose sentence, then @param/@return/@throws with meaning — NOT tags-only (`@return array<string, mixed>`); NOT prefix CRC + crcCheck() in the same method; inline MUST use labels // Why: (logical step), // About: (what this chunk is), // Section: (menu/route) — NOT narration of the code, NOT unlabeled Why prose
 * - Catch bus (18 §9): every catch + every execute() $err → one report helper → Events::trigger('dotapp.catch', $p) then 'dotapp.catch.error'|'.info'; payload = severity, module, source, operation, message, exception, code, file, line, time, context (ids/counts), user_id — NO secrets/tokens/rights/bodies
 * - Hooks (41): useful side-effects (SMS/mail/paid/lockout) MUST Events::trigger('module.{mod}.{name}.hook') + Hook/Why/About/Params/Use block + .hooks — NOT every save; NOT secrets; NOT patch the other module; NOT old shop.item.saved shape; DACore-bound MUST read app/modules/DACore/.hooks first
 * - Extender (12 §10): judge first; owner exists()/call(); ordinary result returns, only isOriginal() continues; extend in Listeners::register(); target listener routes; own Module routes or []; prefer controller string; NOT next(), marker response, Events, $request/secrets, duplicate, or DACore patch
 * - Search: ASK in the plan; lookup lists (articles/products) MUST AJAX search unless declined — debounce, 3+ chars, SQL+paginate, NOT JS filter
 * - 2FA boxes: $dotapp().twoFactor — do not invent OTP widgets
 * - Deletes: graphical confirm first — never alert()/confirm()
 * - UI copy: product language — never prompt-echo / “this user can…”
 * - Session: DSM::use('Shop') — NEVER $_SESSION / session_start()
 * - Save checks: PHP MUST re-verify — FE modal/overlay is UX only
 * - Files: $dotapp().uploadFile — NEVER FormData + load()/fo-rm; PHP MUST reject .php (ext+MIME+headers)
 * - Request: data() = protected; data(true) = original — MUST true for passwords/HTML/hashes
 * - Login-required / admin: {prefixUrl}/{Module}/… + Gate@login 403 Response; MUST register handlers inside Auth::isLogged()
 * - Comments: English; labels Why: / About: / Section: — not every line, not unlabeled
 * - PHP: default 7.4+; ASK in the plan for a higher version — NOT match / ?-> / union mixed / named args / promotion / attributes / enum / readonly / str_contains unless they said yes (00 §2i)
 * - Cursor: inherit parent model for subagents; ASK before expensive models; Composer 2.5 = file hunt only, not the coder
 * - Finish gate (LAW): after every chunk grep crcCheck once, enc ids, bound SQL, data(true), middleware vs action, Events::trigger vs .hooks — 00 §2c / 41
 * - Visible outcome (LAW): user always sees save/fail; DACore admin = search DACore then toast; public = mark the wrong field — 00 §2d
 * - Layout / UX-UI (LAW): buttons MUST have padding vs parent (esp. bottom); center/align to siblings; never flush to the card edge — 00 §2f
 * - After a new Installation.php version: rename installed_*_install.php → install.php (agent does it)
 * - DACore zip (LAW, 00 §2e): in the zip MUST be dainstall.php (renamed from install.php) + init/ live copies + inert root init. MUST NOT leave install.php in the zip — DACore rejects it and never runs Installation. Working tree stays install.php. NEVER pack app/modules/DACore/. Non-DACore: no zip.
 * - DACore: search DACore (read-only) + this module before writing a new library/widget — do not reinvent
 * - DACore menu: edit/detail MUST withMenu 7th $currentFile = registered list URL when the path is not under that leaf — NOT a menu row per edit URL
 * - Origin (42): global identity/session, not sandbox. Installer register result checked; create exact-id lookup; stamp + read token/id verify; login/2FA/every gate exact-origin + logout on mismatch; lists INNER JOIN profiles + bound origin; generic duplicate/foreign reply; ASK before listing another origin; no RCE/IDOR/escalation
 * - DACore: operators MUST keep 2FA on; dangerous actions MUST step-up 2FA (32 §6)
 * - DACore AI writes: ui_events (name = tool id) + DACore.AI.UIEvent on the matching page only
 * - Edit only this module + app/config.php (this module’s assets too). Do not edit other modules.
 * - Never edit app/parts/. Never edit app/modules/DACore/ unless the user themselves asked and confirmed the update wipe (00 §1).
 * - Never propose a DACore edit — implement in this module.
 * See AIRULES/00-AGENT-CONTRACT.md
 */
```

---

## 7. DACore is sacred (same rank as framework core)

This rulebook variant covers **framework + DACore**. DACore is an admin-UI **module**, not framework core — treat its files like core **by default**.

**Why:** DACore is installed and updated as a complete package. Any edit, patch, extra file, or “small addition” inside `app/modules/DACore/` **vanishes on the next DACore update**. There is no merge.

**Default:** **MUST NOT** edit DACore (files, assets, menu, settings). **MUST NOT** propose a DACore edit. Work **only** in the module you are programming (`app/modules/<YourModule>/`, including **that** module’s assets). Use public APIs: `DotApp::call("DACore:…")`.

**Informed exception:** If the user **on their own** asks to edit DACore **and** confirms they accept the update wipe, you may edit DACore for that request. Warn once, then wait. Vague “fix the admin” is **not** permission. Details: [§1](#dacore-files--strict-default-informed-exception-only).

| Never (default) | Instead |
|-----------------|---------|
| Edit any existing file under `app/modules/DACore/` | Current module + `DotApp::call("DACore:…")` |
| **Add** controllers, views, JS, CSS, SQL, or any other file into DACore | Create **your own** module: `app/modules/<YourModule>/` |
| Offer / suggest a DACore patch | Implement in the current module; do not mention DACore as a place to write |
| Quick-fix a DACore bug in place without an informed ask | Warn (wipe on update); only proceed if they still insist on editing DACore |
| Fork / copy DACore internals into DACore | Read DACore source **read-only**; call only documented APIs |

| Rule | Detail |
|------|--------|
| **Never write directly** to `dacore_menu`, `dacore_ai_tools`, `dacore_installations`, `dacore_modules`, `dacore_plugin_logs`, `dacore_settings`, `dacore_notifications`, `dacore_notifications_inbox`, `dacore_email_senders`, `dacore_email_templates`, `dacore_sms_senders`, `{prefix}users_rights*` | Use the registration / push / `Email@send` / `Sms@send` APIs. **Read** `dacore_modules.extra1`…`extra5` via `DACore:Plugins@listByExtra!` (or a bound SELECT) — never `UPDATE` those columns from your module ([35](35-DACORE-INSTALL.md) §3c) |
| Register menu / rights / AI tools | In **your** `Installation.php`, not per request |
| Installer-managed user groups (DACore 1.0.8+) | After registering rights, call `DACore:Roles@createGroup!` with stable `(creator, groupid)` and exact `module/rightname` pairs. It creates `editable=0`; assign/remove/delete via `Roles@*`. Never store its numeric id or write `users_roles*` / `users_rights` directly. [32](32-DACORE-RIGHTS.md) §1 |
| **Develop vs pack** | While coding: **`install.php`** + **live** root init files. After a new version: rename `installed_*` → `install.php`. User asks to zip a **DACore-bound** module (**including create+zip**): **MUST** [§2e](#2e-dacore-installable-zip-must--law) — zip has **`dainstall.php`** (rename from `install.php`), **`init/`** live copies, **inert** root init, **no** `install.php`. Working tree restored. Not a DACore module: no zip. **MUST NOT** pack `app/modules/DACore/`. [35](35-DACORE-INSTALL.md) §4–§5. |
| **New module menu** | **ASK** before scaffolding: shared sidebar vs module-own (`withMenu` `$menuId`). Group `type => 2` when many items stay in the global tree; large modules: header + **one** entry, inner pages pass the branch id. Do not register “Return back”. [31](31-DACORE-MENU.md) |
| **Active sidebar on subpages** | Edit/detail **MUST** keep the list/section leaf highlighted. `withMenu` 7th `$currentFile` = registered list URL when the path is not under that leaf (`/users/4` vs `/users-list`). **MUST NOT** register a menu row per edit URL. [31](31-DACORE-MENU.md) Active sidebar |
| Render admin pages | `DACore:Page@withMenu!` — never build your own HTML shell |
| Missing widgets / ported UI | **MUST search DACore first** (read-only). Then **MUST** add CSS/JS in **your** module (`$css`/`$js` on `withMenu`) only if nothing fits. Prefix classes `{lowercase_modulename}_*`. Match DACore colors. Never patch DACore. |
| Admin JS / ports | DACore runs on **`$dotapp`**. jQuery may coexist for **UI only**. **All requests** use `$dotapp().form` / `load` / bridge — never `$.ajax`. Porting jQuery **is** writing a new `$dotapp().fn` library — **ask**, then rewrite (do not wrap `$.fn`). Playbook: [09](09-DOTAPP-JS-AND-BRIDGE.md) §4.C, [EX-15](examples/EX-15-dotapp-js-library.md). If DACore already ships the widget, use it. |
| **Notiflix** | **DACore admin shell only.** On `Page@withMenu!` you may use it (preferred) **or** your module overlay. Public / front-office pages **MUST** ship **module preloaders** — Notiflix is not there. Preloaders are **MUST** either way ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3). |
| **Operator 2FA** | DACore operators **MUST** have at least one 2FA method (TOTP / SMS / email) and **MUST NOT** be able to turn it off. Dangerous admin actions **MUST** re-prompt with `$dotapp().twoFactor` and verify in **your** module — not `Auth::confirmTwoFactor` (login stage 2 only). See [32](32-DACORE-RIGHTS.md) §6. |
| **AI write → open page** | Write tools **MUST** return `ui_events` (`name` = tool id) when an admin screen shows that data. Page JS listens `DACore.AI.UIEvent`, filters by name, AJAX-refreshes — no `location.reload()`. Wrong page **MUST NOT** refresh. No secrets in payload. [34](34-DACORE-AI-TOOLS.md) §5. |
| **Lists / pager** | Users, logs, items **MUST** follow [40](40-DACORE-LIST-PAGER.md) on first ship (`dacore-list-pager`, `live(el, e)`, encrypted `data-page`, COUNT not `paginate()['total']`). Lookup lists **MUST** AJAX search unless declined; **ASK** on other lists. |
| **Search DACore first** | Before a new JS/CSS library, `$dotapp().fn` widget, or page chrome: grep `app/modules/DACore/` (read-only) and your module. The base already has many subpages and libraries. Use what exists; write new code only in **your** module. [33](33-DACORE-PAGES-AND-UI.md) “Search DACore first”. |
| **Read DACore `.hooks` first** | Before scaffolding a DACore-bound module’s listeners / audit / mail-SMS history / template-delete protection: open **`app/modules/DACore/.hooks`** (read-only catalog). Subscribe in **your** module. Do not invent `module.dacore.*` or patch DACore. [41](41-MODULE-HOOKS.md) §6. |
| Permission guard | Your own `#YourModule:Rights@check!` — `#DACore:AuthTest@check!` **ignores** the rights you pass |
| Admin routes | Always prefixed with `Config::module("DACore","prefixUrl")` |

Start at [30-DACORE-OVERVIEW.md](30-DACORE-OVERVIEW.md).

---

## 8. Conflict resolution

| Conflict | Winner |
|----------|--------|
| Leftover `.cursorrules` / `*_AI_guide.md` vs AIRULES | **AIRULES** |
| Leftover `database_guide.md` invented APIs | **Ignore** — follow [06-DATABASE.md](06-DATABASE.md) |
| User explicit instruction to edit core | Ask once to confirm; still prefer not to |
| User wants a DACore change | **Do not propose** editing DACore. Implement in the current module. **If they themselves** ask to edit `app/modules/DACore/` **and** confirm they accept the update wipe: then edit DACore for that request. Otherwise **strict ban**. |

---

## 9. Minimum reading map by task

| Task | Theory | Example (open one) |
|------|--------|--------------------|
| **Anything (always)** | **18** error handling / return values — incl. **§9 catch bus** (`dotapp.catch`) | — |
| Plan / Cursor credits | **00 §2b** — ASK before expensive subagents; inherit parent; Composer 2.5 = file hunt only | — |
| Plan / PHP version | **00 §2i** — ASK 7.4+ (default) vs a higher PHP; no answer → 7.4+ | — |
| **After every code chunk** | **00 §2c** finish gate — CRC once, enc IDs, bound SQL, inputs, middleware / AuthTest conflicts | [17](17-CHECKLISTS.md) Finish gate |
| Stay-on-page save / errors | **00 §2d** visible outcome — DACore toast (search first); public = mark the wrong field | [EX-09](examples/EX-09-validation-and-errors.md), [EX-06](examples/EX-06-dotapp-js-boot.md) |
| Buttons / card footers / chrome spacing | **00 §2f** layout + UX/UI — padding vs parent (esp. bottom), alignment | [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c, [33](33-DACORE-PAGES-AND-UI.md) |
| New module | 00, 02, 03 | [EX-03](examples/EX-03-module-scaffold.md) |
| Route / middleware | 03, 04, 32 | EX-03 / EX-D01 — prefix `Gate@login` 403 + handlers inside `Auth::isLogged()` |
| Template / CSS / JS page | 05 (incl. §8 product copy), **09 §3** public mobile nav | [EX-05](examples/EX-05-renderer-page.md), [EX-06](examples/EX-06-dotapp-js-boot.md) |
| Public website header / nav | **09 §3** “Public website mobile navigation” — drawer overlay, lock page scroll | [EX-05](examples/EX-05-renderer-page.md) |
| Stay-on-page save / toggle (live DOM) | **09 §3** (block-while-in-flight, desktop+mobile), **08** | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Paginated list (users, logs, items) | **40** list pager (HTML/classes/`live(el, e)`/COUNT) — **MUST** ship, **MUST** be AJAX | **[EX-D08](examples/EX-D08-list-pager.md)**, [EX-06](examples/EX-06-dotapp-js-boot.md) |
| **Module hooks / connect modules** | **41** — `module.{mod}.{name}.hook` + `.hooks` (not every save); DACore-bound **MUST** read `app/modules/DACore/.hooks` first | **[EX-16](examples/EX-16-module-hooks.md)** |
| **Replace a judged output (Extender)** | **12 §10** / **00 §2h** — owner `exists()`/`call()`; `extend()` in `Listeners::register()`; target listener routes; own Module routes or `[]`; controller string preferred; **MUST NOT** patch DACore | **[EX-17](examples/EX-17-extender.md)** |
| List search (articles, catalog, …) | **09 §3** “Interactive AJAX search” — **ASK** in the plan; lookup lists **MUST** unless declined | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| List UX (filters, sort, empty, bulk, …) | **09 §3** “List UX” — **ASK** / **MUST** table | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Delete (confirm dialog) | **09 §3** “Confirm before delete” | **[EX-06](examples/EX-06-dotapp-js-boot.md)** |
| Custom `$dotapp` library / jQuery port | **09 §4** (esp. §4.C) | **[EX-15](examples/EX-15-dotapp-js-library.md)** |
| Database query | 06, 18 | [EX-04](examples/EX-04-database-crud.md) — `raw()`: every `?` is a placeholder, including comments |
| Tables / migrations | 07 (rename `installed_*` → `install.php` after a new version) | [EX-13](examples/EX-13-schema-migrations.md) |
| **DACore zip / “create module and pack it”** | **00 §2e** — zip **MUST** have `dainstall.php` (renamed from `install.php`) + `init/`; **MUST NOT** ship `install.php` | [EX-D04](examples/EX-D04-dacore-installer.md), [35](35-DACORE-INSTALL.md) |
| **Secure form (HTML fields + submit)** | **08, 09** | **[EX-01](examples/EX-01-secure-form-complete.md)**, [EX-02](examples/EX-02-secure-form-edit-api.md) |
| AJAX without a form (`load` only) | **08, 09** | [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 |
| Encrypt IDs / unique `$key2` | **11 §8, 05, 08** | [EX-02](examples/EX-02-secure-form-edit-api.md), [EX-14](examples/EX-14-auth-and-2fa.md) |
| Validation / error responses | 19 | [EX-09](examples/EX-09-validation-and-errors.md) — **`data(true)`** = original; `data()` = protected |
| Config / keys | 10 | [EX-08](examples/EX-08-config-secrets.md) |
| Bridge click | 09 | [EX-07](examples/EX-07-bridge.md) |
| Auth / 2FA / permissions | **11** (incl. §11 privilege / secrets / SQL owner / bot **warn**), **09** (`twoFactor`), **19** (`data(true)`), **32** | [EX-14](examples/EX-14-auth-and-2fa.md) |
| **Shop / custom register-login / user list** | **[42](42-DACORE-USER-ORIGIN.md)** — stamp origin, isolate by `origin_id`, ASK before a DACore-replacement admin, non-escalatable code | [11](11-AUTH-AND-CRYPTO.md), [24](24-ATTACK-VECTORS.md) |
| **Any attack surface (input, auth, output, upload, public endpoint)** | **24** attack vectors — open the matching section, then §11 threat pass | [EX-01](examples/EX-01-secure-form-complete.md), [EX-14](examples/EX-14-auth-and-2fa.md) |
| **New table / migration / any loop or query you care about** | **25** performance — §1 memory, §2 I/O, **§3 indexes**, §4 column types, §5 big lists, §6 frontend | [EX-13](examples/EX-13-schema-migrations.md), [EX-04](examples/EX-04-database-crud.md) |
| **Every file you write (CRCchecking + PHPDoc purpose + Why/About/Section)** | **25 §7** | [EX-01](examples/EX-01-secure-form-complete.md) |
| Cache / logs / sessions | 20 | [EX-10](examples/EX-10-cache-logger-session.md) |
| Email / SMS / QR | 21 | [EX-11](examples/EX-11-email-sms-qr.md) |
| AI / search / MCP | 22 | [EX-12](examples/EX-12-ai-search-mcp.md) |
| Services index | 12 (`dotapp.catchall` = core debug funnel); **41** = `module.{mod}.{name}.hook` + `.hooks` | [EX-16](examples/EX-16-module-hooks.md) |
| Tests | 13 | — |
| Anything uncertain | 14, 15, **23**, **36**, then `app/parts/` | examples/README.md |
| **Debug / “it doesn’t work”** | **23** (grep middleware + `crcCheck` count first; §1b catch trail; **§1c `dotapp.catchall` event tracer**; DACore = §7) | [EX-01](examples/EX-01-secure-form-complete.md), [EX-10](examples/EX-10-cache-logger-session.md) |
| **Debug tool / see all events** | **01** `dotapp.catchall` (core fires every `trigger()`); **18** §9 `dotapp.catch` (failures); **41** `module.{mod}.{name}.hook` + `.hooks` — listener in **your** module | [EX-10](examples/EX-10-cache-logger-session.md), [EX-16](examples/EX-16-module-hooks.md) |

### DACore admin layer

| Task | Theory | Example |
|------|--------|---------|
| **Any DACore work (start here)** | **30** | — |
| New admin module | 30, **31 (ASK menu layout)**, 32, 35 | [EX-D01](examples/EX-D01-dacore-module-skeleton.md) |
| Menu items | **31** (grouping, shared vs module-own) | EX-D01 |
| Permissions / route guards | 32 | EX-D01 |
| Operator 2FA / dangerous admin actions | **32 §6**, **09** (`twoFactor`) | [EX-14](examples/EX-14-auth-and-2fa.md) |
| Admin page, dotgrid, tables | 33 (incl. §3 AJAX pager; **search DACore first**) | [EX-D02](examples/EX-D02-dacore-admin-page.md) |
| Admin edit/detail sidebar | **31** Active sidebar — `withMenu` 7th `$currentFile` | [EX-D02](examples/EX-D02-dacore-admin-page.md) |
| New admin library / widget / `$dotapp().fn` | **33** “Search DACore first”, **09 §4** | [EX-15](examples/EX-15-dotapp-js-library.md) |
| AI tools | 34 (incl. §5 `ui_events`) | [EX-D03](examples/EX-D03-dacore-ai-tool.md) |
| Installer wiring | **35** (incl. **§3c extra1…extra5** discovery flags) + **00 §2e** | [EX-D04](examples/EX-D04-dacore-installer.md) |
| **User origin / custom login / shop accounts** | **[42](42-DACORE-USER-ORIGIN.md)** — global identity/session trust model; checked register/create/stamp/read; login+2FA+every gate; joined/bound origin lists; DACore form allow-list caveat | [11](11-AUTH-AND-CRYPTO.md), [32](32-DACORE-RIGHTS.md) |
| Inbox notifications | **37** | [EX-D05](examples/EX-D05-dacore-notifications.md) |
| DACore quirks | 36 | — |
