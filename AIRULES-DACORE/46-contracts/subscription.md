# 46 — `subscription` / v1

Parent index: [46-DACORE-EXTRA-CONTRACTS.md](../46-DACORE-EXTRA-CONTRACTS.md). Universal peer rules: parent §6.

A `<Host>` and a recurring-billing pack must be able to interoperate from this page alone. Machine catalog: `DACore\Libraries\ExtraContracts` role `subscription`, controller `SubscriptionContract`.

This role is **recurrence state**. Card capture / refund is `payment`. Mail is `DACore:Email@send!`. **MUST NOT** invent SMTP or store PAN.

---

## 1. Role

| Slot | Required value |
|------|----------------|
| `extra1` | `subscription` |
| `extra2` | `v1` |
| `extra3` | `recurring` |
| `extra4` | `generic` \| `cms` \| `shop` \| `erp` |
| `extra5` | empty |

```php
'extra1' => 'subscription',
'extra2' => 'v1',
'extra3' => 'recurring',
'extra4' => 'shop',
'extra5' => '',
```

```php
$packs = DotApp::call('DACore:Plugins@listByContract!', 'subscription', 'v1');
$recurring = DotApp::call('DACore:Plugins@listByContract!', 'subscription', 'v1', 'recurring');
```

| extra3 | Meaning |
|--------|---------|
| `recurring` | Create / cancel / status for a named plan. Interval lives on the plan, not on every call |

| extra5 | Meaning |
|--------|---------|
| *(empty)* | No qualifier. **MUST NOT** invent `trial` / `metered` here (put flags on the plan row) |

**Kind:** peer. **Controller:** `{Module}:SubscriptionContract@…!`

The **`<Host>` module** **MUST NOT** set `extra1=subscription` on itself.

---

## 2. Discovery (host)

1. Settings `<select>` / `dotSelect2` from `listByContract!('subscription','v1')`.
2. Persist the **selected module name** in the **host’s** settings. **MUST NOT** `UPDATE dacore_modules`.
3. Zero packs = empty state. One pack = operator still chooses unless host copy says auto-single.
4. After pick: `DotApp::call($module . ':SubscriptionContract@capabilities!')`.
5. Host **MUST** listen for `module.dacore.plugin_uninstall.veto` when that pack is uninstalled.

Discovery **MUST NOT** boot the pack. `capabilities()` is the first wake.

---

## 3. `capabilities()`

**Call:** `DotApp::call('{Module}:SubscriptionContract@capabilities!')`  
**Input:** none.  
**MUST NOT** throw.

**Success:**

```php
[
    'ok' => true,
    'contract' => 'v1',
    'module' => 'RecurKit',          // exact module name
    'modes' => ['recurring'],
    'plans' => [
        [
            'id' => '…ciphertext or pack token…',
            'name' => 'Monthly',
            'amount' => '9.90',      // decimal string
            'currency' => 'EUR',
            'interval' => 'month',   // day | week | month | year
            'interval_count' => 1,
        ],
    ],
    'scale' => 2,
]
```

`plans` is a **bounded** catalog for a `<select>`. If plans grow without bound, page them (do not `all()` a growing table) and keep `plans` as the first page plus `plans_total`.

**Failure:** `['ok' => false, 'message' => 'Subscriptions are not ready.']` — product copy, no `getMessage()`.

---

## 4. Methods

All in-process, `public static`, call string with `!`. **No CRC** on these helpers. Replies `{ok:true,…}` / `{ok:false, message:'…'}`.

Subscription and plan ids that leave PHP toward HTML **MUST** be `{{ enc(...) }}`. Incoming encrypted ids: `Crypto::decrypt` `=== false` → `ok:false`. Growing subscription tables: `COUNT` + `LIMIT` — **MUST NOT** `all()`.

Amounts are **decimal strings**, never `float`.

### `create($plan, $payerRef)`

**Call:** `DotApp::call('{Module}:SubscriptionContract@create!', $plan, $payerRef)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$plan` | string | Encrypted plan id or pack-stable plan token from `capabilities` |
| `$payerRef` | string | Host customer / account token (origin-safe). **MUST NOT** PAN, CVV, or raw email as the only key |

**Success:**

