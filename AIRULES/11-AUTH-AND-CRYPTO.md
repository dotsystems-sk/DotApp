# 11 — Auth and Crypto (complete API + error codes)

---

## 1. Login

**MUST** read email/password from `$request->data(true)` (original). `$request->data()` is the **protected** copy — `)`, `=`, `%`, `&`, `'` etc. are rewritten, so `Auth::login` / `Auth::createUser` hash a **different** password. Same for the installer admin form. Canonical: [19](19-VALIDATION-AND-INPUT.md).

**MUST** handle **every** outcome and **show** it: `crcCheck` fail, `form()` `null`/`false`, `Auth::login === false` (malformed), and `$result['error']` 1–5 / 99. A 400 “Bad request” with no toast is incomplete. Sample: [EX-14](examples/EX-14-auth-and-2fa.md).

```php
use Dotsystems\App\Parts\Auth;

$result = Auth::login([
    'email' => $email,          // OR 'username' => $u — never both
    'password' => $password,    // OR 'passwordHash'
    'stage' => 0,
], $rememberMe = false);

if ($result === false) {
    // MALFORMED INPUT (missing password, both email+username, wrong stage)
    return ['status' => 0, 'message' => 'Bad request'];
}

if ($result['logged'] === true) {
    if (Auth::loggedStage() === 2) {
        // 2FA required — redirect to your 2FA page
    } else {
        // fully logged in
    }
} else {
    // $result['error'] / $result['error_txt']
}
```

**`login()` returns `false` on malformed input** — check that before touching array keys.

### `login()` error codes

| `error` | Meaning |
|---------|---------|
| 0 | no error |
| 1 | IP blocked by the per-user firewall |
| 2 | wrong password |
| 3 | user not found |
| 4 | failed loading the rights list |
| 5 | both `email` **and** `username` supplied |
| 99 | database error |

Return keys: `logged` (bool), `error` (int), `error_txt` (string\|null).

---

## 2. Session state and identity

| Call | Returns |
|------|---------|
| `Auth::isLogged()` | `bool` (stage 1 **and** logged) |
| `Auth::loggedStage()` | `0` none, `1` full, `2` awaiting 2FA |
| `Auth::userId()` | `int\|null` |
| `Auth::username()` | `string\|null` |
| `Auth::attributes()` | `array` — the full user row |
| `Auth::getAuthData()` | `array` (whole auth blob) |
| `Auth::permissions()` | `array` of `"Module.right"` strings |
| `Auth::token()` | `string\|null` |
| `Auth::lastLogin()` / `Auth::lastActivity()` | `int\|null` |
| `Auth::setAttribute($k,$v)` / `getAttribute($k)` | void / `mixed\|null` |
| `Auth::updateActivity()` | void |
| `Auth::refreshToken()` | new `string` |
| `Auth::logout($clearSessionCookie = false)` | void |
| `Auth::lock(array $fields = [])` | `bool` — `false` if already locked; **irreversible** |
| `Auth::isLocked()` | `bool` |

**`Auth::logged()` does not exist** even though the facade lists it — calling it throws `\BadMethodCallException`. Use `isLogged()`.

---

## 3. Permissions

```php
if (!Auth::can(['dotapp.root', 'Shop.admin'])) {          // OR by default
    return new Response(403, 'Forbidden');
}
if (!Auth::can(['Shop.read', 'Shop.write'], \Dotsystems\App\Parts\AuthObj::$And)) {
    // requires BOTH
}
Auth::permissionRefresh();   // bool; false if not logged / DB failure
```

Permission strings are built as `"{module}.{rightname}"` from the rights tables. A non-string/non-array argument returns `false`.

**`Auth::hasRole()` / `Auth::roles()` are effectively unusable in core** — `login()` never populates roles (always `[]`). Use permissions.

---

## 4. Creating users

