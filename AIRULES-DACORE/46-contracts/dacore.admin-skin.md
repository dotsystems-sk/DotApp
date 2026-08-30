# 46 — `dacore.admin-skin` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6. Admin-skin paths already shipped: [33-DACORE-PAGES-AND-UI.md](../33-DACORE-PAGES-AND-UI.md) §11.

This is a **pack / skin** contract (files only). A host and a skin pack must be able to interoperate from this page alone. There is **no** `{Role}Contract` controller.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `dacore.admin-skin` |
| `extra2` | `v1` |
| `extra3` | `css` \| `shell-css` \| `assets` |
| `extra4` | *(omit — this role does not use a host family)* |
| `extra5` | *(omit)* |

```php
'extra1' => 'dacore.admin-skin',
'extra2' => 'v1',
'extra3' => 'css', // or 'shell-css' or 'assets'
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'dacore.admin-skin', 'v1');
$cssOnly = DotApp::call('DACore:Plugins@listByContract!', 'dacore.admin-skin', 'v1', 'css');
$legacy = DotApp::call('DACore:Plugins@listByExtra!', 1, 'dacore.admin-skin');
```

| extra3 | Meaning |
|--------|---------|
| `css` | Presentation only. Pack **MUST** ship `assets/dacore-skin/skin.css`. DACore keeps the built-in shell HTML. |
| `shell-css` | CSS **and** a body skeleton. Pack **MUST** ship `assets/dacore-skin/skin.css` **and** `views/dacore-skin/page.view.php`. |
| `assets` | Class-compatible **file mirror**. Pack **MUST** ship DACore’s relative asset tree starting at `assets/css/core.css`. DACore rewrites chrome URLs from `/assets/modules/DACore/` to this module. **MUST NOT** require `dacore-skin/skin.css`. Different HTML → Extender on `Page@withMenu` / `Menu@generate` ([33](../33-DACORE-PAGES-AND-UI.md) §2). |

| extra5 | Meaning |
|--------|---------|
| *(omit)* | No qualifier. **MUST NOT** put a URL, CDN host, or tracker token here. |

**Kind:** pack / skin (`dacore-skin`). **Controller:** none.

DACore **MUST NOT** set `extra1=dacore.admin-skin` on itself. A `<Host>` that selects an administration-skin pack **MUST NOT** set this token on itself either.

---

## 2. Discovery (host)

1. DACore settings `<select>` / `dotSelect2` from `listByContract!('dacore.admin-skin','v1')`.
2. Persist the **selected module name** in **DACore settings**. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state (built-in shell stays). One pack = the operator **still** chooses. Installation **never** auto-activates a skin.
4. Empty selection = the non-removable DACore default shell. A global Skins switch **MAY** disable the selected package without deleting the stored module name.
5. After pick, DACore **probes the fixed files** on that module (see §3). There is **no** `DotApp::call('{Module}:SkinContract@…')`.
6. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is the **selected** skin.

Discovery **MUST NOT** boot the pack. Listing extras is enough. File probe happens only after the operator selected a module (or when rendering an already-selected skin).

---

## 3. `capabilities()` (host file probe — no controller)

**Call:** none. **MUST NOT** invent `SkinContract` / `AdminSkinContract`.  
**Input:** selected module name (string from settings).  
**MUST NOT** throw out to the operator — a bad skin falls back to the built-in shell.

After pick, the host **probes** `{moduleDir}/assets/dacore-skin/skin.css` and, when `extra3=shell-css`, `{moduleDir}/views/dacore-skin/page.view.php`. When `extra3=assets`, it probes `{moduleDir}/assets/css/core.css` instead. Treat the result as:

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'DarkAdmin',          // exact module name
    'modes' => ['shell-css'],         // extra3 this pack declared (`css` | `shell-css` | `assets`)
    'skin_css' => '/assets/modules/DarkAdmin/dacore-skin/skin.css', // '' when extra3=assets
    'shell_view' => 'DarkAdmin:dacore-skin/page', // '' when extra3=css or assets
]
```

**Failure:** `['ok' => false, 'message' => 'The selected admin skin is not ready.']` — product copy, no `getMessage()`, no disk path.

Treat as failure (then **built-in shell**): missing file, uninstalled module, empty view, incompatible markup, or a throwing view.

---

## 4. Methods (host apply — fixed files)

All in-process. **No CRC**. There is no peer controller. Replies `{ok:true,…}` / `{ok:false, message:'…'}` are the **host’s** readiness / apply result.

Ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}` if a settings form posts a pack id. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. **MUST NOT** `glob('app/modules')`.

