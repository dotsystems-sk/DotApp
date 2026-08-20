# EX-14 — Auth, permissions, 2FA

Rules: [11](../11-AUTH-AND-CRYPTO.md), [19](../19-VALIDATION-AND-INPUT.md) (`data(true)` = original).

**MUST** take the password from `$request->data(true)['data']`. `$request->data()` is `protect()`-escaped — `)`, `=`, `%` become a **different** password. **MUST** show `reply.message` on every failure (`crcCheck`, `form()` `null`/`false`, `Auth::login === false`, error codes). Silent 400 is incomplete.

`$dotapp().twoFactor` is **input UX only**. Completing the boxes or covering Save with a modal does **not** authorize. The PHP handler **MUST** verify the code before persist ([08](../08-FORMS-AND-SECURITY.md)).

Privilege, secrets, lockout, SQL ownership, own-password proof: [11](../11-AUTH-AND-CRYPTO.md) §11. A public login/register **MUST** be mentioned to the user as bot-exposed (CAPTCHA is optional).

## Login handler (secure form + all error codes)

```php
use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Logger;
use Dotsystems\App\Parts\Validator;

public static function loginPost($request)
{
    if (Auth::isLogged() || Auth::loggedStage() === 2) {
        return DotApp::DotApp()->ajaxReply([
            'status' => 0, 'errorNo' => 9, 'message' => 'Already signed in',
        ], 200);
    }

    if (!$request->crcCheck()) {
        return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Bad request'], 400);
    }

    $answer = $request->form(['POST'], 'loginForm', function ($request) {
        $payload = $request->data(true)['data'] ?? [];
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $remember = (($payload['remember'] ?? '') === 'on');

        if (!Validator::isEmail($email)) {
            return ['code' => 200, 'body' => ['status' => 0, 'errorNo' => 1, 'message' => 'Enter a valid email']];
        }

        $login = Auth::login(['email' => $email, 'password' => $password, 'stage' => 0], $remember);

        if ($login === false) {
            // malformed input (missing password, both email+username, wrong stage)
            return ['code' => 200, 'body' => ['status' => 0, 'errorNo' => 1, 'message' => 'Bad request']];
        }

        if ($login['logged'] !== true) {
            $map = [
                1 => 'Access blocked from your IP',
                2 => 'Invalid email or password',
                3 => 'Invalid email or password',
                4 => 'Could not load permissions',
                5 => 'Bad request',
                99 => 'Server error',
            ];
            Logger::use()->warning('login failed', ['error' => $login['error']]);
            return ['code' => 200, 'body' => [
                'status' => 0, 'errorNo' => 2,
                'message' => $map[$login['error']] ?? 'Login failed',
            ]];
        }

        if (Auth::loggedStage() === 2) {
            return ['code' => 200, 'body' => [
                'status' => 1, 'twofactor' => 1, 'redirectTo' => '/shop/login/2fa',
            ]];
        }

        return ['code' => 200, 'body' => ['status' => 1, 'redirectTo' => '/shop/']];
    }, function ($request, $name) {
        return ['code' => 403, 'body' => ['status' => 0, 'message' => 'Invalid signature']];
    });

    if (!is_array($answer) || !isset($answer['body'])) {
        return DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'Rejected'], 400);
    }
    return DotApp::DotApp()->ajaxReply($answer['body'], $answer['code']);
}
```

Template + JS: see [EX-01-secure-form-complete.md](EX-01-secure-form-complete.md).

---

## Route protection

```php
// middleware class: app/modules/Shop/Middleware/Gate.php
namespace Dotsystems\App\Modules\Shop\Middleware;

use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Response;

class Gate extends \Dotsystems\App\Parts\ModuleMiddleware
{
    public static function check($request, array $rights = [])
    {
        if (!Auth::isLogged()) {
            return Response::redirect('/shop/login', 302);
        }
        if (!empty($rights) && !Auth::can($rights)) {   // OR semantics
            return new Response(403, 'Forbidden');
        }
        // returning null/void continues the pipeline
    }
}
```

