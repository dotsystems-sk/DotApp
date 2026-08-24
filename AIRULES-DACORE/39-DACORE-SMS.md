# 39 — DACore SMS sender drivers (LAW)

**Open this file only when the module sends SMS or implements an SMS gateway.** Framework `SmsProvider` is [21](21-EMAIL-SMS-QR.md). Sample: [EX-D07](examples/EX-D07-dacore-sms.md). Mail is [38](38-DACORE-EMAIL.md).

Operators install **driver modules**. DACore keeps a registry of those drivers and dispatches send/status/test. Your module **picks** a sender (`sender_key`) and calls `DACore:Sms@send!`. It does **not** invent a second SMS stack.

In-process only: `DotApp::call("DACore:Sms@…!")`. No HTTP route. No CRC. Class name is `Sms` (file `Sms.php`) — Linux is case-sensitive; do **not** write `SMS` / `sms`.

---

## Planning (LAW — ASK)

When the module might **send SMS**, **ASK before scaffolding**:

> Send through DACore SMS sender drivers? (Almost always yes.)

**9 of 10** say yes. `Parts\Sms` / `SmsProvider` / `Config::module('DACore','smsProvider')` only if they **decline**.

**Your module:** a `<select>` in **your** settings — which DACore sender for this event. Empty / `DEFAULT` uses the default driver so a fresh install works as soon as the first driver is registered. That is all. Do **not** clone a gateway form into DACore.

---

## Two roles

| You are writing | What you do |
|-----------------|-------------|
| **Consumer** (shop, CRM, 2FA, …) | `listSenders!` → store `sender_key` → `send!` |
| **Driver** (one SMS provider) | Register in `Installation.php`. Own credentials, settings page, test, uninstall. Zero routes/menu is allowed — set `settings_url`. |

DACore `/dacore/sms-senders` is a **list**. The only action is **set as default**. No add, edit, delete, enable, or test. Removal = uninstall the driver module (`Sms@unregisterByCreator!`).

---

## Consumer API

### `Sms@listSenders!` → `list<array>`

`id`, `token`, `sender_key`, `name`, `info`, `settings_url`, `supports_from`, `supports_status`, `is_default`, `available`. Only enabled, supported-contract drivers are returned. No credentials. No `extra1`–`extra3` — those are driver-private routing tokens.

HTML may use encrypted `token`. **Store `sender_key`** in `{lowercase}_*` — never the auto-increment `id` (ids differ per install).

### `Sms@defaultSenderKey!` → `string`

Empty when no driver is registered.

### `Sms@send!` → `{ok, message_id, message, errors}`

Aliases: `sendSMS!`, `sms!`.

| Arg | Rule |
|-----|------|
| `$sender` | `sender_key`, encrypted token, int id, `DEFAULT`, empty (default), or one payload array |
| `$to` | Destination number as the caller passed it |
| `$text` | Body |
| `$from` | Optional originator. Drivers with `supports_from=0` ignore it |
| `$options` | Driver-private extras. Never required |
| `$context` | DACore adds this last argument on dispatch: `sender_key`, `creator`, `extra1`, `extra2`, `extra3`. Consumers never pass it. |
| Array form | `['id'\|'sender'\|'sender_key', 'to', 'text', 'from', 'options']` |
| Returns | Always an array. Check `ok === true`. Never throws |

```php
$result = DotApp::call('DACore:Sms@send!', $senderKey, $phone, $text);
if (($result['ok'] ?? false) !== true) {
    Logger::use()->error('Shop SMS failed', ['errors' => $result['errors'] ?? []]);
}
```

### `Sms@status!` / `smsStatus!` → `{ok, status, message, errors}`

`$status` is `sent` / `delivered` / `failed` / `unknown` when the driver supports it.

### `Sms@testSender!` → `{ok, message, errors}`

For the **driver's** settings page. Not shown in DACore's list.

Use `DotApp::call` — do not import DACore SMS store classes.

---

## Driver contract

Register from **your** `Installation.php` (upsert by `sender_key`):

