# 32 — DACore Rights and Route Protection

Permissions are framework-level strings `"{module}.{rightname}"` stored in `{prefix}users_rights_list` and linked to users via `{prefix}users_rights`. DACore provides the CRUD helpers.

Runtime checks use the framework `Auth` facade ([11](11-AUTH-AND-CRYPTO.md)).

---

## 1. Registering rights (in `Installation.php`)

### One group per module

```php
DotApp::call("DACore:Rights@createGroup!", string $name, string $creator): ?int
```

| Aspect | Detail |
|--------|--------|
| Returns | `int` group id, or **`null`** when the INSERT failed |
| Idempotency | **one group per `creator`** — an existing group returns its id |
| Throws / logs | no |

```php
$groupId = DotApp::call("DACore:Rights@createGroup!", "Shop", "Shop");
if ($groupId === null) {
    Logger::use()->error('Shop: cannot create rights group');
    return;
}
```

### Individual rights — parameter order matters

```php
DotApp::call(
    "DACore:Rights@createRight!",
    int $groupId,
    string $name,          // human label
    string $description,
    string $module,         // -> permission prefix
    string $rightname,      // -> permission suffix
    string $creator,        // for uninstall cleanup
    int $active = 1,
    int $ordering = 0,
    int $custom = 0
): ?int
```

| Aspect | Detail |
|--------|--------|
| Returns | `int` right id, or **`null`** on failure |
| Idempotency key | `(group_id, module, rightname)` |
| Resulting permission string | `"{$module}.{$rightname}"` |

```php
$rightId = DotApp::call(
    "DACore:Rights@createRight!",
    $groupId,
    "Shop administrator",
    "Full access to the Shop module",
    "Shop",              // module
    "administrator",     // rightname  =>  permission "Shop.administrator"
    "Shop"               // creator
);
```

Recommended right set for a module: one `administrator` plus fine-grained `area.action` rights (`items.view`, `items.edit`, `orders.export`).

**MUST (product copy):** `$name` and `$description` are what operators see on the rights screen. Write them as a software company would — capability only (`Shop administrator`, `Full access to the Shop module`). **MUST NOT** echo the ticket (`This user can use the AI assistant in the corner. They can hide the icon themselves.`). Hide-icon and similar preferences are **settings**, not extra sentences on the right. See [05](05-VIEWS-TEMPLATES-ASSETS.md) §8.

### Assigning and removing

```php
DotApp::call("DACore:Rights@assign!", int $userId, int $rightId): bool
DotApp::call("DACore:Rights@remove!", int $userId, int $rightId): bool
```

`assign` returns `true` when already assigned. `remove` returns `true` even when the row was missing.

### Uninstall cleanup

```php
DotApp::call("DACore:Rights@deleteRight!", string $creator, ?string $rightname = null): bool
DotApp::call("DACore:Rights@deleteGroup!", string $creator): bool
```

`deleteRight` with `$rightname = null` removes **all** rights created by that `creator` plus their user assignments. `deleteGroup` does that and then removes the group row.

---

## 2. Checking rights at runtime

Use the framework API:

```php
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\AuthObj;

Auth::can('Shop.items.view');                              // single
Auth::can(['dotapp.root', 'Shop.administrator']);           // OR (default)
Auth::can(['Shop.items.view', 'Shop.items.edit'], AuthObj::$And);   // AND
Auth::permissions();                                        // array of strings
```

`dotapp.root` is the superuser permission — include it in every allow-list.

`Auth::can()` does **not** understand wildcards. Wildcards only exist in the menu rights and in your own middleware helper (below).

---

## 3. Your own route-guard middleware (required)

**Do not use `#DACore:AuthTest@check!` for permission checks** — it ignores the rights you pass (see [36](36-DACORE-KNOWN-ISSUES.md)). **Do not** copy `#DACore:AuthTest@loginRouter!` into your module (`header` + `exit()`). Your login gate **MUST** return a `Response`.

### Admin URL prefix + login `before` (**MUST**)

Path = `{DACore prefixUrl}/{ModuleName}/…` (module name `Shop`, not a kebab slug). Cover the whole tree with **one** login middleware. This is **not** Laravel: `Router::before($pattern, $fn)` binds only if the **current** request already matches ([03](03-MODULES-AND-ROUTING.md)).

```powershell
php dotapper.php --module=Shop --create-middleware=Gate
php dotapper.php --module=Shop --create-middleware=Rights
```