```php
Router::get('/shop/admin/items', 'Shop:Admin@items!')
    ->before(function ($request) {
        return \Dotsystems\App\DotApp::call('#Shop:Gate@check!', $request, [
            'dotapp.root', 'Shop.admin', 'Shop.items.view',
        ]);
    });
```

Permission strings are `"{module}.{rightname}"`. `Auth::can(['a','b'])` is OR; use `Auth::can(['a','b'], \Dotsystems\App\Parts\AuthObj::$And)` for AND.

**Do not use `Auth::hasRole()`** — core never populates roles.

---

## 2FA (TOTP) enrolment + confirmation

```php
use Dotsystems\App\Parts\QR;
use Dotsystems\App\Parts\TOTP;

// 1. enrolment page — generate the secret, store it on the user, show a QR
try {
    $secret = TOTP::newSecret();
    $uri = TOTP::otpauth(Auth::attributes()['email'] ?? 'user', $secret);
    $qrDataUri = QR::imageToBase64(QR::generate($uri, ['level' => 'qrm'])->outputPNG());
} catch (\Throwable $e) {
    Logger::use()->error('2FA enrolment failed', ['msg' => $e->getMessage()]);
    return new Response(500, 'Could not start 2FA setup');
}
// persist $secret into your users table column tfa_auth_secret + set tfa_auth = 1
```

```php
// 2. confirmation step (user is in stage 2)
$r = Auth::confirmTwoFactor(['tfa' => $code]);

if ($r['confirmed'] !== true) {
    $map = [
        1 => 'Not awaiting two-factor confirmation',
        2 => 'Invalid authenticator code',
        3 => 'Invalid SMS code',
        4 => 'Invalid email code',
        5 => 'No two-factor method provided',
    ];
    return ['status' => 0, 'message' => $map[$r['error']] ?? 'Verification failed'];
}
return ['status' => 1, 'redirectTo' => '/shop/'];
```

### 2FA input UX (**MUST** — `$dotapp().twoFactor`)

Do **not** invent digit boxes. `dotapp.js` already has them ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §3).

```html
<div class="two-fa-inputs">
  <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
  <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
  <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
  <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
  <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
  <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
</div>
```

```javascript
$dotapp(".two-fa-inputs input").twoFactor(function (code) {
  $dotapp().load("/shop/login/2fa", "POST", { tfa: code }, function (raw) {
    var reply = $dotapp().parseReply(raw);
    if (reply && reply.status == 1 && reply.redirectTo) window.location = reply.redirectTo;
  });
}, { length: 6, allowLetters: false, autoSubmit: true });
```

SMS and email codes are generated by core but **not sent** — deliver them yourself (see [EX-11-email-sms-qr.md](EX-11-email-sms-qr.md)).

---

## Creating a user

```php
try {
    $r = Auth::createUser($username, $password, $email, ['created_by' => Auth::userId()]);
} catch (\Throwable $e) {
    return ['status' => 0, 'message' => 'Invalid email'];    // createUser throws on bad email
}

if ($r['error'] === 1) { return ['status' => 0, 'message' => 'User already exists']; }
if ($r['error'] === 99) {
    Logger::use()->error('createUser DB error', (array) ($r['error_data'] ?? []));
    return ['status' => 0, 'message' => 'Server error'];
}
```

---

## Encrypting IDs in templates

```html
<input type="hidden" name="id" value="{{ enc(Shop.item.id): $itemId }}" />
```

```php
$id = Crypto::decrypt($payload['id'] ?? '', 'Shop.item.id');
if ($id === false) {     // decrypt returns FALSE, not null
    return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Invalid id']];
}
```

Ciphertext includes the per-session key — do **not** store it in the database expecting to decrypt it in a later session.

---

## Login throttling (not built in)

```php
Router::post('/shop/login', 'Shop:Login@loginPost!')
    ->throttle(['per_minute' => 5, 'per_hour' => 40])
    ->limitExceeded(function ($request) {
        return new Response(429, 'Too many attempts');
    });
```

Core has no account lockout or password-reset flow — implement them in your module if required.
