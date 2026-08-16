# 21 — Email, SMS, QR

These libraries are **not** used by the example modules. Everything below comes from the core source. Do not invent methods.

---

## 1. Email

**There is no fluent `to()->subject()->send()` chain.** Only the static `Email` facade and the imperative `Emailer` class.

### Configuration

```php
// app/config.php
Config::email('main', 'smtp', [
    'host' => 'smtp.example.com',
    'port' => 587,
    'timeout' => 30,
    'secure' => 'tls',        // '' | 'ssl' | 'tls'
    'username' => 'user@example.com',
    'password' => 'secret',
    'from' => 'user@example.com',
]);

// Optional, only needed for sendAndSave()
Config::email('main', 'imap', [
    'host' => 'imap.example.com',
    'port' => 993,
    'timeout' => 30,
    'secure' => 'ssl',
    'username' => 'user@example.com',
    'password' => 'secret',
]);
```

Supported SMTP keys read by the code: `host`, `port`, `timeout`, `secure`, `username`, `password`, `from`.
Supported IMAP keys: `host`, `port`, `timeout`, `secure`, `username`, `password`.

**Not supported:** `fromName`, `auth`/`authType` in config, `replyTo`. SMTP auth type is only settable programmatically via `Emailer::setCredentials(..., $smtpAuthType)`.

### Sending

```php
use Dotsystems\App\Parts\Email;
use Dotsystems\App\Parts\Logger;

$result = Email::send(
    'main',                        // account name
    'a@example.com,b@example.com', // string (comma separated) or array
    'Subject',
    '<p>HTML body</p>',
    null,                          // contentType; null => 'text/html'
    ['/abs/path/file.pdf'],        // attachments (file paths)
    ['cc@example.com'],            // cc
    ['bcc@example.com']            // bcc
);

if ($result !== true) {
    // $result is string[] of error messages
    Logger::use()->error('Mail failed', ['errors' => $result]);
}
```

| Method | Returns | Throws |
|--------|---------|--------|
| `Email::send($account,$to,$subject,$body,$contentType=null,$attachments=[],$cc=[],$bcc=[])` | `true` **or `string[]` of errors** | `\Exception` only if the SMTP account config is missing |
| `Email::sendAndSave($folder,$account,$to,$subject,$body,$contentType=null,$attachments=[],$cc=[],$bcc=[])` | same | `\Exception` if SMTP **or** IMAP config missing |

**Never assume a boolean `false` on failure — it returns an array.** Check `!== true`.

Notes: BCC is used in `RCPT TO` but not written into visible headers. A bad attachment path logs an error but the send may still succeed.

### Emailer (send + receive)

```php
use Dotsystems\App\Parts\Emailer;

$emailer = new Emailer(
    ['host'=>'smtp.example.com','port'=>587,'secure'=>'tls','timeout'=>30],
    ['host'=>'imap.example.com','port'=>993,'secure'=>'ssl','timeout'=>30,'protocol'=>'imap']
);
$emailer->setCredentials('smtpUser','smtpPass','imapUser','imapPass'); // 5th arg: 'LOGIN'|'PLAIN'|'NTLM'
$emailer->setDebugLevel(2);

if (!$emailer->sendEmail('from@example.com', ['to@example.com'], [], [], 'Hi', 'Body', 'text/html')) {
    $errors = $emailer->getErrors();
}

$list = $emailer->getEmailList('INBOX', 10, 0, 'UNSEEN');  // [] on failure
$msg  = $emailer->getEmail(42, 'INBOX');                    // null on failure
$emailer->deleteEmail(42, 'INBOX');                         // bool
$emailer->saveAttachment(42, 0, '/tmp/att.bin', 'INBOX');   // bool
$emailer->disconnect();
```

Constructor throws `\InvalidArgumentException` on invalid config. Everything else returns `bool` / `array` / `null` and accumulates messages in `getErrors()` — **no exceptions for protocol failures**.

`switchReceiverProtocol('imap'|'pop3')` throws on any other value.

### Message shapes

`getEmailList()` → array keyed by message id: `['headers' => array (lowercase keys), 'flags' => string[]]`
`getEmail()` → `['headers' => array, 'body' => string, 'flags' => string[], 'attachments' => [['headers'=>..,'data'=>..,'filename'?,'size'?], ...]]`

### IMAP vs POP3

| Feature | IMAP | POP3 |
|---------|------|------|
| Folders / `$mailbox` | yes | ignored |
| SEARCH criteria (`ALL`, `UNSEEN`, `FROM "x"`) | yes | only `RECENT`/`ALL` return data |
| Flags in list | yes | no |
| STARTTLS | yes | **no** (SSL only) |
| Save to Sent (`sendAndSave`) | yes | no |
| Mark as read | **not implemented** | n/a |

