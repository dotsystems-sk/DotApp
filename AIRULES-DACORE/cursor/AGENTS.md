# AGENTS.md — DotApp + DACore

You are working on a **DotApp PHP** project (not Laravel/Symfony/CodeIgniter) that has the **DACore** admin module installed.

## Before any edit

1. Read `AIRULES/00-AGENT-CONTRACT.md`.
2. Follow the entire `AIRULES/` knowledge base.
3. Edit **only** `app/config.php` and `app/modules/<TargetModule>/` (your own module).
4. **Never** edit `app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, **or `app/modules/DACore/`**.
5. **Never add files into DACore.** A DACore update overwrites the whole module; every local change disappears. Extend via **your own** module and `DotApp::call()` APIs only.

## Non-negotiable syntax

- Routes: `Module:Controller@method!` (`!` = no DI parameters in the method).
- Controllers: `public static function`.
- DB: `DB::module("RAW")->q(function ($qb) { ... })->all()|first()|execute()`.
- **Tables MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names, `dotapp_*`, or `dacore_*` for module data.
- Templates: `{{ var: $x }}`, `{{ if }}...{{ /if }}`, `{{ foreach }}...{{ /foreach }}` — **not** Blade `{{ $x }}` / `endif`.
- Forms (**preferred always**): `<fo-rm>` + `{{ formName(handler) }}` + **`/assets/dotapp/dotapp.js`** (injects random keys) + PHP `crcCheck()` + `form()` — stronger than plain CSRF. Sample: `AIRULES/examples/EX-01-secure-form-complete.md`.
- JS: `$dotapp` — **not** `$` / `$.ajax`.

## Scaffolding

Prefer `php dotapper.php` generators. Run from project root. Put `--module=` **before** `--create-controller|model|middleware`.

## Deep docs

| Topic | File |
|-------|------|
| Contract | `AIRULES/00-AGENT-CONTRACT.md` |
| CLI | `AIRULES/02-DOTAPPER-CLI.md` |
| Routing | `AIRULES/03-MODULES-AND-ROUTING.md` |
| Controllers | `AIRULES/04-CONTROLLERS-AND-RESPONSES.md` |
| Views | `AIRULES/05-VIEWS-TEMPLATES-ASSETS.md` |
| Database | `AIRULES/06-DATABASE.md` |
| Forms | `AIRULES/08-FORMS-AND-SECURITY.md` |
| Frontend | `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` |
| Config/secrets | `AIRULES/10-CONFIG-AND-SECRETS.md` |
| Antipatterns | `AIRULES/14-ANTIPATTERNS.md` |
| Checklists | `AIRULES/17-CHECKLISTS.md` |
| **DACore overview** | `AIRULES/30-DACORE-OVERVIEW.md` |
| DACore menu | `AIRULES/31-DACORE-MENU.md` |
| DACore rights | `AIRULES/32-DACORE-RIGHTS.md` |
| DACore pages / UI | `AIRULES/33-DACORE-PAGES-AND-UI.md` |
| DACore AI tools | `AIRULES/34-DACORE-AI-TOOLS.md` |
| DACore installer | `AIRULES/35-DACORE-INSTALL.md` |
| DACore quirks | `AIRULES/36-DACORE-KNOWN-ISSUES.md` |

## DACore rules (hard)

DACore is as sacred as framework core. It is updated as a package; **any edit or extra file inside it is wiped on update.**

- **Never** edit, patch, delete, or **add** anything under `app/modules/DACore/`
- Use only what DACore already exposes: `DotApp::call("DACore:…")`
- Put all new admin features in **your own** module (`app/modules/<YourModule>/`)
- Never write directly to `dacore_menu` / `dacore_ai_tools` / `dacore_installations` / `users_rights*`
- Render admin pages with `DACore:Page@withMenu!`
- Prefer DACore widgets; **MUST** add module CSS/JS (`$css`/`$js`) when the shell has no equivalent (charts, ported UI). Classes `{lowercase_modulename}_*`. Match admin colors. Never patch DACore.
- Guard routes with your own `#YourModule:Rights@check!` — `#DACore:AuthTest@check!` ignores passed rights
- Register menu / rights / AI tools in **your** `Installation.php`
- If asked to “just change DACore”: refuse and implement it in your module instead

AIRULES is the single source of truth.
