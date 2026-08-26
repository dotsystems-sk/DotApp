# 16 — Recipes (framework level)

These recipes use **framework APIs only**, so they work with or without DACore. Replace `Shop` with your module name.

For admin-area work (menu, rights, admin pages, AI tools, inbox, mail, SMS) use the DACore recipes instead: [examples/EX-D01](examples/EX-D01-dacore-module-skeleton.md)–[EX-D07](examples/EX-D07-dacore-sms.md) and docs [30](30-DACORE-OVERVIEW.md)–[39](39-DACORE-SMS.md).

---

## R1 — Scaffold a module

```powershell
Set-Location "path\to\project-root"
php .\dotapper.php --create-module=Shop
php .\dotapper.php --module=Shop --create-controller=Home
```

Edit `app/modules/Shop/module.init.php` — remove example noise, add real routes and config fallbacks.

---

## R2 — Public page with layout

**module.init.php**

```php
Router::get('/shop/', 'Shop:Home@index!', Router::STATIC_ROUTE);
```

**Controllers/Home.php**

```php
public static function index($request)
{
    return \Dotsystems\App\Parts\Renderer::new()
        ->module('Shop')
        ->setView('home')
        ->setLayout('content/welcome')
        ->setViewVar('title', 'Shop')
        ->setViewVar('message', 'Welcome')
        ->renderView();
}
```

**views/home.view.php** — outer shell with `{{ content }}`  
**views/layouts/content/welcome.layout.php** — inner HTML; `{{ var: $message }}` via **view vars** when using `renderView` (pass message as viewVar).

If using `renderLayout()` alone, use `setLayoutVar`. You may also generate a layout to a string and inject it (`setViewVar` / `str_replace`). Full composition: [05](05-VIEWS-TEMPLATES-ASSETS.md) §1b.

**Mobile chrome (MUST):** overlay drawer L/R, lock page scroll while open (including iOS), scrollable nav list, contacts + compact search in the drawer unless large search is its own section. [09](09-DOTAPP-JS-AND-BRIDGE.md) §3. DACore admin uses `Page@withMenu!` — do not rebuild that shell.

---

## R3 — Protected page (framework Auth)

Prefix + login `before` returning `Response` 403. Not Laravel. Handlers still only inside `Auth::isLogged()`. Canonical: [03](03-MODULES-AND-ROUTING.md).

```php
$member = '/Shop/account';
Router::before([$member, $member . '/*'], '#Shop:Gate@login!');

if (\Dotsystems\App\Parts\Auth::isLogged() === true) {
    Router::get($member, 'Shop:Account@index!', Router::STATIC_ROUTE);
}
```

---

## R4 — Secure fo-rm POST (PREFERRED for all user forms)

Copy [examples/EX-01-secure-form-complete.md](examples/EX-01-secure-form-complete.md).

Must include `/assets/dotapp/dotapp.js` (random keys). Prefer this over plain CSRF. **MUST:** `{{ formName }}` between `<fo-rm>` and `</fo-rm>` — never after `</fo-rm>`. After success while staying on the page: patch `reply.html` + toast — not `location.reload()` ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3, [EX-06](examples/EX-06-dotapp-js-boot.md)). Row **delete:** graphical confirm first (`Notiflix.Confirm` on admin — never `alert()` / `window.confirm()`), then `load()`. Theory: [08-FORMS-AND-SECURITY.md](08-FORMS-AND-SECURITY.md).

---

## R5 — JSON API endpoint

```php
Router::get('/api/v1/shop/items', 'Shop:Api@listItems!', Router::STATIC_ROUTE);

public static function listItems($request)
{
    try {
        $rows = \Dotsystems\App\Parts\DB::module('RAW')->q(function ($qb) {
            $qb->select('*')->from('shop_items')->orderBy('id', 'DESC')->limit(50);
        })->all();                       // [] when empty
        return \Dotsystems\App\Parts\Response::json(['status' => 1, 'items' => $rows]);
    } catch (\Throwable $e) {
        \Dotsystems\App\Parts\Logger::use()->error('api list failed', ['msg' => $e->getMessage()]);
        return \Dotsystems\App\Parts\Response::json(['status' => 0, 'message' => 'Server error'], 500);
    }
}
```

Protect with an API key before-hook if public. Full return-value rules: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md), full CRUD: [examples/EX-04](examples/EX-04-database-crud.md). A **UI** list of logs/users/items **MUST** paginate (R13) — do not copy this `->all()` dump into an admin table.

---

## R6 — Bridge function

Template button with `{{ dotbridge:on(click)="ping()" }}`.  
In `initialize` or a loaded controller registrar:

```php
$dotApp->bridge->fn('ping', function ($request) {
    return ['pong' => true];
});
```

---

## R7 — Ship a table via Installation.php

