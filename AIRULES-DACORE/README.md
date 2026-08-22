# DotApp AIRULES-DACORE — AI Agent Rulebook (Framework + DACore)

A complete set of rules and guides for AI agents (Cursor IDE, GROK 4.6, and weaker models) so they can work **correctly** with the DotApp PHP framework **and the DACore module** — without Laravel/Blade/Eloquent/jQuery hallucinations.

## What this is

`AIRULES-DACORE/` is the DACore overlay. Use it in a project where **the DACore admin module is installed**.

- **Language:** English
- **Contents:** docs `00`–`25` = framework · `30`–`40` = DACore layer · **`41` = module hooks** (every module)

### Which variant to use

| Project | Folder |
|---------|--------|
| Bare framework, no admin UI | `AIRULES/` |
| DACore admin module installed | **`AIRULES-DACORE/`** (this one) |

Never copy both into the same project — the agent would have two conflicting sources. Copy this folder into the project **as `AIRULES/`**.

## DACore layer (`30`–`40`)

| File | Contents |
|------|----------|
| [30-DACORE-OVERVIEW.md](30-DACORE-OVERVIEW.md) | What DACore is, tables, config keys, call map |
| [31-DACORE-MENU.md](31-DACORE-MENU.md) | Menu item registration |
| [32-DACORE-RIGHTS.md](32-DACORE-RIGHTS.md) | Permissions + `{prefixUrl}/{Module}/…` + `Gate@login` 403 |
| [33-DACORE-PAGES-AND-UI.md](33-DACORE-PAGES-AND-UI.md) | `Page@withMenu`, dotgrid, UI contract |
| [34-DACORE-AI-TOOLS.md](34-DACORE-AI-TOOLS.md) | AI tools for the DACore chat |
| [35-DACORE-INSTALL.md](35-DACORE-INSTALL.md) | Develop with `install.php`; zip **MUST** rename to `dainstall.php` + `init/` (installer rejects `install.php`) |
| [36-DACORE-KNOWN-ISSUES.md](36-DACORE-KNOWN-ISSUES.md) | DACore bugs and traps |
| [37-DACORE-NOTIFICATIONS.md](37-DACORE-NOTIFICATIONS.md) | Inbox `Notifications@push` |
| [38-DACORE-EMAIL.md](38-DACORE-EMAIL.md) | Outgoing mail API — **open only when the module sends email** |
| [39-DACORE-SMS.md](39-DACORE-SMS.md) | SMS driver registry — **open only when the module sends SMS** |
| [40-DACORE-LIST-PAGER.md](40-DACORE-LIST-PAGER.md) | **List pager law** — HTML classes, `live(el, e)`, encrypted `data-page`, COUNT |

Samples: [examples/EX-D01](examples/EX-D01-dacore-module-skeleton.md) through [EX-D08](examples/EX-D08-list-pager.md). Module-to-module hooks (every module, not DACore-only): [41](41-MODULE-HOOKS.md), [EX-16](examples/EX-16-module-hooks.md).

### Most important DACore rules

