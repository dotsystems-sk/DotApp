# 38 — DACore email (LAW)

**Open this file only when the module sends mail, seeds templates, or registers an email driver.** Inbox bell is [37](37-DACORE-NOTIFICATIONS.md). Sample: [EX-D06](examples/EX-D06-dacore-email.md).

Operators already own HTML templates and a sender registry in DACore. Your module **picks** a sender and a template. It does **not** invent a second mail stack.

Built-in SMTP is one per-sender driver (`dacore.smtp` → `DACore:EmailDriver@send`). External modules register their own rows with `controller_send` / `controller_test`. DACore composes the message once (recipients, rendered subject/body, content type, attachments) and calls that stored controller. There is no hidden SMTP-only dispatch branch.

In-process only: `DotApp::call("DACore:Email@…!")`. No HTTP route. No CRC.

---

## Planning (LAW — ASK)

When the module might **send mail**, **ASK before scaffolding**:

> Use DACore email senders and templates? (Almost always yes.)

**9 of 10** say yes. `Config::email` / `Parts\Email::send` only if they **decline**.

**Your module:** a `<select>` in **your** settings — which DACore sender (and which template) for this event. Optionally one sender per user. That is all. Do **not** clone DACore’s SMTP forms. Do **not** build credential UI for another module’s driver.

---

## Two roles

| You are writing | What you do |
|-----------------|-------------|
| **Consumer** (shop, CRM, 2FA, …) | `listSenders!` → store encrypted `token` or int `id` (legacy) or `sender_key` → `send!` |
| **Driver** (SES, SendGrid, Graph, …) | Register in `Installation.php`. Own credentials and settings. Set `settings_url`. Uninstall with `unregisterByCreator!`. |

DACore `{prefixUrl}/dacore/email-senders` is the operator page for **built-in SMTP** and, in a separate card, **external email drivers**. External rows that reach SMTP edit/save fail closed and are not rewritten as `dacore.smtp`.

Existing SMTP rows without driver columns still send: they alias to `sender_key=dacore.smtp.{id}`, `creator=DACore`, `driver_key=dacore.smtp`, `controller_send=DACore:EmailDriver@send`, `contract_version=1`, `enabled=1`. SMTP admin save writes those fields when the columns exist.

---

## Typical consumer module

1. Settings: `<select>` from `Email@listSenders!` / `Email@listTemplates!` (encrypted `token` in HTML; store **int** `id` or `sender_key` in `{lowercase}_*`).
2. Optional installer: `Email@addTemplate!` for module slugs (`Shop.Order`). `Email@registerSender!` with SMTP fields only if you ship a dedicated mailbox (upsert by **name**).
3. Event: `Email@send!` with the stored sender token (or `sender_key`) + template **slug** (or token) + `vars`.

Default sender: row with `is_default`, else the first enabled contract-v1 sender.

---

## Consumer API

### `Email@listSenders!` → `list<array>`

`id`, `token`, `sender_key`, `name`, `email`, `driver_key`, `settings_url`, `is_default`, `available`. Only enabled, supported-contract senders. No password, no host, no username.

### `Email@listTemplates!` → `list<array>`

`id`, `token`, `slug`, `name`, `is_system`.

### `Email@registerSender!` → `{ok, id?, sender_key?, token?, message?, errors?}`

Two payload shapes:

- **SMTP mailbox** (legacy): upsert by **name**. Keys: `name`, `email`, `host`, `port`, `secure` (`tls`|`ssl`|`''`), `username`, `password`, optional `timeout`, `is_default`.
- **External driver**: upsert by **sender_key**. Keys: `sender_key`, `creator`, `name`, `controller_send`, optional `controller_test`, `driver_key`, `settings_url`, `email` (From display), `contract_version` (omit = 1), `enabled`. **MUST NOT** send `host` / `password`.

The module that first registers a `sender_key` owns it through `creator`. Another creator cannot overwrite that key. `creator=DACore` is reserved for built-in SMTP. Check `ok === true`.

### `Email@unregisterByCreator!` → `bool`

Driver uninstall. Deletes that creator’s sender rows. **Never** wipes `creator=DACore` SMTP mailboxes. Templates stay unless you also call `unregisterTemplatesByCreator!`.

