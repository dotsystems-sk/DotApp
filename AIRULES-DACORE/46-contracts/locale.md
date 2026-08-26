# 46 — `locale` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6. Translator files: [05-VIEWS-TEMPLATES-ASSETS.md](../05-VIEWS-TEMPLATES-ASSETS.md) §9. Menu overlay JSON: [03-MODULES-AND-ROUTING.md](../03-MODULES-AND-ROUTING.md).

This is a **pack** contract (files only). A host and a language pack must be able to interoperate from this page alone. There is **no** `{Role}Contract` controller.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `locale` |
| `extra2` | `v1` |
| `extra3` | language code: catalog `sk` \| `en` \| `de` \| `cs` \| `pl` \| `hu` \| `fr` \| `es` \| `it`, **or** any `^[a-z]{2}(-[A-Z]{2})?$` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | `ui` \| `content` \| `both` |

```php
'extra1' => 'locale',
'extra2' => 'v1',
'extra3' => 'sk',
'extra4' => 'generic',
'extra5' => 'both',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'locale', 'v1');
$slovak = DotApp::call('DACore:Plugins@listByContract!', 'locale', 'v1', 'sk');
```

| extra3 | Meaning |
|--------|---------|
| `sk` `en` `de` `cs` `pl` `hu` `fr` `es` `it` | Catalog language codes (machine list in `ExtraContracts`) |
| `ll` or `ll-CC` | Any other code matching `^[a-z]{2}(-[A-Z]{2})?$` (example: `en-US`, `pt-BR`) |

| extra5 | Meaning |
|--------|---------|
| `ui` | Admin / menu / host chrome strings only |
| `content` | Public / article / catalog strings only |
| `both` | Both trees (see §5) |

**Kind:** pack. **Controller:** none.

The **host** **MUST NOT** set `extra1=locale` on itself. This role is **not** `translate` (machine/manual API) and **not** DACore `UserPolicy` profile locale.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('locale','v1')`. Optional fourth argument filters `extra3`.
2. Persist the **selected module name** (and the language the operator wants active) in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state (host default locale). One pack = operator still chooses unless host copy says auto-single.
4. After pick, the host **loads JSON files** from **that** module through `Translator::loadLocaleFile` (see §3–§4). There is **no** `LocaleContract`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is the selected locale.

Discovery **MUST NOT** boot the pack. **MUST NOT** `include` `about.php` / `module.init.php`. **MUST NOT** `glob('app/modules')`.

---

## 3. `capabilities()` (host file probe — no controller)

**Call:** none. **MUST NOT** invent `LocaleContract`.  
**Input:** selected module name.  
**MUST NOT** throw — `loadLocaleFile` **silently skips** a missing file; the host must still treat a required miss as `ok:false`.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'LocaleSk',          // exact module name
    'modes' => ['sk'],               // extra3 (language code)
    'scope' => 'both',               // extra5
    'translator_locale' => 'sk_sk',  // normalized (see §5)
    'files' => ['sk_sk.json', 'content/sk_sk.json'],
]
```

**Failure:** `['ok' => false, 'message' => 'The selected language pack is not ready.']` — product copy, no `getMessage()`, no disk path.

---

## 4. Methods (host apply — translation files)

All in-process. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` if a settings form posts a pack id. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`.

### `storeSelection($opts)` (host)

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Exact `module` from a `listByContract!` row |
| `locale` | string | Optional override. Default = pack `extra3` normalized per §5 |

**Success:** `['ok' => true, 'module' => 'LocaleSk', 'translator_locale' => 'sk_sk']`.

**Failure:** module not in the list; illegal `extra3` → `ok:false`. **MUST NOT** write `dacore_modules`.

### `applyLocale($opts)` (host)

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Selected pack |
| `scope` | string | `ui` \| `content` \| `both` — **MUST NOT** load a tree the pack’s `extra5` does not declare |
| `translator_locale` | string | Normalized locale (`sk_sk`, `en_us`) |

**Success:**

```php
[
    'ok' => true,
    'translator_locale' => 'sk_sk',
    'loaded' => ['sk_sk.json', 'content/sk_sk.json'],
]
```

