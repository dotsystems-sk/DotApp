# 24 — Attack vectors (hard law)

Every row below is a **law**: that attack **MUST NOT** be possible in code you ship. This file is the **catalogue** (what must never happen + the DotApp / DACore mechanism). The long *how* stays in the linked doc — do not duplicate it here.

**How to use it (cheap):** open only the sections for the surface you touch. Writing SQL → §1. Login / 2FA / rights → §3, §4. A view or JSON → §2, §5. Upload → §6. Public endpoint → §7. Then run the **threat pass** in §11 on your diff.

---

## 0. Four root laws

1. **Everything the client can influence is hostile.** Body, query, cookies, headers, filenames, JSON, `data-*` — **and** any value that came from a client earlier and now sits in the DB, cache, or session (second-order input). Cap length, cast the type, whitelist the allowed set **before** use.
2. **The server decides.** Hidden fields, disabled buttons, an omitted menu item, JS validation, a Notiflix confirm = **UX**. Every gate **MUST** also exist in PHP. Removing the frontend **MUST** still fail ([08](08-FORMS-AND-SECURITY.md), [11](11-AUTH-AND-CRYPTO.md) §11).
3. **Fix it in your module, never in DACore.** A vulnerability in **your** code is fixed in `app/modules/<YourModule>/`. **MUST NOT** patch, extend, or add files under `app/modules/DACore/`, and **MUST NOT** write directly to `dacore_*` / `users_rights*` tables ([00](00-AGENT-CONTRACT.md) §1).
4. **Not listed is not allowed.** If a vector is missing from this file, apply the nearest row **and say so in chat**. “Probably fine” is a bug. Never weaken a rule to make a feature work — **ask** instead.

---

