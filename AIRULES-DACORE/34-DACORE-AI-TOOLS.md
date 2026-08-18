# 34 — DACore AI Tools

DACore ships an AI chat. Your module exposes capabilities to it by registering **tools** in `dacore_ai_tools`. DACore filters tools by the current user's permissions, describes them to the model, and calls your controller when the model picks one.

Prerequisite: the `dacore_ai_tools` table must exist and `Config::module('DACore','AI')['enabled']` must be `true`.

---

## 1. Registration API

```php
DotApp::call("DACore:AITools@register", string $toolid, array $data): bool
DotApp::call("DACore:AITools@delete", string $toolid): bool      // alias: @unregister
```

| Aspect | Detail |
|--------|--------|
| Returns | `bool` |
| `false` when | `$toolid` empty, `$data` not an array, or `creator` / `description` / `controller` empty, or the DB write fails |
| Throws / logs | never — check the return value |
| Idempotency | **upsert by `toolid`** |
| Column safety | unknown keys are dropped by comparing against the live table columns |

`$toolid` is truncated to 100 chars. Convention: `Module.Entity.Action`, e.g. `Shop.Items.Search`.

Register from `Installation.php`, never per request.

---

## 2. `$data` keys (complete)

| Key | Type | Required | Default | Notes |
|-----|------|----------|---------|-------|
| `creator` | string (≤100) | **yes** | — | Your module name; used for cleanup |
| `description` | string | **yes** | — | Short routing hint shown in the tool list. **MUST** be product copy if operators see it ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8). `howtouse` may be technical for the model. |
| `controller` | string | **yes** | — | DotApp callable, e.g. `Shop:AITools@itemsSearch!` |
| `howtouse` | string or array | no | `'{}'` | Detailed prompt text / JSON schema for the model. Arrays are JSON-encoded |
| `rights` | array or JSON string | no | `'[]'` | Permission strings, OR logic. **Empty = tool hidden from everyone** |
| `helper` | int 0–2 | no | `0` | `0` regular, `1` hidden helper, `2` discoverable helper |
| `workflow` | string (≤160) | no | `''` | Groups tools into a named workflow |
| `tool_type` | string (≤50) | no | `''` | Vocabulary: `lookup`, `read`, `write`, `update`, `delete`, `helper` |
| `risk_level` | int 0–5 | no | `1` | Clamped to the range |
| `requires_confirmation` | bool/int | no | `0` | Truthy ⇒ `1`; ask the user before executing |
| `intent_tags` | array or JSON string | no | `'[]'` | Synonyms that help the model pick this tool |
| `allowed_tools` | array or JSON string | no | `'[]'` | Tool ids that may follow this one |
| `forbidden_tools` | array or JSON string | no | `'[]'` | Tool ids that must not follow |

### `rights` — critical difference from the menu

| Value | Effect |
|-------|--------|
| `[]` (default) | **tool is invisible and never executable** |
| `['dotapp.root','Shop.administrator']` | OR check via `Auth::can()` |
| `['Shop.*']` | **does not work** — AI tool rights have **no wildcard support** |

Always list explicit permissions, including `dotapp.root`.

Rights are checked twice: when the tool list is built and again immediately before execution.

---

## 3. Registration example

```php
DotApp::call("DACore:AITools@register", "Shop.Items.Search", [
    'creator' => 'Shop',
    'description' => 'Search shop items by title or SKU and return id, title, price.',
    'controller' => 'Shop:AITools@itemsSearch!',
    'rights' => ['dotapp.root', 'Shop.administrator', 'Shop.items.view'],
    'helper' => 0,
    'tool_type' => 'lookup',
    'risk_level' => 0,
    'requires_confirmation' => false,
    'workflow' => 'Shop.Catalog',
    'intent_tags' => ['find item', 'search product', 'najdi produkt'],
    'allowed_tools' => ['Shop.Items.Update'],
    'forbidden_tools' => [],
    'howtouse' => [
        'parameters' => [
            'query' => 'string, required, part of the title or SKU',
            'limit' => 'integer, optional, default 20, max 100',
        ],
        'returns' => 'JSON: { result: bool, message: string, items: [ {id, title, price} ] }',
        'notes' => 'Read-only. Use before Shop.Items.Update to obtain the item id.',
    ],
]);
```

Put the parameter contract in `howtouse` — the backend does not validate it, the model reads it.

---

## 4. Tool controller contract

```php
DotApp::call($tool['controller'], $data, $chatSession);
```

So your handler is:

```php
public static function itemsSearch($data, $aiobj)
```

