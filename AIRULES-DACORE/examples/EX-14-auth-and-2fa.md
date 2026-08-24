# EX-14 — Auth, permissions, 2FA

Rules: [11](../11-AUTH-AND-CRYPTO.md), [19](../19-VALIDATION-AND-INPUT.md) (`data(true)` = original). Origin-scoped module identity: [42](../42-DACORE-USER-ORIGIN.md). Operator step-up (**ASK**, default no): [32](../32-DACORE-RIGHTS.md) §6, [EX-D10](EX-D10-stepup-2fa-modal.md).

**MUST** take the password from `$request->data(true)['data']`. `$request->data()` is `protect()`-escaped — `)`, `=`, `%` become a **different** password. **MUST** show `reply.message` on every failure (`crcCheck`, `form()` `null`/`false`, `Auth::login === false`, error codes). Silent 400 is incomplete.

`$dotapp().twoFactor` is **input UX only**. Completing the boxes or covering Save with a modal does **not** authorize. The PHP handler **MUST** verify the code before persist ([08](../08-FORMS-AND-SECURITY.md)).

Privilege, secrets, lockout, SQL ownership, own-password proof: [11](../11-AUTH-AND-CRYPTO.md) §11. A public login/register **MUST** be mentioned to the user as bot-exposed (CAPTCHA is optional).

## Origin-scoped login handler (secure form + all error codes)

Auth identity/session is global. This Shop example therefore checks `shop.checkout` immediately after successful credentials. The same check is repeated by 2FA and every Shop route gate below. A foreign-origin mismatch is deliberately indistinguishable from bad credentials.

```php
use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Logger;
use Dotsystems\App\Parts\Validator;

public static function loginPost($request)
{
    if (Auth::isLogged() || Auth::loggedStage() === 2) {
        $existingId = (int) Auth::userId();
        $existingPolicy = $existingId > 0
            ? DotApp::call('DACore:UserPolicy@read', $existingId)
            : null;
        if (
            is_array($existingPolicy)
            && (string) ($existingPolicy['origin'] ?? '') === 'shop.checkout'
            && (int) ($existingPolicy['origin_id'] ?? 0) > 0
        ) {
            return DotApp::DotApp()->ajaxReply([
                'status' => 0, 'errorNo' => 9, 'message' => 'Already signed in',
            ], 200);
        }
        // Why: a foreign global session must not be mistaken for Shop authentication.
        Auth::logout();
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

        $userId = (int) Auth::userId();
        $policy = $userId > 0
            ? DotApp::call('DACore:UserPolicy@read', $userId)
            : null;
        if (
            !is_array($policy)
            || (string) ($policy['origin'] ?? '') !== 'shop.checkout'
            || (int) ($policy['origin_id'] ?? 0) < 1
        ) {
            // Why: Auth session is global; foreign/fallback origin must not survive this module login.
            Auth::logout();
            return ['code' => 200, 'body' => [
                'status' => 0, 'errorNo' => 2, 'message' => 'Invalid email or password',
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

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Response;

class Gate extends \Dotsystems\App\Parts\ModuleMiddleware
{
    public static function check($request, array $rights = [])
    {
        if (!Auth::isLogged()) {
            return Response::redirect('/shop/login', 302);
        }
        $userId = (int) Auth::userId();
        $policy = $userId > 0
            ? DotApp::call('DACore:UserPolicy@read', $userId)
            : null;
        if (
            !is_array($policy)
            || (string) ($policy['origin'] ?? '') !== 'shop.checkout'
            || (int) ($policy['origin_id'] ?? 0) < 1
        ) {
            // Why: a session created by DACore/remember-me/another module is still global.
            Auth::logout();
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
    \Dotsystems\App\Modules\Shop\Libraries\CatchBus::reportCatch($e);
    return new Response(500, 'Could not start 2FA setup');
}
// persist $secret into your users table column tfa_auth_secret + set tfa_auth = 1
```