## 1. Injection into interpreters (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| SQL injection | QueryBuilder bindings only. Never concatenate request data into SQL | [06](06-DATABASE.md) |
| `raw()` binding mismatch | In `$qb->raw()` every `?` is a placeholder — **including inside comments** (`COMMENT 'SMS?'` throws). Never mix `?` and `:named` | [06](06-DATABASE.md) |
| Second-order SQLi | A value read back from DB / cache / session is still input — bind it too | [06](06-DATABASE.md) |
| `LIKE` wildcard abuse | Escape `%` and `_` in the user string, cap its length, then bind | [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 |
| Order-by / column injection | Sort column and direction from a **whitelist array** — never the raw request value | [06](06-DATABASE.md) |
| Stored / reflected XSS | `{{ var: $x }}` compiles to `echo` with **no auto-escaping**. Escape in PHP (`htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`) before passing it to the view. `protect()` is a guard, not the escape | [05](05-VIEWS-TEMPLATES-ASSETS.md) §1, [19](19-VALIDATION-AND-INPUT.md) |
| Rich-text / HTML field | Accept HTML only where the product needs it, only from a user with the mutate right, sanitised to a tag/attribute whitelist. `data(true)` for the original | [19](19-VALIDATION-AND-INPUT.md) |
| DOM XSS | User text goes in with `.text()`. Never `.html()` a request/DB string. Search highlight escapes first, then wraps the match | [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 |
| Template injection | User input is **data**, never template source. Never compile a string that contains `{{ }}` from input | [05](05-VIEWS-TEMPLATES-ASSETS.md) |
| PHP code execution | No `eval`, no `$$var`, no `new $class`, no `call_user_func` with a callable named by the request. Method/route names come from a whitelist | — |
| Command injection | No `exec` / `shell_exec` / `system` / `passthru` / `proc_open` / backticks with request data. Unavoidable → fixed binary + `escapeshellarg()` | — |
| Object injection | Never `unserialize()` a request, cookie, or DB blob. Use `json_decode($s, true)` | [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) |
| XXE / XML bomb | Parse untrusted XML with `LIBXML_NONET`, reject `DOCTYPE` / external entities, cap size | — |
| ReDoS | No nested-quantifier regex on user input; cap the length first | [19](19-VALIDATION-AND-INPUT.md) |

---

## 2. Injection into channels (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| Header / CRLF injection | No `\r` `\n` from input in `header()`, `Response::redirect`, cookies, or mail headers | [04](04-CONTROLLERS-AND-RESPONSES.md) |
| Open redirect | Redirect targets from a fixed allowlist or your own `{prefixUrl}` path. Never `Response::redirect($request->query(true)['next'])` | [03](03-MODULES-AND-ROUTING.md) |
| Mail header injection | Validate the address (`Validator`); recipient / subject / from are never multi-line input; recipients come from **your** DB row, not the post | [21](21-EMAIL-SMS-QR.md) |
| SSRF | A URL from input never goes into `HttpHelper::request` unmodified — host allowlist, `https` only, no internal / loopback / metadata IPs, no `file://` | [19](19-VALIDATION-AND-INPUT.md) §5 |
| Log injection | Strip `\r\n` before `Logger`; never log passwords, tokens, or full payloads | [20](20-CACHE-LOGGER-SESSION.md) |
| CSV / formula injection | On export prefix cells starting with `= + - @` with `'` | — |
| Mass assignment | Insert / update an **explicit column whitelist**. Never spread `$request->data()` into the query — a posted `is_admin`, `user_id`, `price`, `right`, or `status` must be ignored | [06](06-DATABASE.md) |

---

## 3. Identity, session, brute force (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| CSRF | `crcCheck()` **exactly once** per state-changing POST — the API prefix (`#DACore:AuthTest@LoginAndCRC!` / `@CRC!`) **XOR** the action, never both | [08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md) |
| State change over GET | Save / toggle / delete **MUST** be POST behind CRC. A GET that mutates is a bug | [08](08-FORMS-AND-SECURITY.md) |
| Session fixation | Rotate the token after login and after a privilege change — `Auth::refreshToken()` | [11](11-AUTH-AND-CRYPTO.md) §2 |
| Session hijack | `Config::session('secure', true)` on HTTPS, keep `httponly` + `SameSite=Strict`; state via `DSM` — never `$_SESSION` | [11](11-AUTH-AND-CRYPTO.md) §10, [20](20-CACHE-LOGGER-SESSION.md) |
| Password brute force | Public login / reset **MUST** have `->throttle([...])` or `Limiter` + your own failure counter | [19](19-VALIDATION-AND-INPUT.md) §6 |
| 2FA / OTP / reset-code brute force | A wrong code increments the **same** counter as a wrong password; refuse while locked **before** verifying | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Timing / loose compare | Secrets compared with `hash_equals()`, never `==`. Ids cast `(int)` after decrypt, compared with `===` | [11](11-AUTH-AND-CRYPTO.md) §11 |
| User enumeration | Wrong user and wrong password give the **same** message (you still **MUST** show a failure). Same for reset: “if the address exists we sent a link” | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Reset-token abuse | `random_bytes()` (never `rand`/`uniqid`/`time()`), stored hashed, single use, short TTL, bound to that user, invalidated on password change | [11](11-AUTH-AND-CRYPTO.md) |
| Remember-me 2FA skip | The RM path skips the 2FA stage — **MUST NOT** enable `rm_autologin` for the admin or any 2FA-protected area | [11](11-AUTH-AND-CRYPTO.md) §6 |
| Operator without 2FA | DACore operators **MUST** keep 2FA on; dangerous admin actions **MUST** step-up 2FA re-verified in PHP | [32](32-DACORE-RIGHTS.md) §6 |
| Auth bypass by route | Login-only handlers registered **only** inside `if (Auth::isLogged() === true)`; admin HTML behind `{prefixUrl}/{Module}/…` + `Gate@login` | [03](03-MODULES-AND-ROUTING.md) |
| Dead route / 500 | Every registered URL hits an existing handler and returns a `Response`; feature off → redirect or 404 | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Logout that does not log out | Use the project’s signed logout URL. A token-less GET redirect leaves the session alive | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Credentials in the URL | No password, token, or code in a query string (they land in logs, history, `Referer`) | — |

---

## 4. Access control (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| IDOR / swapped ciphertext | Ids to the browser are `{{ enc(Shop.item.id): $id }}` with a **unique `$key2`**; decrypt `=== false` → reject; **and** the query carries the owner predicate (`WHERE id = :id AND user_id = :uid`) | [11](11-AUTH-AND-CRYPTO.md) §8, §11 |
| Wrong guard | Rights checked with **your own** `#YourModule:Rights@check!` — `#DACore:AuthTest@check!` **ignores the passed rights** | [32](32-DACORE-RIGHTS.md) |
| Missing function-level check | `Auth::can()` on **every** action, not only where the menu or the view hides it | [11](11-AUTH-AND-CRYPTO.md) §3, [32](32-DACORE-RIGHTS.md) |
| Privilege escalation | **MUST NOT** grant a right/group the actor lacks, mutate a more privileged operator, or promote a target into a tier the actor is not. Elevated targets and elevated groups = `dotapp.root` | [11](11-AUTH-AND-CRYPTO.md) §11, [32](32-DACORE-RIGHTS.md) §5 |
| Rights written behind DACore’s back | **MUST NOT** `INSERT`/`UPDATE` `users_rights*` / `dacore_*` yourself — go through what DACore exposes | [00](00-AGENT-CONTRACT.md) §1, [32](32-DACORE-RIGHTS.md) |
| Secrets behind a read right | TOTP secret, otpauth/QR, recovery codes, hashes, API keys, reset tokens **MUST NOT** be loaded into a view/JSON unless the actor may **mutate** that factor | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Account takeover via own profile | Changing **own** password verifies the **current** password in PHP (`data(true)`); another user’s password / e-mail / 2FA is the elevated mutate | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Tampered business fields | Price, discount, quantity limits, owner, role, state come from **your** DB / config — never from the post, even encrypted | [08](08-FORMS-AND-SECURITY.md) |
| Client-side authorization | A hidden menu item or disabled control is UX. The endpoint refuses on its own | [08](08-FORMS-AND-SECURITY.md) |
| Workflow skip | Multi-step flows re-verify every earlier step server-side (paid before ship, verified before publish) — never trust a “step 3” flag from the client | [08](08-FORMS-AND-SECURITY.md) |
| Double submit / race | Re-read the state in the same persist (`exists()` / status check) before the write; unique index where the DB can enforce it | [06](06-DATABASE.md) |

---

## 5. Output to the browser (**MUST**)

There is **no `Response::headers()`**. Set headers with PHP `header()` **before** any output, or ask the user to add them at the web-server level (`.htaccess` is ASK-first).

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| Clickjacking | `X-Frame-Options: SAMEORIGIN` (or CSP `frame-ancestors`) on admin / form pages | — |
| MIME sniffing | `X-Content-Type-Options: nosniff` | — |
| Referrer leak | `Referrer-Policy: strict-origin-when-cross-origin` (tokens never in the URL anyway) | — |
| CORS over-share | Never `Access-Control-Allow-Origin: *` together with credentials. Allowlist the origin or leave CORS off | — |
| Cached private page | Admin HTML / JSON: `Cache-Control: no-store` | — |
| Tabnabbing | `target="_blank"` always with `rel="noopener noreferrer"` | — |
| `postMessage` trust | Check `event.origin` against your own origin before acting | — |
| Secrets in the client | No API keys, tokens, or connection strings in assets, inline JS, or `localStorage`; keys stay in `app/config.php` | [10](10-CONFIG-AND-SECRETS.md) |
| Data over-fetch in JSON | `select` only the columns the screen needs — never dump a user/order row (hashes, tokens, rights, internal flags) into `ajaxReply` | [06](06-DATABASE.md) |

---

## 6. Files and paths (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| Upload → RCE | Reject `.php` and every executable/script — **extension** (incl. `.jpg.php`, `.php.jpg`), **`finfo` MIME from the bytes**, magic/headers. `$file['type']` and FE `accept=` are not checks | [09](09-DOTAPP-JS-AND-BRIDGE.md) |
| Executable upload dir | Store outside the webroot or in a non-executing directory; serve through PHP with a fixed content type | [09](09-DOTAPP-JS-AND-BRIDGE.md) |
| Path traversal / LFI | Never concatenate input into a path. `basename()` + extension whitelist + your own generated name; verify `realpath()` still starts with your base dir | — |
| Zip slip | Validate every archive entry path before extracting; reject `..` and absolute paths | — |
| Filename XSS | Escape the stored filename on output like any other user string | [05](05-VIEWS-TEMPLATES-ASSETS.md) |
| Upload flooding / bombs | Cap file size, file count, and image dimensions; reject early | [09](09-DOTAPP-JS-AND-BRIDGE.md) |
| Wrong gate on upload | Upload endpoints use `$request->upload()` — **MUST NOT** `crcCheck()` there, but the route still needs its login gate | [09](09-DOTAPP-JS-AND-BRIDGE.md) |

---

## 7. Abuse, flooding, cost (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| Bots on public endpoints | Register / login / contact / comment / reset / public create: **MUST warn the user in chat** that CAPTCHA or an equivalent would reduce abuse. Do not add one unless asked. Shipping silently is a bug | [11](11-AUTH-AND-CRYPTO.md) §11 |
| No rate limit | Public POST gets `->throttle(['per_minute' => …, 'per_hour' => …])` (+ `->limitExceeded()` if you want your own reply) or `Limiter` | [03](03-MODULES-AND-ROUTING.md), [19](19-VALIDATION-AND-INPUT.md) §6 |
| Mail / SMS flooding | Throttle per IP **and** per identity; queue or cap; never send in a loop over request data | [21](21-EMAIL-SMS-QR.md) |
| Notification flooding | `DACore:Notifications@push` fires **on the event** — not on every request, not from `Installation.php` | [37](37-DACORE-NOTIFICATIONS.md) |
| Unbounded list | `paginate()` + a cap on page size; never `->all()` on an accumulating table | [06](06-DATABASE.md) |
| Expensive query per hit | `exists()` / `COUNT(*)` / `limit(1)` / needed columns / one `join`; no N+1 in `foreach` | [06](06-DATABASE.md) |
| Bridge abuse | `rateLimit(seconds,count)`, `oneTimeUse`, `expireAt`, `regenerateId` on bridge buttons | [09](09-DOTAPP-JS-AND-BRIDGE.md) |
| Paid-API drain (AI, SMS, mail) | Login + rights + rate limit before any paid call; cap payload size | [22](22-AI-SEARCH-MCP.md), [34](34-DACORE-AI-TOOLS.md) |

---

## 8. Leaks (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| Stack trace / SQL error to the user | `try/catch (\Throwable)` → log it, return a generic `message`. **Never** `$e->getMessage()` in the reply, never an empty `catch` | [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) |
| Debug leftovers | No `var_dump` / `print_r` / `echo $sql` / `console.log(payload)` in shipped code | [23](23-DEBUG-PLAYBOOK.md) |
| Secrets in the repo | Keys and credentials only in `app/config.php` (with fallbacks); never hardcoded in a module, comment, or example | [10](10-CONFIG-AND-SECRETS.md) |
| PII / secrets in logs | Never log passwords, tokens, codes, card data, or whole request bodies | [20](20-CACHE-LOGGER-SESSION.md) |
| Existence oracle | 403 vs 404 vs a different wording **MUST NOT** reveal that someone else’s record exists — same reply for “not yours” and “not there” | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Secrets on the event bus | `Events::trigger` payloads are ids / counts / flags — **never** passwords, TOTP/OTP, CRC, tokens, rights blobs, or request bodies. Same leak law as logs. Document names in `.hooks`, not secrets | [41](41-MODULE-HOOKS.md) |
| Debug / admin endpoint left in | No “temporary” route without a gate; delete it before you say done | [03](03-MODULES-AND-ROUTING.md) |

---

## 9. Crypto and tokens (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| Home-made crypto | Use the `Crypto` facade (AES-256-CBC) / `password_*` via `Auth`. No md5/sha1 for passwords, no custom cipher, no base64 as “encryption” | [11](11-AUTH-AND-CRYPTO.md) §8 |
| Ciphertext treated as permission | Encrypting an id only stops guessing; `Auth::can` + owner predicate are **still** required | [11](11-AUTH-AND-CRYPTO.md) §11 |
| Context replay | Always pass a meaningful, **unique** `$key2` (`'Shop.item.id'`) so a ciphertext from one field cannot be reused in another | [11](11-AUTH-AND-CRYPTO.md) §8 |
| Persisted ciphertext | The per-session key participates — **MUST NOT** store `Crypto::encrypt()` output in the DB expecting a later decrypt | [11](11-AUTH-AND-CRYPTO.md) §8 |
| Weak randomness | Tokens / codes / filenames from `random_bytes()` / `random_int()` — never `rand`, `mt_rand`, `uniqid`, `time()` | — |
| Missing integrity | Signing something yourself: `hash_hmac()` + `hash_equals()` (there is no HMAC facade) | [11](11-AUTH-AND-CRYPTO.md) §8 |
| Default keys shipped | Generate real `app.c_enc_key`, `rm_key`, `rmrcm_key`, unique `app.name` on a new app | [10](10-CONFIG-AND-SECRETS.md) |

---

## 10. Third party, AI, webhooks (**MUST**)

| Attack | Law (**MUST**) | Where |
|--------|----------------|-------|
| CDN / remote script | **Search DACore first** — it already ships many libraries. Otherwise self-host in **your** module assets and pin the version. No `<script src="https://…">` in the admin | [33](33-DACORE-PAGES-AND-UI.md) |
| Copy-pasted snippet | Rewrite anything with `eval`, `innerHTML` of input, `$.ajax`, or a hardcoded key before it lands in the module | [14](14-ANTIPATTERNS.md) |
| Prompt injection | Text from users / DB / the web is **data** for the model. Its output **MUST NOT** be executed, put in SQL, or printed as HTML unescaped; validate it against your own whitelist | [22](22-AI-SEARCH-MCP.md) |
| AI acting beyond the actor | An AI tool that writes **MUST** run the same `Auth::can` + owner scope as the manual path, and `ui_events` / `DACore.AI.UIEvent` only on the matching page | [34](34-DACORE-AI-TOOLS.md) §5 |
| Unverified webhook | Verify the signature with `hash_hmac` + `hash_equals`, check a timestamp/replay window, then process; never trust a payload id alone | [11](11-AUTH-AND-CRYPTO.md) §8 |
| Envelope not checked | `HttpHelper::request` / `FastSearch::*` → check `['success']` before touching `['data']` | [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) |

---

## 11. Threat pass (**MUST** — run it, do not imagine it)

Part of the finish gate ([00](00-AGENT-CONTRACT.md) §2c). Grep **your module + the diff**, not the whole project. Any hit that is not justified = fix **now**.

| # | Grep / look at | Fail if |
|---|----------------|---------|
| 1 | `crcCheck(`, `AuthTest` | not exactly once on that POST (prefix **and** action); on a GET; on an upload |
| 2 | `raw(`, `where(`, `->q(` | request data concatenated into SQL; `?` that is not a binding; sort column from the request |
| 3 | `{{ var:`, `.html(`, `innerHTML` | a user/DB string printed or injected without PHP escaping |
| 4 | `data(`, `query(` | password/HTML/hash without `true`; a posted `price` / `right` / `user_id` trusted; no length cap |
| 5 | `enc(`, `decrypt(` | plain id in HTML/JSON; reused `$key2`; `false` not rejected |
| 6 | `Auth::can`, `Rights@check`, `where('user_id'` | action without a rights check; `#DACore:AuthTest@check!` used for rights; `WHERE id` alone after decrypt; handler outside `Auth::isLogged()` |
| 7 | `header(`, `redirect(`, `HttpHelper::request` | input in a header; redirect target from the request; URL from the request |
| 8 | `exec`, `eval`, `unserialize`, `include $`, `file_get_contents($` | present at all with request data |
| 9 | `upload(`, `move_uploaded_file` | extension/MIME/header check missing; executable directory |
| 10 | `throttle(`, `Limiter`, `paginate(` | public POST with no limit; list with `->all()` |
| 11 | `getMessage()`, `var_dump`, `print_r`, `console.log` | leaked to the user or left in the diff |
| 12 | the diff + the route list | anything under `app/modules/DACore/`; a direct write to `dacore_*` / `users_rights*`; a public `noauth` endpoint shipped **without the bot warning in chat**; a registered URL with no handler |

**Pass →** continue or say done. **Fail →** fix now. Only then claim the work is finished.

Also fail the chunk if `Events::trigger(` carries a secret / request body, or if `.hooks` sits under `assets/` ([41](41-MODULE-HOOKS.md) — same leak law as §8).
