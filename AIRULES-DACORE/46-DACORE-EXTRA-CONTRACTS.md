# 46 — DACore reserved `extra1`…`extra5` and peer contracts (**MUST**)

`dacore_modules.extra1`…`extra5` are **short discovery tokens** copied from a package `about.php` at ZIP install/update. They exist so a **host** (CMS, Shop, ERP) can **find installed packs by role** without booting them.

DACore owns the **reserved** `extra1` vocabulary. This file is the **index**: slot grammar, discovery, universal peer rules, and a table of every reserved role.

**Deep contracts** (inputs, outputs, hooks — the density a host and a pack need to interoperate) live in **[46-contracts/](46-contracts/README.md)**. The density template is **[46-contracts/filemanager.md](46-contracts/filemanager.md)**. Agents implementing or calling a role **MUST** open that role’s file, not only this index.

Agents **MUST NOT** invent a competing token (`files`, `media`, `fm`, `theme-pack`) when a reserved role already exists.

A **host** that picks packs **MUST** keep `app/modules/<Host>/AIRULES/` so a pack does not invent routes that host never listens to. When that folder exists, follow **project AIRULES + those files** ([00](00-AGENT-CONTRACT.md) §2n). CMS: `app/modules/CMS/AIRULES/`.

Machine catalog (same tokens): `DACore\Libraries\ExtraContracts` and `DotApp::call('DACore:Plugins@listByContract!', $role, 'v1')`.

Canonical discovery helper: [35](35-DACORE-INSTALL.md) §3c. Sleep law: [03](03-MODULES-AND-ROUTING.md). Admin skins (already shipped): [33](33-DACORE-PAGES-AND-UI.md) §11.

---

## 1. Three extra worlds (**MUST NOT** mix)

| Where | Purpose |
|-------|---------|
| `dacore_modules.extra1`…`extra5` | Package discovery from `about.php` — **this file** |
| `dacore_users_profiles.extra1`…`extra5` | Per-user slots via `DACore:UserPolicy@*` — **not** roles |
| SMS / email sender `extra1`–`extra3` | Driver-private routing tokens — **not** pack roles ([38](38-DACORE-EMAIL.md), [39](39-DACORE-SMS.md)) |

---

## 2. Slot grammar (**MUST**)

