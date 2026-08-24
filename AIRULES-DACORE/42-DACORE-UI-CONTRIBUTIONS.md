# 42B — DACore UI contributions (dashboard widgets and settings panels)

**Read this when a module adds a card to the DACore home dashboard or a settings panel opened from DACore Settings.** Mail is [38](38-DACORE-EMAIL.md). SMS is [39](39-DACORE-SMS.md). Skins stay package extras. This file is the contract for **lazy, disabled-by-default** UI contributions.

In-process only: `DotApp::call('DACore:UiContributions@registerWidget!')` (and the sibling unregister / settings-panel methods). No HTTP register route. No CRC. Linux is case-sensitive — keep `UiContributions.php`.

DACore owns the GET routes, Installation DDL, and `DacoreRegistryCatalog` wiring. Contribution modules **MUST NOT** patch those files.

---

## 1. Two registries, two feature switches

| Kind | Table | Feature switch | Cache section | Render moment |
|------|--------|----------------|---------------|---------------|
| Dashboard widget | `dacore_dashboard_widgets` | `widgets` | `dashboard_widgets` | DACore home (`Login::index`) when the switch is on |
| Settings panel | `dacore_settings_panels` | `settings_panels` | `settings_panels` | Dedicated GET only. The main Settings page shows **cards/links**, never provider HTML |

Both switches default **off** (`extensions_widgets_enabled`, `extensions_settings_panels_enabled` in `DacoreSettings`). Rows also default **`enabled = 0`**. A module that registers a contribution does **not** appear until an operator turns the group on **and** enables the row.

`DacoreRegistryCache::featureEnabled('widgets'|'settings_panels')` is checked **before** any table read or `DotApp::call` to a provider. A disabled group is an empty dashboard / hidden card list — no provider controllers run.

---

## 2. Register / unregister

```php
DotApp::call('DACore:UiContributions@registerWidget!', [
    'widget_key' => 'shop.dashboard.orders', // stable; not table id
    'creator' => 'Shop',                     // module folder; uninstall uses this
    'title' => 'Open orders',
    'controller_render' => 'Shop:Dashboard@ordersWidget',
    'required_right' => 'Shop.orders.view',  // empty = every logged-in operator
    'settings_url' => '/dacore/shop/settings', // optional relative admin path
    'sort_order' => 10,
    'cache_ttl' => 60,                       // seconds; 0 = no HTML cache
    'contract_version' => 1,
]);

DotApp::call('DACore:UiContributions@registerSettingsPanel!', [
    'panel_key' => 'shop.settings.general',
    'creator' => 'Shop',
    'title' => 'Shop',
    'controller_render' => 'Shop:Settings@dacorePanel',
    'required_right' => 'Shop.settings.view',
    'settings_url' => '/dacore/shop/settings',
    'sort_order' => 10,
    'contract_version' => 1,
]);
```

Uninstall (Installation `uninstaller()`):

```php
DotApp::call('DACore:UiContributions@unregisterWidgetsByCreator!', 'Shop');
DotApp::call('DACore:UiContributions@unregisterSettingsPanelsByCreator!', 'Shop');
```

Rules:

- Idempotent by `widget_key` / `panel_key`. Same creator updates title, controller, right, URL, order, TTL.
- Another `creator` **MUST NOT** hijack the key.
- `contract_version` **MUST** be `1` for this runtime. Other values are refused.
- `controller_render` is `Module:Controller@method` (no trailing `!`). DACore appends `!` on dispatch so the provider module `module.init.php` is not loaded as a side effect of `DotApp::call`.
- `settings_url` is an optional relative admin path (`/…`). Protocol-relative and off-site URLs are dropped.
- Register **does not** store secrets, HTML, or request bodies.
- New rows stay **disabled**. Do not pass `enabled` to self-activate.

---

## 3. Provider return contract

Both widget and panel controllers are **static**, take **no request**, and return:

```php
return [
    'html' => '<p>Open orders: 12</p>',              // string, ≤ 100000 bytes
    'css'  => ['/assets/modules/Shop/css/dash.css'], // relative paths only
    'js'   => ['/assets/modules/Shop/js/dash.js'],
];
```

DACore normalizes that payload (`DashboardWidgetsStore::normalizeOutput` / `SettingsPanelsStore::normalizeOutput`):

