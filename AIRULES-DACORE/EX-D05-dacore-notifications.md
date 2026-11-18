# EX-D05 — Push a DACore notification

Rules: [37](../37-DACORE-NOTIFICATIONS.md).

Call **on the event** in **your** module. Not in `Installation.php`. Not every request.

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

| Check | |
|-------|--|
| `!== true` | API never throws or logs |
| `topic` | `operations` \| `security` \| `system` \| `account` only |
| Recipients | `users` and/or `rights` — empty set ⇒ `false` |
| Copy | product language / trans keys — no HTML |

**MUST NOT:** `INSERT` into `dacore_notifications*`. **MUST NOT** build a module inbox UI.
