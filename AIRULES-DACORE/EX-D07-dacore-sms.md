# EX-D07 — Send SMS through DACore drivers

**Open when the module sends SMS or implements a gateway.** Rules: [39](../39-DACORE-SMS.md). Mail: [EX-D06](EX-D06-dacore-email.md).

In-process: `DotApp::call("DACore:Sms@…!")`. No HTTP. No CRC. Class is `Sms`, not `SMS`.

## Consumer module (shop, CRM, …)

```php
use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Logger;

// Settings: fill a <select> — store sender_key (varchar), not table id.
$senders = DotApp::call('DACore:Sms@listSenders!');
$defaultKey = DotApp::call('DACore:Sms@defaultSenderKey!');

// Event. Empty / DEFAULT uses the default driver.
$result = DotApp::call('DACore:Sms@send!', $senderKey, $phone, $text, $from);
if (($result['ok'] ?? false) !== true) {
    Logger::use()->error('Shop SMS failed', ['errors' => $result['errors'] ?? []]);
}
```

Optional status (only if `supports_status` is 1):

```php
$st = DotApp::call('DACore:Sms@status!', $senderKey, $result['message_id']);
```

## Driver module (one provider)

Installer — upsert by `sender_key`. Second install does not duplicate. Zero routes and zero menu items are allowed; `settings_url` is enough for the DACore list.

```php
$reg = DotApp::call('DACore:Sms@registerSender!', [
    'sender_key'        => 'supersms123.sender',
    'creator'           => 'supersms123',
    'name'              => '123SMS',
    'info'              => 'SMS for SK/CZ. Settings live in this module.',
    'settings_url'      => '/dacore/supersms123/settings',
    'controller_send'   => 'supersms123:Provider@send',
    'controller_status' => 'supersms123:Provider@status',
    'controller_test'   => 'supersms123:Provider@test',
    'supports_from'     => 1,
    'extra1'            => 'profile-key', // optional non-secret routing token
]);
if (($reg['ok'] ?? false) !== true) {
    Logger::use()->error('SMS driver register failed', ['errors' => $reg['errors'] ?? []]);
}
```

Uninstaller:

```php
DotApp::call('DACore:Sms@unregisterByCreator!', 'supersms123');
```

Controller in **your** module (`app/modules/supersms123/Controllers/Provider.php`). Same arguments every time. MUST NOT throw. Read credentials from this module.

```php
namespace Dotsystems\App\Modules\supersms123\Controllers;

class Provider extends \Dotsystems\App\Parts\Controller
{
    public static function send($to, $text, $from = '', $options = [], $context = [])
    {
        // extra1 is a driver-private routing token (never a secret).
        $profileKey = is_array($context) ? (string) ($context['extra1'] ?? '') : '';
        unset($profileKey);
        // Call your gateway. Ignore $from when the account has no originator.
        return ['ok' => true, 'message_id' => 'abc123'];
        // return ['ok' => false, 'message' => 'Gateway rejected the number'];
    }

    public static function status($messageId, $context = [])
    {
        return ['ok' => true, 'status' => 'delivered'];
    }

    public static function test($to = '', $context = [])
    {
        return ['ok' => true, 'message' => 'Test SMS accepted'];
    }
}
```

Test send from **your** settings page (not from DACore's list):

```php
DotApp::call('DACore:Sms@testSender!', 'supersms123.sender', $to);
```

**MUST NOT:** write `dacore_sms_senders`; keep API keys in DACore (including `extra1`–`extra3`); clone add/edit on `/dacore/sms-senders`; store auto-increment ids; call `DACore:SMS@…`.
