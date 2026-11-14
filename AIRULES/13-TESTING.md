# 13 — Testing

## Writing module tests

Namespace: `Dotsystems\App\Modules\{Module}\tests`

```php
<?php
namespace Dotsystems\App\Modules\Shop\tests;

use Dotsystems\App\Parts\Tester;

Tester::addTest('Shop encrypt roundtrip', function () {
    $ok = true; // assert something
    return [
        'status' => $ok ? 1 : 0, // 1=pass, 0=fail, 2=skip
        'info' => $ok ? 'ok' : 'mismatch',
        'test_name' => 'Shop encrypt roundtrip',
        'context' => ['module' => 'Shop'],
    ];
});
```

Place files under `app/modules/Shop/tests/` (e.g. `CryptoTest.php`). Dotapper creates the `tests/` directory on `--create-module`, but **does not** generate PHP test classes or AI guide stubs.

Core tests live in `app/tests/` (do not edit unless asked — outside module scope).

---

## Running tests

```powershell
Set-Location "path\to\project-root"
php .\dotapper.php --test                      # core tests
php .\dotapper.php --test-modules               # all modules
php .\dotapper.php --module=Shop --test         # one module
```

Dotapper bootstraps via `index.php` with synthetic `$_SERVER`.

---

## What to test

- Pure helpers (crypto roundtrip, validators, transformers)
- DB logic with care (prefer isolated test DB)
- Avoid brittle full-HTTP UI tests unless the project already does so

Return structured arrays from every test callback — do not throw silently without status.
