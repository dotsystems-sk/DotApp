# EX-D03 — DACore AI tool end to end

Rules: [34](../34-DACORE-AI-TOOLS.md).

## 1. Register (in `Installation.php`)

```php
DotApp::call("DACore:AITools@register", "Shop.Items.Search", [
    'creator' => 'Shop',
    'description' => 'Search shop items by title or SKU. Read-only.',
    'controller' => 'Shop:AITools@itemsSearch!',
    'rights' => ['dotapp.root', 'Shop.administrator', 'Shop.items.view'],
    'helper' => 0,
    'tool_type' => 'lookup',
    'risk_level' => 0,
    'requires_confirmation' => false,
    'workflow' => 'Shop.Catalog',
    'intent_tags' => ['find item', 'search product', 'najdi produkt', 'hladaj tovar'],
    'allowed_tools' => ['Shop.Items.Update'],
    'forbidden_tools' => [],
    'howtouse' => [
        'purpose' => 'Find items so their id can be used by other tools.',
        'parameters' => [
            'query' => 'string, REQUIRED, fragment of title or SKU',
            'limit' => 'integer, optional, default 20, max 100',
        ],
        'returns' => 'JSON { result: bool, message: string, items: [{id, title, sku, price}] }',
        'examples' => ['{"query":"bolt"}', '{"query":"SKU-12","limit":5}'],
    ],
]);

DotApp::call("DACore:AITools@register", "Shop.Items.Update", [
    'creator' => 'Shop',
    'description' => 'Update the price of one shop item by id.',
    'controller' => 'Shop:AITools@itemUpdate!',
    'rights' => ['dotapp.root', 'Shop.administrator', 'Shop.items.edit'],
    'tool_type' => 'update',
    'risk_level' => 3,
    'requires_confirmation' => true,          // model asks the user first
    'workflow' => 'Shop.Catalog',
    'intent_tags' => ['change price', 'zmen cenu'],
    'howtouse' => [
        'parameters' => [
            'id' => 'integer, REQUIRED, item id from Shop.Items.Search',
            'price' => 'number, REQUIRED, new price, >= 0',
        ],
        'returns' => 'JSON { result: bool, message: string, ui_events: [...] }',
        'notes' => 'Call Shop.Items.Search first to resolve the id.',
    ],
]);
```

`rights` must never be empty (the tool would be hidden from everyone) and must not use wildcards.

## 2. Read tool

```php
<?php
namespace Dotsystems\App\Modules\Shop\Controllers;

use Dotsystems\App\Parts\DB;
use Dotsystems\App\Parts\Logger;

class AITools extends \Dotsystems\App\Parts\Controller
{
    public static function itemsSearch($data, $aiobj)
    {
        try {
            $query = trim((string) ($data['query'] ?? ''));
            $limit = max(1, min(100, (int) ($data['limit'] ?? 20)));

            if ($query === '') {
                return json_encode(['result' => false, 'message' => 'Parameter "query" is required.']);
            }

            $rows = DB::module('RAW')->q(function ($qb) use ($query, $limit) {
                $qb->raw(
                    'SELECT id, title, sku, price FROM shop_items
                      WHERE (title LIKE :q OR sku LIKE :q) AND active = 1
                      ORDER BY id DESC LIMIT :lim',
                    ['q' => '%' . $query . '%', 'lim' => $limit]
                );
            })->all();

            if (empty($rows)) {
                return json_encode(['result' => true, 'message' => 'No item matched "' . $query . '".', 'items' => []]);
            }

            return json_encode([
                'result' => true,
                'message' => 'Found ' . count($rows) . ' item(s).',
                'items' => $rows,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Logger::use()->error('Shop.Items.Search failed', ['msg' => $e->getMessage()]);
            return json_encode(['result' => false, 'message' => 'Internal error while searching items.']);
        }
    }
}
```

## 3. Write tool with validation and `ui_events`

