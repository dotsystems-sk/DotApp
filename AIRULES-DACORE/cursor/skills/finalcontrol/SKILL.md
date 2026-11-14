---
name: finalcontrol
description: Final control of one DotApp/DACore module before hand-off. Walks every button and control, checks padding and spacing between blocks, JS listeners, backend endpoints, rights and privilege escalation, Why/About comments, runtime schema probes, and leftover agent helper files whose names start with _, then fixes every failure. Use when the user types /finalcontrol, asks for a final control, záverečná kontrola, finálna kontrola, or a pre-release check of a module.
disable-model-invocation: true
---

# Final control (`/finalcontrol <Module>`)

Canonical law: `AIRULES/00-AGENT-CONTRACT.md` §2u. The standard every control is held to: `AIRULES/26-CONTROL-SLICE.md`. This is a **fix run**, not a report-only audit: every failure found is fixed in the same run, then re-checked.

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
- [ ] 3. JS: every control has a working listener / form / GET route; opening `<fo-rm>` has literal method and action
- [ ] 4. Backend: every JS URL hits a registered route + existing method, HTTP 200 + status
- [ ] 5. Rights: login gate, Rights@check per route, write right ≠ view right, ownership, no escalation
- [ ] 6. Input / IDs / CRC / SQL (finish gate rows)
- [ ] 7. Comments: CRCchecking — PHPDoc, Why / About / Section
- [ ] 8. Runtime schema probes = zero (AIRULES/07 §0a1)
- [ ] 8b. No leftover agent helpers (no file or folder whose name starts with _)
- [ ] 9. Fix all, re-grep, tests, PLAN log, click list
```

## 1. Control inventory

Run the source check first and keep its output — it finds dead buttons, URLs with no route, silent requests, raw DOM, a wrong boot block, HTTP 4xx/5xx product replies and spacing classes with no CSS rule:

```
php AIRULES/tools/control-check.php <Target>
```

Every line it prints is a finding. It is a floor, not the whole control: continue with the inventory below.

Grep `views/**/*.view.php`, `views/**/*.layout.php`, module `assets/**/*.js`, and any HTML built in PHP:

`<button`, `class="[^"]*\bbtn\b`, `type="submit"`, `<fo-rm`, `{{ formName(`, `data-(action|id|page|token|del|toggle)`, `<a [^>]*href=`, `onclick=`, `$dotapp().load(`, `$dotapp().form(`, `.live(`, `uploadFile(`, `twoFactor(`, `modal(`.

Build one row per control:

| # | Control (label) | view file:line | Parent block | JS handler file:line | URL + method | Route (module.init.php:line) | Controller@method | Rights | Result |

A control with no row in every column is a **finding**, not a skip.

## 2. Layout / UX (`ux-ui-layout.mdc`, `23-admin-page-sections.mdc`, `AIRULES/33` §6–§6b)

For each control and its parent, read the markup **and** the CSS that applies (module CSS + DACore classes used):

- Padding vs parent on **left, right, top, bottom**. Nothing flush against a card, `card-body`, footer, modal, drawer, or page edge.
- Buttons are in an action row: `.dacore-card-actions` after `.card-body`, or `d-flex flex-wrap gap-2`, or `.dacore-toolbar` (`AIRULES/26` §2). A bare button at the end of `.card-body` is a finding.
- Every spacing class used has a CSS rule in DACore `core.css` or this module CSS. `card-footer`, `pb-3`, `pb-4`, `pt-0`, `pt-3`, `px-4`, `border-0`, `bg-transparent` have **none** in the shell — replace them, do not add more of them.
- Placement matches sibling blocks (centered or same rhythm), not shoved left.
- Groups of buttons wrap on mobile with a gap (`gap`, `flex-wrap`), touch target ≥ 40px, no horizontal overflow.
- **Spacing between blocks:** consecutive cards / sections / `<dot-col>` / alerts / tables / pagers have vertical gap (`mb-*`, `gap`, margin). Two blocks touching = finding. Same for label ↔ input ↔ help text inside forms.
- Admin forms: page header + numbered sections + lede + Why panel when needed; not one wall of inputs.
- Choice controls follow `ux-ui-choice-controls.mdc`; growing lists have the AJAX pager (`13-dacore-list-pager.mdc`).
- No Bootstrap tabs (`22-dacore-not-bootstrap.mdc`).

## 3. JS listeners (`AIRULES/09` §3)

Every actionable control has exactly one working path:

- `<fo-rm>` with `{{ formName(handler) }}` **inside** it and a submit button. The opening tag (`<fo-rm` through its `>`) has **literal** `method` and `action`. Any `{{ }}` in that opening tag is a **finding**: rewrite the attributes as plain text (`action="/api/v1/auth/<Module>/…"`). Do not leave `{{ var: $apiAuth }}/…` or `action="{{ var: $postAction }}"` even when `method` is written first — compiled `?>` hides `method` (the page shows `{{ formName }}` as text) or cuts `action` (the form posts to the current page). `{{ }}` in the form **body** (field values, labels, `formName`) is correct.
- `$dotapp().live("click", selector, function (el, e) { … })` — first argument is the **element** (`function (e)` with `e.currentTarget` = dead button), or
- a plain `<a href>` to a **registered GET** route.

Check per handler: selector actually matches the markup (class/data attribute spelled the same); the request goes through the module helper (`xxxSend` / `xxxForm`, `AIRULES/26` §5) — a direct `$dotapp().load()` / `.form()` in a handler is a finding; `.form()` has `.onError()` as well as `.after()`; `load()` has its error callback; overlay removed and a toast shown on success, on `status` 0 **and** on HTTP failure; success patches `reply.html` (no `location.reload()`); the file ends with the canonical boot block; `$dotapp` is used instead of `getElementById` / `querySelector` / `getAttribute` / `innerHTML`; delete uses `Notiflix.Confirm` / `$dotapp().modal()` (no `alert` / `confirm`); text written with `.text()` not `.html()` for user strings. Buttons with a handler but no markup, and markup with no handler, are both findings.

## 4. Backend endpoints (`24-admin-ajax-and-class-bugs.mdc`)

For every URL found in JS or forms:

- A matching `Router::get/post` exists in `module.init.php` (same path, same HTTP method, same prefix) and points to an existing `Controller@method!`.
- The method unwraps nested `data` (`load()`/`form()` post `{ data: {...}, crc }`), reads persist values with `data(true)`, and answers **HTTP 200 + `status` 0|1 + `message`** on every branch through the module `Reply::ok()` / `Reply::fail()` (no 400/403/500, no `Response::json()` for a `load()` / `form()` caller). Each stop — input, record, rule, DB `$err`, `catch` — has its own sentence; `Server error` / `Bad request` / `Rejected` / `Save failed` are findings.
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

## 8b. Leftover agent helpers

Scratch files from a stuck agent run usually have a name that starts with `_` (`_probe.php`, `Libraries/_dump.php`, `_scratch/note.txt`). Glob the whole module. Any **file or folder** under `app/modules/<Target>/` whose name starts with `_` is a **finding**.

They must not stay in the tree. A leftover script or dump is a security hole if it ships. A DACore install zip **MUST NOT** contain them: `EX-D09` refuses to pack until they are gone.

Fix in this run:

- If the code is still used, move it to a normal path with no leading `_` and update every caller.
- Otherwise delete the file and remove the empty folder.
- Glob again. Zero matches before the control is done.

## 9. Fix, verify, report

1. Fix every finding in the module (only `app/modules/<Target>/`; DACore only under the informed exception).
2. Re-run the greps of steps 1–8b; `php -l` changed files; `php dotapper.php --module=<Target> --test`.
3. Append a dated entry to `app/modules/<Target>/PLAN/11-LOG.md` (what was checked, what was fixed, what is open).
4. Reply in the user's language:
   - one-line verdict;
   - table: area | checked | found | fixed | open;
   - list of fixes with `file:line`;
   - **click list** for the operator (open X → press Y → expect toast Z), because the browser is not used.

Never claim "all OK" for a step whose grep was not run.