With DACore installed, use its tracking: [35-DACORE-INSTALL.md](35-DACORE-INSTALL.md) / [examples/EX-D04](examples/EX-D04-dacore-installer.md).  
Trigger while coding is **`install.php`** (not `dainstall.php`). After a new version, rename `installed_*_install.php` back to `install.php`. Pack `dainstall.php` + `init/` **only** for a DACore-bound module when the user asks. Non-DACore: copy the folder with `install.php`. **Not** for `app/modules/DACore/` itself.

---

## R8 — Translations

```php
\Dotsystems\App\Parts\Translator::loadLocaleFile('Shop:sk_sk.json', 'sk_sk');
\Dotsystems\App\Parts\Translator::setLocale('sk_sk');
```

`translations/sk_sk.json`:

```json
{
  "welcome": "Vitajte"
}
```

Template: `{{_ "welcome" }}` or `{{_ "Welcome" }}` matching keys. **MUST:** translation **values** are product copy ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) — never prompt-echo.

---

## R9 — Module settings with fallbacks

In `initialize()`:

```php
Config::module('Shop', 'itemsPerPage') ?? Config::module('Shop', 'itemsPerPage', 20);
```

User override in `app/config.php`:

```php
Config::module('Shop', 'itemsPerPage', 50);
```

---

## R10 — Unit-style test

```php
Tester::addTest('Shop math', function () {
    return [
        'status' => (1+1 === 2) ? 1 : 0,
        'info' => '1+1',
        'test_name' => 'Shop math',
        'context' => ['module' => 'Shop'],
    ];
});
```

```powershell
php .\dotapper.php --module=Shop --test
```

---

## R13 — Paginated AJAX list (**MUST** — first ship, interactive)

Users, logs, items, orders — any collection that **can accumulate** — **MUST** COUNT + LIMIT on the **first** version, not `->all()` into the view. **“Few rows now” is not a skip.**

Pager **MUST** follow [40](40-DACORE-LIST-PAGER.md): `type="button"` + `$dotapp().load()` + `function (el, e)`; overlay; patch rows **and** pager. **MUST NOT** `<a href="?page=2">` / `location.reload()` / `replaceState`. Copy-paste: [EX-D08](examples/EX-D08-list-pager.md). Lookup lists: **AJAX search** unless declined — **ASK** in the plan ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3).

Theory: [40](40-DACORE-LIST-PAGER.md), [06](06-DATABASE.md).

---

## R12 — Beyond the basics

| Need | Recipe |
|------|--------|
| Paginated UI list (logs, users, items) | R13 + [EX-D08](examples/EX-D08-list-pager.md) |
| Validation + JSON error envelope | [examples/EX-09](examples/EX-09-validation-and-errors.md) |
| Cache / logging / sessions | [examples/EX-10](examples/EX-10-cache-logger-session.md) |
| Email, SMS provider, QR | [examples/EX-11](examples/EX-11-email-sms-qr.md) |
| AI, FastSearch, MCP tools | [examples/EX-12](examples/EX-12-ai-search-mcp.md) |
| SchemaBuilder DDL / introspection | [examples/EX-13](examples/EX-13-schema-migrations.md) |
| Login, permissions, 2FA | [examples/EX-14](examples/EX-14-auth-and-2fa.md) (`$dotapp().twoFactor` for login boxes) |
| Second 2FA prompt after login (step-up) | **ASK** in the plan (default no). If yes: [32](32-DACORE-RIGHTS.md) §6 + [EX-D10](examples/EX-D10-stepup-2fa-modal.md) |
| Cursor credits / subagents | [00](00-AGENT-CONTRACT.md) §2b — **ASK** in the plan; inherit parent; Composer 2.5 = file hunt only |
| Planning depth | [00](00-AGENT-CONTRACT.md) §2k + §2o + §2p / [45](45-MODULE-PLANNING.md) — write **this** module’s `PLAN/` (split files); packs read host `AIRULES/`, not host `PLAN/` |
| Finish gate after every chunk | [00](00-AGENT-CONTRACT.md) §2c — grep CRC, IDs, SQL, inputs, middleware; [17](17-CHECKLISTS.md) |
| Visible outcome (save/fail) | [00](00-AGENT-CONTRACT.md) §2d — DACore toast (search first); public mark the field; [EX-09](examples/EX-09-validation-and-errors.md) |
| Connect modules / fire a useful hook | [41](41-MODULE-HOOKS.md) — `module.{mod}.{name}.hook` + `.hooks` (not every save); [EX-16](examples/EX-16-module-hooks.md) |

---

## R11 — New app secrets

When user asks to harden a fresh install:

1. Generate three keys with `bin2hex(random_bytes(32))`  
2. Set `app.name`, `c_enc_key`, `rm_key`, `rmrcm_key` in `app/config.php`  
3. Enable `session.secure` if HTTPS  
4. Keep module fallbacks  

See [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md).
