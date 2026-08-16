# DotApp AIRULES-DACORE — AI Agent Rulebook (Framework + DACore)

A complete set of rules and guides for AI agents (Cursor IDE, GROK 4.6, and weaker models) so they can work **correctly** with the DotApp PHP framework **and the DACore module** — without Laravel/Blade/Eloquent/jQuery hallucinations.

## What this is

`AIRULES-DACORE/` is the DACore overlay. Use it in a project where **the DACore admin module is installed**.

- **Language:** English
- **Contents:** docs `00`–`22` = framework · `30`–`36` = DACore layer

### Which variant to use

| Project | Folder |
|---------|--------|
| Bare framework, no admin UI | `AIRULES/` |
| DACore admin module installed | **`AIRULES-DACORE/`** (this one) |

Never copy both into the same project — the agent would have two conflicting sources. Copy this folder into the project **as `AIRULES/`**.

## DACore layer (`30`–`36`)

| File | Contents |
|------|----------|
| [30-DACORE-OVERVIEW.md](30-DACORE-OVERVIEW.md) | What DACore is, tables, config keys, call map |
| [31-DACORE-MENU.md](31-DACORE-MENU.md) | Menu item registration |
| [32-DACORE-RIGHTS.md](32-DACORE-RIGHTS.md) | Permissions + route guards |
| [33-DACORE-PAGES-AND-UI.md](33-DACORE-PAGES-AND-UI.md) | `Page@withMenu`, dotgrid, UI contract |
| [34-DACORE-AI-TOOLS.md](34-DACORE-AI-TOOLS.md) | AI tools for the DACore chat |
| [35-DACORE-INSTALL.md](35-DACORE-INSTALL.md) | Installer with DACore tracking |
| [36-DACORE-KNOWN-ISSUES.md](36-DACORE-KNOWN-ISSUES.md) | DACore bugs and traps |

Samples: [examples/EX-D01](examples/EX-D01-dacore-module-skeleton.md) through [EX-D04](examples/EX-D04-dacore-installer.md).

### Most important DACore rules

1. **Never edit files in `app/modules/DACore/`** — integrate via `DotApp::call()` APIs.
2. **Never write directly** to `dacore_menu`, `dacore_ai_tools`, `dacore_installations`, or `users_rights*`.
3. **`#DACore:AuthTest@check!` ignores the rights** you pass it — create your own `Middleware/Rights.php`.
4. Register menu, rights, and AI tools **in `Installation.php`**, not on every request.
5. Render pages with **`DACore:Page@withMenu!`** — never build your own HTML shell.
6. An AI tool with empty `rights` is **invisible to everyone**; wildcards do not work here.

## Hard rules

1. **AIRULES is the single source of truth** for AI. The old `.cursorrules`, `database_guide.md`, and module `*_AI_guide.md` files are gone.
2. **You may edit only:**
   - `app/config.php`
   - files inside **your own** module at `app/modules/<YourModule>/`
