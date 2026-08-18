# AIRULES / examples — open ONE file per task

Short, copy-paste-ready patterns. Every example shows **return values** and **error handling**. Open only the file you need (saves context).

| File | Open when |
|------|-----------|
| [EX-01-secure-form-complete.md](EX-01-secure-form-complete.md) | Any user POST form — **preferred security path** (`fo-rm` + `formName` + `dotapp.js`) |
| [EX-02-secure-form-edit-api.md](EX-02-secure-form-edit-api.md) | CRUD/edit form posting to an API route |
| [EX-03-module-scaffold.md](EX-03-module-scaffold.md) | New module, routes, config fallbacks |
| [EX-04-database-crud.md](EX-04-database-crud.md) | Select/insert/update/delete, transactions, pagination, **return shapes** |
| [EX-05-renderer-page.md](EX-05-renderer-page.md) | Page render: view + layout + assets |
| [EX-06-dotapp-js-boot.md](EX-06-dotapp-js-boot.md) | Page JS boot, **block while in flight** (Notiflix or module preloaders), **live DOM** (no reload), **delete confirm** |
| [EX-15-dotapp-js-library.md](EX-15-dotapp-js-library.md) | New `$dotapp().fn(...)` library **or** jQuery → `$dotapp` port |
| [EX-07-bridge.md](EX-07-bridge.md) | Button → PHP via `dotbridge` |
| [EX-08-config-secrets.md](EX-08-config-secrets.md) | New app keys, module setting fallbacks |
| [EX-09-validation-and-errors.md](EX-09-validation-and-errors.md) | Validator / Input groups / JSON error envelopes |
| [EX-10-cache-logger-session.md](EX-10-cache-logger-session.md) | Cache, Logger, DSM sessions |
| [EX-11-email-sms-qr.md](EX-11-email-sms-qr.md) | Email, SMS provider, QR codes |
| [EX-12-ai-search-mcp.md](EX-12-ai-search-mcp.md) | AI calls, FastSearch, MCP tools |
| [EX-13-schema-migrations.md](EX-13-schema-migrations.md) | SchemaBuilder, DDL, introspection |
| [EX-14-auth-and-2fa.md](EX-14-auth-and-2fa.md) | Login, permissions, 2FA (`$dotapp().twoFactor`), user creation |

## DACore admin layer

| File | Open when |
|------|-----------|
| [EX-D01-dacore-module-skeleton.md](EX-D01-dacore-module-skeleton.md) | New admin module: routes, rights middleware, controller |
| [EX-D02-dacore-admin-page.md](EX-D02-dacore-admin-page.md) | Admin page: table, dotgrid form, secure save, page JS |
| [EX-D03-dacore-ai-tool.md](EX-D03-dacore-ai-tool.md) | AI tool: register, write + `ui_events`, `DACore.AI.UIEvent` page refresh |
| [EX-D04-dacore-installer.md](EX-D04-dacore-installer.md) | Installer: tables → rights → menu → AI tools → record |

Theory lives in `0x`–`2x` (framework) and `3x` (DACore). These files are **executable patterns**.

---

## Two rules that apply to every example

### 1. Security: prefer `fo-rm` + `formName`

For any form a user submits in a browser:

1. **Must** load `/assets/dotapp/dotapp.js` (it injects per-session encryption keys).
2. **Must** use `<fo-rm>` + `{{ formName(handlerName) }}`. **MUST** place `formName` **between** `<fo-rm>` and `</fo-rm>` — never after `</fo-rm>`.
3. **Must** validate with `$request->crcCheck()` then `$request->form(...)`.

This binds the handler name, action URL and HTTP method under a per-form key plus CRC and a one-time CSRF token — far stronger than a plain CSRF field. `{{ CSRF }}` alone is not sufficient for the DotApp JS transport.

### 2. Errors: never ignore a return value

DotApp has **four** failure styles — callback pairs, `false`/`null`, envelope arrays, and exceptions. The most dangerous:

- `execute()` **throws** if you omit the error callback.
- `first()` is unsafe on an empty result (RAW warns, ORM crashes) — use `all()` + `[0] ?? null`.
- `Crypto::decrypt()` returns **`false`**.
- `Cache::load()` returns **`null`** on a miss.
- `Email::send()` returns **an array of error strings**, not `false`.
- A missing view/layout renders **`""`** with no exception.
- `Auth::login()` returns **`false`** on malformed input.
- `$request->form()` can return your callback value, `false`, `null`, or throw.

Full contract: [18-ERROR-HANDLING-AND-RETURN-VALUES.md](../18-ERROR-HANDLING-AND-RETURN-VALUES.md).
