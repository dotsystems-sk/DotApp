# EX-07 — Bridge (click → PHP)

## Template

```html
<input type="text" dotbridge="email" />
<button type="button"
  {{ dotbridge:on(click)="ping(email)" regenerateId oneTimeUse rateLimit(60,10) }}>
  Ping
</button>
<script src="/assets/dotapp/dotapp.js"></script>
```

## PHP (in module initialize or early controller load)

```php
$dotApp->bridge->fn('ping', function ($request) {
    $email = $request->data(true)['data']['email'] ?? '';
    return ['ok' => true, 'email' => $email];
});
```

## JS hooks (optional)

```javascript
$dotapp()
  .bridge('ping', 'click')
  .before(function (data, el) {})
  .after(function (body, el) {
    console.log(body);
  });
```

Still requires `/assets/dotapp/dotapp.js`. Prefer `fo-rm` + `formName` for full form posts; use bridge for discrete actions.