```php
$r = Auth::createUser($username, $password, $email, ['note' => 'imported']);
// $r['error']: 0 = ok, 1 = duplicate, 99 = DB error
// $r['error_txt'], $r['error_data'] (99 only)
```

Throws `\Exception('Invalid email address.')` if `$email` fails `FILTER_VALIDATE_EMAIL`, so wrap it:

```php
try {
    $r = Auth::createUser($u, $p, $email);
} catch (\Throwable $e) {
    return Response::json(['status' => 0, 'message' => 'Invalid email'], 422);
}
```

Passwords are hashed internally (bcrypt via `DotApp::generatePasswordHash`). Never store plaintext. The `$password` argument **MUST** be the original string (`$request->data(true)`), not the protected copy.

---

## 5. Two-factor authentication

| Type | Enable column | Secret / storage | Notes |
|------|---------------|------------------|-------|
| App (TOTP) | `users.tfa_auth = 1` | `users.tfa_auth_secret` (Base32) | full support |
| SMS | `users.tfa_sms = 1` | `tfa_sms_number*` columns | code generated, **core does not send it** |
| Email | `users.tfa_email = 1` | — | code generated, **core does not send it** |

```php
$r = Auth::confirmTwoFactor(['tfa' => $code]);          // or 'tfa_sms' / 'tfa_email'
// $r['confirmed'] bool, $r['error'] int, $r['error_txt']
```

| `error` | Meaning |
|---------|---------|
| 0 | confirmed → stage becomes 1 |
| 1 | not in stage 2 |
| 2 | invalid TOTP |
| 3 | invalid SMS code |
| 4 | invalid email code |
| 5 | no recognised method in the argument |

Enrolment helpers:

```php
use Dotsystems\App\Parts\TOTP;
$secret = TOTP::newSecret();                     // throws if length < 16
$uri    = TOTP::otpauth($userEmail, $secret);    // for a QR code
$code   = TOTP::generate($secret);               // for verification/testing
```

`Config::totp(...)`: `issuer` (`DotApp`), `algorithm` (`SHA256`), `digits` (6), `period` (30). TOTP methods throw `\InvalidArgumentException` / `\RuntimeException` on bad input.

You must send SMS/email codes yourself (see [21-EMAIL-SMS-QR.md](21-EMAIL-SMS-QR.md)).

**Frontend (**MUST**):** code boxes use `$dotapp(".two-fa-inputs input").twoFactor(...)` — already in `dotapp.js` (auto-advance, paste, auto-submit). **MUST NOT** invent a custom OTP widget. See [09](09-DOTAPP-JS-AND-BRIDGE.md) §3 “Built-in 2FA fields” and [EX-14](examples/EX-14-auth-and-2fa.md).

---

## 6. Remember-me

```php
Auth::login($data, true);   // sets the RM token + locks auth data
Auth::autoLogin();          // bool; call when not logged in
```

Config: `session.rm_always_use`, `session.rm_autologin`, `session.rm_lifetime` (30 days). With `rm_autologin = true`, `DotApp::run()` attempts auto-login automatically.

**Security note:** the remember-me path logs in with `fromRM = true`, which **skips the 2FA stage**. Do not enable `rm_autologin` for high-security areas that rely on 2FA. Requires `Config::get('app','name')` to be set or it throws `\RuntimeException`.

---

## 7. Tables used by core Auth

| Table | Access |
|-------|--------|
| `{prefix}users` | read + write (`last_logged_at`, createUser) |
| `{prefix}users_rights`, `{prefix}users_rights_list` | read (permissions) |
| `{prefix}users_firewall` | read (only when the user has `tfa_firewall = 1`) |
| `{prefix}users_rmtokens` | read + write |
| `{prefix}users_roles*` | **not used by core** |
| `{prefix}users_password_resets` | table exists, **no flow implemented** |
| `{prefix}users_sessions` | used only by the DB session driver |
| `{prefix}users_url_firewall` | not used by core |