Manual sender deletion first fires `module.dacore.email_sender_delete.veto`. Disabling a sender first refuses an active email-2FA dependency, then fires the shared `module.dacore.driver_deactivate.veto`. Creator-wide uninstall cleanup is covered by the earlier package-uninstall veto and does not emit one per-row sender veto.

### `Email@testSender!` → `{ok, message, errors}`

`$sender` = int id, encrypted `token`, `sender_key`, or **name**. Optional `$to`. Dispatches through `controller_test` (else `controller_send`) with a composed TESTMAIL. Returns never leak exception text or credentials.

```php
DotApp::call("DACore:Email@testSender!", $sender['id'], 'you@example.com');
```

### `Email@addTemplate!` → `{ok, id?, slug?, message?, errors?}`

Upsert by **slug**. `TESTMAIL` / `CONFIRM` / `WELCOME` **cannot** be overwritten this way. Slug: `[A-Za-z0-9][A-Za-z0-9._-]*`, max 64. Prefer `Module.Event` (`Shop.Order`).

### `Email@send!` → `true` **or** `string[]`

| Key | Rule |
|-----|------|
| `id` | Encrypted sender **token** (never a raw int in `send`) |
| `sender_key` | Alternative to `id` for driver-aware callers |
| `to` / `cc` / `bcc` | String, CSV, or array |
| Body | **Exactly one** of `template` or `text` |
| `template` | Slug (`Shop.Order`), encrypted token, or int (in-process) |
| `vars` | Replaces `{{ token }}`. Built-in: `email`, `name`, `app_name`, `date`, `time`, `year`, `confirm_link`. Unknown tokens stay |
| Returns | `true` or `string[]`. Never `false`. Check `!== true` |

```php
$result = DotApp::call("DACore:Email@send!", [
    'id' => $sender['token'],
    'to' => $email,
    'subject' => 'Your order',
    'template' => 'Shop.Order',
    'vars' => ['name' => $name],
]);
if ($result !== true) {
    Logger::use()->error('Shop mail failed', ['errors' => $result]);
}
```

HTML selects: encrypted `token`. **Store ints** (legacy) or `sender_key` in your tables. Use `DotApp::call` — do not import DACore email store classes.

`Email@send!` stays compatible: same array payload, `true` or `string[]`. Template rendering stays in DACore. Disabled senders and unsupported `contract_version` fail closed. When the email feature switch is off, send/test/list return disabled/empty.

---

## Driver contract

Register from **your** `Installation.php` (upsert by `sender_key`):

```php
$reg = DotApp::call('DACore:Email@registerSender!', [
    'sender_key'       => 'shop.ses.default',
    'creator'          => 'ShopSes',
    'name'             => 'Amazon SES',
    'email'            => 'noreply@example.com',
    'driver_key'       => 'ses',
    'controller_send'  => 'ShopSes:Provider@send',
    'controller_test'  => 'ShopSes:Provider@test',
    'settings_url'     => '/dacore/shopses/settings',
    'contract_version' => 1,
    'enabled'          => 1,
]);
if (($reg['ok'] ?? false) !== true) {
    Logger::use()->error('Email driver register failed', ['errors' => $reg['errors'] ?? []]);
}
```

Uninstall:

```php
DotApp::call('DACore:Email@unregisterByCreator!', 'ShopSes');
```

`sender_key` / `driver_key`: `[A-Za-z0-9][A-Za-z0-9._-]{0,49}`. `settings_url`: relative path starting with `/` (not `//`). Controllers: `Module:Controller@method`.

`contract_version` is currently `1`; omitting it means v1. DACore refuses unsupported versions. `enabled=0` keeps the row for administration but removes it from list/send/test dispatch.

With optimization active, enabled sender **metadata** is compiled into `dacoreAutoLoader.php` section `email_drivers`. Cache rows contain **no** password, host, username, email body, or credentials. Without optimization (or when that section is still an empty stub), the same resolver performs an exact bound database query. Registration, SMTP save, delete, and lifecycle changes call `DacoreRegistryCache::rebuildIfPresent()`.

Your controller methods **MUST NOT throw**. DACore always passes the composed message first and `$context` last.