- `html` must be a string without NUL. Oversized HTML is **rejected** (not truncated mid-tag).
- `css` / `js` must be arrays of at most 16 relative paths starting with `/`, not `//`, with no `..`, `\`, or `:`.
- Extra keys are ignored. Missing/invalid shape → the contribution is skipped (widget) or replaced with product copy (panel).
- Provider HTML is inserted as markup. Escape operator text **in the provider**.
- One provider exception is reported on the catch bus (`CatchBus::reportCatch`) and **MUST NOT** fail the dashboard. Panels show a generic “could not be loaded” message — never `$e->getMessage()`.

Widgets with `cache_ttl > 0` store **successful** normalized payloads in DACore's existing `Cache::use('DAcore')` namespace. The cache identity includes the widget key **and** the current operator id plus a rights fingerprint. Errors are never cached. Privileged HTML from one operator must not appear for another.

---

## 4. Dashboard (widgets)

`Login::index` (`needLogged`, `{prefixUrl}{defaultUrl}`):

1. If `widgets` is off → empty product dashboard, no provider calls.
2. Resolve enabled contract-v1 rows through `DacoreRegistryCache::section('dashboard_widgets', …)` with a bounded SQL fallback (`LIMIT 100`).
3. Prefetch `Auth::permissions()` **once** (and `Auth::can(['dotapp.root'])` once). Filter in memory. Empty `required_right` is visible to every logged-in operator. `dotapp.root` sees all enabled rows. **MUST NOT** call `Auth::can` inside the render loop.
4. Call matching controllers lazily with `DotApp::call($controller . '!')` in `sort_order`, `id` order.
5. Merge/dedupe CSS/JS into `DACore:Page@withMenu!`.
6. Empty list still renders a finished home page (heading, padding, sidebar). Not a blank title-only shell.

---

## 5. Settings panels

The root Settings page (`Settings::settingsPage`) lists enabled panels the operator may open as **cards**. It **MUST NOT** call `controller_render` / `DotApp::call` for providers.

### Expected GET route (main model)

```
GET {prefixUrl}/dacore/settings/panel/{key}
#DACore:AuthTest@needLogged!
DACore:Settings@settingsPanelPage!
```

`{key}` is `panel_key` (stable slug, not table id). Pass `withMenu` `$currentFile` = `{prefixUrl}/dacore/settings` so the Settings leaf stays active.

`Settings::settingsPanelPage`:

- Requires a logged-in operator.
- Requires the `settings_panels` switch.
- Loads **that** row only (cache scan or indexed exact fallback).
- Enforces `dotapp.root` **or** `required_right` via the prefetched permission map.
- Calls the provider **once** for that panel.
- Renders `users/settings_panel` inside `DACore:Page@withMenu!`.
- **MUST NOT** host a DACore save form. The provider owns its authenticated POST.

### Root administration

`{prefixUrl}/dacore/ui-extensions` shows only metadata for widgets and panels in separate COUNT + LIMIT pagers; it never renders provider HTML. It shows the current skin as one summary and links to Settings for selection instead of dumping the skin catalog. Feature-off lists do not query their registry. Enabling is immediate; disabling requires graphical confirmation and PHP `StepUp::verify()`. Actions patch rows + pager and toast without reloading.

---

## 6. Lazy cache rules

- `cacheRows()` is metadata only (keys, titles, controllers, rights, order, TTL). No HTML.
- Catalog (main model) compiles `dashboard_widgets` / `settings_panels` from `cacheRows()` only when the matching feature switch is on.
- A **valid** cache file with an empty section means “known empty” and skips the DB fallback. Wire `cacheRows()` before relying on optimized requests.
- `register` / `unregisterByCreator` / `setEnabled` call `DacoreRegistryCache::rebuildIfPresent()`.
- Feature-off paths **MUST NOT** call provider controllers.

---

## 7. Security cautions

- No secrets in the registry tables or catch-bus context.
- No numeric row ids in dashboard/settings HTML; cards use `panel_key` in the path.
- Rights checks use the in-memory grant map, not a query per card.
- Widget HTML cache **MUST** include operator id or a rights fingerprint.
- Asset URLs are same-origin relative paths only.
- Public controller methods in DACore start with `CRCchecking —`. These APIs are not routes (`none`).

---

## 8. SQL (Installation — main model)

Do **not** apply this from a contribution module. Installation owns the migration. `enabled` defaults to `0`. `contract_version` defaults to `1`. No backfill (new tables). Engine InnoDB, `utf8mb4`.

### `dacore_dashboard_widgets`

| Column | Type | Default | Notes |
|--------|------|---------|-------|
| `id` | int(11) AUTO_INCREMENT | | PRIMARY KEY |
| `widget_key` | varchar(64) NOT NULL | | UNIQUE. Stable slug. |
| `creator` | varchar(64) NOT NULL | | Immutable module folder. KEY `creator`. |
| `title` | varchar(190) NOT NULL | | Operator label. |
| `controller_render` | varchar(190) NOT NULL | | `Module:Controller@method`, no `!`. |
| `required_right` | varchar(190) NOT NULL | `''` | Empty = every logged-in operator. |
| `settings_url` | varchar(255) NOT NULL | `''` | Optional relative admin path. |
| `sort_order` | int(11) NOT NULL | `0` | Stable order with `id`. |
| `cache_ttl` | int(10) UNSIGNED NOT NULL | `0` | Seconds. `0` = do not cache HTML. |
| `contract_version` | smallint(5) UNSIGNED NOT NULL | `1` | Runtime supports `1` only. |
| `enabled` | tinyint(1) NOT NULL | `0` | Register must not self-activate. |
| `created_at` | datetime NOT NULL | | |
| `updated_at` | datetime NOT NULL | | |

Index `enabled_contract_sort` (`enabled`,`contract_version`,`sort_order`,`id`) — query: dashboard resolve enabled contract-v1 widgets in stable order.

### `dacore_settings_panels`

Same as widgets **without** `cache_ttl`. Unique `panel_key`. Index `enabled_contract_sort` (`enabled`,`contract_version`,`sort_order`,`id`) — query: settings page list enabled contract-v1 panels in stable order.

Backfill: none. Existing installs get empty tables; rows appear when modules register (still `enabled = 0` until an operator enables them).
