# EX-02 — Secure edit form → API route

Pattern from real modules (e.g. FaceTerminal employee edit): page UI posts to an API path; handler name is a stable string like `upravazamestnanca`.

## View / layout fragment

```html
<fo-rm
  action="/api/v1/shop/items/edit"
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

Use `{{ enc(context): $id }}` when IDs must not be forgeable in plain HTML. Decrypt in PHP with the **same** context string.

## JS

```javascript
(function () {
  var runMe = function ($dotapp) {
    $dotapp()
      .form("#itemEdit")
      .before(function (data, form) {
        if ($dotapp(form).attr("blocked") == 1) return $dotapp().halt();
        $dotapp(form).attr("blocked", "1");
        $dotapp("#itemSaveBtn").attr("loading", "true").attr("loader", "dots");
      })
      .after(function (data, response, form) {
        var reply = $dotapp().parseReply(response);
        if (reply && reply.status == 1) {
          // toast / reload partial
        } else if (reply && reply.message) {
          alert(reply.message);
        }
        $dotapp(form).attr("blocked", "0");
        $dotapp("#itemSaveBtn").removeAttr("loading").removeAttr("loader");
      });
  };
  if (window.$dotapp) runMe(window.$dotapp);
  else window.addEventListener("dotapp", function () { runMe(window.$dotapp); }, { once: true });
})();
```

## PHP

```php
Router::post('/api/v1/shop/items/edit', 'Shop:Items@save!', Router::STATIC_ROUTE);

public static function save($request)
{
    if (!$request->crcCheck()) {
        return \Dotsystems\App\DotApp::DotApp()->ajaxReply(['status' => 0, 'message' => 'crc'], 400);
    }

    $answer = $request->form(['POST'], 'saveItem', function ($request) {
        $data = $request->data(true)['data'] ?? [];
        $id = \Dotsystems\App\DotApp::DotApp()->decrypt($data['id'] ?? '', 'Shop.item.id');
        if (!$id) {
            return ['code' => 200, 'body' => ['status' => 0, 'message' => 'Bad id']];
        }
        // DB update...
        return ['code' => 200, 'body' => ['status' => 1]];
    });

    return \Dotsystems\App\DotApp::DotApp()->ajaxReply($answer['body'], $answer['code']);
}
```

Payload fields live under `$request->data(true)['data']` after DotApp transport unwrap.
