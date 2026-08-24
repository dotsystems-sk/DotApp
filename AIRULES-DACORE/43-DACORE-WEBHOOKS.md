# 43 — DACore outbound webhooks (LAW)

**Open this file only when a module sends outbound webhooks or registers a webhook driver.** Inbox notifications are [37](37-DACORE-NOTIFICATIONS.md). Mail is [38](38-DACORE-EMAIL.md). SMS is [39](39-DACORE-SMS.md).

DACore keeps a **driver registry** and an **endpoint table**. Consumer modules store an `endpoint_key` and call `DACore:Webhooks@send!`. They do **not** invent a second HTTP signer or POST stack.

Programmatic APIs are in-process only: `DotApp::call("DACore:Webhooks@…!")`. Class name is `Webhooks` (file `Webhooks.php`). DACore's root-only administration routes use the same stores and never expose the HMAC secret.

The operator switch is `DacoreRegistryCache::featureEnabled('webhooks')`, default **off**. While off, `send!` / `testEndpoint!` / `sendHttpHmac!` perform **zero** endpoint queries, **zero** driver-registry queries, **zero** provider autoload, and **zero** network.

---

## Consumer API

### `Webhooks@send!` → `{ok, status, message}`

| Arg | Rule |
|-----|------|
| `$endpointKey` | Stable `endpoint_key`, or one array `['endpoint'\|'endpoint_key', 'event', 'payload']` |
| `$event` | `^[a-z][a-z0-9_]{0,47}(\.[a-z][a-z0-9_]{0,47}){0,7}$`, max 80 chars |
| `$payload` | Array of JSON values only (null / bool / int / float / string / array). No objects, resources, INF/NAN. Max depth 8, max 200 nodes, max 65536 encoded bytes |

`status` is one of: `ok`, `disabled`, `invalid`, `not_found`, `blocked`, `failed`, `error`.

```php
$result = DotApp::call('DACore:Webhooks@send!', $endpointKey, 'shop.order.paid', [
    'order_id' => $orderId,
]);
if (($result['ok'] ?? false) !== true) {
    Logger::use()->error('Shop webhook failed', ['status' => $result['status'] ?? '']);
}
```

Never log URL, HMAC secret, or payload.

### `Webhooks@testEndpoint!`

Same path as `send!` with event `dacore.webhook.test` and payload `['ok' => true]`.

### Register / list (in-process, no UI)

- `registerDriver!` / `unregisterDriver!` / `unregisterByCreator!` / `listDrivers!` / `setDriverEnabled!`
- `registerEndpoint!` / `unregisterEndpoint!` / `unregisterEndpointsByCreator!` / `listEndpoints!`

`listEndpoints!($page, $perPage)` returns a bounded pager envelope (`data`, `current_page`, `last_page`, `per_page`, `total`). Rows expose `has_secret` (0/1) and **never** `hmac_secret`.

---

## Driver contract (v1)

Register from the **driver** module `Installation.php` (upsert by `driver_key`):

```php
$reg = DotApp::call('DACore:Webhooks@registerDriver!', [
    'driver_key'       => 'mygw.webhook',
    'creator'          => 'MyGw',
    'name'             => 'My Gateway',
    'controller_send'  => 'MyGw:Provider@send',
    'controller_test'  => 'MyGw:Provider@test', // optional
    'settings_url'     => '/dacore/mygw/settings', // optional relative path
    'contract_version' => 1,
    'enabled'          => 1,
]);
```

Uninstall: `Webhooks@unregisterByCreator!` with the module name. **`creator=DACore` is refused** so the built-in HTTP/HMAC driver cannot be wiped.

`driver_key` / `endpoint_key`: `[A-Za-z0-9][A-Za-z0-9._-]{0,49}`. Controllers: `Module:Controller@method`. `contract_version` must be `1`. The first `creator` owns the key; another module cannot overwrite it. `enabled=0` keeps the row but removes it from dispatch.

Disabling an enabled driver first fires `module.dacore.driver_deactivate.veto` with driver metadata only. Endpoint URLs, HMAC secrets, headers, and payloads are excluded.

External `controller_send($event, $payload, $context)` receives **secret-free** metadata: `endpoint_key`, `driver_key`, `name`, `url`, `timeout`, `creator`. HMAC material stays in **that** module’s config unless the built-in HTTP/HMAC driver is selected.

The built-in sender is invoked as `DACore:Webhooks@sendHttpHmac!($endpointId, $event, $payload)`. It re-reads the endpoint row and decrypts the HMAC secret internally. Dispatch never forwards the secret.

---

## Built-in HTTP/HMAC

Seed row (Installation owns INSERT):

| Column | Value |
|--------|--------|
| `driver_key` | `dacore.http_hmac` |
| `creator` | `DACore` |
| `name` | `HTTP HMAC` |
| `controller_send` | `DACore:Webhooks@sendHttpHmac` |
| `controller_test` | `DACore:Webhooks@testHttpHmac` |
| `contract_version` | `1` |
| `enabled` | `1` |

### Canonical string

```
{unix_timestamp}.{event}.{raw_json_body}
```

Example: `1700000000.shop.order.paid.{"id":7}`

`raw_json_body` is `json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)` after the bounds check.

HMAC: `hash_hmac('sha256', $canonical, $secret)` (hex). Header:

