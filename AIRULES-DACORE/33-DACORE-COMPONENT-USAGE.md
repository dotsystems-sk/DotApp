# 33 — DACore component CSS usage

Use this when choosing **classes** for admin UI. Page shell, grid, and pager **law** stay in [33-DACORE-PAGES-AND-UI.md](33-DACORE-PAGES-AND-UI.md) and [40-DACORE-LIST-PAGER.md](40-DACORE-LIST-PAGER.md). Compact reminder: `AIRULES/cursor/rules/30-dacore-component-usage.mdc`.

## What `core.css` contains

The admin head always loads `colors.css` then **`/assets/modules/DACore/css/core.css`**. That file is the **only globally loaded production CSS** for shared components.

| Region | Source | Role |
|--------|--------|------|
| Dedicated freeze | `assets/css/build/core-legacy.freeze.css` | Historical minified snapshot (development-only). `--write` reads this, never generated `core.css` |
| Legacy payload | `assets/colors.css` + `assets/css/components/*` (except `chat-embed.css`) | Editable sources. Current minify does **not** reproduce the freeze artifact |
| V2 append | `assets/css/components-v2/*` | Direct `dacore-` API for **new or migrated** screens |
| Boundary | `/* DACore v2 shared components */` | Generated `core.css` prefix before this comment must equal the freeze |

Rebuild: `php app/modules/DACore/tools/css-build.php --check` / `--write`. `--check --target=legacy` confirms the dedicated freeze file and the generated `core.css` prefix. `--audit-source` reports that editable sources do **not** reproduce the historical minifier artifact. Do not add a globally linked `components-v2.css`.

**Feature CSS** (file manager first) is **not** in `core.css`. Load it only on screens that need it, or ship it with `-WithFileManager`.

**Production ZIP:** ships generated `core.css` plus only two extra component files that live PHP still links (`tabs-pills.css`, `vendor-select2.css`). Other `assets/css/components/` files stay in the development tree as editable builder inputs. Do not copy the whole directory into a package.

## Direct `dacore-` API (v2)

New public classes start with `dacore-`. They do **not** require a demo/page wrapper.

### Tabs — real GET links

```html
<nav class="dacore-tabs" aria-label="Section">
  <a class="dacore-tab is-active" href="{prefix}/{Module}/items" aria-current="page">Items</a>
  <a class="dacore-tab" href="{prefix}/{Module}/items/settings">Settings</a>
  <a class="dacore-tab is-disabled" aria-disabled="true">Archived</a>
</nav>
```

**MUST:** real `<a href>` GET subpages. **MUST NOT:** `data-bs-toggle="tab"`, `.tab-pane.fade`, `bootstrap.bundle.js`, wrapping in `.ui-tabs-pills-page`.

Existing plugins/settings/debugger screens keep `.ui-tabs-pills-page` + `.nav-tabs`. Do not unwrap those onto bare `.nav` / `.nav-link` (navbar collision).

### Pager

Use the audited classes: `card-footer dacore-list-pager`, `dacore-list-pager-summary`, `dacore-list-pager-nav`. Rules live in `core.css` (v2 copy) **and** still in `users.css`. Law: [40-DACORE-LIST-PAGER.md](40-DACORE-LIST-PAGER.md). Do not restyle global `.pagination`.

### Tiny primitives

`.dacore-toolbar`, `.dacore-empty`, `.dacore-card-actions` — optional new clusters. They do not change `.card` / `.btn`.

## Feature bundle — file manager

| Load | Path |
|------|------|
| Generated bundle the JS injects | `/assets/modules/DACore/css/features/file-manager.css` |
| JS | `/assets/modules/DACore/js/pages/file-manager/dot-file-manager.js` (and the two opener scripts) |

Existing root class is **`.dfmgr`**. Do not put these rules in `core.css`. Historical `css/pages/file-manager/*.css` files are **build inputs only** and stay out of the ZIP. Default production ZIP omits the feature; `-WithFileManager` includes the generated CSS plus the three JS files.

## Semantic wrapper vs accidental page wrapper

| OK | Forbidden as an API |
|----|---------------------|
| `.dacore-tabs` | `.ui-tabs-pills-page`, `.ui-*-page`, `.extended-ui-*-page` |
| `.dacore-list-pager` | Copying demo HTML from `assets/pages/` into a live view |
| Module prefix `{modulename}_*` for **new** look | Global `.d-none`, bare `.nav-link`, restyling `.btn` / `.card` / `.form-control` |

Page wrappers in legacy CSS are **demo locks**. Do not promote them.

## Forbidden broad selectors

**MUST NOT** add or unprefix:

- `.d-none`
- bare `.nav`, `.nav-link`, `.nav-tabs` appearance for new work (use `.dacore-tabs`)
- global restyles of `.btn`, `.card`, `.form-control`, `.pagination`
- `data-bs-toggle="tab"` / Bootstrap JS tabs
