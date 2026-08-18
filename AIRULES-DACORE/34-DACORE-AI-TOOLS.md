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

## 5. Refresh the open page after a write tool (**MUST**)

When an AI tool **changes data that some admin screen already shows**, that screen **MUST** update **in place** (AJAX / `$dotapp().load()` / the same fetch the page already uses). **MUST NOT** `location.reload()`.

This is **MUST** wherever it improves UX (lists, calendars, detail panes, paginated tables). It is **not** a global refresh: the **open page** decides. If the operator is on **Users** and the assistant creates an **item**, the users page **MUST NOT** move. If that page’s script is not loaded, nothing happens — that is correct.

### Pipeline (do not invent another bus)

1. The tool JSON includes `ui_events` **only on success** (`result` truthy). DACore **drops** them when `result` is false.
2. The model **MUST NOT** mention `ui_events` in the chat. DACore forwards them on `POST …/dacore/api/v1/ai/chat/send`.
3. The chat widget dispatches **one** browser event on `window`:

`DACore.AI.UIEvent` — `ev.detail.name` (string), `ev.detail.payload` (object).

4. **Your** page JS listens, **filters by `name`**, then refreshes **only that widget**.

### When to send

| Send `ui_events` | Do not |
|------------------|--------|
| Create / update / delete / assign that a live admin view can show | Lookup / search / get-only tools |
| After the write actually committed | Validation failure, no-op, permission refusal |

One tool may return **several** events. Names: `^[A-Za-z0-9_.:-]{1,120}$`.

### Event `name` (**MUST** = tool id)

Use the **same** string as `toolid` (`Shop.Items.Update`, not `Shop.Items.Updated` or `refreshList`). The page whitelist matches the tool the assistant just ran.

### Payload (you design it — situation-specific)

Put **only** what that screen needs to show the right slice: record id, date, page/filter for pagination, employee id to highlight. Each screen is different; design the payload so the listener can land on the **correct page of a paginated list** or the **correct month**, then **re-fetch**. Do **not** ship a full HTML row in the payload unless that is already how the page patches.

**MUST NOT** put secrets in `payload`: encryption keys, API keys, passwords, tokens, CSRF material, connection strings. Do not dump extra PII. Numeric/string **ids** needed to find the row are fine **in JS memory**. If a value will be written into HTML (`data-*`, `<option value>`), **encrypt** it the usual way ([11](11-AUTH-AND-CRYPTO.md) §8) — do not treat this event as a way to leak plain ids into the DOM.

### Page JS (**MUST**)

Load this only on the screen that owns the data. Filter `detail.name`. Missing DOM / destroyed widget → return. Refresh with the **same** endpoint and overlay policy as a **manual** refresh on that screen (Notiflix / module overlay when that refresh already blocks; silent patch only when that screen already does).

```javascript
window.addEventListener("DACore.AI.UIEvent", function (ev) {
  if (!ev || !ev.detail) return;
  if (ev.detail.name !== "Shop.Items.Update") return;
  var p = ev.detail.payload || {};
  // p.id, p.page, … — then $dotapp().load(...) + patch html; no location.reload()
});
```

Copy-paste: [examples/EX-D03-dacore-ai-tool.md](examples/EX-D03-dacore-ai-tool.md).

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
| Write tool with no `ui_events` while an admin list/calendar shows that data | Return `ui_events` (name = tool id) + page listener on `DACore.AI.UIEvent` ([34](34-DACORE-AI-TOOLS.md) §5) |
| `location.reload()` after an AI write | `$dotapp().load()` / existing page fetch; filter by `detail.name` |
| Users page refreshing because an **item** tool fired | Listeners **MUST** ignore other `name`s; script only on the matching screen |
| Secrets / API keys / passwords in `payload` | Ids and view hints only — never keys |
| `INSERT INTO dacore_ai_tools ...` | `DACore:AITools@register` |
| Registering tools in `module.init.php` | `Installation.php` |
| Trusting model-supplied ids blindly | Validate ownership and ranges |
| Expecting `dacore_ai_tools` to exist | Verify it — DACore 1.0.0 does not create it |
