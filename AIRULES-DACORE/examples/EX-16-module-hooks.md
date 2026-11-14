# EX-16 — Module hooks (`module.{mod}.{name}.hook` + `.hooks` + listen)

Canonical law: [41-MODULE-HOOKS.md](../41-MODULE-HOOKS.md). API: [12-SERVICES.md](../12-SERVICES.md) §2. Comments: [25](../25-PERFORMANCE-AND-CODE-QUALITY.md) §7.

`trigger()` returns `$result` unchanged. Listener returns are **not** a veto. Payloads = ids/counts, never secrets.

Fire **only** when another module could log, show history, or sync — not on every save.

When the module **plugs into DACore**, open **`app/modules/DACore/.hooks` first** (Fired + Veto contracts). That is the catalog of events DACore already offers — subscribe there instead of inventing `module.dacore.*` or patching DACore ([41](../41-MODULE-HOOKS.md) §6).

---

## 1. Fire after a useful side-effect (Shop sent SMS)

```php
$sent = $this->sendTwoFactorSms($userId, $templateId);
if ($sent !== true) {
    return self::fail(Translator::trans('The SMS could not be sent.'));
}
// Hook: module.shop.sms_sent.hook
// Why: another module may store SMS history or an operator audit without patching this sender.
// About: a 2FA SMS was accepted by the gateway for this user.
// Params: user_id (int), channel (sms), template_id (int). No body, no OTP.
// Use: history tables, operator logs, rate-limit dashboards.
Events::trigger('module.shop.sms_sent.hook', [
    'user_id' => (int) $userId,
    'channel' => 'sms',
    'template_id' => (int) $templateId,
]);
```

**MUST NOT** fire inside `foreach` over a growing list — one batch after the loop, and only if a consumer would use the batch:

```php
// Hook: module.shop.items_rebuilt.hook
// Why: a cache/search module can rebuild an index without patching Shop.
// About: a batch of catalog items was republished after import.
// Params: ids (int[]), count (int). No row bodies.
// Use: search index, CDN purge, stock cache.
Events::trigger('module.shop.items_rebuilt.hook', [
    'ids' => $idList,
    'count' => count($idList),
]);
```

---

## 2. `app/modules/Shop/.hooks` (same chunk)

Filename is **`.hooks`**, Markdown body, module **root** (not `assets/`, not `hooks.md`).

```markdown
# Shop hooks

Shape: module.{modulename}.{hook_name}.hook
`trigger()` ignores listener return values — they are not a veto.

## Fired

### module.shop.sms_sent.hook

- **When:** after the SMS gateway accepted the send.
- **Payload (`$result`):** `user_id` (int), `channel` (`sms`), `template_id` (int).
- **Extra:** none.
- **Use:** history, operator audit, rate-limit dashboards.
- **Secrets:** none.

## Subscribed

_(none)_
```

---

## 3. Listen from another module (Audit)

Read `app/modules/Shop/.hooks` first. Register only in **Audit** `module.listeners.php`:

If Audit's full module routes do not cover the requests where Shop fires this event, give the Audit listener its own map:

```php
public function initializeRoutes()
{
    // The producer routes matter here; Audit's admin page can stay asleep.
    return ['/api/v1/auth/Shop/*'];
}
```

Omit this method when listener and module routes are identical. Old modules then inherit `Module::initializeRoutes()`.

```php
use Dotsystems\App\Parts\Events;
use Dotsystems\App\Modules\Audit\Libraries\CatchBus;
use Dotsystems\App\Modules\Audit\Libraries\AuditStore;

Events::on('module.shop.sms_sent.hook', function ($result, ...$data) {
    try {
        $userId = (int) ($result['user_id'] ?? 0);
        if ($userId < 1) {
            return;
        }
        // Why: Audit records Shop SMS without patching Shop.
        AuditStore::recordSms($userId, $result);
    } catch (\Throwable $e) {
        CatchBus::reportCatch($e);
    }
});
```

Document the subscription under **Subscribed** in `app/modules/Audit/.hooks`.

**MUST NOT** edit Shop or DACore to “inject” Audit.

---

## 4. Explicit pre-action veto (different contract)

Use this only when Shop deliberately allows another loaded module to stop an action:

```php
use Dotsystems\App\Parts\Events;
use Dotsystems\App\Parts\Veto;

$veto = Events::triggerWithVeto('module.shop.item_delete.veto', [
    'item_id' => (int) $itemId,
]);
if ($veto instanceof Veto) {
    return [
        'ok' => false,
        'code' => $veto->code(),
    ];
}

// Persist only after every listener allowed the action.
```

A subscriber returns an explicit object; `false` is intentionally ignored:

```php
Events::on('module.shop.item_delete.veto', function ($result) {
    $itemId = (int) ($result['item_id'] ?? 0);
    if ($itemId > 0 && AuditStore::hasRequiredHistory($itemId)) {
        return new Veto('audit.history_required', 'Audit history still references this item.', [
            'item_id' => $itemId,
        ]);
    }
    return null;
});
```

Document this under **Veto contracts** in Shop’s `.hooks`, including timing, payload, and allowed codes. The subscriber’s `Listeners::initializeRoutes()` must cover the Shop request where this event fires. Never expose `Veto::message()` or `details()` directly to a browser.

DACore already ships two owner contracts you can listen to without patching DACore: `module.dacore.email_template_delete.veto` and `module.dacore.sms_template_delete.veto` ([38](../38-DACORE-EMAIL.md), [39](../39-DACORE-SMS.md), `app/modules/DACore/.hooks`).