**Not implemented in core (build it in your module if needed):** password reset flow, max-login-attempt lockout, role loading. If you build lockout, **MUST** [§11](#11-privilege-and-record-safety-must).

---

## 8. Crypto

```php
use Dotsystems\App\Parts\Crypto;

$cipher = Crypto::encrypt($plain, 'Shop.item.id');       // string
$plain  = Crypto::decrypt($cipher, 'Shop.item.id');      // string|FALSE

if ($plain === false) {
    return Response::json(['status' => 0, 'message' => 'Invalid token'], 400);
}

$cipherArr = Crypto::encrypta(['id' => 5], 'Shop.payload');
$arr = Crypto::decrypta($cipherArr, 'Shop.payload');     // array|false|null
```

| Method | Returns |
|--------|---------|
| `encrypt($text, $key2 = '')` | `string` (base64 IV+ciphertext) |
| `decrypt($text, $key2 = '')` | `string` or **`false`** |
| `encrypta($array, $key2 = '')` / `encryptArray` | `string` |
| `decrypta($text, $key2 = '')` / `decryptArray` | `array`, or `false`/`null` |

Key composition (AES-256-CBC): `app.c_enc_key` + the per-session `_enc_key` + your `$key2` context. **Always pass a meaningful `$key2`** (e.g. `'Shop.item.id'`) so a ciphertext from one context cannot be replayed in another. A wrong `$key2` yields `false`, not an exception.

Because the session key participates, ciphertext is **not portable across sessions** — do not persist `Crypto::encrypt()` output in the database expecting to decrypt it later in another session.

**URL path (MUST — law):** `encrypt()` returns standard base64 containing `+`, `/`, and `=`. In a path these become `%2B`, `%2F`, and `%3D`; Apache treats `%2F` as a slash and can return Not Found. Keep AES and a unique `$key2`, but seal the ciphertext before using it in an `href`, `{token}` path segment, or `redirectTo`:

```php
$sealed = rtrim(strtr($cipher, '+/', '-_'), '='); // A-Za-z0-9-_
```

When opening, raw-URL-decode, normalize spaces back to `+` for legacy tokens, reverse `-_` to `+/`, restore `=` padding to a multiple of four, then decrypt with the same `$key2`. **MUST** accept both sealed and leftover standard-base64 tokens during migration. `{{ enc(...) }}` remains standard base64 and **MUST NOT** be placed directly in a URL path. Hidden fields, `data-*`, and POST bodies may keep it. Put the sealing/opening helper in the owning module; **MUST NOT** patch core or invent another cipher.

There is no HMAC facade; use PHP `hash_hmac()` if you need signatures.

---

## 9. Sessions

See [20-CACHE-LOGGER-SESSION.md](20-CACHE-LOGGER-SESSION.md). **MUST** `DSM::use('Shop')`. **MUST NOT** raw `$_SESSION` or `session_start()`. Auth state lives in the reserved key `_request.auth`.

---

## 10. Security checklist

1. Replace `app.c_enc_key`, `app.rm_key`, `app.rmrcm_key` and set a unique `app.name` ([10](10-CONFIG-AND-SECRETS.md)).
2. `Config::session('secure', true)` on HTTPS; keep `httponly` + `SameSite=Strict`.
3. Check `Auth::login()` for `false` before array access.
4. Use `Auth::can()` on every protected route/action.
5. Always pass `$key2` to Crypto and check `=== false`.
6. Understand that remember-me bypasses 2FA before enabling `rm_autologin`.
7. Implement password reset and login throttling yourself (`Limiter` + your own table).
8. 2FA code boxes: `$dotapp().twoFactor` — do not invent an OTP widget ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3).
9. App session state: **`DSM::use('Shop')`** — never `$_SESSION` / `session_start()` ([20](20-CACHE-LOGGER-SESSION.md)).
10. Persist in **PHP**: re-check 2FA / rights / validation. A frontend overlay is UX only ([08](08-FORMS-AND-SECURITY.md)).
11. Privilege, secrets, lockout, SQL ownership, own-password proof, live routes: [§11](#11-privilege-and-record-safety-must).
12. Every other known vector (injection, XSS, SSRF, open redirect, mass assignment, enumeration, headers, upload, rate limit, weak randomness): [24-ATTACK-VECTORS.md](24-ATTACK-VECTORS.md) — law, plus the §11 threat pass on your diff.

---

## 11. Privilege and record safety (**MUST**)

General module law — not one admin screen. Encryption of IDs is **not** enough ([08](08-FORMS-AND-SECURITY.md)).

### Secrets vs read rights

**MUST NOT** put TOTP secrets, otpauth/QR payloads, backup/recovery codes, password hashes, API keys, or reset tokens into a view, JSON, or layout var unless the actor **may mutate** that factor (enrol, rotate, reveal). A **read** right on the page is not that permission. Hiding the value in HTML is **not** enough — do not load it in PHP. The tab/link to that screen **MUST** be omitted unless they have the mutate right.

### Guessable secrets share lockout

Core has **no** lockout. If **your** module has login throttling / `recordFailure` / IP lock: a wrong **2FA / OTP / reset / recovery** code **MUST** increment the **same** counter as a wrong password. **MUST** refuse while locked **before** verifying the code. A 6-digit guess with no limit is a bug.

Compare secrets **you** store in the module with `hash_equals()` (not `==`). Do not patch `app/parts/` because core TOTP uses `==`.

### No privilege escalation

The actor **MUST NOT**:

- grant a right, group, or role they **do not** hold;
- mutate an account / role / group that is **more privileged** than they are (admin, root, “elevated”);
- turn a target **into** that higher tier unless they already are that tier.

Own **ordinary** profile (display name, own password with current-password proof) is allowed. Someone else’s password, 2FA, rights, or group membership **MUST** pass a mutate check on **that target** — not merely “logged in” or a blanket `Shop.users.edit`. The product’s highest role (`dotapp.root` on DACore; your module’s equivalent otherwise) is who may touch elevated targets.

### Record scope in SQL

After decrypt, **MUST** load/update/delete with an owner (or permission) predicate in the **query**: `WHERE id = :id AND user_id = :uid` (or `Auth::can` on **that** row). `WHERE id = :id` alone after decrypt lets a swapped ciphertext steal another user’s chat, file, order, or message.

### Own password vs takeover

Changing **own** password **MUST** verify the **current** password in PHP (`$request->data(true)` + the same verify path as login). Changing password / email / 2FA **for another user** is the elevated mutate in “No privilege escalation”. A hidden current-password field is UX only.

### Logout and dead routes

Logout **MUST** use the project’s signed logout URL (session token). A token-less GET that only redirects **leaves the session**. Every registered route **MUST** hit an existing handler that returns a `Response` (feature off → redirect or 404). **MUST NOT** register a URL whose method is missing (500). Public links **MUST** include `prefixUrl` when the app is mounted under one.

### State-changing POST still needs a gate

**MUST NOT** skip `crcCheck` because a widget cannot sign. Use the module CRC prefix **or** action ([08](08-FORMS-AND-SECURITY.md), [03](03-MODULES-AND-ROUTING.md)). Upload already uses `$request->upload()` instead. A public POST with no CRC and no other documented gate is a bug.

### Public endpoints vs bots (**warn** — not MUST captcha)

When you plan or ship a **public** (`noauth` / anonymous) endpoint that bots can hammer (register, login, contact, comments, password reset, public create), **MUST tell the user in chat** that CAPTCHA or an equivalent (server-side rate limit, proof token) would reduce abuse. **MUST NOT** ship that endpoint silently without the warning. **MUST NOT** add CAPTCHA unless they ask. If they decline, continue. A frontend-only honeypot is not protection unless PHP also checks it.