3. **Never touch** the core (`app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, …).
4. Create controllers, models, and middleware with **`dotapper.php`**, not by hand.
5. Secure forms = **`<fo-rm>`** + `{{ formName(...) }}` — **not** jQuery, **not** `f-form`.

## Quick install

See [INSTALL.md](INSTALL.md).

In short:

1. Install DotApp (`git clone` or `php dotapper.php --install`).
2. Copy `AIRULES-DACORE/` into the project **as `AIRULES/`** (replacing the framework-only variant).
3. Copy the Cursor rules as described in [INSTALL.md](INSTALL.md).
4. Tell the agent: *“Read AIRULES/00-AGENT-CONTRACT.md first. Follow AIRULES for all DotApp work.”*

## Code samples (save context)

Theory lives in `00`–`22`. **Ready copy-paste patterns** are in [examples/](examples/) — the agent should open **one** EX file per task:

- [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md) — **preferred** security: `fo-rm` + `formName` + `dotapp.js`
- More: module, DB, renderer, JS boot, bridge, secrets — see [examples/README.md](examples/README.md)

**Security priority:** for user-facing forms always use `fo-rm` + `formName` + required `/assets/dotapp/dotapp.js` (injects random keys). This is stronger than plain CSRF.

## Document map

| File | Contents |
|------|----------|
| [00-AGENT-CONTRACT.md](00-AGENT-CONTRACT.md) | Hard laws — edit boundaries, workflow |
| [examples/](examples/) | Short code samples by situation |
| [01-ARCHITECTURE.md](01-ARCHITECTURE.md) | Lifecycle, module structure |
| [02-DOTAPPER-CLI.md](02-DOTAPPER-CLI.md) | Full CLI reference |
| [03-MODULES-AND-ROUTING.md](03-MODULES-AND-ROUTING.md) | Modules, routes, middleware |
| [04-CONTROLLERS-AND-RESPONSES.md](04-CONTROLLERS-AND-RESPONSES.md) | Controllers, response |
| [05-VIEWS-TEMPLATES-ASSETS.md](05-VIEWS-TEMPLATES-ASSETS.md) | Template syntax, assets |
| [06-DATABASE.md](06-DATABASE.md) | DB / QueryBuilder |
| [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md) | Migrations, Installation.php |
| [08-FORMS-AND-SECURITY.md](08-FORMS-AND-SECURITY.md) | fo-rm, CRC, CSRF |
| [09-DOTAPP-JS-AND-BRIDGE.md](09-DOTAPP-JS-AND-BRIDGE.md) | Frontend + Bridge |
| [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md) | Config, keys, fallbacks |
| [11-AUTH-AND-CRYPTO.md](11-AUTH-AND-CRYPTO.md) | Auth, 2FA, Crypto |
| [12-SERVICES.md](12-SERVICES.md) | Cache, Logger, Email, … |
| [13-TESTING.md](13-TESTING.md) | Tester + dotapper --test |
| [14-ANTIPATTERNS.md](14-ANTIPATTERNS.md) | Wrong vs right (Laravel/…) |
| [15-KNOWN-ISSUES.md](15-KNOWN-ISSUES.md) | Quirks + leftover-doc corrections |
| [16-RECIPES.md](16-RECIPES.md) | End-to-end recipes |
| [17-CHECKLISTS.md](17-CHECKLISTS.md) | Pre-flight / pre-commit |
| **[18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md)** | **Return values + error handling (mandatory)** |
| [19-VALIDATION-AND-INPUT.md](19-VALIDATION-AND-INPUT.md) | Validator, Input, Request, Response, HttpHelper, Limiter |
| [20-CACHE-LOGGER-SESSION.md](20-CACHE-LOGGER-SESSION.md) | Cache, Logger, DSM, Config |
| [21-EMAIL-SMS-QR.md](21-EMAIL-SMS-QR.md) | Email/IMAP/POP3, SMS, QR |
| [22-AI-SEARCH-MCP.md](22-AI-SEARCH-MCP.md) | AI drivers, FastSearch, MCP |
| [cursor/](cursor/) | Cursor IDE: AGENTS.md, `.mdc` rules |

## Critical: return values

The framework uses **four different failure styles**. That is why [18-ERROR-HANDLING-AND-RETURN-VALUES.md](18-ERROR-HANDLING-AND-RETURN-VALUES.md) is mandatory reading:

- `execute()` **throws** if you omit the error callback
- `first()` is unsafe on an empty result (RAW warning, ORM fatal)
- `Crypto::decrypt()` returns **`false`**, `Cache::load()` returns **`null`**
- `Email::send()` returns an **array of error strings**, not `false`
- a missing template renders **`""`** with no exception
- `Auth::login()` returns **`false`** on bad input

## What AIRULES is not

- It is not a copy of the old `.cursorrules` — that file taught **incorrect** template syntax.

## Version

- **Part:** 1 (Framework) + 2 (DACore)
- **Target models:** GROK 4.6 and weaker
- **IDE:** Cursor (primary), portable to other agents via markdown
- **Verified against:** DotApp clean install source (`app/parts/*`, `dotapper.php`) and the DACore module
