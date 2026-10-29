---
name: finalcontrol
description: Final control of one DotApp/DACore module before hand-off. Walks every button and control, checks padding and spacing between blocks, JS listeners, backend endpoints, rights and privilege escalation, Why/About comments and runtime schema probes, then fixes every failure. Use when the user types /finalcontrol, asks for a final control, záverečná kontrola, finálna kontrola, or a pre-release check of a module.
disable-model-invocation: true
---

# Final control (`/finalcontrol <Module>`)

Canonical law: `AIRULES/00-AGENT-CONTRACT.md` §2u. This is a **fix run**, not a report-only audit: every failure found is fixed in the same run, then re-checked.

## 0. Scope

- Target = the module the user named (`/finalcontrol FaceTerminal`). No name → the module of the current task; still unclear → **ASK**.
- Read scope: `app/modules/<Target>/`, DACore read-only, `app/parts/` read-only, `AIRULES/` (`module-read-scope.mdc`). Host `AIRULES/` if the target is a pack.
- Read `app/modules/<Target>/PLAN/` first when it exists.
- **No browser** (`25-no-unsolicited-browser.mdc`). Everything is checked in source.
- Large module: split the inventory by folder across subagents (model = what the user chose, default `inherit`). **Verify** their findings yourself before fixing; do not trust a subagent's "OK".

Copy this checklist into the reply and tick it:

```
Final control <Module>:
- [ ] 1. Control inventory (every button / link-action / form / row action)
- [ ] 2. Layout: padding on all sides, space below last buttons, spacing between blocks, mobile
- [ ] 3. JS: every control has a working listener / form / GET route
- [ ] 4. Backend: every JS URL hits a registered route + existing method, HTTP 200 + status
- [ ] 5. Rights: login gate, Rights@check per route, write right ≠ view right, ownership, no escalation
- [ ] 6. Input / IDs / CRC / SQL (finish gate rows)
- [ ] 7. Comments: CRCchecking — PHPDoc, Why / About / Section
- [ ] 8. Runtime schema probes = zero (AIRULES/07 §0a1)
- [ ] 9. Fix all, re-grep, tests, PLAN log, click list
```

## 1. Control inventory

Grep `views/**/*.view.php`, `views/**/*.layout.php`, module `assets/**/*.js`, and any HTML built in PHP:

`<button`, `class="[^"]*\bbtn\b`, `type="submit"`, `<fo-rm`, `{{ formName(`, `data-(action|id|page|token|del|toggle)`, `<a [^>]*href=`, `onclick=`, `$dotapp().load(`, `$dotapp().form(`, `.live(`, `uploadFile(`, `twoFactor(`, `modal(`.

Build one row per control:

| # | Control (label) | view file:line | Parent block | JS handler file:line | URL + method | Route (module.init.php:line) | Controller@method | Rights | Result |

A control with no row in every column is a **finding**, not a skip.

## 2. Layout / UX (`ux-ui-layout.mdc`, `23-admin-page-sections.mdc`, `AIRULES/33` §6–§6b)

For each control and its parent, read the markup **and** the CSS that applies (module CSS + DACore classes used):

- Padding vs parent on **left, right, top, bottom**. Nothing flush against a card, `card-body`, footer, modal, drawer, or page edge.
- Buttons that are the **last content** of a block have space below (`card-footer` + `pb-3`/`pb-4` or CSS `padding-bottom`). `pt-0` / `border-0` is compensated on the other side.
- Placement matches sibling blocks (centered or same rhythm), not shoved left.
- Groups of buttons wrap on mobile with a gap (`gap`, `flex-wrap`), touch target ≥ 40px, no horizontal overflow.
- **Spacing between blocks:** consecutive cards / sections / `<dot-col>` / alerts / tables / pagers have vertical gap (`mb-*`, `gap`, margin). Two blocks touching = finding. Same for label ↔ input ↔ help text inside forms.
- Admin forms: page header + numbered sections + lede + Why panel when needed; not one wall of inputs.
- Choice controls follow `ux-ui-choice-controls.mdc`; growing lists have the AJAX pager (`13-dacore-list-pager.mdc`).
- No Bootstrap tabs (`22-dacore-not-bootstrap.mdc`).

## 3. JS listeners (`AIRULES/09` §3)

Every actionable control has exactly one working path:

- `<fo-rm>` with `{{ formName(handler) }}` **inside** it and a submit button, or
- `$dotapp().live("click", selector, function (el, e) { … })` — first argument is the **element** (`function (e)` with `e.currentTarget` = dead button), or
- a plain `<a href>` to a **registered GET** route.