```php
    public static function itemUpdate($data, $aiobj)
    {
        try {
            $id = (int) ($data['id'] ?? 0);
            $price = $data['price'] ?? null;

            if ($id <= 0) {
                return json_encode(['result' => false, 'message' => 'Parameter "id" must be a positive integer.']);
            }
            if (!is_numeric($price) || (float) $price < 0) {
                return json_encode(['result' => false, 'message' => 'Parameter "price" must be a number >= 0.']);
            }
            $price = round((float) $price, 2);

            // The model can hallucinate ids - verify existence.
            $rows = DB::module('RAW')->q(function ($qb) use ($id) {
                $qb->select(['id', 'title'])->from('shop_items')->where('id', '=', $id)->limit(1);
            })->all();
            $item = $rows[0] ?? null;

            if ($item === null) {
                return json_encode(['result' => false, 'message' => 'Item ' . $id . ' does not exist.']);
            }

            $affected = 0;
            DB::module('RAW')->q(function ($qb) use ($id, $price) {
                $qb->update('shop_items')->set(['price' => $price])->where('id', '=', $id);
            })->execute(
                function ($r, $db, $exec) use (&$affected) { $affected = $exec['affected_rows'] ?? 0; },
                function ($error) { Logger::use()->error('Shop.Items.Update db', $error); }
            );

            if ($affected === 0) {
                return json_encode(['result' => false, 'message' => 'Price was not changed.']);
            }

            return json_encode([
                'result' => true,
                'message' => 'Price of "' . $item['title'] . '" set to ' . $price . '.',
                'ui_events' => [
                    [
                        'name' => 'Shop.Items.Update', // MUST equal toolid
                        'payload' => ['id' => $id], // add page/filter when the list is paginated
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            Logger::use()->error('Shop.Items.Update failed', ['msg' => $e->getMessage()]);
            return json_encode(['result' => false, 'message' => 'Internal error while updating the item.']);
        }
    }
```

`ui_events` are forwarded only when `result` is truthy. `name` **MUST** equal the tool id (`Shop.Items.Update`). Payload: only what the **items list page** needs (id, page/filter). **MUST NOT** include secrets or keys. Lookup tools omit `ui_events`.

### Page JS (only on the items list — not on Users)

```javascript
window.addEventListener("DACore.AI.UIEvent", function (ev) {
  if (!ev || !ev.detail) return;
  if (ev.detail.name !== "Shop.Items.Update") return;
  var p = ev.detail.payload || {};
  if (!$dotapp("#shopItemsWrap").length) return;
  // overlay + $dotapp().load(...) using p.id / p.page — same as the page's own refresh
  // MUST NOT location.reload()
});
```

The users-admin script **MUST NOT** listen for `Shop.Items.Update`. If that file is not on the page, the event is ignored. See [34](../34-DACORE-AI-TOOLS.md) §5.

## 4. System context

```php
    public static function addSystemContext()
    {
        return \Dotsystems\App\DotApp::call('DACore:AI@addSystemContext', 'Shop', [
            'currency' => \Dotsystems\App\Parts\Config::module('Shop', 'currency') ?? 'EUR',
            'can_edit' => \Dotsystems\App\Parts\Auth::can(['dotapp.root', 'Shop.items.edit']),
            'hint' => 'Item prices are stored without VAT.',
        ]);
    }
```

Registered in `module.listeners.php`:

```php
$dotApp->on('DACore.ai.chat.active', 'Shop:AITools@addSystemContext');
$dotApp->on('DACore.permissions.refresh', 'Shop:AITools@addSystemContext');
```

Keep it short — it is prepended to every prompt.

## 5. Uninstall

```php
foreach (['Shop.Items.Search', 'Shop.Items.Update'] as $toolid) {
    DotApp::call("DACore:AITools@delete", $toolid);
}
```

## 6. Checklist

- [ ] `dacore_ai_tools` exists (DACore 1.0.0 does not create it)
- [ ] `rights` non-empty, no wildcards, includes `dotapp.root`
- [ ] `controller` string ends with `!`
- [ ] Handler signature `($data, $aiobj)`
- [ ] Always returns JSON with `result` and `message`
- [ ] `try/catch` around the whole body
- [ ] Model-supplied ids validated against the DB
- [ ] Write tools use `requires_confirmation => true` and a realistic `risk_level`
- [ ] Write tools that change on-screen admin data return `ui_events` (`name` = tool id); the matching page listens for `DACore.AI.UIEvent` and AJAX-refreshes — no `location.reload()`; other pages ignore the name
- [ ] `ui_events` payload has no secrets / keys
- [ ] Registration happens in `Installation.php`, not per request
