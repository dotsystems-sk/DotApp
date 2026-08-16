# 11 — Auth and Crypto (complete API + error codes)

---

## 1. Login

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

Passwords are hashed internally (bcrypt via `DotApp::generatePasswordHash`). Never store plaintext.

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

**Not implemented in core (build it in your module if needed):** password reset flow, max-login-attempt lockout, role loading.

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

There is no HMAC facade; use PHP `hash_hmac()` if you need signatures.

---

## 9. Sessions

See [20-CACHE-LOGGER-SESSION.md](20-CACHE-LOGGER-SESSION.md). Use `DSM::use('YourModule')`, never raw `$_SESSION`. Auth state lives in the reserved key `_request.auth`.

---

## 10. Security checklist

1. Replace `app.c_enc_key`, `app.rm_key`, `app.rmrcm_key` and set a unique `app.name` ([10](10-CONFIG-AND-SECRETS.md)).
2. `Config::session('secure', true)` on HTTPS; keep `httponly` + `SameSite=Strict`.
3. Check `Auth::login()` for `false` before array access.
4. Use `Auth::can()` on every protected route/action.
5. Always pass `$key2` to Crypto and check `=== false`.
6. Understand that remember-me bypasses 2FA before enabling `rm_autologin`.
7. Implement password reset and login throttling yourself (`Limiter` + your own table).
