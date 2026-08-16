# 22 — AI, FastSearch, MCP

Three different error models. Read carefully — none of these are used in the example modules.

| Library | Error model |
|---------|-------------|
| AI | **throws `AIException`** |
| FastSearch | **returns** `['success','data','error']` |
| MCP | JSON-RPC `error` object |

---

## 1. AI

### Fluent chain (exact order)

```php
use Dotsystems\App\Parts\AI;
use Dotsystems\App\Parts\AIException;

try {
    $out = AI::driver('AIDriverOpenAI')     // 1. required, first
        ->system('You are a helpful assistant.')   // 2. required -> returns AIRequest
        ->messages([                                // 3. optional
            ['role' => 'user', 'content' => $prompt],
        ])
        ->options([                                 // 4. optional (but api_key + model needed)
            'api_key' => Config::module('Shop', 'ai_key'),
            'model' => 'gpt-4o',
            'temperature' => 0.3,
            'max_tokens' => 800,
        ])
        ->call();                                   // 5. terminal
} catch (AIException $e) {
    Logger::use()->error('AI failed', ['msg' => $e->getMessage()]);
    return Response::json(['status' => 0, 'message' => 'AI unavailable'], 503);
}

$text  = $out['reply'];                        // assistant text
$usage = $out['raw']['usage'] ?? null;         // OpenAI/Grok token usage
$all   = $out['all_messages'];                 // system + conversation
```

`call()` returns exactly:

```php
['all_messages' => array, 'reply' => string, 'raw' => array|null]
```

**There is no** `temperature()`, `stream()`, or `tools()` chain method — pass them through `options()`.

### Options

Shared (OpenAI-compatible drivers: `AIDriverOpenAI`, `AIDriverGrok`):

| Key | Default | Notes |
|-----|---------|-------|
| `api_key` | — | **required** |
| `model` | — | **required** |
| `base_url` | OpenAI `https://api.openai.com/v1`, Grok `https://api.x.ai/v1` | |
| `organization` | — | OpenAI header |
| `ca_file` / `ca_fingerprint` | — | TLS pinning |
| `http_timeout` | connect + 30 s | |
| `http_connect_timeout` | 2 s | |
| `http_max_retries` | 3 (1–15) | retries 429 / 502–504 |
| `http_retry_delay_ms` | 500 | ×1.8 backoff, cap 8 s |
| passthrough | — | `temperature`, `max_tokens`, `max_completion_tokens`, `top_p`, `frequency_penalty`, `presence_penalty`, `response_format`, `seed`, `user`, `n`, `logprobs`, `top_logprobs`, `stop`, `logit_bias` |

Gemini (`AIDriverGemini`) differences:

| Key | Note |
|-----|------|
| `api_key` | sent as `?key=` query param |
| `base_url` | `https://generativelanguage.googleapis.com` |
| `topP` | **not** `top_p` |
| `topK`, `candidateCount` | Gemini names |
| `max_tokens` | mapped to `maxOutputTokens` |
| usage | `$out['raw']['usageMetadata']` |

### Not implemented

- **Tool / function calling** — no `tools` passthrough
- **Streaming** — single blocking request

### `AIException`

Always code `0`. Thrown for: missing `api_key`/`model`, transport failure, provider error JSON, unparseable response, invalid message shape (each message needs string `role` and `content`), unknown driver id.

### Custom driver

```php
class MyAiDriver implements \Dotsystems\App\Parts\AIDriverInterface
{
    public function complete(array $messages, ?string $system, array $options): array
    {
        // must return ['reply' => string, 'raw' => array|null]
        // throw AIException on failure
    }
}

AI::registerDriver('MyAiDriver', MyAiDriver::class);
$out = AI::driver('MyAiDriver')->system('...')->messages([...])->options([...])->call();
```

Per-driver config can also live in `Config::get('ai', 'AIDriverOpenAI')` and is shallow-merged under your `options()`.

---