```php
<?php
namespace Dotsystems\App\Modules\Shop\Middleware;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\Response;

class Gate extends \Dotsystems\App\Parts\ModuleMiddleware
{
    /** HTML / GET area. MUST NOT crcCheck() — GET has no CRC. */
    public static function login($request)
    {
        if (!Auth::isLogged()) {
            return new Response(403, DotApp::call(Config::module('DACore', 'error403Page')));
        }
    }
}
```

```php
$admin = rtrim((string) Config::module('DACore', 'prefixUrl'), '/') . '/Shop';

Router::before([$admin, $admin . '/*'], '#Shop:Gate@login!');

if (Auth::isLogged() === true) {
    Router::get($admin . '/items', 'Shop:Admin@items!')
        ->before(function ($request) {
            return DotApp::call('#Shop:Rights@check!', $request, [
                'dotapp.root',
                'Shop.administrator',
                'Shop.items.view',
            ]);
        });
}
```

`/dacore/Shop/*` matches URLs that **start with** `/dacore/Shop/`. Exact `/dacore/Shop` does not — that is why the array includes `$admin`. **MUST NOT** put `crcCheck()` on this HTML hook.

### Versioned POST API (**MUST**)

Put JSON / `fo-rm` / `$dotapp().load()` POSTs under `/api/v{n}/auth|noauth/{Module}/…`, **not** under `{prefixUrl}/Shop/…`. DACore already CRC’s `POST /dacore/*` (`#DACore:AuthTest@check!`) — a second `crcCheck()` there **burns** and fails. Wire `<fo-rm action>` and JS `load()` to the API URL. Keep v1 when you add v2.

**Use DACore’s methods** (they exist — do not invent `@CRCcheck` / `@LoginAndCRCcheck`, do not copy them into Shop):

| Hook | What it does |
|------|----------------|
| `#DACore:AuthTest@LoginAndCRC!` | `crcCheck()` + `Auth::isLogged()` — token **burned** |
| `#DACore:AuthTest@CRC!` | `crcCheck()` only — token **burned** |
| `#DACore:AuthTest@check!` | CRC on **DACore’s** `POST /dacore/*` — **not** a rights guard ([36](36-DACORE-KNOWN-ISSUES.md)) |

```php
$authApi = '/api/v1/auth/Shop';
$openApi = '/api/v1/noauth/Shop';
Router::before(['POST'], [$authApi, $authApi . '/*'], '#DACore:AuthTest@LoginAndCRC!');
Router::before(['POST'], [$openApi, $openApi . '/*'], '#DACore:AuthTest@CRC!');
```

The action **MUST NOT** `crcCheck()` again. `formName` → only `form()`. Rights stay `#Shop:Rights@check!`. **MUST NOT** hang `uploadFile` under these prefixes.

### Rights middleware

Generate `Rights` and attach **per route** inside the `isLogged` block (logged in ≠ this right):

```powershell
php dotapper.php --module=Shop --create-middleware=Rights
```

```php
<?php
namespace Dotsystems\App\Modules\Shop\Middleware;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\Response;

class Rights extends \Dotsystems\App\Parts\ModuleMiddleware
{
    /**
     * Returns a 403 Response when the user lacks every listed right,
     * or null to let the request continue.
     */
    public static function check($request, array $rights = [])
    {
        if (!self::userHaveRights($rights)) {
            return new Response(403, DotApp::call(Config::module("DACore", "error403Page")));
        }
        return null;
    }

    /**
     * OR semantics with wildcard support:
     *   '*'            -> any logged-in user
     *   'Shop.*'       -> any permission starting with "Shop."
     *   'Shop.x.view'  -> exact permission
     */
    public static function userHaveRights(array $rights = []): bool
    {
        if (!Auth::isLogged()) {
            return false;
        }
        if (empty($rights)) {
            return true;
        }

        $permissions = Auth::permissions();
        if (!is_array($permissions)) {
            $permissions = [];
        }

        $literals = [];
        foreach ($rights as $right) {
            if ($right === '*') {
                return true;
            }
            if (substr($right, -2) === '.*') {
                $prefix = substr($right, 0, -1);          // "Shop."
                foreach ($permissions as $permission) {
                    if (strpos($permission, $prefix) === 0) {
                        return true;
                    }
                }
                continue;
            }
            $literals[] = $right;
        }

        return !empty($literals) && Auth::can($literals);
    }
}
```

### Attaching rights — GET page route