- `X-DACore-Webhook-Event: {event}`
- `X-DACore-Webhook-Timestamp: {unix}`
- `X-DACore-Webhook-Signature: sha256={hex}`
- `Content-Type: application/json`

Verify with `hash_equals`. Outbound send **computes** the signature; it does not need to verify it.

Timeouts: 1–10 seconds (default 5). Intended connect timeout is 2 seconds.

---

## URL / SSRF rules

Every stored and sent URL:

- HTTPS only
- No userinfo, no fragment
- Host is not `localhost` / `*.localhost` / numeric-only
- IP literals in loopback, RFC1918, link-local, CGNAT (`100.64.0.0/10`), benchmark (`198.18.0.0/15`), multicast, and other reserved ranges are rejected (IPv4 and IPv6, including IPv4-mapped IPv6)
- On send, DNS is re-resolved; **any** non-public A/AAAA fails closed (empty DNS also fails closed)

---

## Pinned HTTPS transport

`HttpHelper::request()` is not used because it cannot disable redirects or pin the validated DNS answer. The built-in sender has one bounded DACore-local cURL path with all of these controls:

- HTTPS protocol only; TLS peer and hostname verification on
- redirects and proxies disabled
- every A/AAAA answer must be public, then the chosen answer is pinned with `CURLOPT_RESOLVE`
- connect timeout 2 seconds and bounded total timeout
- response body discarded without buffering
- one synchronous attempt, with no retry or queue

External drivers that own their own HTTP stack remain responsible for their own SSRF posture; DACore still validates the endpoint URL at register/send time.

---

## Cache

`WebhookDriversStore::cacheRows()` is **driver metadata only** (`id`, `driver_key`, `creator`, `name`, `controller_send`, `controller_test`, `settings_url`, `contract_version`, `enabled`). **Never** endpoint URL, HMAC secret, or payload.

`DacoreRegistryCatalog` writes `WebhookDriversStore::cacheRows()` into `webhook_drivers` only while the feature is enabled. Without the generated cache, dispatch uses the exact indexed DB fallback.

Endpoints are **never** cached. Send loads the endpoint by exact `endpoint_key` from the database, then resolves the driver from cache / exact DB fallback.

No `module.init.php` autoload of provider modules.

---

## Administration

`{prefixUrl}/dacore/webhooks` is a root-only, separately lazy admin leaf. Driver metadata and endpoint rows use independent COUNT + LIMIT pagers. GET never invokes providers and never selects `hmac_secret`; the secret field is write-only, and blank on edit keeps the existing cipher. The feature-off path performs no registry read or mutation. Driver/endpoint disable and endpoint delete require graphical confirmation plus PHP `StepUp::verify()`. A driver cannot be disabled while it has an enabled endpoint; disable those endpoints first. Bulk creator cleanup also refuses to remove a driver referenced by another creator's endpoint, so module uninstall cannot leave broken transport references. Save, test, toggle, delete, and pager actions patch the page and toast without a reload.

---

## Schema (Installation — main model)

### `dacore_webhook_drivers`

```sql
CREATE TABLE IF NOT EXISTS `dacore_webhook_drivers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `driver_key` varchar(50) NOT NULL,
    `creator` varchar(64) NOT NULL,
    `name` varchar(190) NOT NULL,
    `controller_send` varchar(190) NOT NULL,
    `controller_test` varchar(190) NOT NULL DEFAULT '',
    `settings_url` varchar(255) NOT NULL DEFAULT '',
    `contract_version` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
    `enabled` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `driver_key` (`driver_key`),
    KEY `creator` (`creator`),
    -- Query: enabled contract-v1 driver lookup by driver_key for lazy webhook dispatch.
    KEY `enabled_contract_key` (`enabled`,`contract_version`,`driver_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

Helpers: `WebhookDriversStore::tableSql()`, `WebhookDriversStore::builtinPayload()`.

### `dacore_webhook_endpoints`

```sql
CREATE TABLE IF NOT EXISTS `dacore_webhook_endpoints` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `endpoint_key` varchar(50) NOT NULL,
    `creator` varchar(64) NOT NULL,
    `name` varchar(190) NOT NULL,
    `driver_key` varchar(50) NOT NULL,
    `url` varchar(2048) NOT NULL,
    `hmac_secret` text NOT NULL,
    `timeout` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
    `enabled` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `endpoint_key` (`endpoint_key`),
    KEY `creator` (`creator`),
    KEY `driver_key` (`driver_key`),
    -- Query: enabled endpoint exact lookup by endpoint_key at send time.
    KEY `enabled_endpoint_key` (`enabled`,`endpoint_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

Helper: `WebhookEndpointsStore::tableSql()`. Encrypt `hmac_secret` with `DotApp::dotApp()->encrypt($plain, 'webhook.endpoint.hmac', true)`.

Successful delivery fires `module.dacore.webhook_sent.hook` once with endpoint id/key, driver key, and event name only.

---

## MUST NOT

- Inbound webhook routes, queues, retries, dead-letter, or `dotapp.catchall` listeners for this feature
- Cache endpoint URL / HMAC secret / payload
- Return `hmac_secret` from list/read/cache/log/hook/reply
- Add another webhook HTTP path; the built-in pinned transport is the only DACore-owned network sender
- `INSERT` into `dacore_webhook_*` from a consumer module — use `Webhooks@register*!`
