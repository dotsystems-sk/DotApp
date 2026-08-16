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

**Do not use `#DACore:AuthTest@check!` for permission checks** — it ignores the rights you pass (see [36](36-DACORE-KNOWN-ISSUES.md)). Generate your own middleware:

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

### Attaching it — GET page route

```php
Router::get(
    Config::module("DACore", "prefixUrl") . "/shop-admin/items",
    "Shop:Admin@items!"
)->before(function ($request) {
    return DotApp::call("#Shop:Rights@check!", $request, [
        'dotapp.root',
        'Shop.administrator',
        'Shop.items.view',
    ]);
});
```

### Attaching it — POST endpoint (CRC first, then rights)

```php
Router::post(
    Config::module("DACore", "prefixUrl") . "/shop-admin/items/save",
    "Shop:Admin@save!"
)->before(function ($request) {
    if ($request->crcCheck() === false) {
        return new Response(403, DotApp::call(Config::module("DACore", "error403Page")));
    }
    return DotApp::call("#Shop:Rights@check!", $request, [
        'dotapp.root',
        'Shop.administrator',
        'Shop.items.edit',
    ]);
});
```

Returning a `Response` from a `before` hook short-circuits the route ([03](03-MODULES-AND-ROUTING.md)).

---

## 4. Login and permission refresh middleware

DACore already registers globally in its own `module.init.php`:

| Middleware | Purpose |
|------------|---------|
| `#DACore:AuthTest@loginRouter!` | Redirects anonymous users to the login or 2FA URL using `header()` + `exit()` |
| `#DACore:AuthTest@permissionRefresh!` | Re-reads permissions every `permissionsAutorefreshTime` seconds and triggers `DACore.permissions.refresh` |
| `#DACore:AuthTest@check!` | CRC validation for `POST /dacore/*` |

You normally do **not** re-register these. Register your admin routes only when logged in if you want to keep the router small:

```php
if (Auth::isLogged() === true) {
    // routes for the authenticated admin area
}
```

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

## 6. Mistakes to avoid

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