1. **Never edit, patch, or add files in `app/modules/DACore/` by default** (including DACore assets). **MUST NOT propose** a DACore edit. Put all new work in **the current module**. Touch DACore **only** if the user **themselves** asks **and** confirms the next update wipes it ([00](00-AGENT-CONTRACT.md) §1). Otherwise **strict ban**. Use `DotApp::call("DACore:…")`.
2. **Never write directly** to `dacore_menu`, `dacore_ai_tools`, `dacore_installations`, `dacore_modules`, `dacore_plugin_logs`, `dacore_settings`, `dacore_notifications`, `dacore_notifications_inbox`, `dacore_email_senders`, `dacore_email_templates`, `dacore_sms_senders`, or `users_rights*`. **Read** `extra1`…`extra5` via `DACore:Plugins@listByExtra!` when a host picks packs ([35](35-DACORE-INSTALL.md) §3c).
3. **`#DACore:AuthTest@check!` ignores the rights** you pass it — create your own `Middleware/Rights.php`.
4. Register menu, rights, and AI tools **in your `Installation.php`**, not on every request. Push inbox notifications with **`DACore:Notifications@push` on the event** — not from the installer, not every request ([37](37-DACORE-NOTIFICATIONS.md)). **Outgoing mail:** open [38](38-DACORE-EMAIL.md) (do not invent SMTP). **Outgoing SMS:** open [39](39-DACORE-SMS.md) (do not invent a gateway). **While coding:** **`install.php`** + live root init files; after a new version rename `installed_*` → `install.php`. A **DACore** install zip **MUST** contain **`dainstall.php`** (renamed from `install.php`) + **`init/`** — a zip that still has `install.php` is **rejected** and Installation **never runs** ([00](00-AGENT-CONTRACT.md) §2e, [35](35-DACORE-INSTALL.md) §4–§5). A non-DACore module: no zip — `install.php` and copy.
5. Render pages with **`DACore:Page@withMenu!`** — never build your own HTML shell. **ASK** shared vs module-own menu before a new DACore module. Many items: group with `type => 2`, or header + one entry and pass `$menuId`. Edit/detail: 7th `$currentFile` = registered list URL when the path is not under that leaf ([31](31-DACORE-MENU.md)).
6. An AI tool with empty `rights` is **invisible to everyone**; wildcards do not work here.
7. **MUST search DACore first** before a new library or widget (grep `app/modules/DACore/` read-only + your module). The base already has many subpages and libraries — reuse them. **MUST** add your own CSS/JS in the **current** module only when that search finds no equivalent (charts, ported controls). Keep the shell and admin colors. Prefix classes `{lowercase_modulename}_*`. Never edit DACore to “add” UI (unless the informed exception in [00](00-AGENT-CONTRACT.md) §1).
8. DACore admin runs on **`$dotapp`**. jQuery may sit beside it for UI widgets, but **every request** uses `$dotapp` (`form` / `load` / bridge) — never `$.ajax`. Porting jQuery **is** writing a new `$dotapp().fn` library: **ask**, then rewrite (do not wrap `$.fn`). Playbook: [09](09-DOTAPP-JS-AND-BRIDGE.md) §4.C and [EX-15](examples/EX-15-dotapp-js-library.md).

## Hard rules

1. **AIRULES is the single source of truth** for AI. The old `.cursorrules`, `database_guide.md`, and module `*_AI_guide.md` files are gone.
2. **You may edit only:**
   - `app/config.php`
   - files inside **the module you are programming** at `app/modules/<YourModule>/` (including **its** assets)