| Argument | Content |
|----------|---------|
| `$data` | associative array of the arguments the model supplied |
| `$aiobj` | the active chat session object |

**Return a JSON string** (an array is `json_encode`d for you). Established shape:

```php
return json_encode([
    'result' => true,          // false on failure
    'message' => 'Found 3 items',
    'items' => $rows,
], JSON_UNESCAPED_UNICODE);
```

An uncaught `\Throwable` is caught by DACore and converted into an error JSON, but you should handle failures yourself so the model gets a useful message.

### Complete handler

```php
<?php
namespace Dotsystems\App\Modules\Shop\Controllers;

use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Logger;

class AITools extends \Dotsystems\App\Parts\Controller
{
    public static function itemsSearch($data, $aiobj)
    {
        try {
            $query = trim((string) ($data['query'] ?? ''));
            $limit = (int) ($data['limit'] ?? 20);
            $limit = max(1, min(100, $limit));

            if ($query === '') {
                return json_encode([
                    'result' => false,
                    'message' => 'Parameter "query" is required.',
                ]);
            }

            $rows = DB::module('RAW')->q(function ($qb) use ($query, $limit) {
                $qb->raw(
                    'SELECT id, title, price FROM shop_items
                      WHERE title LIKE :q OR sku LIKE :q
                      ORDER BY id DESC LIMIT :lim',
                    ['q' => '%' . $query . '%', 'lim' => $limit]
                );
            })->all();

            return json_encode([
                'result' => true,
                'message' => 'Found ' . count($rows) . ' item(s).',
                'items' => $rows,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Logger::use()->error('Shop.Items.Search failed', ['msg' => $e->getMessage()]);
            return json_encode([
                'result' => false,
                'message' => 'Internal error while searching items.',
            ]);
        }
    }
}
```

### Business rules are still your job

DACore enforces the `rights` list. Everything else — ownership checks, quotas, referential integrity, whether this user may touch *this* record — must be validated inside your handler.

---

## 5. Refreshing the UI after a write tool

A write tool can ask the frontend to react by returning `ui_events`:

```php
return json_encode([
    'result' => true,
    'message' => 'Item updated.',
    'ui_events' => [
        [
            'name' => 'Shop.Items.Updated',      // ^[A-Za-z0-9_.:-]{1,120}$
            'payload' => ['id' => $id],
        ],
    ],
]);
```

`ui_events` are collected **only when `result` is truthy** and are forwarded to the chat frontend, where your page JS can listen for them.

---

## 6. Feeding extra context to the model

```php
DotApp::call('DACore:AI@addSystemContext', 'Shop', $contextArray): bool
```

Returns `false` when the module name is empty or no chat session is active.

Wire it to the chat lifecycle in `module.listeners.php`:

```php
DotApp::DotApp()->on("DACore.ai.chat.active", "Shop:AITools@addSystemContext");
DotApp::DotApp()->on("DACore.permissions.refresh", "Shop:AITools@addSystemContext");
```

```php
public static function addSystemContext()
{
    return DotApp::call('DACore:AI@addSystemContext', 'Shop', [
        'currency' => Config::module('Shop', 'currency') ?? 'EUR',
        'user_can_edit' => Auth::can(['dotapp.root', 'Shop.items.edit']),
    ]);
}
```

Keep it small — it is prepended to every prompt.

---

## 7. Uninstall

```php
foreach (['Shop.Items.Search', 'Shop.Items.Update'] as $toolid) {
    DotApp::call("DACore:AITools@delete", $toolid);
}
```

---

## 8. Mistakes to avoid

| Wrong | Right |
|-------|-------|
| `'rights' => []` | Explicit list — empty hides the tool completely |
| `'rights' => ['Shop.*']` | No wildcards for AI tools; list permissions |
| `controller` without `!` | `'Shop:AITools@method!'` (no DI) |
| Handler with one parameter | `function ($data, $aiobj)` |
| Returning a plain string / echoing | Return JSON with `result` and `message` |
| Letting exceptions escape | `try/catch` and return `result: false` |
| Putting permission names in `howtouse` | Rights belong in `rights` only |
| Tool `description` that echoes the ticket | Product copy operators can see ([05](05-VIEWS-TEMPLATES-ASSETS.md) §8) |
| `INSERT INTO dacore_ai_tools ...` | `DACore:AITools@register` |
| Registering tools in `module.init.php` | `Installation.php` |
| Trusting model-supplied ids blindly | Validate ownership and ranges |
| Expecting `dacore_ai_tools` to exist | Verify it — DACore 1.0.0 does not create it |
