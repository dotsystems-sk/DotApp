# 46 — `translate` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a pack must be able to interoperate from this page alone. This is **not** `locale` (language **files**; extra3 is a language code). See [locale.md](locale.md) when that file exists; extras for locale stay on the parent table.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `translate` |
| `extra2` | `v1` |
| `extra3` | `manual` \| `machine` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'translate',
'extra2' => 'v1',
'extra3' => 'machine',
'extra4' => 'generic',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'translate', 'v1');
$machine = DotApp::call('DACore:Plugins@listByContract!', 'translate', 'v1', 'machine');
```

| extra3 | Meaning |
|--------|---------|
| `manual` | Glossary / operator table. Missing row → `ok:false`. No remote provider required |
| `machine` | Pack calls a provider it configured. API keys stay in pack config |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | Unused in v1. **MUST NOT** invent a qualifier (`deepl`, `google`) as `extra5` |

**Kind:** peer. **Controller:** `{Module}:TranslateContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=translate` on itself.

A **locale pack** uses `extra1=locale`, extra3 = `sk` / `en` / …, extra5 = `ui` \| `content` \| `both`. That pack has **no** `TranslateContract`. **MUST NOT** set `extra1=translate` on a locale zip.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('translate','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':TranslateContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:TranslateContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'Translator',        // exact module name
    'modes' => ['machine'],          // extra3 this pack actually implements
    'from' => ['en', 'sk', 'de'],    // source codes this pack accepts
    'to' => ['en', 'sk', 'de'],      // target codes
    'max_chars' => 8000,
]
```

**Failure:** `['ok' => false, 'message' => 'Translation is not ready.']` — product copy, no `getMessage()`.

`from` / `to` **MUST NOT** include API keys, account ids, or provider URLs.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

### `translate($text, $from, $to)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$text` | string | Source text. Host already validated. Length ≤ `max_chars`. Empty → `ok:false` |
| `$from` | string | Source code. Must be in `capabilities.from` |
| `$to` | string | Target code. Must be in `capabilities.to`. Same as `$from` → `ok:false` or echo `$text` with `ok:true` — pack picks one and documents it |

On HTTP, the host/pack reads `$text` with `$request->data(true)` (original), then calls this helper. The helper itself has no CRC.

**Success:**

```php
[
    'ok' => true,
    'text' => 'Katalóg na jeseň',
]
```

**Failure:** unknown language, oversize, missing glossary row (`manual`), provider error (`machine`) → `['ok' => false, 'message' => 'Could not translate this text.']`.

**MUST NOT** return the provider key, request body dump, or `getMessage()`.

The host `htmlspecialchars` `text` before `{{ var: }}` when it prints the result.

---

## 5. Not a locale pack

| Role | What it is |
|------|------------|
| `locale` | File pack: UI / content strings on disk. extra3 = language code |
| `translate` | Peer API: turn `$text` from `$from` into `$to` |

A `<Host>` that ships Slovak UI files is `locale`. A `<Host>` that calls a glossary or machine API is `translate`. **MUST NOT** merge the extras.

---

## 6. Secrets (**MUST**)

Provider tokens live in the pack’s config / secrets — **not** in `about.php` extra1–extra5, **not** in `capabilities()`, **not** in `translate` replies, **not** in hooks.

A failed remote call reports `dotapp.catch` through the pack helper, then `ok:false` product copy.

---

## 7. Language whitelist

`$from` / `$to` are short codes from `capabilities` lists (typically ISO 639-1). **MUST NOT** accept a free-text locale from the request as a SQL/file path.

v1 has no `detect($text)` method.

---

## 8. Hooks

Fire only after a useful persist — **not** on every machine `translate`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.translate_stored.hook` | Manual glossary row saved | `from`, `to`, `chars` (int) |

**MUST NOT** put source text, translated text, or API keys in the payload. A one-shot `machine` call **MUST NOT** fire a hook (flood). Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`i18n`, `l10n`, `locale` as this contract)
- Set `extra1=translate` on a locale files pack
- `glob('app/modules')` or `include` the pack to discover it
- Put API keys in extras, capabilities, or replies
- Leak `getMessage()` or the source text on the hook bus
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1 (`translate`, not `locale`)
- Host lists with `listByContract!`
- `translate` returns `{ok, text}` only
- No keys in extras / replies
- Hooks named in `.hooks` only if a glossary persist fires
- No `crcCheck()` on `capabilities` / `translate`
