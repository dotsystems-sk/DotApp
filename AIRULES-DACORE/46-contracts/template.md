# 46 — `template` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6. Renderer: [05-VIEWS-TEMPLATES-ASSETS.md](../05-VIEWS-TEMPLATES-ASSETS.md).

This is a **pack** contract (files only). A host (CMS, Shop) and a theme pack must be able to interoperate from this page alone. There is **no** `{Role}Contract` controller.

**CMS host handbook:** when the pack is for **CMS**, also read **`app/modules/CMS/AIRULES/`** (routes CMS listens to, stems, chrome vars). Follow **project AIRULES + that folder together** ([00](../00-AGENT-CONTRACT.md) §2n). This file is the reserved-role I/O; it does **not** list CMS public catch-alls.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `template` |
| `extra2` | `v1` |
| `extra3` | `site` \| `blog` \| `shop` \| `landing` \| `email-html` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'template',
'extra2' => 'v1',
'extra3' => 'site',
'extra4' => 'cms',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'template', 'v1');
$shopThemes = DotApp::call('DACore:Plugins@listByContract!', 'template', 'v1', 'shop');
```

| extra3 | Meaning |
|--------|---------|
| `site` | Public website chrome + pages under `views/public/` |
| `blog` | Article / listing public views |
| `shop` | Catalog / product / cart public views |
| `landing` | Single-purpose landing views |
| `email-html` | HTML email fragments under `views/public/` — **send** is still `DACore:Email@send!` |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** put a template folder path or URL here. |

**Kind:** pack. **Controller:** none.

The **host** (CMS / Shop) **MUST NOT** set `extra1=template` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('template','v1')`. Optional fourth argument filters `extra3`.
2. Persist the **selected module name** in the **host’s** settings (example: `Config::module('Cms', 'template_module', 'ThemeBlog')`). **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick, the host **probes** `views/public/` on **that** module via Renderer (see §3–§4). There is **no** `TemplateContract`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is the selected theme.

Discovery **MUST NOT** boot the pack. **MUST NOT** `include` / `require` the pack’s `about.php`, `module.init.php`, `Installation.php`, or `settings.php` to list or describe it. **MUST NOT** `glob('app/modules')` or `glob` the pack’s `views/`.

---

## 3. `capabilities()` (host file probe — no controller)

**Call:** none. **MUST NOT** invent `TemplateContract`.  
**Input:** selected module name.  
**MUST NOT** throw — a missing view renders `""`; the host uses its own fallback.

After pick, the host checks that the selected module is still `status = 1` in the `listByContract!` result and that a **named** view exists when it first renders. Treat the result as:

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'ThemeBlog',         // exact module name
    'modes' => ['blog'],             // extra3 this pack declared
    'views_root' => 'public',        // views/public/ — not a disk path
    'assets_base' => '/assets/modules/ThemeBlog/',
]
```

**Failure:** `['ok' => false, 'message' => 'The selected template is not ready.']` — product copy, no `getMessage()`, no absolute path.

---

## 4. Methods (host render — fixed folders)

All in-process. **No CRC**. Replies `{ok:true,…}` / `{ok:false, message:'…'}`. Growing catalogues of **pages** belong to the host and **MUST** paginate; a theme pack is a bounded set of view **names** the host already knows.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`.

### `storeSelection($opts)` (host)

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Exact `module` from a `listByContract!` row. Whitelist against that list. |
| `mode` | string | Optional. Expected `extra3` (`site` / `blog` / `shop` / `landing` / `email-html`) |

**Success:** `['ok' => true, 'module' => 'ThemeBlog']`. Host writes **its** settings only.

**Failure:** unknown module (not in the list); mode mismatch → `ok:false`. **MUST NOT** write `dacore_modules`.

### `renderView($opts)` (host)

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Selected pack module (from host settings, re-checked against `listByContract!`) |
| `name` | string | View stem **allow-list** only (`home`, `article`, `listing`, `product`, `cart`, `mail/order`, …). Host owns the list. **MUST NOT** take a free path from the request. |
| `vars` | array | Data for the view. Every user string **MUST** be `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` **before** `setViewVar`. |

Map `name` → `public/{name}` → `app/modules/{Module}/views/public/{name}.view.php`. Reject `..`, a leading `/`, `\`, or a name not on the allow-list.

**Success:**

```php
[
    'ok' => true,
    'html' => '…Renderer output…',
    'module' => 'ThemeBlog',
    'view' => 'public/article',
]
```

Host load (PHP 7.4):

```php
$html = Renderer::new()
    ->module($module)
    ->setView('public/' . $name)
    ->setViewVar('title', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'))
    ->renderView();
```

Cross-module form: `setView($module . ':public/' . $name)`.

**Failure:** unknown module; illegal `name`; empty `""` from Renderer when the host requires a view → `ok:false` and the **host** fallback view (in the host module). **MUST NOT** scan `views/public/` with `glob`.

`{{ var: }}` does **not** escape. Host **MUST** `htmlspecialchars` before passing. Pack views **MUST NOT** print raw request data.

### `assetUrl($opts)` (host)

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Selected pack |
| `path` | string | Relative file under that pack’s `assets/` (`css/site.css`). No `..` |

**Success:** `['ok' => true, 'url' => '/assets/modules/ThemeBlog/css/site.css']` **only** when the file is under `/assets/modules/{Module}/…`.

**Failure:** path escape, missing file → `ok:false` (no guessed URL).

---

## 5. v1 folders (**MUST**)

| Area | Path inside the pack |
|------|----------------------|
| Public views | `views/public/*.view.php` (layouts: `views/public/layouts/` or `views/layouts/` as the pack documents) |
| Pack assets | `assets/` served as `/assets/modules/{Module}/…` |

`email-html` still uses `views/public/`. The host renders a fragment, then `DACore:Email@send!`. The pack **MUST NOT** invent SMTP.

---

## 6. How the host stores and applies the pick

1. Operator chooses a row from `listByContract!`.
2. Host stores **only** the module name (and optionally the chosen `extra3`) in **host** settings / host table.
3. On a public request, host reads that name, confirms the module is still in `listByContract!` (`status = 1` rows only).
4. Host maps the **route** to a **fixed view stem** (`article`, `home`) — not a filename from the query string.
5. Host calls Renderer on **that** module (`§4 renderView`). Pack JS/CSS go on the public page from `assetUrl` / `$css` / `$js`.
6. If the pack is gone or the view is `""`, host uses **its** fallback — never a silent blank page without product copy.

---

## 7. Hooks

Fire only after a useful persist — **not** on every public render.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| *(none required from the pack)* | A theme pack does not persist host content | — |

The **host** may fire **its** hooks when an article is published. The pack **MUST NOT** fire a hook on `renderView`. Host **MUST** listen for `module.dacore.plugin_uninstall.veto`. [41](../41-MODULE-HOOKS.md).

---

## 8. MUST NOT

- Invent `extra1` (`theme`, `theme-pack`, `skin`, `cms-template`)
- `glob('app/modules')` or `include` the pack to discover it
- `glob` `views/public/` to invent view names
- Set `extra1=template` on the CMS / Shop **host**
- Pass unescaped user strings into `{{ var: }}`
- Invent SMTP for `email-html` (use [38](../38-DACORE-EMAIL.md))
- Leak `getMessage()`, raw disk paths, or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 9. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!`
- Selected module stored in **host** settings only
- Views loaded with Renderer `module()` / `Module:public/…` after pick
- `htmlspecialchars` before every user `setViewVar`
- No `glob` / `include` of `about.php` / `module.init.php`
- No `crcCheck()` on `listByContract!` / Renderer
- No peer controller invented