```php
[
    'ok' => true,
    'subscription_id' => '…ciphertext…', // HTML: {{ enc(RecurKit.sub.id): $id }}
]
```

The pack records recurrence. **MUST NOT** charge a card inside this method — host calls `payment` `createPayment` / `capture` with the amount **strings** from the plan. Idempotent on the same `$plan` + `$payerRef` when the host sends a stable ref: return the existing active id.

**Failure:** decrypt fail, unknown plan, payer refused, pack not ready → `ok:false`. **MUST NOT** leak gateway tokens.

### `cancel($subscriptionId)`

**Call:** `DotApp::call('{Module}:SubscriptionContract@cancel!', $subscriptionId)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$subscriptionId` | string | Encrypted id from `create` |

**Success:** `['ok' => true]`. Status becomes `cancelled`. Idempotent if already cancelled.

**Failure:** decrypt `=== false`, unknown, already terminal in a way the pack rejects → `ok:false`.

### `status($subscriptionId)`

**Call:** `DotApp::call('{Module}:SubscriptionContract@status!', $subscriptionId)`

| Arg | Type | Meaning |
|-----|------|---------|
| `$subscriptionId` | string | Encrypted id from `create` |

**Success:**

```php
[
    'ok' => true,
    'status' => 'active',            // active | past_due | cancelled | paused
    'plan' => '…token…',
    'amount' => '9.90',              // decimal string
    'currency' => 'EUR',
    'interval' => 'month',
]
```

**MUST NOT** return payer email, PAN, or mandate secrets. `status` is a read — **MUST NOT** fire a hook.

**Failure:** decrypt fail, unknown → `ok:false`.

---

## 5. Host checkout (not HTTP on the contract)

`create` / `cancel` / `status` are in-process. Host checkout POST: `/api/v1/auth/{Host}/…` + `#DACore:AuthTest@LoginAndCRC!`. Action **MUST NOT** `crcCheck()` again.

**MUST NOT** expose `create` / `cancel` on `/api/v1/noauth/…`.

Payer identity: host origin rules ([42](../42-DACORE-USER-ORIGIN.md)). The pack **MUST NOT** own the Auth session or call `Auth::createUser`.

---

## 6. Admin / UI

Plan choice is bounded — native `<select>` or `dotSelect2` of encrypted plan ids. **MUST NOT** a typed plan code box when `capabilities().plans` exists.

Cancel uses graphical confirm (`Notiflix.Confirm` on admin). Toast the outcome.

No media picker. This role has no `picker_js`.

---

## 7. Identity and amounts v1 (**MUST**)

- Subscription ids in HTML are encrypted. Decrypt `false` → reject. Still check host ownership in PHP.
- `amount` is a decimal string (`'9.90'`). **MUST NOT** `float`.
- `$payerRef` is a host token, not a payment instrument.
- Tables `{lowercase_modulename}_*` ; index plan, payer ref, status (comment names the `status` / host list query).
- Host lists of subscribers **MUST** paginate ([40](../40-DACORE-LIST-PAGER.md)).

---

## 8. Hooks

Fire only after a useful persist — **not** on `status`.

| Event | When | Payload (ids/counts only) |
|-------|------|---------------------------|
| `module.{mod}.subscription_created.hook` | New recurring row | `subscription_id`, `plan` |
| `module.{mod}.subscription_cancelled.hook` | Cancel persisted | `subscription_id`, `plan` |

**MUST NOT** put payer email, PAN, mandate ids, or request bodies in the payload. Document in the pack `.hooks`. [41](../41-MODULE-HOOKS.md).

---

## 9. MUST NOT

- Invent `extra1` (`billing`, `recur`, `membership`)
- `glob('app/modules')` or `include` the pack to discover it
- Use `float` for amounts
- Leak `getMessage()`, PAN, OTP, or gateway tokens
- `all()` on a growing subscription table
- Charge cards inside `create` (that is `payment`)
- Set `extra1=subscription` on `<Host>`
- PHP 8+ syntax unless the plan named a higher version

---

## 10. Finish gate

- `about.php` extras match §1 (`extra3=recurring`)
- Host lists with `listByContract!`
- Every public HTML subscription / plan id is encrypted
- Amounts are decimal strings
- Hooks omit payer PII
- No `crcCheck()` on `capabilities` / `create` / `cancel` / `status`