3. **Never touch** the core (`app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, …). **DACore:** default same ban (`app/modules/DACore/` — files and assets). **MUST NOT propose** a DACore edit. **Exception:** user themselves asks and confirms the update wipe ([00](00-AGENT-CONTRACT.md) §1).
4. Create controllers, models, and middleware with **`dotapper.php`**, not by hand.
5. Secure forms = **`<fo-rm>`** + `{{ formName(...) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop) = `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. After save/toggle on the same page **MUST** patch the DOM from JSON + a short toast — no `location.reload()`. **MUST** cover the form/list until the request ends. **DACore admin:** Notiflix (preferred) **or** your module preloaders. **Public website:** you **MUST** build preloaders yourself (Notiflix is DACore-only). Deletes **MUST** open a graphical confirm first (`Notiflix.Confirm` on admin) — never `alert()` / `window.confirm()`. UX **MUST** be excellent on desktop **and** mobile. User-visible strings **MUST** read as shipped product copy — never prompt-echo.
6. **MUST:** Module tables are `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never `items`, `dotapp_*`, or `dacore_*` for your module data.
7. **MUST paginate accumulating lists** (users, logs, items, orders, …) in the **first** version: [40](40-DACORE-LIST-PAGER.md) pager (`live(el, e)`, encrypted `data-page`, COUNT + LIMIT). No pager, or a pager that reloads the admin shell / writes `?page=`, is incomplete. “Few rows now” is not a skip. Lookup lists **MUST** ship **AJAX search** unless declined; **ASK** on other lists. **Cheap I/O (MUST):** `exists()` / `COUNT(*)` / `limit(1)` / only used columns / one `join` — not `all()` then filter, not N+1. Canonical: [40](40-DACORE-LIST-PAGER.md), [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
8. **MUST** store app session state with **`DSM::use('Shop')`**. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: [20](20-CACHE-LOGGER-SESSION.md), [EX-10](examples/EX-10-cache-logger-session.md).
9. **MUST** re-check every persist in **PHP**. Frontend modal/overlay is UX only — skipping it **MUST** still fail on the server. Canonical: [08](08-FORMS-AND-SECURITY.md).
10. **MUST** upload files with **`$dotapp().uploadFile`**. **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP **MUST** reject `.php` / executables (extension + `finfo` MIME + headers). Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md).
11. **Public website (MUST):** mobile nav is an overlay drawer from the left or right; the page behind **MUST NOT** scroll while it is open (including iOS); the drawer list **MUST** scroll; contacts + compact search live in the drawer unless large search is its own mobile section. **Not** the DACore admin shell. Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
12. **Cursor credits (MUST):** when **planning** programming, **ASK** whether more expensive models may be used. Subagents **MUST inherit** the chat model. **MUST NOT** silently spawn Opus / GPT-5 / thinking / xhigh / cloud / best-of-N. Composer 2.5 is **only** for hunting a pile of files — **not** the programmer. Canonical: [00](00-AGENT-CONTRACT.md) §2b.
13. **Finish gate (LAW):** after **every** code chunk **and** before claiming done, **MUST** grep this module — double `crcCheck`, plain IDs, unbound SQL, wrong `data()`, middleware / AuthTest conflicts, `Events::trigger` vs `.hooks`. **MUST NOT** skip. Canonical: [00](00-AGENT-CONTRACT.md) §2c, [17](17-CHECKLISTS.md), [41](41-MODULE-HOOKS.md).
14. **Visible outcome (LAW):** the user **MUST** see save success **and** failure. **DACore admin:** grep DACore first, then **toast** (Notiflix / `$dotapp().toast()`). **Public:** mark the wrong input (red + message on the field). Canonical: [00](00-AGENT-CONTRACT.md) §2d.
15. **Attack vectors (LAW):** the known vectors in [24](24-ATTACK-VECTORS.md) **MUST NOT** be shippable — injection (SQL, XSS, command, template, deserialization), headers / redirect / SSRF, mass assignment, CSRF / brute force / enumeration, IDOR and escalation, files and paths, missing rate limit, leaks, weak crypto, prompt injection. Open the section for the surface you touch, then run the **threat pass** ([24](24-ATTACK-VECTORS.md) §11) on the diff. Fix it in **your** module — never by patching DACore. A vector not listed is still forbidden — apply the nearest rule and **say it in chat**.
16. **Catch bus (LAW):** every `catch` **and** every `execute()` `$err` **MUST** report through one helper in **your** module: `Events::trigger('dotapp.catch', $payload)` then `dotapp.catch.error` (aborted) or `dotapp.catch.info` (recovered), with the fixed payload (`severity, module, source, operation, message, exception, code, file, line, time`, plus `context` ids/counts and `user_id`). No secrets, tokens, rights blobs or request bodies in it. That is what makes a debugger possible later — reporting is **in addition to** the toast, never instead of it. Nothing for this goes under `app/modules/DACore/`. Canonical: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.
17. **Performance + readability (LAW):** smallest possible I/O, memory bounded by pages (never “load all and filter”), **indexes designed for the queries you wrote** (FK + every `WHERE`/`JOIN`/`ORDER BY` column, composite order equality → range → sort, leftmost prefix, no duplicate prefixes), sane column types, DACore assets reused instead of a second library, and code a human can read: controller/middleware PHPDoc **MUST** start with **`CRCchecking —`** (where CRC runs — prefix XOR action), then a **purpose sentence** then tags (not `@return array<string, mixed>` alone), labeled **`// Why:`** / **`// About:`** / **`// Section:`**. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7.
18. **Layout / UX-UI (LAW):** every new button **MUST** be checked for padding vs the parent (especially **bottom**) and placed deliberately (center / same rhythm as siblings). A Save glued to the card edge is a **bug**. General UX/UI principles **MUST** be followed **at all costs**. Canonical: [00](00-AGENT-CONTRACT.md) §2f, [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c.
19. **Module hooks (LAW):** fire **`module.{lowercase_modulename}.{hook_name}.hook`** only when another module could log, show history, or sync (SMS/mail sent, payment, lockout) — **MUST NOT** on every save. Document in **`.hooks`**. Above `trigger()`: `Hook:` / `Why:` / `About:` / `Params:` / `Use:`. Connect by listening, never by patching the owner. A DACore-bound module **MUST** read **`app/modules/DACore/.hooks` first**. Canonical: [41](41-MODULE-HOOKS.md) §6, [00](00-AGENT-CONTRACT.md) §2g.

## Quick install

See [INSTALL.md](INSTALL.md).

In short:

1. Install DotApp (`git clone` or `php dotapper.php --install`).
2. Copy `AIRULES-DACORE/` into the project **as `AIRULES/`** (replacing the framework-only variant).
3. Copy the Cursor rules as described in [INSTALL.md](INSTALL.md).
4. Tell the agent: *“Read AIRULES/00-AGENT-CONTRACT.md first. Follow AIRULES for all DotApp work.”*

## Code samples (save context)

Theory lives in `00`–`25` (framework), `30`–`40` (DACore), and **[41](41-MODULE-HOOKS.md)** (module hooks). **Ready copy-paste patterns** are in [examples/](examples/) — the agent should open **one** EX file per task:

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
| [12-SERVICES.md](12-SERVICES.md) | Cache, Logger, Email, … |
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
| [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) | Hunt order when the user asks **why** it fails — grep middleware + `crcCheck` first; §1c = `dotapp.catchall` event tracer; DACore = §7 |
| **[24-ATTACK-VECTORS.md](24-ATTACK-VECTORS.md)** | **Known attack vectors as law** — injection, identity, access control, headers, files, abuse, leaks, crypto, AI + the §11 threat pass |
| **[25-PERFORMANCE-AND-CODE-QUALITY.md](25-PERFORMANCE-AND-CODE-QUALITY.md)** | **Performance + schema + readable code as law** — memory/algorithms, I/O budget, **index & column design**, big lists, frontend cost (reuse DACore assets), **PHPDoc purpose sentence** + Why/About/Section, §8 perf pass |
| **[41-MODULE-HOOKS.md](41-MODULE-HOOKS.md)** | **Module hooks (LAW)** — `module.{mod}.{name}.hook` when useful (not every save); `.hooks` file; listen, do not patch; DACore-bound modules **MUST** read `app/modules/DACore/.hooks` first |
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

- It is not a copy of the old `.cursorrules` — that file taught **incorrect** template syntax.

## Version

- **Part:** 1 (Framework) + 2 (DACore)
- **Target models:** GROK 4.6 and weaker. Subagents **MUST inherit** that chat model unless the user said yes in the plan ([00](00-AGENT-CONTRACT.md) §2b).
- **IDE:** Cursor (primary), portable to other agents via markdown
- **Verified against:** DotApp clean install source (`app/parts/*`, `dotapper.php`) and the DACore module
