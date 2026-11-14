# EX-02 — Secure edit form → API route

Page UI posts to an API path. The handler name is a stable string like `saveItem` — it must match `form(..., 'saveItem', ...)` in PHP.

## View / layout fragment

```html
<fo-rm
  action="/api/v1/auth/Shop/items/edit"
  method="post"
  id="itemEdit"
>
  <input type="hidden" name="id" value="{{ enc(Shop.item.id): $itemId }}" />
  <input type="text" name="title" value="{{ var: $title }}" required />

  {{ formName(saveItem) }}

  <button type="submit" id="itemSaveBtn">Save</button>
</fo-rm>

<script src="/assets/dotapp/dotapp.js"></script>
<script src="/assets/modules/Shop/js/item-edit.js"></script>
```

**MUST:** `{{ formName(saveItem) }}` is between `<fo-rm>` and `</fo-rm>` — never after `</fo-rm>`. `method` and `action` on the opening tag are literal, as in this sample. **MUST NOT** put `{{ }}` inside `<fo-rm …>`.

Use `{{ enc(context): $id }}` when IDs must not be forgeable in plain HTML. Decrypt in PHP with the **same** context string.

## JS

```javascript
(function () {
  function runMe($dotapp) {
    // About: request helper — copy shopReply / shopDone / shopFail / shopSend / shopForm
    // from AIRULES/26 §5 (full file: EX-06) here, once per module.

    // Section: Shop — item editor
    // About: save the item and redraw the list under the form.
    shopForm("#itemEdit", "#itemEditCard", function (reply) {
      if (reply.html) {
        $dotapp("#listInner").html(reply.html);
      }
    });
  }

  if (window.$dotapp) {
    runMe(window.$dotapp);
  } else {
    window.addEventListener(
      "dotapp",
      function () {
        runMe(window.$dotapp);
      },
      { once: true }
    );
  }
})();
```

## PHP

```php
Router::post('/api/v1/auth/Shop/items/edit', 'Shop:Items@save!', Router::STATIC_ROUTE);

/**
 * CRCchecking — #DACore:AuthTest@LoginAndCRC! (prefix /api/v1/auth/Shop/*). No crcCheck() here.
 *
 * Saves the edited item and returns the redrawn list.
 *
 * @param  mixed $request POST from <fo-rm id="itemEdit">.
 * @return mixed Reply::ok() or Reply::fail() — always HTTP 200.
 */
public static function save($request)
{
    try {
        $answer = $request->form(['POST'], 'saveItem', function ($request) {
            $data = $request->data(true)['data'] ?? [];
            $id = \Dotsystems\App\Parts\Crypto::decrypt((string) ($data['id'] ?? ''), 'Shop.item.id');
            if ($id === false) {                       // decrypt returns FALSE, not null
                return Reply::fail('This item link has expired. Reload the page and try again.');
            }
            // validate + DB update: each stop returns its own Reply::fail('…') — AIRULES/26 §7
            return Reply::ok('Item saved.', ['html' => self::rowsHtml()]);
        }, function ($request, $name) {
            // Why: without this callback a bad form signature throws.
            return Reply::fail('This form has expired. Reload the page and try again.');
        });
        if ($answer === false || $answer === null) {
            return Reply::fail('This form does not match its handler (saveItem). Reload the page and try again.');
        }
        return $answer;
    } catch (\Throwable $e) {
        self::reportCatch('Shop:Items@save', 'shop.item.update', $e, 'error');
        return Reply::fail('The item was not saved because of a server fault (shop.item.update). The fault was logged.');
    }
}
```

Payload fields live under `$request->data(true)['data']` after DotApp transport unwrap.
