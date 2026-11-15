# 47 — DACore Audit Log (DACore 1.0.62)

DACore keeps an administration trail in `dacore_audit_log`: who did what to whom, when, and from which IP address. Operators read it at **System → Audit log** (`{prefixUrl}/dacore/audit-log`).

Your module **writes** into it through one call. It never reads or writes the table itself.

---

## 1. What DACore records on its own

| Area (`category`) | Examples |
|---|---|
| `auth` | sign-in, failed sign-in step, IP lockout, throttled registration |
| `users` | account created / registered, status changed, profile and policy saved |
| `security` | password changed or reset, two-factor changed, device signed out |
| `rights` | groups of an account changed, group created / renamed / deleted, group rights changed |
| `settings` | change of the audit recording policy (always recorded) |
| `module` | rows written by installed modules (this file §2) |

Importance levels: `1` info, `2` notice, `3` warning, `4` critical.

Root chooses which areas and which lowest level are recorded, and how many days records are kept. A viewer needs `dotapp.root` or `DACore.admin.logs`. There is no delete action; rows leave only through retention.

---

## 2. Recording from your module (**MUST** for security-relevant actions)

```php
DotApp::call('DACore:Audit@record!', string $module, string $action, int $level = 1, array $options = []): bool
```

| Argument | Meaning |
|---|---|
| `$module` | Your module folder name (`Shop`). `DACore` is refused. |
| `$action` | Stable machine name, lowercase with dots: `order.refunded`, `price.changed`. Never translated, never built from user text. |
| `$level` | `1` info, `2` notice, `3` warning, `4` critical |
| `$options['target_type']` | What was touched: `order`, `user`, `invoice` |
| `$options['target_id']` | Its id (string, max 64) |
| `$options['target_label']` | Human label shown in the list (order number, email) |
| `$options['message']` | One sentence shown in the list, in the product language of your module |
| `$options['meta']` | Array of details (before/after values). Stored as JSON, max ~4 kB |

```php
DotApp::call('DACore:Audit@record!', 'Shop', 'order.refunded', 3, [
    'target_type' => 'order',
    'target_id' => (string) $orderId,
    'target_label' => $orderNumber,
    'message' => 'Order refunded',
    'meta' => ['amount' => $amount, 'currency' => $currency],
]);
```

**Contract (MUST):**

- Returns `true` only when a row was inserted. `false` is normal when the operator switched that area or level off. **MUST NOT** treat `false` as an error and **MUST NOT** block the action on it.
- The actor, IP address, browser, and time come from the session and the request. You cannot set them.
- **MUST NOT** put passwords, one-time codes, TOTP secrets, API keys, tokens, or message bodies into `message` or `meta`. Keys that look like secrets are replaced with `[removed]`, but that filter is a safety net, not permission.
- Call it **after** the action persisted, on the event. Not in the installer, not on every request, not on a GET.
- **MUST NOT** `SELECT`/`INSERT`/`UPDATE`/`DELETE` `dacore_audit_log` or `dacore_rate_limits` from your module.

**When to record (guide):** changes of money, rights, ownership, published content, exports of personal data, destructive actions, and anything an operator may later have to explain. Not list views, not filters, not autosaves.

---

## 3. Mistakes to avoid

| Wrong | Right |
|---|---|
| `INSERT INTO dacore_audit_log ...` | `DACore:Audit@record!` |
| Recording before the write succeeded | Record after the persist |
| `'action' => 'Refunded order ' . $number` | Stable `order.refunded` + `target_label` |
| Putting the new password / OTP / token into `meta` | Record only that it changed |
| Failing the request because `record` returned `false` | Ignore the return value for flow control |
| Your own "activity log" table for operator actions | Use the DACore audit log |