```php
$reg = DotApp::call('DACore:Sms@registerSender!', [
    'sender_key'        => 'supersms123.sender',
    'creator'           => 'supersms123',
    'name'              => '123SMS',
    'info'              => 'SMS for SK/CZ.',
    'settings_url'      => '/dacore/supersms123/settings',
    'controller_send'   => 'supersms123:Provider@send',
    'controller_status' => 'supersms123:Provider@status',
    'controller_test'   => 'supersms123:Provider@test',
    'supports_from'     => 1,
    'contract_version'  => 1,
    'enabled'           => 1,
    'extra1'            => 'profile-key', // optional; non-secret routing token
    'extra2'            => '',
    'extra3'            => '',
]);
if (($reg['ok'] ?? false) !== true) {
    Logger::use()->error('SMS driver register failed', ['errors' => $reg['errors'] ?? []]);
}
```

Uninstall:

```php
DotApp::call('DACore:Sms@unregisterByCreator!', 'supersms123');
```

`sender_key`: `[A-Za-z0-9][A-Za-z0-9._-]{0,49}`. `settings_url`: relative path starting with `/` (not `//`). Controllers: `Module:Controller@method`.

`contract_version` is currently `1`; omitting it means the current v1 contract. DACore refuses unsupported versions. The module that first registers a `sender_key` owns it through `creator`; another creator cannot overwrite that key. `enabled=0` keeps the row for administration but removes it from list/default/send/status/test dispatch. Existing rows migrate as enabled v1.

With optimization active, enabled sender metadata is compiled into the independent `dacoreAutoLoader.php`. Without it, the same resolver performs exact bound database queries. Registration, removal, default changes, and lifecycle changes refresh an existing snapshot. If refresh fails, DACore removes the stale file and immediately uses the database fallback.

`{prefixUrl}/dacore/sms-senders` exposes two distinct states. **Enabled** is the global dispatch switch; **Active** means the driver was verified for SMS 2FA (`tested_at`). A disabled driver cannot be default or Active. Turning either state off requires graphical confirmation and PHP `StepUp::verify()`; a sender currently configured for SMS 2FA must first be removed from that channel in Settings. When the SMS feature is off, the list and POST actions do not query or mutate the driver registry.

`extra1`–`extra3` are optional `varchar(64)` tokens (`[A-Za-z0-9._-]` or empty). Omitted extras stay unchanged on upsert. **MUST NOT** store API keys, passwords, signatures, or phone numbers there — credentials stay in the driver module.

Your controller methods **MUST NOT throw**. Same argument order every time. DACore always appends `$context` last; older drivers that omit the parameter still work.

```php
public static function send($to, $text, $from = '', $options = [], $context = [])
{
    $profileKey = is_array($context) ? (string) ($context['extra1'] ?? '') : '';
    return ['ok' => true, 'message_id' => 'abc123'];
    // or ['ok' => false, 'message' => 'Gateway rejected the number'];
}

public static function status($messageId, $context = [])
{
    return ['ok' => true, 'status' => 'delivered'];
}

public static function test($to = '', $context = [])
{
    return ['ok' => true, 'message' => 'Test SMS accepted'];
}
```

`$context` keys: `sender_key`, `creator`, `extra1`, `extra2`, `extra3`. Use them to select among several senders registered by the same module.

Credentials stay in **your** tables / config. DACore stores routing metadata only.

The first registered driver becomes **default**. When the default is uninstalled, the next remaining row takes over. Consumer modules that stored nothing / `DEFAULT` then keep working.

---

## Stop an operator from deleting your SMS template

DACore fires `Events::triggerWithVeto('module.dacore.sms_template_delete.veto', …)` **before** a manual delete of one custom SMS template. Uninstall wipe does **not**.

Return `new \Dotsystems\App\Parts\Veto($code, $message, $details)` from **your** `module.listeners.php`. `false` is ignored. Cover `{prefixUrl}/dacore/sms-templates` (and `/new`, `/edit`) in `Listeners::initializeRoutes()` and run `--optimize-modules`. Canonical: [41](41-MODULE-HOOKS.md), `app/modules/DACore/.hooks`.

---

## MUST NOT

- A second SMS stack (`Parts\Sms` / `SmsProvider`) unless the user declined DACore drivers
- `INSERT` / `UPDATE` / `DELETE` on `dacore_sms_senders`
- Keep provider secrets in DACore — including in `extra1`–`extra3`
- Propose add / edit / delete / test on DACore's SMS senders list
- Store auto-increment `id` as the stable sender reference — use `sender_key`
- Call `DACore:SMS@…` (wrong class case on Linux)