Put GET/POST handlers **inside** `if (Auth::isLogged() === true)` (**MUST**). The prefix `Gate@login` already 403s; rights `before` still runs: logged in ≠ this right.

```php
Router::get(
    $admin . '/items',
    'Shop:Admin@items!'
)->before(function ($request) {
    return DotApp::call('#Shop:Rights@check!', $request, [
        'dotapp.root',
        'Shop.administrator',
        'Shop.items.view',
    ]);
});
```

### Attaching rights — POST endpoint (CRC already on the API prefix)

```php
Router::post(
    $authApi . '/items/save',
    'Shop:Admin@save!',
    Router::STATIC_ROUTE
)->before(function ($request) {
    return DotApp::call('#Shop:Rights@check!', $request, [
        'dotapp.root',
        'Shop.administrator',
        'Shop.items.edit',
    ]);
});
```

**MUST NOT** `crcCheck()` here — `LoginAndCRC` already burned the token. Then `form()` in the controller.

Returning a `Response` from a `before` hook short-circuits the route ([03](03-MODULES-AND-ROUTING.md)).

---

## 4. Login and permission refresh middleware

DACore already registers globally in its own `module.init.php`:

| Middleware | Purpose |
|------------|---------|
| `#DACore:AuthTest@loginRouter!` | On **DACore’s own** routes: redirects anonymous users to login/2FA with `header()` + `exit()` — **not** a `Response` |
| `#DACore:AuthTest@sessionGuard!` | Session enforcement on `{prefixUrl}` — DACore registers it **only when already logged in** |
| `#DACore:AuthTest@permissionRefresh!` | Re-reads permissions every `permissionsAutorefreshTime` seconds and triggers `DACore.permissions.refresh` |
| `#DACore:AuthTest@check!` | CRC on `POST /dacore/*` (token burned) — **not** a permission guard |
| `#DACore:AuthTest@CRC!` | CRC only — attach on **your** `POST /api/v1/noauth/{Module}/*` |
| `#DACore:AuthTest@LoginAndCRC!` | CRC + logged in — attach on **your** `POST /api/v1/auth/{Module}/*` |

You normally do **not** re-register `check` / `sessionGuard` / `loginRouter`. **Your** module **MUST** still: HTML `Gate@login` on `{prefixUrl}/Shop`, POST `#DACore:AuthTest@LoginAndCRC!` / `@CRC!` on `/api/v1/auth|noauth/Shop`, handlers only when `Auth::isLogged()` (auth API + HTML). Admin pages **MUST NEVER** render for an anonymous user.

```php
$admin = rtrim((string) Config::module('DACore', 'prefixUrl'), '/') . '/Shop';
$authApi = '/api/v1/auth/Shop';
$openApi = '/api/v1/noauth/Shop';
Router::before([$admin, $admin . '/*'], '#Shop:Gate@login!');
Router::before(['POST'], [$authApi, $authApi . '/*'], '#DACore:AuthTest@LoginAndCRC!');
Router::before(['POST'], [$openApi, $openApi . '/*'], '#DACore:AuthTest@CRC!');

if (Auth::isLogged() === true) {
    // GET pages + auth API routes + Rights@check!
}
```

Rights middleware **inside** that block is still required: logged in ≠ `Shop.items.edit`. Canonical: [03](03-MODULES-AND-ROUTING.md). Sample: [EX-D01](examples/EX-D01-dacore-module-skeleton.md).

To react to permission changes (e.g. rebuild a cached AI context):

```php
DotApp::DotApp()->on("DACore.permissions.refresh", "Shop:Admin@onPermissionsRefresh");
```

---

## 5. Current user

```php
Auth::userId();
Auth::username();
Auth::attributes()['email'] ?? null;
Auth::isLogged();
Auth::loggedStage();     // 1 = full, 2 = awaiting 2FA
```

Do **not** use `Auth::hasRole()` — core never populates roles ([11](11-AUTH-AND-CRYPTO.md)).

---

## 6. Operator 2FA and step-up (**MUST** — DACore admin only)

This applies **only** inside the DACore admin shell (`Page@withMenu!`). It does **not** apply to a public website.

A **DACore operator** is anyone working in that shell (`dotapp.root` and/or a module `*.administrator` right).

### Operators MUST have 2FA — and MUST NOT turn it off

At least **one** method MUST be on: authenticator (`tfa_auth`), SMS (`tfa_sms`), or email (`tfa_email`). Type does not matter.

