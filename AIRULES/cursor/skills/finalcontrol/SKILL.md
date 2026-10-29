---
name: finalcontrol
description: Final control of one DotApp module before hand-off. Walks every button and control, checks padding and spacing between blocks, JS listeners, backend endpoints, rights and privilege escalation, Why/About comments and runtime schema probes, then fixes every failure. Use when the user types /finalcontrol, asks for a final control, záverečná kontrola, finálna kontrola, or a pre-release check of a module.
disable-model-invocation: true
---

# Final control (`/finalcontrol <Module>`)

Canonical law: `AIRULES/00-AGENT-CONTRACT.md` §2u. This is a **fix run**, not a report-only audit: every failure found is fixed in the same run, then re-checked.

This skill is the framework-only procedure. It does not call DACore, Notiflix, or an admin shell. Feedback, confirms, and settings switches are the module’s own UI.

## 0. Scope

- Target = the module the user named (`/finalcontrol Shop`). No name → the module of the current task; still unclear → **ASK**.
- Read scope: `app/modules/<Target>/`, `app/parts/` read-only, `AIRULES/`. Host `AIRULES/` if the target is a pack for a named host.
- Read `app/modules/<Target>/PLAN/` first when it exists.
- **No browser** (`25-no-unsolicited-browser.mdc`). Everything is checked in source.

Copy this checklist into the reply and tick it:

```
Final control <Module>:
- [ ] 1. Control inventory (every button / link-action / form / row action)
- [ ] 2. Layout: padding on all sides, space below last buttons, spacing between blocks, mobile
- [ ] 3. JS: every control has a working listener / form / GET route
- [ ] 4. Backend: every JS URL hits a registered route + existing method, HTTP 200 + status
- [ ] 5. Rights: login gate, rights check per private route, write right ≠ view right, ownership, no escalation
- [ ] 6. Input / IDs / CRC / SQL (finish gate rows)
- [ ] 7. Comments: CRCchecking — PHPDoc, Why / About / Section
- [ ] 8. Runtime schema probes = zero (AIRULES/07 §0a)
- [ ] 9. Fix all, re-grep, tests, PLAN log, click list
```

## 1. Control inventory

Grep `views/**/*.view.php`, `views/**/*.layout.php`, module `assets/**/*.js`, and any HTML built in PHP:

`<button`, `class="[^"]*\bbtn\b`, `type="submit"`, `<fo-rm`, `{{ formName(`, `data-(action|id|page|token|del|toggle)`, `<a [^>]*href=`, `onclick=`, `$dotapp().load(`, `$dotapp().form(`, `.live(`, `uploadFile(`.

Build one row per control:

| # | Control (label) | view file:line | Parent block | JS handler file:line | URL + method | Route (module.init.php:line) | Controller@method | Rights | Result |

A control with no row in every column is a **finding**, not a skip.

## 2. Layout (`ux-ui-layout.mdc`, `AIRULES/00` §2f)

For each control and its parent, read the markup and the CSS that applies:

- Padding vs parent on **left, right, top, bottom**. Nothing flush against a card, footer, modal, drawer, or page edge.
- Buttons that are the **last content** of a block have space below (`padding-bottom` or an equivalent footer). Zeroing one side is compensated on the other.
- Placement matches sibling blocks. Groups of buttons wrap on mobile, touch target stays usable, no horizontal overflow.
- Consecutive cards, sections, alerts, tables, and pagers have a vertical gap. Label, input, and help text do not touch.
- A bounded choice is a native `<select>` (or the module’s existing picker that shows choices before typing). A growing list has an AJAX pager.

## 3. JS listeners (`AIRULES/09` §3)

Every actionable control has exactly one working path:

- `<fo-rm>` with `{{ formName(handler) }}` **inside** it and a submit button, or
- `$dotapp().live("click", selector, function (el, e) { … })` — first argument is the **element** (`function (e)` with `e.currentTarget` = dead button), or
- a plain `<a href>` to a **registered GET** route.

Check per handler: the selector matches the markup; overlay starts and is removed on success **and** error; success patches `reply.html` and shows the module’s own status (no `location.reload()`); error shows `reply.message`; delete uses a graphical confirm (no `alert` / `window.confirm`); user strings use `.text()`. Buttons with a handler but no markup, and markup with no handler, are both findings.

## 4. Backend endpoints (`24-dotapp-ajax-and-class-bugs.mdc`)

For every URL found in JS or forms:

- A matching `Router::get/post` exists in `module.init.php` and points to an existing `Controller@method!`.
- The method unwraps nested `data`, reads persist values with `data(true)`, and answers **HTTP 200 + `status` 0|1 + `message`** for product outcomes.
- `crcCheck()` runs **once** per POST (prefix middleware **or** action, never both, never on GET or `upload()`).
- Every `catch` / `execute()` `$err` reports through the module catch helper.
- One broken handler → grep and fix every sibling.

## 5. Rights and privilege (`AIRULES/11` §11, `AIRULES/24`)

For **every** private route:

- The page lives under the module prefix, behind the module login gate, and is registered only inside `Auth::isLogged() === true`.
- An explicit rights check runs (`Auth::can` or the module’s own check) with a named right. No private route without one.
- A POST that writes or deletes needs the **write/delete** right, not only the page’s view right.
- IDs from the browser are encrypted with a unique `$key2`, `Crypto::decrypt` is checked `=== false`, and the SQL scopes by owner (`WHERE id = :id AND owner = :me`).
- No acting on a more privileged account. No rights, role, owner, or price taken from the request body. Sort and column names come from a whitelist.
- JS-only gates are re-checked in PHP.
- Public POST has `throttle()`. Public pages do not leak private data.

## 6. Finish-gate rows

Run the table in `10-dotapp-finish-gate.mdc` on the module. Grep, do not imagine.

## 7. Comments (`AIRULES/25` §7)

- Every public Controller / Middleware method: PHPDoc **first line** `CRCchecking — <prefix|this action|none>`, then a purpose sentence, then `@param`/`@return` with meaning.
- Every other public method: a purpose sentence.
- Every non-obvious logical step: `// Why:`.
- Every page action / route block: `// About:` and `// Section:`.
- Remove narration, dead code, and bare `TODO`.

## 8. Runtime schema probes

Outside `Installation.php`: zero `SHOW TABLES|COLUMNS|INDEX`, `information_schema`, `DESCRIBE`, `tableExists`, `columnExists`, `indexExists`, `ensureTable`, executed `CREATE`/`ALTER`. See `AIRULES/07` §0a.

## 9. Fix, verify, report

1. Fix every finding in `app/modules/<Target>/` only.
2. Re-run the greps of steps 1–8; `php -l` changed files; `php dotapper.php --module=<Target> --test`.
3. Append a dated entry to `app/modules/<Target>/PLAN/11-LOG.md`.
4. Reply in the user’s language: one-line verdict; table area | checked | found | fixed | open; fixes with `file:line`; a **click list** for the operator.

Never claim "all OK" for a step whose grep was not run.
