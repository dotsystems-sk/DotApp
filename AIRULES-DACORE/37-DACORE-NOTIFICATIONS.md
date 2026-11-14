# 37 — DACore Notifications

In-app inbox owned by DACore. **One system.** Modules only **push**. Operators read in the DACore navbar / history page. Read state is **per user**. This is **not** SMTP mail — outgoing mail is [38](38-DACORE-EMAIL.md).

Sample: [EX-D05](examples/EX-D05-dacore-notifications.md).

---

## API

```php
DotApp::call("DACore:Notifications@push", array $data): bool
```

| Aspect | Detail |
|--------|--------|
| Returns | `bool` — `true` on insert **or** existing `dedupe` |
| `false` when | `$data` invalid, `module`/`title` empty, no recipients, bad enum, or DB write fails |
| Throws | never |
| Logs | nothing — **check `!== true` yourself** |
| Idempotency | **not** an upsert. Optional `dedupe` → second call returns `true` and writes nothing |
| HTTP | **no** public push route — in-process `DotApp::call` only |

**When:** a real event (order created, lockout). Prefer listening to the owner’s `module.{mod}.{name}.hook` from **your** `module.listeners.php` ([41](41-MODULE-HOOKS.md)) then `Notifications@push` — **MUST NOT** from `Installation.php`. **MUST NOT** every request.

---

## `$data` keys

| Key | Type | Required | Default | Notes |
|-----|------|----------|---------|-------|
| `module` | string | **yes** | — | Your module name (`Shop`). Max 50. `[A-Za-z0-9_]` |
| `title` | string | **yes** | — | Short line. Product copy / trans key ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8). Max 190. **MUST NOT** HTML |
| `body` | string | no | `''` | Long text. Same copy rules. **MUST NOT** HTML |
| `icon` | string | no | from `severity` | Remix classes (`ri ri-shopping-cart-line`). No `<` |
| `topic` | string | no | `operations` | **Closed:** `operations` \| `security` \| `system` \| `account` |
| `severity` | string | no | `info` | **Closed:** `info` \| `success` \| `warning` \| `danger` |
| `url` | string | no | `''` | Relative path starting with `/`. No `javascript:` / `//` |
| `urlprefix` | int | no | `0` | `1` = prepend `Config::module('DACore','prefixUrl')` |
| `users` | int[] | * | — | Target user ids. Use this and/or `rights` |
| `rights` | string[] | * | — | Permission strings. DACore fans out to users who have any of them (OR) |
| `dedupe` | string | no | — | Unique key, e.g. `Shop.order.4821`. Max 100 |
| `ref_type` | string | no | `''` | Module object type. Max 50 |
| `ref_id` | string | no | `''` | Module object id. Max 50 |

`*` At least one of `users` / `rights` with a non-empty resolved set. Empty recipients ⇒ `false`.

Do **not** invent topics (`billing`, `ShopOrdersV2`). Put the source in `module`.

---

## Call

```php
$ok = DotApp::call("DACore:Notifications@push", [
    'module' => 'Shop',
    'title' => 'New order #4821',
    'body' => 'Paid 49.90 EUR.',
    'topic' => 'operations',
    'severity' => 'info',
    'icon' => 'ri ri-shopping-cart-line',
    'url' => '/shop/orders/4821',
    'urlprefix' => 1,
    'rights' => ['dotapp.root', 'Shop.orders'],
    'dedupe' => 'Shop.order.4821',
]);
if ($ok !== true) {
    Logger::use()->error('Shop notify failed');
}
```

---

## MUST NOT

- Own notification tables / a second bell UI in **your** module
- `INSERT`/`UPDATE`/`DELETE` on `dacore_notifications` / `dacore_notifications_inbox`
- HTTP endpoint that inserts notifications
- `push` from installer or from every page load
- HTML in `title` / `body`
- Notify with neither `users` nor `rights`

Uninstall **MUST NOT** delete inbox history (audit).

---

## Orchestration (`Notifications@dispatch`)

Explicit fan-out across named drivers. **No** policy engine, user preferences, implicit channels, workflow, retries, or queue. The global feature `notifications` defaults **off**.

```php
DotApp::call("DACore:Notifications@dispatch", [
    'channels' => [
        'inbox' => [
            'module' => 'Shop',
            'title' => 'New order #4821',
            'body' => 'Paid 49.90 EUR.',
            'topic' => 'operations',
            'users' => [$operatorId],
        ],
        'email' => [
            'id' => $senderToken,
            'to' => $email,
            'subject' => 'New order #4821',
            'text' => 'Paid 49.90 EUR.',
        ],
        'sms' => [
            'to' => $msisdn,
            'text' => 'Order #4821 paid.',
        ],
    ],
]): array
```

