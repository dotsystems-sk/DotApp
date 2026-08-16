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

AIRULES is the single source of truth.
