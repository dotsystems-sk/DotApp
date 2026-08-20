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

**When:** a real event (order created, lockout). **MUST NOT** from `Installation.php`. **MUST NOT** every request.

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