## 2. FastSearch

### You must register a driver first

`FastSearch::use()` defaults to driver id `'default'`, and **nothing registers it**. Without registration the constructor throws `\Exception("Search driver ... not defined!")`.

```php
// app/config.php
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\FastSearchDriverMeiliSearch;

Config::searchEngines('meilisearch_host', 'http://127.0.0.1:7700');
Config::searchEngines('meilisearch_api_key', $key);
Config::searchDriver('meili', FastSearchDriverMeiliSearch::driver());
```

Available drivers: `FastSearchDriverElasticSearch`, `FastSearchDriverOpenSearch`, `FastSearchDriverMeiliSearch`, `FastSearchDriverAlgolia`, `FastSearchDriverTypeSense`.

### Usage — every method returns an envelope

```php
use Dotsystems\App\Parts\FastSearch;

$fs = FastSearch::use('catalog', 'meili');

$r = $fs->configureIndex('products', ['name' => 'text', 'price' => 'float']);
if (!$r['success']) { /* $r['error']->getErrorCode() */ }

$fs->index('products', '1', ['name' => 'Widget', 'price' => 9.99]);
$fs->bulkIndex('products', [['id' => '2', 'name' => 'Bolt']]);

$r = $fs->search('products', 'widget', ['price' => ['gte' => 5]], 10, 0, [
    'search_fields' => ['name'],
    'highlight' => true,
    'facets' => ['category'],
]);

if ($r['success']) {
    foreach ($r['data'] as $key => $hit) {
        if ($key === 'facets') { continue; }   // facets share the same array
        // $hit is a document array
    }
}
```

| Method | Signature |
|--------|-----------|
| `configureIndex` | `(string $index, $fields = [])` |
| `indexExists` | `(string $index)` → `data` = `bool` |
| `deleteIndex` | `(string $index)` |
| `index` | `(string $index, string $id, array $document)` |
| `bulkIndex` | `(string $index, array $documents)` |
| `search` | `(string $index, string $query, array $filters = [], int $limit = 10, int $offset = 0, array $options = [])` |
| `update` | `(string $index, string $id, array $document)` |
| `delete` | `(string $index, string $id)` |
| `clear` | `(string $index)` |
| `refresh` | `(string $index)` |
| `getIndexSchema` | `(string $index)` → `data` = field ⇒ type |
| `updateIndexSettings` | `(string $index, array $settings)` |

**Names that do not exist:** `addDocuments`, `createIndex`, `settings()`.

### Allowed `$options` keys

`typo_tolerance`, `case_sensitive`, `search_fields`, `return_fields`, `sort`, `highlight`, `match_type`, `facets`

Any other key → `success = false` with error code `INVALID_OPTION`.

Filters: `['field' => $value]` for term filters, `['field' => ['gte' => x, 'lte' => y]]` for ranges.

### `search()` result shape

`data` is a **flat list of documents**, optionally with a string key `'facets'`. There is **no** normalized `total`, `hits`, or `processing_time`.

### `FastSearchError`

Returned, not thrown. Methods: `getErrorCode(): string`, `getHttpStatus(): ?int`, `getContext(): array`.

Codes include: `INVALID_OPTION`, `INVALID_FIELDS`, `INDEX_NOT_FOUND`, `INDEX_ALREADY_EXISTS`, `DOCUMENT_NOT_FOUND`, `INDEXING_FAILED`, `SEARCH_FAILED`, `UPDATE_FAILED`, `DELETION_FAILED`, `CLEAR_FAILED`, `REFRESH_FAILED`, `SCHEMA_RETRIEVAL_FAILED`, `SETTINGS_UPDATE_FAILED`, `CONNECTION`, `AUTHENTICATION_FAILED`, `RATE_LIMIT`, `SERVER_ERROR`, `INVALID_REQUEST`, `UNKNOWN`.

Drivers retry 408/429/503 with backoff (`*_retry_attempts`, `*_retry_delay_ms`).

