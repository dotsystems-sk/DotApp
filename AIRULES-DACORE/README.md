# DotApp AIRULES-DACORE — AI Agent Rulebook (Framework + DACore)

A complete set of rules and guides for AI agents (Cursor IDE, GROK 4.6, and weaker models) so they can work **correctly** with the DotApp PHP framework **and the DACore module** — without Laravel/Blade/Eloquent/jQuery hallucinations.

## What this is

`AIRULES-DACORE/` is the DACore overlay. Use it in a project where **the DACore admin module is installed**.

- **Language:** English
- **Contents:** docs `00`–`25` = framework · `30`–`40` = DACore layer · **`41` = module hooks** · **`42` = user origin** · **`45` = module planning depth** · **`46` = reserved extra1–extra5 / peer contracts**

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
| [35-DACORE-INSTALL.md](35-DACORE-INSTALL.md) | Develop with `install.php`; zip **MUST** use [EX-D09](examples/EX-D09-dacore-pack-zip.md) (`dainstall.php` + `init/`); `installer()` keys **MUST** be quoted `'1.0.0' =>` text; **§3c** extras → [46](46-DACORE-EXTRA-CONTRACTS.md) |
| [36-DACORE-KNOWN-ISSUES.md](36-DACORE-KNOWN-ISSUES.md) | DACore bugs and traps |
| [37-DACORE-NOTIFICATIONS.md](37-DACORE-NOTIFICATIONS.md) | Inbox `Notifications@push` |
| [38-DACORE-EMAIL.md](38-DACORE-EMAIL.md) | Outgoing mail API — **open only when the module sends email** |
| [39-DACORE-SMS.md](39-DACORE-SMS.md) | SMS driver registry — **open only when the module sends SMS** |
| [40-DACORE-LIST-PAGER.md](40-DACORE-LIST-PAGER.md) | **List pager law** — HTML classes, `live(el, e)`, encrypted `data-page`, COUNT |

Samples: [examples/EX-D01](examples/EX-D01-dacore-module-skeleton.md) through [EX-D08](examples/EX-D08-list-pager.md), plus **[EX-D09](examples/EX-D09-dacore-pack-zip.md)** (canonical install zip packer). Module-to-module hooks (every module, not DACore-only): [41](41-MODULE-HOOKS.md), [EX-16](examples/EX-16-module-hooks.md). User origin / custom login / shop accounts: [42](42-DACORE-USER-ORIGIN.md). Reserved pack extras / peer APIs: [46](46-DACORE-EXTRA-CONTRACTS.md).

### Most important DACore rules