| Slot | Meaning | Rule |
|------|---------|------|
| `extra1` | Role | Reserved token from [§5](#5-reserved-extra1-roles-must) **or** private `{lowercase_modulename}.{token}` (a dot is **required**) |
| `extra2` | Contract version | `v1` (current). `v2` only after this file and `ExtraContracts` add it |
| `extra3` | Mode / capability | Enum of that role (tables below) |
| `extra4` | Host family | `generic` \| `cms` \| `shop` \| `erp` when the role uses a family; otherwise omit |
| `extra5` | Qualifier | Role-specific or empty |

**Token rules:** quoted strings in `about.php` (`'filemanager'`), **not** HTML nowdoc. Length ≤ 64. Charset `[a-zA-Z0-9._-]`. No spaces, sentences, secrets, rights names, URLs, or JSON.

**MUST:**

- Omit unused keys (empty columns). A normal Shop / CMS **host** often has **no** extras
- **ASK** when planning a **pack**: “Which **reserved** `extra1` from this file?” Do not invent a synonym
- **ASK** when planning a **host** that picks packs: which reserved role it will list (`filemanager`, `template`, …). Packs **MUST** use that exact string
- Re-install / update the pack after changing extras — flags are stored at install, not read live from disk on every request
- One package = **one** `extra1` role. A suite (shop + CRM + warehouse) is a **host** and omits extras; satellites (payment, shipping, filemanager) are separate packages

**MUST NOT:**

- Set `extra1` of the host’s own role on the host (`template` on the CMS, `filemanager` on the file manager host itself, `shop` on Shop)
- `UPDATE dacore_modules` from your module (installer + `about.php` only)
- Use extras instead of `DACore:Rights@*`
- Put HTML, URLs, JSON blobs, passwords, or a comma-separated list of roles in extra*
- Invent a new reserved `extra1`. If the role is missing: **ASK** and add it here **and** in `ExtraContracts` in a later DACore version (new row, not a silent alias)
- Discover packs with `glob('app/modules/*')`, `include` of `about.php` / `module.init.php`, or `DotApp::call` into the pack **only** to list it

The installer **does not** reject unknown `extra1` (older packs must keep working). Agents **MUST** still use this vocabulary.

---

## 3. How to list packs

Preferred (role + contract, optional mode):

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'filemanager', 'v1');
$full = DotApp::call('DACore:Plugins@listByContract!', 'filemanager', 'v1', 'full');
```

Older slot lookup still works:

```php
$packs = DotApp::call('DACore:Plugins@listByExtra!', 1, 'filemanager');
```

Both are **in-process**, not HTTP — **no CRC**. Empty / invalid token, unknown slot, or extra columns not yet migrated → `[]`. **MUST NOT** call either helper with `''` to mean “all modules”.

Return shape: list of `{module, version, extra1, extra2, extra3, extra4, extra5}` for `status = 1` rows only. Discovery **MUST NOT** boot those modules.

Host settings: native `<select>` or existing `dotSelect2` of that list. Persist the **selected module name** in **the host’s** settings. Zero packs = empty state. One pack = the operator still chooses unless the host’s settings copy explicitly says auto-single.

After the operator picks a module, the host **MAY** `DotApp::call('{Module}:{Role}Contract@capabilities!')`. That **does** wake the provider — only then.

---

## 4. Two integration styles (**MUST NOT** duplicate)

**A. DACore-owned registries** (already exist — **no** competing `extra1`): email senders, SMS senders, notification drivers, webhook drivers, IP geo drivers, dashboard widgets, settings panels. Call `DACore:Email@…!` / `Sms@…!` / `Webhooks@…!` / `IpGeoDriver@…!` / `UiContributions@…!` — [30](30-DACORE-OVERVIEW.md) §3a, [37](37-DACORE-NOTIFICATIONS.md), [38](38-DACORE-EMAIL.md), [39](39-DACORE-SMS.md), [42](42-DACORE-UI-CONTRIBUTIONS.md), [43](43-DACORE-WEBHOOKS.md).

**Forbidden as `extra1`:** `email`, `sms`, `webhook`, `notification`, `ip-geo`, `widget`, `settings-panel`, `backup`.

**B. Peer contracts:** discover with extras, then `DotApp::call('{Module}:{Role}Contract@method!')`. DACore does **not** dispatch the call (unlike `Sms@send`).

Administration skins stay on extras and **fixed files** (no peer controller): `dacore.admin-skin` / `v1` / `css` or `shell-css` — [33](33-DACORE-PAGES-AND-UI.md) §11.

---

## 5. Reserved `extra1` roles (**MUST**)

`extra2` is `v1` until this file adds `v2`. `extra4` when marked “family”: `generic` \| `cms` \| `shop` \| `erp`.

**MUST** implement or call a role from its **deep file** (Contract column). This table is only the map. Density template: [46-contracts/filemanager.md](46-contracts/filemanager.md).

### 5a. Pack-only (fixed files, no `{Role}Contract`)

| extra1 | extra3 | Deep contract |
|--------|--------|---------------|
| `dacore.admin-skin` | `css` \| `shell-css` | [46-contracts/dacore.admin-skin.md](46-contracts/dacore.admin-skin.md) |
| `template` | `site` \| `blog` \| `shop` \| `landing` \| `email-html` | [46-contracts/template.md](46-contracts/template.md) |
| `locale` | language code (`sk`, `en`, …) | [46-contracts/locale.md](46-contracts/locale.md) |

### 5b. Web / CMS peers

| extra1 | extra3 | Controller | Deep contract |
|--------|--------|------------|---------------|
| `filemanager` | `full` \| `picker` \| `storage` | `MediaContract` | [46-contracts/filemanager.md](46-contracts/filemanager.md) |
| `storage` | `local` \| `s3` \| `sftp` | `StorageContract` | [46-contracts/storage.md](46-contracts/storage.md) |
| `dms` | `records` \| `records-workflow` | `DmsContract` | [46-contracts/dms.md](46-contracts/dms.md) |
| `editor` | `html` \| `markdown` \| `blocks` | `EditorContract` | [46-contracts/editor.md](46-contracts/editor.md) |
| `page-builder` | `blocks` \| `sections` | `PageBuilderContract` | [46-contracts/page-builder.md](46-contracts/page-builder.md) |
| `form-builder` | `contact` \| `survey` \| `quiz` | `FormBuilderContract` | [46-contracts/form-builder.md](46-contracts/form-builder.md) |
| `comments` | `threaded` \| `flat` | `CommentsContract` | [46-contracts/comments.md](46-contracts/comments.md) |
| `search` | `sql` \| `fulltext` \| `external` | `SearchContract` | [46-contracts/search.md](46-contracts/search.md) |
| `seo` | `meta` \| `schema` \| `full` | `SeoContract` | [46-contracts/seo.md](46-contracts/seo.md) |
| `sitemap` | `xml` \| `rss` | `SitemapContract` | [46-contracts/sitemap.md](46-contracts/sitemap.md) |
| `translate` | `manual` \| `machine` | `TranslateContract` | [46-contracts/translate.md](46-contracts/translate.md) |
| `captcha` | `image` \| `recaptcha` \| `hcaptcha` \| `turnstile` | `CaptchaContract` | [46-contracts/captcha.md](46-contracts/captcha.md) |
| `cookie-consent` | `notice` \| `cmp` | `ConsentContract` | [46-contracts/cookie-consent.md](46-contracts/cookie-consent.md) |
| `auth-provider` | `oauth` \| `oidc` \| `saml` \| `ldap` \| `social` | `AuthProviderContract` | [46-contracts/auth-provider.md](46-contracts/auth-provider.md) |
| `cdn` | `purge` \| `rewrite` | `CdnContract` | [46-contracts/cdn.md](46-contracts/cdn.md) |
| `image-optimizer` | `local` \| `remote` | `ImageOptContract` | [46-contracts/image-optimizer.md](46-contracts/image-optimizer.md) |
| `page-cache` | `full` \| `fragment` | `PageCacheContract` | [46-contracts/page-cache.md](46-contracts/page-cache.md) |
| `gallery` | `grid` \| `masonry` | `GalleryContract` | [46-contracts/gallery.md](46-contracts/gallery.md) |
| `slider` | `hero` \| `carousel` | `SliderContract` | [46-contracts/slider.md](46-contracts/slider.md) |
| `menu-public` | `tree` \| `flat` | `PublicMenuContract` | [46-contracts/menu-public.md](46-contracts/menu-public.md) |
| `newsletter` | `list` \| `list-segment` | `NewsletterContract` | [46-contracts/newsletter.md](46-contracts/newsletter.md) |
| `analytics` | `pixel` \| `server` | `AnalyticsContract` | [46-contracts/analytics.md](46-contracts/analytics.md) |
| `maps` | `tiles` \| `geocode` | `MapsContract` | [46-contracts/maps.md](46-contracts/maps.md) |

### 5c. Commerce / ERP peers

| extra1 | extra3 | Controller | Deep contract |
|--------|--------|------------|---------------|
| `payment` | `card` \| `bank` \| `wallet` \| `cash` \| `cod` | `PaymentContract` | [46-contracts/payment.md](46-contracts/payment.md) |
| `shipping` | `courier` \| `pickup` \| `rate` | `ShippingContract` | [46-contracts/shipping.md](46-contracts/shipping.md) |
| `tax` | `percent` \| `rules` \| `external` | `TaxContract` | [46-contracts/tax.md](46-contracts/tax.md) |
| `currency` | `table` \| `feed` | `CurrencyContract` | [46-contracts/currency.md](46-contracts/currency.md) |
| `catalog` | `products` \| `products-variants` | `CatalogContract` | [46-contracts/catalog.md](46-contracts/catalog.md) |
| `cart` | `session` \| `api` | `CartContract` | [46-contracts/cart.md](46-contracts/cart.md) |
| `checkout` | `session` \| `api` | `CheckoutContract` | [46-contracts/checkout.md](46-contracts/checkout.md) |
| `inventory` | `qty` \| `lots` | `InventoryContract` | [46-contracts/inventory.md](46-contracts/inventory.md) |
| `warehouse` | `bins` \| `lots` | `WarehouseContract` | [46-contracts/warehouse.md](46-contracts/warehouse.md) |
| `pricing` | `list` \| `promo` | `PricingContract` | [46-contracts/pricing.md](46-contracts/pricing.md) |
| `subscription` | `recurring` | `SubscriptionContract` | [46-contracts/subscription.md](46-contracts/subscription.md) |
| `marketplace` | `vendors` | `MarketplaceContract` | [46-contracts/marketplace.md](46-contracts/marketplace.md) |
| `affiliate` | `partners` | `AffiliateContract` | [46-contracts/affiliate.md](46-contracts/affiliate.md) |
| `reviews` | `stars` | `ReviewsContract` | [46-contracts/reviews.md](46-contracts/reviews.md) |
| `coupon` | `code` | `CouponContract` | [46-contracts/coupon.md](46-contracts/coupon.md) |
| `pos` | `retail` \| `hospitality` | `PosContract` | [46-contracts/pos.md](46-contracts/pos.md) |
| `invoice` | `sales` | `InvoiceContract` | [46-contracts/invoice.md](46-contracts/invoice.md) |
| `accounting` | `ledger` | `LedgerContract` | [46-contracts/accounting.md](46-contracts/accounting.md) |
| `crm` | `contacts` | `CrmContract` | [46-contracts/crm.md](46-contracts/crm.md) |
| `helpdesk` | `tickets` | `HelpdeskContract` | [46-contracts/helpdesk.md](46-contracts/helpdesk.md) |
| `project` | `tasks` | `ProjectContract` | [46-contracts/project.md](46-contracts/project.md) |
| `hr` | `employees` | `HrContract` | [46-contracts/hr.md](46-contracts/hr.md) |
| `asset` | `register` | `AssetContract` | [46-contracts/asset.md](46-contracts/asset.md) |
| `fleet` | `vehicles` | `FleetContract` | [46-contracts/fleet.md](46-contracts/fleet.md) |
| `maintenance` | `workorders` | `MaintenanceContract` | [46-contracts/maintenance.md](46-contracts/maintenance.md) |
| `booking` | `slot` | `BookingContract` | [46-contracts/booking.md](46-contracts/booking.md) |
| `events` | `ticket` | `EventsContract` | [46-contracts/events.md](46-contracts/events.md) |
| `calendar` | `agenda` | `CalendarContract` | [46-contracts/calendar.md](46-contracts/calendar.md) |
| `lms` | `course` | `LmsContract` | [46-contracts/lms.md](46-contracts/lms.md) |
| `forum` | `board` | `ForumContract` | [46-contracts/forum.md](46-contracts/forum.md) |
| `chat` | `live` | `ChatContract` | [46-contracts/chat.md](46-contracts/chat.md) |
| `kb` | `articles` | `KbContract` | [46-contracts/kb.md](46-contracts/kb.md) |
| `workflow` | `bpm` | `WorkflowContract` | [46-contracts/workflow.md](46-contracts/workflow.md) |
| `report` | `table` | `ReportContract` | [46-contracts/report.md](46-contracts/report.md) |
| `bi` | `chart` | `BiContract` | [46-contracts/bi.md](46-contracts/bi.md) |
| `esign` | `session` | `EsignContract` | [46-contracts/esign.md](46-contracts/esign.md) |
| `import-export` | `csv` \| `xml` \| `feed` | `ImportExportContract` | [46-contracts/import-export.md](46-contracts/import-export.md) |
| `barcode` | `code128` | `BarcodeContract` | [46-contracts/barcode.md](46-contracts/barcode.md) |
| `pdf` | `render` | `PdfContract` | [46-contracts/pdf.md](46-contracts/pdf.md) |
| `print` | `job` | `PrintContract` | [46-contracts/print.md](46-contracts/print.md) |
| `label` | `shipping` | `LabelContract` | [46-contracts/label.md](46-contracts/label.md) |

`dms` is **not** `filemanager` — see both deep files.

---

## 6. Universal peer v1 rules (**MUST**)

Applies to every `{Role}Contract` above.

- Controller name is **fixed** (table). Methods are `public static`. Call strings use `!`.
- In-process only. **No CRC** on these helpers (same as `Plugins@listByExtra!`). HTTP upload / picker POST stays on the pack’s own `/api/v1/auth/{Module}/…` + `#DACore:AuthTest@LoginAndCRC!`.
- First method: `capabilities()` → `{ok, contract:'v1', module, modes, …}`. **MUST NOT** throw.
- Discovery **MUST NOT** `DotApp::call` into the pack. Wake it only after the host settings pick.
- Host picker = `listByContract!` + `<select>` / `dotSelect2`. **MUST NOT** `glob(app/modules)`.
- HTML ids = `{{ enc(...) }}` with a unique `$key2`. Decrypt `=== false` → reject. Still check rights / ownership in PHP.
- Replies `{ok:true,…}` / `{ok:false, message:'…'}`. **MUST NOT** leak `getMessage()`, paths outside the jail, secrets, PAN, OTP, or request bodies.
- PHP 7.4+ unless the plan named a higher version: no `match` / `?->` / `str_contains` / union `mixed`.
- Hooks only on a useful side-effect (`stored`, `deleted`, `paid`) — **not** on `list`. Name in the pack `.hooks`. [41](41-MODULE-HOOKS.md).
- Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when the operator deletes the **selected** pack ([DACore `.hooks`](../app/modules/DACore/.hooks)).
- UI picker when `extra3` is `picker` or `full`: host loads `picker_js` / `picker_css` from `capabilities()` and calls **`$dotapp().mediaPicker({ module, … })`**. The pack implements that `$dotapp` fn. **MUST NOT** copy DACore JS into the pack.