```php
public static function send($message = [], $context = [])
{
    // $message: to, cc, bcc, subject, body, contentType, attachments, from, from_name, debug
    // $context: sender_id, sender_key, creator, driver_key — no secrets
    return true;
    // or ['Could not deliver']
    // or ['ok' => false, 'message' => 'Gateway rejected the address']
}

public static function test($message = [], $context = [])
{
    return ['ok' => true, 'message' => 'Test accepted'];
}
```

Credentials stay in **your** tables / config. DACore stores routing metadata only. Do not duplicate template lookup — the body is already rendered.

On success DACore fires `module.dacore.mail_sent.hook` **once** with `sender_id` and `to_count` only. Built-in SMTP does **not** fire a second hook from `EmailMailer::dispatch`.

---

## Operator administration (Email senders)

Root operators open `{prefixUrl}/dacore/email-senders`. The SMTP card is unchanged: search, pager, Active, Test, Edit, Delete. The **External email drivers** card is a second list with its own COUNT + LIMIT pager (encrypted `data-dacore-page` inside `#dacoreEmailDriversWrap`). The two pagers do not share page state.

GET lists **metadata only**: name, From address, `driver_key`, owner (`creator`), enabled/default, contract state, and a same-origin `settings_url` when `normalizeSettingsUrl()` accepts it. No password, host, username, or provider callable. The list does **not** `DotApp::call` a driver and does **not** autoload `controller_send`. Unsupported `contract_version` shows as unavailable; Test / Set as default stay hidden.

Row actions stay on the same POST (`#DACore:AuthTest@crcGuard!` already ran). Visible toast + live patch of driver rows and pager. Overlay `#dacoreEmailDriversWrap`. No reload. Buttons wrap on mobile and keep padding vs the card.

| Action | Rule |
|--------|------|
| **Test** | `EmailMailer::test` — this is when the provider loads |
| **Set as default** | Only when the row is enabled and contract-v1 (`EmailSendersStore::setDefault`) |
| **Enable** | `setEnabled(true)` — no step-up |
| **Disable** | Notiflix confirm + operator step-up 2FA (`$dotapp().twoFactor`). PHP `StepUp::verify` **before** `setEnabled(false)`. Overlay is UX only. If this sender is the email-2FA channel, the store already refuses until Settings turns that channel off |
| **Remove** | `EmailSendersStore::delete` — `module.dacore.email_sender_delete.veto` still applies |
| **Settings** | Render `<a href>` only for a relative path starting with `/` (not `//`). Never an off-site URL or `header()` redirect |

When the email **feature switch** is off, the external card shows a short disabled-state note and **MUST NOT** query the external driver list or load providers. SMTP metadata may still render. Send/test already fail through `DacoreRegistryCache::featureEnabled('email')`.

Do **not** clone this UI in a consumer module. Point operators at DACore Email senders. Your module keeps its own credential screen and sets `settings_url` at `registerSender!`.

---

## Stop an operator from deleting your template

DACore fires `Events::triggerWithVeto('module.dacore.email_template_delete.veto', …)` **before** a manual delete of one custom email template. Uninstall wipe does **not**.

Return `new \Dotsystems\App\Parts\Veto($code, $message, $details)` from **your** `module.listeners.php`. `false` is ignored. Cover `{prefixUrl}/dacore/email-templates` (and `/new`, `/edit`) in `Listeners::initializeRoutes()` and run `--optimize-modules`. Canonical: [41](41-MODULE-HOOKS.md), `app/modules/DACore/.hooks`.

---

## MUST NOT

- SMTP UI / PHPMailer / `Config::email` unless the user declined DACore senders
- `Parts\Email::send` as the default path
- `INSERT` into `dacore_email_senders` / `dacore_email_templates`
- Store provider secrets in DACore — including on driver `registerSender!`
- Raw int as `Email@send!` `id`
- Both `template` and `text`
- Overwrite `TESTMAIL` / `CONFIRM` / `WELCOME` via `addTemplate!`
- Edit an external driver through DACore’s SMTP form
- Load an external email provider on the Email senders GET (the list is metadata only)
- A second inbox — `DACore:Notifications@push` ([37](37-DACORE-NOTIFICATIONS.md))
