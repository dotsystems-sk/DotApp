# EX-11 — Email, SMS, QR

Rules: [21-EMAIL-SMS-QR.md](../21-EMAIL-SMS-QR.md).

## Email — returns `true` OR an array of error strings

```php
use Dotsystems\App\Parts\Email;
use Dotsystems\App\Parts\Logger;

function sendOrderMail(string $to, string $html): bool
{
    try {
        $result = Email::send(
            'main',            // account configured via Config::email('main','smtp',[...])
            $to,
            'Your order',
            $html,
            null,              // null => text/html
            [],                // attachments (file paths)
            [],                // cc
            []                 // bcc
        );
    } catch (\Throwable $e) {
        // thrown only when the SMTP account config is missing
        Logger::use()->error('Mail config missing', ['msg' => $e->getMessage()]);
        return false;
    }

    if ($result !== true) {
        // $result is string[] — NOT false
        Logger::use()->error('Mail send failed', ['errors' => $result]);
        return false;
    }
    return true;
}
```

Config (in `app/config.php`):

```php
Config::email('main', 'smtp', [
    'host' => 'smtp.example.com',
    'port' => 587,
    'timeout' => 30,
    'secure' => 'tls',          // '' | 'ssl' | 'tls'
    'username' => 'user@example.com',
    'password' => 'secret',
    'from' => 'user@example.com',
]);
```

No `fromName`, no `replyTo`. Multiple recipients: comma-separated string or array.

### Reading a mailbox

```php
use Dotsystems\App\Parts\Emailer;

$emailer = new Emailer(
    ['host'=>'smtp.example.com','port'=>587,'secure'=>'tls','timeout'=>30],
    ['host'=>'imap.example.com','port'=>993,'secure'=>'ssl','timeout'=>30,'protocol'=>'imap']
);
$emailer->setCredentials('smtpUser','smtpPass','imapUser','imapPass');

$list = $emailer->getEmailList('INBOX', 20, 0, 'UNSEEN');   // [] on failure
foreach ($list as $id => $meta) {
    // $meta['headers'] (lowercase keys), $meta['flags']
    $msg = $emailer->getEmail($id, 'INBOX');                 // null on failure
    if ($msg === null) { continue; }
    // $msg['headers'], $msg['body'], $msg['flags'], $msg['attachments']
}
if ($errors = $emailer->getErrors()) {
    Logger::use()->error('IMAP problems', ['errors' => $errors]);
}
$emailer->disconnect();
```

POP3 ignores folders and SEARCH criteria; there is no mark-as-read API.

---

## SMS — you must implement a provider

```php
use Dotsystems\App\Parts\SmsProvider;
use Dotsystems\App\Parts\HttpHelper;

class GatewaySms implements SmsProvider
{
    private array $config = [];

    public function setConfig($config): void { $this->config = (array) $config; }

    public function validatePhoneNumber($phoneNumber, $callback = null): bool
    {
        return (bool) preg_match('/^\+?[0-9]{9,15}$/', (string) $phoneNumber);
    }

    public function send($phoneNumber, $message, $options = [])
    {
        if (!$this->validatePhoneNumber($phoneNumber)) {
            return ['ok' => false, 'error' => 'invalid_number'];
        }
        $res = HttpHelper::request('POST', 'https://gateway.example/send', [
            'to' => $phoneNumber, 'text' => $message,
        ], ['timeout' => 10], ['Authorization: Bearer ' . ($this->config['api_key'] ?? '')]);

        if (!$res['success']) {
            return ['ok' => false, 'error' => $res['error'], 'http' => $res['http_code']];
        }
        return ['ok' => true, 'id' => $res['response']['id'] ?? null];
    }

    public function receive($filter, $message = null) { return []; }
    public function getStatus($messageId) { return 'unknown'; }
}
```

```php
use Dotsystems\App\Parts\Sms;

try {
    Sms::setConfig(GatewaySms::class, ['api_key' => Config::module('Shop', 'sms_key')]);
    $r = Sms::send(GatewaySms::class, '+421900000000', 'Your code: 123456');
} catch (\Throwable $e) {
    Logger::use()->error('SMS failed', ['msg' => $e->getMessage()]);
}
```

Accept extra optional parameters in `validatePhoneNumber`/`receive` — the facade passes 2 arguments while the interface declares 1.

---

## QR (needs GD, PNG only)

```php
use Dotsystems\App\Parts\QR;
use Dotsystems\App\Parts\TOTP;

try {
    $secret = TOTP::newSecret();
    $uri = TOTP::otpauth($userEmail, $secret);

    $qr = QR::generate($uri, [
        'level' => 'qrm',
        'fg' => '000000',
        'bg' => 'FFFFFF',
        'size_multiplier' => 5,
    ]);

    $dataUri = QR::imageToBase64($qr->outputPNG());
} catch (\Throwable $e) {
    Logger::use()->error('QR failed', ['msg' => $e->getMessage()]);
    $dataUri = '';
}
```

```html
{{ if $qrDataUri !== '' }}
  <img src="{{ var: $qrDataUri }}" width="220" height="220" alt="" />
{{ /if }}
```

No SVG output, no file-save helper, no logo support. Oversized input is silently truncated.