Your module **MUST NOT** expose a control, API, or installer step that clears the last 2FA method for an operator (including themselves). Turning admin 2FA off is **forbidden**. If you build a profile/2FA settings screen, an operator cannot disable the only remaining method.

If the current operator has **no** 2FA method, dangerous actions below **MUST** be refused (do not “skip 2FA this once”).

When you create or promote an operator, enable at least one method as part of that flow.

### Dangerous actions MUST re-ask for a 2FA code

Before an action that can **seriously damage or disable** the system, prompt again for a fresh code. Login 2FA is not enough.

Examples (not exhaustive): delete or demote an admin / `dotapp.root` account; grant `dotapp.root`; wipe or uninstall module data; drop tables; change auth/crypto keys; disable 2FA; bulk destructive deletes.

**UI:** `$dotapp().twoFactor` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3). **MUST NOT** invent another OTP widget.

**Which code:** use the method that is **on** for this user. Prefer authenticator if `tfa_auth = 1`; else SMS; else email. SMS/email codes are generated by **you** and **you** send them ([21](21-EMAIL-SMS-QR.md)) — core does not send.

**PHP:** `Auth::confirmTwoFactor()` **MUST NOT** be used here. It only works at login **stage 2** (`error` 1 when already fully logged in). `Auth::tfaTotp()` is also `null` at stage 1.

TOTP step-up (operator already at stage 1):

```php
$user = Auth::attributes();
if (empty($user['tfa_auth']) || empty($user['tfa_auth_secret'])) {
    return ['code' => 200, 'body' => ['status' => 0, 'message' => '2FA required']];
}
$expected = \Dotsystems\App\Parts\TOTP::generate($user['tfa_auth_secret']);
if (!hash_equals((string) $expected, (string) $code)) {
    return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Invalid 2FA code']];
}
// then perform the dangerous action
```

SMS/email step-up: issue a new 6-digit code, store it in **your module** session (`DSM::use('Shop')`) with a short TTL, one-time; send it; compare with `hash_equals`. Never persist the code in a cookie or in HTML. **MUST NOT** `$_SESSION`.

Overlay the confirm UI until the request ends ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3). Still `Auth::can()` / encrypted ids / `crcCheck()`.

The overlay is **UX only**. **MUST** verify the 2FA code in PHP **before** persisting — including turning a 2FA method off. If the request skipped the modal (overlay removed, Save clicked anyway, DevTools POST), **MUST NOT** write. No code / wrong code → refuse and leave 2FA on. See [08](08-FORMS-AND-SECURITY.md) “Server is the authority”.

**Dangerous flags in your module (MUST):** do not persist “off” on the same handler as ordinary settings. The general save **MUST ignore** a request that turns the flag off (including a crafted POST). Turning it off **MUST** be a separate PHP handler that verifies a 2FA code first. Covering Save with a 2FA overlay is not enough.

Do **not** patch DACore’s own login or user screens to enforce this. Put the prompt and the check in **your** module.

---

## 7. Mistakes to avoid

| Wrong | Right |
|-------|-------|
| `INSERT INTO dotapp_users_rights_list ...` | `DACore:Rights@createRight!` |
| Wrong parameter order in `createRight` | `($groupId, $name, $description, $module, $rightname, $creator)` |
| Ignoring `null` returns | Check and log |
| `#DACore:AuthTest@check!` with a rights array | Your own `#Shop:Rights@check!` |
| Omitting `dotapp.root` | Superuser loses access |
| `Auth::can('Shop.*')` | Wildcards need your middleware helper |
| Registering rights on every request | Do it in `Installation.php` |
| Checking rights only in the UI | Enforce on the route **and** in the handler |
| `Auth::confirmTwoFactor` for a logged-in dangerous action | TOTP: `TOTP::generate` vs `attributes()['tfa_auth_secret']`; SMS/email: your one-time challenge ([32](32-DACORE-RIGHTS.md) §6) |
| UI that turns off an operator’s last 2FA method | Forbidden — operators MUST keep at least one method |
| 2FA overlay/modal only; save still writes without a code | PHP verifies the code **before** persist ([08](08-FORMS-AND-SECURITY.md), this file §6) |
| Dangerous flag turned off on the same save as other settings | General save ignores “off”; a separate 2FA handler turns it off ([32](32-DACORE-RIGHTS.md) §6) |
| Right description that echoes the ticket (“this user can hide the icon…”) | Product copy: name the capability only ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
