# 16 — Recipes (framework level)

These recipes use **framework APIs only**, so they work with or without DACore. Replace `Shop` with your module name.

For admin-area work (menu, rights, admin pages, AI tools, inbox) use the DACore recipes instead: [examples/EX-D01](examples/EX-D01-dacore-module-skeleton.md)–[EX-D05](examples/EX-D05-dacore-notifications.md) and docs [30](30-DACORE-OVERVIEW.md)–[37](37-DACORE-NOTIFICATIONS.md).

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

```php
Router::get('/shop/admin', 'Shop:Home@admin!')
    ->before(function ($request) {
        if (!\Dotsystems\App\Parts\Auth::isLogged()) {
            return new \Dotsystems\App\Parts\Response(403, 'Login required');
        }
    });
```

Or `#Shop:AuthGate@check!` middleware class created via dotapper.

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
Trigger file is **`dainstall.php`** (not `install.php`) on **your** module under DACore. Keep `init/` copies of `module.init.php` and `module.listeners.php`. **Not** for `app/modules/DACore/` itself.  
For a module that must also run on a bare framework, use the module-owned pattern in [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md) with `install.php`.

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

Users, logs, items, orders — any collection that **can accumulate** — **MUST** `->paginate($perPage, $page)` on the **first** version, not `->all()` into the view. **“Few rows now” is not a skip.**

Pager **MUST** be **interactive AJAX**: `type="button"` + `$dotapp().load()`; overlay the list while in flight; patch rows **and** pager. **MUST NOT** `<a href="?page=2">` / `location.reload()`. A reload pager counts as missing. Admin markup: `DACore:Page@paginate!` `$callable` as buttons ([33](33-DACORE-PAGES-AND-UI.md) §3). Lookup lists: **AJAX search** unless declined — **ASK** in the plan ([09](09-DOTAPP-JS-AND-BRIDGE.md) §3).

Theory: [06](06-DATABASE.md), [09](09-DOTAPP-JS-AND-BRIDGE.md) §3. Copy-paste: [EX-04](examples/EX-04-database-crud.md), [EX-06](examples/EX-06-dotapp-js-boot.md), [EX-D01](examples/EX-D01-dacore-module-skeleton.md).

---

## R12 — Beyond the basics

| Need | Recipe |
|------|--------|
| Paginated UI list (logs, users, items) | R13 + [EX-06](examples/EX-06-dotapp-js-boot.md) |
| Validation + JSON error envelope | [examples/EX-09](examples/EX-09-validation-and-errors.md) |
| Cache / logging / sessions | [examples/EX-10](examples/EX-10-cache-logger-session.md) |
| Email, SMS provider, QR | [examples/EX-11](examples/EX-11-email-sms-qr.md) |
| AI, FastSearch, MCP tools | [examples/EX-12](examples/EX-12-ai-search-mcp.md) |
| SchemaBuilder DDL / introspection | [examples/EX-13](examples/EX-13-schema-migrations.md) |
| Login, permissions, 2FA | [examples/EX-14](examples/EX-14-auth-and-2fa.md) (`$dotapp().twoFactor` for code boxes) |
| Cursor credits / subagents | [00](00-AGENT-CONTRACT.md) §2b — **ASK** in the plan; inherit parent; Composer 2.5 = file hunt only |

---

## R11 — New app secrets

When user asks to harden a fresh install:

1. Generate three keys with `bin2hex(random_bytes(32))`  
2. Set `app.name`, `c_enc_key`, `rm_key`, `rmrcm_key` in `app/config.php`  
3. Enable `session.secure` if HTTPS  
4. Keep module fallbacks  

See [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md).
