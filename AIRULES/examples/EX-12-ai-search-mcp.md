# EX-12 — AI, FastSearch, MCP

Rules: [22-AI-SEARCH-MCP.md](../22-AI-SEARCH-MCP.md). Three different error models — do not mix them.

## AI — throws `AIException`

```php
use Dotsystems\App\Parts\AI;
use Dotsystems\App\Parts\AIException;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\Logger;

function askAi(string $prompt): ?array
{
    $key = Config::module('Shop', 'ai_key');
    if (empty($key)) {
        return null;   // configuration missing — fail soft
    }

    try {
        $out = AI::driver('AIDriverOpenAI')
            ->system('You are a concise product assistant.')
            ->messages([
                ['role' => 'user', 'content' => $prompt],
            ])
            ->options([
                'api_key' => $key,
                'model' => Config::module('Shop', 'ai_model') ?? 'gpt-4o',
                'temperature' => 0.2,
                'max_tokens' => 600,
                'http_timeout' => 40,
                'http_max_retries' => 3,
            ])
            ->call();
    } catch (AIException $e) {
        Logger::use()->error('AI call failed', ['msg' => $e->getMessage()]);
        return null;
    }

    return [
        'text' => $out['reply'],                          // assistant text
        'tokens' => $out['raw']['usage']['total_tokens'] ?? null,
    ];
}
```

`call()` returns `['all_messages' => array, 'reply' => string, 'raw' => array|null]`.

Gemini differences: `topP` (not `top_p`), `topK`, `candidateCount`, usage under `$out['raw']['usageMetadata']`.

**No tool/function calling and no streaming** — do not attempt either.

---

## FastSearch — register a driver, then check `['success']`

```php
// app/config.php — REQUIRED, nothing registers a default driver
use Dotsystems\App\Parts\FastSearchDriverMeiliSearch;

Config::searchEngines('meilisearch_host', 'http://127.0.0.1:7700');
Config::searchEngines('meilisearch_api_key', $meiliKey);
Config::searchDriver('meili', FastSearchDriverMeiliSearch::driver());
```

```php
use Dotsystems\App\Parts\FastSearch;
use Dotsystems\App\Parts\Logger;

function searchItems(string $query, int $limit = 10, int $offset = 0): array
{
    try {
        $fs = FastSearch::use('catalog', 'meili');
    } catch (\Throwable $e) {
        // Recovered path (empty result, page still renders) → severity 'info' (18 §9).
        Diag::reportCatch('Shop:Search@query', 'shop.search.driver_missing', $e, 'info', ['q_len' => strlen($q)]);
        return [];
    }

    $r = $fs->search('products', $query, ['active' => 1], $limit, $offset, [
        'search_fields' => ['name', 'description'],
        'highlight' => true,
    ]);

    if (!$r['success']) {
        Logger::use()->error('search failed', [
            'code' => $r['error']->getErrorCode(),
            'http' => $r['error']->getHttpStatus(),
        ]);
        return [];
    }

    $hits = [];
    foreach ($r['data'] as $key => $hit) {
        if ($key === 'facets') { continue; }   // facets live in the same array
        $hits[] = $hit;
    }
    return $hits;
}
```

Indexing:

```php
$fs->configureIndex('products', ['name' => 'text', 'price' => 'float']);
$fs->index('products', (string) $id, ['name' => $name, 'price' => $price, 'active' => 1]);
$fs->bulkIndex('products', $documents);
$fs->delete('products', (string) $id);
```

Method names are `configureIndex`, `bulkIndex`, `updateIndexSettings` — **not** `createIndex`, `addDocuments`, `settings`. Allowed `$options` keys only: `typo_tolerance, case_sensitive, search_fields, return_fields, sort, highlight, match_type, facets`.

Keep an SQL fallback: search is an optional dependency.

---

## MCP — library only, you must route and protect it

```php
use Dotsystems\App\Parts\MCP;
use Dotsystems\App\Parts\DB;

// register once (module initialize or a boot controller)
MCP::addTool(
    'shop_find_item',
    'Find shop items by title fragment',
    [
        'title' => ['type' => 'string', 'description' => 'Part of the title'],
    ],
    function (array $params) {
        $rows = DB::module('RAW')->q(function ($qb) use ($params) {
            $qb->raw(
                'SELECT id, title, price FROM shop_items WHERE title LIKE :t LIMIT 20',
                ['t' => '%' . $params['title'] . '%']
            );
        })->all();
        return ['items' => $rows];
    }
);
```

```php
// route — MCP registers none, and does NOT enforce auth
Router::post('/shop/mcp', function ($request) {
    return Response::json(MCP::execute($request));
}, Router::STATIC_ROUTE)->before('#Shop:ApiGate@check!');
```

Only `string`, `integer`, `boolean` parameter types are supported and **every declared parameter is required**. `addTool()` returns `bool` — check it. `$authentication` metadata is ignored at call time, so the route guard is your only protection.

Error codes returned inside the JSON-RPC envelope: `-32600` invalid request, `-32601` not found, `-32602` bad parameter/protocol, `-32001` exception inside your callback.