### Config keys per engine

`elasticsearch_*` / `opensearch_*`: `host`, `username`, `password`, `ca_fingerprint`, `ca_file`, `retry_attempts`, `retry_delay_ms`
`meilisearch_*`: `host`, `api_key`, `ca_fingerprint`, `ca_file`, retries
`algolia_*`: `app_id`, `search_api_key`, `write_api_key`, `wait_for_task`, `ca_*`, retries, `wait_max_attempts`, `wait_delay_ms`
`typesense_*`: `host`, `api_key`, `ca_*`, retries

### Known driver issues

- MeiliSearch `updateIndexSettings()` is **broken** (passes settings where a URL is expected).
- MeiliSearch facet aggregation is buggy.
- Algolia driver **throws on construction** without `algolia_app_id`.
- `limit = 0` can cause a division by zero in Algolia/Typesense pagination.
- `case_sensitive` is a client-side `levenshtein` post-filter, not engine-level.

### Custom driver

`Config::searchDriver($name, $array)` validates only 5 keys, but FastSearch calls **14**: `configureIndex, indexExists, deleteIndex, index, bulkIndex, normalizeOptions, search, update, delete, clear, refresh, getIndexSchema, updateIndexSettings` (+ construction). Implement all of them, each returning the `success/data/error` envelope.

---

## 3. MCP (Model Context Protocol server)

MCP is a **library only** — it registers no routes and enforces no authentication. You must wire and protect it.

### Registering capabilities

```php
use Dotsystems\App\Parts\MCP;

$ok = MCP::addTool(
    'shop_find_item',
    'Find a shop item by title',
    [
        'title' => ['type' => 'string', 'description' => 'Title fragment'],
    ],
    function (array $params) {
        $rows = DB::module('RAW')->q(function ($qb) use ($params) {
            $qb->raw('SELECT id,title FROM shop_items WHERE title LIKE :t LIMIT 20',
                     ['t' => '%' . $params['title'] . '%']);
        })->all();
        return ['items' => $rows];
    }
);
if ($ok === false) { /* invalid args, duplicate name, or non-callable */ }
```

| Method | Returns |
|--------|---------|
| `MCP::addTool($name, $description, array $parameters, $callback, $authentication = null)` | `bool` |
| `MCP::addResource($name, $description, $uri, $mimeType, array $arguments, $callback)` | `bool` |
| `MCP::addPrompt($name, $description, array $parameters, $callback)` | `bool` |
| `MCP::discovery()` | `['tools'=>..,'resources'=>..,'prompts'=>..]` |
| `MCP::execute($request)` | JSON-RPC response array |
| `MCP::getAll()` | full registry incl. callbacks |

Parameter schema: `paramName => ['type' => 'string'|'integer'|'boolean', 'description' => string]`.
**All declared parameters are required** at call time. Only those three types are supported.

`$authentication` metadata is stored and returned in discovery but **never enforced** — protect the route yourself.

### Exposing it

```php
Router::post('/mcp', function ($request) {
    return Response::json(MCP::execute($request));
})->before('#Shop:ApiGate@check!');
```

`execute()` expects the DotApp **`RequestObj`** (it reads `$request->data(true)`), not a raw array.

### JSON-RPC contract

Request: `{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"shop_find_item","arguments":{"title":"bolt"}}}`

Supported methods: `initialize` (requires `protocolVersion` `2025-06-18`), `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`, `prompts/get`.

| Error code | Meaning |
|------------|---------|
| `-32600` | Invalid request envelope |
| `-32601` | Method / tool / resource / prompt not found |
| `-32602` | Missing or invalid parameter, incompatible protocol version |
| `-32001` | Exception thrown inside your callback |

Server identity is fixed: `mcp-dotapp-server`, version `1.8`, protocol `2025-06-18`.

Resource callbacks are wrapped as `['data' => <return>, 'mimeType' => <registered>]`.