1. **Never edit, patch, or add files in `app/modules/DACore/` by default** (including DACore assets). **MUST NOT propose** a DACore edit. Put all new work in **the current module**. Touch DACore **only** if the user **themselves** asks **and** confirms the next update wipes it ([00](00-AGENT-CONTRACT.md) §1). Otherwise **strict ban**. Use `DotApp::call("DACore:…")`.
2. **Never write directly** to `dacore_menu`, `dacore_ai_tools`, `dacore_installations`, `dacore_modules`, `dacore_plugin_logs`, `dacore_settings`, `dacore_notifications`, `dacore_notifications_inbox`, `dacore_email_senders`, `dacore_email_templates`, `dacore_sms_senders`, or `users_rights*`. **Read** `extra1`…`extra5` via `DACore:Plugins@listByContract!` / `@listByExtra!` when a host picks packs ([35](35-DACORE-INSTALL.md) §3c, [46](46-DACORE-EXTRA-CONTRACTS.md)).
3. **`#DACore:AuthTest@check!` ignores the rights** you pass it — create your own `Middleware/Rights.php`.
4. Register menu, rights, and AI tools **in your `Installation.php`**, not on every request. Push inbox notifications with **`DACore:Notifications@push` on the event** — not from the installer, not every request ([37](37-DACORE-NOTIFICATIONS.md)). **Outgoing mail:** open [38](38-DACORE-EMAIL.md) (do not invent SMTP). **Outgoing SMS:** open [39](39-DACORE-SMS.md) (do not invent a gateway). **While coding:** **`install.php`** + live root init files; after a new version rename `installed_*` → `install.php`. A **DACore** install zip **MUST** be built with [EX-D09](examples/EX-D09-dacore-pack-zip.md) (`copy .txt` → `dacore-pack-zip.php` → run → delete), **MUST** contain **`dainstall.php`** + **`init/`**, and every `installer()` / `uninstaller()` key **MUST** be quoted text (`'1.0.0' =>` — **not** `self::` / `static::` / constants; the scanner greps text and does not run PHP) — a zip that still has `install.php` **or** no quoted version keys is **rejected** ([00](00-AGENT-CONTRACT.md) §2e / §5 item 27, [35](35-DACORE-INSTALL.md) §2 / §4–§5). A non-DACore module: no zip — `install.php` and copy.
5. Render pages with **`DACore:Page@withMenu!`** — never build your own HTML shell. **ASK** shared nested (`0` → `2` → `1`, `$menuId` `''`) vs module-own before a new DACore module. **No answer → shared nested.** Module-own only if the user explicitly chose it. Edit/detail: 7th `$currentFile` = registered list URL when the path is not under that leaf ([31](31-DACORE-MENU.md)).
6. An AI tool with empty `rights` is **invisible to everyone**; wildcards do not work here.
7. **MUST search DACore first** before a new library or widget (grep `app/modules/DACore/` read-only + your module). The base already has many subpages and libraries — reuse them. **MUST** add your own CSS/JS in the **current** module only when that search finds no equivalent (charts, ported controls). Keep the shell and admin colors. Prefix classes `{lowercase_modulename}_*`. Never edit DACore to “add” UI (unless the informed exception in [00](00-AGENT-CONTRACT.md) §1). **MUST NOT** grep a sibling module for chrome or an example ([00](00-AGENT-CONTRACT.md) §1b).
8. DACore admin runs on **`$dotapp`**. jQuery may sit beside it for UI widgets, but **every request** uses `$dotapp` (`form` / `load` / bridge) — never `$.ajax`. Porting jQuery **is** writing a new `$dotapp().fn` library: **ask**, then rewrite (do not wrap `$.fn`). Playbook: [09](09-DOTAPP-JS-AND-BRIDGE.md) §4.C and [EX-15](examples/EX-15-dotapp-js-library.md).

## Hard rules

1. **AIRULES is the single source of truth** for AI. The old `.cursorrules`, `database_guide.md`, and module `*_AI_guide.md` files are gone.
2. **You may edit only:**
   - `app/config.php`
   - files inside **the module you are programming** at `app/modules/<YourModule>/` (including **its** assets)
