# 10 — Config and Secrets

## The only editable framework file

Agents may edit **`app/config.php`**. Everything else in core is forbidden.

Typical `app/config.php` responsibilities:

- Composer autoload
- Session / cache / logger drivers
- `Config::set("app","name", ...)`
- `Config::set("app","c_enc_key", ...)`
- `Config::addDatabase(...)`
- Optional email / search engine credentials
- Create `DotApp` and `load_modules()`

---

## @AUTOCONFIG does nothing automatically

```php
/* @AUTOCONFIG */
/* @END[AUTOCONFIG] */
```

This block is **empty**. No installer / dotapper writer fills secrets here. Agents must generate keys explicitly when the user asks for a new secure app setup.

---

## Mandatory secrets on new installs

| Key | Purpose | Default (unsafe) |
|-----|---------|------------------|
| `app.name` | App identity; feeds remember-me cookie naming | placeholder |
| `app.c_enc_key` | Primary encryption key | `YourSuperSecretKey` |
| `app.rm_key` | Remember-me encryption | `RememberMe :)` |
| `app.rmrcm_key` | Remember-me cookie name crypto | `RandomCookieName :)` |

Generate:

```powershell
php -r "foreach (['c_enc_key','rm_key','rmrcm_key'] as $k) echo $k.': '.bin2hex(random_bytes(32)).PHP_EOL;"
```

Write into config:

```php
Config::set("app", "name", "MyUniqueAppName");
Config::set("app", "c_enc_key", "PASTE_64_HEX_CHARS");
Config::set("app", "rm_key", "PASTE_64_HEX_CHARS");
Config::set("app", "rmrcm_key", "PASTE_64_HEX_CHARS");
```

Also set production session flags:

```php
Config::session("lifetime", 3600);
Config::session("secure", true);   // HTTPS
Config::session("httponly", true);
Config::session("samesite", "Strict");
```

`app.name_hash` is auto-derived — do not invent it.

---

## Database registration

```php
Config::addDatabase(
    "main",          // connection name
    "127.0.0.1",
    "user",
    "password",
    "dbname",
    "UTF8",
    "MYSQL",
    "pdo"
);
```

---

## Reading config from modules

```php
Config::module("Shop", "apikey");                 // get
Config::get("db", "prefix");
Config::session("lifetime");
```

There is **no** global `config()` helper.

---

## Module settings with fallbacks (required pattern)

User may override in `app/config.php`:

```php
Config::module("Shop", "apikey", "production-secret");
```

Module `initialize()` **must** still define defaults if missing:

```php
// Pattern A — null coalesce setter (common in example modules)
Config::module("Shop", "public") ?? Config::module("Shop", "public", false);
Config::module("Shop", "enckey") ?? Config::module("Shop", "enckey", bin2hex(random_bytes(16)));
Config::module("Shop", "timeout") ?? Config::module("Shop", "timeout", 8);

// Pattern B — nested array merge
$defaults = ["enabled" => false, "model" => "gpt-4o"];
Config::module("Shop", "AI", array_replace($defaults, Config::module("Shop", "AI") ?? []));

// Pattern C — read-site fallback
$timeout = (int)(Config::module("Shop", "timeout") ?? 8);

// Pattern D — set only if absent
Config::module("Shop", "maxAttempts", 5, Config::IF_NOT_EXIST);
```

**Never** hard-code production secrets only in the module without documenting that `app/config.php` overrides them. Prefer weak-looking defaults only for local bootstrap, and instruct the user to override.

---

## Important Config sections (defaults live in Config.php)

| Section | Examples |
|---------|----------|
| `app` | name, c_enc_key, rm_key, rmrcm_key, version |
| `db` | prefix `dotapp_`, driver `pdo`, maindb `main`, cache |
| `session` | lifetime, cookie flags, redis_*, file dirs |
| `cache` | use, driver, lifetime, prefix, redis/memcached |
| `logger` | driver, log_levels, folder, core_log_enabled |
| `totp` | issuer, algorithm, digits, period |
| `bridge` | storage_limit |
| `router` | match_cache |
| `searchEngines` | ES / Meili / Algolia / Typesense keys |
| `emailer` | via `Config::email($account, ...)` |
| `modules` | via `Config::module` |

---

## Agent behavior when user asks “generate secure keys”

1. Generate with `random_bytes` / `bin2hex`.
2. Write into `app/config.php` only.
3. Tell the user to keep secrets out of VCS if possible.
4. Do not commit secrets into module source as permanent production values.
5. Ensure module fallbacks still exist so the app boots without every key filled.
