# 38 — DACore email (LAW)

**Open this file only when the module sends mail or seeds templates.** Inbox bell is [37](37-DACORE-NOTIFICATIONS.md). Sample: [EX-D06](examples/EX-D06-dacore-email.md).

Operators already own SMTP and HTML templates in DACore. Your module **picks** a sender and a template. It does **not** invent a second mail stack.

In-process only: `DotApp::call("DACore:Email@…!")`. No HTTP route. No CRC.

---

## Planning (LAW — ASK)

When the module might **send mail**, **ASK before scaffolding**:

> Use DACore email senders and templates? (Almost always yes.)

**9 of 10** say yes. `Config::email` / `Parts\Email::send` only if they **decline**.

**Your module:** a `<select>` in **your** settings — which DACore sender (and which template) for this event. Optionally one sender per user. That is all. Do **not** clone DACore’s SMTP forms.

---

## Typical module

1. Settings: `<select>` from `Email@listSenders!` / `Email@listTemplates!` (encrypted `token` in HTML; store **int** `id` in `{lowercase}_*`).
2. Optional installer: `Email@addTemplate!` for module slugs (`Shop.Order`). `Email@registerSender!` only if you ship a dedicated SMTP account (upsert by **name** — second install does not duplicate).
3. Event: `Email@send!` with the stored sender token + template **slug** (or token) + `vars`.

Default sender: row with `is_default`, else the first sender.

---

## API

### `Email@listSenders!` → `list<array>`

`id`, `token`, `name`, `email`, `is_default`. No password, no host.

### `Email@listTemplates!` → `list<array>`

`id`, `token`, `slug`, `name`, `is_system`.

### `Email@registerSender!` → `{ok, id?, token?, message?, errors?}`

Upsert by **name**. Keys: `name`, `email`, `host`, `port`, `secure` (`tls`|`ssl`|`''`), `username`, `password`, optional `timeout`, `is_default`. Check `ok === true`.

### `Email@testSender!` → `{ok, message, errors}`

`$sender` = int id, encrypted `token`, or **name**. Optional `$to`.

```php
DotApp::call("DACore:Email@testSender!", $sender['id'], 'you@example.com');
```

### `Email@addTemplate!` → `{ok, id?, slug?, message?, errors?}`

Upsert by **slug**. `TESTMAIL` / `CONFIRM` / `WELCOME` **cannot** be overwritten this way. Slug: `[A-Za-z0-9][A-Za-z0-9._-]*`, max 64. Prefer `Module.Event` (`Shop.Order`).

### `Email@send!` → `true` **or** `string[]`

| Key | Rule |
|-----|------|
| `id` | Encrypted sender **token** (never a raw int in `send`) |
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

HTML selects: encrypted `token`. **Store ints** in your tables. Use `DotApp::call` — do not import DACore email store classes.

---

## MUST NOT

- SMTP UI / PHPMailer / `Config::email` unless the user declined DACore senders
- `Parts\Email::send` as the default path
- `INSERT` into `dacore_email_senders` / `dacore_email_templates`
- Raw int as `Email@send!` `id`
- Both `template` and `text`
- Overwrite `TESTMAIL` / `CONFIRM` / `WELCOME` via `addTemplate!`
- A second inbox — `DACore:Notifications@push` ([37](37-DACORE-NOTIFICATIONS.md))
