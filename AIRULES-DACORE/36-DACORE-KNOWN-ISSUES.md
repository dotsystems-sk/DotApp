# 36 — DACore Known Issues

DACore-specific traps. Framework-level issues are in [15](15-KNOWN-ISSUES.md).

---

## 1. `#DACore:AuthTest@check!` ignores the rights you pass

When called with a non-null array it does **not** evaluate your argument — it checks a hardcoded FaceTerminal permission list. It is only reliable for its registered purpose: CRC validation of `POST /dacore/*`.

**Consequence:** never use it as your permission guard.

```php
// WRONG - your rights are discarded
->before(function ($request) {
    return DotApp::call("#DACore:AuthTest@check!", $request, ['Shop.items.view']);
});

// RIGHT - your own middleware
->before(function ($request) {
    return DotApp::call("#Shop:Rights@check!", $request, ['dotapp.root', 'Shop.items.view']);
});
```

See [32](32-DACORE-RIGHTS.md) for the middleware to copy.

---

## 2. No unregister API for menu items

`Controllers/Menu.php` has `register` and `generate` but **no delete**. An uninstaller must remove rows itself:

```php
DB::module('RAW')->q(function ($qb) {
    $qb->raw("DELETE FROM `dacore_menu` WHERE `menuid` LIKE 'Shop.%'", []);
})->execute(null, function ($e) { Logger::use()->error('menu cleanup', $e); });
```

This is the one sanctioned direct write to a DACore table, and only during uninstall. Prefixing every `menuid` with your module name makes it safe.

---

## 3. AI-related tables are not created by DACore 1.0.0

`Installation.php` in DACore creates `dacore_installations` and `dacore_menu`. It only **ALTERs** `dacore_ai_tools` in later versions, and never creates `dacore_chat` / `dacore_chat_messages`.

Before registering AI tools, verify:

```php
if (!DB::schemaBuilder()->tableExists('dacore_ai_tools')) {
    Logger::use()->error('dacore_ai_tools missing - import the DACore SQL dump first');
    return;
}
```

---

## 4. AI tool rights have no wildcard support

The menu understands `*` and `Module.*`. AI tools do **not** — each permission must be listed literally. An empty `rights` array makes the tool invisible to everyone, including root.

---

## 5. Duplicate `menuid` rows are possible

`register` upserts the row with the **lowest id** for a given `menuid`. If duplicates ever get inserted manually, later ones become orphaned and are still rendered. Keep `menuid` values stable and unique.

---

## 6. Event listener return values are discarded

`Dacore:Page@withMenu.rendering` / `.rendered` let you inspect the payload, but `trigger()` returns its input unchanged, so you cannot rewrite the page from a listener. Build the final HTML before calling `Page@withMenu`.

---

## 7. `loginRouter` uses `header()` + `exit()`

`#DACore:AuthTest@loginRouter!` redirects by writing a header and terminating the script instead of returning a `Response`. Code placed after it in the same hook never runs, and no `after` hooks fire.

---

## 8. Email 2FA code generation is gated on the wrong flag

In the login flow the email verification code is generated when `tfa_auth` is set rather than `tfa_email`. If you build an email-2FA flow, verify the behaviour in your deployment before relying on it.

---

## 9. Config key typo: `miltilanguage`

The multilanguage switch is spelled **`miltilanguage`** (not `multilanguage`). Use the exact key or your setting is ignored.

---

## 10. `template.footerdata` has no default

`page.view.php` reads `$templatedata['footerdata']`, but the default `template` array does not define it. Set it in `app/config.php` if your footer needs it:

```php
Config::module('DACore', 'template', array_replace(
    Config::module('DACore', 'template') ?? [],
    ['footerdata' => '© ' . date('Y') . ' My Company']
));
```

---

## 11. Menu cache is cleared globally

`Menu@register` calls `Cache::use('DAcore')->clear()` when `useCache` is on — this flushes the **whole** `DAcore` cache context, not just menu keys. Harmless during install; a reason not to register menus at runtime.

Note the capitalisation: the cache context is `DAcore`, while the config namespace is `DACore`.

---

## 12. `Installations@exist!` cannot distinguish "missing" from "DB error"

It returns `false` in both cases, so a transient DB failure makes your migration run again. Keep migrations idempotent (`CREATE TABLE IF NOT EXISTS`, `IF NOT EXISTS` guards, upsert APIs).

---

## Priority order when docs disagree

1. DACore source under `app/modules/DACore/` — **read-only**. Never edit or add files there (updates wipe them).
2. This DACore layer (`30`–`36`)
3. Framework docs (`00`–`22`)
4. Anything else
