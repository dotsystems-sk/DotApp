# 16 — Recipes (framework level)

These recipes use **framework APIs only**, so they work with or without DACore. Replace `Shop` with your module name.

For admin-area work (menu, rights, admin pages, AI tools) use the DACore recipes instead: [examples/EX-D01](examples/EX-D01-dacore-module-skeleton.md)–[EX-D04](examples/EX-D04-dacore-installer.md) and docs [30](30-DACORE-OVERVIEW.md)–[36](36-DACORE-KNOWN-ISSUES.md).

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

**views/home.view.php** — shell with `{{ content }}`  
**views/layouts/content/welcome.layout.php** — `{{ var: $message }}` via view vars when using `renderView` (pass message as viewVar).

If using `renderLayout()` alone, use `setLayoutVar`.

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

Must include `/assets/dotapp/dotapp.js` (random keys). Prefer this over plain CSRF. Theory: [08-FORMS-AND-SECURITY.md](08-FORMS-AND-SECURITY.md).

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

Protect with an API key before-hook if public. Full return-value rules: [18](18-ERROR-HANDLING-AND-RETURN-VALUES.md), full CRUD: [examples/EX-04](examples/EX-04-database-crud.md).

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
For a module that must also run on a bare framework, use the module-owned pattern in [07-SCHEMA-AND-INSTALL.md](07-SCHEMA-AND-INSTALL.md).  
Either way, `install.php` calls `Installation::module('Shop')->install();`.

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

Template: `{{_ "welcome" }}` or `{{_ "Welcome" }}` matching keys.

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

## R12 — Beyond the basics

| Need | Recipe |
|------|--------|
| Validation + JSON error envelope | [examples/EX-09](examples/EX-09-validation-and-errors.md) |
| Cache / logging / sessions | [examples/EX-10](examples/EX-10-cache-logger-session.md) |
| Email, SMS provider, QR | [examples/EX-11](examples/EX-11-email-sms-qr.md) |
| AI, FastSearch, MCP tools | [examples/EX-12](examples/EX-12-ai-search-mcp.md) |
| SchemaBuilder DDL / introspection | [examples/EX-13](examples/EX-13-schema-migrations.md) |
| Login, permissions, 2FA | [examples/EX-14](examples/EX-14-auth-and-2fa.md) |

---

## R11 — New app secrets

When user asks to harden a fresh install:

1. Generate three keys with `bin2hex(random_bytes(32))`  
2. Set `app.name`, `c_enc_key`, `rm_key`, `rmrcm_key` in `app/config.php`  
3. Enable `session.secure` if HTTPS  
4. Keep module fallbacks  

See [10-CONFIG-AND-SECRETS.md](10-CONFIG-AND-SECRETS.md).