```php
// 2. confirmation step (user is in stage 2)
$userId = (int) Auth::userId();
$policy = $userId > 0
    ? DotApp::call('DACore:UserPolicy@read', $userId)
    : null;
if (
    !is_array($policy)
    || (string) ($policy['origin'] ?? '') !== 'shop.checkout'
    || (int) ($policy['origin_id'] ?? 0) < 1
) {
    Auth::logout();
    return ['status' => 0, 'message' => 'Invalid email or password'];
}

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

// Why: repeat after stage transition; route middleware will enforce it on later requests too.
$policy = DotApp::call('DACore:UserPolicy@read', (int) Auth::userId());
if (
    !is_array($policy)
    || (string) ($policy['origin'] ?? '') !== 'shop.checkout'
    || (int) ($policy['origin_id'] ?? 0) < 1
) {
    Auth::logout();
    return ['status' => 0, 'message' => 'Invalid email or password'];
}
return ['status' => 1, 'redirectTo' => '/shop/'];
```

### 2FA input UX (**MUST** — `$dotapp().twoFactor`)

Do **not** invent digit boxes. `dotapp.js` already has them ([09](../09-DOTAPP-JS-AND-BRIDGE.md) §3). This block is **login**. A second prompt after login is a different chrome — only if the plan named it: [EX-D10](EX-D10-stepup-2fa-modal.md).

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

The installer must already have registered `shop.checkout` and checked `{ok:true, origin_id>0}`. `Auth::createUser` does **not** return the id. Duplicate email/username is global across all origins and must not reveal that the row belongs to DACore/another module.

```php
use Dotsystems\App\DotApp;
use Dotsystems\App\Modules\Shop\Libraries\CatchBus;
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\DB;

try {
    $r = Auth::createUser($username, $password, $email, ['created_by' => Auth::userId()]);
} catch (\Throwable $e) {
    CatchBus::reportCatch($e);
    return ['status' => 0, 'message' => 'Account could not be created'];
}

if ($r['error'] === 1) {
    // Why: do not enumerate a DACore operator or another module's globally unique account.
    return ['status' => 0, 'message' => 'Account could not be created'];
}
if ($r['error'] === 99) {
    CatchBus::reportDb((array) ($r['error_data'] ?? []));
    return ['status' => 0, 'message' => 'Server error'];
}

try {
    // Why: createUser returns no id. Table name is fixed from trusted config, never request input.
    $prefix = (string) Config::app('dbPrefix');
    if ($prefix === '') {
        $prefix = (string) Config::get('db', 'prefix');
    }
    $usersTable = ($prefix !== '' ? $prefix : 'dotapp_') . 'users';
    $rows = DB::module('RAW')->q(function ($qb) use ($usersTable, $email) {
        $qb->raw(
            'SELECT `id` FROM `' . $usersTable . '` WHERE `email` = :email LIMIT 1',
            ['email' => $email]
        );
    })->all();
    $userId = (int) ($rows[0]['id'] ?? 0);
    if ($userId < 1) {
        return ['status' => 0, 'message' => 'Account could not be created'];
    }

    $stamp = DotApp::call(
        'DACore:UserPolicy@stampOrigin',
        $userId,
        'shop.checkout',
        'Shop'
    );
    $policy = DotApp::call('DACore:UserPolicy@read', $userId);
    if (
        $stamp !== true
        || !is_array($policy)
        || (string) ($policy['origin'] ?? '') !== 'shop.checkout'
        || (int) ($policy['origin_id'] ?? 0) < 1
    ) {
        // Why: do not report success with dacore.legacy or a foreign/fallback origin.
        return ['status' => 0, 'message' => 'Account could not be created'];
    }
} catch (\Throwable $e) {
    CatchBus::reportCatch($e);
    return ['status' => 0, 'message' => 'Account could not be created'];
}
```

`CatchBus` above means this module’s one report helper ([18](../18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9), not a DACore file. The installer’s checked `registerOrigin` establishes that `Shop` owns the fixed token. Runtime still requires the exact token and a positive id. Define a safe compensating action for a partial create failure—never silently log in or expose the account.

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
