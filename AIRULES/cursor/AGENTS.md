# AGENTS.md — DotApp

You are working on a **DotApp PHP** project (not Laravel/Symfony/CodeIgniter).

## Before any edit

1. Read `AIRULES/00-AGENT-CONTRACT.md`.
2. Follow the entire `AIRULES/` knowledge base.
3. Edit **only** `app/config.php` and `app/modules/<TargetModule>/`.
4. **Never** edit `app/parts/`, `app/DotApp.php`, `app/vendor/`, `dotapper.php`, `index.php`, or other modules.

## Non-negotiable syntax

- Routes: `Module:Controller@method!` (`!` = no DI parameters in the method).
- Controllers: `public static function`.
- DB: `DB::module("RAW")->q(function ($qb) { ... })->all()|first()|execute()`.
- **Tables MUST** be `{lowercase_modulename}_*` (module `Shop` → `shop_items`). Never unprefixed names or `dotapp_*` for module data.
- Templates: `{{ var: $x }}`, `{{ if }}...{{ /if }}`, `{{ foreach }}...{{ /foreach }}` — **not** Blade `{{ $x }}` / `endif`.
- Forms: `<fo-rm>` + `{{ formName(handler) }}` **MUST between** `<fo-rm>` and `</fo-rm>` — **only** for real multi-field submit. Row actions (toggle, delete, reorder, drag-and-drop, paginate) **MUST** be `$dotapp().load()` + encrypted `data-*`, never one `<fo-rm>` per button. + **`/assets/dotapp/dotapp.js`** + PHP `crcCheck()` + `form()` for real forms. Sample: `AIRULES/examples/EX-01-secure-form-complete.md`. Row-action sample: `AIRULES/examples/EX-06-dotapp-js-boot.md`.
- JS: `$dotapp` — **not** `$` / `$.ajax`. After a successful `fo-rm` / `load` **MUST** update the DOM from JSON (`html` / data) and a short toast — no `location.reload()`. **MUST** overlay the form/list with **your module preloaders** until the request ends. UX **MUST** work on desktop **and** mobile. `redirectTo` only when leaving the page. 2FA boxes: **`$dotapp().twoFactor`**. Deletes: graphical confirm first — never `alert()` / `window.confirm()`.

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
| Frontend | `AIRULES/09-DOTAPP-JS-AND-BRIDGE.md` (§3 = live UX + overlays desktop/mobile; §4 = `$dotapp().fn`; §4.C = jQuery ports) |
| Config/secrets | `AIRULES/10-CONFIG-AND-SECRETS.md` |
| Antipatterns | `AIRULES/14-ANTIPATTERNS.md` |
| Checklists | `AIRULES/17-CHECKLISTS.md` |

AIRULES is the single source of truth.