Requirements: raw sockets + `stream_socket_enable_crypto` for TLS. The PHP `imap` extension is **not** used. `fileinfo` improves attachment MIME detection.

---

## 2. SMS

**No concrete provider ships with the framework** — only the `SmsProvider` interface. You must implement one.

### Interface to implement

```php
use Dotsystems\App\Parts\SmsProvider;

class MySmsProvider implements SmsProvider
{
    private array $config = [];

    public function send($phoneNumber, $message, $options = [])
    {
        $res = \Dotsystems\App\Parts\HttpHelper::request('POST', 'https://gateway/send', [
            'to' => $phoneNumber, 'text' => $message,
        ], ['timeout' => 10], ['Authorization: Bearer ' . ($this->config['api_key'] ?? '')]);

        if (!$res['success']) {
            return ['ok' => false, 'error' => $res['error']];
        }
        return ['ok' => true, 'id' => $res['response']['id'] ?? null];
    }

    public function receive($filter) { return []; }

    public function validatePhoneNumber($phoneNumber): bool
    {
        return (bool) preg_match('/^\+?[0-9]{9,15}$/', (string) $phoneNumber);
    }

    public function getStatus($messageId) { return 'unknown'; }

    public function setConfig($config): void { $this->config = (array) $config; }
}
```

### Facade

```php
use Dotsystems\App\Parts\Sms;

Sms::setConfig(MySmsProvider::class, ['api_key' => $key]);
$result = Sms::send(MySmsProvider::class, '+421900000000', 'Hello');
```

| Method | Returns |
|--------|---------|
| `Sms::send($provider, $phone, $message, array $ctorArgs = [])` | provider's return |
| `Sms::validatePhoneNumber($provider, $phone, $callback = null, array $ctorArgs = [])` | provider's return |
| `Sms::receive($provider, $phone, $message, array $ctorArgs = [])` | provider's return |
| `Sms::getStatus($provider, $messageId, array $ctorArgs = [])` | provider's return |
| `Sms::setConfig($provider, $config, array $ctorArgs = [])` | provider's return |

`$provider` may be an instance or a class-name string. Throws `\InvalidArgumentException` for an invalid provider and wraps provider errors in `\RuntimeException` — **always `try/catch`**.

**Arity mismatch warning:** the facade calls `validatePhoneNumber($phone, $callback)` and `receive($phone, $message)` with 2 arguments while the interface declares 1. Accept extra optional parameters in your implementation to stay compatible.

---

## 3. QR

Requires the **GD extension**. PNG output only.

```php
use Dotsystems\App\Parts\QR;

try {
    $qr = QR::generate('https://example.com', [
        'level' => 'qrm',          // qrl | qrm | qrq | qrh
        'fg' => '000000',
        'bg' => 'FFFFFF',
        'size_multiplier' => 5,
        'density' => 0.95,
    ]);

    $dataUri = QR::imageToBase64($qr->outputPNG());   // data:image/png;base64,...
} catch (\Throwable $e) {
    Logger::use()->error('QR failed', ['msg' => $e->getMessage()]);
}
```

| Method | Returns | Throws |
|--------|---------|--------|
| `QR::generate($text, array $config = [])` | `QR` instance | `\InvalidArgumentException` on empty text |
| `$qr->outputPNG()` | GD image resource | — |
| `QR::imageToBase64($image)` | `string` data URI | `\RuntimeException` if PNG encoding fails |

**No** `outputSVG()`, no save-to-file helper, no logo overlay.

### Config options

| Key | Default | Meaning |
|-----|---------|---------|
| `level` | `'qrl'` | error correction: `qrl`=L, `qrm`=M, `qrq`=Q, `qrh`=H |
| `bg` | `'FFFFFF'` | background hex |
| `fg` | `'000000'` | foreground hex |
| `density` | `1` | module fill ratio |
| `size_multiplier` | `5` | scales `sf` and padding |
| `wq` | `1` | quiet-zone width multiplier |
| `wm` | `1` | module width multiplier |
| `sf` | `4` × `size_multiplier` | base scale |
| `sx` / `sy` | `= scale` | per-axis scale |
| `p` | `0` × `size_multiplier` | padding base |
| `pv` / `ph` | `= p` | vertical / horizontal padding |
| `pt` / `pl` / `pr` / `pb` | derived | individual sides |

Input longer than the detected capacity is **silently truncated**.

### Serving a QR from a route

```php
Router::get('/shop/qr/{data:s}', function ($request) {
    $qr = QR::generate($request->matchData()['data'], ['level' => 'qmr']);
    ob_start();
    imagepng($qr->outputPNG());
    $png = ob_get_clean();
    return Response::code(200)->contentType('image/png')->body2($png);
});
```

Prefer `imageToBase64()` when embedding into a template (for example a 2FA setup page).