### `applyCss($opts)` (host)

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Exact selected module name from `listByContract!` |
| `href` | string | Public href from the probe (`/assets/modules/{Module}/dacore-skin/skin.css`). **MUST NOT** be an external URL |

**Success:**

```php
[
    'ok' => true,
    'href' => '/assets/modules/DarkAdmin/dacore-skin/skin.css',
]
```

The host injects that stylesheet in the **fixed CSS order** (see §6). **Failure:** missing local file, non-module href, external host → `ok:false` and skip the skin CSS.

### `applyShell($opts)` (host, `extra3=shell-css` only)

**Input** `$opts` array:

| Key | Type | Meaning |
|-----|------|---------|
| `module` | string | Exact selected module name |
| `view` | string | Must be `{Module}:dacore-skin/page` (maps to `views/dacore-skin/page.view.php`) |

**Success:**

```php
[
    'ok' => true,
    'html' => '…body skeleton only…',
]
```

**Failure:** `css` mode (no shell view); missing / empty / throwing view → `ok:false`. Host **MUST** render the built-in DACore shell.

The host loads the view with Renderer from **that** module (`setView('DarkAdmin:dacore-skin/page')` or `->module('DarkAdmin')->setView('dacore-skin/page')`). Discovery **MUST NOT** `include` `about.php` or `module.init.php`.

---

## 5. Fixed paths (**MUST**)

| extra3 | Required local files (inside the pack module) |
|--------|-----------------------------------------------|
| `css` | `assets/dacore-skin/skin.css` |
| `shell-css` | `assets/dacore-skin/skin.css` **and** `views/dacore-skin/page.view.php` |
| `assets` | `assets/css/core.css` plus the rest of the DACore-shaped tree the operator expects (`colors.css`, `js/dotapp.shell.js`, …) |

Public URL for the CSS is only `/assets/modules/{Module}/dacore-skin/skin.css`. Runtime / vendor / CDN paths are **not** skins.

---

## 6. CSS order and document ownership (**MUST**)

CSS order is **fixed**:

1. DACore core styles
2. Selected skin `skin.css` (if `ok`)
3. Page-specific styles (`withMenu` `$css`)

DACore **owns** `<html>`, `<head>`, `$dotapp`, Notiflix, and shell scripts. The skin **MUST NOT** emit those. A `css` pack changes presentation only.

---

## 7. Shell view variables (`shell-css`)

A `shell-css` view receives the **same** escaped / pre-rendered shell variables as the built-in DACore page and returns **only** the common body skeleton:

| Variable | Meaning |
|----------|---------|
| `templatedata` | Pre-rendered shell data block |
| `defaultUrl` | Admin default / home URL (already safe) |
| `DACoreMenuLeft` | Pre-rendered left menu HTML |
| `navbar` | Pre-rendered top bar HTML |
| `title` | Page title (already escaped) |
| `body` | Trusted module fragment from `Page@withMenu!` |
| `aichatdiv` | Pre-rendered AI chat mount |
| `assetsPath` | Chrome asset prefix (`/assets/modules/DACore` or the selected assets module), already escaped |

Use `{{ var: $title }}` (and the others) as provided. **MUST NOT** wrap them in a second document. **MUST NOT** emit `<html>`, `<head>`, core styles, `$dotapp` boot, Notiflix, or shell scripts. DACore wraps the fragment in its own document and runtime assets.

---

## 8. Hooks

Fire only after a useful persist — **not** on discovery or every admin GET.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| *(none required from the pack)* | A skin pack does not persist operator data | — |

The **host** (DACore settings) already owns the selection write. The pack **MUST NOT** fire a hook on file probe. Host **MUST** still listen for `module.dacore.plugin_uninstall.veto` when the selected pack is removed. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`theme`, `theme-pack`, `admin-theme`, `skin`)
- `glob('app/modules')` or `include` the pack to discover it
- Auto-activate a skin on ZIP install / update
- Load external CSS, SVG, fonts-from-CDN, or a tracker
- Emit `<html>` / `<head>` / `$dotapp` / Notiflix / shell scripts from `page.view.php`
- Patch DACore core CSS instead of shipping `assets/dacore-skin/skin.css`
- Leak `getMessage()`, raw disk paths, or request bodies
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1
- Host lists with `listByContract!` (`dacore.admin-skin`, `v1`)
- Required files exist at the **fixed** paths
- Operator pick in DACore settings; never auto-activate
- Missing / throwing skin → built-in shell
- CSS order: DACore core → skin → page
- No `crcCheck()` on `listByContract!` / file probe
- No peer controller invented
