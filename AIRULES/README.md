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
3. **Never touch** the core (`app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, …).
4. Create controllers, models, and middleware with **`dotapper.php`**, not by hand.
5. Secure forms = **`<fo-rm>`** + `{{ formName(...) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop) = `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. After save/toggle on the same page **MUST** patch the DOM from JSON + a short toast — no `location.reload()`. **MUST** ship **your own** form/list preloaders (Notiflix is DACore-only). Deletes **MUST** open a graphical confirm first — never `alert()` / `window.confirm()`. UX **MUST** be excellent on desktop **and** mobile.
6. **MUST:** Module tables are `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never `items` or `dotapp_*` for module data.

## Quick install

See [INSTALL.md](INSTALL.md).

In short:

1. Install DotApp (`git clone` or `php dotapper.php --install`) — `AIRULES/` ships with the framework.
2. Copy the Cursor rules as described in [INSTALL.md](INSTALL.md).
3. Tell the agent: *“Read AIRULES/00-AGENT-CONTRACT.md first. Follow AIRULES for all DotApp work.”*

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
| [09-DOTAPP-JS-AND-BRIDGE.md](09-DOTAPP-JS-AND-BRIDGE.md) | Frontend + Bridge + **custom `$dotapp().fn` libraries** (jQuery ports = §4.C) |
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

- It does not depend on DACore — you can write ordinary modules on the bare framework.
- It is not a copy of the old `.cursorrules` — that file taught **incorrect** template syntax.

## Version

- **Part:** 1 (Framework)
- **Target models:** GROK 4.6 and weaker
- **IDE:** Cursor (primary), portable to other agents via markdown
- **Verified against:** DotApp clean install source (`app/parts/*`, `dotapper.php`)
