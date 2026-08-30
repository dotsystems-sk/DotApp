# 41 — Module hooks (MUST — law)

A module that only talks to itself is a silo. When a step is **worth another module (or a future agent) reacting to** — SMS sent, order paid, user locked out — **MUST** fire `Events::trigger()` and **MUST** document that name in **`app/modules/<YourModule>/.hooks`**.

**MUST NOT** spray a trigger on every save, toggle, or pager click. The agent **MUST** judge: would Inventory, Audit, Notifications, or a later module reasonably subscribe **without patching this file**? If you cannot name that use, **do not** fire.

Canonical API: [12](12-SERVICES.md) §2, [03](03-MODULES-AND-ROUTING.md) “Events from modules”, [01](01-ARCHITECTURE.md) (`dotapp.catchall`). Sample: [EX-16](examples/EX-16-module-hooks.md). Finish gate: [00](00-AGENT-CONTRACT.md) §2c / §2g. Inline comments: [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7.

**Not Extender.** Replacing how a page/cart/export renders (one handler owns the result) is [12](12-SERVICES.md) §10 / [EX-17](examples/EX-17-extender.md) — **MUST NOT** fire a hook for that. **MUST NOT** treat Extender as required on every method — judge first ([00](00-AGENT-CONTRACT.md) §2h). **MUST NOT** patch DACore to insert `Extender::call`.

---

## 0. Four root laws

1. **Judge, then fire.** Fire only after a **successful** side-effect another module could care about (sent SMS/mail, captured payment, finished workflow, lockout, confirmed 2FA). Skipping a **named** useful hook is a bug. Firing on a trivial catalog save “because hooks are cheap” is also a bug.
2. **Document it.** Every `module.{yourmodule}.*.hook` you fire **MUST** have a matching heading in that module’s **`.hooks`**. A trigger without a row, or a row without a trigger, is a **bug**. Update `.hooks` in the **same chunk**.
3. **Connect, do not patch.** To react to another module: **read its `.hooks`**, then `Events::on(...)` in **your** `module.listeners.php`. **MUST NOT** edit the other module (and **MUST NOT** edit DACore) to “add a call”. Inventing an event name the owner never fires is a bug. A **DACore-bound** module **MUST** start with **`app/modules/DACore/.hooks`** ([§6](#6-listening-from-another-module-must)).
4. **No secrets on the bus.** Hook payloads are **ids, counts, status flags** — never passwords, TOTP/OTP, CRC, tokens, rights blobs, SMS/email bodies, or request bodies. The same leak law as the catch bus ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9, [24](24-ATTACK-VECTORS.md) §8).

---

## 1. Name (**MUST**)

```
module.{lowercase_modulename}.{hook_name}.hook
```

Four lowercase segments. Core lowercases names — write them lowercase. `{hook_name}` is `snake_case` and **MUST NOT** contain dots (the fourth segment is always `hook`).

| Module | Example |
|--------|---------|
| `Shop` | `module.shop.sms_sent.hook` |
| `Shop` | `module.shop.order_paid.hook` |
| `Invoice` | `module.invoice.document_issued.hook` |

```php
use Dotsystems\App\Parts\Events;

Events::trigger('module.shop.sms_sent.hook', [
    'user_id' => (int) $userId,
    'channel' => 'sms',
    'template_id' => (int) $templateId,
]);
```

| Fact | Law |
|------|-----|
| Return | `trigger()` returns `$result` **unchanged**. Listener **return values are ignored** — they are **not** a veto. |
| Exceptions | A throw in a listener **aborts remaining listeners**. Listeners **MUST** be cheap and wrap risky work in their own `try/catch` (then the catch bus). |
| Debug funnel | Core already fires `dotapp.catchall` on every `trigger()` except itself. **MUST NOT** fire `dotapp.catchall` yourself. |
| Failures | Persist errors stay on **`dotapp.catch`** ([18](18-ERROR-HANDLING-AND-RETURN-VALUES.md) §9). Do **not** reuse catch-bus names for business hooks. |
| `hasListener()` | **MUST NOT** skip a **decided** trigger because nobody is subscribed yet. Catchall + future modules need the name. |

| **MUST** | **MUST NOT** |
|----------|----------------|
| Prefix `module.` + **this** module’s lowercase name + `snake_case` + `.hook` | `shop.item.saved`, `{module}.{noun}.{happened}` (old shape) |
| One name per moment; extra keys in the payload | `dotapp.*` for business (core + catch bus only) |
| | Another module’s prefix (`module.blog.*` from Shop) |
| | Laravel-style `event(new ItemSaved)` / `Event::dispatch` |

DACore: you do **not** add `module.dacore.*` from **your** module. If the informed DACore exception applies ([00](00-AGENT-CONTRACT.md) §1), DACore uses `module.dacore.{hook_name}.hook` and **`app/modules/DACore/.hooks`**.

### Explicit pre-action veto contracts

`triggerWithVeto()` is a separate opt-in core API for an owner that deliberately allows another module to stop an action. Its name ends in `.veto`:

```
module.{lowercase_modulename}.{action_name}.veto
```

Example: `module.shop.item_delete.veto`. This is **not** a normal post-success `.hook` and MUST NOT replace one.

- The owner fires it immediately before the reversible action and handles the returned `Veto|null`.
- Only `new \Dotsystems\App\Parts\Veto($code, $message, $details)` stops dispatch. `false` and every other old return are ignored.
- The first `Veto` wins and later listeners do not run. Listener exceptions still propagate.
- The owner documents the exact name, timing, payload, and allowed veto codes in its root `.hooks` under a separate **Veto contracts** section.
- Payload and `Veto::details()` follow the same no-secrets law as hooks. The core never sends `message` or `details` to a browser.
- A subscriber MUST cover the producer request in `Listeners::initializeRoutes()` and regenerate `modulesAutoLoader.php`; an unloaded listener cannot veto.
- Ordinary `trigger()` ignores even a returned `Veto`, so old modules remain compatible.
- DACore ships two owner contracts today: `module.dacore.email_template_delete.veto` and `module.dacore.sms_template_delete.veto` ([38](38-DACORE-EMAIL.md), [39](39-DACORE-SMS.md)). Uninstall wipe is not gated.

---

## 2. When to fire (**MUST** judge)

**Fire** when you can name a future consumer in the comment (`Use:`). Typical yes:

| Moment | Example name |
|--------|----------------|
| This module **sent** SMS or mail (gateway accepted) | `module.shop.sms_sent.hook` |
| Workflow step finished (paid, registered, issued) | `module.shop.order_paid.hook` |
| Security / audit step (lockout, 2FA confirmed) | `module.shop.user_locked.hook` |
| A batch of domain ids changed in a way others sync | `module.shop.items_rebuilt.hook` (one event after the loop) |

**MUST NOT fire:**

| Skip | Why |
|------|-----|
| Ordinary save/update of a row with **no** named cross-module use | Noise; other modules would not subscribe |
| Every GET / list render / pager click | Not a side-effect others need |
| Inside `foreach` of a growing page | One **batch** event with an id list after the loop ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §1–§2) |
| Failed persist | Catch bus, not a fake success hook |
| Before login / CRC / rights | Listeners would see unauthenticated attempts |
| Per-row in an installer loop | One event after the batch, or none |

Test: *“Would another module log this, show history, or sync a side table without me editing this file?”* Yes → fire. No / “maybe someday on every save” → skip.

---

## 3. Comment block on every trigger (**MUST**)

Immediately **above** `Events::trigger('module.…hook'` the agent **MUST** write this block. Keywords are **required** (same idea as `// Why:` — [25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7):

```php
// Hook: module.shop.sms_sent.hook
// Why: another module may store SMS history or an operator audit without patching this sender.
// About: a 2FA SMS was accepted by the gateway for this user.
// Params: user_id (int), channel (sms|call), template_id (int). No body, no OTP.
// Use: history tables, operator logs, rate-limit dashboards in a later module.
Events::trigger('module.shop.sms_sent.hook', [
    'user_id' => (int) $userId,
    'channel' => 'sms',
    'template_id' => (int) $templateId,
]);
```

| Line | Meaning |
|------|---------|
| `Hook:` | Exact event name (must match `.hooks` and the `trigger()` string) |
| `Why:` | Why this hook exists (cross-module / future), **not** a restatement of `trigger()` |
| `About:` | What just happened in this module |
| `Params:` | Payload keys and types; what is **absent** (no body, no OTP) |
| `Use:` | Concrete future uses — if you cannot write this line, **do not** fire |

`Section:` belongs on the **action / file** ([25](25-PERFORMANCE-AND-CODE-QUALITY.md) §7), not repeated on every hook unless the trigger sits far from that header.

---

## 4. Payload (**MUST**)

Server-side bus — **plain `(int)` ids are correct here**. `{{ enc() }}` is for the **browser**, not for `Events::trigger`.

**MUST NOT** put in `$result` / `...$data`: password, hash, TOTP secret/code, CRC, session token, rights array, raw request, email HTML, SMS body, card data, full `SELECT *` row that includes secrets.

Cap lists of ids. **MUST NOT** pass the whole growing table.

---

## 5. `.hooks` file (**MUST**)

| Rule | Detail |
|------|--------|
| Path | **`app/modules/<YourModule>/.hooks`** — module **root**, next to `module.init.php` |
| Name | Exactly **`.hooks`**. Markdown **content**, **not** a `.md` / `.html` filename |
| Why that name | Agents and humans read it; it is **not** a public page. **MUST NOT** put it under `assets/` (those files can be fetched). `app/.htaccess` already denies the module root except `assets/` |
| Runtime | The framework **does not** load `.hooks`. It is documentation only — **MUST NOT** `include` / `require` / `eval` it |
| Zip | **MUST** ship inside a DACore-bound module zip when any `module.{this}.*` hook exists ([35](35-DACORE-INSTALL.md)). Working tree keeps it |
| Secrets | **MUST NOT** put keys, emails of operators, or example passwords in `.hooks` |

### Format (Markdown inside `.hooks`)

English. Normal events use **Fired** (this module triggers) and **Subscribed** (this module listens — so the next agent does not double-subscribe). Add a separate **Veto contracts** section only when this module owns an explicit `triggerWithVeto()` action.

Each **Fired** heading is the **exact** event name:

```markdown
# Shop hooks

Events this module fires and listens to. Names are lowercased by the core.
Shape: module.{modulename}.{hook_name}.hook
`trigger()` ignores listener return values — they are not a veto.

## Fired

### module.shop.sms_sent.hook

- **When:** after the SMS gateway accepted the send (not on compose, not on failure).
- **Payload (`$result`):** `user_id` (int), `channel` (`sms`\|`call`), `template_id` (int).
- **Extra (`...$data`):** none.
- **Use:** history tables, operator audit, rate-limit dashboards.
- **Secrets:** none (no body, no OTP).

## Subscribed

### module.invoice.document_issued.hook

- **Owner:** `Invoice` — read `app/modules/Invoice/.hooks`.
- **This module:** `Shop:Stock@onInvoiceIssued!` in `module.listeners.php`.
```

**MUST** delete a `.hooks` row when you remove the `trigger()`. **MUST NOT** leave “coming soon” names.

---

## 6. Listening from another module (**MUST**)

### DACore catalog (**MUST**)

When you program a **DACore-bound** `<TargetModule>` (new **or** existing), **MUST** open **`app/modules/DACore/.hooks`** **read-only** **before** scaffolding listeners, history tables, lockout reactions, mail/SMS audit, or template-delete protection.

That file is the catalog of events DACore already fires (`Fired` = `module.dacore.*.hook`, **Veto contracts** = `module.dacore.*.veto`: login, lockout, 2FA, mail/SMS sent, plugin install, template delete, …). Subscribe in **your** `module.listeners.php` so the module uses that potential instead of reinventing it or patching DACore.

**MUST NOT** invent `module.dacore.*` names from your module. **MUST NOT** skip a listed event because you did not open the file. **MUST NOT** edit `app/modules/DACore/` to “add a call”.

1. **Read** `app/modules/<Other>/.hooks` (and grep `Events::trigger` there if the file is thin). For a DACore-bound module, **Other** is **DACore first** (`app/modules/DACore/.hooks`), then any other owner the user **named** as the subscribe/extend target — not a tour of `app/modules/*/.hooks`. Reading that owner’s `.hooks` is **not** a license to copy their views or CSS ([00](00-AGENT-CONTRACT.md) §1b).
2. Register in **your** `module.listeners.php` — register **only**, no query/HTTP on include ([03](03-MODULES-AND-ROUTING.md)).
3. Keep `initializeRoutes()` on **your** prefixes — **MUST NOT** `['*']` just to hear events ([03](03-MODULES-AND-ROUTING.md) sleep law). Loaded modules still receive global events.
4. Listener body: cheap; own `try/catch` + your catch-bus helper; **MUST NOT** push a DACore inbox notification on every fire ([37](37-DACORE-NOTIFICATIONS.md)).
5. **MUST NOT** `DotApp::call` the other module on every request just to “discover” hooks — the file is on disk.

```php
Events::on('module.shop.sms_sent.hook', function ($result, ...$data) {
    try {
        $userId = (int) ($result['user_id'] ?? 0);
        if ($userId < 1) {
            return;
        }
        // Why: Audit stores SMS history from Shop without patching Shop.
        AuditStore::recordSms($userId, $result);
    } catch (\Throwable $e) {
        CatchBus::reportCatch($e);
    }
});
```

---

## 7. Finish gate (**MUST**)

After every chunk that **adds or changes** `Events::trigger(` or `Events::triggerWithVeto(`, **grep** (do not imagine):

```text
Events::trigger(
Events::triggerWithVeto(
```

| Fail now | Fix |
|----------|-----|
| `Events::trigger('shop.` / `{mod}.{noun}.{happened}` for business | Rename to `module.{mod}.{hook_name}.hook` |
| A **named** useful side-effect (SMS sent, paid, lockout) with **no** hook | Add trigger + `.hooks` + comment block |
| Hook on a trivial save with no `Use:` you can name | Remove the trigger |
| `Events::trigger('module.` **without** the `Hook:` / `Why:` / `About:` / `Params:` / `Use:` block | Add the block |
| `triggerWithVeto('module.` without a **Veto contracts** heading in `.hooks` | Document the `.veto` name, timing, payload, and producer POSTs |
| Name **not** in `.hooks` | Add the heading in the same chunk |
| `.hooks` names that are not in the PHP | Delete the stale row |
| Trigger **inside** `foreach` of a list that can grow | One batch event after the loop |
| Password / OTP / token / `$request->data` / SMS body in the payload | Ids and counts only |
| `Events::trigger('dotapp.catchall'` | Remove — core already fires it |
| Skip a **decided** hook because `hasListener` is false | Always fire that name |
| Putting `.hooks` in `assets/` or naming it `hooks.md` | Module-root `.hooks` only |

Tick [17](17-CHECKLISTS.md) Finish gate **Hooks**.

---

## 8. Antipatterns (short)

| Wrong | Right |
|-------|--------|
| Trigger on every item save “just in case” | Fire only when `Use:` names a real consumer |
| Skip SMS/mail/payment because “nobody listens yet” | Fire + `.hooks` + comment block |
| `shop.item.saved` | `module.shop.order_paid.hook` (four segments) |
| Patch DACore / Shop to call your code | Listen to **their** documented event |
| `hooks.md` in `assets/` | `app/modules/Shop/.hooks` |
| Veto via `return false` | Persist already happened; do your own work or don’t |
| `Events::trigger` to replace a method | `Extender` on a judged render/cart/export — **not** every method ([12](12-SERVICES.md) §10, [00](00-AGENT-CONTRACT.md) §2h) |
| Trigger before `execute()` `$ok` | Fire in the success path only |
| Full user row / SMS body on the bus | `id` + flags |
| `trigger()` with no `Hook:` comment | The five-line block |

Full table: [14](14-ANTIPATTERNS.md).