Host apply (PHP 7.4) **after** pick only:

```php
Translator::loadLocaleFile($module . ':sk_sk.json', 'sk_sk');
Translator::loadLocaleFile($module . ':content/sk_sk.json', 'sk_sk');
Translator::setLocale('sk_sk');
```

Use `Translator::has($key, $locale)` if the host must detect a miss. A miss returns the source string; there is **no** fallback chain — **MUST NOT** patch DACore / core Translator to add one.

**Failure:** required file missing for the declared `extra5`; illegal scope → `ok:false`. The host keeps the previous locale.

### `menuOverlay($opts)` (host, `ui` / `both` only)

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Selected pack |
| `ll` | string | Two-letter code (`sk`) for optional `menu_{ll}.json` |

**Success:** `['ok' => true, 'file' => 'LocaleSk:menu_sk.json']` when that small overlay exists (256 KiB cap — [03](../03-MODULES-AND-ROUTING.md)).

**Failure:** `extra5=content`; missing overlay → `ok:false` (not fatal; menu keys stay as source text).

---

## 5. Files the pack MUST ship

Normalize `extra3` → `{translator_locale}` and `{file}`:

| extra3 | `{translator_locale}` | `{file}` |
|--------|------------------------|----------|
| `en` | `en_us` | `en_us.json` |
| `ll` (other two letters) | `{ll}_{ll}` | `{ll}_{ll}.json` (`sk` → `sk_sk.json`) |
| `ll-CC` | `{ll}_{cc}` lowercased | `{ll}_{cc}.json` (`sk-SK` → `sk_sk.json`) |

| extra5 | Required under `app/modules/{Module}/translations/` |
|--------|-----------------------------------------------------|
| `ui` | `{file}` (UI / chrome keys) |
| `content` | `content/{file}` |
| `both` | `{file}` **and** `content/{file}` |

Optional when `extra5` is `ui` or `both`: `translations/menu_{ll}.json` (or the `Module:menu_{ll}.json` descriptor form in [03](../03-MODULES-AND-ROUTING.md)) for DACore menu / widget labels only.

JSON keys are the **source text**, lowercased on lookup. Placeholders: `{{ arg0 }}`, `{{ arg1 }}`, … ([05](../05-VIEWS-TEMPLATES-ASSETS.md) §9).

**MUST NOT** put SMTP credentials, API keys, or rights names in the JSON.

---

## 6. How the host applies locale after pick

1. Operator chooses a `locale` pack from `listByContract!`.
2. Host stores the module name + normalized `translator_locale` in **host** settings / DSM (`DSM::use('Cms')`) — **not** `$_SESSION`.
3. On a request that should use that language, host calls `applyLocale` (Translator load + `setLocale`).
4. Public views still `htmlspecialchars` user content. Translator replaces **keys**, not HTML safety.
5. If the pack is uninstalled or a required file is missing, host reverts to its default locale and shows a toast / empty state — **MUST NOT** silently invent strings from another pack.

---

## 7. Hooks

Fire only after a useful persist — **not** on every `trans()`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| *(none required from the pack)* | Loading JSON is not a persist | — |

Host **MUST** listen for `module.dacore.plugin_uninstall.veto`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`i18n`, `language`, `lang`, `l10n`)
- Invent a `LocaleContract` or patch DACore / core `Translator`
- Invent SMTP or a mail gateway because a pack is “Slovak” ([38](../38-DACORE-EMAIL.md) is unrelated)
- `glob('app/modules')` or `include` the pack to discover it
- Put secrets in `extra*` or translation JSON
- Leak `getMessage()`, raw disk paths, or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1 (`extra3` is a language code, `extra5` is `ui`\|`content`\|`both`)
- Host lists with `listByContract!`
- Required JSON files exist at §5 paths
- Host applies with `Translator::loadLocaleFile` + `setLocale` **after** pick
- No core Translator patch; no SMTP
- No `glob` / `include` of `about.php` / `module.init.php`
- No `crcCheck()` on `listByContract!` / `loadLocaleFile`
- No peer controller invented