| Aspect | Detail |
|--------|--------|
| Returns | `['ok' => bool, 'channels' => [key => ['ok' => bool, 'errors' => string[]]], 'errors' => string[]]` |
| `ok` | `true` only when **every** requested channel succeeded |
| Feature off | Stable failure, **zero** `dacore_notification_drivers` reads, **zero** provider autoload, **zero** transport |
| `push` | Unchanged inbox API. Does **not** consult this switch |
| Throws | never |
| HTTP | **no** public dispatch route — in-process `DotApp::call` only |
| Cap | At most **8** explicit channels; payload JSON per channel ≤ 8192 bytes |

Built-in adapters use the **same** `controller_dispatch` path as externals:

| `driver_key` | Stored controller | Delegates to |
|--------------|-------------------|--------------|
| `inbox` | `DACore:Notifications@adapterInbox` | `Notifications@push` / `NotifyStore::push` (bool) |
| `email` | `DACore:Notifications@adapterEmail` | `Email@send` (preserves `true\|list<string>`) |
| `sms` | `DACore:Notifications@adapterSms` | `Sms@send` (preserves envelope) |

Do **not** copy sender/template tables or SMTP/SMS gateways into this registry.

### Registry (in-process, no HTTP)

```php
DotApp::call('DACore:Notifications@registerDriver', [...]);
DotApp::call('DACore:Notifications@unregisterByCreator', 'Shop');
DotApp::call('DACore:Notifications@listDrivers');
DotApp::call('DACore:Notifications@setDriverEnabled', 'inbox', false);
```

| Rule | Detail |
|------|--------|
| Grammar | `driver_key` `[A-Za-z0-9][A-Za-z0-9._-]{0,49}`; controller `Module:Controller@method` |
| Owner | `creator` is immutable; a second module cannot steal a key |
| Contract | `contract_version` **must** be `1` |
| Built-ins | `inbox` / `email` / `sms` stay `creator=DACore`; `unregisterByCreator('DACore')` is refused |
| Deactivation | `setDriverEnabled(..., false)` fires `module.dacore.driver_deactivate.veto` with metadata only; no payload or credentials |
| Cache | `notification_drivers` metadata only — **no** payloads, passwords, or bodies |
| Resolve | One cache section **or** one bound `IN` query for all requested keys — never one query per channel |

### Administration

`{prefixUrl}/dacore/notification-drivers` is a root-only, separately lazy admin leaf. Its GET reads only a paginated metadata projection and never invokes `controller_dispatch`. When the feature is off, GET/POST do not query or mutate the registry. Enabling is immediate; disabling requires graphical confirmation and PHP `StepUp::verify()`, then still passes through the documented deactivation veto. Every action patches rows + pager and shows a toast without reloading.

### Table (Installation owns the migration)

This is the **live DACore column list**, not the installer pattern for your module. Your `Installation.php` **MUST** probe then `CREATE TABLE` without `IF NOT EXISTS` ([07](07-SCHEMA-AND-INSTALL.md) §0).

```sql
CREATE TABLE IF NOT EXISTS `dacore_notification_drivers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `driver_key` varchar(50) NOT NULL,
    `creator` varchar(64) NOT NULL,
    `name` varchar(190) NOT NULL,
    `controller_dispatch` varchar(190) NOT NULL,
    `settings_url` varchar(255) NOT NULL DEFAULT '',
    `contract_version` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
    `enabled` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `driver_key` (`driver_key`),
    KEY `creator` (`creator`),
    -- Query: enabled contract-v1 lookup of explicit driver keys during Notifications@dispatch.
    KEY `enabled_contract_key` (`enabled`,`contract_version`,`driver_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

Seed rows (idempotent `INSERT IGNORE`):

```sql
INSERT IGNORE INTO `dacore_notification_drivers`
(`driver_key`,`creator`,`name`,`controller_dispatch`,`settings_url`,`contract_version`,`enabled`,`created_at`,`updated_at`)
VALUES
('inbox','DACore','Inbox','DACore:Notifications@adapterInbox','',1,1,NOW(),NOW()),
('email','DACore','Email','DACore:Notifications@adapterEmail','',1,1,NOW(),NOW()),
('sms','DACore','SMS','DACore:Notifications@adapterSms','',1,1,NOW(),NOW())
```

Dispatch itself does not fire a business hook; the owning workflow remains responsible for its domain event. Driver deactivation uses the documented veto contract.

---

## Wrong / Right

| Wrong | Right |
|-------|-------|
| `INSERT INTO dacore_notifications …` | `DACore:Notifications@push` |
| `shop_notifications` table + module inbox page | DACore navbar + `{prefix}/dacore/notifications` |
| `push` in `Installation.php` | Call on the event in **your** controller/service |
| `push` on every request | Call once when the event happens |
| `topic => 'orders'` | `topic => 'operations'`, `module => 'Shop'` |
| No `users` and no `rights` | `'rights' => ['dotapp.root', 'Shop.orders']` and/or `'users' => [$id]` |
| Ignoring `false` | `if ($ok !== true) { Logger::use()->error(…); }` |
