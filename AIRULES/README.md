# DotApp AIRULES — AI Agent Rulebook (Part 1: Framework)

A complete set of rules and guides for AI agents (Cursor IDE, GROK 4.6, and weaker models) so they can work **correctly** with the DotApp PHP framework — without Laravel/Blade/Eloquent/jQuery hallucinations.

## What this is

`AIRULES/` ships with the framework. After install it sits in the project root (next to `index.php`). Point the agent at it.

- **Language:** English
- **Scope:** the framework itself — **independent of DACore**
- **DACore admin UI:** use the `AIRULES-DACORE/` variant instead (copy it as `AIRULES/`)

## Hard rules

1. **AIRULES is the single source of truth** for AI. The old `.cursorrules`, `database_guide.md`, and module `*_AI_guide.md` files are gone.
2. **You may edit only:**
   - `app/config.php`
   - files inside **your own** module at `app/modules/<YourModule>/`
3. **Never touch** the core (`app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, …). The kernel is **finished**. **MUST NOT** edit it even if the user asks — implement in the module. Read `app/parts/` **read-only** when an API is missing from AIRULES.
4. Create controllers, models, and middleware with **`dotapper.php`**, not by hand.
5. Secure forms = **`<fo-rm>`** + `{{ formName(...) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop) = `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. After save/toggle on the same page **MUST** patch the DOM from JSON + a short toast — no `location.reload()`. **MUST** ship **your own** form/list preloaders (Notiflix is DACore-only). Deletes **MUST** open a graphical confirm first — never `alert()` / `window.confirm()`. UX **MUST** be excellent on desktop **and** mobile. User-visible strings **MUST** read as shipped product copy — never prompt-echo.
6. **MUST:** Module tables are `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never `items` or `dotapp_*` for module data.
7. **MUST paginate accumulating lists** (users, logs, items, orders, …) in the **first** version: `paginate()` + **interactive AJAX** pager. Lookup lists **MUST** ship **AJAX search** unless declined; **ASK** on other lists. **Cheap I/O (MUST):** `exists()` / `COUNT(*)` / `limit(1)` / only used columns / one `join` — not `all()` then filter, not N+1. Canonical: [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
8. **MUST** store app session state with **`DSM::use('Shop')`**. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: [20](20-CACHE-LOGGER-SESSION.md), [EX-10](examples/EX-10-cache-logger-session.md).
9. **MUST** re-check every persist in **PHP**. Frontend modal/overlay is UX only — skipping it **MUST** still fail on the server. Canonical: [08](08-FORMS-AND-SECURITY.md).
10. **MUST** upload files with **`$dotapp().uploadFile`**. **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP **MUST** reject `.php` / executables (extension + `finfo` MIME + headers). Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md).
11. **Public website (MUST):** mobile nav is an overlay drawer from the left or right; the page behind **MUST NOT** scroll while it is open (including iOS); the drawer list **MUST** scroll; contacts + compact search live in the drawer unless large search is its own mobile section. Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
12. **Cursor credits (MUST):** when **planning** programming, **ASK** whether more expensive models may be used. Subagents **MUST inherit** the chat model. **MUST NOT** silently spawn Opus / GPT-5 / thinking / xhigh / cloud / best-of-N. Composer 2.5 is **only** for hunting a pile of files — **not** the programmer. Canonical: [00](00-AGENT-CONTRACT.md) §2b.
13. **Finish gate (LAW):** after **every** code chunk **and** before claiming done, **MUST** grep this module — double `crcCheck`, plain IDs, unbound SQL, wrong `data()`, middleware vs action, `Events::trigger` vs `.hooks`. **MUST NOT** skip. Canonical: [00](00-AGENT-CONTRACT.md) §2c, [17](17-CHECKLISTS.md), [41](41-MODULE-HOOKS.md).
14. **Visible outcome (LAW):** the user **MUST** see save success **and** failure. **Preferred** on public FE+BE: mark the wrong input (red + message on the field). **You MUST build** your own toast/status (Notiflix is DACore-only). Canonical: [00](00-AGENT-CONTRACT.md) §2d.
15. **Attack vectors (LAW):** the known vectors in [24](24-ATTACK-VECTORS.md) **MUST NOT** be shippable — injection (SQL, XSS, command, template, deserialization), headers / redirect / SSRF, mass assignment, CSRF / brute force / enumeration, IDOR and escalation, files and paths, missing rate limit, leaks, weak crypto, prompt injection. Open the section for the surface you touch, then run the **threat pass** ([24](24-ATTACK-VECTORS.md) §11) on the diff. A vector not listed is still forbidden — apply the nearest rule and **say it in chat**.
16. **Catch bus (LAW):** every `catch` **and** every `execute()` `$err` **MUST** report through one module helper: `Events::trigger('dotapp.catch', $payload)` then `dotapp.catch.error` (aborted) or `dotapp.catch.info` (recovered), with the fixed payload (`severity, module, source, operation, message, exception, code, file, line, time`, plus `context` ids/counts and `user_id`). No secrets, tokens or request bodies in it. That is what makes a debugger possible later — reporting is **in addition to** the user-visible outcome, never instead of it. Canonical: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.
17. **Performance + readability (LAW):** smallest possible I/O, memory bounded by pages (never “load all and filter”), **indexes designed for the queries you wrote**, sane column types, and code a human can read: controller/middleware PHPDoc **MUST** start with **`CRCchecking —`** (where CRC runs — prefix XOR action), then a **purpose sentence** then tags (not `@return array<string, mixed>` alone), labeled **`// Why:`** / **`// About:`** / **`// Section:`**. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7.
18. **Module hooks (LAW):** useful side-effects (SMS/mail sent, payment, lockout) **MUST** `Events::trigger('module.{mod}.{name}.hook', …)` with the comment block and a `.hooks` row. **MUST NOT** fire on every save. Listen in **your** module — `Listeners::initializeRoutes()` may cover the producer URL without waking the whole module. Pre-action stop is **`triggerWithVeto()`** + `Veto`, not `return false`. Canonical: [41](41-MODULE-HOOKS.md).
19. **Extender (judge — not every method):** owner `Extender::exists()` + immediate `return Extender::call(...)`; extender registers in `Listeners::register()` before Module initialization. Target URLs belong in the listener map; the Module map stays on its own URLs or `[]`. Prefer a controller string. **MUST NOT** use `.loaded` for initialize-time points, Events, `$request`/secrets, or patch the owner. Canonical: [12](12-SERVICES.md) §10, [00](00-AGENT-CONTRACT.md) §2h.

## What's New (2026-08-22)

### Extender — opt-in method replacement

New core class: **`Dotsystems\App\Parts\Extender`**. A module can **replace** another module’s method for the current request — one handler owns the result. This is **not** Events, hooks, or `triggerWithVeto()`.

- **Judge first:** offer Extender on highly replaceable **outputs** (page/block HTML, cart, export) — **not** on every method.
- When the owner opts in: `exists()` then immediately `return call(...)`. No original / next.
- Register `extend()` in **`Listeners::register()`**. Put target URLs in `Listeners::initializeRoutes()`; keep the Module map on its own URLs or `[]`.
- Prefer a controller string handler. Direct listener registration is earlier than every Module `initialize()`; `.loaded` is too late for initialize-time points.
- One replacement per class+method. Recursion throws. Pass only explicit safe arguments.

Canonical: [12](12-SERVICES.md) §10. Sample: [EX-17](examples/EX-17-extender.md). Framework README: project-root `README.md`.

## Kernel APIs (2026-08-22)

The kernel is **finished**. Agents **MUST NOT** edit `app/parts/`, `DotApp.php`, `dotapper.php`, or `index.php` — even if the user asks.

- Module loader **v2:** `php dotapper.php --optimize-modules` writes `$modules` + `$listeners` (`$modulesAutoLoaderVersion = 2`). Old `$modules`-only files still work.
- `Listeners::initializeRoutes()` may differ from `Module::initializeRoutes()`; omit/`null` inherits the module map.
- Matching listeners register **before** matching modules initialize.
- **`Events::triggerWithVeto()`** + `Dotsystems\App\Parts\Veto` — ordinary `trigger()` still ignores returns.
- **`Extender`:** judge first; owner `exists()` / `call()`, extender `extend()` in `Listeners::register()` on target listener routes. Own Module routes or `[]`; controller string preferred. Not Events. Sample [EX-17](examples/EX-17-extender.md).

Canonical: [01](01-ARCHITECTURE.md), [03](03-MODULES-AND-ROUTING.md), [12](12-SERVICES.md) §2 / §10, [41](41-MODULE-HOOKS.md), [EX-16](examples/EX-16-module-hooks.md), [EX-17](examples/EX-17-extender.md). Framework README What's New: project-root `README.md`.

## Quick install

See [INSTALL.md](INSTALL.md).

In short:

1. Install DotApp (`git clone` or `php dotapper.php --install`) — `AIRULES/` ships with the framework.
2. Copy the Cursor rules as described in [INSTALL.md](INSTALL.md).
3. Tell the agent: *“Read AIRULES/00-AGENT-CONTRACT.md first. Follow AIRULES for all DotApp work.”*

## Code samples (save context)

Theory lives in `00`–`25`. **Ready copy-paste patterns** are in [examples/](examples/) — the agent should open **one** EX file per task:

- [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md) — **preferred** security: `fo-rm` + `formName` + `dotapp.js`
- More: module, DB, renderer, JS boot, bridge, secrets — see [examples/README.md](examples/README.md)

**Security priority:** for user-facing forms always use `fo-rm` + `formName` + required `/assets/dotapp/dotapp.js` (injects random keys). This is stronger than plain CSRF.

## Document map

| File | Contents |
|------|----------|
| [00-AGENT-CONTRACT.md](00-AGENT-CONTRACT.md) | Hard laws — edit boundaries, workflow, **§2c finish gate** |
| [examples/](examples/) | Short code samples by situation |
| [01-ARCHITECTURE.md](01-ARCHITECTURE.md) | Lifecycle, module structure — incl. core `dotapp.catchall` debug funnel |
| [02-DOTAPPER-CLI.md](02-DOTAPPER-CLI.md) | Full CLI reference |
| [03-MODULES-AND-ROUTING.md](03-MODULES-AND-ROUTING.md) | Modules, routes, middleware — prefix `Gate@login` 403 + handlers inside `Auth::isLogged()`; English why-comments |
| [04-CONTROLLERS-AND-RESPONSES.md](04-CONTROLLERS-AND-RESPONSES.md) | Controllers, response |
| [05-VIEWS-TEMPLATES-ASSETS.md](05-VIEWS-TEMPLATES-ASSETS.md) | Template syntax, assets |
| [06-DATABASE.md](06-DATABASE.md) | DB / QueryBuilder — `$qb->raw()`: every `?` is a placeholder, including comments |
| [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md) | Migrations, Installation.php |
| [08-FORMS-AND-SECURITY.md](08-FORMS-AND-SECURITY.md) | fo-rm, CRC, CSRF |
| [09-DOTAPP-JS-AND-BRIDGE.md](09-DOTAPP-JS-AND-BRIDGE.md) | Frontend + Bridge + **custom `$dotapp().fn` libraries** (jQuery ports = §4.C) |
| [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md) | Config, keys, fallbacks |
| [11-AUTH-AND-CRYPTO.md](11-AUTH-AND-CRYPTO.md) | Auth, 2FA, Crypto |
| [12-SERVICES.md](12-SERVICES.md) | Cache, Logger, Email, Events, **`triggerWithVeto` / `Veto`**, **`Extender`**, Module API |
| [13-TESTING.md](13-TESTING.md) | Tester + dotapper --test |
| [14-ANTIPATTERNS.md](14-ANTIPATTERNS.md) | Wrong vs right (Laravel/…) |
| [15-KNOWN-ISSUES.md](15-KNOWN-ISSUES.md) | Quirks + leftover-doc corrections |
| [16-RECIPES.md](16-RECIPES.md) | End-to-end recipes |
| [17-CHECKLISTS.md](17-CHECKLISTS.md) | Pre-flight / **finish gate** (00 §2c) |
| **[18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md)** | **Return values + error handling (mandatory)** — incl. §9 `dotapp.catch` bus |
| [19-VALIDATION-AND-INPUT.md](19-VALIDATION-AND-INPUT.md) | Validator, Input, Request, Response, HttpHelper, Limiter |
| [20-CACHE-LOGGER-SESSION.md](20-CACHE-LOGGER-SESSION.md) | Cache, Logger, **DSM** (never `$_SESSION`) |
| [21-EMAIL-SMS-QR.md](21-EMAIL-SMS-QR.md) | Email/IMAP/POP3, SMS, QR |
| [22-AI-SEARCH-MCP.md](22-AI-SEARCH-MCP.md) | AI drivers, FastSearch, MCP |
| [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) | Hunt order when the user asks **why** it fails — grep middleware + `crcCheck` first; §1c = `dotapp.catchall` event tracer |
| **[24-ATTACK-VECTORS.md](24-ATTACK-VECTORS.md)** | **Known attack vectors as law** — injection, identity, access control, headers, files, abuse, leaks, crypto, AI + the §11 threat pass |
| **[25-PERFORMANCE-AND-CODE-QUALITY.md](25-PERFORMANCE-AND-CODE-QUALITY.md)** | **Performance + schema + readable code as law** — memory/algorithms, I/O budget, **index & column design**, big lists, frontend cost, **PHPDoc purpose sentence** + Why/About/Section, §8 perf pass |
| **[41-MODULE-HOOKS.md](41-MODULE-HOOKS.md)** | **Business hooks as law** — `module.{mod}.{name}.hook` + `.hooks` (not every save); **`triggerWithVeto` / `Veto`** |
| [cursor/](cursor/) | Cursor IDE: AGENTS.md, `.mdc` rules |

## Critical: return values

The framework uses **four different failure styles**. That is why [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md) is mandatory reading:

- `execute()` **throws** if you omit the error callback
- `first()` is unsafe on an empty result (RAW warning, ORM fatal)
- `Crypto::decrypt()` returns **`false`**, `Cache::load()` returns **`null`**
- `Email::send()` returns an **array of error strings**, not `false`
- a missing template renders **`""`** with no exception
- `Auth::login()` returns **`false`** on bad input
- `$request->data()` is **protected**; passwords/HTML **MUST** use `$request->data(true)` ([19](19-VALIDATION-AND-INPUT.md))

## What AIRULES is not

- It does not depend on DACore — you can write ordinary modules on the bare framework.
- It is not a copy of the old `.cursorrules` — that file taught **incorrect** template syntax.

## Version

- **Part:** 1 (Framework)
- **Target models:** GROK 4.6 and weaker. Subagents **MUST inherit** that chat model unless the user said yes in the plan ([00](00-AGENT-CONTRACT.md) §2b).
- **IDE:** Cursor (primary), portable to other agents via markdown
- **Verified against:** DotApp clean install source (`app/parts/*`, `dotapper.php`)