---

## 7. Where the deep contracts live

**MUST NOT** treat this index as enough to implement a pack or host integration.

| Need | Open |
|------|------|
| Density template (every other file **MUST** match this depth) | [46-contracts/filemanager.md](46-contracts/filemanager.md) |
| Folder index | [46-contracts/README.md](46-contracts/README.md) |
| One reserved role | [§5](#5-reserved-extra1-roles-must) → that row’s **Deep contract** file |

Each role file **MUST** contain: extras table, `about.php` snippet, `listByContract!` example, extra3/extra5 meaning, `capabilities()` success/fail PHP arrays (peers), every listed method’s input table + success/fail shapes, hooks, MUST NOT, finish gate. Pack-only roles (`dacore.admin-skin`, `template`, `locale`) use the same depth for **paths and how the host loads after pick** instead of `{Role}Contract` methods.

---

## 8. Cross-role notes (pointers only)

Full I/O lives in the role file. These notes only stop agents from picking the wrong extra1:

- **payment / shipping / captcha / search / auth-provider** — [46-contracts/payment.md](46-contracts/payment.md), [shipping.md](46-contracts/shipping.md), [captcha.md](46-contracts/captcha.md), [search.md](46-contracts/search.md), [auth-provider.md](46-contracts/auth-provider.md)
- **template / locale / dacore.admin-skin** — no `{Role}Contract`; see their pack-only files
- **newsletter send** is still `DACore:Email@send!` — [newsletter.md](46-contracts/newsletter.md)
- **maps** is not IP geo — [maps.md](46-contracts/maps.md)
- **catalog** is not the Shop **host** — [catalog.md](46-contracts/catalog.md)

---

## 9. Private extras

A host-only flag that is **not** in [§5](#5-reserved-extra1-roles-must) **MUST** be `{lowercase_modulename}.{token}` (example: `shop.loyalty-tier`). **MUST NOT** take a reserved short name.

---

## 10. Finish gate (this topic)

- Pack `about.php` extras match a reserved row (or a dotted private token)
- Host lists with `listByContract!` (or `listByExtra!`) — never `glob` / `include`
- Host does not set `extra1` to its own role
- Peer calls use the **table** controller name after the operator picked a module
- No `crcCheck()` on `listByContract!` / `listByExtra!` / `capabilities` / upload
- File / record ids in HTML are encrypted
- No write to `dacore_modules` from the host