Check per handler: selector actually matches the markup (class/data attribute spelled the same); overlay on start and removed on success **and** error; success patches `reply.html` + toast (no `location.reload()`); error shows `reply.message`; delete uses `Notiflix.Confirm` / `$dotapp().modal()` (no `alert` / `confirm`); text written with `.text()` not `.html()` for user strings. Buttons with a handler but no markup, and markup with no handler, are both findings.

## 4. Backend endpoints (`24-admin-ajax-and-class-bugs.mdc`)

For every URL found in JS or forms:

- A matching `Router::get/post` exists in `module.init.php` (same path, same HTTP method, same prefix) and points to an existing `Controller@method!`.
- The method unwraps nested `data` (`load()`/`form()` post `{ data: {...}, crc }`), reads persist values with `data(true)`, and answers **HTTP 200 + `status` 0|1 + `message`** for product outcomes (no 400/500 for "not found"/"invalid").
- `crcCheck()` runs **once** per POST (prefix middleware **or** action, never both, never on GET or `upload()`).
- Every `catch` / `execute()` `$err` reports through the module catch helper.
- Same-class rule: one broken handler → grep and fix every sibling.

## 5. Rights and privilege escalation (`AIRULES/32`, `AIRULES/11` §11, `AIRULES/24`)

For **every** route (GET pages and POST actions):

- Admin route is under `{prefixUrl}/<Module>/…` or `/api/v1/auth/<Module>/…`, behind `Gate@login` / `LoginAndCRC!`, and registered only inside `Auth::isLogged() === true`.
- `->before(... Rights@check ...)` with explicit `['dotapp.root', '<Module>.<right>']`. **No route without a rights check.** `#DACore:AuthTest@check!` is not a rights guard.
- A POST that writes/deletes needs the **write/delete** right, not the page's view right. Compare each POST with the GET that shows its button.
- IDs from the browser are encrypted with a unique `$key2`, `Crypto::decrypt` is checked `=== false`, and the SQL scopes by owner/tenant (`WHERE id = :id AND owner = :me`) — no IDOR.
- No acting on a more privileged operator (editing root/admin, granting rights the actor does not hold, changing own role).
- No rights, role, owner, or price taken from the request body; no column/sort names from the request without a whitelist.
- JS-only gates (hidden button, disabled field, overlay) are re-checked in PHP.
- Public (`noauth`) POST has `throttle()`; public pages do not leak admin data.

## 6. Finish-gate rows

Run the full table in `10-dotapp-finish-gate.mdc` on the module (CRC once, encrypted IDs, URL-safe tokens, bound SQL, `data(true)`, visible outcome, catch bus, hooks vs `.hooks`, PHP 7.4 syntax, perf). Grep, do not imagine.

## 7. Comments (`AIRULES/25` §7) — fix, do not just report

- Every public Controller / Middleware method: PHPDoc **first line** `CRCchecking — <prefix|this action|none>`, then a purpose sentence, then `@param`/`@return` with meaning.
- Every other public method: purpose sentence (no tags-only PHPDoc).
- Every non-obvious logical step: `// Why:` (the reason, constraint, or risk — not what the line does).
- Every page action / route block: `// About:` (what this chunk is) and `// Section:` (menu/route group).
- JS handlers: `// About:` per handler, `// Why:` for overlay/unwrap/confirm decisions.
- Remove narration (`// increment i`), dead code, bare `TODO`, prompt-echo.

## 8. Runtime schema probes

Outside `Installation.php`: zero `SHOW TABLES|COLUMNS|INDEX`, `information_schema`, `DESCRIBE`, `tableExists`, `columnExists`, `indexExists`, `hasColumn`/`hasTable` with DB I/O, `ensureTable(s)`, executed `CREATE`/`ALTER`. See `AIRULES/07` §0a1.

## 9. Fix, verify, report

1. Fix every finding in the module (only `app/modules/<Target>/`; DACore only under the informed exception).
2. Re-run the greps of steps 1–8; `php -l` changed files; `php dotapper.php --module=<Target> --test`.
3. Append a dated entry to `app/modules/<Target>/PLAN/11-LOG.md` (what was checked, what was fixed, what is open).
4. Reply in the user's language:
   - one-line verdict;
   - table: area | checked | found | fixed | open;
   - list of fixes with `file:line`;
   - **click list** for the operator (open X → press Y → expect toast Z), because the browser is not used.

Never claim "all OK" for a step whose grep was not run.
