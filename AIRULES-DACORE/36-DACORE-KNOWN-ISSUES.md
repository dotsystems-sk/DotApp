# 36 — DACore Known Issues

DACore-specific traps. Framework-level issues are in [15](15-KNOWN-ISSUES.md). When the user asks **why** an admin POST fails, hunt with [23](23-DEBUG-PLAYBOOK.md) §7 **before** blaming DACore.

---

## 1. `#DACore:AuthTest@check!` ignores the rights you pass

When called with a non-null array it does **not** evaluate your argument — it checks a hardcoded built-in permission list. It is only reliable for its registered purpose: CRC validation of `POST /dacore/*`.

**Consequence:** never use it as your permission guard. `CRC` / `LoginAndCRC` also ignore a `$rights` argument — they only CRC (and login). After any of these, the token is **burned**: **MUST NOT** `crcCheck()` again in the action.

`#DACore:AuthTest@CRC!` and `#DACore:AuthTest@LoginAndCRC!` **do exist** — attach them on **your** `POST /api/v1/noauth|auth/{Module}/*`. Do not invent `@CRCcheck` / `@LoginAndCRCcheck`. Do not copy the class into Shop.

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
$ok = false;
DB::module('RAW')->q(function ($qb) {
    $qb->raw(
        'DELETE FROM `dacore_menu` WHERE `menuid` LIKE :prefix',
        ['prefix' => 'Shop.%']
    );
})->execute(
    function () use (&$ok) {
        $ok = true;
    },
    function ($error) use (&$ok) {
        CatchBus::reportDb($error);
        $ok = false;
    }
);
if ($ok !== true) {
    throw new \RuntimeException('Shop uninstall cleanup failed.');
}
```

This is the one sanctioned direct write to a DACore table, and only during uninstall. **`menuid` MUST start with your module name.** Uninstall **MUST** delete only that prefix (`Shop.%` from Shop, `Reports.%` from Reports). An extension that hung items under another module’s header **MUST NOT** `DELETE … LIKE 'HostModule.%'` — that destroys the host menu. See [31](31-DACORE-MENU.md).

Callback `return` values do not stop uninstall. Every cleanup return and DB result must be checked; a failure reports and throws a generic exception so DACore keeps the module folder for retry ([35](35-DACORE-INSTALL.md)).

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

It returns `false` in both cases, so a transient DB failure makes your migration run again. Keep migrations idempotent: **probe** (`SHOW TABLES LIKE` / `information_schema`) then `CREATE`/`ALTER` without `IF NOT EXISTS`, plus upsert APIs. **MUST NOT** `CREATE TABLE IF NOT EXISTS` ([07](07-SCHEMA-AND-INSTALL.md) §0).

---

## 13. `Menu@getItems($menuId)` is one level when `$menuId !== ''`

Full menu (`''`) loads every `dacore_menu` row and builds the tree. **Default for new modules is this full tree** with nested `0` → `2` → `1`. A branch id selects only `menuid = X OR parent = X`, then appends **Return back**. Grandchildren of those rows are **not** loaded, so `type => 2` groups under a module-own `$menuId` will not show their leaves. Inner items **MUST** be direct children of the branch id. Use a branch `$menuId` **only** when the user explicitly chose module-own. See [31](31-DACORE-MENU.md).

---

## 14. Pager click is dead, or CRC fails on page 2

`$dotapp().live(event, selector, fn)` calls **`fn(el, e)`**. A handler written as `function (e) { e.currentTarget }` never sees the button — the click is a silent no-op. Fix: `function (el, e)` and `el.getAttribute("data-page")`.

`crcCheck()` compares the encrypted Referer from page load with `$_SERVER['HTTP_REFERER']`. `history.replaceState` of `?page=` / `?logpage=` changes the Referer on the **next** POST → HTTP 400 CRC failed. Stay on the same path. Buttons, not `<a href="?page=">`.

`QueryObject::paginate()['total']` is often 0 (COUNT through `execute()`). Then the UI shows “1–10 of 10” and `last_page = 1`. **MUST** `COUNT(*)` via `all()`.

Law: [40](40-DACORE-LIST-PAGER.md).

---

## 15. Plugin zip: “Installation.php has no version keys” / package version `0.0.0`

DACore does **not** include or execute `Installation.php` when it validates a plugin zip. It **greps the file as text** for quoted semver keys (`'1.0.0' =>` / `"1.0.0" =>`). `self::VERSION =>`, `static::…`, class constants, and other expressions are valid PHP and run on the server, but the scanner sees **zero** keys → reject, package version `0.0.0`.

**Fix:** write every `installer()` / `uninstaller()` key as quoted text in the source (`'1.0.0' =>`). Keep `self::VERSION` **only inside** `Installations@exist!` / `@insert!`. **MUST NOT** use `self` or any similar expression as the **array key**. Rebuild the zip with the handbook packer ([EX-D09](examples/EX-D09-dacore-pack-zip.md)) — copy the `.txt` to `dacore-pack-zip.php`, run it, delete the `.php`. **MUST NOT** invent a new packer.

Law: [35](35-DACORE-INSTALL.md) §2, [00](00-AGENT-CONTRACT.md) §5 item 27.

---

## 16. Encrypted id in the path → Apache “Not Found”

`Crypto::encrypt` is standard base64 (`+` `/` `=`). A DACore-bound edit URL like `/{prefix}/{Module}/products/{token}` percent-encodes `/` as `%2F`. Apache treats that as a **slash** and returns **Not Found** before PHP. The token is still correct AES — the **alphabet** is wrong for a path.

**Fix in the current module:** seal `+/` → `-_`, strip `=`; open before decrypt and still accept leftover standard `{{ enc }}` tokens. **MUST NOT** put `{{ enc }}` into a path `href`. **MUST NOT** patch DACore or `app/parts/` for this.

Law: [11](11-AUTH-AND-CRYPTO.md) §8, [33](33-DACORE-PAGES-AND-UI.md) §13.

---

## Priority order when docs disagree

1. DACore source under `app/modules/DACore/` — **read-only by default**. Do not edit or add files there (updates wipe them) unless the user **themselves** asked and confirmed the wipe ([00](00-AGENT-CONTRACT.md) §1). **MUST NOT propose** that edit.
2. This DACore layer (`30`–`40`)
3. Framework docs (`00`–`22`)
4. Anything else
