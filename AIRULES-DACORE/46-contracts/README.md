# 46-contracts — per-role v1 deep contracts

**Index and universal rules:** [../46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md) (§2 slot grammar, §3 discovery, §6 peer rules).

**Density template:** [filemanager.md](filemanager.md). Every other file in this folder **MUST** match that depth: extras, `about.php` / discovery, `capabilities()` success/fail shapes (or pack-only load steps), each method’s inputs and outputs, hooks, MUST NOT, finish gate.

Agents **MUST NOT** invent a new `extra1`. Missing role → ASK, then add to 46 + `ExtraContracts` + a new file here in a later DACore version.

---

## Pack-only (no `{Role}Contract`)

| extra1 | File |
|--------|------|
| `dacore.admin-skin` | [dacore.admin-skin.md](dacore.admin-skin.md) |
| `template` | [template.md](template.md) |
| `locale` | [locale.md](locale.md) |

## Web peers

| extra1 | File |
|--------|------|
| `filemanager` | [filemanager.md](filemanager.md) |
| `storage` | [storage.md](storage.md) |
| `dms` | [dms.md](dms.md) |
| `editor` | [editor.md](editor.md) |
| `page-builder` | [page-builder.md](page-builder.md) |
| `form-builder` | [form-builder.md](form-builder.md) |
| `comments` | [comments.md](comments.md) |
| `search` | [search.md](search.md) |
| `seo` | [seo.md](seo.md) |
| `sitemap` | [sitemap.md](sitemap.md) |
| `translate` | [translate.md](translate.md) |
| `captcha` | [captcha.md](captcha.md) |
| `cookie-consent` | [cookie-consent.md](cookie-consent.md) |
| `auth-provider` | [auth-provider.md](auth-provider.md) |
| `cdn` | [cdn.md](cdn.md) |
| `image-optimizer` | [image-optimizer.md](image-optimizer.md) |
| `page-cache` | [page-cache.md](page-cache.md) |
| `gallery` | [gallery.md](gallery.md) |
| `slider` | [slider.md](slider.md) |
| `menu-public` | [menu-public.md](menu-public.md) |
| `newsletter` | [newsletter.md](newsletter.md) |
| `analytics` | [analytics.md](analytics.md) |
| `maps` | [maps.md](maps.md) |

## Commerce / ERP peers

| extra1 | File |
|--------|------|
| `payment` | [payment.md](payment.md) |
| `shipping` | [shipping.md](shipping.md) |
| `tax` | [tax.md](tax.md) |
| `currency` | [currency.md](currency.md) |
| `catalog` | [catalog.md](catalog.md) |
| `cart` | [cart.md](cart.md) |
| `checkout` | [checkout.md](checkout.md) |
| `inventory` | [inventory.md](inventory.md) |
| `warehouse` | [warehouse.md](warehouse.md) |
| `pricing` | [pricing.md](pricing.md) |
| `subscription` | [subscription.md](subscription.md) |
| `marketplace` | [marketplace.md](marketplace.md) |
| `affiliate` | [affiliate.md](affiliate.md) |
| `reviews` | [reviews.md](reviews.md) |
| `coupon` | [coupon.md](coupon.md) |
| `pos` | [pos.md](pos.md) |
| `invoice` | [invoice.md](invoice.md) |
| `accounting` | [accounting.md](accounting.md) |
| `crm` | [crm.md](crm.md) |
| `helpdesk` | [helpdesk.md](helpdesk.md) |
| `project` | [project.md](project.md) |
| `hr` | [hr.md](hr.md) |
| `asset` | [asset.md](asset.md) |
| `fleet` | [fleet.md](fleet.md) |
| `maintenance` | [maintenance.md](maintenance.md) |
| `booking` | [booking.md](booking.md) |
| `events` | [events.md](events.md) |
| `calendar` | [calendar.md](calendar.md) |
| `lms` | [lms.md](lms.md) |
| `forum` | [forum.md](forum.md) |
| `chat` | [chat.md](chat.md) |
| `kb` | [kb.md](kb.md) |
| `workflow` | [workflow.md](workflow.md) |
| `report` | [report.md](report.md) |
| `bi` | [bi.md](bi.md) |
| `esign` | [esign.md](esign.md) |
| `import-export` | [import-export.md](import-export.md) |
| `barcode` | [barcode.md](barcode.md) |
| `pdf` | [pdf.md](pdf.md) |
| `print` | [print.md](print.md) |
| `label` | [label.md](label.md) |
