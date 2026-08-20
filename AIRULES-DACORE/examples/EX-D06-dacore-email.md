# EX-D06 — Send mail through DACore

**Open when the module sends mail.** Rules: [38](../38-DACORE-EMAIL.md). Inbox: [EX-D05](EX-D05-dacore-notifications.md).

In-process: `DotApp::call("DACore:Email@…!")`. No HTTP. No CRC.

```php
use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Logger;

// Installer (optional): seed a module template. Upsert by slug. TESTMAIL/CONFIRM/WELCOME are blocked.
$tpl = DotApp::call('DACore:Email@addTemplate!', [
    'slug' => 'Shop.Order',
    'name' => 'Order confirmation',
    'body' => '<p>Hi {{ name }}</p>',
]);
if (($tpl['ok'] ?? false) !== true) {
    Logger::use()->error('Shop template seed failed', ['errors' => $tpl['errors'] ?? []]);
}

// Settings: fill a <select> — token in HTML, store int id in shop_*.
$senders = DotApp::call('DACore:Email@listSenders!');
$templates = DotApp::call('DACore:Email@listTemplates!');

// Event: send. id = encrypted token. template = slug (or token).
$result = DotApp::call('DACore:Email@send!', [
    'id' => $senderToken,
    'to' => $email,
    'subject' => 'Your order',
    'template' => 'Shop.Order',
    'vars' => ['name' => $name, 'email' => $email],
]);
if ($result !== true) {
    Logger::use()->error('Shop mail failed', ['errors' => $result]);
}
```

Optional dedicated SMTP (upsert by **name** — second install does not duplicate):

```php
$sender = DotApp::call('DACore:Email@registerSender!', [
    'name' => 'Shop mail',
    'email' => 'shop@example.com',
    'host' => 'smtp.example.com',
    'port' => 587,
    'secure' => 'tls',
    'username' => 'shop@example.com',
    'password' => 'secret',
]);
if (($sender['ok'] ?? false) !== true) {
    return;
}
DotApp::call('DACore:Email@testSender!', $sender['id'], 'you@example.com');
```

**MUST NOT:** clone DACore SMTP screens; `Config::email` / `Parts\Email::send` unless declined; raw int as `send` `id`; both `template` and `text`.