3. **Never touch** the core (`app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, …). **DACore:** default same ban (`app/modules/DACore/` — files and assets). **MUST NOT propose** a DACore edit. **Exception:** user themselves asks and confirms the update wipe ([00](00-AGENT-CONTRACT.md) §1).
3b. **Read scope (LAW):** when programming a DACore-bound `<Target>`, **MAY read** only `<Target>` + `app/modules/DACore/` (read-only) + framework core (read-only) + `AIRULES/`. **MUST NOT** browse `app/modules/<Sibling>/` for a look or an example. A sibling joins the allow-list **only** when the user **named** it as the module this work extends / listens to / Extender-targets. Then read that host’s `AIRULES/` — **MUST NOT** open the host’s `PLAN/` unless the user asked to continue developing the host. Examples come from `AIRULES/examples/`, not a live sibling ([00](00-AGENT-CONTRACT.md) §1b, §2p).
4. Create controllers, models, and middleware with **`dotapper.php`**, not by hand.
5. Secure forms = **`<fo-rm>`** + `{{ formName(...) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop) = `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. After save/toggle on the same page **MUST** patch the DOM from JSON + a short toast — no `location.reload()`. **MUST** cover the form/list until the request ends. **DACore admin:** Notiflix (preferred) **or** your module preloaders. **Public website:** you **MUST** build preloaders yourself (Notiflix is DACore-only). Deletes **MUST** open a graphical confirm first (`Notiflix.Confirm` on admin) — never `alert()` / `window.confirm()`. UX **MUST** be excellent on desktop **and** mobile. User-visible strings **MUST** read as shipped product copy — never prompt-echo.
6. **MUST:** Module tables are `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never `items`, `dotapp_*`, or `dacore_*` for your module data.
6b. **MySQL-safe installer DDL (LAW):** probe first (`SHOW TABLES LIKE` / `information_schema`), then `CREATE TABLE` / `ALTER TABLE`. **MUST NOT** `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS` — older MySQL errors; column `IF NOT EXISTS` is MariaDB-only. Helpers in **your** module. Canonical: [07](07-SCHEMA-AND-INSTALL.md) §0, [00](00-AGENT-CONTRACT.md) §5 item 30.
7. **Growing result lists:** users, logs, items, orders, … **MUST** use [40](40-DACORE-LIST-PAGER.md) on first ship (`live(el, e)`, encrypted `data-page`, COUNT + LIMIT), with list search where appropriate. **Single-value pickers are different:** bounded known choices use `<select>`; longer bounded choices use existing `dotSelect2`; only large remote choices use AJAX `dotSelect2`. Never hide known choices behind a bare text input/empty datalist that requires an exact remembered name. Canonical: [40](40-DACORE-LIST-PAGER.md), [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
8. **MUST** store app session state with **`DSM::use('Shop')`**. **MUST NOT** `$_SESSION` or `session_start()`. Canonical: [20](20-CACHE-LOGGER-SESSION.md), [EX-10](examples/EX-10-cache-logger-session.md).
9. **MUST** re-check every persist in **PHP**. Frontend modal/overlay is UX only — skipping it **MUST** still fail on the server. Canonical: [08](08-FORMS-AND-SECURITY.md).
10. **MUST** upload files with **`$dotapp().uploadFile`**. **MUST NOT** `FormData` + `load()` / `<fo-rm>`. PHP **MUST** reject `.php` / executables (extension + `finfo` MIME + headers). Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md).
11. **Public website (MUST):** mobile nav is an overlay drawer from the left or right; the page behind **MUST NOT** scroll while it is open (including iOS); the drawer list **MUST** scroll; contacts + compact search live in the drawer unless large search is its own mobile section. **Not** the DACore admin shell. Canonical: [09](09-DOTAPP-JS-AND-BRIDGE.md) §3.
12. **Cursor credits (MUST):** when **planning** programming, **ASK** whether more expensive models may be used. Subagents **MUST inherit** the chat model. **MUST NOT** silently spawn Opus / GPT-5 / thinking / xhigh / cloud / best-of-N. Composer 2.5 is **only** for hunting a pile of files — **not** the programmer. Canonical: [00](00-AGENT-CONTRACT.md) §2b.
13. **PHP version (MUST):** default **PHP 7.4+**. When **planning**, **ASK** whether to stay on 7.4+ or write for a higher version. No answer → 7.4+. **MUST NOT** ship PHP 8+ syntax unless they named a higher version. Canonical: [00](00-AGENT-CONTRACT.md) §2i.
14. **Finish gate (LAW):** after **every** code chunk **and** before claiming done, **MUST** grep this module — double `crcCheck`, plain IDs, unbound SQL, wrong `data()`, middleware / AuthTest conflicts, `Events::trigger` vs `.hooks`. **MUST NOT** skip. Canonical: [00](00-AGENT-CONTRACT.md) §2c, [17](17-CHECKLISTS.md), [41](41-MODULE-HOOKS.md).
15. **Visible outcome (LAW):** the user **MUST** see save success **and** failure. **DACore admin:** grep DACore first, then **toast** (Notiflix / `$dotapp().toast()`). **Public:** mark the wrong input (red + message on the field). Canonical: [00](00-AGENT-CONTRACT.md) §2d.
16. **Attack vectors (LAW):** the known vectors in [24](24-ATTACK-VECTORS.md) **MUST NOT** be shippable — injection (SQL, XSS, command, template, deserialization), headers / redirect / SSRF, mass assignment, CSRF / brute force / enumeration, IDOR and escalation, files and paths, missing rate limit, leaks, weak crypto, prompt injection. Open the section for the surface you touch, then run the **threat pass** ([24](24-ATTACK-VECTORS.md) §11) on the diff. Fix it in **your** module — never by patching DACore. A vector not listed is still forbidden — apply the nearest rule and **say it in chat**.
17. **Catch bus (LAW):** every `catch` **and** every `execute()` `$err` **MUST** report through one helper in **your** module: `Events::trigger('dotapp.catch', $payload)` then `dotapp.catch.error` (aborted) or `dotapp.catch.info` (recovered), with the fixed payload (`severity, module, source, operation, message, exception, code, file, line, time`, plus `context` ids/counts and `user_id`). No secrets, tokens, rights blobs or request bodies in it. That is what makes a debugger possible later — reporting is **in addition to** the toast, never instead of it. Nothing for this goes under `app/modules/DACore/`. Canonical: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9.
18. **Performance + readability (LAW):** smallest possible I/O, memory bounded by pages (never “load all and filter”), **indexes designed for the queries you wrote** (FK + every `WHERE`/`JOIN`/`ORDER BY` column, composite order equality → range → sort, leftmost prefix, no duplicate prefixes), sane column types, DACore assets reused instead of a second library, and code a human can read: controller/middleware PHPDoc **MUST** start with **`CRCchecking —`** (where CRC runs — prefix XOR action), then a **purpose sentence** then tags (not `@return array<string, mixed>` alone), labeled **`// Why:`** / **`// About:`** / **`// Section:`**. Canonical: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7.
19. **Layout / UX-UI (LAW):** every new button **MUST** be checked for padding vs the parent (especially **bottom**) and placed deliberately (center / same rhythm as siblings). A Save glued to the card edge is a **bug**. General UX/UI principles **MUST** be followed **at all costs**. Canonical: [00](00-AGENT-CONTRACT.md) §2f, [05](05-VIEWS-TEMPLATES-ASSETS.md) §8c.
20. **Module hooks (LAW):** fire **`module.{lowercase_modulename}.{hook_name}.hook`** only when another module could log, show history, or sync (SMS/mail sent, payment, lockout) — **MUST NOT** on every save. Document in **`.hooks`**. Above `trigger()`: `Hook:` / `Why:` / `About:` / `Params:` / `Use:`. Connect by listening, never by patching the owner. A DACore-bound module **MUST** read **`app/modules/DACore/.hooks` first**. Canonical: [41](41-MODULE-HOOKS.md) §6, [00](00-AGENT-CONTRACT.md) §2g.
21. **Extender (judge — not every method):** owner `exists()` + `call()`; ordinary result returns, only `isOriginal()` continues owner logic. Extender registers in `Listeners::register()` before Module initialization. Target URLs in listener map; own Module routes or `[]`; controller string preferred. **MUST NOT** invent `next()`, return the marker, use `.loaded` for initialize-time, Events, `$request`/secrets, or patch owner/DACore. Canonical: [12](12-SERVICES.md) §10, [00](00-AGENT-CONTRACT.md) §2h.
22. **Module AIRULES (LAW):** a host others extend **MUST** ship `app/modules/<This>/AIRULES/`. Packs read that handbook first. **MUST NOT** open the host’s `PLAN/` when writing a pack. Canonical: [00](00-AGENT-CONTRACT.md) §2n, §2p.
23. **PLAN folder (LAW):** a new module / first major surface / rewrite **MUST** write `app/modules/<This>/PLAN/` as a **split folder** and implement **from that folder**. Chat-only / one-file plans fail. **MUST NOT** open a host’s `PLAN/` when writing a pack. Canonical: [00](00-AGENT-CONTRACT.md) §2o, §2p, [45](45-MODULE-PLANNING.md).
24. **Rule stack (LAW):** 1 project `AIRULES/` (always) → 2 named-host `AIRULES/` → 3 **this** module’s `PLAN/`. Priority 1 wins. Canonical: [00](00-AGENT-CONTRACT.md) §2p.
25. **No unsolicited browser (LAW):** **MUST NOT** live-click admin/public pages (MCP browser / CDP) to prove Save/Delete. Finish-gate grep + source UX + a short click list for the operator. Browser only if they asked or said yes. Canonical: [00](00-AGENT-CONTRACT.md) §2r.

## What's New (2026-08-30)

### No unsolicited browser

Agents **MUST NOT** drive a live browser to prove admin/public Save/Delete. Finish-gate grep + source UX. Give the operator a click list. Browser only if they asked or said yes. Canonical: [00](00-AGENT-CONTRACT.md) §2r, [33](33-DACORE-PAGES-AND-UI.md) §12, compact `25-no-unsolicited-browser.mdc`.

### Renderer lifecycle events

Core `dotapp.renderer.before` / `dotapp.renderer.after` events carry `RendererLifecycleContext` contract 1. A before-listener substitutes layout/view/rendered-code output only with `useReplacement()`; listener returns are ignored, and custom/after events are observe-only. Canonical: [05](05-VIEWS-TEMPLATES-ASSETS.md) §5, [EX-18](examples/EX-18-renderer-lifecycle.md).

## What's New (2026-08-26)

### Rule stack — where to look

Agents look in **folders**, not in one Cursor chat file. Priority (1 wins):

1. Project `AIRULES/` — always. Hard laws. A host handbook cannot skip CRC / PHP 7.4+ / DACore ban / finish gate.
2. `app/modules/<Host>/AIRULES/` — only when this work **extends** that host (for example, template or payment packs). How to write a pack. **MUST NOT** weaken 1.
3. `app/modules/<This>/PLAN/` — portable plan of **the module you are building**. Split files. **MUST NOT** weaken 1 or 2.

Pack-specific workflows belong in the named host’s own `AIRULES/` and travel with that host. A pack **MUST NOT** open the host’s `PLAN/`. Building a host itself writes both `app/modules/<Host>/AIRULES/` (for future pack authors) and `app/modules/<Host>/PLAN/` (for host development).

Canonical: [00](00-AGENT-CONTRACT.md) §2n, §2o, §2p, [45](45-MODULE-PLANNING.md).

## What's New (2026-08-22)

### Extender — opt-in method replacement

New core class: **`Dotsystems\App\Parts\Extender`**. A module can replace another module’s method for the current request — one handler either owns the result or explicitly defers to the owner’s original logic. This is **not** Events, hooks, or `triggerWithVeto()`. **MUST NOT** patch DACore to insert `Extender::call`.

- **Judge first:** offer Extender on highly replaceable **outputs** (page/block HTML, cart, export) — **not** on every method.
- When the owner opts in: `exists()` then `call(...)`; return an ordinary result, or continue only when `isOriginal()` recognizes the unique `original()` marker. There is no `next()` chain.
- Register `extend()` in **`Listeners::register()`**. Target URLs belong in `Listeners::initializeRoutes()`; Module keeps own routes or `[]`.
- Prefer a controller string. Direct registration is before every Module `initialize()`; `.loaded` is too late for initialize-time points.
- One replacement per class+method. Recursion throws. Pass only explicit safe arguments.

Canonical: [12](12-SERVICES.md) §10. Sample: [EX-17](examples/EX-17-extender.md). Framework README: project-root `README.md`.

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
| [23-DEBUG-PLAYBOOK.md](23-DEBUG-PLAYBOOK.md) | Hunt order when the user asks **why** it fails — grep middleware + `crcCheck` first; §1c = `dotapp.catchall` event tracer; DACore = §7 |
| **[24-ATTACK-VECTORS.md](24-ATTACK-VECTORS.md)** | **Known attack vectors as law** — injection, identity, access control, headers, files, abuse, leaks, crypto, AI + the §11 threat pass |
| **[25-PERFORMANCE-AND-CODE-QUALITY.md](25-PERFORMANCE-AND-CODE-QUALITY.md)** | **Performance + schema + readable code as law** — memory/algorithms, I/O budget, **index & column design**, big lists, frontend cost (reuse DACore assets), **PHPDoc purpose sentence** + Why/About/Section, §8 perf pass |
| **[41-MODULE-HOOKS.md](41-MODULE-HOOKS.md)** | **Module hooks (LAW)** — `module.{mod}.{name}.hook` when useful (not every save); `.hooks` file; listen, do not patch; DACore-bound modules **MUST** read `app/modules/DACore/.hooks` first |
| **[42-DACORE-USER-ORIGIN.md](42-DACORE-USER-ORIGIN.md)** | **User origin (LAW)** — global identity/session trust model; checked register/create/stamp/read; exact-origin login+2FA+every gate/list/write; generic failures; ASK before DACore-replacement admin; no RCE/IDOR/escalation |
| **[45-MODULE-PLANNING.md](45-MODULE-PLANNING.md)** | **Planning depth + PLAN folder (LAW)** — write `app/modules/<This>/PLAN/` (split files) then implement from it. Packs read host `AIRULES/`, not host `PLAN/` ([00](00-AGENT-CONTRACT.md) §2p) |
| **[46-DACORE-EXTRA-CONTRACTS.md](46-DACORE-EXTRA-CONTRACTS.md)** | **Reserved `extra1`…`extra5` index (LAW)** — slot grammar, `listByContract!`; deep I/O in **[46-contracts/](46-contracts/README.md)** (template: [filemanager.md](46-contracts/filemanager.md)); hosts MUST NOT invent tokens |
| [cursor/](cursor/) | Cursor IDE: **source** `AGENTS.md` + `.mdc` rules. Agent **MUST** copy them into `.cursor/rules/` ([00](00-AGENT-CONTRACT.md) §2l, [INSTALL.md](INSTALL.md)) |

## Critical: return values

The framework uses **four different failure styles**. That is why [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md) is mandatory reading:

- `execute()` **throws** if you omit the error callback
- `first()` is unsafe on an empty result (RAW warning, ORM fatal)
- `Crypto::decrypt()` returns **`false`**, `Cache::load()` returns **`null`**
- `Email::send()` returns an **array of error strings**, not `false`
- a missing template renders **`""`** with no exception
- `Auth::login()` returns **`false`** on bad input
- `$request->data()` is **protected**; persist / passwords / HTML / URLs **MUST** use `$request->data(true)` — SQL write is named / `?` bindings, not `protect()` ([19](19-VALIDATION-AND-INPUT.md), [06](06-DATABASE.md))

## What AIRULES is not

- It is not a copy of the old `.cursorrules` — that file taught **incorrect** template syntax.

## Version

- **Part:** 1 (Framework) + 2 (DACore)
- **Target models:** GROK 4.6 and weaker. Subagents **MUST inherit** that chat model unless the user said yes in the plan ([00](00-AGENT-CONTRACT.md) §2b).
- **IDE:** Cursor (primary), portable to other agents via markdown
- **Verified against:** DotApp clean install source (`app/parts/*`, `dotapper.php`) and the DACore module
